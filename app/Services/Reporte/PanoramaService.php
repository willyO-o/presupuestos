<?php

namespace App\Services\Reporte;

use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\FamiliaProducto;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panorama del BI: la vista para decidir. KPIs contra el periodo anterior,
 * ventas y margen por mes, ingresos y margen por tipo de producto (con
 * drill-down), embudo de ventas, los 5 mejores clientes y productos, y las
 * ventas por vendedor y por ciudad. Todo con los mismos filtros globales.
 *
 * **Agrega en SQL** (`SUM`/`COUNT`/`GROUP BY`, el mes con ConsultaSql::mes):
 * no carga las líneas en memoria. Cada métrica es una consulta agregada.
 *
 * **Qué es cada número** (y lo dice la ayuda "i" de cada cuadro):
 * - Ingreso = subtotal de cada línea de las cotizaciones APROBADAS o
 *   CONVERTIDAS, con el descuento de la cotización prorrateado por línea.
 *   Sin IVA ni instalación: es lo que vende cada tipo de producto.
 * - Margen = margen bruto presupuestado: (ingreso − costo ajustado de la
 *   línea) / ingreso, antes de impuestos. Solo sobre las líneas con hoja de
 *   costos: las anteriores al motor de margen guardan costo 0 y su "margen"
 *   sería del 100 %. La rentabilidad después de impuestos sigue en la
 *   pestaña Presupuestos y rentabilidad.
 * - Periodo anterior = el mismo rango corrido tantos meses como dura
 *   (12 meses → el mismo tramo del año anterior), para comparar mes con mes.
 *
 * Respeta el alcance por sucursal del usuario (`visiblePara`) en todo.
 */
class PanoramaService
{
    public const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    /** Clientes y productos del top. */
    public const TOP = 5;

    /** Barras con nombre en vendedor y ciudad; el resto va en "Otros". */
    public const BARRAS = 8;

    /** Ingreso neto de la línea (ver ConsultaSql::INGRESO_NETO_LINEA). */
    private const NETO = ConsultaSql::INGRESO_NETO_LINEA;

    /** Columnas agregadas que comparten todas las consultas de líneas. */
    private const AGREGADOS = 'SUM('.self::NETO.') AS ingreso, '
        .'SUM(CASE WHEN cotizacion_detalle.costo_ajustado > 0 THEN '.self::NETO.' ELSE 0 END) AS ingreso_medido, '
        .'SUM(CASE WHEN cotizacion_detalle.costo_ajustado > 0 THEN cotizacion_detalle.costo_ajustado ELSE 0 END) AS costo, '
        .'COUNT(DISTINCT c.id) AS ventas';

    /**
     * @param  array{familia_id: ?int, categoria_id: ?int, cliente_id: ?int, sucursal_id: ?int}  $filtros
     * @return array<string, mixed>
     */
    public function datos(User $usuario, PeriodoReporte $periodo, array $filtros): array
    {
        $meses = $periodo->meses();
        $corrimiento = max($meses->count(), 1);
        $anteriorDesde = $periodo->desde->copy()->subMonthsNoOverflow($corrimiento);
        $anteriorHasta = $periodo->hasta->copy()->subMonthsNoOverflow($corrimiento);

        // Un periodo que cierra a fin de mes compara contra otro fin de mes:
        // 30 de noviembre corrido un mes es el 31 de octubre, no el 30.
        if ($periodo->hasta->isSameDay($periodo->hasta->copy()->endOfMonth())) {
            $anteriorHasta = $anteriorHasta->endOfMonth();
        }

        $actual = $this->totales($usuario, $periodo->desde, $periodo->hasta, $filtros);
        $anterior = $this->totales($usuario, $anteriorDesde, $anteriorHasta, $filtros);

        $mensual = $this->mensual($usuario, $periodo->desde, $periodo->hasta, $filtros);
        $mensualAnterior = $this->mensual($usuario, $anteriorDesde, $anteriorHasta, $filtros);

        return [
            'periodo' => $periodo->toArray(),
            'periodo_anterior' => [
                'desde' => $anteriorDesde->toDateString(),
                'hasta' => $anteriorHasta->toDateString(),
            ],
            'kpis' => $this->kpis($actual, $anterior),
            'ventas_mensuales' => $this->ventasMensuales($meses, $corrimiento, $mensual, $mensualAnterior),
            'ingresos_margen' => $this->ingresosYMargen($meses, $mensual, $actual),
            'por_tipo' => $this->porTipo($usuario, $periodo, $filtros, $actual['ingreso']),
            'embudo' => $this->embudo($usuario, $periodo, $filtros),
            'top_clientes' => $this->topClientes($usuario, $periodo, $filtros, $actual['ingreso']),
            'top_productos' => $this->topProductos($usuario, $periodo, $filtros, $actual['ingreso']),
            'por_vendedor' => $this->porVendedor($usuario, $periodo, $filtros, $actual['ingreso']),
            'por_ciudad' => $this->porCiudad($usuario, $periodo, $filtros, $actual['ingreso']),
            'opciones' => $this->opciones($usuario),
        ];
    }

