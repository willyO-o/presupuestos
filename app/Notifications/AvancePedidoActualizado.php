<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el vendedor dueño de la cotización de origen: se modificó el avance
 * (etapa) de un ítem de su pedido (ver
 * App\Http\Controllers\PedidoController::actualizarEstado).
 */
class AvancePedidoActualizado extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Pedido $pedido,
        private readonly PedidoDetalle $detalle,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Avance de pedido actualizado',
            'mensaje' => "Pedido {$this->pedido->numero_pedido}: \"{$this->detalle->descripcion}\" pasó a {$this->detalle->estado_item}",
            'url' => route('pedidos.show', $this->pedido),
            'icono' => 'fa-solid fa-dolly',
            'color' => 'primary',
        ];
    }
}
