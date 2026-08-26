<?php

namespace App\Services\Calculo;

/**
 * Medidas de una línea de cotización/pedido para UNA unidad del producto
 * (ancho/alto/profundo en metros). `cantidad` (cuántas unidades se piden)
 * se maneja aparte, en App\Services\Calculo\CosteoProductoService — no es
 * una variable de fórmula, para que una receta describa siempre "cuánto
 * material lleva una unidad" y no se pueda duplicar el multiplicador.
 */
final readonly class MedidasCotizacion
{
    public function __construct(
        public ?float $ancho = null,
        public ?float $alto = null,
        public ?float $profundo = null,
    ) {}

    /**
     * ancho × alto, si ambas medidas están presentes.
     */
    public function area(): ?float
    {
        return $this->ancho !== null && $this->alto !== null
            ? $this->ancho * $this->alto
            : null;
    }

    /**
     * Perímetro del rectángulo ancho×alto, si ambas medidas están presentes.
     */
    public function perimetro(): ?float
    {
        return $this->ancho !== null && $this->alto !== null
            ? ($this->ancho + $this->alto) * 2
            : null;
    }

    /**
     * Variables disponibles para una expresión de `formula`, listas para
     * NXP\MathExecutor::setVars(). Solo incluye las que se pueden calcular
     * con las medidas dadas — una fórmula que referencia una variable no
     * incluida (ej. `profundo` en un producto sin esa medida) falla con
     * App\Exceptions\FormulaInvalidaException en vez de calcular en
     * silencio con un 0 incorrecto.
     *
     * @return array<string, float>
     */
    public function variables(): array
    {
        return array_filter([
            'ancho' => $this->ancho,
            'alto' => $this->alto,
            'profundo' => $this->profundo,
            'area' => $this->area(),
            'perimetro' => $this->perimetro(),
        ], fn (?float $valor): bool => $valor !== null);
    }
}
