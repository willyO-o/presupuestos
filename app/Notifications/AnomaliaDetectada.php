<?php

namespace App\Notifications;

use App\Models\BiAnomalia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Anomalía de prioridad alta recién detectada (ver
 * App\Services\Reporte\DeteccionAnomaliasService). Solo las ALTA notifican:
 * las medias quedan en la pestaña Alertas para no saturar la campanita.
 */
class AnomaliaDetectada extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BiAnomalia $anomalia,
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
            'titulo' => 'Anomalía detectada',
            'mensaje' => $this->anomalia->titulo,
            'url' => route('reportes.bi.alertas').'#anomalias',
            'icono' => 'fa-solid fa-wave-square',
            'color' => 'danger',
        ];
    }
}
