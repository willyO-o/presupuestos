<?php

use App\Models\Area;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'pedidos.ver', 'pedidos.crear', 'pedidos.asignar_area',
        'pedidos.actualizar_estado', 'pedidos.ver_todas_sucursales',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

/**
 * Cuenta SIN alcance de sucursal. Varias pruebas de acá le suman
 * `pedidos.ver_todas_sucursales`, que es el override que amplía el alcance
 * solo para este módulo (ver TieneAlcanceSucursal).
 */
function userWithPedido(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * Cuenta con alcance TODAS.
 *
 * Hace falta donde el override de pedidos NO alcanza: al convertir una
 * cotización (se acota con las reglas de COTIZACIÓN, ver
 * `PedidoController::create`) y al operar sobre los ítems de un pedido, que
 * desde la fase 4 exige alcance real y no solo el permiso de acción.
 */
function userWithPedidoGlobal(string ...$permissions): User
{
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function cotizacionConvertible(int $lineas = 2): Cotizacion
{
    return Cotizacion::factory()
        ->aprobada()
        ->has(CotizacionDetalle::factory()->count($lineas), 'detalles')
        ->create();
}

test('guests are redirected to login', function () {
    $this->get(route('pedidos.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pedidos.index'))
        ->assertForbidden();
});

test('a user with ver_todas_sucursales sees every pedido', function () {
    Pedido::factory()->count(3)->create();

    $this->actingAs(userWithPedido('pedidos.ver', 'pedidos.ver_todas_sucursales'))
        ->get(route('pedidos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Pedidos/Index')->has('pedidos.data', 3));
});

test('the create page lists approved cotizaciones pending conversion', function () {
    cotizacionConvertible();

    $this->actingAs(userWithPedido('pedidos.ver'))
        ->get(route('pedidos.create'))
        ->assertForbidden();

    // La lista de convertibles se acota con las reglas de COTIZACIÓN, no con
    // `pedidos.ver_todas_sucursales`: convertir la cotización de otra sucursal
    // es escribir sobre su cartera, no solo mirarla. Por eso la cuenta necesita
    // alcance de verdad y no le alcanza el override de pedidos.
    $conAlcance = userWithPedido('pedidos.crear');
    $conAlcance->update(['alcance_sucursal' => 'TODAS']);

    $this->actingAs($conAlcance->fresh())
        ->get(route('pedidos.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Pedidos/Create')->has('cotizaciones', 1));
});

test('storing converts an approved cotizacion into a pedido', function () {
    $cotizacion = cotizacionConvertible(2);

    $response = $this->actingAs(userWithPedidoGlobal('pedidos.crear'))
        ->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id]);

    $pedido = Pedido::latest('id')->first();

    $response->assertRedirect(route('pedidos.show', $pedido));
    expect($pedido->cotizacion_id)->toBe($cotizacion->id)
        ->and($pedido->estado)->toBe('DISENO')
        ->and($pedido->detalles)->toHaveCount(2)
        ->and($cotizacion->fresh()->estado)->toBe('CONVERTIDA')
        ->and((float) $pedido->total)->toBe((float) $cotizacion->total);
});

test('a cotizacion that is not approved cannot be converted', function () {
    $cotizacion = Cotizacion::factory()->has(CotizacionDetalle::factory(), 'detalles')->create();

    $this->actingAs(userWithPedidoGlobal('pedidos.crear'))
        ->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id])
        ->assertSessionHas('error');

    expect(Pedido::count())->toBe(0);
});

test('a cotizacion cannot be converted twice', function () {
    $cotizacion = cotizacionConvertible();
    $user = userWithPedidoGlobal('pedidos.crear');

    $this->actingAs($user)->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id]);
    $this->actingAs($user)->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id])
        ->assertSessionHas('error');

    expect(Pedido::where('cotizacion_id', $cotizacion->id)->count())->toBe(1);
});

test('a user without ver_todas_sucursales only sees pedidos of their sucursal', function () {
    $sucursalPropia = Sucursal::factory()->create();
    $otraSucursal = Sucursal::factory()->create();

    $user = userWithPedido('pedidos.ver');
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursalPropia->id]);

    $propio = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->convertida()->create(['sucursal_id' => $sucursalPropia->id])->id,
    ]);
    $ajeno = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->convertida()->create(['sucursal_id' => $otraSucursal->id])->id,
    ]);

    $this->actingAs($user)->get(route('pedidos.index'))
        ->assertInertia(fn ($page) => $page->has('pedidos.data', 1)
            ->where('pedidos.data.0.id', $propio->id));

    $this->actingAs($user)->get(route('pedidos.show', $ajeno))->assertForbidden();
    $this->actingAs($user)->get(route('pedidos.show', $propio))->assertOk();
});

