<?php

namespace App\Services\Compra;

use App\Models\Compra;
use App\Models\CompraDetalle;
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
 * Usado por CompraController::aprobar y HistorialComercialSeeder — no duplicar la
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

            // Todo se lleva a la unidad de medida del material: una línea
            // comprada por rollo suma `cantidad × contenido` m² al stock, y su
            // precio por m² es el subtotal repartido en esos m².
            $porMaterial = $compra->detalles()->get()
                ->groupBy('material_id')
                ->map(fn ($lineas) => [
                    'cantidad' => $lineas->sum(fn (CompraDetalle $linea): float => $linea->cantidadEnMedida()),
                    'costo' => (float) $lineas->sum('subtotal'),
                ]);

            foreach ($porMaterial as $materialId => $fila) {
                if ($fila['cantidad'] <= 0) {
                    continue;
                }

                /** @var Material $material */
                $material = Material::query()->lockForUpdate()->findOrFail($materialId);

                $costoUnitario = round($fila['costo'] / $fila['cantidad'], 2);

                // El precio de la presentación es lo que trae × su precio por
                // unidad de medida: los dos quedan coherentes.
                $precioPresentacion = round($costoUnitario * max((float) $material->contenido_presentacion, 0.0001), 2);

                $material->update([
                    'stock_actual' => (float) $material->stock_actual + $fila['cantidad'],
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
