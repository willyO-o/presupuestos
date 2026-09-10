<?php

use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\OrdenCompraCliente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SeguimientoPostventa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/**
 * Cableado del alcance por sucursal en los módulos (fase 3).
 *
 * `AlcanceSucursalTest` prueba la REGLA (el trait y el scope). Esto prueba que
 * cada controlador la haya ENCHUFADO: es lo que se olvida, y cuando se olvida
 * no falla nada — simplemente se ve de más.
 *
 * Cada prueba arma dos sucursales con una cadena completa de datos
 * (cotización → pedido → nota / pago / OC / postventa) y una cuenta que solo
 * administra la primera. Los tests de cada módulo NO sirven para esto: usan
 * cuentas con alcance TODAS a propósito, para comprobar el módulo y no el
 * acotado.
 */
uses(RefreshDatabase::class);

/**
 * Cadena completa de documentos colgando de una cotización de `$sucursal`.
 *
 * @return array{cotizacion: Cotizacion, pedido: Pedido, nota: NotaEntrega, pago: Pago, oc: OrdenCompraCliente, postventa: SeguimientoPostventa}
 */
function cadenaDeSucursal(Sucursal $sucursal): array
{
    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $sucursal->id]);
    $pedido = Pedido::factory()->create(['cotizacion_id' => $cotizacion->id]);

    return [
        'cotizacion' => $cotizacion,
        'pedido' => $pedido,
        'nota' => NotaEntrega::factory()->create(['pedido_id' => $pedido->id]),
        'pago' => Pago::factory()->create(['pedido_id' => $pedido->id]),
        'oc' => OrdenCompraCliente::factory()->create(['pedido_id' => $pedido->id]),
        'postventa' => SeguimientoPostventa::factory()->create(['pedido_id' => $pedido->id]),
    ];
}

/** Cuenta cuyo alcance es exactamente `$sucursal` (vía ficha de empleado). */
function usuarioDe(Sucursal $sucursal, string ...$permisos): User
{
    collect($permisos)->each(fn (string $p) => Permission::findOrCreate($p, 'web'));

    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->givePermissionTo($permisos);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| Listados
|--------------------------------------------------------------------------
*/

test('cada listado acotado muestra solo la sucursal que el usuario administra', function (
    string $ruta,
    string $permiso,
    string $prop,
) {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    cadenaDeSucursal($mia);
    cadenaDeSucursal($ajena);

    $this->actingAs(usuarioDe($mia, $permiso))
        ->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has($prop, 1));
})->with([
    'cotizaciones' => ['cotizaciones.index', 'cotizaciones.ver', 'cotizaciones.data'],
    'pedidos' => ['pedidos.index', 'pedidos.ver', 'pedidos.data'],
    'notas de entrega' => ['notas-entrega.index', 'notas-entrega.ver', 'notas.data'],
    'órdenes de compra' => ['ordenes-compra-cliente.index', 'ordenes-compra-cliente.ver', 'ordenes.data'],
    'pagos' => ['pagos.index', 'pagos.ver', 'pagos.data'],
    'postventa' => ['seguimientos-postventa.index', 'seguimientos-postventa.ver', 'seguimientos.data'],
]);

