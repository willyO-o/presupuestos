<?php

namespace App\Notifications;

use App\Models\NotaEntrega;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el vendedor dueño de la cotización de origen: se emitió una nota de
 * entrega de su pedido (ver App\Http\Controllers\NotaEntregaController::store).
 */
class OrdenEntregaEmitida extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NotaEntrega $nota,
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
            'titulo' => 'Orden de entrega emitida',
            'mensaje' => "Nota de entrega {$this->nota->numero_nota} del pedido {$this->nota->pedido?->numero_pedido}",
            'url' => route('notas-entrega.show', $this->nota),
            'icono' => 'fa-solid fa-truck-ramp-box',
            'color' => 'info',
        ];
    }
}
