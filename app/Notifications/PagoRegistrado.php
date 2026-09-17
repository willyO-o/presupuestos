<?php

namespace App\Notifications;

use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el administrador: se registró un pago contra un pedido, con el estado
 * de cobranza resultante (ver App\Http\Controllers\PagoController::store).
 */
class PagoRegistrado extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Pago $pago,
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
        $pedido = $this->pago->pedido;

        return [
            'titulo' => 'Pago registrado',
            'mensaje' => "Pedido {$pedido?->numero_pedido}: Bs ".number_format((float) $this->pago->monto, 2)
                ." ({$this->pago->metodo_pago}). Estado de cobranza: {$pedido?->estadoPago()}",
            'url' => route('pagos.index'),
            'icono' => 'fa-solid fa-money-check-dollar',
            'color' => 'success',
        ];
    }
}
