<?php

namespace App\Services\Reporte;

use App\Models\CotizacionDetalle;
use App\Models\HistorialPrecioMaterial;
use App\Models\Pedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Inteligencia de negocios: productos/categorías más vendidos, evolución
 * del costo de los materiales y proyección de demanda (media móvil +
 * tendencia lineal). Es el aporte central del proyecto de grado — la
 * matemática se mantiene simple y explicable a propósito.
 */
class InteligenciaNegociosService
{
    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $lineasVendidas = CotizacionDetalle::query()
            ->whereHas('cotizacion', fn ($q) => $q->whereIn('estado', ['APROBADA', 'CONVERTIDA']))
            ->with('producto:id,nombre,categoria_producto_id', 'producto.categoriaProducto:id,nombre')
            ->get(['id', 'cotizacion_id', 'producto_id', 'cantidad', 'subtotal']);

        return [
            'productos_mas_vendidos' => $this->agrupar(
                $lineasVendidas->filter(fn ($l) => $l->producto),
                fn ($l) => $l->producto->nombre,
            ),
            'categorias_mas_vendidas' => $this->agrupar(
                $lineasVendidas->filter(fn ($l) => $l->producto?->categoriaProducto),
                fn ($l) => $l->producto->categoriaProducto->nombre,
            ),
            'evolucion_costos' => $this->evolucionCostos(),
            'demanda' => $this->demanda(),
        ];
    }

    /**
     * @param  Collection<int, CotizacionDetalle>  $lineas
     * @return list<array{nombre: string, cantidad: float, monto: float}>
     */
    private function agrupar(Collection $lineas, callable $clave): array
    {
        return $lineas
            ->groupBy($clave)
            ->map(fn (Collection $g, string $nombre): array => [
                'nombre' => $nombre,
                'cantidad' => round((float) $g->sum('cantidad'), 2),
                'monto' => round((float) $g->sum('subtotal'), 2),
            ])
            ->sortByDesc('monto')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Serie de `precio_unitario` por material (los que tienen al menos 2
     * puntos de historial), para graficar cómo evolucionó el costo.
     *
     * @return list<array{material: string, puntos: list<array{fecha: string, precio: float}>}>
     */
    private function evolucionCostos(): array
    {
        return HistorialPrecioMaterial::query()
            ->with('material:id,nombre')
            ->orderBy('vigente_desde')
            ->get(['material_id', 'precio_unitario', 'vigente_desde'])
            ->groupBy(fn ($h) => $h->material?->nombre ?? '—')
            ->filter(fn (Collection $g) => $g->count() >= 2)
            ->map(fn (Collection $g, string $nombre): array => [
                'material' => $nombre,
                'puntos' => $g->map(fn ($h) => [
                    'fecha' => $h->vigente_desde->toDateString(),
                    'precio' => (float) $h->precio_unitario,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Demanda mensual de pedidos (12 meses), media móvil de 3 meses y
     * proyección de los próximos 3 meses por regresión lineal simple.
     *
     * @return array<string, mixed>
     */
    private function demanda(): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11);

        $porMes = Pedido::query()
            ->where('fecha_pedido', '>=', $desde)
            ->get(['fecha_pedido'])
            ->groupBy(fn ($p) => $p->fecha_pedido->format('Y-m'))
            ->map->count();

        $serie = collect(range(0, 11))->map(function (int $i) use ($desde, $porMes): array {
            $mes = $desde->copy()->addMonths($i);

            return [
                'mes' => $mes->translatedFormat('M Y'),
                'clave' => $mes->format('Y-m'),
                'pedidos' => $porMes->get($mes->format('Y-m'), 0),
            ];
        });

        $valores = $serie->pluck('pedidos')->all();

        // Media móvil de 3 meses.
        $mediaMovil = $serie->map(function ($punto, int $i) use ($valores): array {
            $ventana = array_slice($valores, max(0, $i - 2), min($i + 1, 3));

            return ['mes' => $punto['mes'], 'valor' => round(array_sum($ventana) / count($ventana), 1)];
        })->all();

        return [
            'serie' => $serie->map(fn ($p) => ['mes' => $p['mes'], 'pedidos' => $p['pedidos']])->all(),
            'media_movil' => $mediaMovil,
            'proyeccion' => $this->proyectar($valores),
            'estacionalidad' => $this->estacionalidad(),
        ];
    }

    /**
     * Regresión lineal por mínimos cuadrados sobre la serie mensual y
     * proyección de los siguientes 3 meses (nunca negativa).
     *
     * @param  list<int>  $valores
     * @return list<array{mes: string, pedidos_estimados: float}>
     */
    private function proyectar(array $valores): array
    {
        $n = count($valores);
        if ($n < 3) {
            return [];
        }

        $sumX = $sumY = $sumXY = $sumX2 = 0;
        foreach ($valores as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominador = $n * $sumX2 - $sumX ** 2;
        $pendiente = $denominador != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominador : 0;
        $intercepto = ($sumY - $pendiente * $sumX) / $n;

        return collect(range(1, 3))->map(function (int $k) use ($n, $pendiente, $intercepto): array {
            $mes = Carbon::now()->startOfMonth()->addMonths($k);

            return [
                'mes' => $mes->translatedFormat('M Y'),
                'pedidos_estimados' => round(max($intercepto + $pendiente * ($n - 1 + $k), 0), 1),
            ];
        })->all();
    }

    /**
     * Total de pedidos por mes calendario (todos los años juntos), para ver
     * en qué meses la empresa tiene más movimiento.
     *
     * @return list<array{mes: string, pedidos: int}>
     */
    private function estacionalidad(): array
    {
        $porMesDelAnio = Pedido::query()
            ->get(['fecha_pedido'])
            ->groupBy(fn ($p) => $p->fecha_pedido->month)
            ->map->count();

        return collect(range(1, 12))->map(fn (int $m): array => [
            'mes' => Carbon::create(null, $m, 1)->translatedFormat('M'),
            'pedidos' => $porMesDelAnio->get($m, 0),
        ])->all();
    }
}
