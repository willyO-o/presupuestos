<?php

namespace App\Services\Pedido;

use App\Models\Pedido;
use App\Models\SeguimientoPostventa;

/**
 * Programa el contacto de postventa de un pedido entregado: el paso
 * "Seguimiento 7 días" del Proceso 3 de la empresa (y §11 del documento de
 * proceso completo). Es el ÚNICO lugar donde se crea un
 * `seguimiento_postventa`, para que ningún pedido entregado se quede sin
 * seguimiento por haberse marcado como entregado desde otra pantalla.
 *
 * Lo llama `Pedido::recalcularEstado()` cuando el pedido pasa a ENTREGADO
 * (desde NotaEntregaController o desde el avance de etapa en
 * PedidoController), y el seeder de datos de prueba.
 */
class ProgramarPostventaService
{
    /**
     * Crea el seguimiento pendiente si el pedido está entregado y todavía no
     * tiene uno. Idempotente: llamarlo dos veces no duplica ni reprograma
     * (la FK `pedido_id` es única).
     */
    public function programar(Pedido $pedido): ?SeguimientoPostventa
    {
        if ($pedido->estado !== 'ENTREGADO' || $pedido->seguimientoPostventa()->exists()) {
            return null;
        }

        $entrega = $pedido->fecha_entrega_real ?? now();

        return $pedido->seguimientoPostventa()->create([
            'fecha_programada' => $entrega->copy()->addDays((int) config('postventa.dias_seguimiento'))->toDateString(),
            'estado' => 'PENDIENTE',
        ]);
    }
}
