<?php

namespace App\Services\Reporte;

use App\Models\CotizacionDetalle;
use App\Models\HistorialPrecioMaterial;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Inteligencia de negocios: productos/categorías más vendidos, evolución
 * del costo de los materiales y proyección de demanda (media móvil +
 * tendencia lineal). Es el aporte central del proyecto de grado — la
 * matemática se mantiene simple y explicable a propósito (vive en
 * `Pronostico`, compartida con los demás reportes de BI).
 *
 * Todo se acota al periodo del filtro de fechas: sin periodo, los últimos
 * `config('bi.meses_por_defecto')` meses.
 */
class InteligenciaNegociosService
{
    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario, ?PeriodoReporte $periodo = null): array
    {
        $periodo ??= PeriodoReporte::desdeFiltros(null, null, config('bi.meses_por_defecto'));

        $lineasVendidas = CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->whereHas('cotizacion', fn ($q) => $q
                ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
                ->whereBetween('fecha', [$periodo->desde, $periodo->hasta]))
            ->with('producto:id,nombre,categoria_producto_id', 'producto.categoriaProducto:id,nombre')
            ->get(['id', 'cotizacion_id', 'producto_id', 'cantidad', 'subtotal']);

        return [
            'periodo' => $periodo->toArray(),
            'productos_mas_vendidos' => $this->agrupar(
                $lineasVendidas->filter(fn ($l) => $l->producto),
                fn ($l) => $l->producto->nombre,
            ),
            'categorias_mas_vendidas' => $this->agrupar(
                $lineasVendidas->filter(fn ($l) => $l->producto?->categoriaProducto),
                fn ($l) => $l->producto->categoriaProducto->nombre,
            ),
            // `evolucion_costos` NO se acota: el historial de precios cuelga de
            // `material`, que no tiene sucursal (el inventario es de toda la
            // empresa). La pantalla lo rotula como dato global.
            'evolucion_costos' => $this->evolucionCostos($periodo),
            'demanda' => $this->demanda($usuario, $periodo),
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
     * puntos de historial dentro del periodo), para graficar cómo evolucionó
     * el costo.
     *
     * @return list<array{material: string, puntos: list<array{fecha: string, precio: float}>}>
     */
    private function evolucionCostos(PeriodoReporte $periodo): array
    {
        return HistorialPrecioMaterial::query()
            ->with('material:id,nombre')
            ->whereBetween('vigente_desde', [$periodo->desde, $periodo->hasta])
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
     * Demanda mensual de pedidos en el periodo, media móvil de 3 meses y
     * proyección de los meses siguientes por regresión lineal simple, con su
     * intervalo de predicción y la ficha del método (`metodo`).
     *
     * @return array<string, mixed>
     */
    private function demanda(User $usuario, PeriodoReporte $periodo): array
    {
        $pedidos = Pedido::query()
            ->visiblePara($usuario)
            ->whereBetween('fecha_pedido', [$periodo->desde, $periodo->hasta])
            ->get(['fecha_pedido']);

        $porMes = $pedidos->groupBy(fn ($p) => $p->fecha_pedido->format('Y-m'))->map->count();

        $serie = $periodo->meses()->map(fn (string $etiqueta, string $clave): array => [
            'mes' => $etiqueta,
            'pedidos' => $porMes->get($clave, 0),
        ])->values();

        $valores = $serie->pluck('pedidos')->all();

        $mediaMovil = collect(Pronostico::mediaMovil($valores))
            ->map(fn (?float $valor, int $i): array => [
                'mes' => $serie[$i]['mes'],
                'valor' => round((float) $valor, 1),
            ])
            ->all();

        $analisis = $periodo->pronostico($valores);
        $etiquetas = $periodo->mesesSiguientes(config('bi.meses_proyeccion'));

        return [
            'serie' => $serie->all(),
            'media_movil' => $mediaMovil,
            'proyeccion' => collect($analisis['proyeccion'])
                ->map(fn (array $punto, int $i): array => [
                    'mes' => $etiquetas[$i],
                    'pedidos_estimados' => round($punto['valor'], 1),
                    'inferior' => round($punto['inferior'], 1),
                    'superior' => round($punto['superior'], 1),
                ])
                ->all(),
            'variacion_mensual' => $analisis['variacion_mensual'] === null ? null : round($analisis['variacion_mensual'], 2),
            'metodo' => $analisis['metodo'],
            'estacionalidad' => $this->estacionalidad($pedidos),
        ];
    }

    /**
     * Total de pedidos por mes calendario dentro del periodo (todos los años
     * juntos), para ver en qué meses la empresa tiene más movimiento.
     *
     * @param  Collection<int, Pedido>  $pedidos
     * @return list<array{mes: string, pedidos: int}>
     */
    private function estacionalidad(Collection $pedidos): array
    {
        $porMesDelAnio = $pedidos
            ->groupBy(fn ($p) => $p->fecha_pedido->month)
            ->map->count();

        return collect(range(1, 12))->map(fn (int $m): array => [
            'mes' => Carbon::create(null, $m, 1)->translatedFormat('M'),
            'pedidos' => $porMesDelAnio->get($m, 0),
        ])->all();
    }
}