    /* -----------------------------------------------------------------
     * Consultas base
     * -------------------------------------------------------------- */

    /**
     * Líneas vendidas del rango con los filtros, con la cotización (`c`), el
     * producto (`p`) y su categoría (`cat`) unidos para agrupar.
     *
     * @param  array<string, ?int>  $filtros
     */
    private function lineas(User $usuario, Carbon $desde, Carbon $hasta, array $filtros): Builder
    {
        return CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->join('cotizacion as c', 'c.id', '=', 'cotizacion_detalle.cotizacion_id')
            ->leftJoin('producto as p', 'p.id', '=', 'cotizacion_detalle.producto_id')
            ->leftJoin('categoria_producto as cat', 'cat.id', '=', 'p.categoria_producto_id')
            ->whereIn('c.estado', self::VENDIDOS)
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->when($filtros['cliente_id'], fn (Builder $q, int $id) => $q->where('c.cliente_id', $id))
            ->when($filtros['sucursal_id'], fn (Builder $q, int $id) => $q->where('c.sucursal_id', $id))
            ->when($filtros['familia_id'], fn (Builder $q, int $id) => $q->where('cat.familia_producto_id', $id))
            ->when($filtros['categoria_id'], fn (Builder $q, int $id) => $q->where('p.categoria_producto_id', $id));
    }

    /**
     * Cotizaciones del rango (en cualquier estado) con los filtros. Con un
     * filtro de tipo de producto, cuentan las que tienen al menos una línea
     * de ese tipo.
     *
     * @param  array<string, ?int>  $filtros
     */
    private function cotizaciones(User $usuario, Carbon $desde, Carbon $hasta, array $filtros): Builder
    {
        return Cotizacion::query()
            ->visiblePara($usuario)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($filtros['cliente_id'], fn (Builder $q, int $id) => $q->where('cliente_id', $id))
            ->when($filtros['sucursal_id'], fn (Builder $q, int $id) => $q->where('sucursal_id', $id))
            ->when($filtros['categoria_id'], fn (Builder $q, int $id) => $q->whereHas(
                'detalles.producto',
                fn (Builder $p) => $p->where('categoria_producto_id', $id),
            ))
            ->when($filtros['familia_id'] && ! $filtros['categoria_id'], fn (Builder $q) => $q->whereHas(
                'detalles.producto.categoriaProducto',
                fn (Builder $cat) => $cat->where('familia_producto_id', $filtros['familia_id']),
            ));
    }

