<?php

use App\Models\CategoriaMaterial;
use App\Models\Material;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithMaterialPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('materiales.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('materiales.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Material::factory()->count(3)->create();
    $user = userWithMaterialPermissions('materiales.ver');

    $response = $this->actingAs($user)->get(route('materiales.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Materiales/Index')
        ->has('materiales.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    Material::factory()->create(['nombre' => 'Lona FrontLight 3,20x50m']);
    Material::factory()->create(['nombre' => 'Tubo 20x20x0,9mm']);
    $user = userWithMaterialPermissions('materiales.ver');

    $response = $this->actingAs($user)->get(route('materiales.index', ['search' => 'Lona']));

    $response->assertInertia(fn ($page) => $page
        ->has('materiales.data', 1)
        ->where('materiales.data.0.nombre', 'Lona FrontLight 3,20x50m')
    );
});

test('the list can be filtered by categoria', function () {
    $categoria = CategoriaMaterial::factory()->create();
    Material::factory()->create(['categoria_material_id' => $categoria->id]);
    Material::factory()->create();
    $user = userWithMaterialPermissions('materiales.ver');

    $response = $this->actingAs($user)->get(route('materiales.index', ['categoria' => $categoria->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('materiales.data', 1)
        ->where('materiales.data.0.categoria_material_id', $categoria->id)
    );
});

test('the list can be filtered by estado', function () {
    Material::factory()->create(['estado' => 'ACTIVO']);
    Material::factory()->inactivo()->create();
    $user = userWithMaterialPermissions('materiales.ver');

    $response = $this->actingAs($user)->get(route('materiales.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('materiales.data', 1)
        ->where('materiales.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a material', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithMaterialPermissions('materiales.ver', 'materiales.crear');

    $response = $this->actingAs($user)->post(route('materiales.store'), [
        'categoria_material_id' => $categoria->id,
        'nombre' => 'Lona FrontLight 3,20x50m',
        'presentacion' => 'Rollo 3,20x50m',
        'unidad_medida' => 'M2',
        'precio_presentacion' => 850,
        'precio_unitario' => 5.3,
        'stock_actual' => 20,
        'stock_minimo' => 5,
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('materiales.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('material', ['nombre' => 'Lona FrontLight 3,20x50m', 'categoria_material_id' => $categoria->id]);
});

test('a material can be created with a redondeo_compra, and it is optional', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithMaterialPermissions('materiales.crear');

    $base = [
        'categoria_material_id' => $categoria->id,
        'presentacion' => 'Plancha 1,22x2,44m',
        'unidad_medida' => 'M2',
        'precio_presentacion' => 520,
        'precio_unitario' => 175,
        'stock_actual' => 10,
        'stock_minimo' => 2,
        'estado' => 'ACTIVO',
    ];

    $this->actingAs($user)->post(route('materiales.store'), [
        ...$base, 'nombre' => 'Acrílico 3mm', 'redondeo_compra' => 2.98,
    ])->assertRedirect(route('materiales.index'));

    $this->actingAs($user)->post(route('materiales.store'), [
        ...$base, 'nombre' => 'Lona', 'redondeo_compra' => '',
    ])->assertRedirect(route('materiales.index'));

    expect((float) Material::where('nombre', 'Acrílico 3mm')->value('redondeo_compra'))->toBe(2.98)
        ->and(Material::where('nombre', 'Lona')->value('redondeo_compra'))->toBeNull();
});

test('a negative redondeo_compra is rejected', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithMaterialPermissions('materiales.crear');

    $this->actingAs($user)->post(route('materiales.store'), [
        'categoria_material_id' => $categoria->id,
        'nombre' => 'X',
        'presentacion' => 'Y',
        'unidad_medida' => 'M2',
        'precio_presentacion' => 10,
        'precio_unitario' => 10,
        'stock_actual' => 0,
        'stock_minimo' => 0,
        'redondeo_compra' => -1,
        'estado' => 'ACTIVO',
    ])->assertSessionHasErrors('redondeo_compra');
});

test('a user without permission cannot create a material', function () {
    $categoria = CategoriaMaterial::factory()->create();
    $user = userWithMaterialPermissions('materiales.ver');

    $this->actingAs($user)->post(route('materiales.store'), [
        'categoria_material_id' => $categoria->id,
        'nombre' => 'Lona FrontLight 3,20x50m',
        'presentacion' => 'Rollo 3,20x50m',
        'unidad_medida' => 'M2',
        'precio_presentacion' => 850,
        'precio_unitario' => 5.3,
        'stock_actual' => 20,
        'stock_minimo' => 5,
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('material', 0);
});

test('creating a material requires a valid categoria, nombre and unidad_medida', function () {
    $user = userWithMaterialPermissions('materiales.ver', 'materiales.crear');

    $response = $this->actingAs($user)->post(route('materiales.store'), [
        'categoria_material_id' => 999,
        'nombre' => '',
        'presentacion' => '',
        'unidad_medida' => 'KG',
        'precio_presentacion' => -5,
        'precio_unitario' => -5,
        'stock_actual' => 0,
        'stock_minimo' => 0,
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['categoria_material_id', 'nombre', 'presentacion', 'unidad_medida', 'precio_presentacion', 'precio_unitario']);
    $this->assertDatabaseCount('material', 0);
});

test('a user with permission can update a material', function () {
    $material = Material::factory()->create(['nombre' => 'Original']);
    $user = userWithMaterialPermissions('materiales.ver', 'materiales.editar');

    $response = $this->actingAs($user)->put(route('materiales.update', $material), [
        'categoria_material_id' => $material->categoria_material_id,
        'nombre' => 'Actualizado',
        'presentacion' => $material->presentacion,
        'unidad_medida' => $material->unidad_medida,
        'precio_presentacion' => $material->precio_presentacion,
        'precio_unitario' => $material->precio_unitario,
        'stock_actual' => $material->stock_actual,
        'stock_minimo' => $material->stock_minimo,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('materiales.index'));
    $this->assertDatabaseHas('material', [
        'id' => $material->id,
        'nombre' => 'Actualizado',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update a material', function () {
    $material = Material::factory()->create(['nombre' => 'Original']);
    $user = userWithMaterialPermissions('materiales.ver');

    $this->actingAs($user)->put(route('materiales.update', $material), [
        'categoria_material_id' => $material->categoria_material_id,
        'nombre' => 'Actualizado',
        'presentacion' => $material->presentacion,
        'unidad_medida' => $material->unidad_medida,
        'precio_presentacion' => $material->precio_presentacion,
        'precio_unitario' => $material->precio_unitario,
        'stock_actual' => $material->stock_actual,
        'stock_minimo' => $material->stock_minimo,
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('material', ['id' => $material->id, 'nombre' => 'Original']);
});

test('a user with permission can delete a material', function () {
    $material = Material::factory()->create();
    $user = userWithMaterialPermissions('materiales.ver', 'materiales.eliminar');

    $response = $this->actingAs($user)->delete(route('materiales.destroy', $material));

    $response->assertRedirect(route('materiales.index'));
    $this->assertDatabaseMissing('material', ['id' => $material->id]);
});

test('a user without permission cannot delete a material', function () {
    $material = Material::factory()->create();
    $user = userWithMaterialPermissions('materiales.ver');

    $this->actingAs($user)->delete(route('materiales.destroy', $material))
        ->assertForbidden();

    $this->assertDatabaseHas('material', ['id' => $material->id]);
});

test('super-admin bypasses individual permissions', function () {
    Material::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('materiales.index'))
        ->assertOk();
});
