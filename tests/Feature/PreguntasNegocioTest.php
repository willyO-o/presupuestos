<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\HistorialPrecioMaterial;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleMaterial;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Reporte\InformeProyeccionesCsv;
use App\Services\Reporte\InformeProyeccionesService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\PreguntasNegocioService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Preguntas para decidir (`/reportes/bi/preguntas`, y la sección del informe).
 *
 * Dos ventas del trimestre julio–septiembre, hechas para sacar las cuentas a
 * mano. Hoy es 20 de octubre de 2026.
 *
 * A) Banner (m²), Illimani, Daniel: Bs 1.000 sin descuento, costo 600, verde.
 *    Hoja por unidad: lona 200 + mano de obra 5 h × 20 = 100; 2 unidades.
 *    Pedido 2 × (2 m × 1 m) = 4 m², entregado; se usaron Bs 440 de lona
 *    (presupuestado 400: +10 %). Cobrado entero.
 * B) Letrero (unidad), Sajama, Alejandra: Bs 2.000 − 200 de descuento, costo
 *    1.500, rojo, precio puesto a mano (sugerido 1.500 × 1,45 = 2.175).
 *    Hoja: MDF 1.000 + otros 400 + mano de obra 4 h × 25 = 100. Pedido
 *    entregado; se usaron Bs 950 de MDF (−5 %). No pagó nada (75 días).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    config(['bi.preguntas.minimo_ventas' => 1]);

    $this->usuario = User::factory()->todasLasSucursales()->create();
    $this->periodo = PeriodoReporte::desdeFiltros('2026-07-01', '2026-09-30');

    $this->sucursal = Sucursal::factory()->create(['nombre' => 'El Alto']);
    $this->area = Area::factory()->create();
    $daniel = Empleado::factory()->recycle([$this->sucursal, $this->area])->create(['nombres' => 'Daniel', 'paterno' => 'Huarachi']);
    $alejandra = Empleado::factory()->recycle([$this->sucursal, $this->area])->create(['nombres' => 'Alejandra', 'paterno' => 'Villegas']);

    $lona = Material::factory()->create(['nombre' => 'Lona 13oz', 'unidad_medida' => 'M2']);
    $mdf = Material::factory()->create(['nombre' => 'MDF 9mm', 'unidad_medida' => 'UNIDAD']);
    HistorialPrecioMaterial::factory()->create(['material_id' => $lona->id, 'precio_unitario' => 20, 'vigente_desde' => '2026-07-01']);
    HistorialPrecioMaterial::factory()->create(['material_id' => $lona->id, 'precio_unitario' => 25, 'vigente_desde' => '2026-08-15']);

    $banner = Producto::factory()->create(['nombre' => 'Banner', 'unidad_medida' => 'M2']);
    $letrero = Producto::factory()->create(['nombre' => 'Letrero', 'unidad_medida' => 'UNIDAD']);

    // A) Banner.
    $a = venta($daniel, 'Distribuidora Illimani', [
        'fecha' => '2026-07-10', 'estado' => 'APROBADA', 'subtotal' => 1000, 'descuento' => 0, 'costo_base' => 600, 'costo_ajustado' => 600, 'estado_margen' => 'VERDE',
    ]);
    $lineaA = CotizacionDetalle::factory()->for($a, 'cotizacion')->create([
        'producto_id' => $banner->id, 'cantidad' => 2, 'precio_unitario' => 500, 'subtotal' => 1000,
        'costo_base' => 600, 'costo_ajustado' => 600, 'margen_aplicado' => 0.45, 'precio_manual' => 'NO',
    ]);
    item($lineaA, 'MATERIAL', 10, 20, $lona);
    item($lineaA, 'MANO_OBRA', 5, 20);
    $pedidoA = Pedido::factory()->create(['cotizacion_id' => $a->id, 'fecha_pedido' => '2026-07-12', 'total' => 1130, 'estado' => 'ENTREGADO']);
    $detalleA = PedidoDetalle::factory()->create(['pedido_id' => $pedidoA->id, 'cotizacion_detalle_id' => $lineaA->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 2, 'estado_item' => 'ENTREGADO']);
    PedidoDetalleMaterial::factory()->create(['pedido_detalle_id' => $detalleA->id, 'material_id' => $lona->id, 'cantidad_usada' => 17.6, 'costo_real' => 440, 'created_at' => '2026-07-20']);
    Pago::factory()->create(['pedido_id' => $pedidoA->id, 'monto' => 1130, 'fecha_pago' => '2026-07-25']);

    // B) Letrero.
    $b = venta($alejandra, 'Farmacias Sajama', [
        'fecha' => '2026-08-05', 'estado' => 'CONVERTIDA', 'subtotal' => 2000, 'descuento' => 200, 'costo_base' => 1500, 'costo_ajustado' => 1500, 'estado_margen' => 'ROJO',
    ]);
    $lineaB = CotizacionDetalle::factory()->for($b, 'cotizacion')->create([
        'producto_id' => $letrero->id, 'cantidad' => 1, 'precio_unitario' => 2000, 'subtotal' => 2000,
        'costo_base' => 1500, 'costo_ajustado' => 1500, 'margen_aplicado' => 0.45, 'precio_manual' => 'SI',
    ]);
    item($lineaB, 'MATERIAL', 1, 1000, $mdf);
    item($lineaB, 'OTRO', 1, 400);
    item($lineaB, 'MANO_OBRA', 4, 25);
    $pedidoB = Pedido::factory()->create(['cotizacion_id' => $b->id, 'fecha_pedido' => '2026-08-06', 'total' => 2034, 'estado' => 'ENTREGADO']);
    $detalleB = PedidoDetalle::factory()->create(['pedido_id' => $pedidoB->id, 'cotizacion_detalle_id' => $lineaB->id, 'ancho' => null, 'alto' => null, 'cantidad' => 1, 'estado_item' => 'ENTREGADO']);
    PedidoDetalleMaterial::factory()->create(['pedido_detalle_id' => $detalleB->id, 'material_id' => $mdf->id, 'cantidad_usada' => 1, 'costo_real' => 950, 'created_at' => '2026-08-10']);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<string, mixed>  $atributos
 */
