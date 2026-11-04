<?php

use App\Models\Configuracion;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\TipoProyecto;
use App\Models\User;
use App\Services\Cotizador\CotizadorPublicoService;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;

/**
 * Panel del dueño para el cotizador web (Ventas → Cotizador web): qué se
 * publica, de dónde sale el precio y los ajustes generales.
 */
beforeEach(function () {
    Permission::findOrCreate('cotizador-web.configurar', 'web');
    RateLimiter::clear('cotizador-calcular');
});

function duenoCotizadorWeb(): User
{
    $usuario = User::factory()->todasLasSucursales()->create();
    $usuario->givePermissionTo('cotizador-web.configurar');

    return $usuario;
}

function productoPorM2(array $atributos = []): Producto
{
    return Producto::factory()->create([
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        ...$atributos,
    ]);
}

/** Precio sin IVA de la primera línea de una estimación pública. */
function precioPublico(Producto $producto, float $ancho, float $alto): float
{
    return (float) test()->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => $ancho, 'alto' => $alto, 'cantidad' => 1]],
    ])->assertOk()->json('lineas.0.precio_unitario');
}

test('only the owner permission opens the panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cotizador-web.index'))
        ->assertForbidden();

    $producto = productoPorM2(['nombre' => 'Lona fija', 'modo_precio_web' => 'PRECIO_FIJO', 'precio_web' => 40]);

    $this->actingAs(duenoCotizadorWeb())
        ->get(route('cotizador-web.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CotizadorWeb/Index')
            ->where('productos.data.0.id', $producto->id)
            // 1 m² × Bs 40
            ->where('productos.data.0.precio_referencia', fn ($valor) => (float) $valor === 40.0)
            ->where('productos.data.0.publicado', false));
});

test('furniture templates are not offered in the web panel', function () {
    productoPorM2(['modo_cotizacion' => 'MUEBLE']);

    $this->actingAs(duenoCotizadorWeb())
        ->get(route('cotizador-web.index'))
        ->assertInertia(fn ($page) => $page->has('productos.data', 0));
});

test('a fixed price product is published without a recipe and priced per square metre', function () {
    $producto = productoPorM2();

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.productos.update', $producto), [
            'cotizable_web' => 'SI',
            'modo_precio_web' => 'PRECIO_FIJO',
            'precio_web' => 55,
        ])
        ->assertSessionHasNoErrors();

    expect(Producto::cotizableWeb()->pluck('id')->all())->toBe([$producto->id])
        // 2 × 1,5 = 3 m² × Bs 55
        ->and(precioPublico($producto, 2, 1.5))->toBe(165.0);
});

test('saving another mode ignores the unfinished ranges the modal always sends', function () {
    $producto = productoPorM2();

    // El modal manda sus filas de rango de ejemplo (sin precio) en cualquier modo.
    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.productos.update', $producto), [
            'cotizable_web' => 'SI',
            'modo_precio_web' => 'PRECIO_FIJO',
            'precio_web' => 55,
            'rangos' => [['area_hasta' => 2, 'precio_m2' => ''], ['area_hasta' => '', 'precio_m2' => '']],
        ])
        ->assertSessionHasNoErrors();

    expect($producto->fresh()->modo_precio_web)->toBe('PRECIO_FIJO')
        ->and($producto->preciosWeb()->count())->toBe(0);
});

test('area ranges pick the price per square metre by the size of the job', function () {
    $producto = productoPorM2();

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.productos.update', $producto), [
            'cotizable_web' => 'SI',
            'modo_precio_web' => 'RANGOS',
            'rangos' => [
                ['area_hasta' => 2, 'precio_m2' => 80],
                ['area_hasta' => 10, 'precio_m2' => 60],
                ['area_hasta' => null, 'precio_m2' => 50],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($producto->preciosWeb()->count())->toBe(3)
        ->and(precioPublico($producto, 1, 2))->toBe(160.0)    // 2 m², primer rango (tope incluido)
        ->and(precioPublico($producto, 2, 2.5))->toBe(300.0)  // 5 m² × 60
        ->and(precioPublico($producto, 4, 5))->toBe(1000.0);  // 20 m² × 50
});

test('when the area passes every top, the last range applies', function () {
    $producto = productoPorM2(['cotizable_web' => 'SI', 'modo_precio_web' => 'RANGOS']);
    $producto->preciosWeb()->createMany([
        ['area_hasta' => 2, 'precio_m2' => 80],
        ['area_hasta' => 5, 'precio_m2' => 70],
    ]);

    expect(precioPublico($producto, 3, 3))->toBe(630.0);
});

test('invalid web price setups are rejected', function (array $atributos, array $datos, string $campo) {
    $producto = Producto::factory()->create([
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        ...$atributos,
    ]);

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.productos.update', $producto), ['cotizable_web' => 'SI', ...$datos])
        ->assertSessionHasErrors($campo);
})->with([
    'ranges on a per unit product' => [
        ['unidad_medida' => 'UNIDAD'],
        ['modo_precio_web' => 'RANGOS', 'rangos' => [['area_hasta' => null, 'precio_m2' => 10]]],
        'modo_precio_web',
    ],
    'ranges out of order' => [
        [],
        ['modo_precio_web' => 'RANGOS', 'rangos' => [['area_hasta' => 5, 'precio_m2' => 10], ['area_hasta' => 3, 'precio_m2' => 9]]],
        'rangos.1.area_hasta',
    ],
    'open range in the middle' => [
        [],
        ['modo_precio_web' => 'RANGOS', 'rangos' => [['area_hasta' => null, 'precio_m2' => 10], ['area_hasta' => 3, 'precio_m2' => 9]]],
        'rangos.0.area_hasta',
    ],
    'fixed price without a price' => [[], ['modo_precio_web' => 'PRECIO_FIJO'], 'precio_web'],
    'materials without recipe or base cost' => [[], ['modo_precio_web' => 'MATERIALES'], 'modo_precio_web'],
]);

test('a recipe that needs depth cannot be published by materials', function () {
    $producto = productoPorM2();
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'formula_id' => Formula::factory()->create(['expresion' => 'ancho * profundo'])->id,
        'cantidad_por_unidad' => null,
    ]);

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.productos.update', $producto), [
            'cotizable_web' => 'SI',
            'modo_precio_web' => 'MATERIALES',
        ])
        ->assertSessionHasErrors('modo_precio_web');
});

