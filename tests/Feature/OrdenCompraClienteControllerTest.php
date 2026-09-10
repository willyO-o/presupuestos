<?php

use App\Models\Cotizacion;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['ordenes-compra-cliente.ver', 'ordenes-compra-cliente.crear', 'ordenes-compra-cliente.validar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithOc(string ...$permissions): User
{
    // Alcance TODAS: estas pruebas comprueban el MODULO, no el acotado por
    // sucursal (eso vive en AlcanceSucursalTest y AlcanceModulosTest). Sin
    // alcance la cuenta no veria ninguna fila y todo daria falso negativo.
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function pedidoParaOc(): Pedido
{
    return Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->convertida()->create()->id,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('ordenes-compra-cliente.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ordenes-compra-cliente.index'))
        ->assertForbidden();
});

test('a user with permission sees the list and the pedidos without OC', function () {
    OrdenCompraCliente::factory()->count(2)->create();
    pedidoParaOc();

    $this->actingAs(userWithOc('ordenes-compra-cliente.ver'))
        ->get(route('ordenes-compra-cliente.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OrdenesCompraCliente/Index')
            ->has('ordenes.data', 2)
            ->has('pedidosSinOc'));
});

test('an OC reads its cliente from the pedido chain, without storing a FK', function () {
    $pedido = pedidoParaOc();

    $this->actingAs(userWithOc('ordenes-compra-cliente.crear'))
        ->post(route('ordenes-compra-cliente.store'), [
            'pedido_id' => $pedido->id,
            'numero_oc' => 'OC-11021545',
            'fecha' => now()->toDateString(),
            'monto_total' => 1500,
            'condicion_pago' => '60 DIAS',
        ])
        ->assertRedirect(route('ordenes-compra-cliente.index'));

    $this->assertDatabaseHas('orden_compra_cliente', [
        'pedido_id' => $pedido->id,
        'numero_oc' => 'OC-11021545',
        'estado' => 'PENDIENTE',
    ]);

    // El cliente no se guarda: se deriva de pedido → cotizacion → cliente.
    $orden = OrdenCompraCliente::query()->where('numero_oc', 'OC-11021545')->firstOrFail();

    expect($orden->cliente()->id)->toBe($pedido->cotizacion->cliente_id)
        ->and($orden->cliente_razon_social)->toBe($pedido->cotizacion->cliente->razon_social);
});

test('an OC flags when its amount differs from the pedido total', function () {
    $pedido = pedidoParaOc();

    $coincide = OrdenCompraCliente::factory()->create([
        'pedido_id' => $pedido->id,
        'monto_total' => $pedido->total,
    ]);

    expect($coincide->difiereDelPedido())->toBeFalse();

    $coincide->update(['monto_total' => (float) $pedido->total + 50]);

    expect($coincide->fresh()->difiereDelPedido())->toBeTrue();
});

test('a pedido cannot have two OCs', function () {
    $orden = OrdenCompraCliente::factory()->create();

    $this->actingAs(userWithOc('ordenes-compra-cliente.crear'))
        ->post(route('ordenes-compra-cliente.store'), [
            'pedido_id' => $orden->pedido_id,
            'numero_oc' => 'OTRA',
            'fecha' => now()->toDateString(),
            'monto_total' => 100,
        ])
        ->assertSessionHasErrors('pedido_id');
});

test('validar and anular only work on a pending OC and need the validar permission', function () {
    $orden = OrdenCompraCliente::factory()->create();

    $this->actingAs(userWithOc('ordenes-compra-cliente.crear'))
        ->post(route('ordenes-compra-cliente.validar', $orden))
        ->assertForbidden();

    $this->actingAs(userWithOc('ordenes-compra-cliente.validar'))
        ->post(route('ordenes-compra-cliente.validar', $orden))
        ->assertRedirect(route('ordenes-compra-cliente.index'));

    expect($orden->fresh()->estado)->toBe('VALIDADA');

    $this->actingAs(userWithOc('ordenes-compra-cliente.validar'))
        ->post(route('ordenes-compra-cliente.anular', $orden))
        ->assertSessionHas('error');
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('ordenes-compra-cliente.index'))->assertOk();
});
