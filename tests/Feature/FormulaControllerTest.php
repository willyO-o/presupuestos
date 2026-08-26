<?php

use App\Models\Formula;
use App\Models\ProductoMaterial;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['formulas.ver', 'formulas.crear', 'formulas.editar', 'formulas.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithFormulaPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('formulas.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('formulas.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Formula::factory()->count(3)->create();
    $user = userWithFormulaPermissions('formulas.ver');

    $response = $this->actingAs($user)->get(route('formulas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 3)
    );
});

test('the list can be searched by nombre', function () {
    Formula::factory()->create(['nombre' => 'Área simple']);
    Formula::factory()->create(['nombre' => 'Perímetro']);
    $user = userWithFormulaPermissions('formulas.ver');

    $response = $this->actingAs($user)->get(route('formulas.index', ['search' => 'Área']));

    $response->assertInertia(fn ($page) => $page
        ->has('formulas.data', 1)
        ->where('formulas.data.0.nombre', 'Área simple')
    );
});

test('a user with permission can create a formula', function () {
    $user = userWithFormulaPermissions('formulas.ver', 'formulas.crear');

    $response = $this->actingAs($user)->post(route('formulas.store'), [
        'nombre' => 'Área simple',
        'expresion' => 'ancho * alto',
        'descripcion' => 'Área de una cara',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('formulas.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('formula', ['nombre' => 'Área simple', 'expresion' => 'ancho * alto']);
});

test('creating a formula rejects a syntactically invalid expresion', function () {
    $user = userWithFormulaPermissions('formulas.ver', 'formulas.crear');

    $response = $this->actingAs($user)->post(route('formulas.store'), [
        'nombre' => 'Inválida',
        'expresion' => 'ancho * * alto',
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['expresion']);
    $this->assertDatabaseCount('formula', 0);
});

test('a user without permission cannot create a formula', function () {
    $user = userWithFormulaPermissions('formulas.ver');

    $this->actingAs($user)->post(route('formulas.store'), [
        'nombre' => 'Área simple',
        'expresion' => 'ancho * alto',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('formula', 0);
});

test('a user with permission can update a formula', function () {
    $formula = Formula::factory()->create(['nombre' => 'Original', 'expresion' => 'ancho * alto']);
    $user = userWithFormulaPermissions('formulas.ver', 'formulas.editar');

    $response = $this->actingAs($user)->put(route('formulas.update', $formula), [
        'nombre' => 'Actualizada',
        'expresion' => '(ancho + alto) * 2',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('formulas.index'));
    $this->assertDatabaseHas('formula', [
        'id' => $formula->id,
        'nombre' => 'Actualizada',
        'expresion' => '(ancho + alto) * 2',
    ]);
});

test('a user with permission can delete a formula', function () {
    $formula = Formula::factory()->create();
    $user = userWithFormulaPermissions('formulas.ver', 'formulas.eliminar');

    $response = $this->actingAs($user)->delete(route('formulas.destroy', $formula));

    $response->assertRedirect(route('formulas.index'));
    $this->assertDatabaseMissing('formula', ['id' => $formula->id]);
});

test('deleting a formula in use by a producto_material line is restricted', function () {
    $formula = Formula::factory()->create();
    ProductoMaterial::factory()->dinamica($formula)->create();
    $user = userWithFormulaPermissions('formulas.ver', 'formulas.eliminar');

    $this->actingAs($user)->delete(route('formulas.destroy', $formula));

    $this->assertDatabaseHas('formula', ['id' => $formula->id]);
});

test('super-admin bypasses individual permissions', function () {
    Formula::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('formulas.index'))
        ->assertOk();
});

/* ── Probar fórmula (endpoint JSON, sin guardar nada) ─────────────────── */

test('a user with permission can test a formula expression', function () {
    $user = userWithFormulaPermissions('formulas.ver');

    $response = $this->actingAs($user)->postJson(route('formulas.probar'), [
        'expresion' => '(ancho + alto) * 2',
        'ancho' => 2,
        'alto' => 1.5,
    ]);

    $response->assertOk();
    $response->assertJson(['resultado' => 7.0]);
});

test('testing an expression with a missing variable returns a 422 with an error message', function () {
    $user = userWithFormulaPermissions('formulas.ver');

    $response = $this->actingAs($user)->postJson(route('formulas.probar'), [
        'expresion' => 'ancho * alto * profundo',
        'ancho' => 2,
        'alto' => 1.5,
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['error']);
});
