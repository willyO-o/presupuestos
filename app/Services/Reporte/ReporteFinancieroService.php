<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\Pedido;
use Illuminate\Support\Carbon;

/**
 * Reporte financiero: ventas por periodo/sucursal, rentabilidad real de
 * cada pedido (costo real de materiales vs. total cotizado) y cuentas por
 * cobrar. Agregación en PHP para ser independiente del motor de BD.
 */
class ReporteFinancieroService
{
    /**
     * @return array<string, mixed>
     */
    public function datos(?string $desde = null, ?string $hasta = null): array
    {
        $desde = $desde ? Carbon::parse($desde)->startOfDay() : Carbon::now()->startOfYear();
        $hasta = $hasta ? Carbon::parse($hasta)->endOfDay() : Carbon::now()->endOfDay();

        $cotizaciones = Cotizacion::query()
            ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('sucursal:id,nombre')
            ->get(['id', 'sucursal_id', 'fecha', 'total']);

        $pedidos = Pedido::query()
            ->whereIn('estado', ['ACABADO', 'ENTREGADO'])
            ->with(['cotizacion:id,codigo_verificacion', 'detalles.materialesUsados', 'pagos'])
            ->get();

        return [
            'rango' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'ventas_por_mes' => $cotizaciones
                ->groupBy(fn ($c) => $c->fecha->format('Y-m'))
                ->map(fn ($g, $mes) => ['mes' => $mes, 'total' => round((float) $g->sum('total'), 2)])
                ->values()
                ->sortBy('mes')
                ->values()
                ->all(),
            'ventas_por_sucursal' => $cotizaciones
                ->groupBy(fn ($c) => $c->sucursal?->nombre ?? 'Sin sucursal')
                ->map(fn ($g, $nombre) => ['sucursal' => $nombre, 'total' => round((float) $g->sum('total'), 2)])
                ->values()
                ->all(),
            'total_vendido' => round((float) $cotizaciones->sum('total'), 2),
            'rentabilidad' => $pedidos->map(function (Pedido $pedido): array {
                $costoReal = round((float) $pedido->detalles->flatMap->materialesUsados->sum('costo_real'), 2);
                $ingreso = (float) $pedido->total;

                return [
                    'pedido' => $pedido->numero_pedido,
                    'cotizacion' => $pedido->cotizacion?->codigo_verificacion,
                    'ingreso' => $ingreso,
                    'costo_real' => $costoReal,
                    'margen' => round($ingreso - $costoReal, 2),
                    'margen_pct' => $ingreso > 0 ? round(($ingreso - $costoReal) / $ingreso * 100, 1) : null,
                ];
            })->values()->all(),
            'cuentas_por_cobrar' => $this->cuentasPorCobrar(),
        ];
    }

    /**
     * @return array{total: float, pedidos: list<array<string, mixed>>}
     */
    private function cuentasPorCobrar(): array
    {
        $pedidos = Pedido::query()
            ->where('estado', '!=', 'CANCELADO')
            ->with('pagos')
            ->get()
            ->map(fn (Pedido $p) => [
                'pedido' => $p->numero_pedido,
                'total' => (float) $p->total,
                'pagado' => $p->totalPagado(),
                'saldo' => $p->saldo(),
            ])
            ->filter(fn ($p) => $p['saldo'] > 0)
            ->values();

        return [
            'total' => round((float) $pedidos->sum('saldo'), 2),
            'pedidos' => $pedidos->all(),
        ];
    }
}
