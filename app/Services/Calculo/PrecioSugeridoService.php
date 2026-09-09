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
    public function __construct(
        private readonly CosteoProductoService $costeoProducto,
        private readonly MotorMargenService $motorMargen,
    ) {}

    /**
     * @return array{costo_material_unitario: float, margen: float, precio_sugerido: float, motor: array<string, mixed>, insumos: list<array{material_id: int, tipo: string, descripcion: string, unidad: string, cantidad: float, costo_unitario: float, subtotal: float}>, lineas: list<array{material: string, unidad: string, cantidad: float, cantidad_bruta: float, redondeada: bool, costo: float}>}
     */
    public function calcular(
        Producto $producto,
        ?float $ancho,
        ?float $alto,
        ?float $profundo = null,
        ?TipoProyecto $tipoProyecto = null,
        float $instalacion = 0.0,
    ): array {
        $medidas = new MedidasCotizacion(ancho: $ancho, alto: $alto, profundo: $profundo);

        // `cantidad = 1`: la hoja de costos describe UNA unidad; la cantidad
        // pedida se multiplica después, al armar el subtotal de la línea.
        $resultado = $this->costeoProducto->calcular($producto, $medidas, 1.0);

        $costoUnitario = round($resultado->costoMaterial, 2);
        $motor = $this->motorMargen->calcularCon($tipoProyecto, $costoUnitario, $instalacion);

        return [
            'costo_material_unitario' => $costoUnitario,
            'margen' => round($motor->margen, 4),
            'precio_sugerido' => round($motor->precio, 2),
            'motor' => $motor->toArray(),
            'insumos' => array_map(fn (LineaCosteo $linea): array => [
                'material_id' => $linea->productoMaterial->material_id,
                'tipo' => 'MATERIAL',
                'descripcion' => $linea->productoMaterial->material->nombre,
                'unidad' => $linea->productoMaterial->material->unidad_medida,
                'cantidad' => round($linea->cantidadConsumida, 4),
                'costo_unitario' => round((float) $linea->productoMaterial->material->precio_unitario, 4),
                'subtotal' => round($linea->costo, 4),
            ], $resultado->lineas),
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