test('a product with only a base cost is published by materials with the margin', function () {
    $producto = productoPorM2(['precio_base' => 30, 'cotizable_web' => 'SI', 'modo_precio_web' => 'MATERIALES']);
    TipoProyecto::query()->delete();

    // 2 m² × Bs 30 de costo, con el margen sugerido y factor 1 (sin niveles).
    $esperado = round(60 * (1 + config('cotizacion.margen_sugerido')), 2);

    expect(Producto::cotizableWeb()->pluck('id')->all())->toBe([$producto->id])
        ->and(precioPublico($producto, 2, 1))->toBe($esperado);
});

test('probar previews the unsaved setup without changing the product', function () {
    $producto = productoPorM2();

    $this->actingAs(duenoCotizadorWeb())
        ->postJson(route('cotizador-web.productos.probar', $producto), [
            'modo_precio_web' => 'PRECIO_FIJO',
            'precio_web' => 100,
            'ancho' => 2,
            'alto' => 1,
            'cantidad' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('precio_unitario', fn ($valor) => (float) $valor === 200.0)
        ->assertJsonPath('subtotal', fn ($valor) => (float) $valor === 400.0)
        ->assertJsonPath('total', fn ($valor) => (float) $valor === round(400 * (1 + config('margen.impuestos.iva')), 2));

    expect($producto->fresh()->modo_precio_web)->toBe('MATERIALES');
});

test('probar explains why it cannot price', function () {
    $producto = productoPorM2();

    $this->actingAs(duenoCotizadorWeb())
        ->postJson(route('cotizador-web.productos.probar', $producto), [
            'modo_precio_web' => 'RANGOS',
            'rangos' => [],
            'ancho' => 1,
            'alto' => 1,
            'cantidad' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

test('general settings are saved and override the config file', function () {
    TipoProyecto::factory()->create(['nombre' => 'Medio', 'estado' => 'ACTIVO']);

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.ajustes'), [
            'tipo_proyecto' => 'Medio',
            'holgura' => 10,
            'vigencia_dias' => 15,
        ])
        ->assertSessionHasNoErrors();

    $servicio = app(CotizadorPublicoService::class);

    expect(Configuracion::valor(CotizadorPublicoService::CLAVE_HOLGURA))->toBe('0.1')
        ->and($servicio->holgura())->toBe(0.1)
        ->and($servicio->vigenciaDias())->toBe(15)
        ->and($servicio->tipoProyecto()->nombre)->toBe('Medio');
});

test('choosing the first active level overrides the level set in the env', function () {
    TipoProyecto::query()->delete();
    TipoProyecto::factory()->create(['nombre' => 'Básico', 'estado' => 'ACTIVO', 'orden' => 1]);
    TipoProyecto::factory()->create(['nombre' => 'Medio', 'estado' => 'ACTIVO', 'orden' => 2]);
    config(['cotizador.tipo_proyecto' => 'Medio']);

    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.ajustes'), ['tipo_proyecto' => null, 'holgura' => 15, 'vigencia_dias' => 7])
        ->assertSessionHasNoErrors();

    expect(app(CotizadorPublicoService::class)->tipoProyecto()->nombre)->toBe('Básico');
});

test('settings reject an unknown complexity level', function () {
    $this->actingAs(duenoCotizadorWeb())
        ->put(route('cotizador-web.ajustes'), [
            'tipo_proyecto' => 'No existe',
            'holgura' => 10,
            'vigencia_dias' => 7,
        ])
        ->assertSessionHasErrors('tipo_proyecto');
});

test('without saved settings the config file still rules', function () {
    config(['cotizador.holgura' => 0.2, 'cotizador.vigencia_dias' => 9]);

    $servicio = app(CotizadorPublicoService::class);

    expect($servicio->holgura())->toBe(0.2)
        ->and($servicio->vigenciaDias())->toBe(9);
});

test('a material price change reaches the web price by materials', function () {
    $producto = productoPorM2(['cotizable_web' => 'SI', 'modo_precio_web' => 'MATERIALES']);
    $material = Material::factory()->create(['precio_unitario' => 50, 'redondeo_compra' => null]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $antes = precioPublico($producto, 1, 1);
    $material->update(['precio_unitario' => 100]);

    expect(precioPublico($producto, 1, 1))->toBeGreaterThan($antes);
});
