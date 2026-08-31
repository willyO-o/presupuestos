<?php

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'compras.ver', 'compras.crear', 'compras.editar', 'compras.eliminar', 'compras.aprobar',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithCompra(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function cabeceraCompraValida(): array
{
    return [
        'proveedor_id' => Proveedor::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'numero_factura' => 'F-00123',
        'fecha' => now()->toDateString(),
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('compras.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('compras.index'))
        ->assertForbidden();
});

test('a user with permission sees the list', function () {
    Compra::factory()->count(3)->create();

    $this->actingAs(userWithCompra('compras.ver'))
        ->get(route('compras.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Compras/Index')->has('compras.data', 3));
});

test('the create page requires the crear permission', function () {
    $this->actingAs(userWithCompra('compras.ver'))
        ->get(route('compras.create'))
        ->assertForbidden();

    $this->actingAs(userWithCompra('compras.crear'))
        ->get(route('compras.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Compras/Create')->has('materiales'));
});

test('storing a compra computes the total on the server and forces estado PENDIENTE', function () {
    $material = Material::factory()->create();

    $payload = [
        ...cabeceraCompraValida(),
        'total' => 999999,
        'estado' => 'PAGADA',
        'detalles' => [
            ['material_id' => $material->id, 'cantidad' => 3, 'precio_unitario' => 100],
            ['material_id' => $material->id, 'cantidad' => 2, 'precio_unitario' => 25],
        ],
    ];

    $response = $this->actingAs(userWithCompra('compras.crear'))->post(route('compras.store'), $payload);

    $compra = Compra::latest('id')->first();

    $response->assertRedirect(route('compras.show', $compra));
    expect($compra->estado)->toBe('PENDIENTE')
        ->and((float) $compra->total)->toBe(350.0)
        ->and($compra->detalles)->toHaveCount(2);

    $this->assertDatabaseHas('compra_detalle', [
        'compra_id' => $compra->id,
        'material_id' => $material->id,
        'cantidad' => 3,
        'subtotal' => 300,
    ]);
});

test('a compra needs at least one detalle line', function () {
    $this->actingAs(userWithCompra('compras.crear'))
        ->post(route('compras.store'), [...cabeceraCompraValida(), 'detalles' => []])
        ->assertSessionHasErrors('detalles');
});

test('the show page renders the compra with its detalle', function () {
    $compra = Compra::factory()->has(CompraDetalle::factory()->count(2), 'detalles')->create();

    $this->actingAs(userWithCompra('compras.ver'))
        ->get(route('compras.show', $compra))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Compras/Show')->has('compra.detalles', 2));
});

test('a paid compra cannot be edited or deleted', function () {
    $compra = Compra::factory()->pagada()->create();

    $this->actingAs(userWithCompra('compras.editar'))
        ->get(route('compras.edit', $compra))
        ->assertRedirect(route('compras.show', $compra));

    $this->actingAs(userWithCompra('compras.eliminar'))
        ->delete(route('compras.destroy', $compra))
        ->assertSessionHas('error');

    $this->assertModelExists($compra);
});

test('updating a pending compra replaces its detalle', function () {
    $compra = Compra::factory()->has(CompraDetalle::factory()->count(3), 'detalles')->create();
    $material = Material::factory()->create();

    $this->actingAs(userWithCompra('compras.editar'))
        ->put(route('compras.update', $compra), [
            ...cabeceraCompraValida(),
            'detalles' => [['material_id' => $material->id, 'cantidad' => 1, 'precio_unitario' => 200]],
        ])
        ->assertRedirect(route('compras.show', $compra));

    expect($compra->fresh()->detalles)->toHaveCount(1)
        ->and((float) $compra->fresh()->total)->toBe(200.0);
});

test('aprobar marks the compra PAGADA and impacts the inventory once', function () {
    $material = Material::factory()->create([
        'stock_actual' => 10,
        'precio_unitario' => 50,
        'precio_presentacion' => 500,
    ]);
    $compra = Compra::factory()->create(['fecha' => now()->subDay()->toDateString()]);
    CompraDetalle::factory()->for($compra)->create([
        'material_id' => $material->id,
        'cantidad' => 4,
        'precio_unitario' => 60,
        'subtotal' => 240,
    ]);

    $this->actingAs(userWithCompra('compras.ver'))
        ->post(route('compras.aprobar', $compra))
        ->assertForbidden();

    $this->actingAs(userWithCompra('compras.aprobar'))
        ->post(route('compras.aprobar', $compra))
        ->assertRedirect(route('compras.show', $compra));

    $material->refresh();
    expect($compra->fresh()->estado)->toBe('PAGADA')
        ->and((float) $material->stock_actual)->toBe(14.0)
        ->and((float) $material->precio_unitario)->toBe(60.0);

    $this->assertDatabaseHas('historial_precio_material', [
        'material_id' => $material->id,
        'precio_unitario' => 60,
    ]);

    // Segunda aprobación bloqueada: el stock no se duplica.
    $this->actingAs(userWithCompra('compras.aprobar'))
        ->post(route('compras.aprobar', $compra))
        ->assertSessionHas('error');

    expect((float) $material->fresh()->stock_actual)->toBe(14.0);
});

test('anular marks a pending compra ANULADA', function () {
    $compra = Compra::factory()->create();

    $this->actingAs(userWithCompra('compras.aprobar'))
        ->post(route('compras.anular', $compra))
        ->assertRedirect(route('compras.show', $compra));

    expect($compra->fresh()->estado)->toBe('ANULADA');
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('compras.index'))->assertOk();
});
