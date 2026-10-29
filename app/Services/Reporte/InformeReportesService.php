<?php

namespace App\Services\Reporte;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma los informes en PDF del reporte financiero y del de producción.
 *
 * Mismo criterio que `InformeProyeccionesService`: NO consulta nada propio.
 * Llama a los MISMOS servicios que las pantallas (con el mismo usuario, y por
 * lo tanto el mismo alcance por sucursal), de modo que el papel y la pantalla
 * no puedan decir cosas distintas. Lo que agrega es lo que un documento
 * necesita y la pantalla no: el rótulo de alcance escrito, los meses sin
 * ventas en cero, las participaciones y los "puntos clave", frases armadas
 * con plantillas a partir de esos mismos números (no IA).
 */
class InformeReportesService
{
    /**
     * Nombre legible de cada etapa (las claves son las de `Pedido::FLUJO` y
     * `PedidoSeguimiento::ETAPAS`).
     *
     * @var array<string, string>
     */
    public const ETAPAS = [
        'DISENO' => 'Diseño',
        'ELABORACION' => 'Elaboración',
        'ACABADO' => 'Acabado',
        'CONTROL_CALIDAD' => 'Control de calidad',
        'ENTREGA' => 'Entrega',
        'ENTREGADO' => 'Entregado',
    ];

    /**
     * Una cuenta por cobrar se marca como vieja pasados estos días desde el
     * pedido.
     */
    public const DIAS_DEUDA_VIEJA = 30;