function venta(Empleado $vendedor, string $cliente, array $atributos): Cotizacion
{
    return Cotizacion::factory()->recycle([test()->sucursal, test()->area])->create([
        'cliente_id' => Cliente::factory()->create(['razon_social' => $cliente])->id,
        'empleado_id' => $vendedor->id,
        'sucursal_id' => test()->sucursal->id,
        ...$atributos,
    ]);
}

function item(CotizacionDetalle $linea, string $tipo, float $cantidad, float $costoUnitario, ?Material $material = null): void
{
    CotizacionDetalleItem::factory()->create([
        'cotizacion_detalle_id' => $linea->id,
        'material_id' => $material?->id,
        'tipo' => $tipo,
        'unidad' => $tipo === 'MANO_OBRA' ? 'HORA' : 'UNIDAD',
        'cantidad' => $cantidad,
        'costo_unitario' => $costoUnitario,
        'subtotal' => $cantidad * $costoUnitario,
    ]);
}

/**
 * @return array<string, mixed>
 */
function preguntaDe(string $clave, ?User $usuario = null): array
{
    $datos = app(PreguntasNegocioService::class)->datos($usuario ?? test()->usuario, test()->periodo);

    return collect($datos['preguntas'])->firstWhere('clave', $clave);
}

/*
|--------------------------------------------------------------------------
| Pantalla
|--------------------------------------------------------------------------
*/

