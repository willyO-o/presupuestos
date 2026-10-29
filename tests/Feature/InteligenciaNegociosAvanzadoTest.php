<?php

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\HistorialPrecioMaterial;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleMaterial;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Reporte\AlertasNegocioService;
use App\Services\Reporte\CostosMaterialesService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\PresupuestosRentabilidadService;
use App\Services\Reporte\SimuladorDecisionesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Pestañas nuevas de Inteligencia de negocios: costos y materiales,
 * presupuestos y rentabilidad, alertas y simulador.
 *
 * Los servicios se prueban con un periodo FIJO (enero–abril 2026) para que las
 * series mensuales no dependan del día en que corre la suite.
 */
beforeEach(function () {
    $this->periodo = PeriodoReporte::desdeFiltros('2026-01-01', '2026-04-30');
    // Alcance TODAS: acá se prueba la matemática; el acotado tiene su test.
    $this->usuario = User::factory()->todasLasSucursales()->create();
});

/**
 * Un material con tres precios: 100 en enero, 110 en febrero, 121 en marzo.
 */
function materialConPrecios(string $nombre = 'Lona 13oz', array $precios = ['2026-01-05' => 100, '2026-02-05' => 110, '2026-03-05' => 121]): Material
{
    $material = Material::factory()->create(['nombre' => $nombre, 'precio_unitario' => end($precios)]);

    foreach ($precios as $fecha => $precio) {
        HistorialPrecioMaterial::factory()->create([
            'material_id' => $material->id,
            'precio_unitario' => $precio,
            'vigente_desde' => $fecha,
        ]);
    }

    return $material;
}

/*
|--------------------------------------------------------------------------
| Rutas
|--------------------------------------------------------------------------
*/

