<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vence a diario las cotizaciones PENDIENTE que pasaron su fecha_vencimiento.
Schedule::command('cotizaciones:vencer')->dailyAt('00:15');

// Inteligencia de negocios con IA (config/ia.php y config/bi.php). Hora de
// la empresa: el servidor corre en UTC.
Schedule::command('bi:detectar-anomalias')
    ->dailyAt('06:30')
    ->timezone(config('ia.zona_horaria'))
    ->withoutOverlapping();

Schedule::command('bi:resumen-semanal')
    ->weeklyOn(1, '07:00')
    ->timezone(config('ia.zona_horaria'))
    ->withoutOverlapping();
