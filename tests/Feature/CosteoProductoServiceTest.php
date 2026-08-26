<?php

use App\Models\CategoriaMaterial;
use App\Models\CategoriaProducto;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Services\Calculo\CosteoProductoService;
use App\Services\Calculo\MedidasCotizacion;

function productoDe(string $unidadMedida, ?string $requiereMedidas = null): Producto
{
    return Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory(),
        'unidad_medida' => $unidadMedida,
        'requiere_medidas' => $requiereMedidas ?? ($unidadMedida === 'UNIDAD' ? 'NO' : 'SI'),
    ]);
}

function materialA(array $atributos = []): Material
{
    return Material::factory()->create(array_merge([
        'categoria_material_id' => CategoriaMaterial::factory(),
        'precio_unitario' => 10.0,
    ], $atributos));
}

test('calcula el costo de una linea estatica segun el driver M2 del producto', function () {
    $producto = productoDe('M2');
    $material = materialA(['precio_unitario' => 20.0]);

    ProductoMaterial::factory()->for($producto)->for($material)->create([
        'cantidad_por_unidad' => 1.1, // 10% de desperdicio
    ]);

    $resultado = app(CosteoProductoService::class)->calcular(
        $producto,
        new MedidasCotizacion(ancho: 2.0, alto: 1.5), // area = 3 m2
        cantidad: 4.0,
    );

    // 1.1 * area(3) * cantidad(4) * precio(20) = 264
    expect($resultado->costoMaterial)->toBe(264.0)
        ->and($resultado->lineas)->toHaveCount(1);
});

test('calcula el costo de una linea dinamica evaluando su formula', function () {
    $producto = productoDe('UNIDAD');
    $material = materialA(['precio_unitario' => 50.0]);
    $formula = Formula::factory()->create(['expresion' => 'ancho * alto']);

    ProductoMaterial::factory()->for($producto)->for($material)->dinamica($formula)->create();

    $resultado = app(CosteoProductoService::class)->calcular(
        $producto,
        new MedidasCotizacion(ancho: 0.5, alto: 0.3),
        cantidad: 2.0,
    );

    // formula(0.5*0.3=0.15) * cantidad(2) * precio(50) = 15
    expect($resultado->costoMaterial)->toBe(15.0);
});

test('combina lineas estaticas y dinamicas, el caso de letras corporeas 3D', function () {
    $producto = productoDe('UNIDAD');

    $acrilico = materialA(['nombre' => 'Acrílico transparente 3mm', 'precio_unitario' => 175.0]);
    $vinil = materialA(['nombre' => 'Vinil adhesivo brillante', 'precio_unitario' => 30.0]);
    $silicona = materialA(['nombre' => 'Silicona industrial', 'precio_unitario' => 28.0]);

    $area = Formula::factory()->create(['expresion' => 'ancho * alto']);
    $perimetroConProfundidad = Formula::factory()->create(['expresion' => '(ancho + alto) * 2 * profundo']);

    ProductoMaterial::factory()->for($producto)->for($acrilico)->dinamica($area)->create();
    ProductoMaterial::factory()->for($producto)->for($vinil)->dinamica($perimetroConProfundidad)->create();
    ProductoMaterial::factory()->for($producto)->for($silicona)->create(['cantidad_por_unidad' => 0.5]);

    $medidas = new MedidasCotizacion(ancho: 0.5, alto: 0.3, profundo: 0.08);

    $resultado = app(CosteoProductoService::class)->calcular($producto, $medidas, cantidad: 3.0);

    // Cara: 0.5*0.3=0.15 m2 * 175 = 26.25 por unidad
    // Canto: (0.5+0.3)*2*0.08=0.128 m2 * 30 = 3.84 por unidad
    // Silicona: 0.5 (fijo, no depende de medidas) * 28 = 14 por unidad
    // (26.25 + 3.84 + 14) * 3 unidades = 132.27
    expect($resultado->costoMaterial)->toBe(132.27)
        ->and($resultado->lineas)->toHaveCount(3);
});

test('lanza excepcion si un producto M2 no recibe ancho/alto', function () {
    $producto = productoDe('M2');
    ProductoMaterial::factory()->for($producto)->for(materialA())->create(['cantidad_por_unidad' => 1.0]);

    app(CosteoProductoService::class)->calcular($producto, new MedidasCotizacion, cantidad: 1.0);
})->throws(InvalidArgumentException::class);
