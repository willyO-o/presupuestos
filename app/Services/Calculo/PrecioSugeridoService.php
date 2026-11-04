<?php

namespace App\Services\Calculo;

use App\Models\Producto;
use App\Models\TipoProyecto;

/**
 * Traduce la receta/BOM de un producto (vía CosteoProductoService) en la hoja
 * de costos de una línea de cotización: la lista de insumos con su cantidad y
 * costo unitario (las columnas A-E del Excel) y, pasándola por el motor de
 * margen (MotorMargenService) con el nivel de complejidad elegido, el precio
 * unitario sugerido.
 *
 * Lo que devuelve `insumos` es exactamente lo que el formulario carga como
 * filas de `cotizacion_detalle_item`: a partir de ahí el vendedor puede
 * agregar mano de obra, quitar materiales o corregir cantidades a mano, igual
 * que hacía sobre la hoja de cálculo. El precio sugerido siempre se puede
 * sobreescribir — ver database-design.md §8.
 */
class PrecioSugeridoService
{
    /**
     * Descripción de la fila de mano de obra que aporta el producto. El
     * formulario la reconoce por este texto para reemplazarla (y no
     * duplicarla) cuando el vendedor vuelve a traer los insumos.
     */
    public const DESCRIPCION_MANO_OBRA = 'Horas de mano de obra (según producto)';

    public function __construct(
        private readonly CosteoProductoService $costeoProducto,
        private readonly MotorMargenService $motorMargen,
    ) {}

    /**
     * @return array{costo_material_unitario: float, costo_mano_obra_unitario: float, margen: float, precio_sugerido: float, motor: array<string, mixed>, insumos: list<array{material_id: int|null, tipo: string, descripcion: string, unidad: string, cantidad: float, costo_unitario: float, subtotal: float}>, lineas: list<array{material: string, unidad: string, cantidad: float, cantidad_bruta: float, redondeada: bool, costo: float}>}
     */
    public function calcular(
        Producto $producto,
        ?float $ancho,
        ?float $alto,
        ?float $profundo = null,
        ?TipoProyecto $tipoProyecto = null,
        float $instalacion = 0.0,
        ?int $niveles = null,
    ): array {
        $medidas = new MedidasCotizacion(ancho: $ancho, alto: $alto, profundo: $profundo, niveles: $niveles);

        // `cantidad = 1`: la hoja de costos describe UNA unidad; la cantidad
        // pedida se multiplica después, al armar el subtotal de la línea.
        $resultado = $this->costeoProducto->calcular($producto, $medidas, 1.0);

        $costoUnitario = round($resultado->costoMaterial, 2);
        $manoObra = $this->manoObraDe($producto);
        $motor = $this->motorMargen->calcularCon(
            $tipoProyecto,
            $costoUnitario + ($manoObra['subtotal'] ?? 0.0),
            $instalacion,
        );

        $insumos = array_map(fn (LineaCosteo $linea): array => [
            'material_id' => $linea->productoMaterial->material_id,
            'tipo' => 'MATERIAL',
            'descripcion' => $linea->productoMaterial->material->nombre,
            'unidad' => $linea->productoMaterial->material->unidad_medida,
            'cantidad' => round($linea->cantidadConsumida, 4),
            'costo_unitario' => round((float) $linea->productoMaterial->material->precio_unitario, 4),
            'subtotal' => round($linea->costo, 4),
        ], $resultado->lineas);

        if ($manoObra !== null) {
            $insumos[] = $manoObra;
        }

        return [
            'costo_material_unitario' => $costoUnitario,
            'costo_mano_obra_unitario' => round($manoObra['subtotal'] ?? 0.0, 2),
            'margen' => round($motor->margen, 4),
            'precio_sugerido' => round($motor->precio, 2),
            'motor' => $motor->toArray(),
            'insumos' => $insumos,
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

    /**
     * Fila de mano de obra de UNA unidad: las horas del producto a la tarifa
     * de la empresa. `null` si el producto no tiene horas cargadas.
     *
     * @return array{material_id: null, tipo: string, descripcion: string, unidad: string, cantidad: float, costo_unitario: float, subtotal: float}|null
     */
    private function manoObraDe(Producto $producto): ?array
    {
        $horas = (float) $producto->horas_mano_obra;

        if ($horas <= 0) {
            return null;
        }

        $costoHora = (float) config('cotizacion.costo_hora_mano_obra');

        return [
            'material_id' => null,
            'tipo' => 'MANO_OBRA',
            'descripcion' => self::DESCRIPCION_MANO_OBRA,
            'unidad' => 'HORA',
            'cantidad' => round($horas, 4),
            'costo_unitario' => round($costoHora, 4),
            'subtotal' => round($horas * $costoHora, 4),
        ];
    }
}
