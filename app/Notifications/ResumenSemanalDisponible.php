<?php

namespace App\Notifications;

use App\Models\BiResumen;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * El resumen semanal del BI ya está publicado (ver
 * App\Services\Reporte\ResumenSemanalService).
 */
class ResumenSemanalDisponible extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BiResumen $resumen,
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
        $titular = $this->resumen->contenido['titular'] ?? '';

        return [
            'titulo' => 'Resumen semanal · '.($this->resumen->hechos['alcance'] ?? 'Toda la empresa'),
            'mensaje' => $titular,
            'url' => route('reportes.bi.resumen', ['resumen' => $this->resumen->id]),
            'icono' => 'fa-solid fa-newspaper',
            'color' => 'primary',
        ];
    }
}
