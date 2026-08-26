<?php

namespace Database\Seeders;

use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use Illuminate\Database\Seeder;

class ProductoMaterialSeeder extends Seeder
{
    /**
     * Receta de costo (BOM) de una parte del catálogo curado de
     * ProductoSeeder/MaterialSeeder. Mezcla líneas estáticas (factor fijo
     * `cantidad_por_unidad`, el caso de siempre) con líneas dinámicas
     * (`formula`, ver FormulaSeeder) — en particular "Letras corpóreas 3D
     * iluminadas" combina una fórmula de área (cara) con una de perímetro
     * con profundidad (canto lateral), que es exactamente el caso que
     * `cantidad_por_unidad` por sí solo no puede resolver (ver
     * .ai/rules/migrations.md, "Motor de cálculo por tipo de producto").
     *
     * Cada fila: [producto, material, 'estatico' => factor] o
     * [producto, material, 'formula' => nombre de Formula].
     */
    public function run(): void
    {
        $productos = Producto::pluck('id', 'nombre');
        $materiales = Material::pluck('id', 'nombre');
        $formulas = Formula::pluck('id', 'nombre');

        $recetas = [
            ['Bastidor lona PVC 1440dpi', 'Lona FrontLight 13oz', 'estatico' => 1.05],
            ['Bastidor lona PVC 1440dpi', 'Tubo cuadrado 20x20x0,9mm', 'formula' => 'Perímetro'],

            ['Gigantografía frontlight', 'Lona FrontLight 13oz', 'estatico' => 1.03],
            ['Gigantografía backlight', 'Lona Backlight', 'estatico' => 1.03],
            ['Banner lona frontlight', 'Lona FrontLight 13oz', 'estatico' => 1.05],
            ['Rotulado vinil adhesivo', 'Vinil adhesivo brillante', 'estatico' => 1.05],

            ['Toldo publicitario lona', 'Lona FrontLight 13oz', 'estatico' => 1.10],
            ['Toldo publicitario lona', 'Tubo cuadrado 40x40x1,5mm', 'formula' => 'Perímetro'],

            ['Letrero luminoso caja de luz', 'Acrílico transparente 3mm', 'estatico' => 1.0],
            ['Letrero luminoso caja de luz', 'Tubo cuadrado 40x40x1,5mm', 'formula' => 'Perímetro'],

            // Caso estrella: dos drivers distintos (área + perímetro con
            // profundidad) combinados en un solo producto UNIDAD.
            ['Letras corpóreas 3D iluminadas', 'Acrílico transparente 3mm', 'formula' => 'Área simple'],
            ['Letras corpóreas 3D iluminadas', 'Vinil adhesivo brillante', 'formula' => 'Perímetro con profundidad'],
            ['Letras corpóreas 3D iluminadas', 'Silicona industrial', 'estatico' => 0.5],

            ['Exhibidor de piso MDF a medida', 'MDF 9mm', 'estatico' => 0.5],
            ['Exhibidor de piso MDF a medida', 'Tornillos autorroscantes', 'estatico' => 0.2],
        ];

        foreach ($recetas as $receta) {
            [$nombreProducto, $nombreMaterial] = $receta;

            $atributos = ['producto_id' => $productos[$nombreProducto], 'material_id' => $materiales[$nombreMaterial]];

            $valores = isset($receta['formula'])
                ? ['formula_id' => $formulas[$receta['formula']], 'cantidad_por_unidad' => null]
                : ['formula_id' => null, 'cantidad_por_unidad' => $receta['estatico']];

            ProductoMaterial::firstOrCreate($atributos, $valores);
        }
    }
}
