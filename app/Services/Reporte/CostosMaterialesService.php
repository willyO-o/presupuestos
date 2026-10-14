<?php

namespace App\Services\Reporte;

use App\Models\CotizacionDetalleItem;
use App\Models\HistorialPrecioMaterial;
use App\Models\PedidoDetalleMaterial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Costos y materiales: proyección del costo futuro de los insumos, consumo
 * real (producción) frente al presupuestado (hojas de costo) e impacto en
 * bolivianos de las variaciones de precio.
 *
 * Dos alcances distintos conviven acá, y la pantalla los rotula:
 *
 * - **Precios** (`historial_precio_material`) son de toda la empresa: el
 *   material no tiene sucursal. No se acotan.
 * - **Consumo** (`pedido_detalle_material`, `cotizacion_detalle_item`) cuelga
 *   de pedidos y cotizaciones, que sí son de una sucursal: se acota con
 *   `visiblePara()` igual que el resto de los reportes.
 *
 * El precio de un material entre dos compras es el de la última compra
 * ("siguió igual"): por eso las series mensuales arrastran el último precio
 * conocido, incluido el vigente ANTES de que empiece el periodo.
 */
class CostosMaterialesService
{
    /** Materiales que se ofrecen en el selector de proyección individual. */
    private const MATERIALES_PROYECTADOS = 8;

