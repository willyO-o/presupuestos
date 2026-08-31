<?php

namespace App\Services\Reporte;

use App\Models\Pedido;
use App\Models\PedidoSeguimiento;

/**
 * Reporte de producción: cumplimiento de tiempos de entrega, duración
 * promedio de cada etapa y carga de trabajo por área.
 */
class ReporteProduccionService
{
    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $entregados = Pedido::query()
            ->where('estado', 'ENTREGADO')
            ->whereNotNull('fecha_entrega_estimada')
            ->whereNotNull('fecha_entrega_real')
            ->get(['numero_pedido', 'fecha_entrega_estimada', 'fecha_entrega_real']);

        $aTiempo = $entregados->filter(
            fn ($p) => $p->fecha_entrega_real->lessThanOrEqualTo($p->fecha_entrega_estimada)
        );

        $seguimientosCerrados = PedidoSeguimiento::query()
            ->whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin')
            ->get(['etapa', 'fecha_inicio', 'fecha_fin']);

        return [
            'cumplimiento' => [
                'entregados' => $entregados->count(),
                'a_tiempo' => $aTiempo->count(),
                'tarde' => $entregados->count() - $aTiempo->count(),
                'cumplimiento_pct' => $entregados->count() > 0
                    ? round($aTiempo->count() / $entregados->count() * 100, 1)
                    : null,
            ],
            'duracion_por_etapa' => collect(PedidoSeguimiento::ETAPAS)
                ->map(function (string $etapa) use ($seguimientosCerrados): array {
                    $grupo = $seguimientosCerrados->where('etapa', $etapa);
                    $horas = $grupo->map(fn ($s) => $s->fecha_inicio->diffInHours($s->fecha_fin));

                    return [
                        'etapa' => $etapa,
                        'dias_promedio' => $horas->isNotEmpty() ? round($horas->avg() / 24, 1) : null,
                        'muestras' => $grupo->count(),
                    ];
                })
                ->all(),
            'carga_por_area' => PedidoSeguimiento::query()
                ->whereNull('fecha_fin')
                ->with('area:id,nombre')
                ->get(['id', 'area_id'])
                ->groupBy(fn ($s) => $s->area?->nombre ?? 'Sin área')
                ->map(fn ($g, $nombre) => ['area' => $nombre, 'items_abiertos' => $g->count()])
                ->values()
                ->all(),
            'pedidos_activos_por_etapa' => collect(Pedido::FLUJO)
                ->filter(fn ($e) => $e !== 'ENTREGADO')
                ->map(fn (string $etapa) => [
                    'etapa' => $etapa,
                    'pedidos' => Pedido::query()->where('estado', $etapa)->count(),
                ])
                ->values()
                ->all(),
        ];
    }
}
