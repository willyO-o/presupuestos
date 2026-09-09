<?php

namespace App\Services\Calculo;

/**
 * Resultado completo del motor de margen (MotorMargenService): la cadena
 * costo base → costo ajustado → precio → impuestos → utilidad → semáforo,
 * más el precio final que ve el cliente.
 *
 * Los valores se guardan SIN redondear (igual que las celdas del Excel, que
 * arrastran todos los decimales); usar `toArray()` para persistirlos o
 * mandarlos al frontend ya redondeados a 2 decimales.
 */
final readonly class ResultadoMargen
{
    public function __construct(
        /** Suma de los insumos, antes de aplicar complejidad. */
        public float $costoBase,
        /** costo base × factor de complejidad del tipo de proyecto. */
        public float $costoAjustado,
        public float $factorComplejidad,
        /** Margen aplicado como fracción (0.45 = 45 %). */
        public float $margen,
        /** Precio antes de impuestos: costo ajustado × (1 + margen). */
        public float $precio,
        /** Impuesto a las Transacciones. */
        public float $it,
        /** precio − costo ajustado − IT. */
        public float $utilidadAntesIue,
        /** Impuesto sobre las Utilidades de las Empresas. */
        public float $iue,
        /** Lo que realmente gana la empresa: utilidad antes de IUE − IUE. */
        public float $utilidadReal,
        /** VERDE / AMARILLO / ROJO. */
        public string $estado,
        /** ACEPTAR / REVISAR PRECIO / NO ACEPTAR. */
        public string $recomendacion,
        public float $iva,
        /** precio + IVA. */
        public float $precioFinal,
        /** Monto manual de instalación (0 si la categoría no la lleva). */
        public float $instalacion,
        /** precio final + instalación: lo que paga el cliente. */
        public float $totalPrecioFinal,
    ) {}

    /**
     * Utilidad real como fracción del costo ajustado — el número que
     * compara el semáforo. 0 si no hay costo (ítem de pura reventa).
     */
    public function rentabilidad(): float
    {
        return $this->costoAjustado > 0 ? $this->utilidadReal / $this->costoAjustado : 0.0;
    }

    /**
     * @return array{costo_base: float, costo_ajustado: float, factor_complejidad: float, margen: float, precio: float, it: float, utilidad_antes_iue: float, iue: float, utilidad_real: float, rentabilidad: float, estado: string, recomendacion: string, iva: float, precio_final: float, instalacion: float, total_precio_final: float}
     */
    public function toArray(): array
    {
        return [
            'costo_base' => round($this->costoBase, 2),
            'costo_ajustado' => round($this->costoAjustado, 2),
            'factor_complejidad' => round($this->factorComplejidad, 2),
            'margen' => round($this->margen, 4),
            'precio' => round($this->precio, 2),
            'it' => round($this->it, 2),
            'utilidad_antes_iue' => round($this->utilidadAntesIue, 2),
            'iue' => round($this->iue, 2),
            'utilidad_real' => round($this->utilidadReal, 2),
            'rentabilidad' => round($this->rentabilidad(), 4),
            'estado' => $this->estado,
            'recomendacion' => $this->recomendacion,
            'iva' => round($this->iva, 2),
            'precio_final' => round($this->precioFinal, 2),
            'instalacion' => round($this->instalacion, 2),
            'total_precio_final' => round($this->totalPrecioFinal, 2),
        ];
    }
}
