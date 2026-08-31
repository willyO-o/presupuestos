<?php

namespace App\Services\Pedido;

use App\Models\Cotizacion;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Convierte una cotización APROBADA en un pedido / orden de trabajo
 * (paso "Confirmación → Realiza la orden" del flujo del proyecto,
 * database-design.md §12): crea el `pedido`, copia las líneas a
 * `pedido_detalle` y deja la cotización como CONVERTIDA.
 *
 * Usado por PedidoController::store y PedidoSeeder — no duplicar.
 */
class ConvertirCotizacionService
{
    public function convertir(Cotizacion $cotizacion, ?string $fechaEntregaEstimada = null): Pedido
    {
        if (! $cotizacion->esConvertible()) {
            throw new RuntimeException('Solo una cotización aprobada y sin pedido puede convertirse.');
        }

        return DB::transaction(function () use ($cotizacion, $fechaEntregaEstimada): Pedido {
            $cotizacion->loadMissing('detalles');

            $pedido = Pedido::create([
                'cotizacion_id' => $cotizacion->id,
                'numero_pedido' => $this->generarNumero(),
                'fecha_pedido' => now()->toDateString(),
                'fecha_entrega_estimada' => $fechaEntregaEstimada,
                'estado' => 'DISENO',
                'total' => $cotizacion->total,
            ]);

            $pedido->detalles()->createMany(
                $cotizacion->detalles->map(fn ($linea): array => [
                    'cotizacion_detalle_id' => $linea->id,
                    'descripcion' => $linea->descripcion,
                    'ancho' => $linea->ancho,
                    'alto' => $linea->alto,
                    'cantidad' => $linea->cantidad,
                    'estado_item' => 'DISENO',
                ])->all(),
            );

            $cotizacion->update(['estado' => 'CONVERTIDA']);

            return $pedido;
        });
    }

    private function generarNumero(): string
    {
        do {
            $numero = 'PED-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Pedido::where('numero_pedido', $numero)->exists());

        return $numero;
    }
}