    /**
     * @param  array<string, ?int>  $filtros
     * @return array{ingreso: float, ingreso_medido: float, costo: float, ventas: int, emitidas: int, aprobadas: int, decididas: int}
     */
    private function totales(User $usuario, Carbon $desde, Carbon $hasta, array $filtros): array
    {
        $lineas = $this->lineas($usuario, $desde, $hasta, $filtros)->selectRaw(self::AGREGADOS)->toBase()->first();

        $estados = $this->cotizaciones($usuario, $desde, $hasta, $filtros)
            ->selectRaw('COUNT(*) AS emitidas, '
                ."SUM(CASE WHEN estado IN ('APROBADA', 'CONVERTIDA') THEN 1 ELSE 0 END) AS aprobadas, "
                ."SUM(CASE WHEN estado <> 'PENDIENTE' THEN 1 ELSE 0 END) AS decididas")
            ->toBase()
            ->first();

        return [
            'ingreso' => round((float) ($lineas->ingreso ?? 0), 2),
            'ingreso_medido' => round((float) ($lineas->ingreso_medido ?? 0), 2),
            'costo' => round((float) ($lineas->costo ?? 0), 2),
            'ventas' => (int) ($lineas->ventas ?? 0),
            'emitidas' => (int) ($estados->emitidas ?? 0),
            'aprobadas' => (int) ($estados->aprobadas ?? 0),
            'decididas' => (int) ($estados->decididas ?? 0),
        ];
    }

    /**
     * Ingreso, ingreso medido y costo por mes (`AAAA-MM` => fila).
     *
     * @param  array<string, ?int>  $filtros
     * @return Collection<string, object>
     */
    private function mensual(User $usuario, Carbon $desde, Carbon $hasta, array $filtros): Collection
    {
        $mes = ConsultaSql::mes('c.fecha');

        return $this->lineas($usuario, $desde, $hasta, $filtros)
            ->selectRaw("{$mes} AS mes, ".self::AGREGADOS)
            ->groupByRaw($mes)
            ->toBase()
            ->get()
            ->keyBy('mes');
    }

    /* -----------------------------------------------------------------
     * KPIs
     * -------------------------------------------------------------- */

    /**
     * @param  array<string, float|int>  $actual
     * @param  array<string, float|int>  $anterior
     * @return list<array<string, mixed>>
     */
    private function kpis(array $actual, array $anterior): array
    {
        $conversion = fn (array $t): ?float => $t['decididas'] > 0 ? round($t['aprobadas'] / $t['decididas'] * 100, 1) : null;
        $ticket = fn (array $t): ?float => $t['ventas'] > 0 ? round($t['ingreso'] / $t['ventas'], 2) : null;
        $margen = fn (array $t): ?float => $t['ingreso_medido'] > 0 ? round(($t['ingreso_medido'] - $t['costo']) / $t['ingreso_medido'] * 100, 1) : null;

        $kpi = fn (string $clave, string $etiqueta, ?float $valor, ?float $previo, string $formato, string $nota) => [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'valor' => $valor,
            'anterior' => $previo,
            'formato' => $formato,
            // Un porcentaje se compara en puntos (32 % → 35 % es +3 pp), no
            // en % de un %; el resto, en variación relativa.
            'unidad_variacion' => $formato === 'porcentaje' ? 'pp' : '%',
            'variacion' => match (true) {
                $valor === null || $previo === null => null,
                $formato === 'porcentaje' => round($valor - $previo, 1),
                $previo == 0.0 => null,
                default => round(($valor - $previo) / abs($previo) * 100, 1),
            },
            'nota' => $nota,
        ];

        return [
            $kpi('ventas', 'Ventas del periodo', $actual['ingreso'], $anterior['ingreso'] ?: null, 'bs', 'Sin IVA ni instalación'),
            $kpi('cotizaciones', 'Cotizaciones emitidas', (float) $actual['emitidas'], $anterior['emitidas'] ? (float) $anterior['emitidas'] : null, 'numero', "{$actual['aprobadas']} aprobadas"),
            $kpi('conversion', 'Conversión de cotizaciones', $conversion($actual), $conversion($anterior), 'porcentaje', 'Aprobadas entre las ya decididas'),
            $kpi('ticket', 'Venta promedio', $ticket($actual), $ticket($anterior), 'bs', "Calculada sobre {$actual['ventas']} ventas"),
            // "Presupuestado": sale de la hoja de costos de la cotización, no
            // de lo que producción consumió de verdad (eso es el margen del
            // reporte financiero). El rótulo lo dice para no confundirlos.
            $kpi('margen', 'Margen presupuestado', $margen($actual), $margen($anterior), 'porcentaje', 'Antes de impuestos · No es margen real'),
        ];
    }

