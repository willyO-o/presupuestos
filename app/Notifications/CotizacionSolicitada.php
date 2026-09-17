<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Para el administrador: un cliente solicitó una cotización desde el portal
 * (ver App\Http\Controllers\ClientePortalController::solicitarStore).
 */
class CotizacionSolicitada extends Notification
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
            'titulo' => 'Nueva solicitud de cotización',
            'mensaje' => "{$this->cotizacion->cliente?->razon_social} solicitó una cotización ({$this->cotizacion->codigo_verificacion})",
            'url' => route('cotizaciones.show', $this->cotizacion),
            'icono' => 'fa-solid fa-file-circle-question',
            'color' => 'warning',
        ];
    }
}