test('las pestanas nuevas del BI piden reportes.bi y reciben datos y filtros', function (string $ruta, string $componente, string $clave) {
    Permission::findOrCreate('reportes.bi', 'web');

    $this->actingAs(User::factory()->create())->get(route($ruta))->assertForbidden();

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('reportes.bi');

    $this->actingAs($usuario)
        ->get(route($ruta, ['desde' => '2026-01-01', 'hasta' => '2026-04-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component($componente)
            ->has("datos.{$clave}")
            ->where('datos.periodo.desde', '2026-01-01')
            ->where('datos.periodo.meses', 4)
            ->where('filters.desde', '2026-01-01'));
})->with([
    ['reportes.bi', 'Reportes/Bi', 'demanda'],
    ['reportes.bi.costos', 'Reportes/BiCostos', 'indice'],
    ['reportes.bi.rentabilidad', 'Reportes/BiRentabilidad', 'rentabilidad'],
    ['reportes.bi.alertas', 'Reportes/BiAlertas', 'alertas'],
    ['reportes.bi.simulador', 'Reportes/BiSimulador', 'base'],
]);

test('una fecha ilegible en la URL no revienta la pantalla', function () {
    Permission::findOrCreate('reportes.bi', 'web');
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('reportes.bi');

    $this->actingAs($usuario)
        ->get(route('reportes.bi.costos', ['desde' => 'ayer-noche', 'hasta' => '%%']))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Costos y materiales
|--------------------------------------------------------------------------
*/

test('el indice de costo arrastra el ultimo precio y proyecta la tendencia', function () {
    materialConPrecios();

    $indice = app(CostosMaterialesService::class)->datos($this->usuario, $this->periodo)['indice'];

    // Abril no tuvo compra: el precio "siguió igual" en 121.
    expect($indice['serie'])->toBe([100.0, 110.0, 121.0, 121.0])
        ->and($indice['variacion_periodo'])->toBe(21.0)
        ->and($indice['proyeccion'])->toHaveCount(3)
        ->and($indice['proyeccion'][0]['valor'])->toBeGreaterThan(121.0)
        ->and($indice['variacion_mensual'])->toBeGreaterThan(0.0);
});

test('el indice no se corre de mes cuando hoy es 29, 30 o 31', function () {
    // Con el día de hoy colado en la fecha, "febrero 31" desbordaba a marzo
    // y febrero mostraba el precio de marzo.
    $this->travelTo(Carbon::parse('2026-05-31 10:00'));
    materialConPrecios();

    $indice = app(CostosMaterialesService::class)->datos($this->usuario, $this->periodo)['indice'];

    expect($indice['serie'])->toBe([100.0, 110.0, 121.0, 121.0]);
});

test('el impacto de una variacion es la diferencia de precio por lo consumido', function () {
    $material = materialConPrecios();

    PedidoDetalleMaterial::factory()->create([
        'material_id' => $material->id,
        'cantidad_usada' => 10,
        'costo_real' => 1210,
        'created_at' => '2026-03-20 10:00:00',
    ]);
    // Fuera del periodo: no cuenta ni como consumo ni como impacto.
    PedidoDetalleMaterial::factory()->create([
        'material_id' => $material->id,
        'cantidad_usada' => 99,
        'costo_real' => 9999,
        'created_at' => '2026-06-01 10:00:00',
    ]);

    $datos = app(CostosMaterialesService::class)->datos($this->usuario, $this->periodo);
    $fila = collect($datos['impacto']['materiales'])->firstWhere('material', 'Lona 13oz');

    expect($fila['precio_inicio'])->toBe(100.0)
        ->and($fila['precio_fin'])->toBe(121.0)
        ->and($fila['variacion_pct'])->toBe(21.0)
        ->and($fila['impacto'])->toBe(210.0) // (121 − 100) × 10
        ->and($datos['consumo']['total'])->toBe(1210.0)
        ->and($datos['consumo']['serie'])->toBe([0.0, 0.0, 1210.0, 0.0]);
});

test('el consumo presupuestado sale de las hojas de costo de presupuestos aprobados', function () {
    $material = materialConPrecios();

    $cotizacion = Cotizacion::factory()->aprobada()->create(['fecha' => '2026-02-10']);
    $linea = CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create(['cantidad' => 3]);
    CotizacionDetalleItem::factory()->create([
        'cotizacion_detalle_id' => $linea->id,
        'material_id' => $material->id,
        'cantidad' => 2,
        'costo_unitario' => 110,
        'subtotal' => 220,
    ]);

    $consumo = app(CostosMaterialesService::class)->datos($this->usuario, $this->periodo)['consumo'];
    $fila = collect($consumo['por_material'])->firstWhere('material', 'Lona 13oz');

    expect($consumo['tiene_presupuestado'])->toBeTrue()
        ->and($fila['costo_presupuestado'])->toBe(660.0) // 220 por unidad × 3 unidades
        ->and($fila['costo_real'])->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Presupuestos y rentabilidad
|--------------------------------------------------------------------------
*/

test('la rentabilidad deja afuera los presupuestos sin hoja de costos', function () {
    Cotizacion::factory()->aprobada()->create([
        'fecha' => '2026-02-10', 'costo_ajustado' => 1000, 'utilidad_real' => 400, 'estado_margen' => 'VERDE', 'total' => 2000,
    ]);
    // Anterior al motor: costo 0. Si entrara, la rentabilidad sería infinita.
    Cotizacion::factory()->convertida()->create([
        'fecha' => '2026-03-10', 'costo_ajustado' => 0, 'utilidad_real' => 0, 'total' => 5000,
    ]);
    Cotizacion::factory()->create(['fecha' => '2026-03-15', 'estado' => 'PENDIENTE', 'total' => 300]);
    Cotizacion::factory()->rechazada()->create(['fecha' => '2026-04-01', 'total' => 800]);

    $datos = app(PresupuestosRentabilidadService::class)->datos($this->usuario, $this->periodo);

    expect($datos['rentabilidad']['rentabilidad_promedio'])->toBe(40.0)
        ->and($datos['rentabilidad']['analizados'])->toBe(1)
        ->and($datos['rentabilidad']['excluidos_sin_costo'])->toBe(1)
        ->and($datos['rentabilidad']['porcentaje'])->toBe([null, 40.0, null, null])
        ->and($datos['rentabilidad']['umbrales'])->toBe(['verde' => 30.0, 'amarillo' => 15.0])
        // 2 vendidas de 3 decididas: la pendiente no cuenta.
        ->and($datos['resumen']['tasa_conversion'])->toBe(66.7)
        ->and($datos['resumen']['emitidos'])->toBe(4)
        ->and($datos['pronostico']['monto_emitido'])->toBe([0.0, 2000.0, 5300.0, 800.0])
        ->and($datos['pronostico']['proyeccion_emitido'])->toHaveCount(3);
});

test('el mismo resultado se lee sobre el costo y sobre el precio sin IVA', function () {
    // Precio sin IVA 1.500 − 100 = 1.400; costo 1.000; resultado 280.
    Cotizacion::factory()->aprobada()->create([
        'fecha' => '2026-02-10', 'subtotal' => 1500, 'descuento' => 100, 'costo_ajustado' => 1000, 'utilidad_real' => 280,
    ]);

    $rentabilidad = app(PresupuestosRentabilidadService::class)->datos($this->usuario, $this->periodo)['rentabilidad'];

    expect($rentabilidad['rentabilidad_promedio'])->toBe(28.0)
        ->and($rentabilidad['margen_sobre_precio'])->toBe(20.0);
});

test('la tendencia compara precio y costo de los mismos presupuestos y sin IVA', function () {
    // Febrero: 1.000 − 100 de descuento = 900 sin IVA (el total con IVA es
    // 1.044), costo 600. El de 2.000 no tiene hoja de costos: no entra en
    // ninguna de las dos líneas.
    Cotizacion::factory()->create([
        'fecha' => '2026-02-10', 'subtotal' => 1000, 'descuento' => 100, 'total' => 1044, 'costo_ajustado' => 600,
    ]);
    Cotizacion::factory()->create([
        'fecha' => '2026-02-20', 'subtotal' => 2000, 'descuento' => 0, 'total' => 2260, 'costo_ajustado' => 0,
    ]);
    Cotizacion::factory()->rechazada()->create([
        'fecha' => '2026-03-05', 'subtotal' => 1500, 'descuento' => 0, 'total' => 1695, 'costo_ajustado' => 900,
    ]);

    $tendencia = app(PresupuestosRentabilidadService::class)->datos($this->usuario, $this->periodo)['tendencia'];

    // Antes: febrero salía (1.044 + 2.260) / 2 = 1.652 contra un costo de 600.
    expect($tendencia['ticket'])->toBe([null, 900.0, 1500.0, null])
        ->and($tendencia['costo'])->toBe([null, 600.0, 900.0, null]);
});

/*
|--------------------------------------------------------------------------
| Alertas
|--------------------------------------------------------------------------
*/

test('sin desvios no hay alertas', function () {
    materialConPrecios('Estable', ['2026-01-05' => 100, '2026-03-05' => 102]);

    $datos = app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo);

    expect($datos['alertas'])->toBe([])
        ->and($datos['resumen']['total'])->toBe(0);
});

test('alerta el aumento de costo de un material por encima del umbral', function () {
    materialConPrecios('Vinil', ['2026-01-05' => 100, '2026-03-05' => 120]);

    $alertas = collect(app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo)['alertas']);
    $alerta = $alertas->firstWhere('tipo', 'costo_material');

    expect($alerta)->not->toBeNull()
        ->and($alerta['severidad'])->toBe('alta') // 20 % = el doble del umbral de 10 %
        ->and($alerta['titulo'])->toContain('Vinil')
        ->and($alerta['valor'])->toBe(20.0);
});

test('alerta los presupuestos vivos con margen bajo pero no los ya convertidos', function () {
    $roja = Cotizacion::factory()->create([
        'fecha' => '2026-02-01', 'estado' => 'PENDIENTE', 'costo_ajustado' => 1000, 'utilidad_real' => 50, 'estado_margen' => 'ROJO',
    ]);
    Cotizacion::factory()->aprobada()->create([
        'fecha' => '2026-02-01', 'costo_ajustado' => 1000, 'utilidad_real' => 200, 'estado_margen' => 'AMARILLO',
    ]);
    Cotizacion::factory()->convertida()->create([
        'fecha' => '2026-02-01', 'costo_ajustado' => 1000, 'utilidad_real' => 50, 'estado_margen' => 'ROJO',
    ]);

    $alertas = collect(app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo)['alertas'])
        ->where('tipo', 'margen_bajo');

    expect($alertas)->toHaveCount(2)
        ->and($alertas->first()['severidad'])->toBe('alta')
        ->and($alertas->first()['url'])->toBe(route('cotizaciones.show', $roja))
        // Mismo nombre que en Presupuestos y rentabilidad, y la recomendación en minúsculas.
        ->and($alertas->first()['detalle'])->toContain('Resultado calculado Bs')
        ->and($alertas->first()['detalle'])->toContain('El semáforo recomienda: no aceptar.')
        // Lo que lee la fila de la pantalla: qué hacer, las cifras y por qué.
        ->and($alertas->first()['accion'])->toBe('Revisar precio antes de aceptar el presupuesto')
        ->and($alertas->first()['tipo_etiqueta'])->toBe('Rentabilidad sobre costo baja')
        ->and($alertas->first()['referencia'])->toBe($roja->codigo_verificacion)
        ->and(array_column($alertas->first()['metricas'], 'etiqueta'))->toBe(['Resultado calculado', 'Rentabilidad sobre costo'])
        ->and($alertas->first()['nota'])->toBe('Está por debajo del umbral de 15 % definido por el sistema.')
        ->and($alertas->first()['enlace'])->toBe('Ver presupuesto');
});

test('alerta un presupuesto pendiente cuyos materiales subieron y lo re-evalua con el motor', function () {
    $material = Material::factory()->create(['precio_unitario' => 13]);

    // Cotizado con el material a 10: 3 u. por pieza × 2 piezas = 60 de costo.
    $cotizacion = Cotizacion::factory()->create([
        'fecha' => '2026-03-01', 'estado' => 'PENDIENTE',
        'costo_base' => 60, 'costo_ajustado' => 60, 'subtotal' => 100, 'descuento' => 0,
        'utilidad_real' => 27.75, 'estado_margen' => 'VERDE',
    ]);
    $linea = CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create(['cantidad' => 2, 'factor_complejidad' => 1]);
    CotizacionDetalleItem::factory()->create([
        'cotizacion_detalle_id' => $linea->id, 'material_id' => $material->id,
        'cantidad' => 3, 'costo_unitario' => 10, 'subtotal' => 30,
    ]);

    $alerta = collect(app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo)['alertas'])
        ->firstWhere('tipo', 'presupuesto_desactualizado');

    // Costo nuevo 78 al mismo precio 100: utilidad (100 − 78 − 3) × 0,75 = 14,25
    // → 18 % del costo: AMARILLO.
    expect($alerta)->not->toBeNull()
        ->and($alerta['valor'])->toBe(30.0)
        ->and($alerta['detalle'])->toContain('14,25')
        ->and($alerta['detalle'])->toContain('VERDE → AMARILLO');
});

test('alerta el sobrecosto de material de un pedido frente a lo presupuestado', function () {
    $pedido = Pedido::factory()->create(['fecha_pedido' => '2026-02-15', 'estado' => 'ELABORACION']);
    $presupuestada = CotizacionDetalle::factory()->create(['costo_ajustado' => 100]);
    $detalle = PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'cotizacion_detalle_id' => $presupuestada->id]);
    PedidoDetalleMaterial::factory()->create(['pedido_detalle_id' => $detalle->id, 'costo_real' => 150]);

    $alerta = collect(app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo)['alertas'])
        ->firstWhere('tipo', 'sobrecosto_pedido');

    expect($alerta)->not->toBeNull()
        ->and($alerta['severidad'])->toBe('alta')
        ->and($alerta['valor'])->toBe(50.0)
        ->and($alerta['url'])->toBe(route('pedidos.show', $pedido))
        // La fila compara lo registrado con lo presupuestado en barras.
        ->and($alerta['accion'])->toBe('El material registrado superó el presupuesto en 50,0 %')
        ->and($alerta['comparacion'])->toBe([
            ['etiqueta' => 'Material registrado', 'valor' => 150.0, 'texto' => 'Bs 150,00'],
            ['etiqueta' => 'Material presupuestado', 'valor' => 100.0, 'texto' => 'Bs 100,00'],
        ])
        ->and($alerta['enlace'])->toBe('Ver pedido');
});

