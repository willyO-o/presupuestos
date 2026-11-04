<?php

use App\Models\CategoriaMaterial;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Calculo\PrecioSugeridoService;
use Database\Seeders\CategoriaMaterialSeeder;
use Database\Seeders\CategoriaProductoSeeder;
use Database\Seeders\FormulaSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\MuebleSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    collect([
        'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar',
        'productos.crear', 'productos.editar',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function usuarioMuebles(string ...$permissions): User
{
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function plantillaMueble(array $atributos = []): Producto
{
    return Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'UNIDAD',
        'requiere_medidas' => 'SI',
        'modo_cotizacion' => 'MUEBLE',
        'costo_mano_obra' => 500,
        'estado' => 'ACTIVO',
        ...$atributos,
    ]);
}

function productoGeneral(): Producto
{
    return Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'modo_cotizacion' => 'GENERAL',
        'estado' => 'ACTIVO',
    ]);
}

/**
 * Una cotización de muebles como la arma el asistente: una línea con la
 * plantilla, las cuatro medidas, los materiales y la mano de obra.
 *
 * @return array<string, mixed>
 */
function cotizacionDeMueble(Producto $plantilla, array $linea = []): array
{
    return [
        'tipo' => 'MUEBLE',
        'cliente_id' => Cliente::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'sucursal_id' => Sucursal::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'aplicar_iva' => false,
        'detalles' => [[
            'producto_id' => $plantilla->id,
            'descripcion' => "Elaborado de isla\nBase metálica con MDF",
            'ancho' => 0.7,
            'alto' => 1.6,
            'profundidad' => 0.7,
            'niveles' => 4,
            'cantidad' => 2,
            'items' => [
                ['tipo' => 'MATERIAL', 'descripcion' => 'Tubo 20x20', 'unidad' => 'METRO_LINEAL', 'cantidad' => 18, 'costo_unitario' => 11],
                ['tipo' => 'MANO_OBRA', 'descripcion' => 'Mano de obra (carpintero)', 'unidad' => 'SERVICIO', 'cantidad' => 1, 'costo_unitario' => 500],
            ],
            ...$linea,
        ]],
    ];
}

test('the furniture quoter only offers furniture templates', function () {
    $mueble = plantillaMueble();
    productoGeneral();

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->get(route('cotizaciones.muebles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cotizaciones/Muebles/Create')
            ->has('productos', 1)
            ->where('productos.0.id', $mueble->id)
            ->where('productos.0.costo_mano_obra', fn ($valor) => (float) $valor === 500.0));
});

test('the general form no longer offers furniture templates', function () {
    plantillaMueble();
    $general = productoGeneral();

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->get(route('cotizaciones.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Cotizaciones/Create')
            ->has('productos', 1)
            ->where('productos.0.id', $general->id));
});

test('the furniture quoter offers the active materials catalog', function () {
    $material = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'nombre' => 'Tubo cuadrado de prueba',
        'estado' => 'ACTIVO',
    ]);
    Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'estado' => 'INACTIVO',
    ]);

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->get(route('cotizaciones.muebles.create'))
        ->assertInertia(fn ($page) => $page
            ->has('materiales', 1)
            ->where('materiales.0.id', $material->id));

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->get(route('cotizaciones.create'))
        ->assertInertia(fn ($page) => $page->missing('materiales'));
});

test('the price modality is saved and the manual ones keep the typed price', function (string $modalidad, string $precioManual) {
    $plantilla = plantillaMueble();

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), cotizacionDeMueble($plantilla, [
            'modalidad' => $modalidad,
            'precio_manual' => 'NO',
            'precio_unitario' => 896,
        ]))
        ->assertSessionHasNoErrors();

    $detalle = CotizacionDetalle::firstOrFail();

    expect($detalle->modalidad)->toBe($modalidad)
        ->and($detalle->precio_manual)->toBe($precioManual);

    if ($precioManual === 'SI') {
        expect((float) $detalle->precio_unitario)->toBe(896.0);
    }
})->with([
    'por superficie' => ['SUPERFICIE', 'SI'],
    'precio manual' => ['PRECIO_MANUAL', 'SI'],
    'composición' => ['COMPOSICION', 'NO'],
]);

test('a manual price modality needs the price', function () {
    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), cotizacionDeMueble(plantillaMueble(), ['modalidad' => 'SUPERFICIE']))
        ->assertSessionHasErrors('detalles.0.precio_unitario');
});

