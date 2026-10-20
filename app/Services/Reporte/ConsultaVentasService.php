<?php

namespace App\Services\Reporte;

use App\Models\CotizacionDetalle;
use App\Models\FamiliaProducto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consultas flexibles de ventas sobre las LÍNEAS de presupuesto: la base de
 * las herramientas del asistente de BI.
 *
 * Se trabaja a nivel de línea (cotizacion_detalle) porque las preguntas
 * suelen ser por producto, categoría o familia ("¿cuánto vendimos en letras
 * 3D?"). Definiciones, que el asistente muestra en "cómo lo calculé":
 *
 * - Vendido = presupuestos APROBADA o CONVERTIDA.
 * - Monto = suma de subtotales de línea (precio sin IVA, antes del descuento
 *   de cabecera). Por eso no coincide al centavo con el total facturado del
 *   reporte financiero, que incluye IVA e instalación.
 * - Margen bruto = subtotal − costo ajustado, solo en líneas con hoja de
 *   costos (costo > 0): las anteriores al motor de margen guardan costo 0.
 *
 * Todo pasa por `visiblePara($usuario)`: el asistente nunca ve sucursales
 * que el usuario no ve en pantalla. La agregación es en PHP (igual que el
 * resto del BI) para funcionar igual en MariaDB y sqlite.
 */
class ConsultaVentasService
{
    public const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    public const METRICAS = [
        'monto_vendido' => ['Monto vendido (sin IVA)', 'bs'],
        'monto_presupuestado' => ['Monto presupuestado (sin IVA)', 'bs'],
        'unidades_vendidas' => ['Unidades vendidas', 'numero'],
        'presupuestos' => ['Presupuestos emitidos', 'numero'],
        'ventas' => ['Presupuestos vendidos', 'numero'],
        'ticket_promedio' => ['Venta promedio (sin IVA)', 'bs'],
        'tasa_aprobacion' => ['Tasa de aprobación', 'porcentaje'],
        'margen_bruto' => ['Margen bruto (precio − costo)', 'bs'],
        'rentabilidad_bruta' => ['Rentabilidad bruta sobre costo', 'porcentaje'],
    ];

    public const AGRUPACIONES = ['ninguno', 'mes', 'semana', 'familia', 'categoria', 'producto', 'sucursal', 'vendedor', 'cliente'];

    public const FILTROS = ['familia_id', 'categoria_id', 'producto_id', 'sucursal_id', 'empleado_id', 'cliente_id'];

    /**
     * Líneas de presupuesto del periodo con todo lo necesario para filtrar y
     * agrupar.
     *
     * @param  array<string, int|null>  $filtros
     * @return Collection<int, CotizacionDetalle>
     */
    public function lineas(User $usuario, Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        $filtros = array_filter(array_intersect_key($filtros, array_flip(self::FILTROS)), fn ($v) => $v !== null && $v !== '');

        return CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->whereHas('cotizacion', fn ($q) => $q
                ->whereBetween('fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
                ->when($filtros['sucursal_id'] ?? null, fn ($q, $id) => $q->where('sucursal_id', $id))
                ->when($filtros['empleado_id'] ?? null, fn ($q, $id) => $q->where('empleado_id', $id))
                ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id)))
            ->when($filtros['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $id) => $q->whereHas('producto', fn ($p) => $p->where('categoria_producto_id', $id)))
            ->when($filtros['familia_id'] ?? null, fn ($q, $id) => $q->whereHas('producto.categoriaProducto', fn ($c) => $c->where('familia_producto_id', $id)))
            ->with([
                'cotizacion:id,fecha,estado,sucursal_id,empleado_id,cliente_id',
                'cotizacion.sucursal:id,nombre',
                'cotizacion.empleado:id,nombres,paterno',
                'cotizacion.cliente:id,razon_social',
                'producto:id,nombre,categoria_producto_id',
                'producto.categoriaProducto:id,nombre,familia_producto_id',
                'producto.categoriaProducto.familia:id,nombre',
            ])
            ->get(['id', 'cotizacion_id', 'producto_id', 'cantidad', 'subtotal', 'costo_ajustado']);
    }

    /**
     * Valor de una métrica sobre un grupo de líneas.
     *
     * @param  Collection<int, CotizacionDetalle>  $lineas
     */
    public function valor(string $metrica, Collection $lineas): ?float
    {
        $vendidas = $lineas->filter(fn ($l) => in_array($l->cotizacion?->estado, self::VENDIDOS, true));
        $conCosto = $vendidas->filter(fn ($l) => (float) $l->costo_ajustado > 0);

        return match ($metrica) {
            'monto_vendido' => round((float) $vendidas->sum('subtotal'), 2),
            'monto_presupuestado' => round((float) $lineas->sum('subtotal'), 2),
            'unidades_vendidas' => round((float) $vendidas->sum('cantidad'), 2),
            'presupuestos' => (float) $lineas->pluck('cotizacion_id')->unique()->count(),
            'ventas' => (float) $vendidas->pluck('cotizacion_id')->unique()->count(),
            'ticket_promedio' => ($n = $vendidas->pluck('cotizacion_id')->unique()->count()) > 0
                ? round((float) $vendidas->sum('subtotal') / $n, 2)
                : null,
            'tasa_aprobacion' => $this->tasaAprobacion($lineas),
            'margen_bruto' => round((float) $conCosto->sum(fn ($l) => (float) $l->subtotal - (float) $l->costo_ajustado), 2),
            'rentabilidad_bruta' => ($costo = (float) $conCosto->sum('costo_ajustado')) > 0
                ? round((float) $conCosto->sum(fn ($l) => (float) $l->subtotal - (float) $l->costo_ajustado) / $costo * 100, 1)
                : null,
            default => null,
        };
    }

    /**
     * Agrupa las líneas y calcula la métrica por grupo. Para `mes`/`semana`
     * se rellenan los periodos sin movimiento con cero (una serie de tiempo
     * con huecos miente sobre la tendencia).
     *
     * @param  Collection<int, CotizacionDetalle>  $lineas
     * @return list<array{grupo: string, valor: float|null}>
     */
    public function agrupar(string $metrica, string $agrupacion, Collection $lineas, Carbon $desde, Carbon $hasta): array
    {
        if ($agrupacion === 'ninguno') {
            return [['grupo' => 'Total', 'valor' => $this->valor($metrica, $lineas)]];
        }

        if (in_array($agrupacion, ['mes', 'semana'], true)) {
            $clave = $agrupacion === 'mes'
                ? fn ($l) => $l->cotizacion->fecha->format('Y-m')
                : fn ($l) => $l->cotizacion->fecha->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

            $grupos = $lineas->groupBy($clave);

            return $this->periodos($agrupacion, $desde, $hasta)
                ->map(fn (string $etiqueta, string $k) => [
                    'grupo' => $etiqueta,
                    'valor' => $this->valor($metrica, $grupos->get($k, collect())) ?? 0.0,
                ])
                ->values()
                ->all();
        }

        $nombre = match ($agrupacion) {
            'familia' => fn ($l) => $l->producto?->categoriaProducto?->familia?->nombre ?? FamiliaProducto::SIN_FAMILIA,
            'categoria' => fn ($l) => $l->producto?->categoriaProducto?->nombre ?? 'Sin categoría',
            'producto' => fn ($l) => $l->producto?->nombre ?? 'Ítem sin catálogo',
            'sucursal' => fn ($l) => $l->cotizacion?->sucursal?->nombre ?? 'Sin sucursal',
            'vendedor' => fn ($l) => $l->cotizacion?->empleado
                ? trim($l->cotizacion->empleado->nombres.' '.$l->cotizacion->empleado->paterno)
                : 'Portal / sin vendedor',
            'cliente' => fn ($l) => $l->cotizacion?->cliente?->razon_social ?? 'Sin cliente',
        };

        return $lineas->groupBy($nombre)
            ->map(fn (Collection $g, string $grupo) => ['grupo' => $grupo, 'valor' => $this->valor($metrica, $g)])
            ->sortByDesc(fn ($fila) => $fila['valor'] ?? -INF)
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, string> clave del periodo => etiqueta
     */
    public function periodos(string $agrupacion, Carbon $desde, Carbon $hasta): Collection
    {
        $periodos = collect();

        if ($agrupacion === 'mes') {
            $cursor = $desde->copy()->startOfMonth();
            while ($cursor->lessThanOrEqualTo($hasta)) {
                $periodos->put($cursor->format('Y-m'), PeriodoReporte::etiquetaMes($cursor));
                $cursor->addMonth();
            }

            return $periodos;
        }

        $cursor = $desde->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lessThanOrEqualTo($hasta)) {
            $periodos->put($cursor->toDateString(), 'Sem '.$cursor->isoWeek().' · '.$cursor->translatedFormat('j M'));
            $cursor->addWeek();
        }

        return $periodos;
    }

    /**
     * Aprobadas ÷ decididas (aprobadas + rechazadas + vencidas). Las
     * pendientes todavía pueden aprobarse, así que no cuentan.
     *
     * @param  Collection<int, CotizacionDetalle>  $lineas
     */
    private function tasaAprobacion(Collection $lineas): ?float
    {
        $porCotizacion = $lineas->unique('cotizacion_id')->map(fn ($l) => $l->cotizacion?->estado);
        $decididas = $porCotizacion->reject(fn ($e) => $e === 'PENDIENTE' || $e === null);

        if ($decididas->isEmpty()) {
            return null;
        }

        return round($decididas->filter(fn ($e) => in_array($e, self::VENDIDOS, true))->count() / $decididas->count() * 100, 1);
    }
}
