<?php

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

/*
|--------------------------------------------------------------------------
| Lista blanca del cotizador público
|--------------------------------------------------------------------------
*/

test('un producto nace fuera del cotizador publico si no se dice lo contrario', function () {
    // Publicar un producto en el sitio le da precio a la vista de cualquiera,
    // competencia incluida: tiene que ser una decisión explícita y no algo que
    // pase por omitir un campo (ver add_cotizable_web_to_producto_table).
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.crear');

    $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => $categoria->id,
        'nombre' => 'Producto recien creado',
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        'estado' => 'ACTIVO',
    ])->assertRedirect(route('productos.index'));

    $this->assertDatabaseHas('producto', [
        'nombre' => 'Producto recien creado',
        'cotizable_web' => 'NO',
    ]);
});

test('se puede publicar un producto en el cotizador desde el CRUD', function () {
    $producto = Producto::factory()->create(['cotizable_web' => 'NO']);
    $user = userWithProductoPermissions('productos.ver', 'productos.editar');

    $this->actingAs($user)->put(route('productos.update', $producto), [
        'categoria_producto_id' => $producto->categoria_producto_id,
        'nombre' => $producto->nombre,
        'unidad_medida' => $producto->unidad_medida,
        'requiere_medidas' => $producto->requiere_medidas,
        'cotizable_web' => 'SI',
        'estado' => 'ACTIVO',
    ])->assertRedirect(route('productos.index'));

    expect($producto->fresh()->cotizable_web)->toBe('SI');
});

test('no se acepta cualquier valor en la bandera del cotizador', function () {
    $producto = Producto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.editar');

    $this->actingAs($user)->put(route('productos.update', $producto), [
        'categoria_producto_id' => $producto->categoria_producto_id,
        'nombre' => $producto->nombre,
        'unidad_medida' => $producto->unidad_medida,
        'requiere_medidas' => $producto->requiere_medidas,
        'cotizable_web' => 'TALVEZ',
        'estado' => 'ACTIVO',
    ])->assertSessionHasErrors('cotizable_web');
});

/*
|--------------------------------------------------------------------------
| Imagen referencial: se convierte a JPG para pesar menos en el servidor
|--------------------------------------------------------------------------
*/

test('uploading an imagen converts it to jpg', function () {
    Storage::fake('public');
    $categoria = CategoriaProducto::factory()->create();
    $user = userWithProductoPermissions('productos.ver', 'productos.crear');

    $this->actingAs($user)->post(route('productos.store'), [
        'categoria_producto_id' => $categoria->id,
        'nombre' => 'Con imagen',
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        'estado' => 'ACTIVO',
        'imagen' => UploadedFile::fake()->image('foto.png', 300, 300),
    ])->assertRedirect(route('productos.index'));

    $producto = Producto::where('nombre', 'Con imagen')->firstOrFail();

    expect($producto->imagen)->not->toBeNull()
        ->and($producto->imagen)->toEndWith('.jpg');
    Storage::disk('public')->assertExists($producto->imagen);
});

test('uploading a new imagen removes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('productos/viejo.jpg', 'contenido');
    $producto = Producto::factory()->create(['imagen' => 'productos/viejo.jpg']);
    $user = userWithProductoPermissions('productos.ver', 'productos.editar');

    $this->actingAs($user)->put(route('productos.update', $producto), [
        'categoria_producto_id' => $producto->categoria_producto_id,
        'nombre' => $producto->nombre,
        'unidad_medida' => $producto->unidad_medida,
        'requiere_medidas' => $producto->requiere_medidas,
        'estado' => 'ACTIVO',
        'imagen' => UploadedFile::fake()->image('nueva.png'),
    ])->assertRedirect(route('productos.index'));

    Storage::disk('public')->assertMissing('productos/viejo.jpg');
    expect($producto->fresh()->imagen)->not->toBe('productos/viejo.jpg');
});

test('deleting a producto removes its imagen from disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('productos/foto.jpg', 'contenido');
    $producto = Producto::factory()->create(['imagen' => 'productos/foto.jpg']);
    $user = userWithProductoPermissions('productos.ver', 'productos.eliminar');

    $this->actingAs($user)->delete(route('productos.destroy', $producto))
        ->assertRedirect(route('productos.index'));

    Storage::disk('public')->assertMissing('productos/foto.jpg');
});