test('asignar area creates a seguimiento entry for the item', function () {
    $pedido = Pedido::factory()->has(PedidoDetalle::factory(), 'detalles')->create();
    $detalle = $pedido->detalles->first();
    $area = Area::factory()->create();
    $empleado = Empleado::factory()->create();

    $this->actingAs(userWithPedido('pedidos.ver'))
        ->post(route('pedidos.detalle.asignar-area', [$pedido, $detalle]), [
            'area_id' => $area->id, 'empleado_id' => $empleado->id, 'etapa' => 'DISENO',
        ])
        ->assertForbidden();

    $this->actingAs(userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.asignar_area'))
        ->post(route('pedidos.detalle.asignar-area', [$pedido, $detalle]), [
            'area_id' => $area->id, 'empleado_id' => $empleado->id, 'etapa' => 'ELABORACION',
        ])
        ->assertRedirect(route('pedidos.show', $pedido));

    $this->assertDatabaseHas('pedido_seguimiento', [
        'pedido_detalle_id' => $detalle->id,
        'area_id' => $area->id,
        'etapa' => 'ELABORACION',
    ]);
});

test('advancing every item to ENTREGADO marks the pedido ENTREGADO with a real delivery date', function () {
    $pedido = Pedido::factory()->has(PedidoDetalle::factory()->count(2), 'detalles')->create(['estado' => 'DISENO']);
    $user = userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.actualizar_estado');

    foreach ($pedido->detalles as $detalle) {
        $this->actingAs($user)->put(route('pedidos.detalle.estado', [$pedido, $detalle]), [
            'estado_item' => 'ENTREGADO',
        ])->assertRedirect(route('pedidos.show', $pedido));
    }

    $pedido->refresh();
    expect($pedido->estado)->toBe('ENTREGADO')
        ->and($pedido->fecha_entrega_real)->not->toBeNull();
});

test('the pedido estado tracks the least advanced item', function () {
    $pedido = Pedido::factory()->has(PedidoDetalle::factory()->count(2), 'detalles')->create(['estado' => 'DISENO']);
    $user = userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.actualizar_estado');
    [$a, $b] = $pedido->detalles->all();

    $this->actingAs($user)->put(route('pedidos.detalle.estado', [$pedido, $a]), ['estado_item' => 'ACABADO']);
    $this->actingAs($user)->put(route('pedidos.detalle.estado', [$pedido, $b]), ['estado_item' => 'ELABORACION']);

    expect($pedido->fresh()->estado)->toBe('ELABORACION');
});

test('registrar consumo computes costo_real from the material price when omitted', function () {
    $pedido = Pedido::factory()->has(PedidoDetalle::factory(), 'detalles')->create();
    $detalle = $pedido->detalles->first();
    $material = Material::factory()->create(['precio_unitario' => 20]);

    $this->actingAs(userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.actualizar_estado'))
        ->post(route('pedidos.detalle.consumo', [$pedido, $detalle]), [
            'material_id' => $material->id, 'cantidad_usada' => 3,
        ])
        ->assertRedirect(route('pedidos.show', $pedido));

    $this->assertDatabaseHas('pedido_detalle_material', [
        'pedido_detalle_id' => $detalle->id,
        'material_id' => $material->id,
        'cantidad_usada' => 3,
        'costo_real' => 60,
    ]);
});

test('an item from another pedido returns 404', function () {
    $pedidoA = Pedido::factory()->has(PedidoDetalle::factory(), 'detalles')->create();
    $pedidoB = Pedido::factory()->has(PedidoDetalle::factory(), 'detalles')->create();
    $detalleB = $pedidoB->detalles->first();

    $this->actingAs(userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.estado', [$pedidoA, $detalleB]), ['estado_item' => 'ACABADO'])
        ->assertNotFound();
});

test('cancelar sets the pedido CANCELADO but not once delivered', function () {
    $entregado = Pedido::factory()->entregado()->create();
    $enCurso = Pedido::factory()->create(['estado' => 'ELABORACION']);
    $user = userWithPedido('pedidos.ver_todas_sucursales', 'pedidos.actualizar_estado');

    $this->actingAs($user)->post(route('pedidos.cancelar', $entregado))->assertSessionHas('error');
    expect($entregado->fresh()->estado)->toBe('ENTREGADO');

    $this->actingAs($user)->post(route('pedidos.cancelar', $enCurso));
    expect($enCurso->fresh()->estado)->toBe('CANCELADO');
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('pedidos.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Medidas reales de producción
|--------------------------------------------------------------------------
|
| Es lo que justifica que `pedido_detalle` copie descripción/medidas/cantidad
| de la cotización en vez de leerlas por la FK: el taller mide la pieza
| terminada y la cotización queda intacta como documento histórico.
*/

test('production measurements can diverge from the cotizacion that originated them', function () {
    $pedido = Pedido::factory()->create();
    $detalle = PedidoDetalle::factory()->create([
        'pedido_id' => $pedido->id,
        'descripcion' => 'Letrero fachada',
        'ancho' => 2.00,
        'alto' => 1.00,
        'cantidad' => 1,
    ]);
    $cotizacionDetalle = $detalle->cotizacionDetalle;

    $this->actingAs(userWithPedidoGlobal('pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.medidas', [$pedido, $detalle]), [
            'descripcion' => 'Letrero fachada (ampliado)',
            'ancho' => 2.35,
            'alto' => 1.10,
            'cantidad' => 1,
            'motivo' => 'La pared medía 35 cm más.',
        ])
        ->assertRedirect(route('pedidos.show', $pedido))
        ->assertSessionHas('success');

    $detalle->refresh();

    expect((float) $detalle->ancho)->toBe(2.35)
        ->and($detalle->descripcion)->toBe('Letrero fachada (ampliado)')
        // La cotización de origen NO se toca.
        ->and((float) $cotizacionDetalle->fresh()->ancho)->toBe((float) $cotizacionDetalle->ancho)
        ->and($cotizacionDetalle->fresh()->descripcion)->toBe($cotizacionDetalle->descripcion);
});

test('adjusting measurements does not change the agreed price', function () {
    $pedido = Pedido::factory()->create(['total' => 1500]);
    $detalle = PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'cantidad' => 2]);

    $this->actingAs(userWithPedidoGlobal('pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.medidas', [$pedido, $detalle]), [
            'descripcion' => $detalle->descripcion,
            'ancho' => 5,
            'alto' => 5,
            'cantidad' => 8,
        ])->assertSessionHasNoErrors();

    expect((float) $pedido->fresh()->total)->toBe(1500.0);
});

test('the measurement adjustment is written to the item log', function () {
    $pedido = Pedido::factory()->create();
    $detalle = PedidoDetalle::factory()->create([
        'pedido_id' => $pedido->id,
        'descripcion' => 'Original',
        'cantidad' => 1,
    ]);
    $seguimiento = PedidoSeguimiento::factory()->create(['pedido_detalle_id' => $detalle->id]);

    $this->actingAs(userWithPedidoGlobal('pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.medidas', [$pedido, $detalle]), [
            'descripcion' => 'Corregido',
            'cantidad' => 3,
            'motivo' => 'Se rompió una pieza.',
        ])->assertSessionHasNoErrors();

    expect($seguimiento->fresh()->observaciones)
        ->toContain('Ajuste de medidas')
        ->toContain('Se rompió una pieza.');
});

test('measurements cannot be adjusted on a delivered pedido', function () {
    $pedido = Pedido::factory()->entregado()->create();
    $detalle = PedidoDetalle::factory()->create(['pedido_id' => $pedido->id]);

    $this->actingAs(userWithPedidoGlobal('pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.medidas', [$pedido, $detalle]), [
            'descripcion' => 'No debería guardarse',
            'cantidad' => 1,
        ])->assertSessionHas('error');

    expect($detalle->fresh()->descripcion)->not->toBe('No debería guardarse');
});

test('measurements of a detalle from another pedido are rejected', function () {
    $pedido = Pedido::factory()->create();
    $ajeno = PedidoDetalle::factory()->create();

    $this->actingAs(userWithPedidoGlobal('pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.medidas', [$pedido, $ajeno]), [
            'descripcion' => 'Intruso',
            'cantidad' => 1,
        ])->assertNotFound();
});
