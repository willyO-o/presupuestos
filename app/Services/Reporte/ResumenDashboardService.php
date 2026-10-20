<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * KPIs del panel principal (Pages/Dashboard). Todas las agregaciones se
 * resuelven en el servidor; el frontend solo pinta.
 *
 * **Acotado por sucursal**: cada consulta pasa por `visiblePara($usuario)`. Un
 * KPI es lo que más se lee de un vistazo y lo que menos se cuestiona: si el
 * listado va acotado y el número de arriba no, el número miente con más
 * autoridad que la tabla.
 *
 * La excepción es `materiales_bajo_stock`: `material` no tiene `sucursal_id`
 * (el inventario es de toda la empresa, un solo almacén), así que ese dato es
 * global y la pantalla lo rotula como tal.
 *
 * **Comparación contra el mes anterior al MISMO CORTE**: el día 5 del mes, el
 * mes en curso tiene 5 días de movimiento y el anterior 30 completos; comparar
 * contra el mes entero daría siempre una "caída" falsa. Los `*_anterior` suman
 * del día 1 al mismo día del mes pasado (o a su último día, si es más corto).
 */
class ResumenDashboardService
{
    private const ESTADOS_VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    /**
     * @return array<string, mixed>
     */
    public function resumen(User $usuario): array
    {
        $ahora = Carbon::now();
        $inicioMes = $ahora->copy()->startOfMonth();
        $inicioMesAnterior = $inicioMes->copy()->subMonthNoOverflow();
        $corteMesAnterior = $inicioMesAnterior->copy()
            ->addDays($ahora->day - 1)
            ->endOfDay()
            ->min($inicioMesAnterior->copy()->endOfMonth());

        $cotizaciones = fn () => Cotizacion::query()->visiblePara($usuario);
        $pedidos = fn () => Pedido::query()->visiblePara($usuario);
        $pagos = fn () => Pago::query()->visiblePara($usuario);

        $decididas = $cotizaciones()->whereIn('estado', ['APROBADA', 'RECHAZADA', 'CONVERTIDA'])->count();
        $convertidas = $cotizaciones()->where('estado', 'CONVERTIDA')->count();

        $entregados = $pedidos()
            ->where('estado', 'ENTREGADO')
            ->whereNotNull('fecha_entrega_estimada')
            ->whereNotNull('fecha_entrega_real')
            ->get(['fecha_entrega_estimada', 'fecha_entrega_real']);

        $aTiempo = $entregados->filter(fn ($p) => $p->fecha_entrega_real->lessThanOrEqualTo($p->fecha_entrega_estimada))->count();

        $vendido = fn (Carbon $desde, Carbon $hasta): float => round((float) $cotizaciones()
            ->whereIn('estado', self::ESTADOS_VENDIDOS)
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('total'), 2);

        $cobrado = fn (Carbon $desde, Carbon $hasta): float => round((float) $pagos()
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->sum('monto'), 2);

        return [
            'ventas_mes' => $vendido($inicioMes, $ahora),
            'ventas_mes_anterior' => $vendido($inicioMesAnterior, $corteMesAnterior),
            'cotizaciones_mes' => $cotizaciones()->where('fecha', '>=', $inicioMes)->count(),
            'cotizaciones_mes_anterior' => $cotizaciones()->whereBetween('fecha', [$inicioMesAnterior, $corteMesAnterior])->count(),
            'tasa_conversion' => $decididas > 0 ? round($convertidas / $decididas * 100, 1) : 0.0,
            'ingresos_mes' => $cobrado($inicioMes, $ahora),
            'ingresos_mes_anterior' => $cobrado($inicioMesAnterior, $corteMesAnterior),
            // Global a propósito: el stock no es de una sucursal (ver el
            // docblock de la clase). La pantalla lo rotula.
            'materiales_bajo_stock' => Material::query()->estado('ACTIVO')->conStockBajo()->count(),
            'pedidos_por_etapa' => collect(Pedido::FLUJO)
                ->mapWithKeys(fn (string $etapa) => [
                    $etapa => $pedidos()->where('estado', $etapa)->count(),
                ])->all(),
            'entregas' => [
                'a_tiempo' => $aTiempo,
                'tarde' => $entregados->count() - $aTiempo,
            ],
            'ventas_por_mes' => $this->ventasPorMes($usuario, $ahora),
            'pedidos_recientes' => $this->pedidosRecientes($usuario),
            'productos_mas_vendidos' => $this->productosMasVendidos($usuario, $ahora),
        ];
    }