test('alerta la tendencia sostenida del costo de materiales', function () {
    materialConPrecios(); // +10 % por mes

    $alerta = collect(app(AlertasNegocioService::class)->datos($this->usuario, $this->periodo)['alertas'])
        ->firstWhere('tipo', 'tendencia_costos');

    expect($alerta)->not->toBeNull()
        ->and($alerta['valor'])->toBeGreaterThan(2.0);
});

/*
|--------------------------------------------------------------------------
| Simulador
|--------------------------------------------------------------------------
*/

test('el simulador parte del presupuesto promedio con hoja de costos del periodo', function () {
    Cotizacion::factory()->aprobada()->create(['fecha' => '2026-01-10', 'costo_ajustado' => 1000, 'subtotal' => 1600, 'descuento' => 100]);
    Cotizacion::factory()->convertida()->create(['fecha' => '2026-02-10', 'costo_ajustado' => 3000, 'subtotal' => 4500, 'descuento' => 0]);
    Cotizacion::factory()->aprobada()->create(['fecha' => '2026-02-11', 'costo_ajustado' => 0, 'subtotal' => 9999, 'descuento' => 0]);

    $datos = app(SimuladorDecisionesService::class)->datos($this->usuario, $this->periodo);

    expect($datos['base']['desde_datos'])->toBeTrue()
        ->and($datos['base']['presupuestos'])->toBe(2)
        ->and($datos['base']['costo'])->toBe(2000.0)
        ->and($datos['base']['precio'])->toBe(3000.0) // (1500 + 4500) / 2
        ->and($datos['base']['volumen_mensual'])->toBe(0.5)
        ->and($datos['config']['semaforo'])->toBe(config('margen.semaforo'));
});

