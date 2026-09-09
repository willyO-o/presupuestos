<?php

namespace Database\Seeders;

use App\Models\TipoProyecto;
use Illuminate\Database\Seeder;

class TipoProyectoSeeder extends Seeder
{
    /**
     * Los 4 niveles de complejidad que el Excel
     * `11_Sistema_Margen_Automatico_Xtrapubli` tenía hardcodeados en una
     * cadena de IF anidados (Tipo de Proyecto 1..4). Ahora son datos: el
     * usuario puede editarlos, agregar más niveles o desactivarlos desde el
     * CRUD de Tipos de Proyecto sin tocar código.
     *
     * Idempotente (firstOrCreate por nombre), como los demás seeders de
     * catálogo fijo — ver .ai/rules/seeders.md.
     */
    public function run(): void
    {
        $niveles = [
            [
                'nombre' => 'Básico',
                'descripcion' => 'Trabajo estándar, sin dificultad especial: banners, vinilos, impresión simple.',
                'factor_complejidad' => 1.0,
                'margen_minimo' => 0.45,
                'orden' => 1,
            ],
            [
                'nombre' => 'Medio',
                'descripcion' => 'Requiere estructura o armado: exhibidores, muebles, letreros sin iluminación.',
                'factor_complejidad' => 1.3,
                'margen_minimo' => 0.50,
                'orden' => 2,
            ],
            [
                'nombre' => 'Complejo',
                'descripcion' => 'Varias áreas involucradas o acabados finos: letreros luminosos, islas cabeceras.',
                'factor_complejidad' => 1.5,
                'margen_minimo' => 0.60,
                'orden' => 3,
            ],
            [
                'nombre' => 'Crítico',
                'descripcion' => 'Alto riesgo, plazo corto o instalación en altura: trabajos especiales.',
                'factor_complejidad' => 1.8,
                'margen_minimo' => 0.70,
                'orden' => 4,
            ],
        ];

        foreach ($niveles as $nivel) {
            TipoProyecto::firstOrCreate(
                ['nombre' => $nivel['nombre']],
                [...$nivel, 'estado' => 'ACTIVO'],
            );
        }
    }
}
