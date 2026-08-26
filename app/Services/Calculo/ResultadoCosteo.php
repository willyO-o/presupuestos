<?php

namespace App\Services\Calculo;

/**
 * Resultado de CosteoProductoService::calcular(): el costo total de
 * materiales más el desglose por línea de BOM (para mostrar en el detalle
 * de una cotización, no solo el número final).
 */
final readonly class ResultadoCosteo
{
    /**
     * @param  list<LineaCosteo>  $lineas
     */
    public function __construct(
        public float $costoMaterial,
        public array $lineas,
    ) {}
}