test('sin datos el simulador ofrece una base de ejemplo con el margen sugerido', function () {
    $datos = app(SimuladorDecisionesService::class)->datos($this->usuario, $this->periodo);

    expect($datos['base']['desde_datos'])->toBeFalse()
        ->and($datos['base']['precio'])->toBe(round(1000 * (1 + config('cotizacion.margen_sugerido')), 2));
});

test('el simulador nombra la recomendacion del semaforo y no su color', function () {
    // Pedido del usuario (2026-10-29): "Aceptar" en vez de "zona verde".
    $pagina = File::get(resource_path('js/Pages/Reportes/BiSimulador.vue'));
    preg_match_all("/'bi-simulador\.[a-z-]+': \{.*?\n    \},/s", File::get(resource_path('js/Data/Ayudas.js')), $ayudas);

    expect($ayudas[0])->not->toBeEmpty()
        ->and($pagina.implode("\n", $ayudas[0]))->not->toMatch('/zona (verde|amarilla|roja)|\b(para|de|a) (verde|amarillo)\b/iu')
        // La recomendación sale del motor (textoRecomendacion), no se reescribe en la página.
        ->and($pagina)->toContain('textoRecomendacion')
        ->and($pagina)->toContain('según el sistema');
});

/*
|--------------------------------------------------------------------------
| Alcance por sucursal
|--------------------------------------------------------------------------
*/

