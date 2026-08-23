<?php

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithProductoPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('productos.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('productos.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Producto::factory()->count(3)->create();
    $user = userWithProductoPermissions('productos.ver');

    $response = $this->actingAs($user)->get(route('productos.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Productos/Index')
        ->has('productos.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    Producto::factory()->create(['nombre' => 'Bastidor lona PVC 1440dpi']);
    Producto::factory()->create(['nombre' => 'Banner vinilo']);
    $user = userWithProductoPermissions('productos.ver');

    $response = $this->actingAs($user)->get(route('productos.index', ['search' => 'Bastidor']));

    $response->assertInertia(fn ($page) => $page
        ->has('productos.data', 1)
        ->where('productos.data.0.nombre', 'Bastidor lona PVC 1440dpi')
    );
});

test('the list can be filtered by categoria', function () {
    $categoria = CategoriaProducto::factory()->create();
    Producto::factory()->create(['categoria_producto_id' => $categoria->id]);
    Producto::factory()->create();
    $user = userWithProductoPermissions('productos.ver');

    $response = $this->actingAs($user)->get(route('productos.index', ['categoria' => $categoria->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('productos.data', 1)
        ->where('productos.data.0.categoria_producto_id', $categoria->id)
    );
});

test('the list can be filtered by estado', function () {
    Producto::factory()->create(['estado' => 'ACTIVO']);
    Producto::factory()->inactivo()->create();
    $user = userWithProductoPermissions('productos.ver');

    $response = $this->actingAs($user)->get(route('productos.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('productos.data', 1)
        ->where('productos.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a producto', function () {
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.crear');

    $response = $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => $categoria->id,
        'nombre' => 'Bastidor lona PVC 1440dpi',
        'descripcion' => 'Bastidor con lona impresa',
        'unidad_medida' => 'M2',
        'precio_base' => 120,
        'requiere_medidas' => 'SI',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('productos.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('producto', ['nombre' => 'Bastidor lona PVC 1440dpi', 'categoria_producto_id' => $categoria->id]);
});

test('a producto can be created without precio_base', function () {
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.crear');

    $response = $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => $categoria->id,
        'nombre' => 'Producto sin precio base',
        'unidad_medida' => 'UNIDAD',
        'precio_base' => '',
        'requiere_medidas' => 'NO',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseHas('producto', ['nombre' => 'Producto sin precio base', 'precio_base' => null]);
});

test('a user without permission cannot create a producto', function () {
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithProductoPermissions('productos.ver');

    $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => $categoria->id,
        'nombre' => 'Bastidor lona PVC 1440dpi',
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('producto', 0);
});

test('creating a producto requires a valid categoria, nombre and unidad_medida', function () {
    $user = userWithProductoPermissions('productos.ver', 'productos.crear');

    $response = $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => 999,
        'nombre' => '',
        'unidad_medida' => 'KG',
        'requiere_medidas' => 'TALVEZ',
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['categoria_producto_id', 'nombre', 'unidad_medida', 'requiere_medidas']);
    $this->assertDatabaseCount('producto', 0);
});

test('a user with permission can update a producto', function () {
    $producto = Producto::factory()->create(['nombre' => 'Original']);
    $user = userWithProductoPermissions('productos.ver', 'productos.editar');

    $response = $this->actingAs($user)->put(route('productos.update', $producto), [
        'categoria_producto_id' => $producto->categoria_producto_id,
        'nombre' => 'Actualizado',
        'unidad_medida' => $producto->unidad_medida,
        'requiere_medidas' => $producto->requiere_medidas,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseHas('producto', [
        'id' => $producto->id,
        'nombre' => 'Actualizado',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a producto', function () {
    $producto = Producto::factory()->create(['nombre' => 'Original']);
    $user = userWithProductoPermissions('productos.ver');

    $this->actingAs($user)->put(route('productos.update', $producto), [
        'categoria_producto_id' => $producto->categoria_producto_id,
        'nombre' => 'Actualizado',
        'unidad_medida' => $producto->unidad_medida,
        'requiere_medidas' => $producto->requiere_medidas,
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('producto', ['id' => $producto->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a producto', function () {
    $producto = Producto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.eliminar');

    $response = $this->actingAs($user)->delete(route('productos.destroy', $producto));

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseMissing('producto', ['id' => $producto->id]);
});

test('a user without permission cannot delete a producto', function () {
    $producto = Producto::factory()->create();
    $user = userWithProductoPermissions('productos.ver');

    $this->actingAs($user)->delete(route('productos.destroy', $producto))
        ->assertForbidden();

    $this->assertDatabaseHas('producto', ['id' => $producto->id]);
});

test('super-admin bypasses individual permissions', function () {
    Producto::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('productos.index'))
        ->assertOk();
});
