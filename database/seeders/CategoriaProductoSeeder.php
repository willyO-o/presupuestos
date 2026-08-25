<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use Illuminate\Database\Seeder;

class CategoriaProductoSeeder extends Seeder
{
    /**
     * Siembra las categorías fijas del catálogo de productos publicitarios
     * (ver database-design.md §7). `firstOrCreate` en vez de la factory
     * para mantener nombres limpios y que el seeder sea idempotente.
     */
    public function run(): void
    {
        $categorias = [
            'Bastidores',
            'Banners',
            'Gigantografías',
            'Vinyl Rotulado',
            'Exhibidores',
            'Material POP',
            'Toldos',
            'Letreros Luminosos',
            'Rotulado Vehicular',
        ];

        foreach ($categorias as $nombre) {
            CategoriaProducto::firstOrCreate(['nombre' => $nombre], ['estado' => 'ACTIVO']);
        }
    }
}
