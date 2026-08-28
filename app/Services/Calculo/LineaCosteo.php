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
        /** Cantidad de material consumida por TODAS las unidades pedidas, ya redondeada a la unidad de compra (material.redondeo_compra). */
        public float $cantidadConsumida,
        /** cantidadConsumida × material.precio_unitario. */
        public float $costo,
        /** Cantidad exacta antes de redondear a la unidad de compra — igual a cantidadConsumida si el material no tiene redondeo_compra. */
        public float $cantidadBruta,
    ) {}

    /**
     * true si la cantidad consumida se redondeó hacia arriba a la unidad de
     * compra del material (útil para mostrar "0,6 m² → 2,00 m²" en el desglose).
     */
    public function fueRedondeada(): bool
    {
        return abs($this->cantidadConsumida - $this->cantidadBruta) > 1e-6;
    }
}