    /** Materiales en el ranking de consumo. */
    private const TOP_CONSUMO = 10;

    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario, PeriodoReporte $periodo): array
    {
        $precios = $this->preciosMensuales($periodo);
        $consumoReal = $this->consumoReal($usuario, $periodo);
        $consumoPresupuestado = $this->consumoPresupuestado($usuario, $periodo);

        $indice = $this->indice($precios, $periodo);

        return [
            'periodo' => $periodo->toArray(),
            'meses_proyeccion' => $periodo->mesesSiguientes(config('bi.meses_proyeccion')),
            'indice' => $indice,
            'materiales' => $this->proyeccionPorMaterial($precios, $consumoReal, $periodo),
            'consumo' => $this->consumo($consumoReal, $consumoPresupuestado, $periodo),
            'impacto' => $this->impacto($precios, $consumoReal),
        ];
    }

    /**
     * Índice de costo de materiales del periodo (base 100) con su proyección.
     * Lo usa también el servicio de alertas para avisar de una tendencia.
     *
     * @return array<string, mixed>
     */
    public function indiceDelPeriodo(PeriodoReporte $periodo): array
    {
        return $this->indice($this->preciosMensuales($periodo), $periodo);
    }

    /* -----------------------------------------------------------------
     * Precios
     * -------------------------------------------------------------- */

    /**
     * Precio vigente de cada material al cierre de cada mes del periodo.
     *
     * Solo materiales con al menos dos registros de precio hasta el final del
     * periodo: con uno solo no hay evolución que mirar ni recta que trazar.
     *
     * @return Collection<int, array{id: int, material: string, unidad: string|null, serie: list<float|null>, precio_inicio: float|null, precio_fin: float|null}>
     */
    private function preciosMensuales(PeriodoReporte $periodo): Collection
    {
        $finesDeMes = $periodo->meses()->keys()
            ->map(fn (string $clave) => min(
                $periodo->hasta->toDateString(),
                Carbon::createFromFormat('Y-m', $clave)->endOfMonth()->toDateString(),
            ))
            ->all();

        return HistorialPrecioMaterial::query()
            ->with('material:id,nombre,unidad_medida')
            ->where('vigente_desde', '<=', $periodo->hasta)
            ->orderBy('vigente_desde')
            ->orderBy('id')
            ->get(['id', 'material_id', 'precio_unitario', 'vigente_desde'])
            ->filter(fn ($h) => $h->material)
            ->groupBy('material_id')
            ->filter(fn (Collection $registros) => $registros->count() >= 2)
            ->map(function (Collection $registros) use ($finesDeMes, $periodo): array {
                $precioAl = function (string $fecha) use ($registros): ?float {
                    $vigente = $registros->last(fn ($h) => $h->vigente_desde->toDateString() <= $fecha);

                    return $vigente ? (float) $vigente->precio_unitario : null;
                };

                $serie = array_map($precioAl, $finesDeMes);

                // Precio al arrancar el periodo: el vigente ese día o, si el
                // material se compró por primera vez dentro del periodo, ese
                // primer precio.
                $inicio = $precioAl($periodo->desde->toDateString())
                    ?? collect($serie)->first(fn ($v) => $v !== null);

                $material = $registros->first()->material;

                return [
                    'id' => $material->id,
                    'material' => $material->nombre,
                    'unidad' => $material->unidad_medida,
                    'serie' => $serie,
                    'precio_inicio' => $inicio,
                    'precio_fin' => collect($serie)->last(fn ($v) => $v !== null),
                ];
            })
            ->values();
    }

    /**
     * Índice de costo de materiales: para cada mes, el promedio de
     * (precio del mes ÷ primer precio del material en el periodo) × 100.
     *
     * Es un índice de precios simple (todos los materiales pesan igual):
     * responde "¿cuánto más caro está comprar lo mismo que al inicio?". 100 =
     * igual que al inicio; 112 = 12 % más caro.
     *
     * @param  Collection<int, array<string, mixed>>  $precios
     * @return array<string, mixed>
     */
    private function indice(Collection $precios, PeriodoReporte $periodo): array
    {
        $etiquetas = $periodo->meses()->values()->all();

        $serie = array_map(function (int $i) use ($precios): ?float {
            $relativos = $precios
                ->map(function (array $m) use ($i): ?float {
                    $base = collect($m['serie'])->first(fn ($v) => $v !== null);
                    $actual = $m['serie'][$i];

                    return ($base && $actual !== null) ? $actual / $base * 100 : null;
                })
                ->filter(fn ($v) => $v !== null);

            return $relativos->isEmpty() ? null : round($relativos->avg(), 2);
        }, array_keys($etiquetas));

        $conDato = array_values(array_filter($serie, fn ($v) => $v !== null));
        // Un índice de precios es una foto al cierre de cada mes, no un total:
        // el mes en curso vale entero (`sumaDelMes: false`).
        $analisis = $periodo->pronostico($serie, sumaDelMes: false);

        return [
            'labels' => $etiquetas,
            'serie' => $serie,
            'proyeccion' => $this->redondearProyeccion($analisis['proyeccion']),
            'variacion_mensual' => $this->redondear($analisis['variacion_mensual']),
            'metodo' => $analisis['metodo'],
            'variacion_periodo' => count($conDato) >= 2
                ? round(end($conDato) / $conDato[0] * 100 - 100, 2)
                : null,
            'materiales' => $precios->count(),
        ];
    }

    /**
     * Serie y proyección de precio de los materiales más relevantes del
     * periodo (primero los más consumidos; luego los de más cambios de
     * precio), para el selector de la pantalla.
     *
     * @param  Collection<int, array<string, mixed>>  $precios
     * @param  Collection<int, PedidoDetalleMaterial>  $consumoReal
     * @return list<array<string, mixed>>
     */
    private function proyeccionPorMaterial(Collection $precios, Collection $consumoReal, PeriodoReporte $periodo): array
    {
        $costoConsumido = $consumoReal->groupBy('material_id')->map(fn ($g) => (float) $g->sum('costo_real'));

        return $precios
            ->sortByDesc(fn (array $m) => [
                $costoConsumido->get($m['id'], 0),
                count(array_unique(array_filter($m['serie'], fn ($v) => $v !== null))),
            ])
            ->take(self::MATERIALES_PROYECTADOS)
            ->map(function (array $m) use ($periodo): array {
                $analisis = $periodo->pronostico($m['serie'], sumaDelMes: false);

                return [
                    'id' => $m['id'],
                    'material' => $m['material'],
                    'unidad' => $m['unidad'],
                    'serie' => $m['serie'],
                    'precio_actual' => $m['precio_fin'],
                    'proyeccion' => $this->redondearProyeccion($analisis['proyeccion']),
                    'variacion_mensual' => $this->redondear($analisis['variacion_mensual']),
                    'metodo' => $analisis['metodo'],
                ];
            })
            ->values()
            ->all();
    }

    /* -----------------------------------------------------------------
     * Consumo
     * -------------------------------------------------------------- */

    /**
     * Material realmente consumido por producción en el periodo (lo que el
     * taller registra en cada ítem del pedido).
     *
     * @return Collection<int, PedidoDetalleMaterial>
     */
    private function consumoReal(User $usuario, PeriodoReporte $periodo): Collection
    {
        return PedidoDetalleMaterial::query()
            ->whereHas('pedidoDetalle.pedido', fn ($q) => $q->visiblePara($usuario))
            ->whereBetween('created_at', [$periodo->desde, $periodo->hasta])
            ->with('material:id,nombre,unidad_medida')
            ->get(['id', 'pedido_detalle_id', 'material_id', 'cantidad_usada', 'costo_real', 'created_at']);
    }

    /**
     * Material que las hojas de costo de los presupuestos aprobados en el
     * periodo DIJERON que se iba a usar: cantidad por unidad × unidades de la
     * línea.
     *
     * Solo existe para presupuestos hechos con la hoja de costos del motor de
     * margen (insumos con `material_id`); los anteriores no dejan rastro.
     *
     * @return Collection<int, array{material_id: int, material: string, cantidad: float, costo: float}>
     */
    private function consumoPresupuestado(User $usuario, PeriodoReporte $periodo): Collection
    {
        return CotizacionDetalleItem::query()
            ->whereNotNull('material_id')
            ->whereHas('cotizacionDetalle.cotizacion', fn ($q) => $q
                ->visiblePara($usuario)
                ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
                ->whereBetween('fecha', [$periodo->desde, $periodo->hasta]))
            ->with('material:id,nombre', 'cotizacionDetalle:id,cantidad')
            ->get(['id', 'cotizacion_detalle_id', 'material_id', 'cantidad', 'subtotal'])
            ->filter(fn ($item) => $item->material)
            ->map(function ($item): array {
                $unidades = (float) ($item->cotizacionDetalle?->cantidad ?? 1);

                return [
                    'material_id' => $item->material_id,
                    'material' => $item->material->nombre,
                    'cantidad' => (float) $item->cantidad * $unidades,
                    'costo' => (float) $item->subtotal * $unidades,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, PedidoDetalleMaterial>  $real
     * @param  Collection<int, array<string, mixed>>  $presupuestado
     * @return array<string, mixed>
     */
    private function consumo(Collection $real, Collection $presupuestado, PeriodoReporte $periodo): array
    {
        $realPorMaterial = $real->filter(fn ($c) => $c->material)->groupBy('material_id');
        $presupuestadoPorMaterial = $presupuestado->groupBy('material_id');

        $porMaterial = $realPorMaterial->keys()
            ->merge($presupuestadoPorMaterial->keys())
            ->unique()
            ->map(function (int $id) use ($realPorMaterial, $presupuestadoPorMaterial): array {
                $reales = $realPorMaterial->get($id, collect());
                $estimados = $presupuestadoPorMaterial->get($id, collect());

                return [
                    'material' => $reales->first()?->material->nombre ?? $estimados->first()['material'],
                    'unidad' => $reales->first()?->material->unidad_medida,
                    'cantidad' => round((float) $reales->sum('cantidad_usada'), 2),
                    'costo_real' => round((float) $reales->sum('costo_real'), 2),
                    'costo_presupuestado' => round((float) $estimados->sum('costo'), 2),
                ];
            })
            ->sortByDesc(fn (array $m) => max($m['costo_real'], $m['costo_presupuestado']))
            ->take(self::TOP_CONSUMO)
            ->values();

        $porMes = $real->groupBy(fn ($c) => $c->created_at->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('costo_real'));

        $serie = $periodo->meses()->keys()
            ->map(fn (string $clave) => round($porMes->get($clave, 0), 2))
            ->all();

        $analisis = $periodo->pronostico($serie);

        return [
            'por_material' => $porMaterial->all(),
            'labels' => $periodo->meses()->values()->all(),
            'serie' => $serie,
            'media_movil' => array_map(fn ($v) => $this->redondear($v), Pronostico::mediaMovil($serie)),
            'proyeccion' => $this->redondearProyeccion($analisis['proyeccion']),
            'variacion_mensual' => $this->redondear($analisis['variacion_mensual']),
            'metodo' => $analisis['metodo'],
            'total' => round(array_sum($serie), 2),
            'materiales_distintos' => $realPorMaterial->count(),
            'tiene_presupuestado' => $presupuestado->isNotEmpty(),
        ];
    }

    /* -----------------------------------------------------------------
     * Impacto
     * -------------------------------------------------------------- */

    /**
     * Cuánto costó, en bolivianos, que cada material cambiara de precio
     * dentro del periodo: (precio al final − precio al inicio) × cantidad
     * consumida en el periodo.
     *
     * Es una cota sencilla y explicable del impacto: supone que todo el
     * consumo se pagó al precio final. Un material que cambió de precio pero
     * no se consumió tiene impacto 0 hoy, aunque sigue mostrando su variación
     * (va a pesar en el próximo pedido que lo use).
     *
     * @param  Collection<int, array<string, mixed>>  $precios
     * @param  Collection<int, PedidoDetalleMaterial>  $consumoReal
     * @return array<string, mixed>
     */
    private function impacto(Collection $precios, Collection $consumoReal): array
    {
        $cantidadConsumida = $consumoReal->groupBy('material_id')->map(fn ($g) => (float) $g->sum('cantidad_usada'));

        $filas = $precios
            ->filter(fn (array $m) => $m['precio_inicio'] && $m['precio_fin'] !== null && $m['precio_fin'] != $m['precio_inicio'])
            ->map(function (array $m) use ($cantidadConsumida): array {
                $cantidad = $cantidadConsumida->get($m['id'], 0.0);
                $diferencia = $m['precio_fin'] - $m['precio_inicio'];

                return [
                    'material' => $m['material'],
                    'unidad' => $m['unidad'],
                    'precio_inicio' => round($m['precio_inicio'], 2),
                    'precio_fin' => round($m['precio_fin'], 2),
                    'variacion_pct' => round($diferencia / $m['precio_inicio'] * 100, 2),
                    'cantidad_consumida' => round($cantidad, 2),
                    'impacto' => round($diferencia * $cantidad, 2),
                ];
            })
            ->sortByDesc(fn (array $f) => [abs($f['impacto']), abs($f['variacion_pct'])])
            ->values();

        return [
            'materiales' => $filas->all(),
            'total' => round((float) $filas->sum('impacto'), 2),
            'suben' => $filas->where('variacion_pct', '>', 0)->count(),
            'bajan' => $filas->where('variacion_pct', '<', 0)->count(),
        ];
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /**
     * @param  list<array{valor: float, inferior: float, superior: float}>  $proyeccion
     * @return list<array{valor: float, inferior: float, superior: float}>
     */
    private function redondearProyeccion(array $proyeccion): array
    {
        return array_map(fn (array $p): array => array_map(fn (float $v) => round($v, 2), $p), $proyeccion);
    }

    private function redondear(?float $valor): ?float
    {
        return $valor === null ? null : round($valor, 2);
    }
}