    /* -----------------------------------------------------------------
     * Series por mes
     * -------------------------------------------------------------- */

    /**
     * @param  Collection<string, string>  $meses
     * @param  Collection<string, object>  $mensual
     * @param  Collection<string, object>  $mensualAnterior
     * @return array<string, mixed>
     */
    private function ventasMensuales(Collection $meses, int $corrimiento, Collection $mensual, Collection $mensualAnterior): array
    {
        $claves = $meses->keys();
        $actual = $claves->map(fn (string $k): float => round((float) ($mensual[$k]->ingreso ?? 0), 2))->all();
        $anterior = $claves->map(function (string $k) use ($corrimiento, $mensualAnterior): float {
            $previo = Carbon::createFromFormat('!Y-m', $k)->subMonthsNoOverflow($corrimiento)->format('Y-m');

            return round((float) ($mensualAnterior[$previo]->ingreso ?? 0), 2);
        })->all();

        $conVentas = array_filter($actual, fn (float $v): bool => $v > 0);
        // Con un solo mes el promedio es la misma barra: no se dibuja.
        $promedio = $conVentas === [] || count($actual) < 2 ? null : round(array_sum($actual) / count($actual), 2);
        $mejor = $conVentas === [] ? null : array_search(max($actual), $actual, true);

        return [
            'labels' => $meses->values()->all(),
            // `AAAA-MM` de cada barra: con un clic, la pantalla filtra ese mes.
            'claves' => $claves->all(),
            'actual' => $actual,
            'anterior' => $anterior,
            'promedio' => $promedio,
            'mejor' => $mejor,
            'hallazgo' => $this->hallazgoVentas($meses->values()->all(), $actual, $anterior, $mejor),
        ];
    }

    /**
     * @param  Collection<string, string>  $meses
     * @param  Collection<string, object>  $mensual
     * @param  array<string, float|int>  $actual
     * @return array<string, mixed>
     */
    private function ingresosYMargen(Collection $meses, Collection $mensual, array $actual): array
    {
        $claves = $meses->keys();
        $margen = $claves->map(function (string $k) use ($mensual): ?float {
            $fila = $mensual[$k] ?? null;

            return ($fila && (float) $fila->ingreso_medido > 0)
                ? round(((float) $fila->ingreso_medido - (float) $fila->costo) / (float) $fila->ingreso_medido * 100, 1)
                : null;
        })->all();

        $periodo = $actual['ingreso_medido'] > 0
            ? round(($actual['ingreso_medido'] - $actual['costo']) / $actual['ingreso_medido'] * 100, 1)
            : null;

        return [
            'labels' => $meses->values()->all(),
            'ingresos' => $claves->map(fn (string $k): float => round((float) ($mensual[$k]->ingreso ?? 0), 2))->all(),
            'margen' => $margen,
            'margen_periodo' => $periodo,
            'hallazgo' => $this->hallazgoMargen($meses->values()->all(), $margen, $periodo),
        ];
    }

    /* -----------------------------------------------------------------
     * Por tipo de producto (drill-down)
     * -------------------------------------------------------------- */