test('an unknown price modality is rejected', function () {
    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), cotizacionDeMueble(plantillaMueble(), ['modalidad' => 'OTRA']))
        ->assertSessionHasErrors('detalles.0.modalidad');
});

test('the furniture quoter requires the crear permission', function () {
    $this->actingAs(usuarioMuebles('cotizaciones.ver'))
        ->get(route('cotizaciones.muebles.create'))
        ->assertForbidden();
});

test('a furniture quote is saved as MUEBLE with the carpenter labour in its cost', function () {
    $plantilla = plantillaMueble();

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), cotizacionDeMueble($plantilla))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::with('detalles.items')->sole();
    $linea = $cotizacion->detalles->sole();
    $margen = config('cotizacion.margen_sugerido');

    // Costo de una unidad: 18 × 11 de tubo + 500 de carpintero = 698.
    expect($cotizacion->tipo)->toBe('MUEBLE')
        ->and($cotizacion->esDeMuebles())->toBeTrue()
        ->and((float) $linea->niveles)->toBe(4.0)
        ->and((float) $linea->profundidad)->toBe(0.7)
        ->and((float) $linea->costo_base)->toBe(1396.0)
        ->and((float) $linea->precio_unitario)->toBe(round(698 * (1 + $margen), 2))
        ->and($linea->items->where('tipo', 'MANO_OBRA')->sole()->subtotal)->toEqual(500);
});

test('a quote without tipo is still a general one', function () {
    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cotizacionDeMueble(plantillaMueble()),
            'tipo' => null,
            'detalles' => [['descripcion' => 'Banner', 'cantidad' => 1, 'precio_unitario' => 100]],
        ])
        ->assertSessionHasNoErrors();

    expect(Cotizacion::sole()->tipo)->toBe('GENERAL');
});

test('furniture and general products are not mixed in one quote', function (string $tipo, string $modoProducto) {
    $producto = $modoProducto === 'MUEBLE' ? plantillaMueble() : productoGeneral();

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [...cotizacionDeMueble($producto), 'tipo' => $tipo])
        ->assertSessionHasErrors('detalles.0.producto_id');

    expect(Cotizacion::count())->toBe(0);
})->with([
    'a template in a general quote' => ['GENERAL', 'MUEBLE'],
    'a general product in a furniture quote' => ['MUEBLE', 'GENERAL'],
]);

test('a furniture quote needs a template', function () {
    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), cotizacionDeMueble(plantillaMueble(), ['producto_id' => null]))
        ->assertSessionHasErrors('detalles.0.producto_id');
});

test('a furniture quote opens in the furniture wizard to edit', function () {
    $plantilla = plantillaMueble();
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->state(['producto_id' => $plantilla->id]), 'detalles')
        ->create(['tipo' => 'MUEBLE']);

    $this->actingAs(usuarioMuebles('cotizaciones.editar'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cotizaciones/Muebles/Edit')
            ->has('productos', 1)
            ->where('productos.0.id', $plantilla->id));
});

test('updating a furniture quote keeps its type and only accepts templates', function () {
    $plantilla = plantillaMueble();
    $cotizacion = Cotizacion::factory()->create(['tipo' => 'MUEBLE']);
    $usuario = usuarioMuebles('cotizaciones.editar');

    // Aunque el formulario mande otro tipo, una de muebles sigue siendo de muebles.
    $this->actingAs($usuario)
        ->put(route('cotizaciones.update', $cotizacion), [...cotizacionDeMueble(productoGeneral()), 'tipo' => 'GENERAL'])
        ->assertSessionHasErrors('detalles.0.producto_id');

    $this->actingAs($usuario)
        ->put(route('cotizaciones.update', $cotizacion), cotizacionDeMueble($plantilla))
        ->assertSessionHasNoErrors();

    expect($cotizacion->fresh()->tipo)->toBe('MUEBLE');
});

test('the list can be filtered by quote type', function () {
    Cotizacion::factory()->count(2)->create(['tipo' => 'GENERAL']);
    Cotizacion::factory()->create(['tipo' => 'MUEBLE']);

    $this->actingAs(usuarioMuebles('cotizaciones.ver'))
        ->get(route('cotizaciones.index', ['tipo' => 'MUEBLE']))
        ->assertInertia(fn ($page) => $page
            ->has('cotizaciones.data', 1)
            ->where('cotizaciones.data.0.tipo', 'MUEBLE')
            ->where('filters.tipo', 'MUEBLE'));
});

