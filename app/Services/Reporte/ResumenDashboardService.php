<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Support\Carbon;

/**
 * KPIs del panel principal (Pages/Dashboard). Todas las agregaciones se
 * resuelven en el servidor; el frontend solo pinta.
 */
class ResumenDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        $inicioMes = Carbon::now()->startOfMonth();

        $decididas = Cotizacion::query()->whereIn('estado', ['APROBADA', 'RECHAZADA', 'CONVERTIDA'])->count();
        $convertidas = Cotizacion::query()->where('estado', 'CONVERTIDA')->count();

        $entregados = Pedido::query()
            ->where('estado', 'ENTREGADO')
            ->whereNotNull('fecha_entrega_estimada')
            ->whereNotNull('fecha_entrega_real')
            ->get(['fecha_entrega_estimada', 'fecha_entrega_real']);

        $aTiempo = $entregados->filter(fn ($p) => $p->fecha_entrega_real->lessThanOrEqualTo($p->fecha_entrega_estimada))->count();

        return [
            'cotizaciones_mes' => Cotizacion::query()->where('fecha', '>=', $inicioMes)->count(),
            'tasa_conversion' => $decididas > 0 ? round($convertidas / $decididas * 100, 1) : 0.0,
            'ingresos_mes' => round((float) Pago::query()->where('fecha_pago', '>=', $inicioMes)->sum('monto'), 2),
            'materiales_bajo_stock' => Material::query()->estado('ACTIVO')->conStockBajo()->count(),
            'pedidos_por_etapa' => collect(Pedido::FLUJO)
                ->mapWithKeys(fn (string $etapa) => [
                    $etapa => Pedido::query()->where('estado', $etapa)->count(),
                ])->all(),
            'entregas' => [
                'a_tiempo' => $aTiempo,
                'tarde' => $entregados->count() - $aTiempo,
            ],
            'ventas_por_mes' => $this->ventasPorMes(),
        ];
    }

    /**
     * Total cotizado de las cotizaciones aprobadas/convertidas, por mes,
     * en los últimos 6 meses.
     *
     * @return list<array{mes: string, total: float}>
     */
    private function ventasPorMes(): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(5);

        $porMes = Cotizacion::query()
            ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
            ->where('fecha', '>=', $desde)
            ->get(['fecha', 'total'])
            ->groupBy(fn ($c) => $c->fecha->format('Y-m'))
            ->map(fn ($grupo) => round((float) $grupo->sum('total'), 2));

        return collect(range(0, 5))
            ->map(function (int $i) use ($desde, $porMes): array {
                $mes = $desde->copy()->addMonths($i);

                return [
                    'mes' => $mes->translatedFormat('M Y'),
                    'total' => $porMes->get($mes->format('Y-m'), 0.0),
                ];
            })
            ->all();
    }
}
