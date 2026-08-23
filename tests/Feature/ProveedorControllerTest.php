<?php

use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithProveedorPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('proveedores.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('proveedores.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Proveedor::factory()->count(3)->create();
    $user = userWithProveedorPermissions('proveedores.ver');

    $response = $this->actingAs($user)->get(route('proveedores.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Proveedores/Index')
        ->has('proveedores.data', 3)
    );
});

test('the list can be searched by nombre, nit or contacto', function () {
    Proveedor::factory()->create(['nombre' => 'Distribuidora Central', 'nit' => '123456', 'contacto' => 'Juan Pérez']);
    Proveedor::factory()->create(['nombre' => 'Importadora Norte', 'nit' => '987654', 'contacto' => 'Ana López']);
    $user = userWithProveedorPermissions('proveedores.ver');

    $response = $this->actingAs($user)->get(route('proveedores.index', ['search' => 'Central']));

    $response->assertInertia(fn ($page) => $page
        ->has('proveedores.data', 1)
        ->where('proveedores.data.0.nombre', 'Distribuidora Central')
    );
});

test('the list can be filtered by estado', function () {
    Proveedor::factory()->create(['estado' => 'ACTIVO']);
    Proveedor::factory()->inactivo()->create();
    $user = userWithProveedorPermissions('proveedores.ver');

    $response = $this->actingAs($user)->get(route('proveedores.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('proveedores.data', 1)
        ->where('proveedores.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a proveedor', function () {
    $user = userWithProveedorPermissions('proveedores.ver', 'proveedores.crear');

    $response = $this->actingAs($user)->post(route('proveedores.store'), [
        'nombre' => 'Distribuidora Central',
        'nit' => '123456',
        'contacto' => 'Juan Pérez',
        'telefono' => '71234567',
        'direccion' => 'Av. Siempre Viva 123',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('proveedores.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('proveedor', ['nombre' => 'Distribuidora Central', 'nit' => '123456']);
});

test('a proveedor can be created without optional fields', function () {
    $user = userWithProveedorPermissions('proveedores.ver', 'proveedores.crear');

    $response = $this->actingAs($user)->post(route('proveedores.store'), [
        'nombre' => 'Proveedor Mínimo',
        'nit' => '',
        'contacto' => '',
        'telefono' => '',
        'direccion' => '',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('proveedores.index'));
    $this->assertDatabaseHas('proveedor', ['nombre' => 'Proveedor Mínimo', 'nit' => null]);
});

test('a user without permission cannot create a proveedor', function () {
    $user = userWithProveedorPermissions('proveedores.ver');

    $this->actingAs($user)->post(route('proveedores.store'), [
        'nombre' => 'Distribuidora Central',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('proveedor', 0);
});

test('creating a proveedor requires nombre and a valid estado', function () {
    $user = userWithProveedorPermissions('proveedores.ver', 'proveedores.crear');

    $response = $this->actingAs($user)->post(route('proveedores.store'), [
        'nombre' => '',
        'estado' => 'DESCONOCIDO',
    ]);

    $response->assertSessionHasErrors(['nombre', 'estado']);
    $this->assertDatabaseCount('proveedor', 0);
});

test('a user with permission can update a proveedor', function () {
    $proveedor = Proveedor::factory()->create(['nombre' => 'Original']);
    $user = userWithProveedorPermissions('proveedores.ver', 'proveedores.editar');

    $response = $this->actingAs($user)->put(route('proveedores.update', $proveedor), [
        'nombre' => 'Actualizado',
        'nit' => $proveedor->nit,
        'contacto' => $proveedor->contacto,
        'telefono' => $proveedor->telefono,
        'direccion' => $proveedor->direccion,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('proveedores.index'));
    $this->assertDatabaseHas('proveedor', [
        'id' => $proveedor->id,
        'nombre' => 'Actualizado',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a proveedor', function () {
    $proveedor = Proveedor::factory()->create(['nombre' => 'Original']);
    $user = userWithProveedorPermissions('proveedores.ver');

    $this->actingAs($user)->put(route('proveedores.update', $proveedor), [
        'nombre' => 'Actualizado',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('proveedor', ['id' => $proveedor->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a proveedor', function () {
    $proveedor = Proveedor::factory()->create();
    $user = userWithProveedorPermissions('proveedores.ver', 'proveedores.eliminar');

    $response = $this->actingAs($user)->delete(route('proveedores.destroy', $proveedor));

    $response->assertRedirect(route('proveedores.index'));
    $this->assertDatabaseMissing('proveedor', ['id' => $proveedor->id]);
});

test('a user without permission cannot delete a proveedor', function () {
    $proveedor = Proveedor::factory()->create();
    $user = userWithProveedorPermissions('proveedores.ver');

    $this->actingAs($user)->delete(route('proveedores.destroy', $proveedor))
        ->assertForbidden();

    $this->assertDatabaseHas('proveedor', ['id' => $proveedor->id]);
});

test('super-admin bypasses individual permissions', function () {
    Proveedor::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('proveedores.index'))
        ->assertOk();
});