    /**
     * Total cotizado de las cotizaciones aprobadas/convertidas, mes a mes del
     * año en curso, junto al mismo mes del año anterior.
     *
     * Los meses que todavía no llegaron van en `null` (no en 0): un mes futuro
     * no vendió cero, simplemente no existe todavía, y el gráfico corta la
     * línea en vez de desplomarla.
     *
     * @return list<array{mes: string, anio: int, total: float|null, anterior: float}>
     */
    private function ventasPorMes(User $usuario, Carbon $ahora): array
    {
        $inicioAnio = $ahora->copy()->startOfYear();
        $desde = $inicioAnio->copy()->subYear();

        $porMes = Cotizacion::query()
            ->visiblePara($usuario)
            ->whereIn('estado', self::ESTADOS_VENDIDOS)
            ->whereBetween('fecha', [$desde, $ahora])
            ->get(['fecha', 'total'])
            ->groupBy(fn ($c) => $c->fecha->format('Y-m'))
            ->map(fn (Collection $grupo) => round((float) $grupo->sum('total'), 2));

        return collect(range(0, 11))
            ->map(function (int $i) use ($inicioAnio, $ahora, $porMes): array {
                $mes = $inicioAnio->copy()->addMonths($i);

                return [
                    'mes' => ucfirst(rtrim($mes->translatedFormat('M'), '.')),
                    'anio' => $mes->year,
                    'total' => $mes->greaterThan($ahora) ? null : $porMes->get($mes->format('Y-m'), 0.0),
                    'anterior' => $porMes->get($mes->copy()->subYear()->format('Y-m'), 0.0),
                ];
            })
            ->all();
    }

    /**
     * Los últimos cinco pedidos registrados, para la tabla del panel.
     *
     * @return list<array{id: int, numero_pedido: string, cliente: string|null, fecha: string|null, total: float, estado: string}>
     */
    private function pedidosRecientes(User $usuario): array
    {
        return Pedido::query()
            ->visiblePara($usuario)
            ->with('cotizacion:id,cliente_id', 'cotizacion.cliente:id,razon_social')
            ->latest('fecha_pedido')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'cotizacion_id', 'numero_pedido', 'fecha_pedido', 'total', 'estado'])
            ->map(fn (Pedido $pedido): array => [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'cliente' => $pedido->cotizacion?->cliente?->razon_social,
                'fecha' => $pedido->fecha_pedido?->toDateString(),
                'total' => (float) $pedido->total,
                'estado' => $pedido->estado,
            ])
            ->all();
    }

    /**
     * Los cinco productos que más facturaron en los últimos 12 meses, con su
     * participación sobre el total vendido de TODOS los productos (no solo de
     * estos cinco: si no, el quinto parecería pesar más de lo que pesa).
     *
     * @return list<array{nombre: string, cantidad: float, monto: float, participacion: float}>
     */
    private function productosMasVendidos(User $usuario, Carbon $ahora): array
    {
        $lineas = CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->whereNotNull('producto_id')
            ->whereHas('cotizacion', fn ($q) => $q
                ->whereIn('estado', self::ESTADOS_VENDIDOS)
                ->whereBetween('fecha', [$ahora->copy()->startOfMonth()->subMonths(11), $ahora]))
            ->with('producto:id,nombre')
            ->get(['id', 'cotizacion_id', 'producto_id', 'cantidad', 'subtotal'])
            ->filter(fn (CotizacionDetalle $linea) => $linea->producto);

        $totalVendido = (float) $lineas->sum('subtotal');

        return $lineas
            ->groupBy(fn (CotizacionDetalle $linea) => $linea->producto->nombre)
            ->map(fn (Collection $grupo, string $nombre): array => [
                'nombre' => $nombre,
                'cantidad' => round((float) $grupo->sum('cantidad'), 2),
                'monto' => round((float) $grupo->sum('subtotal'), 2),
                'participacion' => $totalVendido > 0
                    ? round((float) $grupo->sum('subtotal') / $totalVendido * 100, 1)
                    : 0.0,
            ])
            ->sortByDesc('monto')
            ->take(5)
            ->values()
            ->all();
    }
}
