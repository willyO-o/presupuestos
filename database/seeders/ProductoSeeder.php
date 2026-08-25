<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Catálogo curado con nombres reales (ver database-design.md §7), en
     * vez de los nombres genéricos de la factory. Se completa con un lote
     * aleatorio vía factory para tener volumen suficiente para
     * paginación/búsqueda.
     *
     * "Letras corpóreas 3D iluminadas" y "Exhibidor de piso MDF a medida"
     * quedan a propósito como UNIDAD/requiere_medidas=SI sin BOM todavía:
     * son el caso que motivó la conversación sobre un motor de fórmulas
     * (ver .ai/rules/migrations.md) — su cálculo real se resuelve cuando
     * se construya el módulo de Cotización, no en este seeder.
     */
    public function run(): void
    {
        $porCategoria = [
            'Bastidores' => [
                ['nombre' => 'Bastidor lona PVC 1440dpi', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Bastidor backlight retroiluminado', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Banners' => [
                ['nombre' => 'Banner lona frontlight', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Banner roll-up 85x200cm', 'unidad_medida' => 'UNIDAD', 'precio_base' => 450.00, 'requiere_medidas' => 'NO'],
            ],
            'Gigantografías' => [
                ['nombre' => 'Gigantografía frontlight', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Gigantografía backlight', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Vinyl Rotulado' => [
                ['nombre' => 'Rotulado vinil adhesivo', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Vinil microperforado (vision control)', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Exhibidores' => [
                ['nombre' => 'Exhibidor de piso MDF a medida', 'unidad_medida' => 'UNIDAD', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Isla promocional', 'unidad_medida' => 'UNIDAD', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Material POP' => [
                ['nombre' => 'Cenefa colgante', 'unidad_medida' => 'UNIDAD', 'precio_base' => 85.00, 'requiere_medidas' => 'NO'],
                ['nombre' => 'Habladores/Stoppers', 'unidad_medida' => 'UNIDAD', 'precio_base' => 25.00, 'requiere_medidas' => 'NO'],
            ],
            'Toldos' => [
                ['nombre' => 'Toldo publicitario lona', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Letreros Luminosos' => [
                ['nombre' => 'Letrero luminoso caja de luz', 'unidad_medida' => 'M2', 'precio_base' => null, 'requiere_medidas' => 'SI'],
                ['nombre' => 'Letras corpóreas 3D iluminadas', 'unidad_medida' => 'UNIDAD', 'precio_base' => null, 'requiere_medidas' => 'SI'],
            ],
            'Rotulado Vehicular' => [
                ['nombre' => 'Rotulado vehicular completo', 'unidad_medida' => 'UNIDAD', 'precio_base' => 3500.00, 'requiere_medidas' => 'NO'],
                ['nombre' => 'Rotulado vehicular parcial (puertas)', 'unidad_medida' => 'UNIDAD', 'precio_base' => 900.00, 'requiere_medidas' => 'NO'],
            ],
        ];

        foreach ($porCategoria as $categoriaNombre => $productos) {
            $categoriaId = CategoriaProducto::where('nombre', $categoriaNombre)->value('id');

            foreach ($productos as $producto) {
                Producto::firstOrCreate(
                    ['categoria_producto_id' => $categoriaId, 'nombre' => $producto['nombre']],
                    [
                        'descripcion' => null,
                        'unidad_medida' => $producto['unidad_medida'],
                        'precio_base' => $producto['precio_base'],
                        'requiere_medidas' => $producto['requiere_medidas'],
                        'estado' => 'ACTIVO',
                    ],
                );
            }
        }

        Producto::factory(10)->recycle(CategoriaProducto::all())->create();
    }
}
