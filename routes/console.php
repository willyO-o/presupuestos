<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vence a diario las cotizaciones PENDIENTE que pasaron su fecha_vencimiento.
Schedule::command('cotizaciones:vencer')->dailyAt('00:15');
