<?php

use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['productos.ver', 'productos.editar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithProductoMaterialPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $producto = Producto::factory()->create();

    $this->get(route('productos.materiales.index', $producto))->assertRedirect(route('login'));
});

test('a user without permission cannot see the recipe', function () {
    $producto = Producto::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('productos.materiales.index', $producto))
        ->assertForbidden();
});

test('a user with permission sees the recipe lines', function () {
    $producto = Producto::factory()->create();
    ProductoMaterial::factory()->for($producto)->count(2)->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->get(route('productos.materiales.index', $producto));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Productos/Receta')
        ->has('lineas', 2)
    );
});

test('a user with permission can add a static line', function () {
    $producto = Producto::factory()->create();
    $material = Material::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->post(route('productos.materiales.store', $producto), [
        'material_id' => $material->id,
        'cantidad_por_unidad' => 1.5,
    ]);

    $response->assertRedirect(route('productos.materiales.index', $producto));
    $this->assertDatabaseHas('producto_material', [
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
    ]);
});

test('a user with permission can add a dynamic line with a formula', function () {
    $producto = Producto::factory()->create();
    $material = Material::factory()->create();
    $formula = Formula::factory()->create(['expresion' => 'ancho * alto']);
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->post(route('productos.materiales.store', $producto), [
        'material_id' => $material->id,
        'formula_id' => $formula->id,
    ]);

    $response->assertRedirect(route('productos.materiales.index', $producto));
    $this->assertDatabaseHas('producto_material', [
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => $formula->id,
        'cantidad_por_unidad' => null,
    ]);
});

test('a line cannot have both a formula and a factor fijo at the same time', function () {
    $producto = Producto::factory()->create();
    $material = Material::factory()->create();
    $formula = Formula::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->post(route('productos.materiales.store', $producto), [
        'material_id' => $material->id,
        'formula_id' => $formula->id,
        'cantidad_por_unidad' => 1.0,
    ]);

    $response->assertSessionHasErrors(['cantidad_por_unidad']);
    $this->assertDatabaseCount('producto_material', 0);
});

test('a line needs either a formula or a factor fijo', function () {
    $producto = Producto::factory()->create();
    $material = Material::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->post(route('productos.materiales.store', $producto), [
        'material_id' => $material->id,
    ]);

    $response->assertSessionHasErrors(['cantidad_por_unidad']);
    $this->assertDatabaseCount('producto_material', 0);
});

test('a user without permission cannot add a line', function () {
    $producto = Producto::factory()->create();
    $material = Material::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.ver');

    $this->actingAs($user)->post(route('productos.materiales.store', $producto), [
        'material_id' => $material->id,
        'cantidad_por_unidad' => 1.0,
    ])->assertForbidden();

    $this->assertDatabaseCount('producto_material', 0);
});

test('a user with permission can update a line', function () {
    $producto = Producto::factory()->create();
    $linea = ProductoMaterial::factory()->for($producto)->create(['cantidad_por_unidad' => 1.0]);
    $material = Material::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->put(
        route('productos.materiales.update', [$producto, $linea]),
        ['material_id' => $material->id, 'cantidad_por_unidad' => 2.5],
    );

    $response->assertRedirect(route('productos.materiales.index', $producto));
    $this->assertDatabaseHas('producto_material', ['id' => $linea->id, 'cantidad_por_unidad' => 2.5]);
});

test('updating a line belonging to another producto is not found', function () {
    $producto = Producto::factory()->create();
    $otroProducto = Producto::factory()->create();
    $linea = ProductoMaterial::factory()->for($otroProducto)->create();
    $material = Material::factory()->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $this->actingAs($user)->put(
        route('productos.materiales.update', [$producto, $linea]),
        ['material_id' => $material->id, 'cantidad_por_unidad' => 2.5],
    )->assertNotFound();
});

test('a user with permission can delete a line', function () {
    $producto = Producto::factory()->create();
    $linea = ProductoMaterial::factory()->for($producto)->create();
    $user = userWithProductoMaterialPermissions('productos.editar');

    $response = $this->actingAs($user)->delete(route('productos.materiales.destroy', [$producto, $linea]));

    $response->assertRedirect(route('productos.materiales.index', $producto));
    $this->assertDatabaseMissing('producto_material', ['id' => $linea->id]);
});

test('super-admin bypasses individual permissions', function () {
    $producto = Producto::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('productos.materiales.index', $producto))
        ->assertOk();
});