    public function __construct(
        private readonly ReporteFinancieroService $financiero,
        private readonly ReporteProduccionService $produccion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function financiero(User $usuario, ?string $desde = null, ?string $hasta = null, ?int $sucursalId = null): array
    {
        $datos = $this->financiero->datos($usuario, $desde, $hasta, $sucursalId);
        $inicio = Carbon::parse($datos['rango']['desde']);
        $fin = Carbon::parse($datos['rango']['hasta']);
        $total = (float) $datos['total_vendido'];

        $ventasPorMes = $this->mesesCompletos($datos['ventas_por_mes'], $inicio, $fin);

        $sucursales = collect($datos['ventas_por_sucursal'])
            ->sortByDesc('total')
            ->map(fn (array $s): array => [
                ...$s,
                'participacion' => $total > 0 ? round($s['total'] / $total * 100, 1) : 0.0,
            ])
            ->values();

        // Un pedido sin consumo de material registrado tiene costo 0 y un
        // "margen" del 100 %: sumarlo inflaría la rentabilidad. Se lista, pero
        // fuera de los totales y rotulado (mismo criterio que el BI con las
        // cotizaciones sin hoja de costos).
        $rentabilidad = collect($datos['rentabilidad'])
            ->map(fn (array $r): array => [...$r, 'sin_consumo' => $r['costo_real'] <= 0])
            ->sortBy([['sin_consumo', 'asc'], ['margen', 'asc']])
            ->values();

        $conCosto = $rentabilidad->reject(fn (array $r): bool => $r['sin_consumo']);
        $ingresoMedido = (float) $conCosto->sum('ingreso');
        $margenMedido = (float) $conCosto->sum('margen');

        $resumenRentabilidad = [
            'pedidos' => $rentabilidad->count(),
            'con_costo' => $conCosto->count(),
            'sin_consumo' => $rentabilidad->count() - $conCosto->count(),
            'con_perdida' => $conCosto->filter(fn (array $r): bool => $r['margen'] < 0)->count(),
            'ingreso' => round($ingresoMedido, 2),
            'costo' => round((float) $conCosto->sum('costo_real'), 2),
            'margen' => round($margenMedido, 2),
            'margen_pct' => $ingresoMedido > 0 ? round($margenMedido / $ingresoMedido * 100, 1) : null,
        ];

        $perdidas = $conCosto->filter(fn (array $r): bool => $r['margen'] < 0)->sortBy('margen')->values();

        $cuentas = collect($datos['cuentas_por_cobrar']['pedidos'])->sortByDesc('dias')->values();
        $viejas = $cuentas->filter(fn (array $c): bool => $c['dias'] > self::DIAS_DEUDA_VIEJA);

        return [
            'alcance' => AlcanceReporte::rotulo($usuario, $sucursalId),
            'sucursal_id' => $sucursalId,
            'periodo' => [
                'desde' => $inicio->toDateString(),
                'hasta' => $fin->toDateString(),
                'etiqueta' => $this->etiquetaPeriodo($inicio, $fin),
            ],
            'indicadores' => [
                [
                    'rotulo' => 'Total vendido',
                    'valor' => $this->bs($total),
                    'nota' => $this->mesesConVentas($ventasPorMes),
                ],
                [
                    'rotulo' => 'Margen real',
                    'valor' => $resumenRentabilidad['margen_pct'] === null ? '—' : RedaccionPronostico::numero($resumenRentabilidad['margen_pct']).' %',
                    'nota' => $resumenRentabilidad['con_costo'] > 0 ? $this->bs($resumenRentabilidad['margen']).' en '.$this->pedidos($resumenRentabilidad['con_costo']) : 'Sin pedidos con costo real',
                    'tono' => $resumenRentabilidad['margen'] < 0 ? 'peligro' : 'exito',
                ],
                [
                    'rotulo' => 'Pedidos con pérdida',
                    'valor' => (string) $resumenRentabilidad['con_perdida'],
                    'nota' => 'de '.$this->pedidos($resumenRentabilidad['con_costo']).' medidos',
                    'tono' => $resumenRentabilidad['con_perdida'] > 0 ? 'peligro' : 'exito',
                ],
                [
                    'rotulo' => 'Por cobrar',
                    'valor' => $this->bs((float) $datos['cuentas_por_cobrar']['total']),
                    'nota' => $this->pedidos($cuentas->count()).($viejas->isNotEmpty() ? " · {$viejas->count()} de más de ".self::DIAS_DEUDA_VIEJA.' días' : ''),
                    'tono' => $viejas->isNotEmpty() ? 'aviso' : 'info',
                ],
            ],
            'puntos_clave' => $this->puntosFinancieros($total, $ventasPorMes, $sucursales, $conCosto, $resumenRentabilidad, $cuentas, $viejas),
            'ventas_por_mes' => $ventasPorMes,
            'ventas_por_sucursal' => $sucursales->all(),
            'total_vendido' => $total,
            'rentabilidad' => $rentabilidad->all(),
            'rentabilidad_resumen' => $resumenRentabilidad,
            'pedidos_con_perdida' => $perdidas->all(),
            'cuentas_por_cobrar' => [
                'total' => (float) $datos['cuentas_por_cobrar']['total'],
                'pedidos' => $cuentas->all(),
                'viejas' => $viejas->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function produccion(User $usuario, ?int $sucursalId = null): array
    {
        $datos = $this->produccion->datos($usuario, $sucursalId);
        $cumplimiento = $datos['cumplimiento'];

        $etapas = collect($datos['duracion_por_etapa'])
            ->map(fn (array $e): array => [...$e, 'nombre' => self::ETAPAS[$e['etapa']] ?? $e['etapa']])
            ->values();

        $areas = collect($datos['carga_por_area'])->sortByDesc('items_abiertos')->values();
        $activos = collect($datos['pedidos_activos_por_etapa'])
            ->map(fn (array $e): array => [...$e, 'nombre' => self::ETAPAS[$e['etapa']] ?? $e['etapa']])
            ->values();
        $totalActivos = (int) $activos->sum('pedidos');
        $atrasos = collect($datos['atrasos']);

        return [
            'alcance' => AlcanceReporte::rotulo($usuario, $sucursalId),
            'sucursal_id' => $sucursalId,
            'fecha' => today()->translatedFormat('j \d\e F \d\e Y'),
            'indicadores' => [
                [
                    'rotulo' => 'Entregas a tiempo',
                    'valor' => $cumplimiento['cumplimiento_pct'] === null ? '—' : RedaccionPronostico::numero($cumplimiento['cumplimiento_pct']).' %',
                    'nota' => "{$cumplimiento['a_tiempo']} de {$cumplimiento['entregados']} entregados",
                    'tono' => match (true) {
                        $cumplimiento['cumplimiento_pct'] === null => 'info',
                        $cumplimiento['cumplimiento_pct'] >= 90 => 'exito',
                        $cumplimiento['cumplimiento_pct'] >= 70 => 'aviso',
                        default => 'peligro',
                    },
                ],
                [
                    'rotulo' => 'Entregados tarde',
                    'valor' => (string) $cumplimiento['tarde'],
                    'nota' => $atrasos->isNotEmpty()
                        ? sprintf(
                            $atrasos->count() === 1 ? 'Atraso del pedido entregado tarde: %2$s días' : 'Atraso medio de los %1$d pedidos entregados tarde: %2$s días',
                            $atrasos->count(),
                            RedaccionPronostico::numero($atrasos->avg('dias')),
                        )
                        : 'Sin atrasos registrados',
                    'tono' => $cumplimiento['tarde'] > 0 ? 'aviso' : 'exito',
                ],
                [
                    'rotulo' => 'Pedidos en curso',
                    'valor' => (string) $totalActivos,
                    'nota' => 'Antes de la entrega',
                ],
                [
                    'rotulo' => 'Trabajos pendientes',
                    'valor' => (string) $areas->sum('items_abiertos'),
                    'nota' => 'En '.$areas->count().' '.($areas->count() === 1 ? 'área' : 'áreas'),
                ],
            ],
            'puntos_clave' => $this->puntosProduccion($cumplimiento, $atrasos, $etapas, $areas, $activos, $totalActivos),
            'cumplimiento' => $cumplimiento,
            'atrasos' => $atrasos->all(),
            'duracion_por_etapa' => $etapas->all(),
            'carga_por_area' => $areas->all(),
            'pedidos_activos_por_etapa' => $activos->all(),
            'total_activos' => $totalActivos,
        ];
    }

    /* -----------------------------------------------------------------
     * Puntos clave
     * -------------------------------------------------------------- */

    /**
     * @param  list<array{mes: string, etiqueta: string, total: float}>  $ventasPorMes
     * @param  array<string, mixed>  $rentabilidad
     * @return list<string>
     */
    private function puntosFinancieros(float $total, array $ventasPorMes, Collection $sucursales, Collection $conCosto, array $rentabilidad, Collection $cuentas, Collection $viejas): array
    {
        $puntos = [];

        if ($total <= 0) {
            $puntos[] = 'No hubo ventas aprobadas en el periodo.';
        } else {
            $conVentas = array_filter($ventasPorMes, fn (array $m): bool => $m['total'] > 0);
            $mejor = collect($conVentas)->sortByDesc('total')->first();

            if (count($conVentas) > 1) {
                $puntos[] = sprintf(
                    'El mejor mes fue %s, con %s (%s %% de lo vendido en el periodo).',
                    $mejor['etiqueta'],
                    $this->bs($mejor['total']),
                    RedaccionPronostico::numero($mejor['total'] / $total * 100),
                );
            }

            if ($sucursales->count() > 1) {
                $lider = $sucursales->first();
                $puntos[] = sprintf('%s concentra el %s %% de las ventas.', $lider['sucursal'], RedaccionPronostico::numero($lider['participacion']));
            }
        }

        if ($rentabilidad['con_costo'] > 0) {
            $puntos[] = sprintf(
                $rentabilidad['con_costo'] === 1
                    ? 'El único pedido con costo real registrado dejó un margen de %2$s %% (%3$s sobre %4$s cobrados).'
                    : 'Los %1$s con costo real registrado dejaron un margen de %2$s %% (%3$s sobre %4$s cobrados).',
                $this->pedidos($rentabilidad['con_costo']),
                RedaccionPronostico::numero((float) $rentabilidad['margen_pct']),
                $this->bs($rentabilidad['margen']),
                $this->bs($rentabilidad['ingreso']),
            );

            $peor = $conCosto->sortBy('margen')->first();

            $puntos[] = match (true) {
                $rentabilidad['con_perdida'] === 0 => 'Ningún pedido terminado dio pérdida.',
                $rentabilidad['con_perdida'] === 1 => sprintf(
                    'Un pedido costó más de lo que dejó: %s (%s) perdió %s.',
                    $peor['pedido'],
                    $peor['cliente'] ?? 'sin cliente',
                    $this->bs(abs($peor['margen'])),
                ),
                default => sprintf(
                    '%d pedidos costaron más de lo que dejaron; el peor, %s (%s), perdió %s.',
                    $rentabilidad['con_perdida'],
                    $peor['pedido'],
                    $peor['cliente'] ?? 'sin cliente',
                    $this->bs(abs($peor['margen'])),
                ),
            };
        }

        if ($rentabilidad['sin_consumo'] > 0) {
            $puntos[] = $rentabilidad['sin_consumo'] === 1
                ? 'Un pedido terminado no tiene consumo de material registrado y queda fuera del margen: hasta que producción lo cargue, su costo figura en cero.'
                : "{$rentabilidad['sin_consumo']} pedidos terminados no tienen consumo de material registrado y quedan fuera del margen: hasta que producción lo cargue, su costo figura en cero.";
        }

        $puntos[] = $cuentas->isEmpty()
            ? 'No hay saldos pendientes de cobro.'
            : sprintf(
                'Quedan %s por cobrar en %s%s.',
                $this->bs((float) $cuentas->sum('saldo')),
                $this->pedidos($cuentas->count()),
                $viejas->isNotEmpty() ? sprintf('; %d con más de %d días desde el pedido', $viejas->count(), self::DIAS_DEUDA_VIEJA) : '',
            );

        return $puntos;
    }

    /**
     * @param  array<string, mixed>  $cumplimiento
     * @return list<string>
     */
    private function puntosProduccion(array $cumplimiento, Collection $atrasos, Collection $etapas, Collection $areas, Collection $activos, int $totalActivos): array
    {
        $puntos = [];

        $puntos[] = $cumplimiento['cumplimiento_pct'] === null
            ? 'Todavía no hay pedidos entregados con fecha estimada y real para medir el cumplimiento.'
            : sprintf(
                'Se entregó a tiempo el %s %% de los pedidos: %d de %d.',
                RedaccionPronostico::numero($cumplimiento['cumplimiento_pct']),
                $cumplimiento['a_tiempo'],
                $cumplimiento['entregados'],
            );

        if ($atrasos->isNotEmpty()) {
            $mayor = $atrasos->first();
            $puntos[] = sprintf('El mayor atraso fue de %d %s, en el pedido %s.', $mayor['dias'], $mayor['dias'] === 1 ? 'día' : 'días', $mayor['pedido']);
        }

        $conDatos = $etapas->filter(fn (array $e): bool => $e['dias_promedio'] !== null);

        if ($conDatos->isNotEmpty()) {
            $lenta = $conDatos->sortByDesc('dias_promedio')->first();
            $puntos[] = sprintf(
                '%s tuvo la mayor duración promedio: %s días (%d %s).',
                $lenta['nombre'],
                RedaccionPronostico::numero($lenta['dias_promedio']),
                $lenta['muestras'],
                $lenta['muestras'] === 1 ? 'etapa finalizada analizada' : 'etapas finalizadas analizadas',
            );
        }

        $pendientes = (int) $areas->sum('items_abiertos');

        if ($pendientes > 0) {
            $conTrabajo = $areas->filter(fn (array $a): bool => $a['items_abiertos'] > 0);
            // Puede haber empate: nombrar solo a la primera diría que es la única.
            $lideres = $conTrabajo->where('items_abiertos', $conTrabajo->max('items_abiertos'))->values();
            $nombres = $lideres->pluck('area')->all();
            $ultimo = array_pop($nombres);
            $lista = $nombres === [] ? $ultimo : implode(', ', $nombres).' y '.$ultimo;

            $puntos[] = match (true) {
                $conTrabajo->count() === 1 => sprintf('%s concentra %s.', $lista, $pendientes === 1 ? 'el único trabajo pendiente' : "los {$pendientes} trabajos pendientes"),
                $lideres->count() === 1 => sprintf('%s concentra %d de los %d trabajos pendientes, distribuidos en %d áreas.', $lista, $lideres[0]['items_abiertos'], $pendientes, $conTrabajo->count()),
                default => sprintf('%s concentran %d trabajos pendientes cada una, de los %d distribuidos en %d áreas.', $lista, $lideres[0]['items_abiertos'], $pendientes, $conTrabajo->count()),
            };
        }

        if ($totalActivos === 0) {
            $puntos[] = 'No hay pedidos en curso.';
        } else {
            // "Diseño y Acabado tienen 3 cada uno; Elaboración tiene 1": las
            // etapas con la misma cantidad van juntas, de la más cargada a la menos.
            $grupos = $activos
                ->filter(fn (array $e): bool => $e['pedidos'] > 0)
                ->groupBy('pedidos')
                ->sortKeysDesc()
                ->map(function (Collection $grupo, int $cantidad): string {
                    $nombres = $grupo->pluck('nombre')->all();
                    $ultimo = array_pop($nombres);
                    $lista = $nombres === [] ? $ultimo : implode(', ', $nombres).' y '.$ultimo;

                    return $grupo->count() === 1
                        ? "{$lista} tiene {$cantidad}"
                        : "{$lista} tienen {$cantidad} cada uno";
                });

            $puntos[] = sprintf(
                '%s en curso: %s.',
                $totalActivos === 1 ? 'Hay 1 pedido' : "Hay {$totalActivos} pedidos",
                $grupos->implode('; '),
            );
        }

        return $puntos;
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /**
     * Todos los meses del periodo, con cero donde no hubo ventas: sin eso la
     * línea uniría enero con marzo como si febrero no hubiera existido.
     *
     * `nombre` es el mes escrito completo para los textos del PDF (con el año
     * solo si el periodo cruza de año) y `parcial` avisa cuando el periodo
     * corta el mes: un octubre "al día 27" no se compara con un mes entero.
     *
     * @param  list<array{mes: string, total: float}>  $ventas
     * @return list<array{mes: string, etiqueta: string, nombre: string, parcial: ?string, total: float}>
     */
    private function mesesCompletos(array $ventas, Carbon $inicio, Carbon $fin): array
    {
        $porMes = collect($ventas)->pluck('total', 'mes');
        $variosAnios = $inicio->year !== $fin->year;
        $meses = [];

        for ($mes = $inicio->copy()->startOfMonth(); $mes->lessThanOrEqualTo($fin); $mes->addMonth()) {
            $clave = $mes->format('Y-m');
            $parciales = array_filter([
                $mes->isSameMonth($inicio) && $inicio->day > 1 ? "desde el día {$inicio->day}" : null,
                $mes->isSameMonth($fin) && $fin->day < $mes->daysInMonth ? "al día {$fin->day}" : null,
            ]);

            $meses[] = [
                'mes' => $clave,
                'etiqueta' => PeriodoReporte::etiquetaMes($mes),
                'nombre' => PeriodoReporte::nombreMesCompleto($mes->month).($variosAnios ? " {$mes->year}" : ''),
                'parcial' => $parciales === [] ? null : implode(' y ', $parciales),
                'total' => (float) ($porMes[$clave] ?? 0),
            ];
        }

        return $meses;
    }

    private function etiquetaPeriodo(Carbon $inicio, Carbon $fin): string
    {
        $formato = $inicio->year === $fin->year ? 'j \d\e F' : 'j \d\e F \d\e Y';

        return $inicio->translatedFormat($formato).' al '.$fin->translatedFormat('j \d\e F \d\e Y');
    }

    private function bs(float $valor): string
    {
        return 'Bs '.RedaccionPronostico::numero($valor, 2);
    }

    /**
     * @param  list<array{total: float}>  $ventasPorMes
     */
    private function mesesConVentas(array $ventasPorMes): string
    {
        $meses = count(array_filter($ventasPorMes, fn (array $m): bool => $m['total'] > 0));

        return match ($meses) {
            0 => 'Sin ventas en el periodo',
            1 => '1 mes con ventas',
            default => "{$meses} meses con ventas",
        };
    }

    private function pedidos(int $cantidad): string
    {
        return $cantidad === 1 ? '1 pedido' : "{$cantidad} pedidos";
    }
}
