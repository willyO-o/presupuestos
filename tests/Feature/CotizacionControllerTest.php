<?php

use App\Models\CategoriaMaterial;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\Sucursal;
use App\Models\TipoProyecto;
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
    // Alcance TODAS: estas pruebas comprueban el MODULO, no el acotado por
    // sucursal (eso vive en AlcanceSucursalTest y AlcanceModulosTest). Sin
    // alcance la cuenta no veria ninguna fila y todo daria falso negativo.
    $user = User::factory()->todasLasSucursales()->create();
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
        'aplicar_iva' => false,
        // Estos campos NO deben influir en el total guardado:
        'subtotal' => 999999,
        'total' => 999999,
        'iva' => 999999,
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
        ->and((float) $cotizacion->iva)->toBe(0.0)
        ->and((float) $cotizacion->total)->toBe(300.0)
        ->and($cotizacion->codigo_verificacion)->not->toBe('HACKEADO')
        ->and($cotizacion->detalles)->toHaveCount(2)
        // Sin hoja de costos, el precio de la línea solo puede ser manual.
        ->and($cotizacion->detalles->first()->precio_manual)->toBe('SI');

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

    // El IVA lo aplica el motor por defecto (13 % de la base imponible),
    // ya no es un monto que mande el navegador.
    expect($cotizacion->fresh()->detalles)->toHaveCount(1)
        ->and((float) $cotizacion->fresh()->subtotal)->toBe(200.0)
        ->and((float) $cotizacion->fresh()->iva)->toBe(26.0)
        ->and((float) $cotizacion->fresh()->total)->toBe(226.0);
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

/*
|--------------------------------------------------------------------------
| Motor de margen automático (hoja de costos por línea)
|--------------------------------------------------------------------------
|
| Ver tests/Unit/MotorMargenServiceTest.php para la validación numérica
| contra el Excel; acá se comprueba que el controlador lo aplica y que el
| navegador no puede inventarse precios ni rentabilidad.
*/

test('storing a cotizacion prices each line with the margin engine', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $payload = [
        ...cabeceraValida(),
        'aplicar_iva' => true,
        'detalles' => [[
            'descripcion' => 'Mueble exhibidor',
            'tipo_proyecto_id' => $tipoProyecto->id,
            'cantidad' => 1,
            // El navegador manda un precio ridículo: debe ignorarse, porque
            // la línea no está marcada como precio manual.
            'precio_unitario' => 1,
            'items' => [
                ['tipo' => 'MATERIAL', 'descripcion' => 'Fierro', 'cantidad' => 0.55, 'costo_unitario' => 45],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Plancha', 'cantidad' => 0.15, 'costo_unitario' => 145],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Pintura', 'cantidad' => 0.33, 'costo_unitario' => 70],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Otros', 'cantidad' => 1, 'costo_unitario' => 10],
                ['tipo' => 'IMPRESION', 'descripcion' => 'Adhesivo', 'cantidad' => 0.07, 'costo_unitario' => 80],
                ['tipo' => 'MANO_OBRA', 'descripcion' => 'Horas', 'cantidad' => 6, 'costo_unitario' => 15],
            ],
        ]],
    ];

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), $payload)
        ->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();
    $detalle = $cotizacion->detalles->first();

    // Mismos números que la hoja "Muebles Exhibidores" del Excel.
    expect((float) $detalle->costo_base)->toBe(175.20)
        ->and((float) $detalle->costo_ajustado)->toBe(227.76)
        ->and((float) $detalle->precio_unitario)->toBe(341.64)
        ->and((float) $detalle->factor_complejidad)->toBe(1.30)
        ->and((float) $detalle->margen_aplicado)->toBe(0.5)
        ->and($detalle->precio_manual)->toBe('NO')
        ->and($detalle->items)->toHaveCount(6)
        ->and((float) $cotizacion->iva)->toBe(44.41)
        ->and((float) $cotizacion->total)->toBe(386.05)
        ->and((float) $cotizacion->utilidad_real)->toBe(77.72)
        ->and($cotizacion->estado_margen)->toBe('VERDE')
        ->and($cotizacion->recomendacion)->toBe('ACEPTAR');
});

test('the installation amount is added after IVA and is not taxed', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Isla cabecera',
                'tipo_proyecto_id' => $tipoProyecto->id,
                'cantidad' => 1,
                'instalacion' => 100,
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 175.20],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    expect((float) $cotizacion->instalacion)->toBe(100.0)
        // IVA sobre 341,64 (el precio), no sobre 441,64.
        ->and((float) $cotizacion->iva)->toBe(44.41)
        ->and((float) $cotizacion->total)->toBe(486.05);
});

test('a manual price overrides the engine but still feeds the traffic light', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Trabajo negociado',
                'tipo_proyecto_id' => $tipoProyecto->id,
                'cantidad' => 1,
                'precio_manual' => 'SI',
                'precio_unitario' => 250,
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 175.20],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    // 250 sobre un costo ajustado de 227,76 deja una utilidad real mínima.
    expect((float) $cotizacion->subtotal)->toBe(250.0)
        ->and((float) $cotizacion->costo_ajustado)->toBe(227.76)
        ->and($cotizacion->estado_margen)->toBe('ROJO')
        ->and($cotizacion->recomendacion)->toBe('NO ACEPTAR');
});

test('a manual price is required when the seller takes over the price', function () {
    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Sin precio',
                'cantidad' => 1,
                'precio_manual' => 'SI',
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 10],
                ],
            ]],
        ])->assertSessionHasErrors('detalles.0.precio_unitario');
});

test('updating a cotizacion replaces the cost sheet of its lines', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1)->has(CotizacionDetalleItem::factory()->count(3), 'items'), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Línea nueva',
                'cantidad' => 2,
                'items' => [
                    ['tipo' => 'MANO_OBRA', 'descripcion' => 'Horas', 'cantidad' => 4, 'costo_unitario' => 15],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $detalle = $cotizacion->fresh()->detalles->first();

    expect($detalle->items)->toHaveCount(1)
        ->and((float) $detalle->costo_base)->toBe(120.0)
        ->and(CotizacionDetalleItem::query()->count())->toBe(1);
});

test('costear brings back the BOM as prefilled cost sheet rows', function () {
    $producto = Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'M2',
    ]);
    $material = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'precio_unitario' => 100,
        'redondeo_compra' => null,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), [
            'producto_id' => $producto->id,
            'ancho' => 2,
            'alto' => 1.5,
            'tipo_proyecto_id' => $tipoProyecto->id,
        ])
        ->assertOk()
        ->assertJsonPath('costo_material_unitario', 300)
        ->assertJsonPath('insumos.0.cantidad', 3)
        ->assertJsonPath('insumos.0.costo_unitario', 100)
        ->assertJsonPath('insumos.0.material_id', $material->id)
        // 300 × 1,3 × 1,5 = 585
        ->assertJsonPath('precio_sugerido', 585)
        ->assertJsonPath('motor.estado', 'VERDE');
});

test('simular runs the engine over a hand written cost sheet', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.simular'), [
            'costo_base' => 175.20,
            'tipo_proyecto_id' => $tipoProyecto->id,
        ])
        ->assertOk()
        ->assertJsonPath('costo_ajustado', 227.76)
        ->assertJsonPath('precio', 341.64)
        ->assertJsonPath('utilidad_real', 77.72)
        ->assertJsonPath('estado', 'VERDE');
});