    /**
     * El nivel sale de los filtros: sin filtro, por familia; con familia,
     * sus categorías; con categoría, sus productos. Hacer clic en una barra
     * de la pantalla es aplicar ese filtro: el drill-down y los filtros
     * globales son la misma cosa, y todo el resto del Panorama baja con él.
     *
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function porTipo(User $usuario, PeriodoReporte $periodo, array $filtros, float $total): array
    {
        [$nivel, $id, $nombre, $siguiente] = match (true) {
            (bool) $filtros['categoria_id'] => ['producto', 'p.id', 'p.nombre', null],
            (bool) $filtros['familia_id'] => ['categoria', 'cat.id', 'cat.nombre', 'categoria_id'],
            default => ['familia', 'fam.id', 'fam.nombre', 'familia_id'],
        };

        $filas = $this->lineas($usuario, $periodo->desde, $periodo->hasta, $filtros)
            ->leftJoin('familia_producto as fam', 'fam.id', '=', 'cat.familia_producto_id')
            ->selectRaw("{$id} AS id, {$nombre} AS nombre, ".self::AGREGADOS)
            ->groupBy($id, $nombre)
            ->orderByDesc('ingreso')
            ->toBase()
            ->get()
            ->map(fn ($f): array => [
                'id' => $f->id === null ? null : (int) $f->id,
                // Una línea sin producto del catálogo (trabajo a medida o
                // reventa) no tiene familia: va con nombre propio.
                'nombre' => $f->nombre ?? 'Trabajos a medida (sin catálogo)',
                ...$this->metricas($f, $total),
            ]);

        $migas = [['texto' => 'Todos los tipos', 'filtro' => ['familia_id' => null, 'categoria_id' => null]]];

        if ($filtros['familia_id'] || $filtros['categoria_id']) {
            $categoria = $filtros['categoria_id'] ? CategoriaProducto::query()->find($filtros['categoria_id']) : null;
            $familiaId = $filtros['familia_id'] ?? $categoria?->familia_producto_id;
            $familia = $familiaId ? FamiliaProducto::query()->find($familiaId) : null;

            if ($familia) {
                $migas[] = ['texto' => $familia->nombre, 'filtro' => ['familia_id' => $familia->id, 'categoria_id' => null]];
            }

            if ($categoria) {
                $migas[] = ['texto' => $categoria->nombre, 'filtro' => ['familia_id' => $familia?->id, 'categoria_id' => $categoria->id]];
            }
        }

        return [
            'nivel' => $nivel,
            'siguiente' => $siguiente,
            'items' => $filas->values()->all(),
            'migas' => $migas,
            'hallazgo' => $this->hallazgoTipo($filas, $nivel),
        ];
    }

    /* -----------------------------------------------------------------
     * Embudo
     * -------------------------------------------------------------- */

    /**
     * Emitidas → aprobadas → pasadas a pedido → cobradas por completo. Cada
     * etapa es un subconjunto de la anterior, así que el % de paso entre dos
     * etapas es real. Sobre las cotizaciones con fecha en el periodo.
     *
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function embudo(User $usuario, PeriodoReporte $periodo, array $filtros): array
    {
        $base = fn (): Builder => $this->cotizaciones($usuario, $periodo->desde, $periodo->hasta, $filtros);
        $medir = fn (Builder $q): array => (array) $q->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto')->toBase()->first();

        $etapas = [
            ['clave' => 'emitidas', 'nombre' => 'Cotizaciones emitidas', ...$medir($base())],
            ['clave' => 'aprobadas', 'nombre' => 'Aprobadas por el cliente', ...$medir($base()->whereIn('estado', self::VENDIDOS))],
            ['clave' => 'pedido', 'nombre' => 'Pasaron a pedido', ...$medir($base()->whereHas('pedido'))],
            ['clave' => 'pagadas', 'nombre' => 'Cobradas por completo', ...$medir($base()->whereHas(
                'pedido',
                fn (Builder $p) => $p->whereRaw('(SELECT COALESCE(SUM(pago.monto), 0) FROM pago WHERE pago.pedido_id = pedido.id) >= pedido.total - 0.005'),
            ))],
        ];

        $previa = null;
        $etapas = array_map(function (array $e) use (&$previa): array {
            $e['cantidad'] = (int) $e['cantidad'];
            $e['monto'] = round((float) $e['monto'], 2);
            $e['paso'] = $previa === null ? null : ($previa > 0 ? round($e['cantidad'] / $previa * 100, 1) : null);
            $previa = $e['cantidad'];

            return $e;
        }, $etapas);

        return [
            'etapas' => $etapas,
            'hallazgo' => $this->hallazgoEmbudo($etapas),
        ];
    }

    /* -----------------------------------------------------------------
     * Rankings
     * -------------------------------------------------------------- */

