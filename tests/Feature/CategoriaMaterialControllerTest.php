<?php

use App\Models\CategoriaMaterial;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['categorias-material.ver', 'categorias-material.crear', 'categorias-material.editar', 'categorias-material.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithCategoriaMaterialPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('categorias-material.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('categorias-material.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    CategoriaMaterial::factory()->count(3)->create();
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $response = $this->actingAs($user)->get(route('categorias-material.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CategoriasMaterial/Index')
        ->has('categoriasMaterial.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    CategoriaMaterial::factory()->create(['nombre' => 'Gigantografía']);
    CategoriaMaterial::factory()->create(['nombre' => 'Cerrajería']);
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $response = $this->actingAs($user)->get(route('categorias-material.index', ['search' => 'Giganto']));

    $response->assertInertia(fn ($page) => $page
        ->has('categoriasMaterial.data', 1)
        ->where('categoriasMaterial.data.0.nombre', 'Gigantografía')
    );
});

test('the list can be filtered by estado', function () {
    CategoriaMaterial::factory()->create(['estado' => 'ACTIVO']);
    CategoriaMaterial::factory()->inactivo()->create();
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $response = $this->actingAs($user)->get(route('categorias-material.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('categoriasMaterial.data', 1)
        ->where('categoriasMaterial.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a categoria material', function () {
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver', 'categorias-material.crear');

    $response = $this->actingAs($user)->post(route('categorias-material.store'), [
        'nombre' => 'Pinturas',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('categorias-material.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('categoria_material', ['nombre' => 'Pinturas']);
});

test('a user without permission cannot create a categoria material', function () {
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $this->actingAs($user)->post(route('categorias-material.store'), [
        'nombre' => 'Pinturas',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('categoria_material', 0);
});

test('creating a categoria material requires nombre and a valid estado', function () {
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver', 'categorias-material.crear');

    $response = $this->actingAs($user)->post(route('categorias-material.store'), [
        'nombre' => '',
        'estado' => 'DESCONOCIDO',
    ]);

    $response->assertSessionHasErrors(['nombre', 'estado']);
    $this->assertDatabaseCount('categoria_material', 0);
});

test('a user with permission can update a categoria material', function () {
    $categoria = CategoriaMaterial::factory()->create(['nombre' => 'Original']);
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver', 'categorias-material.editar');

    $response = $this->actingAs($user)->put(route('categorias-material.update', $categoria), [
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('categorias-material.index'));
    $this->assertDatabaseHas('categoria_material', [
        'id' => $categoria->id,
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a categoria material', function () {
    $categoria = CategoriaMaterial::factory()->create(['nombre' => 'Original']);
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $this->actingAs($user)->put(route('categorias-material.update', $categoria), [
        'nombre' => 'Actualizada',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('categoria_material', ['id' => $categoria->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a categoria material', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver', 'categorias-material.eliminar');

    $response = $this->actingAs($user)->delete(route('categorias-material.destroy', $categoria));

    $response->assertRedirect(route('categorias-material.index'));
    $this->assertDatabaseMissing('categoria_material', ['id' => $categoria->id]);
});

test('a user without permission cannot delete a categoria material', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithCategoriaMaterialPermissions('categorias-material.ver');

    $this->actingAs($user)->delete(route('categorias-material.destroy', $categoria))
        ->assertForbidden();

    $this->assertDatabaseHas('categoria_material', ['id' => $categoria->id]);
});

test('super-admin bypasses individual permissions', function () {
    CategoriaMaterial::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('categorias-material.index'))
        ->assertOk();
});
