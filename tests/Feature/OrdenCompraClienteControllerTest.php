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
    $user = User::factory()->create();
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

test('storing an OC derives cliente_id from the pedido', function () {
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
        'cliente_id' => $pedido->cotizacion->cliente_id,
        'numero_oc' => 'OC-11021545',
        'estado' => 'PENDIENTE',
    ]);
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
