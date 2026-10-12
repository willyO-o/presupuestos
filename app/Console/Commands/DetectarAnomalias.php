<?php

namespace App\Console\Commands;

use App\Services\Reporte\DeteccionAnomaliasService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DetectarAnomalias extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bi:detectar-anomalias {--fecha= : Día de referencia (YYYY-MM-DD); por defecto hoy en la hora de la empresa}';

    /**
     * @var string
     */
    protected $description = 'Detecta anomalías del negocio (caídas de cotizaciones, márgenes bajos, vendedores inactivos, clientes en riesgo)';

    public function handle(DeteccionAnomaliasService $deteccion): int
    {
        $fecha = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'), config('ia.zona_horaria'))
            : null;

        $nuevas = $deteccion->ejecutar($fecha);

        $this->info("{$nuevas->count()} anomalía(s) nueva(s).");

        foreach ($nuevas as $anomalia) {
            $this->line(" - [{$anomalia->severidad}] {$anomalia->titulo} ({$anomalia->redaccion})");
        }

        return self::SUCCESS;
    }
}
