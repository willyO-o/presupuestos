<?php

namespace App\Services\Calculo;

use App\Exceptions\FormulaInvalidaException;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use InvalidArgumentException;

/**
 * Calcula el costo de materiales (BOM) de un producto para una cotización,
 * resolviendo cada línea de producto_material — factor fijo × driver de
 * unidad_medida, o fórmula dinámica evaluada con las medidas del pedido —
 * en vez de que el vendedor lo calcule a mano (ver database-design.md §7).
 */
class CosteoProductoService
{
    public function __construct(
        private readonly FormulaCalculator $formulaCalculator,
    ) {}

    /**
     * @throws InvalidArgumentException si `$producto` necesita un driver
     *                                  (área para M2, ancho para METRO_LINEAL) que `$medidas` no provee.
     * @throws FormulaInvalidaException si una línea dinámica falla al evaluar.
     */
    public function calcular(Producto $producto, MedidasCotizacion $medidas, float $cantidad = 1.0): ResultadoCosteo
    {
        $producto->loadMissing(['productoMateriales.material', 'productoMateriales.formula']);

        $driver = $this->driver($producto, $medidas);

        $lineas = $producto->productoMateriales
            ->map(function (ProductoMaterial $linea) use ($medidas, $driver, $cantidad): LineaCosteo {
                $cantidadPorUnidad = $linea->esDinamica()
                    ? $this->formulaCalculator->evaluar($linea->formula->expresion, $medidas)
                    : (float) $linea->cantidad_por_unidad * $driver;

                $cantidadBruta = $cantidadPorUnidad * $cantidad;
                $cantidadConsumida = $this->redondearACompra($cantidadBruta, $linea->material->redondeo_compra);
                $costo = $cantidadConsumida * (float) $linea->material->precio_unitario;

                return new LineaCosteo($linea, $cantidadConsumida, $costo, $cantidadBruta);
            });

        return new ResultadoCosteo(
            costoMaterial: round((float) $lineas->sum(fn (LineaCosteo $linea): float => $linea->costo), 2),
            lineas: $lineas->all(),
        );
    }

    /**
     * Redondea hacia arriba la cantidad total consumida al múltiplo
     * `material.redondeo_compra` (en la unidad del material): los materiales
     * se compran en unidades enteras de presentación (una plancha, una barra
     * de 6 m, una caja) y el sobrante de un corte rara vez se reutiliza, así
     * que costear "1/4 de plancha" subestima el costo real. `null`/0 = sin
     * redondeo (material que se corta a medida: lona/vinil de rollo,
     * líquidos). Ver la migración `add_redondeo_compra_to_material_table`.
     */
    private function redondearACompra(float $cantidad, int|float|string|null $multiplo): float
    {
        $multiplo = (float) $multiplo;

        if ($multiplo <= 0.0 || $cantidad <= 0.0) {
            return $cantidad;
        }

        // Epsilon para que el ruido de coma flotante (p. ej. 2.00000001 tras
        // multiplicar) no empuje al siguiente múltiplo/unidad de compra.
        $unidades = (int) ceil($cantidad / $multiplo - 1e-9);

        return round($unidades * $multiplo, 4);
    }

    /**
     * Multiplicador que convierte el factor fijo `cantidad_por_unidad` de
     * una línea estática en cantidad real, según `producto.unidad_medida`:
     * área para M2, un solo lado (ancho) para METRO_LINEAL — convención de
     * este servicio, no está en database-design.md, ver
     * .ai/rules/migrations.md — o 1 para UNIDAD. No aplica a líneas
     * dinámicas: una fórmula ya recibe ancho/alto/profundo/area/perimetro
     * directamente vía MedidasCotizacion::variables().
     */
    private function driver(Producto $producto, MedidasCotizacion $medidas): float
    {
        return match ($producto->unidad_medida) {
            'M2' => $medidas->area() ?? throw new InvalidArgumentException(
                "El producto «{$producto->nombre}» se cotiza por M2 pero faltan ancho/alto."
            ),
            'METRO_LINEAL' => $medidas->ancho ?? throw new InvalidArgumentException(
                "El producto «{$producto->nombre}» se cotiza por METRO_LINEAL pero falta ancho."
            ),
            'UNIDAD' => 1.0,
            default => throw new InvalidArgumentException("unidad_medida desconocida: {$producto->unidad_medida}"),
        };
    }
}
