<?php

use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['sucursales.ver', 'sucursales.crear', 'sucursales.editar', 'sucursales.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('sucursales.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sucursales.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Sucursal::factory()->count(3)->create();
    $user = userWithPermissions('sucursales.ver');

    $response = $this->actingAs($user)->get(route('sucursales.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Sucursales/Index')
        ->has('sucursales.data', 3)
    );
});

test('the list can be searched by nombre, direccion or telefono', function () {
    Sucursal::factory()->create(['nombre' => 'Sucursal Central']);
    Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
    $user = userWithPermissions('sucursales.ver');

    $response = $this->actingAs($user)->get(route('sucursales.index', ['search' => 'Central']));

    $response->assertInertia(fn ($page) => $page
        ->has('sucursales.data', 1)
        ->where('sucursales.data.0.nombre', 'Sucursal Central')
    );
});

test('the list can be filtered by estado', function () {
    Sucursal::factory()->create(['estado' => 'ACTIVO']);
    Sucursal::factory()->inactivo()->create();
    $user = userWithPermissions('sucursales.ver');

    $response = $this->actingAs($user)->get(route('sucursales.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('sucursales.data', 1)
        ->where('sucursales.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a sucursal', function () {
    $user = userWithPermissions('sucursales.ver', 'sucursales.crear');

    $response = $this->actingAs($user)->post(route('sucursales.store'), [
        'nombre' => 'Sucursal El Alto',
        'direccion' => 'Av. 6 de Marzo 123',
        'telefono' => '71234567',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('sucursales.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('sucursal', [
        'nombre' => 'Sucursal El Alto',
        'telefono' => '71234567',
    ]);
});

test('a user without permission cannot create a sucursal', function () {
    $user = userWithPermissions('sucursales.ver');

    $this->actingAs($user)->post(route('sucursales.store'), [
        'nombre' => 'Sucursal El Alto',
        'direccion' => 'Av. 6 de Marzo 123',
        'telefono' => '71234567',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('sucursal', 0);
});

test('creating a sucursal requires nombre, direccion, telefono and a valid estado', function () {
    $user = userWithPermissions('sucursales.ver', 'sucursales.crear');

    $response = $this->actingAs($user)->post(route('sucursales.store'), [
        'nombre' => '',
        'direccion' => '',
        'telefono' => '',
        'estado' => 'EN_MANTENIMIENTO',
    ]);

    $response->assertSessionHasErrors(['nombre', 'direccion', 'telefono', 'estado']);
    $this->assertDatabaseCount('sucursal', 0);
});

test('a user with permission can update a sucursal', function () {
    $sucursal = Sucursal::factory()->create(['nombre' => 'Original']);
    $user = userWithPermissions('sucursales.ver', 'sucursales.editar');

    $response = $this->actingAs($user)->put(route('sucursales.update', $sucursal), [
        'nombre' => 'Actualizada',
        'direccion' => $sucursal->direccion,
        'telefono' => $sucursal->telefono,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('sucursales.index'));
    $this->assertDatabaseHas('sucursal', [
        'id' => $sucursal->id,
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a sucursal', function () {
    $sucursal = Sucursal::factory()->create(['nombre' => 'Original']);
    $user = userWithPermissions('sucursales.ver');

    $this->actingAs($user)->put(route('sucursales.update', $sucursal), [
        'nombre' => 'Actualizada',
        'direccion' => $sucursal->direccion,
        'telefono' => $sucursal->telefono,
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('sucursal', ['id' => $sucursal->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a sucursal', function () {
    $sucursal = Sucursal::factory()->create();
    $user = userWithPermissions('sucursales.ver', 'sucursales.eliminar');

    $response = $this->actingAs($user)->delete(route('sucursales.destroy', $sucursal));

    $response->assertRedirect(route('sucursales.index'));
    $this->assertDatabaseMissing('sucursal', ['id' => $sucursal->id]);
});

test('a user without permission cannot delete a sucursal', function () {
    $sucursal = Sucursal::factory()->create();
    $user = userWithPermissions('sucursales.ver');

    $this->actingAs($user)->delete(route('sucursales.destroy', $sucursal))
        ->assertForbidden();

    $this->assertDatabaseHas('sucursal', ['id' => $sucursal->id]);
});

test('super-admin bypasses individual permissions', function () {
    Sucursal::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('sucursales.index'))
        ->assertOk();
});