test('la pestana pide el permiso del BI y trae las once preguntas en orden', function () {
    Permission::findOrCreate('reportes.bi', 'web');
    $conPermiso = User::factory()->todasLasSucursales()->create();
    $conPermiso->givePermissionTo('reportes.bi');

    $this->actingAs(User::factory()->create())->get(route('reportes.bi.preguntas'))->assertForbidden();

    $this->actingAs($conPermiso)->get(route('reportes.bi.preguntas', ['desde' => '2026-07-01', 'hasta' => '2026-09-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reportes/BiPreguntas')
            ->has('datos.preguntas', 11)
            ->where('datos.preguntas.0.clave', 'costo_producto')
            ->where('datos.preguntas.10.clave', 'decisiones')
            ->where('datos.grupos', PreguntasNegocioService::GRUPOS));
});

/*
|--------------------------------------------------------------------------
| Costos
|--------------------------------------------------------------------------
*/

test('el costo calculado por producto es material registrado mas el resto de la hoja, por unidad de venta', function () {
    $pregunta = preguntaDe('costo_producto');

    // Letrero: 950 de MDF + 500 de otros y mano de obra, por unidad.
    // Banner: (440 + 2 × 100) / 4 m² = 160 por m².
    expect($pregunta['tabla']['filas'])->toBe([
        ['Letrero', '1 / unidad', 950.0, 1000.0, 500.0, 1450.0],
        ['Banner', '1 / m²', 110.0, 100.0, 50.0, 160.0],
    ])
        ->and($pregunta['respuesta'])->toBe('Para Letrero, el costo calculado es Bs 1.450,00 por unidad: Bs 950,00 de material registrado y Bs 500,00 de mano de obra y otros presupuestados. El material queda 5,0 % por debajo de lo presupuestado.')
        ->and($pregunta['base'])->toBe('2 de 2 trabajos entregados (100,0 %) tienen consumo registrado y hoja de costos. Importes por unidad, en Bs.')
        ->and($pregunta['aviso'])->toContain('no se registran horas reales')
        ->and($pregunta['interpretacion'])->toStartWith('No es un costo totalmente real')
        // Para la cuenta "material + obra y otros = costo" de la pantalla.
        ->and($pregunta['cifras'])->toMatchArray(['producto' => 'Letrero', 'unidad' => 'unidad', 'material' => 950.0, 'obra' => 500.0, 'total' => 1450.0]);
});

test('los materiales que suben: precio, costo adicional por lo consumido y la hora de mano de obra', function () {
    $pregunta = preguntaDe('costos_suben');

    // Lona de 20 a 25 (+25 %) con 17,6 m² usados: Bs 88 de más.
    expect($pregunta['tabla']['filas'][0])->toBe(['Lona 13oz', 20.0, 25.0, 25.0, 88.0])
        ->and(array_column($pregunta['tabla']['columnas'], 'titulo'))->toBe(['Material', 'Precio anterior', 'Precio actual', 'Aumento', 'Costo adicional'])
        ->and($pregunta['pregunta'])->toBe('¿Qué materiales están subiendo de precio?')
        ->and($pregunta['respuesta'])->toBe('1 material aumentó de precio y ninguno bajó. El costo adicional calculado es Bs 88 y corresponde a Lona 13oz, con un aumento de 25,0 %.')
        // La tendencia de precios y la mano de obra van a "Cómo interpretar".
        ->and($pregunta['interpretacion'])->toStartWith('El costo adicional se calcula como aumento del precio × cantidad consumida')
        ->and($pregunta['interpretacion'])->toContain('La hora de mano de obra presupuestada pasó de Bs 20,00 a Bs 25,00 (+25,0 %).')
        ->and($pregunta['acciones'][0]['texto'])->toBe('Negociar el precio de Lona 13oz o comparar proveedores.')
        ->and($pregunta['acciones'][0]['impacto'])->toBe(88.0)
        // Para los indicadores de la pantalla y la portada del informe.
        ->and($pregunta['cifras'])->toBe(['materiales' => 1, 'costo_adicional' => 88.0, 'mayor_material' => 'Lona 13oz', 'mayor_aumento' => 25.0, 'mayor_costo' => 88.0]);
});

test('sin materiales que suban la pregunta 2 lo dice y no muestra indicadores', function () {
    HistorialPrecioMaterial::query()->where('precio_unitario', 25)->delete();

    $pregunta = preguntaDe('costos_suben');

    expect($pregunta['respuesta'])->toStartWith('Ningún material subió de precio en el periodo.')
        ->and($pregunta['tabla']['filas'])->toBe([])
        ->and($pregunta['cifras'])->toBe([]);
});

test('el material registrado contra el previsto compara los mismos trabajos y nombra el que se pasa', function () {
    $pregunta = preguntaDe('gasto_presupuesto');

    // 1.390 registrados contra 1.400 previstos: en general no; el banner sí.
    expect($pregunta['respuesta'])->toBe('En general no. En trabajos comparables, el material registrado suma Bs 1.390 frente a Bs 1.400 previstos: 0,7 % menos. De 2 trabajos, 1 superó el umbral de exceso definido por el sistema.')
        ->and($pregunta['tabla']['filas'][0])->toBe(['Banner', '1 / 1', 400.0, 440.0, 40.0, 10.0])
        ->and(array_column($pregunta['acciones'], 'texto'))->toBe([
            'Revisar la hoja de costos de Banner: Bs 40 adicionales (+10,0 %).',
            'Revisar cortes, sobrantes y cantidades previstas de Lona 13oz: consumo 10,0 % mayor al previsto.',
        ])
        ->and(array_column($pregunta['acciones'], 'impacto'))->toBe([40.0, 40.0]);
});

test('un trabajo todavia en produccion no entra a la comparacion', function () {
    PedidoDetalle::query()->where('estado_item', 'ENTREGADO')->first()->update(['estado_item' => 'ACABADO']);

    expect(preguntaDe('gasto_presupuesto')['base'])->toStartWith('1 trabajo entregado con consumo registrado');
});

test('los materiales que se llevan el gasto, hasta cruzar el 80 por ciento', function () {
    $pregunta = preguntaDe('materiales_pesan');

    expect($pregunta['tabla']['filas'])->toBe([
        ['MDF 9mm', 950.0, 68.3, 68.3],
        ['Lona 13oz', 440.0, 31.7, 100.0],
    ])
        ->and($pregunta['respuesta'])->toBe('2 de 2 materiales concentran el 100,0 % del gasto registrado: MDF 9mm y Lona 13oz.')
        ->and($pregunta['acciones'][0]['titulo'])->toBe('Evaluar rebaja de 5 % en dos materiales principales')
        // 5 % de 1.390.
        ->and($pregunta['acciones'][0]['impacto'])->toBe(69.5);
});

/*
|--------------------------------------------------------------------------
| Ganancias
|--------------------------------------------------------------------------
*/

test('productos por resultado bruto en bolivianos, con el descuento prorrateado', function () {
    $pregunta = preguntaDe('productos_rentables');

    // Banner 1.000 − 600 = 400 (40 %); letrero 1.800 − 1.500 = 300 (16,7 %).
    expect($pregunta['tabla']['filas'])->toBe([
        ['Banner', 1, 1000.0, 400.0, 40.0],
        ['Letrero', 1, 1800.0, 300.0, 16.7],
    ])
        ->and($pregunta['respuesta'])->toBe('Banner aporta el mayor resultado bruto calculado: Bs 400, con margen de 40,0 %. También presenta el mayor margen entre los productos analizados.')
        ->and($pregunta['interpretacion'])->toStartWith('Resultado bruto = ingreso sin IVA')
        // Margen promedio 25 %: llevar el letrero ahí son 1.800 × 8,3 %.
        ->and($pregunta['acciones'][0]['impacto'])->toBe(149.4);
});

test('el cliente que mas compra no es el que mas deja', function () {
    $pregunta = preguntaDe('clientes_rentables');

    expect($pregunta['respuesta'])->toBe('Distribuidora Illimani aporta el mayor resultado bruto calculado: Bs 400, con margen de 40,0 %. El cliente con más compras, Farmacias Sajama, ocupa el 2.º lugar en resultado: su margen es 16,7 %.')
        ->and($pregunta['acciones'][0]['texto'])->toStartWith('Revisar las condiciones comerciales de Farmacias Sajama');
});

test('lo que cuestan los descuentos y el precio puesto a mano', function () {
    $pregunta = preguntaDe('descuentos');

    expect($pregunta['respuesta'])->toBe('Se otorgaron Bs 200 en descuentos en 1 de 2 ventas, con descuento promedio de 10,0 %. El margen fue 16,7 % en ventas con descuento y 40,0 % en las demás. Además, 1 línea con precio manual quedó Bs 175 por debajo del sugerido.')
        ->and(array_column($pregunta['acciones'], 'texto'))->toContain('Revisar la línea con precio manual y su diferencia de Bs 175.')
        ->and($pregunta['tabla']['filas'])->toBe([['Alejandra Villegas', 1, 200.0, 10.0, 16.7]])
        ->and(collect($pregunta['acciones'])->firstWhere('impacto', 175.0))->not->toBeNull();
});

test('las ventas con margen bajo, por vendedor', function () {
    $pregunta = preguntaDe('margen_bajo');

    expect($pregunta['respuesta'])->toBe('1 de 2 ventas (50,0 %) quedó con margen bajo: 1 en rojo, por Bs 1.800.')
        ->and($pregunta['tabla']['filas'])->toBe([['Alejandra Villegas', 1, 0, 1, 1800.0]])
        ->and($pregunta['acciones'][0]['impacto'])->toBe(1800.0)
        // Rojo no es pérdida: lo dice "Cómo interpretar" con la cifra.
        ->and($pregunta['interpretacion'])->toContain('Los Bs 1.800 son ventas en rojo, no una pérdida por ese importe.')
        ->and($pregunta['cifras'])->toBe(['ventas' => 2, 'rojas' => 1, 'vendido_rojo' => 1800.0]);
});

/*
|--------------------------------------------------------------------------
| Escenarios
|--------------------------------------------------------------------------
*/

test('si sube la materia prima el precio sube solo en la parte del material', function () {
    $pregunta = preguntaDe('materia_prima');

    // Material 1.400 de 2.100 de costo base (2/3). +10 % → costo 2.240,
    // margen de 25 % a 20 %, se pierden 140 y alcanza con subir 6,7 %.
    expect(array_slice($pregunta['tabla']['filas'], 0, 3))->toBe([
        ['Situación actual', 25.0, 0.0, 0.0],
        ['Materiales +5 %', 22.5, 70.0, 3.3],
        ['Materiales +10 %', 20.0, 140.0, 6.7],
    ])
        ->and($pregunta['respuesta'])->toBe('Con un aumento de 10 % en materiales, manteniendo los demás supuestos, el margen bruto calculado bajaría de 25,0 % a 20,0 %. El resultado sería Bs 140 menor. Para mantener el margen, el ajuste de precios estimado es de 6,7 %.')
        // Es una hipótesis: no compite en "Decisiones".
        ->and($pregunta['acciones'][0]['impacto'])->toBeNull();
});

test('la cobranza cuenta lo pendiente por antiguedad y los entregados sin pago', function () {
    $pregunta = preguntaDe('cobranza');

    expect($pregunta['respuesta'])->toBe('Se cobró el 35,7 % del importe analizado. Quedan Bs 2.034 en 1 pedido.')
        ->and($pregunta['tabla']['filas'])->toBe([
            ['0 a 30 días', 0, 0.0, 0.0],
            ['31 a 60 días', 0, 0.0, 0.0],
            ['61 a 90 días', 1, 2034.0, 100.0],
            ['Más de 90 días', 0, 0.0, 0.0],
        ])
        ->and(array_column($pregunta['acciones'], 'texto'))->toBe([
            'Dar seguimiento a Farmacias Sajama, con Bs 2.034 pendientes.',
            'Evaluar anticipos: 1 pedido se entregó sin pago.',
        ]);
});

/*
|--------------------------------------------------------------------------
| Decisiones
|--------------------------------------------------------------------------
*/

test('las acciones juntan las que tienen dinero en juego, de mayor a menor', function () {
    $pregunta = preguntaDe('decisiones');
    $montos = array_column($pregunta['tabla']['filas'], 2);

    expect($montos)->toBe([1800.0, 175.0, 149.4, 149.4, 88.0, 69.5, 40.0, 40.0])
        ->and($pregunta['pregunta'])->toBe('¿Qué acciones conviene revisar primero?')
        ->and($pregunta['respuesta'])->toBe('Se identifican 8 acciones, ordenadas por monto involucrado. Los montos representan conceptos distintos y no deben sumarse ni interpretarse como ahorro garantizado.')
        ->and($pregunta['aviso'])->toBe('Los montos tienen significados distintos y algunas acciones se superponen. No deben sumarse ni interpretarse como ahorro garantizado.');
});

test('cada accion dice que representa su monto y de que pregunta sale', function () {
    // Pedido del usuario (2026-10-29): que la pregunta 11 se relacione con
    // los datos que originan cada acción, como en su diseño.
    $pregunta = preguntaDe('decisiones');

    expect($pregunta['tabla']['filas'][0])->toBe(['Revisar aprobación de ventas en rojo', 'Ventas con margen bajo', 1800.0, 'Pregunta 8'])
        ->and(array_column($pregunta['tabla']['filas'], 1))->toBe([
            'Ventas con margen bajo',
            'Diferencia frente al sugerido',
            'Mejora estimada del resultado',
            'Mejora estimada del resultado',
            'Costo adicional calculado',
            'Ahorro estimado',
            'Diferencia frente al presupuesto',
            'Exceso de material registrado',
        ])
        ->and(array_column($pregunta['tabla']['filas'], 3))->toBe(['Pregunta 8', 'Pregunta 7', 'Pregunta 5', 'Pregunta 6', 'Pregunta 2', 'Pregunta 4', 'Pregunta 3', 'Pregunta 3'])
        ->and(array_column($pregunta['tabla']['filas'], 0))->toContain('Negociar Lona 13oz o evaluar proveedor', 'Evaluar rebaja de 5 % en dos materiales principales', 'Revisar cortes y sobrantes de Lona 13oz')
        // La pantalla enlaza por clave y colorea por tipo; conserva la frase completa.
        ->and($pregunta['priorizadas'][0])->toMatchArray([
            'origen' => 'margen_bajo',
            'numero' => 8,
            'tipo' => 'riesgo',
            'texto' => 'Solicitar revisión de gerencia antes de aceptar ventas clasificadas en rojo.',
        ])
        ->and(array_values(array_unique(array_column($pregunta['priorizadas'], 'tipo'))))->toEqualCanonicalizing(['riesgo', 'diferencia', 'estimado', 'observado'])
        // Su única acción propia: por dónde empezar, según los frentes de las primeras.
        ->and(array_column($pregunta['acciones'], 'texto'))->toBe([
            'Comenzar por revisar las autorizaciones comerciales. Después, evaluar los precios con margen por debajo del promedio y contrastar los precios y consumos de los materiales de mayor impacto.',
        ]);
});

test('escenarios y cobros exponen sus cifras para la lectura rapida de las acciones', function () {
    expect(preguntaDe('materia_prima')['cifras'])->toBe(['subida' => 10, 'margen_hoy' => 25.0, 'margen' => 20.0, 'reduccion' => 140.0])
        // Pedidos de 1.130 (cobrado) y 2.034 (sin pagos, 75 días).
        // `pedidos` son los que tienen saldo (no los dos del periodo).
        ->and(preguntaDe('cobranza')['cifras'])->toBe(['por_cobrar' => 2034.0, 'pedidos' => 1, 'cobrado' => 35.7, 'antiguo' => 0.0, 'dias' => 90]);
});

test('sin datos cada pregunta lo dice y no inventa acciones', function () {
    $vacio = app(PreguntasNegocioService::class)->datos($this->usuario, PeriodoReporte::desdeFiltros('2025-01-01', '2025-03-31'));
    $porClave = collect($vacio['preguntas'])->keyBy('clave');

    expect($porClave['costo_producto']['respuesta'])->toBe('No hay trabajos entregados con consumo de material registrado en el periodo.')
        ->and($porClave['cobranza']['respuesta'])->toBe('No hay pedidos en el periodo.')
        ->and($porClave['decisiones']['respuesta'])->toBe('Con los datos del periodo no surge ninguna acción con un monto involucrado.')
        ->and(collect($vacio['preguntas'])->flatMap(fn (array $p): array => $p['acciones'])->all())->toBe([]);
});

test('respeta las sucursales del usuario', function () {
    $acotado = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    Empleado::factory()->recycle([Sucursal::factory()->create(), $this->area])->create(['user_id' => $acotado->id]);

    expect(preguntaDe('productos_rentables', $acotado->fresh())['tabla']['filas'])->toBe([])
        ->and(preguntaDe('cobranza', $acotado->fresh())['respuesta'])->toBe('No hay pedidos en el periodo.');
});

/*
|--------------------------------------------------------------------------
| Informe
|--------------------------------------------------------------------------
*/

test('el informe PDF y el CSV traen las preguntas con sus respuestas', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['preguntas']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));
    $csv = InformeProyeccionesCsv::generar($informe);

    // Forma del informe modelo del usuario (2026-10-29): resumen para la
    // gerencia y una hoja por pregunta con sus cinco bloques.
    expect($texto)->toContain('RESUMEN PARA LA GERENCIA')
        ->toContain('Preguntas para decidir')
        ->toContain('Qué conviene revisar primero')
        ->toContain('1 venta está en rojo, por Bs 1.800.')
        ->toContain('Cómo está organizado')
        ->toContain('Antes de comparar cifras')
        ->toContain('PREGUNTA 1 DE 11')
        ->toContain('¿Cuál es el costo calculado por producto?')
        ->toContain('Respuesta breve')
        ->toContain('Datos que respaldan la respuesta')
        ->toContain('Acción sugerida')
        ->toContain('Cómo interpretar el resultado')
        ->toContain('Datos utilizados')
        ->toContain('PREGUNTA 11 DE 11')
        ->toContain('¿Qué acciones conviene revisar primero?')
        // Sin la portada de estimaciones ni una ficha vacía en el anexo.
        ->not->toContain('En pocas palabras')
        ->not->toContain('Ficha de cada estimación')
        // fputcsv entrecomilla los textos con espacios; los números van solos.
        ->and($csv)->toContain('"Pregunta 9";"¿Qué cambiaría si aumenta el precio de los materiales?"')
        ->and($csv)->toContain('"Materiales +10 %";20;140;6,7')
        ->and($csv)->toContain('"Revisar aprobación de ventas en rojo";"Ventas con margen bajo";1800;"Pregunta 8"')
        ->and($csv)->toContain('"Cómo interpretar";"Rojo no significa necesariamente pérdida')
        // La acción sale una vez como "Qué hacer" (en su pregunta), no otra vez en la 11.
        ->and(substr_count($csv, '"Qué hacer";"Solicitar revisión de gerencia'))->toBe(1);
});

test('la tabla de rentabilidad incluye a quien se nombra aunque no este entre los 5 primeros', function () {
    // Pedido del usuario: "Qué hacer" recomendaba revisar un producto que no
    // aparecía en la tabla (estaba en el puesto 6 a 10).
    $fila = fn (string $nombre, float $ganancia): array => [
        'nombre' => $nombre, 'ventas' => 3, 'ingreso' => $ganancia * 2, 'ganancia' => $ganancia, 'margen' => 50.0,
    ];
    $porGanancia = collect([
        $fila('A', 900), $fila('B', 800), $fila('C', 700), $fila('D', 600), $fila('E', 500),
        $fila('F', 400), $fila('Banner roll-up', 300), $fila('H', 200),
    ]);

    $filas = (fn () => $this->filasConMencionados($porGanancia, [$porGanancia[0], null, $porGanancia[6]]))
        ->call(app(PreguntasNegocioService::class));

    expect(array_column($filas, 0))->toBe(['A', 'B', 'C', 'D', 'E', 'Banner roll-up']);
});
