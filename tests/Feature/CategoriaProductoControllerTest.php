<?php

use App\Models\CategoriaProducto;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['categorias-producto.ver', 'categorias-producto.crear', 'categorias-producto.editar', 'categorias-producto.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithCategoriaProductoPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('categorias-producto.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('categorias-producto.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    CategoriaProducto::factory()->count(3)->create();
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $response = $this->actingAs($user)->get(route('categorias-producto.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CategoriasProducto/Index')
        ->has('categoriasProducto.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    CategoriaProducto::factory()->create(['nombre' => 'Banners']);
    CategoriaProducto::factory()->create(['nombre' => 'Toldos']);
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $response = $this->actingAs($user)->get(route('categorias-producto.index', ['search' => 'Bann']));

    $response->assertInertia(fn ($page) => $page
        ->has('categoriasProducto.data', 1)
        ->where('categoriasProducto.data.0.nombre', 'Banners')
    );
});

test('the list can be filtered by estado', function () {
    CategoriaProducto::factory()->create(['estado' => 'ACTIVO']);
    CategoriaProducto::factory()->inactivo()->create();
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $response = $this->actingAs($user)->get(route('categorias-producto.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('categoriasProducto.data', 1)
        ->where('categoriasProducto.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a categoria producto', function () {
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver', 'categorias-producto.crear');

    $response = $this->actingAs($user)->post(route('categorias-producto.store'), [
        'nombre' => 'Exhibidores',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('categorias-producto.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('categoria_producto', ['nombre' => 'Exhibidores']);
});

test('a user without permission cannot create a categoria producto', function () {
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $this->actingAs($user)->post(route('categorias-producto.store'), [
        'nombre' => 'Exhibidores',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('categoria_producto', 0);
});

test('creating a categoria producto requires nombre and a valid estado', function () {
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver', 'categorias-producto.crear');

    $response = $this->actingAs($user)->post(route('categorias-producto.store'), [
        'nombre' => '',
        'estado' => 'DESCONOCIDO',
    ]);

    $response->assertSessionHasErrors(['nombre', 'estado']);
    $this->assertDatabaseCount('categoria_producto', 0);
});

test('a user with permission can update a categoria producto', function () {
    $categoria = CategoriaProducto::factory()->create(['nombre' => 'Original']);
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver', 'categorias-producto.editar');

    $response = $this->actingAs($user)->put(route('categorias-producto.update', $categoria), [
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('categorias-producto.index'));
    $this->assertDatabaseHas('categoria_producto', [
        'id' => $categoria->id,
        'nombre' => 'Actualizada',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a categoria producto', function () {
    $categoria = CategoriaProducto::factory()->create(['nombre' => 'Original']);
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $this->actingAs($user)->put(route('categorias-producto.update', $categoria), [
        'nombre' => 'Actualizada',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('categoria_producto', ['id' => $categoria->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a categoria producto', function () {
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver', 'categorias-producto.eliminar');

    $response = $this->actingAs($user)->delete(route('categorias-producto.destroy', $categoria));

    $response->assertRedirect(route('categorias-producto.index'));
    $this->assertDatabaseMissing('categoria_producto', ['id' => $categoria->id]);
});

test('a user without permission cannot delete a categoria producto', function () {
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithCategoriaProductoPermissions('categorias-producto.ver');

    $this->actingAs($user)->delete(route('categorias-producto.destroy', $categoria))
        ->assertForbidden();

    $this->assertDatabaseHas('categoria_producto', ['id' => $categoria->id]);
});

test('super-admin bypasses individual permissions', function () {
    CategoriaProducto::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('categorias-producto.index'))
        ->assertOk();
});