    /**
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function topClientes(User $usuario, PeriodoReporte $periodo, array $filtros, float $total): array
    {
        $filas = $this->lineas($usuario, $periodo->desde, $periodo->hasta, $filtros)
            ->selectRaw('c.cliente_id AS id, '.self::AGREGADOS)
            ->groupBy('c.cliente_id')
            ->orderByDesc('ingreso')
            ->limit(self::TOP)
            ->toBase()
            ->get();

        $fichas = Cliente::query()->whereIn('id', $filas->pluck('id'))->get(['id', 'razon_social', 'logo'])->keyBy('id');

        $items = $filas->map(fn ($f): array => [
            'id' => (int) $f->id,
            'nombre' => $fichas[$f->id]->razon_social ?? 'Cliente eliminado',
            'logo_url' => $fichas[$f->id]->logo_url ?? null,
            ...$this->metricas($f, $total),
        ])->values();

        $suma = (float) $items->sum('ingreso');

        return [
            'items' => $items->all(),
            'hallazgo' => $items->isEmpty() ? 'No hubo ventas en el periodo.' : sprintf(
                '%s el %s %% de las ventas.',
                $items->count() === 1 ? 'El cliente principal por sí solo es' : "Los {$items->count()} primeros clientes juntos son",
                $this->numero($total > 0 ? $suma / $total * 100 : 0),
            ),
        ];
    }

    /**
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function topProductos(User $usuario, PeriodoReporte $periodo, array $filtros, float $total): array
    {
        $filas = $this->lineas($usuario, $periodo->desde, $periodo->hasta, $filtros)
            ->whereNotNull('cotizacion_detalle.producto_id')
            ->selectRaw('cotizacion_detalle.producto_id AS id, SUM(cotizacion_detalle.cantidad) AS cantidad, '.self::AGREGADOS)
            ->groupBy('cotizacion_detalle.producto_id')
            ->orderByDesc('ingreso')
            ->limit(self::TOP)
            ->toBase()
            ->get();

        $fichas = Producto::query()->whereIn('id', $filas->pluck('id'))->get(['id', 'nombre', 'imagen'])->keyBy('id');

        $items = $filas->map(fn ($f): array => [
            'id' => (int) $f->id,
            'nombre' => $fichas[$f->id]->nombre ?? 'Producto eliminado',
            'imagen_url' => $fichas[$f->id]->imagen_url ?? null,
            'cantidad' => round((float) $f->cantidad, 2),
            ...$this->metricas($f, $total),
        ])->values();

        $primero = $items->first();

        return [
            'items' => $items->all(),
            'hallazgo' => $primero === null ? 'No hubo ventas de productos del catálogo en el periodo.' : sprintf(
                '%s es el producto que más ingresa: %s %% de las ventas.',
                $primero['nombre'],
                $this->numero($primero['participacion']),
            ),
        ];
    }

    /**
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function porVendedor(User $usuario, PeriodoReporte $periodo, array $filtros, float $total): array
    {
        $filas = $this->lineas($usuario, $periodo->desde, $periodo->hasta, $filtros)
            ->selectRaw('c.empleado_id AS id, '.self::AGREGADOS)
            ->groupBy('c.empleado_id')
            ->orderByDesc('ingreso')
            ->toBase()
            ->get();

        $nombres = Empleado::query()->whereIn('id', $filas->pluck('id')->filter())->get(['id', 'nombres', 'paterno'])
            ->mapWithKeys(fn (Empleado $e): array => [$e->id => trim("{$e->nombres} {$e->paterno}")]);

        return $this->ranking(
            $filas->map(fn ($f): array => ['nombre' => $f->id ? ($nombres[$f->id] ?? 'Vendedor eliminado') : 'Sin vendedor (portal del cliente)', ...$this->metricas($f, $total)]),
            $total,
            fn (array $p): string => sprintf('%s lidera con el %s %% de las ventas.', $p['nombre'], $this->numero($p['participacion'])),
        );
    }

    /**
     * @param  array<string, ?int>  $filtros
     * @return array<string, mixed>
     */
    private function porCiudad(User $usuario, PeriodoReporte $periodo, array $filtros, float $total): array
    {
        $filas = $this->lineas($usuario, $periodo->desde, $periodo->hasta, $filtros)
            ->join('cliente as cl', 'cl.id', '=', 'c.cliente_id')
            ->selectRaw('cl.ciudad AS ciudad, '.self::AGREGADOS)
            ->groupBy('cl.ciudad')
            ->orderByDesc('ingreso')
            ->toBase()
            ->get();

        return $this->ranking(
            $filas->map(fn ($f): array => ['nombre' => filled($f->ciudad) ? $f->ciudad : 'Sin ciudad registrada', ...$this->metricas($f, $total)]),
            $total,
            fn (array $p): string => sprintf('%s concentra el %s %% de las ventas.', $p['nombre'], $this->numero($p['participacion'])),
        );
    }

