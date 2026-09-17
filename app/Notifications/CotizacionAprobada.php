<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el vendedor dueño de la cotización: el cliente (o un administrador en
 * su representación) la aprobó (ver
 * App\Http\Controllers\CotizacionController::aprobar y
 * App\Http\Controllers\ClientePortalController::responder).
 */
class CotizacionAprobada extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Cotizacion $cotizacion,
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
            'titulo' => 'Cotización aprobada',
            'mensaje' => "{$this->cotizacion->cliente?->razon_social} aprobó la cotización {$this->cotizacion->codigo_verificacion}",
            'url' => route('cotizaciones.show', $this->cotizacion),
            'icono' => 'fa-solid fa-circle-check',
            'color' => 'success',
        ];
    }
}
