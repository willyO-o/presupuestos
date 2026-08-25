<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Catálogo curado con nombres/presentaciones reales (según la hoja de
     * costos citada en database-design.md §6), en vez de los nombres
     * genéricos de la factory — así el módulo se puede probar/mostrar con
     * datos creíbles. Se completa con un lote aleatorio vía factory para
     * tener volumen suficiente para paginación/búsqueda.
     */
    public function run(): void
    {
        $porCategoria = [
            'Gigantografía' => [
                ['nombre' => 'Lona FrontLight 13oz', 'presentacion' => 'Rollo 3,20x50m', 'unidad_medida' => 'M2', 'precio_presentacion' => 2800.00, 'precio_unitario' => 17.50],
                ['nombre' => 'Lona Backlight', 'presentacion' => 'Rollo 3,20x50m', 'unidad_medida' => 'M2', 'precio_presentacion' => 3600.00, 'precio_unitario' => 22.50],
                ['nombre' => 'Vinil adhesivo brillante', 'presentacion' => 'Rollo 1,52x50m', 'unidad_medida' => 'M2', 'precio_presentacion' => 2280.00, 'precio_unitario' => 30.00],
                ['nombre' => 'Vinil microperforado (vision control)', 'presentacion' => 'Rollo 1,52x50m', 'unidad_medida' => 'M2', 'precio_presentacion' => 3040.00, 'precio_unitario' => 40.00],
            ],
            'Cerrajería' => [
                ['nombre' => 'Tubo cuadrado 20x20x0,9mm', 'presentacion' => 'Barra 6m', 'unidad_medida' => 'METRO', 'precio_presentacion' => 66.00, 'precio_unitario' => 11.00],
                ['nombre' => 'Tubo cuadrado 40x40x1,5mm', 'presentacion' => 'Barra 6m', 'unidad_medida' => 'METRO', 'precio_presentacion' => 132.00, 'precio_unitario' => 22.00],
                ['nombre' => 'Platina 1x1/8', 'presentacion' => 'Barra 6m', 'unidad_medida' => 'METRO', 'precio_presentacion' => 54.00, 'precio_unitario' => 9.00],
                ['nombre' => 'Angular 1x1/8', 'presentacion' => 'Barra 6m', 'unidad_medida' => 'METRO', 'precio_presentacion' => 60.00, 'precio_unitario' => 10.00],
            ],
            'Carpintería' => [
                ['nombre' => 'MDF 9mm', 'presentacion' => 'Plancha 2,44x1,22m', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 180.00, 'precio_unitario' => 180.00],
                ['nombre' => 'Melamina blanca 18mm', 'presentacion' => 'Plancha 2,44x1,83m', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 420.00, 'precio_unitario' => 420.00],
                ['nombre' => 'Triplay 12mm', 'presentacion' => 'Plancha 2,44x1,22m', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 260.00, 'precio_unitario' => 260.00],
                ['nombre' => 'Tornillos autorroscantes', 'presentacion' => 'Caja 100u', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 35.00, 'precio_unitario' => 0.35],
            ],
            'Otros materiales' => [
                ['nombre' => 'Silicona industrial', 'presentacion' => 'Tubo 280ml', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 28.00, 'precio_unitario' => 28.00],
                ['nombre' => 'Cinta doble contacto', 'presentacion' => 'Rollo 50m', 'unidad_medida' => 'METRO', 'precio_presentacion' => 90.00, 'precio_unitario' => 1.80],
                ['nombre' => 'Remaches pop', 'presentacion' => 'Caja 100u', 'unidad_medida' => 'UNIDAD', 'precio_presentacion' => 40.00, 'precio_unitario' => 0.40],
                ['nombre' => 'Acrílico transparente 3mm', 'presentacion' => 'Plancha 1,22x2,44m', 'unidad_medida' => 'M2', 'precio_presentacion' => 520.00, 'precio_unitario' => 175.00],
                ['nombre' => 'Acrílico transparente 5mm', 'presentacion' => 'Plancha 1,22x2,44m', 'unidad_medida' => 'M2', 'precio_presentacion' => 780.00, 'precio_unitario' => 262.00],
            ],
            'Pinturas' => [
                ['nombre' => 'Pintura esmalte sintético', 'presentacion' => 'Galón 3,78L', 'unidad_medida' => 'LITRO', 'precio_presentacion' => 145.00, 'precio_unitario' => 38.40],
                ['nombre' => 'Laca automotriz', 'presentacion' => 'Galón 3,78L', 'unidad_medida' => 'LITRO', 'precio_presentacion' => 210.00, 'precio_unitario' => 55.60],
                ['nombre' => 'Thinner', 'presentacion' => 'Galón 3,78L', 'unidad_medida' => 'LITRO', 'precio_presentacion' => 70.00, 'precio_unitario' => 18.50],
                ['nombre' => 'Primer anticorrosivo', 'presentacion' => 'Galón 3,78L', 'unidad_medida' => 'LITRO', 'precio_presentacion' => 130.00, 'precio_unitario' => 34.40],
            ],
        ];

        foreach ($porCategoria as $categoriaNombre => $materiales) {
            $categoriaId = CategoriaMaterial::where('nombre', $categoriaNombre)->value('id');

            foreach ($materiales as $material) {
                Material::firstOrCreate(
                    ['categoria_material_id' => $categoriaId, 'nombre' => $material['nombre']],
                    [
                        'presentacion' => $material['presentacion'],
                        'unidad_medida' => $material['unidad_medida'],
                        'precio_presentacion' => $material['precio_presentacion'],
                        'precio_unitario' => $material['precio_unitario'],
                        'stock_actual' => fake()->randomFloat(2, 10, 200),
                        'stock_minimo' => fake()->randomFloat(2, 5, 20),
                        'estado' => 'ACTIVO',
                    ],
                );
            }
        }

        Material::factory(10)->recycle(CategoriaMaterial::all())->create();
    }
}