    /**
     * Los primeros con nombre y el resto en "Otros", para que sumen 100.
     *
     * @param  Collection<int, array<string, mixed>>  $filas  Ya ordenadas.
     * @param  callable(array<string, mixed>): string  $hallazgo
     * @return array<string, mixed>
     */
    private function ranking(Collection $filas, float $total, callable $hallazgo): array
    {
        $items = $filas->take(self::BARRAS)->values();
        $resto = $filas->skip(self::BARRAS);

        if ($resto->isNotEmpty()) {
            $ingreso = round((float) $resto->sum('ingreso'), 2);
            $items->push([
                'nombre' => "Otros ({$resto->count()})",
                'ingreso' => $ingreso,
                'margen' => null,
                'participacion' => $total > 0 ? round($ingreso / $total * 100, 1) : 0.0,
                'ventas' => (int) $resto->sum('ventas'),
            ]);
        }

        return [
            'items' => $items->all(),
            'hallazgo' => $items->isEmpty() ? 'No hubo ventas en el periodo.' : $hallazgo($items->first()),
        ];
    }

    /**
     * Ingreso, margen bruto, participación y ventas de una fila agregada.
     *
     * @return array{ingreso: float, margen: float|null, participacion: float, ventas: int}
     */
    private function metricas(object $fila, float $total): array
    {
        $ingreso = round((float) $fila->ingreso, 2);
        $medido = (float) $fila->ingreso_medido;

        return [
            'ingreso' => $ingreso,
            'margen' => $medido > 0 ? round(($medido - (float) $fila->costo) / $medido * 100, 1) : null,
            'participacion' => $total > 0 ? round($ingreso / $total * 100, 1) : 0.0,
            'ventas' => (int) $fila->ventas,
        ];
    }

