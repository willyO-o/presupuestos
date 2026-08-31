<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use Illuminate\Console\Command;

class VencerCotizaciones extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cotizaciones:vencer';

    /**
     * @var string
     */
    protected $description = 'Marca como VENCIDA las cotizaciones PENDIENTE cuya fecha_vencimiento ya pasó';

    public function handle(): int
    {
        $vencidas = Cotizacion::query()
            ->where('estado', 'PENDIENTE')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => 'VENCIDA']);

        $this->info("{$vencidas} cotización(es) marcada(s) como VENCIDA.");

        return self::SUCCESS;
    }
}
