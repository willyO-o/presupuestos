<?php

namespace App\Services\Calculo;

use App\Models\ProductoMaterial;

/**
 * Desglose del costo de una línea de BOM (producto_material) para el total
 * de unidades cotizadas — parte del resultado de CosteoProductoService.
 */
final readonly class LineaCosteo
{
    public function __construct(
        public ProductoMaterial $productoMaterial,
        /** Cantidad de material consumida por TODAS las unidades pedidas (ya multiplicada por cantidad). */
        public float $cantidadConsumida,
        /** cantidadConsumida × material.precio_unitario. */
        public float $costo,
    ) {}
}