test('consumo, rentabilidad y alertas de margen respetan la sucursal del usuario', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    Permission::findOrCreate('reportes.bi', 'web');
    $lector = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $lector->givePermissionTo('reportes.bi');
    Empleado::factory()->create(['user_id' => $lector->id, 'sucursal_id' => $mia->id]);
    $lector = $lector->fresh();

    foreach ([[$mia, 100.0], [$ajena, 7000.0]] as [$sucursal, $costo]) {
        $cotizacion = Cotizacion::factory()->create([
            'sucursal_id' => $sucursal->id, 'fecha' => '2026-02-01', 'estado' => 'PENDIENTE',
            'costo_ajustado' => 1000, 'utilidad_real' => 10, 'estado_margen' => 'ROJO',
        ]);
        $pedido = Pedido::factory()->create(['cotizacion_id' => $cotizacion->id, 'fecha_pedido' => '2026-02-02']);
        $detalle = PedidoDetalle::factory()->create([
            'pedido_id' => $pedido->id,
            'cotizacion_detalle_id' => CotizacionDetalle::factory()->for($cotizacion, 'cotizacion'),
        ]);
        PedidoDetalleMaterial::factory()->create([
            'pedido_detalle_id' => $detalle->id, 'costo_real' => $costo, 'created_at' => '2026-02-03 09:00:00',
        ]);
    }

    $consumo = app(CostosMaterialesService::class)->datos($lector, $this->periodo)['consumo'];
    $margenes = collect(app(AlertasNegocioService::class)->datos($lector, $this->periodo)['alertas'])
        ->where('tipo', 'margen_bajo');
    $resumen = app(PresupuestosRentabilidadService::class)->datos($lector, $this->periodo)['resumen'];

    expect($consumo['total'])->toBe(100.0) // sin acotar serían 7.100
        ->and($margenes)->toHaveCount(1)
        ->and($resumen['emitidos'])->toBe(1);
});
