<?php

namespace App\Console\Commands;

use App\Services\Reporte\ResumenSemanalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerarResumenSemanal extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bi:resumen-semanal
        {--semana= : Cualquier fecha (YYYY-MM-DD) de la semana a resumir; por defecto la última semana completa}
        {--sin-notificar : No avisar por la campanita (útil para regenerar semanas pasadas)}';

    /**
     * @var string
     */
    protected $description = 'Genera el resumen semanal del BI (consolidado y por sucursal), redactado por IA o con la plantilla de respaldo';

    public function handle(ResumenSemanalService $resumenes): int
    {
        $semana = $this->option('semana')
            ? Carbon::parse($this->option('semana'), config('ia.zona_horaria'))->startOfWeek(Carbon::MONDAY)
            : $resumenes->ultimaSemanaCompleta();

        $generados = $resumenes->generarSemana($semana, notificar: ! $this->option('sin-notificar'));

        $this->info("Semana del {$semana->toDateString()}: {$generados->count()} resumen(es).");

        foreach ($generados as $resumen) {
            $this->line(' - '.($resumen->hechos['alcance'] ?? '—')." ({$resumen->origen}): ".($resumen->contenido['titular'] ?? ''));
        }

        return self::SUCCESS;
    }
}
