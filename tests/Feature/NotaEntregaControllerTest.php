<?php

use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\NotaEntregaDetalle;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['notas-entrega.ver', 'notas-entrega.crear'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithNota(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function pedidoConItems(int $items = 2): Pedido
{
    return Pedido::factory()
        ->has(PedidoDetalle::factory()->count($items)->estadoItem('ACABADO'), 'detalles')
        ->create([
            'cotizacion_id' => Cotizacion::factory()->convertida()->create()->id,
            'estado' => 'ACABADO',
        ]);
}

test('guests are redirected to login', function () {
    $this->get(route('notas-entrega.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('notas-entrega.index'))
        ->assertForbidden();
});

test('a user with permission sees the list', function () {
    NotaEntrega::factory()->count(2)->create();

    $this->actingAs(userWithNota('notas-entrega.ver'))
        ->get(route('notas-entrega.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotasEntrega/Index')->has('notas.data', 2));
});

test('the create page renders for a pedido', function () {
    $pedido = pedidoConItems();

    $this->actingAs(userWithNota('notas-entrega.crear'))
        ->get(route('notas-entrega.create', ['pedido' => $pedido->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotasEntrega/Create')->has('pedido.detalles', 2));
});

test('storing a nota marks the delivered items ENTREGADO and advances the pedido', function () {
    $pedido = pedidoConItems(2);
    $empleado = Empleado::factory()->create();

    $response = $this->actingAs(userWithNota('notas-entrega.crear'))->post(route('notas-entrega.store'), [
        'pedido_id' => $pedido->id,
        'empleado_id' => $empleado->id,
        'fecha_entrega' => now()->toDateString(),
        'recibido_por' => 'Juan Pérez',
        'detalles' => $pedido->detalles->map(fn ($d) => [
            'pedido_detalle_id' => $d->id,
            'descripcion' => $d->descripcion,
            'cantidad_entregada' => 1,
        ])->all(),
    ]);

    $nota = NotaEntrega::latest('id')->first();
    $response->assertRedirect(route('notas-entrega.show', $nota));

    expect($nota->detalles)->toHaveCount(2)
        ->and($pedido->fresh()->estado)->toBe('ENTREGADO')
        ->and($pedido->fresh()->fecha_entrega_real)->not->toBeNull()
        ->and($pedido->detalles()->pluck('estado_item')->unique()->all())->toBe(['ENTREGADO']);
});

test('a nota needs at least one item', function () {
    $pedido = pedidoConItems();
    $empleado = Empleado::factory()->create();

    $this->actingAs(userWithNota('notas-entrega.crear'))
        ->post(route('notas-entrega.store'), [
            'pedido_id' => $pedido->id,
            'empleado_id' => $empleado->id,
            'fecha_entrega' => now()->toDateString(),
            'detalles' => [],
        ])
        ->assertSessionHasErrors('detalles');
});

test('the show page renders the nota with its detalle', function () {
    $nota = NotaEntrega::factory()
        ->has(NotaEntregaDetalle::factory()->count(2), 'detalles')
        ->create();

    $this->actingAs(userWithNota('notas-entrega.ver'))
        ->get(route('notas-entrega.show', $nota))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotasEntrega/Show')->has('nota.detalles', 2));
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('notas-entrega.index'))->assertOk();
});
