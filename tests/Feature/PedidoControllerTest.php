<?php

use App\Models\Area;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
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

function userWithPedido(string ...$permissions): User
{
    $user = User::factory()->create();
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

    $this->actingAs(userWithPedido('pedidos.crear'))
        ->get(route('pedidos.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Pedidos/Create')->has('cotizaciones', 1));
});

test('storing converts an approved cotizacion into a pedido', function () {
    $cotizacion = cotizacionConvertible(2);

    $response = $this->actingAs(userWithPedido('pedidos.crear'))
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

    $this->actingAs(userWithPedido('pedidos.crear'))
        ->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id])
        ->assertSessionHas('error');

    expect(Pedido::count())->toBe(0);
});

test('a cotizacion cannot be converted twice', function () {
    $cotizacion = cotizacionConvertible();
    $user = userWithPedido('pedidos.crear');

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
