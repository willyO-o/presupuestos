<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use Illuminate\Database\Seeder;

class CategoriaMaterialSeeder extends Seeder
{
    /**
     * Siembra las categorías fijas de la hoja de costos de materiales (ver
     * database-design.md §6). `firstOrCreate` en vez de la factory para
     * mantener nombres limpios y que el seeder sea idempotente.
     */
    public function run(): void
    {
        $categorias = [
            'Gigantografía',
            'Cerrajería',
            'Carpintería',
            'Otros materiales',
            'Pinturas',
        ];

        foreach ($categorias as $nombre) {
            CategoriaMaterial::firstOrCreate(['nombre' => $nombre], ['estado' => 'ACTIVO']);
        }
    }
}
