<?php

namespace App\Services\Reporte;

use App\Models\Sucursal;
use App\Models\User;

/**
 * Rótulo de qué datos cubre un informe impreso, según las sucursales que ve
 * quien lo genera.
 *
 * En pantalla lo dice `EtiquetaAlcance.vue`; en papel tiene que ir escrito:
 * un total de una sola sucursal leído como el de la empresa es el error que
 * más daño hace (ver .ai/rules/reporte.md), y un PDF circula sin el panel al
 * lado para aclararlo.
 */
class AlcanceReporte
{
    public static function rotulo(User $usuario): string
    {
        $visibles = $usuario->sucursalesVisibles();

        if ($visibles === null) {
            return 'Toda la empresa';
        }

        $nombres = Sucursal::query()->whereIn('id', $visibles)->orderBy('nombre')->pluck('nombre');

        return $nombres->isEmpty() ? 'Ninguna sucursal asignada' : 'Sucursales: '.$nombres->implode(', ');
    }
}
