<?php

namespace App\Services\Compra;

use App\Models\Compra;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aprueba una compra (PENDIENTE → PAGADA) y recién ahí impacta el
 * inventario: sube el stock de cada material, actualiza su precio con el
 * costo de esta compra y deja una fila en `historial_precio_material`
 * (insumo del BI de evolución de costos, y evita alterar cotizaciones/
 * pedidos ya cerrados).
 *
 * Usado por CompraController::aprobar y CompraSeeder — no duplicar la
 * lógica en ninguno de los dos.
 */
class AprobarCompraService
{
    public function aprobar(Compra $compra): void
    {
        if ($compra->estado !== 'PENDIENTE') {
            throw new RuntimeException('Solo una compra pendiente puede aprobarse.');
        }

        DB::transaction(function () use ($compra): void {
            $compra->update(['estado' => 'PAGADA']);

            $porMaterial = $compra->detalles()
                ->selectRaw('material_id, SUM(cantidad) as cantidad_total, SUM(subtotal) as costo_total')
                ->groupBy('material_id')
                ->get();

            foreach ($porMaterial as $fila) {
                /** @var Material $material */
                $material = Material::query()->lockForUpdate()->findOrFail($fila->material_id);

                $costoUnitario = round((float) $fila->costo_total / (float) $fila->cantidad_total, 2);
                $precioUnitarioAnterior = (float) $material->precio_unitario;

                // Se escala `precio_presentacion` con la misma proporción que
                // cambió el precio unitario, para que ambos sigan coherentes.
                $precioPresentacion = $precioUnitarioAnterior > 0
                    ? round((float) $material->precio_presentacion * ($costoUnitario / $precioUnitarioAnterior), 2)
                    : (float) $material->precio_presentacion;

                $material->update([
                    'stock_actual' => (float) $material->stock_actual + (float) $fila->cantidad_total,
                    'precio_unitario' => $costoUnitario,
                    'precio_presentacion' => $precioPresentacion,
                ]);

                $material->historialPrecios()->create([
                    'precio_presentacion' => $precioPresentacion,
                    'precio_unitario' => $costoUnitario,
                    'vigente_desde' => $compra->fecha->toDateString(),
                ]);
            }
        });
    }
}
