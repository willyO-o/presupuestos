<?php

namespace App\Notifications;

use App\Models\Compra;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el administrador: se emitió una nueva orden de compra a un proveedor
 * (ver App\Http\Controllers\CompraController::store).
 */
class OrdenCompraEmitida extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Compra $compra,
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
            'titulo' => 'Nueva orden de compra',
            'mensaje' => "Compra #{$this->compra->id} a {$this->compra->proveedor?->nombre} por Bs ".number_format((float) $this->compra->total, 2),
            'url' => route('compras.show', $this->compra),
            'icono' => 'fa-solid fa-cart-shopping',
            'color' => 'info',
        ];
    }
}
