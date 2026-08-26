<?php

namespace Database\Seeders;

use App\Models\Formula;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    /**
     * Fórmulas reutilizables para líneas de producto_material dinámicas
     * (ver App\Services\Calculo\FormulaCalculator y
     * .ai/rules/migrations.md). Las variables disponibles son
     * ancho/alto/profundo/area/perimetro — ver
     * App\Services\Calculo\MedidasCotizacion::variables().
     * `firstOrCreate` para que el seeder sea idempotente.
     */
    public function run(): void
    {
        $formulas = [
            [
                'nombre' => 'Área simple',
                'expresion' => 'ancho * alto',
                'descripcion' => 'Área de una cara (m²) — material que cubre todo el ancho×alto de una unidad (lona, acrílico, MDF de una cara).',
            ],
            [
                'nombre' => 'Perímetro',
                'expresion' => '(ancho + alto) * 2',
                'descripcion' => 'Perímetro del rectángulo ancho×alto (metros) — material de borde/marco (tubo estructural, canto de aluminio).',
            ],
            [
                'nombre' => 'Perímetro con profundidad',
                'expresion' => '(ancho + alto) * 2 * profundo',
                'descripcion' => 'Área lateral de una pieza con volumen (m²) — ej. la cara lateral (canto) de una letra corpórea 3D, alrededor de todo el perímetro y a lo largo de la profundidad.',
            ],
            [
                'nombre' => 'Volumen',
                'expresion' => 'ancho * alto * profundo',
                'descripcion' => 'Volumen (m³) — relleno o material que ocupa el interior de una pieza con las tres medidas.',
            ],
        ];

        foreach ($formulas as $formula) {
            Formula::firstOrCreate(
                ['nombre' => $formula['nombre']],
                ['expresion' => $formula['expresion'], 'descripcion' => $formula['descripcion'], 'estado' => 'ACTIVO'],
            );
        }
    }
}
