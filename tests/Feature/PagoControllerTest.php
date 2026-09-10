<?php

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['pagos.ver', 'pagos.registrar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithPago(string ...$permissions): User
{
    // Alcance TODAS: estas pruebas comprueban el MODULO, no el acotado por
    // sucursal (eso vive en AlcanceSucursalTest y AlcanceModulosTest). Sin
    // alcance la cuenta no veria ninguna fila y todo daria falso negativo.
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('pagos.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pagos.index'))
        ->assertForbidden();
});

test('a user with permission sees the list and the summary', function () {
    Pago::factory()->count(3)->create();

    $this->actingAs(userWithPago('pagos.ver'))
        ->get(route('pagos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pagos/Index')
            ->has('pagos.data', 3)
            ->has('resumen.total_cobrado'));
});

test('the pedido cobranza state follows the payments, without a pago.estado column', function () {
    $pedido = Pedido::factory()->create(['total' => 1000]);
    $user = userWithPago('pagos.registrar');

    $this->actingAs($user)->post(route('pagos.store'), [
        'pedido_id' => $pedido->id, 'monto' => 400, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => 'EFECTIVO',
    ]);

    // El estado de cobranza pertenece al PEDIDO, no a la fila de pago: la
    // columna `pago.estado` se eliminó porque duplicaba este derivado.
    expect($pedido->fresh()->estadoPago())->toBe('PARCIAL')
        ->and($pedido->fresh()->saldo())->toBe(600.0);

    $this->actingAs($user)->post(route('pagos.store'), [
        'pedido_id' => $pedido->id, 'monto' => 600, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => 'QR',
    ]);

    expect($pedido->fresh()->estadoPago())->toBe('PAGADO')
        ->and($pedido->fresh()->saldo())->toBe(0.0);
});

test('registrar pago requires the pagos.registrar permission', function () {
    $pedido = Pedido::factory()->create(['total' => 500]);

    $this->actingAs(userWithPago('pagos.ver'))
        ->post(route('pagos.store'), [
            'pedido_id' => $pedido->id, 'monto' => 100, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => 'EFECTIVO',
        ])
        ->assertForbidden();

    expect(Pago::count())->toBe(0);
});

test('an invalid metodo_pago is rejected', function () {
    $pedido = Pedido::factory()->create();

    $this->actingAs(userWithPago('pagos.registrar'))
        ->post(route('pagos.store'), [
            'pedido_id' => $pedido->id, 'monto' => 100, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => 'BITCOIN',
        ])
        ->assertSessionHasErrors('metodo_pago');
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('pagos.index'))->assertOk();
});