    /* -----------------------------------------------------------------
     * Opciones de los filtros
     * -------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function opciones(User $usuario): array
    {
        $visibles = $usuario->sucursalesVisibles();

        return [
            'familias' => FamiliaProducto::query()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::query()->whereNotNull('familia_producto_id')->orderBy('nombre')
                ->get(['id', 'nombre', 'familia_producto_id']),
            'clientes' => Cliente::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'sucursales' => Sucursal::query()
                ->when($visibles !== null, fn (Builder $q) => $q->whereIn('id', $visibles))
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ];
    }

    /* -----------------------------------------------------------------
     * Hallazgos: el título que cuenta lo que se ve (plantillas, no IA)
     * -------------------------------------------------------------- */

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $actual
     * @param  list<float>  $anterior
     */
    private function hallazgoVentas(array $labels, array $actual, array $anterior, ?int $mejor): string
    {
        if ($mejor === null) {
            return 'No hubo ventas en el periodo.';
        }

        // Con un solo mes no hay "mejor mes": se compara con el anterior y listo.
        if (count($actual) === 1) {
            $frase = sprintf('%s cerró con %s', $labels[0], $this->bs($actual[0]));

            if ($anterior[0] > 0) {
                $frase .= sprintf(', %s frente al mes anterior', $this->conSigno(($actual[0] - $anterior[0]) / $anterior[0] * 100));
            }

            return $frase.'.';
        }

        $frase = sprintf('%s fue el mejor mes: %s', $labels[$mejor], $this->bs($actual[$mejor]));

        if ($anterior[$mejor] > 0) {
            $variacion = ($actual[$mejor] - $anterior[$mejor]) / $anterior[$mejor] * 100;
            $frase .= sprintf(', %s frente al mismo mes del periodo anterior', $this->conSigno($variacion));
        }

        $totalActual = array_sum($actual);
        $totalAnterior = array_sum($anterior);

        if ($totalAnterior > 0) {
            $frase .= sprintf(
                '. En total, un %s %% %s que el periodo anterior',
                $this->numero(abs(($totalActual - $totalAnterior) / $totalAnterior * 100)),
                $totalActual >= $totalAnterior ? 'más' : 'menos',
            );
        }

        return $frase.'.';
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float|null>  $margen
     */
    private function hallazgoMargen(array $labels, array $margen, ?float $periodo): string
    {
        $medidos = array_filter($margen, fn (?float $m): bool => $m !== null);

        if ($periodo === null || $medidos === []) {
            return 'Sin cotizaciones con hoja de costos en el periodo: no hay margen que medir.';
        }

        $mejor = array_search(max($medidos), $margen, true);
        $peor = array_search(min($medidos), $margen, true);

        if (count($medidos) < 2 || $mejor === $peor) {
            return sprintf('El margen presupuestado del periodo es %s %%.', $this->numero($periodo));
        }

        return sprintf(
            'El margen presupuestado fue %s %% en el periodo: el más alto en %s (%s %%) y el más bajo en %s (%s %%).',
            $this->numero($periodo),
            $labels[$mejor],
            $this->numero($margen[$mejor]),
            $labels[$peor],
            $this->numero($margen[$peor]),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function hallazgoTipo(Collection $filas, string $nivel): string
    {
        if ($filas->isEmpty()) {
            return 'No hubo ventas en el periodo.';
        }

        $primero = $filas->first();

        // Un solo elemento: "trae el 100 %" no dice nada; sí su margen.
        if ($filas->count() === 1) {
            $nombreNivel = ['familia' => 'un solo tipo', 'categoria' => 'una sola categoría', 'producto' => 'un solo producto'][$nivel] ?? 'un solo tipo';

            return sprintf(
                'Todo lo vendido acá es de %s: %s%s.',
                $nombreNivel,
                $primero['nombre'],
                $primero['margen'] === null ? '' : sprintf(', con %s %% de margen', $this->numero($primero['margen'])),
            );
        }

        $frase = sprintf('%s trae el %s %% de los ingresos', $primero['nombre'], $this->numero($primero['participacion']));

        $mejorMargen = $filas->filter(fn (array $f): bool => $f['margen'] !== null && $f['participacion'] >= 5)->sortByDesc('margen')->first();

        if ($mejorMargen && $mejorMargen['nombre'] !== $primero['nombre']) {
            $frase .= sprintf(', pero el mejor margen lo deja %s (%s %%)', $mejorMargen['nombre'], $this->numero($mejorMargen['margen']));
        }

        return $frase.'.';
    }

    /**
     * @param  list<array<string, mixed>>  $etapas
     */
    private function hallazgoEmbudo(array $etapas): string
    {
        if ($etapas[0]['cantidad'] === 0) {
            return 'No hubo cotizaciones en el periodo.';
        }

        $peor = null;

        foreach (array_slice($etapas, 1, null, true) as $i => $etapa) {
            if ($etapa['paso'] !== null && ($peor === null || $etapa['paso'] < $etapas[$peor]['paso'])) {
                $peor = $i;
            }
        }

        if ($peor === null) {
            return 'Ninguna cotización avanzó en el periodo.';
        }

        return sprintf(
            'La mayor pérdida está entre «%s» y «%s»: pasa el %s %%.',
            mb_strtolower($etapas[$peor - 1]['nombre']),
            mb_strtolower($etapas[$peor]['nombre']),
            $this->numero($etapas[$peor]['paso']),
        );
    }

    private function bs(float $valor): string
    {
        return 'Bs '.RedaccionPronostico::numero($valor, 0);
    }

    private function numero(float $valor): string
    {
        return RedaccionPronostico::numero($valor, 1);
    }

    private function conSigno(float $valor): string
    {
        return ($valor > 0 ? '+' : ($valor < 0 ? '-' : '')).$this->numero(abs($valor)).' %';
    }
}
