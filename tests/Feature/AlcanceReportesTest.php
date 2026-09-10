<?php

use App\Models\Area;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\ReporteFinancieroService;
use App\Services\Reporte\ReporteProduccionService;
use App\Services\Reporte\ResumenDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/**
 * Alcance por sucursal en reportes y dashboard (fase 5).
 *
 * Es donde más daño hace equivocarse. En un listado el acotado se nota —faltan
 * filas—; en un KPI o un gráfico no hay con qué comparar, así que un total
 * filtrado se lee como el de la empresa entera y nadie lo cuestiona. Hasta esta
 * fase los cuatro servicios ni siquiera recibían el usuario: un contador de una
 * sucursal veía la facturación consolidada.
 *
 * Lo que NO se acota (y por qué) también se fija acá: `material`, `compra` y
 * `historial_precio_material` no tienen `sucursal_id` — el inventario es de
 * toda la empresa.
 */
uses(RefreshDatabase::class);

/**
 * Dos sucursales, cada una con un pedido entregado, su cobro y su etapa de
 * producción cerrada. Los importes son distintos a propósito para que se vea
 * en el número cuál se coló.
 *
 * @return array{mia: Sucursal, ajena: Sucursal}
 */
function dosSucursalesConMovimiento(): array
{
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    montarMovimiento($mia, monto: 100, total: 1000);
    montarMovimiento($ajena, monto: 7000, total: 9000);

    return ['mia' => $mia, 'ajena' => $ajena];
}

/**
 * Todo se cuelga de `$sucursal` con `recycle()` en vez de dejar que las
 * cadenas de factory creen filas sueltas (detalle → cotizacion_detalle →
 * cotizacion → sucursal; seguimiento → empleado → sucursal, → área).
 *
 * Dos motivos, y el segundo es el que rompe:
 *
 * 1. Una sucursal fantasma falsearía el conteo del propio reporte.
 * 2. `AreaFactory` usa `fake()->unique()->randomElement()` sobre una lista de
 *    SIETE nombres: a la octava área revienta con "Maximum retries of 10000".
 *    `SucursalFactory` tiene el mismo problema con `city()`.
 */
function montarMovimiento(Sucursal $sucursal, float $monto, float $total): Pedido
{
    $cotizacion = Cotizacion::factory()->create([
        'sucursal_id' => $sucursal->id,
        'estado' => 'CONVERTIDA',
        'total' => $total,
        'fecha' => now()->toDateString(),
    ]);

    $pedido = Pedido::factory()->create([
        'cotizacion_id' => $cotizacion->id,
        'estado' => 'ENTREGADO',
        'total' => $total,
        'fecha_pedido' => now()->toDateString(),
        'fecha_entrega_estimada' => now()->toDateString(),
        'fecha_entrega_real' => now()->toDateString(),
    ]);

    Pago::factory()->create([
        'pedido_id' => $pedido->id,
        'monto' => $monto,
        'fecha_pago' => now()->toDateString(),
    ]);

    $detalle = PedidoDetalle::factory()
        ->recycle($cotizacion)
        ->create(['pedido_id' => $pedido->id]);

    PedidoSeguimiento::factory()
        ->recycle([$sucursal, areaDePrueba()])
        ->create([
            'pedido_detalle_id' => $detalle->id,
            'etapa' => 'DISENO',
            'fecha_inicio' => now()->subDays(2),
            'fecha_fin' => now()->subDay(),
        ]);

    return $pedido;
}

/** Un área única por prueba, reutilizada — ver el motivo 2 de arriba. */
function areaDePrueba(): Area
{
    return Area::query()->first() ?? Area::factory()->create();
}

