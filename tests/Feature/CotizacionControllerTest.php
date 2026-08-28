<?php

use App\Models\CategoriaMaterial;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar',
        'cotizaciones.aprobar', 'cotizaciones.eliminar',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWith(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function cabeceraValida(): array
{
    return [
        'cliente_id' => Cliente::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'sucursal_id' => Sucursal::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(15)->toDateString(),
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('cotizaciones.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cotizaciones.index'))
        ->assertForbidden();
});

test('a user with permission sees the list', function () {
    Cotizacion::factory()->count(3)->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Index')->has('cotizaciones.data', 3));
});

test('the show page renders the cotizacion with its detalle', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(2), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.show', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cotizaciones/Show')
            ->has('cotizacion.detalles', 2));
});

test('the edit page renders for a pending cotizacion', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Edit')->has('productos'));
});

test('the create page requires the crear permission', function () {
    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.create'))
        ->assertForbidden();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->get(route('cotizaciones.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Create'));
});

test('storing a cotizacion computes totals on the server and ignores client amounts', function () {
    $user = userWith('cotizaciones.crear');

    $payload = [
        ...cabeceraValida(),
        'descuento' => 50,
        'impuesto' => 0,
        // Estos campos NO deben influir en el total guardado:
        'subtotal' => 999999,
        'total' => 999999,
        'codigo_verificacion' => 'HACKEADO',
        'estado' => 'APROBADA',
        'detalles' => [
            ['descripcion' => 'Banner', 'ancho' => 2, 'alto' => 1, 'cantidad' => 3, 'precio_unitario' => 100],
            ['descripcion' => 'Vinil', 'cantidad' => 2, 'precio_unitario' => 25],
        ],
    ];

    $response = $this->actingAs($user)->post(route('cotizaciones.store'), $payload);

    $cotizacion = Cotizacion::latest('id')->first();

    $response->assertRedirect(route('cotizaciones.show', $cotizacion));
    expect($cotizacion->estado)->toBe('PENDIENTE')
        ->and((float) $cotizacion->subtotal)->toBe(350.0)
        ->and((float) $cotizacion->descuento)->toBe(50.0)
        ->and((float) $cotizacion->total)->toBe(300.0)
        ->and($cotizacion->codigo_verificacion)->not->toBe('HACKEADO')
        ->and($cotizacion->detalles)->toHaveCount(2);

    $this->assertDatabaseHas('cotizacion_detalle', [
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Banner',
        'area_m2' => 2,
        'subtotal' => 300,
    ]);
});

test('a cotizacion needs at least one detalle line', function () {
    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [...cabeceraValida(), 'detalles' => []])
        ->assertSessionHasErrors('detalles');
});

test('an approved cotizacion cannot be edited', function () {
    $cotizacion = Cotizacion::factory()->aprobada()->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [['descripcion' => 'X', 'cantidad' => 1, 'precio_unitario' => 10]],
        ])
        ->assertRedirect(route('cotizaciones.show', $cotizacion))
        ->assertSessionHas('error');
});

test('updating a pending cotizacion replaces its detalle', function () {
    $cotizacion = Cotizacion::factory()->has(CotizacionDetalle::factory()->count(3), 'detalles')->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [['descripcion' => 'Único ítem', 'cantidad' => 1, 'precio_unitario' => 200]],
        ])
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    expect($cotizacion->fresh()->detalles)->toHaveCount(1)
        ->and((float) $cotizacion->fresh()->total)->toBe(200.0);
});

test('aprobar changes the estado and requires the aprobar permission', function () {
    $cotizacion = Cotizacion::factory()->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertForbidden();

    $this->actingAs(userWith('cotizaciones.aprobar'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    expect($cotizacion->fresh()->estado)->toBe('APROBADA');
});

test('only a pending cotizacion can be approved', function () {
    $cotizacion = Cotizacion::factory()->rechazada()->create();

    $this->actingAs(userWith('cotizaciones.aprobar'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertSessionHas('error');

    expect($cotizacion->fresh()->estado)->toBe('RECHAZADA');
});

test('a converted cotizacion cannot be deleted', function () {
    $cotizacion = Cotizacion::factory()->convertida()->create();

    $this->actingAs(userWith('cotizaciones.eliminar'))
        ->delete(route('cotizaciones.destroy', $cotizacion))
        ->assertSessionHas('error');

    $this->assertModelExists($cotizacion);
});

test('costear returns the suggested price from the product BOM', function () {
    $categoriaProducto = CategoriaProducto::factory()->create();
    $producto = Producto::factory()->create([
        'categoria_producto_id' => $categoriaProducto->id,
        'unidad_medida' => 'M2',
    ]);
    $material = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'precio_unitario' => 100,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $response = $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), [
            'producto_id' => $producto->id,
            'ancho' => 2,
            'alto' => 1.5,
        ]);

    // driver M2 = area = 3 ; costo material unitario = 3 * 100 = 300
    $response->assertOk()
        ->assertJsonPath('costo_material_unitario', 300)
        ->assertJson(fn ($json) => $json->where('precio_sugerido', fn ($v) => $v > 300)->etc());
});

test('costear returns 422 when the product needs measures it did not get', function () {
    $producto = Producto::factory()->create(['unidad_medida' => 'M2']);
    Formula::query()->delete();
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $producto->id])
        ->assertStatus(422);
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('cotizaciones.index'))->assertOk();
});