test('costear brings the template materials and its carpenter rate as one amount', function () {
    $plantilla = plantillaMueble(['costo_mano_obra' => 600, 'horas_mano_obra' => 10]);
    ProductoMaterial::factory()->create([
        'producto_id' => $plantilla->id,
        'material_id' => Material::factory()->create([
            'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
            'precio_unitario' => 100,
            'redondeo_compra' => null,
        ])->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 2,
    ]);

    // Un mueble cobra la tarifa del carpintero, no horas a la tarifa por hora.
    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $plantilla->id])
        ->assertOk()
        ->assertJsonPath('costo_material_unitario', 200)
        ->assertJsonPath('costo_mano_obra_unitario', 600)
        ->assertJsonPath('insumos.1.tipo', 'MANO_OBRA')
        ->assertJsonPath('insumos.1.cantidad', 1)
        ->assertJsonPath('insumos.1.costo_unitario', 600)
        ->assertJsonPath('insumos.1.descripcion', PrecioSugeridoService::DESCRIPCION_MANO_OBRA_MUEBLE)
        ->assertJsonPath('descripcion_mano_obra', PrecioSugeridoService::DESCRIPCION_MANO_OBRA_MUEBLE);
});

test('a template without a carpenter rate brings no labour row', function () {
    $plantilla = plantillaMueble(['costo_mano_obra' => null]);

    $this->actingAs(usuarioMuebles('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $plantilla->id])
        ->assertOk()
        ->assertJsonPath('costo_mano_obra_unitario', 0)
        ->assertJsonCount(0, 'insumos');
});

test('a product can be saved as a furniture template with its carpenter rate', function () {
    $this->actingAs(usuarioMuebles('productos.crear'))
        ->post(route('productos.store'), [
            'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
            'nombre' => 'Cabecera de góndola',
            'unidad_medida' => 'UNIDAD',
            'requiere_medidas' => 'SI',
            'modo_cotizacion' => 'MUEBLE',
            'costo_mano_obra' => 450,
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasNoErrors();

    $producto = Producto::where('nombre', 'Cabecera de góndola')->sole();

    expect($producto->esMueble())->toBeTrue()
        ->and((float) $producto->costo_mano_obra)->toBe(450.0);
});

test('a product without modo_cotizacion stays in the general quoter', function () {
    $this->actingAs(usuarioMuebles('productos.crear'))
        ->post(route('productos.store'), [
            'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
            'nombre' => 'Banner',
            'unidad_medida' => 'M2',
            'requiere_medidas' => 'SI',
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasNoErrors();

    expect(Producto::where('nombre', 'Banner')->sole()->modo_cotizacion)->toBe('GENERAL');
});

test('the furniture seeder creates the templates with recipes that cost by levels', function () {
    $this->seed([
        CategoriaMaterialSeeder::class,
        CategoriaProductoSeeder::class,
        MaterialSeeder::class,
        FormulaSeeder::class,
        MuebleSeeder::class,
        // Idempotente: correrlo dos veces no duplica nada.
        MuebleSeeder::class,
    ]);

    $plantillas = Producto::query()->modoCotizacion('MUEBLE')->pluck('nombre');

    expect($plantillas->sort()->values()->all())->toBe([
        'Cabecera de góndola', 'Exhibidor metálico de piso', 'Isla de exhibición', 'Mueble personalizado',
    ]);

    $isla = Producto::where('nombre', 'Isla de exhibición')->sole();
    $conTresNiveles = app(PrecioSugeridoService::class)->calcular($isla, 0.7, 1.6, 0.7, null, 0, 3);
    $conCincoNiveles = app(PrecioSugeridoService::class)->calcular($isla, 0.7, 1.6, 0.7, null, 0, 5);

    expect($conTresNiveles['costo_mano_obra_unitario'])->toBe(600.0)
        ->and(collect($conTresNiveles['insumos'])->where('tipo', 'MATERIAL'))->toHaveCount(5)
        // Más niveles, más tubo y más repisas.
        ->and($conCincoNiveles['costo_material_unitario'])->toBeGreaterThan($conTresNiveles['costo_material_unitario']);
});