test('el listado de empleados tambien se acota', function () {
    // No cuelga de cotización: `empleado` tiene su propia `sucursal_id`.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    Empleado::factory()->create(['sucursal_id' => $ajena->id]);

    // El propio empleado de la cuenta ya cuenta como uno de `$mia`.
    $this->actingAs(usuarioDe($mia, 'empleados.ver'))
        ->get(route('empleados.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('empleados.data', 1));
});

/*
|--------------------------------------------------------------------------
| Totales: acotar la tabla y no el resumen sería peor que no acotar nada
|--------------------------------------------------------------------------
*/

test('los totales de cobranza no filtran la caja de otras sucursales', function () {
    // Un total se lee de un vistazo, sin comprobar de dónde sale: si la tabla
    // va acotada y las tarjetas no, el número miente con más autoridad.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $mia->id]);
    $pedido = Pedido::factory()->create(['cotizacion_id' => $cotizacion->id, 'total' => 1000]);
    Pago::factory()->create(['pedido_id' => $pedido->id, 'monto' => 400]);

    $ajenoCot = Cotizacion::factory()->create(['sucursal_id' => $ajena->id]);
    $ajenoPed = Pedido::factory()->create(['cotizacion_id' => $ajenoCot->id, 'total' => 9999]);
    Pago::factory()->create(['pedido_id' => $ajenoPed->id, 'monto' => 7777]);

    $this->actingAs(usuarioDe($mia, 'pagos.ver'))
        ->get(route('pagos.index'))
        ->assertOk()
        // Sin el acotado serían 8.177 cobrado y 2.822 por cobrar.
        ->assertInertia(fn ($page) => $page
            ->where('resumen.total_cobrado', fn ($v) => (float) $v === 400.0)
            ->where('resumen.por_cobrar', fn ($v) => (float) $v === 600.0));
});

test('el resumen de postventa tampoco cuenta seguimientos ajenos', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    cadenaDeSucursal($mia);
    cadenaDeSucursal($ajena);

    $this->actingAs(usuarioDe($mia, 'seguimientos-postventa.ver'))
        ->get(route('seguimientos-postventa.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('resumen.pendientes', 1));
});

/*
|--------------------------------------------------------------------------
| Rutas de detalle: lo que no sale en la lista tampoco se abre por URL
|--------------------------------------------------------------------------
*/

test('no se puede abrir el detalle de un documento de otra sucursal', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $suyo = cadenaDeSucursal($ajena);

    $usuario = usuarioDe(
        $mia,
        'cotizaciones.ver',
        'pedidos.ver',
        'notas-entrega.ver',
    );

    $this->actingAs($usuario)->get(route('cotizaciones.show', $suyo['cotizacion']))->assertForbidden();
    $this->actingAs($usuario)->get(route('pedidos.show', $suyo['pedido']))->assertForbidden();
    $this->actingAs($usuario)->get(route('notas-entrega.show', $suyo['nota']))->assertForbidden();
});

test('el PDF no es la puerta trasera del documento que no se puede ver', function () {
    // El caso clásico: la pantalla filtra pero la ruta del PDF no, y el
    // documento se baja igual poniendo el id en la URL.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $suyo = cadenaDeSucursal($ajena);

    $usuario = usuarioDe(
        $mia,
        'cotizaciones.ver',
        'pedidos.ver',
        'notas-entrega.ver',
        'ordenes-compra-cliente.ver',
    );

    $this->actingAs($usuario)->get(route('cotizaciones.pdf', $suyo['cotizacion']))->assertForbidden();
    $this->actingAs($usuario)->get(route('pedidos.pdf', $suyo['pedido']))->assertForbidden();
    $this->actingAs($usuario)->get(route('notas-entrega.pdf', $suyo['nota']))->assertForbidden();
    $this->actingAs($usuario)->get(route('ordenes-compra-cliente.pdf', $suyo['oc']))->assertForbidden();
});

test('las acciones que cambian estado tampoco alcanzan a otra sucursal', function () {
    // Acotar solo la lectura dejaría aprobar por URL una cotización que no se
    // puede ni abrir.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $ajena->id, 'estado' => 'PENDIENTE']);

    $usuario = usuarioDe($mia, 'cotizaciones.ver', 'cotizaciones.aprobar', 'cotizaciones.eliminar');

    $this->actingAs($usuario)->post(route('cotizaciones.aprobar', $cotizacion))->assertForbidden();
    $this->actingAs($usuario)->delete(route('cotizaciones.destroy', $cotizacion))->assertForbidden();

    expect($cotizacion->fresh()->estado)->toBe('PENDIENTE');
});

/*
|--------------------------------------------------------------------------
| Desplegables
|--------------------------------------------------------------------------
*/

test('el desplegable de sucursales no ofrece las que no se administran', function () {
    // Un filtro que siempre devuelve vacío parece un error del sistema; y en el
    // formulario, elegir una sucursal ajena sería crear algo que después no se
    // podría ni abrir.
    $mia = Sucursal::factory()->create();
    Sucursal::factory()->count(3)->create();

    $usuario = usuarioDe($mia, 'cotizaciones.ver', 'cotizaciones.crear');

    $this->actingAs($usuario)->get(route('cotizaciones.index'))
        ->assertInertia(fn ($page) => $page->has('sucursales', 1));

    $this->actingAs($usuario)->get(route('cotizaciones.create'))
        ->assertInertia(fn ($page) => $page->has('sucursales', 1));
});

/*
|--------------------------------------------------------------------------
| Quien no tiene límite sigue viéndolo todo
|--------------------------------------------------------------------------
*/

test('el alcance TODAS y los roles globales no pierden nada', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    cadenaDeSucursal($mia);
    $suyo = cadenaDeSucursal($ajena);

    Permission::findOrCreate('cotizaciones.ver', 'web');

    $global = User::factory()->todasLasSucursales()->create();
    $global->givePermissionTo('cotizaciones.ver');

    $this->actingAs($global)->get(route('cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('cotizaciones.data', 2));

    $this->actingAs($global)->get(route('cotizaciones.show', $suyo['cotizacion']))->assertOk();
});
