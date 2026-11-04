<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use Illuminate\Database\Seeder;

class MuebleSeeder extends Seeder
{
    /**
     * Plantillas del cotizador de muebles, tomadas de las cotizaciones reales
     * de la empresa (Isla KOTEX, Cabeceras KOTEX/HUGGIES, exhibidores
     * metálicos de las OC de Venado/Vemassa): base metálica, repisas de MDF,
     * pintura e impresión, más la tarifa del carpintero por mueble.
     *
     * Las fórmulas usan `niveles` (repisas) además de ancho/alto/profundo:
     * por eso el asistente pide las cuatro medidas.
     *
     * Son productos NUEVOS, no "Isla promocional" ni "Exhibidor de piso MDF a
     * medida": esos dos siguen en el cotizador general porque el historial
     * demo (HistorialComercialSeeder) los cotiza junto con lonas, y una
     * cotización ya no mezcla muebles con otros trabajos.
     *
     * Idempotente (firstOrCreate), igual que el resto del catálogo curado.
     * Depende de MaterialSeeder y CategoriaProductoSeeder.
     */
    public function run(): void
    {
        $formulas = $this->formulas();
        $categoriaId = CategoriaProducto::where('nombre', 'Exhibidores')->value('id');
        $materiales = Material::pluck('id', 'nombre');

        foreach ($this->plantillas() as $plantilla) {
            $producto = Producto::firstOrCreate(
                ['categoria_producto_id' => $categoriaId, 'nombre' => $plantilla['nombre']],
                [
                    'descripcion' => $plantilla['descripcion'],
                    'unidad_medida' => 'UNIDAD',
                    'precio_base' => null,
                    'requiere_medidas' => 'SI',
                    'cotizable_web' => 'NO',
                    'modo_cotizacion' => 'MUEBLE',
                    'costo_mano_obra' => $plantilla['mano_obra'],
                    'estado' => 'ACTIVO',
                ],
            );

            foreach ($plantilla['receta'] as $receta) {
                $materialId = $materiales[$receta[0]] ?? null;

                if ($materialId === null) {
                    continue;
                }

                $valores = isset($receta['formula'])
                    ? ['formula_id' => $formulas[$receta['formula']], 'cantidad_por_unidad' => null]
                    : ['formula_id' => null, 'cantidad_por_unidad' => $receta['estatico']];

                ProductoMaterial::firstOrCreate(
                    ['producto_id' => $producto->id, 'material_id' => $materialId],
                    $valores,
                );
            }
        }
    }

    /**
     * Fórmulas propias de los muebles (en metros, m² o planchas por mueble).
     *
     * @return array<string, int>
     */
    private function formulas(): array
    {
        $definiciones = [
            'Estructura metálica de mueble' => [
                'expresion' => 'alto * 4 + (ancho + profundo) * 2 * niveles',
                'descripcion' => 'Metros de tubo: 4 parantes de la altura del mueble y un marco (ancho + profundidad) por cada nivel.',
            ],
            'Pintura de estructura' => [
                'expresion' => '(alto * 4 + (ancho + profundo) * 2 * niveles) * 0.05',
                'descripcion' => 'Litros de pintura para la estructura: 0,05 L por metro de tubo.',
            ],
            'Repisas de MDF (planchas)' => [
                'expresion' => 'ancho * profundo * niveles / 2.98',
                'descripcion' => 'Planchas de MDF (2,44 × 1,22 m ≈ 2,98 m²) para una repisa de ancho × profundidad por nivel.',
            ],
            'Repisas de angular' => [
                'expresion' => 'ancho * 2 * niveles',
                'descripcion' => 'Metros de angular: dos largueros del ancho del mueble por nivel.',
            ],
            'Gráfica de 4 caras' => [
                'expresion' => '(ancho + profundo) * 2 * alto',
                'descripcion' => 'Área de impresión (m²) que rodea las cuatro caras de una isla.',
            ],
        ];

        $ids = [];

        foreach ($definiciones as $nombre => $definicion) {
            $ids[$nombre] = Formula::firstOrCreate(
                ['nombre' => $nombre],
                [...$definicion, 'estado' => 'ACTIVO'],
            )->id;
        }

        return $ids + Formula::pluck('id', 'nombre')->all();
    }

    /**
     * @return list<array{nombre: string, descripcion: string, mano_obra: float|null, receta: list<array<int|string, mixed>>}>
     */
    private function plantillas(): array
    {
        return [
            [
                'nombre' => 'Isla de exhibición',
                'descripcion' => "Elaborado de isla, base metálica montada con trupan MDF, sulfaceado y pintado completo, armado con troqueles según modelo imagen.\nImpresión full color calidad fotográfica en las cuatro caras.",
                'mano_obra' => 600.00,
                'receta' => [
                    ['Tubo cuadrado 20x20x0,9mm', 'formula' => 'Estructura metálica de mueble'],
                    ['MDF 9mm', 'formula' => 'Repisas de MDF (planchas)'],
                    ['Pintura esmalte sintético', 'formula' => 'Pintura de estructura'],
                    ['Vinil adhesivo brillante', 'formula' => 'Gráfica de 4 caras'],
                    ['Tornillos autorroscantes', 'estatico' => 40],
                ],
            ],
            [
                'nombre' => 'Cabecera de góndola',
                'descripcion' => "Elaborado de cabecera, base metálica montada con trupan MDF, sulfaceado y pintado completo, armado con troqueles según modelo imagen.\nExhibidor de cabecera diseñado para maximizar la capacidad de exhibición sin sacrificar el espacio de venta.",
                'mano_obra' => 500.00,
                'receta' => [
                    ['Tubo cuadrado 20x20x0,9mm', 'formula' => 'Estructura metálica de mueble'],
                    ['MDF 9mm', 'formula' => 'Repisas de MDF (planchas)'],
                    ['Pintura esmalte sintético', 'formula' => 'Pintura de estructura'],
                    ['Vinil adhesivo brillante', 'formula' => 'Área simple'],
                    ['Tornillos autorroscantes', 'estatico' => 30],
                ],
            ],
            [
                'nombre' => 'Exhibidor metálico de piso',
                'descripcion' => "Elaboración de exhibidor metálico de piso con repisas de angular, anticorrosivo y pintura al horno.\nCabezal con gráfica impresa a full color.",
                'mano_obra' => 250.00,
                'receta' => [
                    ['Tubo cuadrado 20x20x0,9mm', 'formula' => 'Estructura metálica de mueble'],
                    ['Angular 1x1/8', 'formula' => 'Repisas de angular'],
                    ['Primer anticorrosivo', 'formula' => 'Pintura de estructura'],
                    ['Pintura esmalte sintético', 'formula' => 'Pintura de estructura'],
                    ['Vinil adhesivo brillante', 'estatico' => 0.3],
                ],
            ],
            [
                // Sin receta ni tarifa: para un mueble que no se parece a
                // ninguna plantilla, el vendedor carga los insumos a mano.
                'nombre' => 'Mueble personalizado',
                'descripcion' => 'Mueble a medida según diseño aprobado por el cliente.',
                'mano_obra' => null,
                'receta' => [],
            ],
        ];
    }
}
