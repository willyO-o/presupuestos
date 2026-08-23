<?php

use App\Models\Area;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['areas.ver', 'areas.crear', 'areas.editar', 'areas.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithAreaPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('areas.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('areas.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Area::factory()->count(3)->create();
    $user = userWithAreaPermissions('areas.ver');

    $response = $this->actingAs($user)->get(route('areas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Areas/Index')
        ->has('areas.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    Area::factory()->create(['nombre' => 'Gigantografía']);
    Area::factory()->create(['nombre' => 'Carpintería']);
    $user = userWithAreaPermissions('areas.ver');

    $response = $this->actingAs($user)->get(route('areas.index', ['search' => 'Gigant']));

    $response->assertInertia(fn ($page) => $page
        ->has('areas.data', 1)
        ->where('areas.data.0.nombre', 'Gigantografía')
    );
});

test('the list can be filtered by estado', function () {
    Area::factory()->create(['estado' => 'ACTIVO']);
    Area::factory()->inactivo()->create();
    $user = userWithAreaPermissions('areas.ver');

    $response = $this->actingAs($user)->get(route('areas.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('areas.data', 1)
        ->where('areas.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create an area', function () {
    $user = userWithAreaPermissions('areas.ver', 'areas.crear');

    $response = $this->actingAs($user)->post(route('areas.store'), [
        'nombre' => 'Diseño',
        'descripcion' => 'Área de diseño gráfico',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('areas.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('area', ['nombre' => 'Diseño']);
});

test('an area can be created without descripcion', function () {
    $user = userWithAreaPermissions('areas.ver', 'areas.crear');

    $response = $this->actingAs($user)->post(route('areas.store'), [
        'nombre' => 'Ventas',
        'descripcion' => '',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('areas.index'));
    $this->assertDatabaseHas('area', ['nombre' => 'Ventas', 'descripcion' => null]);
});

test('a user without permission cannot create an area', function () {
    $user = userWithAreaPermissions('areas.ver');

    $this->actingAs($user)->post(route('areas.store'), [
        'nombre' => 'Diseño',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('area', 0);
});

test('creating an area requires nombre and a valid estado', function () {
    $user = userWithAreaPermissions('areas.ver', 'areas.crear');

    $response = $this->actingAs($user)->post(route('areas.store'), [
        'nombre' => '',
        'estado' => 'DESCONOCIDO',
    ]);

    $response->assertSessionHasErrors(['nombre', 'estado']);
    $this->assertDatabaseCount('area', 0);
});

test('a user with permission can update an area', function () {
    $area = Area::factory()->create(['nombre' => 'Original']);
    $user = userWithAreaPermissions('areas.ver', 'areas.editar');

    $response = $this->actingAs($user)->put(route('areas.update', $area), [
        'nombre' => 'Actualizada',
        'descripcion' => $area->descripcion,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('areas.index'));
    $this->assertDatabaseHas('area', [
        'id' => $area->id,
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update an area', function () {
    $area = Area::factory()->create(['nombre' => 'Original']);
    $user = userWithAreaPermissions('areas.ver');

    $this->actingAs($user)->put(route('areas.update', $area), [
        'nombre' => 'Actualizada',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('area', ['id' => $area->id, 'nombre' => 'Original']);
});

test('a user with permission can delete an area', function () {
    $area = Area::factory()->create();
    $user = userWithAreaPermissions('areas.ver', 'areas.eliminar');

    $response = $this->actingAs($user)->delete(route('areas.destroy', $area));

    $response->assertRedirect(route('areas.index'));
    $this->assertDatabaseMissing('area', ['id' => $area->id]);
});

test('a user without permission cannot delete an area', function () {
    $area = Area::factory()->create();
    $user = userWithAreaPermissions('areas.ver');

    $this->actingAs($user)->delete(route('areas.destroy', $area))
        ->assertForbidden();

    $this->assertDatabaseHas('area', ['id' => $area->id]);
});

test('super-admin bypasses individual permissions', function () {
    Area::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('areas.index'))
        ->assertOk();
});