/** Cuenta acotada a `$sucursal` por su ficha de empleado. */
function lectorDe(Sucursal $sucursal, string ...$permisos): User
{
    collect($permisos)->each(fn (string $p) => Permission::findOrCreate($p, 'web'));

    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->givePermissionTo($permisos);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

test('los KPIs del dashboard solo cuentan la sucursal del usuario', function () {
    ['mia' => $mia] = dosSucursalesConMovimiento();

    $datos = app(ResumenDashboardService::class)->resumen(lectorDe($mia));

    expect($datos['cotizaciones_mes'])->toBe(1)
        // Sin acotar serían 7.100: el cobro de la otra sucursal es 70x el propio.
        ->and($datos['ingresos_mes'])->toBe(100.0)
        ->and($datos['pedidos_por_etapa']['ENTREGADO'])->toBe(1)
        ->and($datos['entregas']['a_tiempo'])->toBe(1);
});

test('el dashboard de quien ve todas suma las dos sucursales', function () {
    dosSucursalesConMovimiento();

    $datos = app(ResumenDashboardService::class)->resumen(User::factory()->todasLasSucursales()->create());

    expect($datos['cotizaciones_mes'])->toBe(2)
        ->and($datos['ingresos_mes'])->toBe(7100.0);
});

test('el stock bajo NO se acota y por eso la pantalla lo rotula', function () {
    // `material` no tiene `sucursal_id`: el almacén es uno solo. El dato es
    // global a propósito y el KPI lleva el rótulo "Toda la empresa"
    // (`.stat-global` en Dashboard.vue), para que no se lea como los de al lado.
    ['mia' => $mia] = dosSucursalesConMovimiento();

    Material::factory()->create(['estado' => 'ACTIVO', 'stock_actual' => 1, 'stock_minimo' => 10]);

    $acotado = app(ResumenDashboardService::class)->resumen(lectorDe($mia));
    $global = app(ResumenDashboardService::class)->resumen(User::factory()->todasLasSucursales()->create());

    expect($acotado['materiales_bajo_stock'])->toBe($global['materiales_bajo_stock'])
        ->and($acotado['materiales_bajo_stock'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Reporte financiero
|--------------------------------------------------------------------------
*/

test('el reporte financiero no filtra la facturacion de otras sucursales', function () {
    // El caso que motivó la fase: un contador de una sucursal veía el
    // consolidado de la empresa.
    ['mia' => $mia] = dosSucursalesConMovimiento();

    $datos = app(ReporteFinancieroService::class)->datos(lectorDe($mia));

    expect($datos['total_vendido'])->toBe(1000.0)
        // Una sola sucursal en el desglose, y es la suya.
        ->and($datos['ventas_por_sucursal'])->toHaveCount(1)
        ->and($datos['ventas_por_sucursal'][0]['sucursal'])->toBe($mia->nombre)
        ->and($datos['rentabilidad'])->toHaveCount(1)
        // Cuentas por cobrar: 1000 del pedido − 100 cobrado.
        ->and($datos['cuentas_por_cobrar']['total'])->toBe(900.0);
});

test('el reporte financiero de quien ve todas sigue mostrando las dos', function () {
    dosSucursalesConMovimiento();

    $datos = app(ReporteFinancieroService::class)->datos(User::factory()->todasLasSucursales()->create());

    expect($datos['total_vendido'])->toBe(10000.0)
        ->and($datos['ventas_por_sucursal'])->toHaveCount(2)
        ->and($datos['cuentas_por_cobrar']['total'])->toBe(2900.0);
});

/*
|--------------------------------------------------------------------------
| Reporte de producción
|--------------------------------------------------------------------------
*/

test('produccion solo mide los pedidos y etapas de la sucursal', function () {
    // `pedido_seguimiento` es la cadena más larga del esquema:
    // seguimiento -> detalle -> pedido -> cotizacion.sucursal_id.
    ['mia' => $mia] = dosSucursalesConMovimiento();

    $datos = app(ReporteProduccionService::class)->datos(lectorDe($mia));

    $diseno = collect($datos['duracion_por_etapa'])->firstWhere('etapa', 'DISENO');

    expect($datos['cumplimiento']['entregados'])->toBe(1)
        ->and($diseno['muestras'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Inteligencia de negocios
|--------------------------------------------------------------------------
*/

test('BI acota los productos vendidos y la demanda, pero no el costo de materiales', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $producto = Producto::factory()->create(['nombre' => 'Bastidor']);

    foreach ([[$mia, 300.0], [$ajena, 5000.0]] as [$sucursal, $monto]) {
        $cotizacion = Cotizacion::factory()->aprobada()->create(['sucursal_id' => $sucursal->id]);
        CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create([
            'producto_id' => $producto->id, 'cantidad' => 1, 'subtotal' => $monto,
        ]);
        montarMovimiento($sucursal, monto: 10, total: 100);
    }

    $datos = app(InteligenciaNegociosService::class)->datos(lectorDe($mia));

    // Se afirma sobre el IMPORTE del producto, no sobre cuántos productos hay:
    // `PedidoDetalle::factory()` arrastra su propia `CotizacionDetalle` con un
    // producto aleatorio, así que el conteo no es estable ni interesante.
    $bastidor = collect($datos['productos_mas_vendidos'])->firstWhere('nombre', 'Bastidor');

    expect($bastidor['monto'])->toBe(300.0) // sin acotar serían 5.300
        ->and(collect($datos['demanda']['serie'])->sum('pedidos'))->toBe(1)
        ->and(collect($datos['demanda']['estacionalidad'])->sum('pedidos'))->toBe(1);

    // Y el historial de precios NO se acota: cuelga de `material`, que no tiene
    // sucursal. La pantalla lo rotula "Inventario · toda la empresa".
    $global = app(InteligenciaNegociosService::class)->datos(User::factory()->todasLasSucursales()->create());

    expect($datos['evolucion_costos'])->toBe($global['evolucion_costos']);
});

/*
|--------------------------------------------------------------------------
| Las pantallas reciben el alcance para poder rotularlo
|--------------------------------------------------------------------------
*/

test('las pantallas de reporte saben que alcance estan mostrando', function () {
    // Un total acotado y uno de toda la empresa se ven idénticos: el rótulo
    // (Components/EtiquetaAlcance.vue) es lo único que los distingue.
    ['mia' => $mia] = dosSucursalesConMovimiento();

    $usuario = lectorDe($mia, 'dashboard.ver', 'reportes.financiero');

    $this->actingAs($usuario)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.sucursales.ve_todas', false)
            ->where('auth.sucursales.visibles.0.nombre', $mia->nombre));

    $this->actingAs($usuario)->get(route('reportes.financiero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('datos.total_vendido', fn ($v) => (float) $v === 1000.0));
});
