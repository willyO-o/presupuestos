<?php

namespace App\Services\Calculo;

use App\Models\Producto;

/**
 * Traduce el costo de materiales de un producto (BOM, vía
 * CosteoProductoService) en un precio unitario sugerido para una línea de
 * cotización: costo de materiales de UNA unidad + margen configurable
 * (config/cotizacion.php `margen_sugerido`). El vendedor siempre puede
 * sobrescribir el número — ver database-design.md §8.
 */
class PrecioSugeridoService
{
    public function __construct(
        private readonly CosteoProductoService $costeoProducto,
    ) {}

    /**
     * @return array{costo_material_unitario: float, margen: float, precio_sugerido: float, lineas: list<array{material: string, unidad: string, cantidad: float, cantidad_bruta: float, redondeada: bool, costo: float}>}
     */
    public function calcular(Producto $producto, ?float $ancho, ?float $alto, ?float $profundo = null): array
    {
        $medidas = new MedidasCotizacion(ancho: $ancho, alto: $alto, profundo: $profundo);

        // `cantidad = 1`: el precio sugerido es por unidad; la cantidad
        // pedida se multiplica después, al armar el subtotal de la línea.
        $resultado = $this->costeoProducto->calcular($producto, $medidas, 1.0);

        $margen = (float) config('cotizacion.margen_sugerido', 0.45);
        $costoUnitario = round($resultado->costoMaterial, 2);

        return [
            'costo_material_unitario' => $costoUnitario,
            'margen' => $margen,
            'precio_sugerido' => round($costoUnitario * (1 + $margen), 2),
            'lineas' => array_map(fn (LineaCosteo $linea): array => [
                'material' => $linea->productoMaterial->material->nombre,
                'unidad' => $linea->productoMaterial->material->unidad_medida,
                'cantidad' => round($linea->cantidadConsumida, 4),
                'cantidad_bruta' => round($linea->cantidadBruta, 4),
                'redondeada' => $linea->fueRedondeada(),
                'costo' => round($linea->costo, 2),
            ], $resultado->lineas),
        ];
    }
}
