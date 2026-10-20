<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Preguntas para decidir: el BI contestado en castellano. Once preguntas de
 * costos, ganancias, escenarios y cobranza, cada una con su respuesta en una
 * frase (con la cifra), una tabla corta, qué hacer y con qué datos se
 * respondió. La última junta las acciones de las demás, ordenadas por el
 * dinero que mueven.
 *
 * Todo es cálculo + plantilla: ninguna cifra sale de la IA. Respeta el periodo
 * y las sucursales del usuario como el resto del BI, y agrega en SQL (como el
 * Panorama) salvo donde reutiliza `CostosMaterialesService`.
 *
 * Definiciones que se repiten:
 * - **Venta** = cotización APROBADA o CONVERTIDA.
 * - **Ganancia bruta** = ingreso neto (sin IVA, con el descuento prorrateado)
 *   − costo ajustado de la hoja de costos, antes de impuestos; **margen** =
 *   ganancia ÷ ingreso. Las líneas sin hoja (costo 0, anteriores al motor de
 *   margen) no entran: su margen sería 100 %.
 * - **Costo real** = material registrado como consumo en el pedido. La mano
 *   de obra y los demás insumos son los presupuestados: no se registran
 *   horas reales trabajadas.
 */
class PreguntasNegocioService
{
    private const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    private const TOP = 5;

    private const SIN_PRODUCTO = 'Trabajos a medida (sin catálogo)';

    /** Grupos, en el orden en que salen. */
    public const GRUPOS = [
        'costos' => 'Costos',
        'ganancias' => 'Ganancias',
        'escenarios' => 'Escenarios',
        'decisiones' => 'Decisiones',
    ];

    private const UNIDADES = ['M2' => 'm²', 'METRO_LINEAL' => 'm', 'UNIDAD' => 'unidad'];

    public function __construct(private readonly CostosMaterialesService $costos) {}

    /**
     * @return array{periodo: array<string, mixed>, grupos: array<string, string>, preguntas: list<array<string, mixed>>}
     */
    public function datos(User $usuario, PeriodoReporte $periodo): array
    {
        $costos = $this->costos->datos($usuario, $periodo);
        $trabajos = $this->trabajosComparables($usuario, $periodo);
        $lineas = $this->lineasVendidasPorProducto($usuario, $periodo);

        $preguntas = [
            $this->costoPorProducto($trabajos, $this->trabajosEntregados($usuario, $periodo)->count()),
            $this->costosQueSuben($costos, $usuario, $periodo),
            $this->gastoContraPresupuesto($trabajos, $usuario, $periodo),
            $this->materialesQuePesan($costos),
            $this->productosRentables($lineas),
            $this->clientesRentables($usuario, $periodo),
            $this->descuentos($usuario, $periodo),
            $this->margenBajo($usuario, $periodo),
            $this->materiaPrima($lineas, $costos),
            $this->cobranza($usuario, $periodo),
        ];

        $preguntas[] = $this->decisiones($preguntas);

        return [
            'periodo' => $periodo->toArray(),
            'grupos' => self::GRUPOS,
            'preguntas' => $preguntas,
        ];
    }

    /* -----------------------------------------------------------------
     * Costos
     * -------------------------------------------------------------- */

    /**
     * 1. Material real + mano de obra y otros insumos presupuestados, por
     * unidad de venta (m², metro o unidad), de los trabajos entregados.
     *
     * @param  Collection<int, object>  $trabajos
     * @return array<string, mixed>
     */
    private function costoPorProducto(Collection $trabajos, int $entregados): array
    {
        $porProducto = $trabajos
            ->filter(fn (object $t): bool => $t->producto_id !== null && $this->unidades($t) > 0)
            ->groupBy('producto_id')
            ->map(function (Collection $grupo): array {
                $unidades = $grupo->sum(fn (object $t): float => $this->unidades($t));
                $real = (float) $grupo->sum('costo_real');
                $presupuestado = (float) $grupo->sum(fn (object $t): float => $t->hoja_material * $t->cantidad_cotizada);
                $otros = (float) $grupo->sum(fn (object $t): float => $t->hoja_otros * $t->cantidad_cotizada);

                return [
                    'nombre' => $grupo->first()->producto,
                    'unidad' => self::UNIDADES[$grupo->first()->unidad_medida] ?? 'unidad',
                    'trabajos' => $grupo->count(),
                    'material_real' => round($real / $unidades, 2),
                    'material_presupuestado' => round($presupuestado / $unidades, 2),
                    'otros' => round($otros / $unidades, 2),
                    'total' => round(($real + $otros) / $unidades, 2),
                    'desvio' => $presupuestado > 0 ? round(($real - $presupuestado) / $presupuestado * 100, 1) : null,
                ];
            })
            // Los que más se producen primero; a igual cantidad, el más caro.
            ->sortByDesc(fn (array $p): array => [$p['trabajos'], $p['total']])
            ->values();

        $base = sprintf(
            'Con %s trabajos entregados que tienen consumo registrado y hoja de costos (%s de los %s entregados en el periodo).',
            $this->numero($trabajos->count(), 0),
            $this->pct($entregados > 0 ? $trabajos->count() / $entregados * 100 : 0),
            $this->numero($entregados, 0),
        );
        $aviso = 'La mano de obra es la presupuestada en la hoja de costos: no se registran horas reales trabajadas. No incluye el recargo por complejidad del tipo de proyecto.';

        if ($porProducto->isEmpty()) {
            return $this->pregunta('costo_producto', 'costos', 'No hay trabajos entregados con consumo de material registrado en el periodo.', base: $base, aviso: $aviso);
        }

        $primero = $porProducto->first();
        $respuesta = sprintf(
            'Producir 1 %s de %s nos cuesta %s: %s de material real y %s de mano de obra y otros insumos.',
            $primero['unidad'],
            $primero['nombre'],
            $this->bs($primero['total'], 2),
            $this->bs($primero['material_real'], 2),
            $this->bs($primero['otros'], 2),
        );

        if ($primero['desvio'] !== null && abs($primero['desvio']) >= 1) {
            $respuesta .= sprintf(' El material salió %s frente a lo presupuestado.', RedaccionPronostico::variacion($primero['desvio']));
        }

        return $this->pregunta(
            'costo_producto',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Producto', 'formato' => 'texto'],
                ['titulo' => 'Trabajos', 'formato' => 'cantidad'],
                ['titulo' => 'Por', 'formato' => 'texto'],
                // Cortos: son 7 columnas y en el PDF no se parten.
                ['titulo' => 'Mat. real', 'formato' => 'bs_exacto'],
                ['titulo' => 'Mat. presup.', 'formato' => 'bs_exacto'],
                ['titulo' => 'Obra y otros', 'formato' => 'bs_exacto'],
                ['titulo' => 'Total', 'formato' => 'bs_exacto'],
            ],
            filas: $porProducto->take(self::TOP)->map(fn (array $p): array => [
                $p['nombre'], $p['trabajos'], $p['unidad'], $p['material_real'], $p['material_presupuestado'], $p['otros'], $p['total'],
            ])->all(),
            acciones: [
                ['texto' => sprintf('Usar el costo real como piso al cotizar: cobrar menos de %s por %s de %s es vender a pérdida.', $this->bs($primero['total'], 2), $primero['unidad'], $primero['nombre']), 'impacto' => null],
            ],
            base: $base,
            aviso: $aviso,
        );
    }

    /**
     * 2. Materiales que subieron (variación y lo que costó la subida según lo
     * consumido), el índice de costo y la tarifa de mano de obra presupuestada.
     *
     * @param  array<string, mixed>  $costos
     * @return array<string, mixed>
     */
    private function costosQueSuben(array $costos, User $usuario, PeriodoReporte $periodo): array
    {
        $impacto = $costos['impacto'];
        $suben = collect($impacto['materiales'])
            ->filter(fn (array $m): bool => $m['variacion_pct'] > 0)
            ->sortByDesc(fn (array $m): array => [$m['impacto'], $m['variacion_pct']])
            ->values();
        $manoDeObra = $this->tarifaManoDeObra($usuario, $periodo);
        $base = sprintf('Precios de %s materiales con historial en el periodo; el impacto es la subida × lo que se consumió.', $this->numero($costos['indice']['materiales'] ?? count($impacto['materiales']), 0));

        if ($suben->isEmpty() && $manoDeObra === null) {
            return $this->pregunta('costos_suben', 'costos', sprintf(
                'Ningún material subió de precio en el periodo%s.',
                $impacto['bajan'] > 0 ? " y {$impacto['bajan']} bajaron" : '',
            ), base: $base);
        }

        $frases = [];
        $acciones = [];

        if ($suben->isNotEmpty()) {
            $primero = $suben->first();
            $frases[] = sprintf(
                '%s %s de precio%s. El que más nos costó es %s (%s): %s más por lo que se consumió; en total, %s.',
                $this->numero($impacto['suben'], 0),
                $impacto['suben'] === 1 ? 'material subió' : 'materiales subieron',
                $impacto['bajan'] > 0 ? " y {$impacto['bajan']} bajaron" : ' y ninguno bajó',
                $primero['material'],
                RedaccionPronostico::variacion($primero['variacion_pct']),
                $this->bs($primero['impacto']),
                $this->bs((float) $suben->sum('impacto')),
            );
            $acciones[] = [
                'texto' => sprintf('Negociar el precio de %s o buscar otro proveedor: subió %s y nos costó %s más.', $primero['material'], $this->pct($primero['variacion_pct']), $this->bs($primero['impacto'])),
                'impacto' => $primero['impacto'] > 0 ? (float) $primero['impacto'] : null,
            ];
        }

        $mensual = $costos['indice']['variacion_mensual'] ?? null;
        if ($mensual !== null && abs($mensual) >= 0.1) {
            $frases[] = sprintf('El índice de costo de materiales %s %s por mes.', $mensual > 0 ? 'sube' : 'baja', $this->pct(abs($mensual)));
        }

        if ($manoDeObra !== null && abs($manoDeObra['variacion']) >= 0.5) {
            $frases[] = sprintf(
                'La hora de mano de obra presupuestada pasó de %s a %s (%s).',
                $this->bs($manoDeObra['antes'], 2),
                $this->bs($manoDeObra['ahora'], 2),
                RedaccionPronostico::variacion($manoDeObra['variacion']),
            );
        }

        if ($suben->count() > 1) {
            $acciones[] = ['texto' => 'Revisar los precios de venta de los productos que más usan esos materiales (ver "¿Qué pasaría si sube el precio de la materia prima?").', 'impacto' => null];
        }

        return $this->pregunta(
            'costos_suben',
            'costos',
            implode(' ', $frases) ?: 'Ningún material subió de precio en el periodo.',
            columnas: [
                ['titulo' => 'Material', 'formato' => 'texto'],
                ['titulo' => 'Precio antes', 'formato' => 'bs_exacto'],
                ['titulo' => 'Precio ahora', 'formato' => 'bs_exacto'],
                ['titulo' => 'Variación', 'formato' => 'variacion'],
                ['titulo' => 'Nos costó de más', 'formato' => 'bs'],
            ],
            filas: $suben->take(self::TOP)->map(fn (array $m): array => [
                $m['material'], $m['precio_inicio'], $m['precio_fin'], $m['variacion_pct'], $m['impacto'],
            ])->all(),
            acciones: $acciones,
            base: $base,
        );
    }

    /**
     * Tarifa por hora de mano de obra de las hojas de costos: primera mitad
     * del periodo contra la segunda (promedio ponderado por horas).
     *
     * @return array{antes: float, ahora: float, variacion: float}|null
     */
    private function tarifaManoDeObra(User $usuario, PeriodoReporte $periodo): ?array
    {
        $mes = ConsultaSql::mes('c.fecha');

        $porMes = CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->join('cotizacion as c', 'c.id', '=', 'cotizacion_detalle.cotizacion_id')
            ->join('cotizacion_detalle_item as i', 'i.cotizacion_detalle_id', '=', 'cotizacion_detalle.id')
            ->where('i.tipo', 'MANO_OBRA')
            ->where('i.cantidad', '>', 0)
            ->whereBetween('c.fecha', [$periodo->desde, $periodo->hasta])
            ->groupByRaw($mes)
            ->orderByRaw($mes)
            ->selectRaw("{$mes} AS mes, SUM(i.subtotal) AS costo, SUM(i.cantidad) AS horas")
            ->toBase()
            ->get();

        if ($porMes->count() < 2) {
            return null;
        }

        [$primera, $segunda] = $porMes->chunk((int) ceil($porMes->count() / 2))->values()->all() + [1 => collect()];
        $tarifa = fn (Collection $meses): float => (float) $meses->sum('horas') > 0 ? (float) $meses->sum('costo') / (float) $meses->sum('horas') : 0.0;

        $antes = $tarifa($primera);
        $ahora = $tarifa($segunda);

        if ($antes <= 0 || $ahora <= 0) {
            return null;
        }

        return ['antes' => round($antes, 2), 'ahora' => round($ahora, 2), 'variacion' => round(($ahora - $antes) / $antes * 100, 1)];
    }

    /**
     * 3. Material real contra el de la hoja de costos, en los MISMOS trabajos
     * (entregados, con consumo registrado y con hoja). Comparar totales del
     * periodo mezclaría trabajos distintos.
     *
     * @param  Collection<int, object>  $trabajos
     * @return array<string, mixed>
     */
    private function gastoContraPresupuesto(Collection $trabajos, User $usuario, PeriodoReporte $periodo): array
    {
        $tolerancia = (float) config('bi.preguntas.tolerancia_desvio');
        $base = sprintf(
            'Con %s trabajos entregados que tienen consumo registrado y hoja de costos. Un trabajo "se pasó" si gastó más de %s sobre lo presupuestado.',
            $this->numero($trabajos->count(), 0),
            $this->pct($tolerancia * 100, 0),
        );

        if ($trabajos->isEmpty()) {
            return $this->pregunta('gasto_presupuesto', 'costos', 'No hay trabajos entregados con consumo y hoja de costos para comparar en el periodo.', base: $base);
        }

        $presupuestado = fn (object $t): float => $t->hoja_material * $t->cantidad_cotizada;
        $totalPresupuestado = (float) $trabajos->sum($presupuestado);
        $totalReal = (float) $trabajos->sum('costo_real');
        $pasados = $trabajos->filter(fn (object $t): bool => (float) $t->costo_real > $presupuestado($t) * (1 + $tolerancia))->count();
        $desvio = $totalPresupuestado > 0 ? ($totalReal - $totalPresupuestado) / $totalPresupuestado * 100 : 0.0;

        $porProducto = $trabajos
            ->groupBy(fn (object $t): string => (string) ($t->producto_id ?? 0))
            ->map(function (Collection $grupo) use ($presupuestado, $tolerancia): array {
                $pres = (float) $grupo->sum($presupuestado);
                $real = (float) $grupo->sum('costo_real');

                return [
                    'nombre' => $grupo->first()->producto ?? self::SIN_PRODUCTO,
                    'trabajos' => $grupo->count(),
                    'pasados' => $grupo->filter(fn (object $t): bool => (float) $t->costo_real > $presupuestado($t) * (1 + $tolerancia))->count(),
                    'presupuestado' => round($pres, 2),
                    'real' => round($real, 2),
                    'diferencia' => round($real - $pres, 2),
                    'desvio' => $pres > 0 ? round(($real - $pres) / $pres * 100, 1) : null,
                ];
            })
            ->sortByDesc('diferencia')
            ->values();

        $respuesta = sprintf(
            '%s. En los trabajos comparables gastamos %s en material contra %s presupuestados (%s); %s de %s trabajos se pasaron.',
            $desvio > $tolerancia * 100 ? 'Sí' : ($desvio < -$tolerancia * 100 ? 'No, gastamos menos' : 'En general no'),
            $this->bs($totalReal),
            $this->bs($totalPresupuestado),
            RedaccionPronostico::variacion($desvio),
            $this->numero($pasados, 0),
            $this->numero($trabajos->count(), 0),
        );

        $acciones = [];
        $peorProducto = $porProducto->first(fn (array $p): bool => $p['diferencia'] > 0 && ($p['desvio'] ?? 0) > $tolerancia * 100);
        if ($peorProducto) {
            $acciones[] = [
                'texto' => sprintf('Revisar la hoja de costos de %s: gasta %s más de material de lo presupuestado (%s de más).', $peorProducto['nombre'], $this->pct($peorProducto['desvio']), $this->bs($peorProducto['diferencia'])),
                'impacto' => $peorProducto['diferencia'],
            ];
        }

        $peorMaterial = $this->materialMasDesviado($usuario, $periodo, $tolerancia);
        if ($peorMaterial) {
            $respuesta .= sprintf(' El material que más se pasa es %s (%s).', $peorMaterial['nombre'], RedaccionPronostico::variacion($peorMaterial['desvio']));
            $acciones[] = [
                'texto' => sprintf('Controlar cortes y sobrantes de %s en el taller, o ajustar su cantidad en los materiales del producto: se usa %s más de lo previsto.', $peorMaterial['nombre'], $this->pct($peorMaterial['desvio'])),
                'impacto' => $peorMaterial['diferencia'],
            ];
        }

        return $this->pregunta(
            'gasto_presupuesto',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Producto', 'formato' => 'texto'],
                ['titulo' => 'Trabajos', 'formato' => 'cantidad'],
                ['titulo' => 'Se pasaron', 'formato' => 'cantidad'],
                ['titulo' => 'Presupuestado', 'formato' => 'bs'],
                ['titulo' => 'Real', 'formato' => 'bs'],
                ['titulo' => 'Diferencia', 'formato' => 'bs'],
                ['titulo' => 'Desvío', 'formato' => 'variacion'],
            ],
            filas: $porProducto->take(self::TOP)->map(fn (array $p): array => [
                $p['nombre'], $p['trabajos'], $p['pasados'], $p['presupuestado'], $p['real'], $p['diferencia'], $p['desvio'],
            ])->all(),
            acciones: $acciones,
            base: $base,
            aviso: 'Solo se compara el material: es lo único que se registra como consumo real.',
        );
    }

    /**
     * Material con más gasto de más, en Bs, sobre lo presupuestado en esos
     * mismos trabajos (si pasa la tolerancia).
     *
     * @return array{nombre: string, diferencia: float, desvio: float}|null
     */
    private function materialMasDesviado(User $usuario, PeriodoReporte $periodo, float $tolerancia): ?array
    {
        $trabajos = $this->comparables($usuario, $periodo)->select('pedido_detalle.id', 'pedido_detalle.cotizacion_detalle_id');

        $real = DB::table('pedido_detalle_material as m')
            ->joinSub($trabajos, 't', 't.id', '=', 'm.pedido_detalle_id')
            ->join('material as mat', 'mat.id', '=', 'm.material_id')
            ->groupBy('m.material_id', 'mat.nombre')
            ->selectRaw('m.material_id AS id, mat.nombre AS nombre, SUM(m.costo_real) AS costo')
            ->get()
            ->keyBy('id');

        $presupuestado = DB::table('cotizacion_detalle_item as i')
            ->joinSub($trabajos, 't', 't.cotizacion_detalle_id', '=', 'i.cotizacion_detalle_id')
            ->join('cotizacion_detalle as cd', 'cd.id', '=', 'i.cotizacion_detalle_id')
            ->where('i.tipo', 'MATERIAL')
            ->whereNotNull('i.material_id')
            ->groupBy('i.material_id')
            ->selectRaw('i.material_id AS id, SUM(i.subtotal * cd.cantidad) AS costo')
            ->get()
            ->keyBy('id');

        $peor = $real
            ->filter(fn (object $m): bool => (float) ($presupuestado[$m->id]->costo ?? 0) > 0)
            ->map(fn (object $m): array => [
                'nombre' => $m->nombre,
                'diferencia' => round((float) $m->costo - (float) $presupuestado[$m->id]->costo, 2),
                'desvio' => round(((float) $m->costo / (float) $presupuestado[$m->id]->costo - 1) * 100, 1),
            ])
            ->filter(fn (array $m): bool => $m['diferencia'] > 0 && $m['desvio'] > $tolerancia * 100)
            ->sortByDesc('diferencia')
            ->first();

        return $peor;
    }

    /**
     * 4. Los pocos materiales que se llevan la mayor parte del gasto real.
     *
     * @param  array<string, mixed>  $costos
     * @return array<string, mixed>
     */
    private function materialesQuePesan(array $costos): array
    {
        $consumo = $costos['consumo'];
        $total = (float) $consumo['total'];
        $corte = (float) config('bi.preguntas.pareto');
        $base = sprintf('Material registrado como consumo en los pedidos del periodo: %s en %s materiales.', $this->bs($total), $this->numero($consumo['materiales_distintos'], 0));

        if ($total <= 0) {
            return $this->pregunta('materiales_pesan', 'costos', 'No hay consumo de material registrado en el periodo.', base: $base);
        }

        $acumulado = 0.0;
        $filas = collect($consumo['por_material'])
            ->filter(fn (array $m): bool => $m['costo_real'] > 0)
            ->sortByDesc('costo_real')
            ->values()
            ->map(function (array $m) use (&$acumulado, $total): array {
                $acumulado += $m['costo_real'];

                return ['nombre' => $m['material'], 'costo' => $m['costo_real'], 'parte' => round($m['costo_real'] / $total * 100, 1), 'acumulado' => round($acumulado / $total * 100, 1)];
            });

        // Hasta el material que cruza el corte, incluido.
        $cuantos = $filas->search(fn (array $m): bool => $m['acumulado'] >= $corte * 100);
        $principales = $filas->take($cuantos === false ? $filas->count() : $cuantos + 1);
        $parte = (float) $principales->last()['acumulado'];
        $ahorro = (float) config('bi.preguntas.ahorro_negociable');
        $nombres = $principales->take(3)->pluck('nombre')->all();

        $respuesta = sprintf(
            '%s de %s materiales son el %s del gasto en material: %s.',
            $this->numero($principales->count(), 0),
            $this->numero($consumo['materiales_distintos'], 0),
            $this->pct($parte),
            $this->enumerar($nombres),
        );

        return $this->pregunta(
            'materiales_pesan',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Material', 'formato' => 'texto'],
                ['titulo' => 'Gasto real', 'formato' => 'bs'],
                ['titulo' => 'Parte del gasto', 'formato' => 'porcentaje'],
                ['titulo' => 'Acumulado', 'formato' => 'porcentaje'],
            ],
            filas: $principales->take(8)->map(fn (array $m): array => [$m['nombre'], $m['costo'], $m['parte'], $m['acumulado']])->all(),
            acciones: [[
                'texto' => sprintf(
                    'Concentrar la negociación con proveedores en %s: una rebaja de %s en ellos ahorra %s en un periodo como este.',
                    $this->enumerar($nombres),
                    $this->pct($ahorro * 100, 0),
                    $this->bs($principales->take(3)->sum('costo') * $ahorro),
                ),
                'impacto' => round($principales->take(3)->sum('costo') * $ahorro, 2),
            ]],
            base: $base,
        );
    }

    /* -----------------------------------------------------------------
     * Ganancias
     * -------------------------------------------------------------- */

    /**
     * 5. Dos rankings: más ganancia en Bs (lo que aporta al negocio) y mejor
     * margen (lo que deja cada trabajo).
     *
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private function productosRentables(Collection $lineas): array
    {
        $base = 'Ventas del periodo con hoja de costos. Ganancia bruta = ingreso sin IVA − costo de la hoja, antes de impuestos (el mismo cálculo del Panorama).';

        if ($lineas->isEmpty()) {
            return $this->pregunta('productos_rentables', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: $base);
        }

        $minimo = (int) config('bi.preguntas.minimo_ventas');
        $porGanancia = $lineas->sortByDesc('ganancia')->values();
        $primero = $porGanancia->first();
        $mejorMargen = $lineas->where('ventas', '>=', $minimo)->sortByDesc('margen')->first();
        $margenTotal = $this->margen((float) $lineas->sum('ingreso'), (float) $lineas->sum('costo'));

        $respuesta = sprintf('%s es el que más ganancia deja: %s (margen %s).', $primero['nombre'], $this->bs($primero['ganancia']), $this->pct($primero['margen']));

        if ($mejorMargen && $mejorMargen['nombre'] !== $primero['nombre']) {
            $respuesta .= sprintf(' El de mejor margen es %s (%s), pero aporta menos porque se vende menos.', $mejorMargen['nombre'], $this->pct($mejorMargen['margen']));
        } elseif ($mejorMargen) {
            $respuesta .= ' Además es el de mejor margen.';
        }

        $acciones = [];
        // El que más se vende con el margen más flaco: donde un ajuste de
        // precio rinde más.
        $flojo = $porGanancia->take(10)->where('ventas', '>=', $minimo)
            ->filter(fn (array $p): bool => $margenTotal !== null && $p['margen'] < $margenTotal - 5)
            ->sortBy('margen')
            ->first();
        if ($flojo) {
            $impacto = round($flojo['ingreso'] * ($margenTotal - $flojo['margen']) / 100, 2);
            $acciones[] = [
                'texto' => sprintf('Revisar el precio de %s: se vende mucho (%s) pero deja %s, %s puntos menos que el promedio. Llevarlo al promedio son %s más.', $flojo['nombre'], $this->bs($flojo['ingreso']), $this->pct($flojo['margen']), $this->numero($margenTotal - $flojo['margen'], 1), $this->bs($impacto)),
                'impacto' => $impacto,
            ];
        }

        if ($mejorMargen && $mejorMargen['nombre'] !== $primero['nombre']) {
            $acciones[] = ['texto' => sprintf('Ofrecer más %s en las propuestas: deja %s de margen.', $mejorMargen['nombre'], $this->pct($mejorMargen['margen'])), 'impacto' => null];
        }

        return $this->pregunta(
            'productos_rentables',
            'ganancias',
            $respuesta,
            columnas: $this->columnasRentabilidad('Producto'),
            filas: $porGanancia->take(self::TOP)->map(fn (array $p): array => [$p['nombre'], $p['ventas'], $p['ingreso'], $p['ganancia'], $p['margen']])->all(),
            acciones: $acciones,
            base: $base,
        );
    }

    /**
     * 6. Clientes por ganancia, no por compras: uno grande con descuento puede
     * dejar menos que uno mediano.
     *
     * @return array<string, mixed>
     */
    private function clientesRentables(User $usuario, PeriodoReporte $periodo): array
    {
        $clientes = Cotizacion::query()
            ->visiblePara($usuario)
            ->join('cliente as cl', 'cl.id', '=', 'cotizacion.cliente_id')
            ->whereIn('cotizacion.estado', self::VENDIDOS)
            ->whereBetween('cotizacion.fecha', [$periodo->desde, $periodo->hasta])
            ->where('cotizacion.costo_ajustado', '>', 0)
            ->groupBy('cotizacion.cliente_id', 'cl.razon_social')
            ->selectRaw('cotizacion.cliente_id AS id, cl.razon_social AS nombre, SUM(cotizacion.subtotal - COALESCE(cotizacion.descuento, 0)) AS ingreso, SUM(cotizacion.costo_ajustado) AS costo, COUNT(*) AS ventas')
            ->toBase()
            ->get()
            ->map(fn (object $c): array => $this->rentabilidad($c->nombre, (float) $c->ingreso, (float) $c->costo, (int) $c->ventas));

        $base = 'Ventas del periodo con hoja de costos, por cliente. Ganancia bruta antes de impuestos.';

        if ($clientes->isEmpty()) {
            return $this->pregunta('clientes_rentables', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: $base);
        }

        $porGanancia = $clientes->sortByDesc('ganancia')->values();
        $primero = $porGanancia->first();
        $masCompra = $clientes->sortByDesc('ingreso')->first();
        $margenTotal = $this->margen((float) $clientes->sum('ingreso'), (float) $clientes->sum('costo'));

        $respuesta = sprintf('%s es el cliente que más ganancia nos deja: %s (margen %s).', $primero['nombre'], $this->bs($primero['ganancia']), $this->pct($primero['margen']));

        if ($masCompra['nombre'] !== $primero['nombre']) {
            $puesto = $porGanancia->search(fn (array $c): bool => $c['nombre'] === $masCompra['nombre']) + 1;
            $respuesta .= sprintf(' El que más compra, %s, queda %s.º en ganancia: su margen es %s.', $masCompra['nombre'], $puesto, $this->pct($masCompra['margen']));
        }

        $acciones = [['texto' => sprintf('Cuidar a %s con seguimiento postventa y prioridad en entregas: es quien más ganancia aporta.', $primero['nombre']), 'impacto' => null]];

        $flojo = $clientes->sortByDesc('ingreso')->take(self::TOP)
            ->filter(fn (array $c): bool => $margenTotal !== null && $c['margen'] < $margenTotal - 5)
            ->sortBy('margen')
            ->first();
        if ($flojo) {
            $impacto = round($flojo['ingreso'] * ($margenTotal - $flojo['margen']) / 100, 2);
            array_unshift($acciones, [
                'texto' => sprintf('Revisar las condiciones de %s: compra mucho (%s) pero deja %s, %s puntos menos que el promedio (%s).', $flojo['nombre'], $this->bs($flojo['ingreso']), $this->pct($flojo['margen']), $this->numero($margenTotal - $flojo['margen'], 1), $this->bs($impacto)),
                'impacto' => $impacto,
            ]);
        }

        return $this->pregunta(
            'clientes_rentables',
            'ganancias',
            $respuesta,
            columnas: $this->columnasRentabilidad('Cliente'),
            filas: $porGanancia->take(self::TOP)->map(fn (array $c): array => [$c['nombre'], $c['ventas'], $c['ingreso'], $c['ganancia'], $c['margen']])->all(),
            acciones: $acciones,
            base: $base,
        );
    }

    /**
     * 7. Lo que se regala en descuentos (y cuánto margen cuesta) y lo que
     * quedó por debajo del precio sugerido en las líneas con precio a mano.
     *
     * @return array<string, mixed>
     */
    private function descuentos(User $usuario, PeriodoReporte $periodo): array
    {
        $descuento = 'COALESCE(cotizacion.descuento, 0)';
        $porVendedor = $this->ventas($usuario, $periodo)
            ->groupBy('cotizacion.empleado_id')
            ->selectRaw("cotizacion.empleado_id AS id, COUNT(*) AS ventas,
                SUM(CASE WHEN {$descuento} > 0 THEN 1 ELSE 0 END) AS con_descuento,
                SUM({$descuento}) AS descontado,
                SUM(CASE WHEN {$descuento} > 0 THEN cotizacion.subtotal ELSE 0 END) AS base_descontada,
                SUM(CASE WHEN {$descuento} > 0 AND cotizacion.costo_ajustado > 0 THEN cotizacion.subtotal - {$descuento} ELSE 0 END) AS ingreso_con,
                SUM(CASE WHEN {$descuento} > 0 AND cotizacion.costo_ajustado > 0 THEN cotizacion.costo_ajustado ELSE 0 END) AS costo_con,
                SUM(CASE WHEN {$descuento} <= 0 AND cotizacion.costo_ajustado > 0 THEN cotizacion.subtotal ELSE 0 END) AS ingreso_sin,
                SUM(CASE WHEN {$descuento} <= 0 AND cotizacion.costo_ajustado > 0 THEN cotizacion.costo_ajustado ELSE 0 END) AS costo_sin")
            ->toBase()
            ->get();

        $ventas = (int) $porVendedor->sum('ventas');
        $conDescuento = (int) $porVendedor->sum('con_descuento');
        $descontado = (float) $porVendedor->sum('descontado');
        $promedio = (float) $porVendedor->sum('base_descontada') > 0 ? $descontado / (float) $porVendedor->sum('base_descontada') * 100 : 0.0;
        $margenCon = $this->margen((float) $porVendedor->sum('ingreso_con'), (float) $porVendedor->sum('costo_con'));
        $margenSin = $this->margen((float) $porVendedor->sum('ingreso_sin'), (float) $porVendedor->sum('costo_sin'));
        $manual = $this->preciosAMano($usuario, $periodo);

        $base = sprintf('%s ventas del periodo. El precio sugerido es el del motor de margen: costo ajustado × (1 + margen del tipo de proyecto).', $this->numero($ventas, 0));

        if ($ventas === 0) {
            return $this->pregunta('descuentos', 'ganancias', 'No hay ventas en el periodo.', base: $base);
        }

        $frases = [$conDescuento === 0
            ? 'No dimos descuentos en el periodo.'
            : sprintf('Descontamos %s en %s de %s ventas (%s de descuento en promedio).', $this->bs($descontado), $this->numero($conDescuento, 0), $this->numero($ventas, 0), $this->pct($promedio))];

        if ($margenCon !== null && $margenSin !== null) {
            $frases[] = sprintf('Las ventas con descuento dejan un margen de %s contra %s de las que no tienen.', $this->pct($margenCon), $this->pct($margenSin));
        }

        if ($manual['lineas_debajo'] > 0) {
            $frases[] = sprintf('Además, %s con precio puesto a mano %s %s por debajo del precio sugerido.', $manual['lineas_debajo'] === 1 ? '1 línea' : $this->numero($manual['lineas_debajo'], 0).' líneas', $manual['lineas_debajo'] === 1 ? 'quedó' : 'quedaron', $this->bs($manual['debajo']));
        }

        $acciones = [];
        if ($conDescuento > 0 && $margenCon !== null && $margenSin !== null && $margenSin - $margenCon >= 2) {
            $exceso = $this->descuentoSobreTope($usuario, $periodo, $promedio / 100);
            $acciones[] = [
                'texto' => sprintf('Poner un tope de descuento de %s (el promedio actual) y pedir aprobación por encima: lo descontado de más sobre ese tope suma %s.', $this->pct($promedio), $this->bs($exceso)),
                'impacto' => $exceso > 0 ? $exceso : null,
            ];
        }

        if ($manual['lineas_debajo'] > 0) {
            $acciones[] = ['texto' => sprintf('Revisar los precios puestos a mano: %s quedaron por debajo del sugerido.', $this->bs($manual['debajo'])), 'impacto' => $manual['debajo']];
        }

        $nombres = $this->nombresDeVendedores($porVendedor->pluck('id'));

        return $this->pregunta(
            'descuentos',
            'ganancias',
            implode(' ', $frases),
            columnas: [
                ['titulo' => 'Vendedor', 'formato' => 'texto'],
                ['titulo' => 'Ventas con descuento', 'formato' => 'cantidad'],
                ['titulo' => 'Descontado', 'formato' => 'bs'],
                ['titulo' => 'Descuento promedio', 'formato' => 'porcentaje'],
                ['titulo' => 'Margen con descuento', 'formato' => 'porcentaje'],
            ],
            filas: $porVendedor
                ->filter(fn (object $v): bool => (int) $v->con_descuento > 0)
                ->sortByDesc('descontado')
                ->take(self::TOP)
                ->map(fn (object $v): array => [
                    $nombres($v->id),
                    (int) $v->con_descuento,
                    round((float) $v->descontado, 2),
                    (float) $v->base_descontada > 0 ? round((float) $v->descontado / (float) $v->base_descontada * 100, 1) : null,
                    $this->margen((float) $v->ingreso_con, (float) $v->costo_con),
                ])->values()->all(),
            acciones: $acciones,
            base: $base,
        );
    }

    /**
     * @return array{lineas: int, lineas_debajo: int, debajo: float}
     */
    private function preciosAMano(User $usuario, PeriodoReporte $periodo): array
    {
        $sugerido = 'cotizacion_detalle.costo_ajustado * (1 + cotizacion_detalle.margen_aplicado)';

        $fila = CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->join('cotizacion as c', 'c.id', '=', 'cotizacion_detalle.cotizacion_id')
            ->whereIn('c.estado', self::VENDIDOS)
            ->whereBetween('c.fecha', [$periodo->desde, $periodo->hasta])
            ->where('cotizacion_detalle.precio_manual', 'SI')
            ->where('cotizacion_detalle.costo_ajustado', '>', 0)
            ->selectRaw("COUNT(*) AS lineas,
                SUM(CASE WHEN {$sugerido} > cotizacion_detalle.subtotal THEN 1 ELSE 0 END) AS lineas_debajo,
                SUM(CASE WHEN {$sugerido} > cotizacion_detalle.subtotal THEN {$sugerido} - cotizacion_detalle.subtotal ELSE 0 END) AS debajo")
            ->toBase()
            ->first();

        return [
            'lineas' => (int) ($fila->lineas ?? 0),
            'lineas_debajo' => (int) ($fila->lineas_debajo ?? 0),
            'debajo' => round((float) ($fila->debajo ?? 0), 2),
        ];
    }

    /**
     * Lo descontado por encima de un tope (fracción del subtotal).
     */
    private function descuentoSobreTope(User $usuario, PeriodoReporte $periodo, float $tope): float
    {
        $fila = $this->ventas($usuario, $periodo)
            ->selectRaw('SUM(CASE WHEN COALESCE(cotizacion.descuento, 0) > cotizacion.subtotal * ? THEN COALESCE(cotizacion.descuento, 0) - cotizacion.subtotal * ? ELSE 0 END) AS exceso', [$tope, $tope])
            ->toBase()
            ->first();

        return round((float) ($fila->exceso ?? 0), 2);
    }

    /**
     * 8. Ventas cerradas en amarillo o rojo del semáforo de margen.
     *
     * @return array<string, mixed>
     */
    private function margenBajo(User $usuario, PeriodoReporte $periodo): array
    {
        $neto = 'cotizacion.subtotal - COALESCE(cotizacion.descuento, 0)';
        $porVendedor = $this->ventas($usuario, $periodo)
            ->where('cotizacion.costo_ajustado', '>', 0)
            ->groupBy('cotizacion.empleado_id')
            ->selectRaw("cotizacion.empleado_id AS id, COUNT(*) AS ventas,
                SUM(CASE WHEN cotizacion.estado_margen = 'AMARILLO' THEN 1 ELSE 0 END) AS amarillas,
                SUM(CASE WHEN cotizacion.estado_margen = 'ROJO' THEN 1 ELSE 0 END) AS rojas,
                SUM(CASE WHEN cotizacion.estado_margen = 'AMARILLO' THEN {$neto} ELSE 0 END) AS bs_amarillo,
                SUM(CASE WHEN cotizacion.estado_margen = 'ROJO' THEN {$neto} ELSE 0 END) AS bs_rojo")
            ->toBase()
            ->get();

        $semaforo = config('margen.semaforo');
        $base = sprintf(
            'Ventas con hoja de costos. Rojo = rentabilidad real (después de impuestos) menor a %s del costo; amarillo, entre %s y %s.',
            $this->pct($semaforo['umbral_amarillo'] * 100, 0),
            $this->pct($semaforo['umbral_amarillo'] * 100, 0),
            $this->pct($semaforo['umbral_verde'] * 100, 0),
        );

        $ventas = (int) $porVendedor->sum('ventas');
        if ($ventas === 0) {
            return $this->pregunta('margen_bajo', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: $base);
        }

        $amarillas = (int) $porVendedor->sum('amarillas');
        $rojas = (int) $porVendedor->sum('rojas');
        $bajas = $amarillas + $rojas;

        $respuesta = $bajas === 0
            ? sprintf('Ninguna: las %s ventas del periodo se cerraron en verde.', $this->numero($ventas, 0))
            : sprintf(
                '%s de %s ventas (%s) se cerraron con margen bajo: %s en amarillo (%s) y %s en rojo (%s).',
                $this->numero($bajas, 0),
                $this->numero($ventas, 0),
                $this->pct($bajas / $ventas * 100),
                $this->numero($amarillas, 0),
                $this->bs((float) $porVendedor->sum('bs_amarillo')),
                $this->numero($rojas, 0),
                $this->bs((float) $porVendedor->sum('bs_rojo')),
            );

        $nombres = $this->nombresDeVendedores($porVendedor->pluck('id'));
        $filas = $porVendedor
            ->filter(fn (object $v): bool => (int) $v->amarillas + (int) $v->rojas > 0)
            ->sortByDesc(fn (object $v): array => [(int) $v->rojas, (int) $v->amarillas])
            ->values();

        $acciones = [];
        if ($rojas > 0) {
            $acciones[] = [
                'texto' => sprintf('No aceptar trabajos en rojo sin la aprobación de gerencia: en el periodo se vendieron %s en rojo.', $this->bs((float) $porVendedor->sum('bs_rojo'))),
                'impacto' => round((float) $porVendedor->sum('bs_rojo'), 2),
            ];
        }
        if ($filas->isNotEmpty()) {
            $v = $filas->first();
            $acciones[] = ['texto' => sprintf('Revisar con %s sus %s ventas con margen bajo.', $nombres($v->id), $this->numero((int) $v->amarillas + (int) $v->rojas, 0)), 'impacto' => null];
        }

        return $this->pregunta(
            'margen_bajo',
            'ganancias',
            $respuesta,
            columnas: [
                ['titulo' => 'Vendedor', 'formato' => 'texto'],
                ['titulo' => 'Ventas', 'formato' => 'cantidad'],
                ['titulo' => 'En amarillo', 'formato' => 'cantidad'],
                ['titulo' => 'En rojo', 'formato' => 'cantidad'],
                ['titulo' => 'Vendido con margen bajo', 'formato' => 'bs'],
            ],
            filas: $filas->take(self::TOP)->map(fn (object $v): array => [
                $nombres($v->id), (int) $v->ventas, (int) $v->amarillas, (int) $v->rojas, round((float) $v->bs_amarillo + (float) $v->bs_rojo, 2),
            ])->all(),
            acciones: $acciones,
            base: $base,
        );
    }

    /* -----------------------------------------------------------------
     * Escenarios
     * -------------------------------------------------------------- */

    /**
     * 9. Sensibilidad a la materia prima: si sube un s %, el costo sube
     * s × (parte del material en el costo). El precio que mantiene el margen
     * sube en esa misma proporción.
     *
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $costos
     * @return array<string, mixed>
     */
    private function materiaPrima(Collection $lineas, array $costos): array
    {
        $ingreso = (float) $lineas->sum('ingreso');
        $costo = (float) $lineas->sum('costo');
        $costoBase = (float) $lineas->sum('costo_base');
        $base = 'Ventas del periodo con hoja de costos. "Materia prima" son los insumos de tipo material de la hoja; la mano de obra, la impresión y los servicios no cambian.';

        if ($ingreso <= 0 || $costoBase <= 0) {
            return $this->pregunta('materia_prima', 'escenarios', 'No hay ventas con hoja de costos en el periodo para simular.', base: $base);
        }

        $parte = (float) $lineas->sum('material_base') / $costoBase;
        $margenHoy = $this->margen($ingreso, $costo);
        $escenario = fn (float $subida): array => [
            'margen' => $this->margen($ingreso, $costo * (1 + $parte * $subida / 100)),
            'perdida' => round($costo * $parte * $subida / 100, 2),
            'subir_precio' => round($parte * $subida, 1),
        ];

        $filas = [['Hoy', $margenHoy, 0.0, 0.0]];
        foreach (config('bi.preguntas.escenarios_materia_prima') as $subida) {
            $e = $escenario((float) $subida);
            $filas[] = [sprintf('Materia prima +%s %%', $subida), $e['margen'], $e['perdida'], $e['subir_precio']];
        }

        $mensual = $costos['indice']['variacion_mensual'] ?? null;
        $meses = (int) config('bi.meses_proyeccion');
        if ($mensual !== null && $mensual > 0) {
            $probable = ((1 + $mensual / 100) ** $meses - 1) * 100;
            $e = $escenario($probable);
            $filas[] = [sprintf('Probable en %d meses (tendencia %s/mes)', $meses, RedaccionPronostico::variacion($mensual)), $e['margen'], $e['perdida'], $e['subir_precio']];
        }

        $diez = $escenario(10);
        $expuestos = $lineas
            ->where('ventas', '>=', (int) config('bi.preguntas.minimo_ventas'))
            ->filter(fn (array $p): bool => $p['costo_base'] > 0)
            ->sortByDesc(fn (array $p): float => $p['material_base'] / $p['costo_base'])
            ->take(2)
            ->map(fn (array $p): string => sprintf('%s (el material es el %s de su costo)', $p['nombre'], $this->pct($p['material_base'] / $p['costo_base'] * 100, 0)))
            ->values()
            ->all();

        $respuesta = sprintf(
            'Si la materia prima sube 10 %%, el margen bruto bajaría de %s a %s y perderíamos %s en un periodo como este. Para no perder margen alcanzaría con subir los precios %s.',
            $this->pct($margenHoy),
            $this->pct($diez['margen']),
            $this->bs($diez['perdida']),
            $this->pct($diez['subir_precio']),
        );
        if ($expuestos !== []) {
            $respuesta .= ' Los más expuestos: '.$this->enumerar($expuestos).'.';
        }

        return $this->pregunta(
            'materia_prima',
            'escenarios',
            $respuesta,
            columnas: [
                ['titulo' => 'Escenario', 'formato' => 'texto'],
                ['titulo' => 'Margen bruto', 'formato' => 'porcentaje'],
                ['titulo' => 'Ganancia que se pierde', 'formato' => 'bs'],
                ['titulo' => 'Subir precios', 'formato' => 'porcentaje'],
            ],
            filas: $filas,
            // Sin "dinero en juego": es una hipótesis, no algo que ya pasó, y
            // no debe ganarle en "Decisiones" a lo que sí se midió.
            acciones: [[
                'texto' => sprintf('Si se confirma una subida, ajustar los precios en la misma proporción: %s por cada 10 %% que suba la materia prima. Sin ajustar, con +10 %% perderíamos %s.', $this->pct($diez['subir_precio']), $this->bs($diez['perdida'])),
                'impacto' => null,
            ]],
            base: $base,
            aviso: sprintf('Hoy la materia prima es el %s del costo de lo vendido.', $this->pct($parte * 100)),
        );
    }

    /**
     * 10. Lo vendido en pedidos del periodo contra lo ya cobrado, con la
     * antigüedad de lo que falta.
     *
     * @return array<string, mixed>
     */
    private function cobranza(User $usuario, PeriodoReporte $periodo): array
    {
        $pedidos = Pedido::query()
            ->visiblePara($usuario)
            ->where('estado', '!=', 'CANCELADO')
            ->whereBetween('fecha_pedido', [$periodo->desde, $periodo->hasta])
            ->withSum('pagos', 'monto')
            ->with('cotizacion:id,cliente_id', 'cotizacion.cliente:id,razon_social')
            ->get(['id', 'cotizacion_id', 'fecha_pedido', 'total', 'estado'])
            ->map(fn (Pedido $p): array => [
                'cliente' => $p->cotizacion?->cliente?->razon_social ?? 'Sin cliente',
                'dias' => (int) $p->fecha_pedido->diffInDays(Carbon::today()),
                'total' => (float) $p->total,
                'pagado' => (float) ($p->pagos_sum_monto ?? 0),
                'saldo' => round(max((float) $p->total - (float) ($p->pagos_sum_monto ?? 0), 0), 2),
                'entregado_sin_pago' => $p->estado === 'ENTREGADO' && (float) ($p->pagos_sum_monto ?? 0) <= 0,
            ]);

        $base = 'Pedidos del periodo (sin los cancelados). La antigüedad se cuenta desde la fecha del pedido, como en el Reporte financiero.';

        if ($pedidos->isEmpty()) {
            return $this->pregunta('cobranza', 'escenarios', 'No hay pedidos en el periodo.', base: $base);
        }

        $vendido = (float) $pedidos->sum('total');
        $pendientes = $pedidos->where('saldo', '>', 0.005);
        $saldo = (float) $pendientes->sum('saldo');

        $tramos = config('bi.preguntas.tramos_cobranza');
        $limites = [0, ...$tramos];
        $filas = [];
        foreach ($limites as $i => $desde) {
            $hasta = $limites[$i + 1] ?? null;
            $grupo = $pendientes->filter(fn (array $p): bool => $p['dias'] >= $desde + ($i > 0 ? 1 : 0) && ($hasta === null || $p['dias'] <= $hasta));
            $filas[] = [
                $hasta === null ? sprintf('Más de %d días', $desde) : sprintf('%d a %d días', $i > 0 ? $desde + 1 : 0, $hasta),
                $grupo->count(),
                round((float) $grupo->sum('saldo'), 2),
                $saldo > 0 ? round((float) $grupo->sum('saldo') / $saldo * 100, 1) : 0.0,
            ];
        }

        $viejos = end($filas);
        $respuesta = $saldo <= 0
            ? sprintf('Todo: los %s pedidos del periodo están cobrados.', $this->numero($pedidos->count(), 0))
            : sprintf(
                'Ya cobramos el %s de lo vendido: faltan %s en %s %s.%s',
                $this->pct(($vendido - $saldo) / $vendido * 100),
                $this->bs($saldo),
                $this->numero($pendientes->count(), 0),
                $pendientes->count() === 1 ? 'pedido' : 'pedidos',
                $viejos[2] > 0 ? sprintf(' %s tienen más de %d días.', $this->bs($viejos[2]), end($tramos)) : '',
            );

        $acciones = [];
        if ($viejos[2] > 0) {
            $acciones[] = ['texto' => sprintf('Cobrar primero los %s pedidos con más de %d días: suman %s.', $this->numero($viejos[1], 0), end($tramos), $this->bs($viejos[2])), 'impacto' => $viejos[2]];
        }

        $deudor = $pendientes->groupBy('cliente')->map(fn (Collection $g): float => (float) $g->sum('saldo'))->sortDesc();
        if ($deudor->isNotEmpty()) {
            $acciones[] = ['texto' => sprintf('El que más debe es %s: %s.', $deudor->keys()->first(), $this->bs($deudor->first())), 'impacto' => null];
        }

        $sinPago = $pedidos->where('entregado_sin_pago', true)->count();
        if ($sinPago > 0) {
            $acciones[] = ['texto' => sprintf('Pedir un anticipo al aprobar: %s sin ningún pago.', $sinPago === 1 ? '1 pedido se entregó' : $this->numero($sinPago, 0).' pedidos se entregaron'), 'impacto' => null];
        }

        return $this->pregunta(
            'cobranza',
            'escenarios',
            $respuesta,
            columnas: [
                ['titulo' => 'Antigüedad', 'formato' => 'texto'],
                ['titulo' => 'Pedidos', 'formato' => 'cantidad'],
                ['titulo' => 'Por cobrar', 'formato' => 'bs'],
                ['titulo' => 'Parte', 'formato' => 'porcentaje'],
            ],
            filas: $saldo > 0 ? $filas : [],
            acciones: $acciones,
            base: $base,
        );
    }

    /* -----------------------------------------------------------------
     * Decisiones
     * -------------------------------------------------------------- */

    /**
     * 11. Las acciones de las demás preguntas, ordenadas por el dinero que
     * mueven. No hay recomendaciones propias: cada una repite su cifra.
     *
     * @param  list<array<string, mixed>>  $preguntas
     * @return array<string, mixed>
     */
    private function decisiones(array $preguntas): array
    {
        $acciones = collect($preguntas)
            ->flatMap(fn (array $p): array => array_map(fn (array $a): array => $a + ['origen' => $p['corto']], $p['acciones']))
            ->filter(fn (array $a): bool => $a['impacto'] !== null && $a['impacto'] > 0)
            ->sortByDesc('impacto')
            ->take(8)
            ->values();

        $respuesta = $acciones->isEmpty()
            ? 'Con los datos del periodo no surge ninguna acción con dinero en juego.'
            : sprintf(
                '%s acciones concretas, de la que más dinero mueve a la que menos. La primera: %s',
                $this->numero($acciones->count(), 0),
                lcfirst($acciones->first()['texto']),
            );

        return $this->pregunta(
            'decisiones',
            'decisiones',
            $respuesta,
            columnas: [
                ['titulo' => 'Acción', 'formato' => 'texto'],
                ['titulo' => 'Sale de', 'formato' => 'texto'],
                ['titulo' => 'Dinero en juego', 'formato' => 'bs'],
            ],
            filas: $acciones->map(fn (array $a): array => [$a['texto'], $a['origen'], $a['impacto']])->all(),
            base: 'Cada acción sale de una de las preguntas anteriores y repite su cifra: no hay recomendaciones sin un número detrás. "Dinero en juego" es lo que mueve cada una en un periodo como este; no se suman entre sí porque algunas se superponen.',
        );
    }

    /* -----------------------------------------------------------------
     * Consultas compartidas
     * -------------------------------------------------------------- */

    /**
     * Ítems de pedido entregados (consumo completo) de pedidos del periodo.
     *
     * @return Builder<PedidoDetalle>
     */
    private function trabajosEntregados(User $usuario, PeriodoReporte $periodo): Builder
    {
        return PedidoDetalle::query()
            ->where('pedido_detalle.estado_item', 'ENTREGADO')
            ->whereHas('pedido', fn (Builder $q) => $q
                ->visiblePara($usuario)
                ->where('estado', '!=', 'CANCELADO')
                ->whereBetween('fecha_pedido', [$periodo->desde, $periodo->hasta]));
    }

    /**
     * Los entregados que se pueden comparar: con consumo registrado y con
     * material en la hoja de costos de su cotización.
     *
     * @return Builder<PedidoDetalle>
     */
    private function comparables(User $usuario, PeriodoReporte $periodo): Builder
    {
        return $this->trabajosEntregados($usuario, $periodo)
            ->whereHas('materialesUsados')
            ->whereHas('cotizacionDetalle.items', fn (Builder $q) => $q->where('tipo', 'MATERIAL'));
    }

    /**
     * Un registro por trabajo comparable: material real, material y demás
     * insumos de la hoja (por unidad de la línea), medidas y producto.
     *
     * @return Collection<int, object>
     */
    private function trabajosComparables(User $usuario, PeriodoReporte $periodo): Collection
    {
        $real = DB::table('pedido_detalle_material')
            ->selectRaw('pedido_detalle_id, SUM(costo_real) AS costo_real')
            ->groupBy('pedido_detalle_id');

        $hoja = DB::table('cotizacion_detalle_item')
            ->selectRaw("cotizacion_detalle_id,
                SUM(CASE WHEN tipo = 'MATERIAL' THEN subtotal ELSE 0 END) AS material,
                SUM(CASE WHEN tipo = 'MATERIAL' THEN 0 ELSE subtotal END) AS otros")
            ->groupBy('cotizacion_detalle_id');

        return $this->comparables($usuario, $periodo)
            ->join('cotizacion_detalle as cd', 'cd.id', '=', 'pedido_detalle.cotizacion_detalle_id')
            ->leftJoin('producto as p', 'p.id', '=', 'cd.producto_id')
            ->joinSub($real, 'r', 'r.pedido_detalle_id', '=', 'pedido_detalle.id')
            ->joinSub($hoja, 'h', 'h.cotizacion_detalle_id', '=', 'cd.id')
            ->toBase()
            ->get([
                'pedido_detalle.id', 'pedido_detalle.ancho', 'pedido_detalle.alto', 'pedido_detalle.cantidad',
                'cd.producto_id', 'cd.cantidad as cantidad_cotizada', 'p.nombre as producto', 'p.unidad_medida',
                'r.costo_real', 'h.material as hoja_material', 'h.otros as hoja_otros',
            ])
            ->map(function (object $t): object {
                foreach (['costo_real', 'hoja_material', 'hoja_otros', 'cantidad_cotizada'] as $campo) {
                    $t->{$campo} = (float) $t->{$campo};
                }

                return $t;
            });
    }

    /**
     * Ventas con hoja de costos agrupadas por producto: ingreso, costo,
     * ganancia, margen y la parte de material del costo base.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lineasVendidasPorProducto(User $usuario, PeriodoReporte $periodo): Collection
    {
        $hoja = DB::table('cotizacion_detalle_item')
            ->selectRaw("cotizacion_detalle_id, SUM(CASE WHEN tipo = 'MATERIAL' THEN subtotal ELSE 0 END) AS material")
            ->groupBy('cotizacion_detalle_id');

        return CotizacionDetalle::query()
            ->visiblePara($usuario)
            ->join('cotizacion as c', 'c.id', '=', 'cotizacion_detalle.cotizacion_id')
            ->leftJoin('producto as p', 'p.id', '=', 'cotizacion_detalle.producto_id')
            ->leftJoinSub($hoja, 'h', 'h.cotizacion_detalle_id', '=', 'cotizacion_detalle.id')
            ->whereIn('c.estado', self::VENDIDOS)
            ->whereBetween('c.fecha', [$periodo->desde, $periodo->hasta])
            ->where('cotizacion_detalle.costo_ajustado', '>', 0)
            ->groupBy('cotizacion_detalle.producto_id', 'p.nombre')
            ->selectRaw('cotizacion_detalle.producto_id AS id, p.nombre AS nombre, '
                .'SUM('.ConsultaSql::INGRESO_NETO_LINEA.') AS ingreso, '
                .'SUM(cotizacion_detalle.costo_ajustado) AS costo, '
                .'SUM(cotizacion_detalle.costo_base) AS costo_base, '
                .'SUM(COALESCE(h.material, 0) * cotizacion_detalle.cantidad) AS material_base, '
                .'COUNT(DISTINCT c.id) AS ventas')
            ->toBase()
            ->get()
            ->map(fn (object $f): array => $this->rentabilidad($f->nombre ?? self::SIN_PRODUCTO, (float) $f->ingreso, (float) $f->costo, (int) $f->ventas) + [
                'costo_base' => (float) $f->costo_base,
                'material_base' => (float) $f->material_base,
            ]);
    }

    /**
     * Ventas del periodo visibles para el usuario (cabecera de cotización).
     *
     * @return Builder<Cotizacion>
     */
    private function ventas(User $usuario, PeriodoReporte $periodo): Builder
    {
        return Cotizacion::query()
            ->visiblePara($usuario)
            ->whereIn('cotizacion.estado', self::VENDIDOS)
            ->whereBetween('cotizacion.fecha', [$periodo->desde, $periodo->hasta]);
    }

    /**
     * @param  Collection<int, int|null>  $ids
     * @return callable(int|null): string
     */
    private function nombresDeVendedores(Collection $ids): callable
    {
        $nombres = Empleado::query()->whereIn('id', $ids->filter())->get(['id', 'nombres', 'paterno'])
            ->mapWithKeys(fn (Empleado $e): array => [$e->id => trim("{$e->nombres} {$e->paterno}")]);

        return fn (?int $id): string => $id ? ($nombres[$id] ?? 'Vendedor eliminado') : 'Sin vendedor (portal del cliente)';
    }

    /* -----------------------------------------------------------------
     * Forma y formato
     * -------------------------------------------------------------- */

    /**
     * @param  list<array{titulo: string, formato: string}>  $columnas
     * @param  list<list<mixed>>  $filas
     * @param  list<array{texto: string, impacto: float|null}>  $acciones
     * @return array<string, mixed>
     */
    private function pregunta(
        string $clave,
        string $grupo,
        string $respuesta,
        array $columnas = [],
        array $filas = [],
        array $acciones = [],
        ?string $base = null,
        ?string $aviso = null,
    ): array {
        [$pregunta, $corto] = self::PREGUNTAS[$clave];

        return [
            'clave' => $clave,
            'grupo' => $grupo,
            'pregunta' => $pregunta,
            'corto' => $corto,
            'respuesta' => $respuesta,
            'tabla' => ['columnas' => $columnas, 'filas' => $filas],
            'acciones' => $acciones,
            'base' => $base,
            'aviso' => $aviso,
        ];
    }

    /** Texto de cada pregunta y su rótulo corto (para "Sale de"). */
    public const PREGUNTAS = [
        'costo_producto' => ['¿Cuánto nos cuesta realmente producir cada producto?', 'Costo real por producto'],
        'costos_suben' => ['¿Qué costos están aumentando?', 'Costos que suben'],
        'gasto_presupuesto' => ['¿Estamos gastando más de lo presupuestado?', 'Gasto contra presupuesto'],
        'materiales_pesan' => ['¿En qué materiales se nos va la mayor parte del dinero?', 'Materiales que más pesan'],
        'productos_rentables' => ['¿Qué producto genera mayor rentabilidad?', 'Productos más rentables'],
        'clientes_rentables' => ['¿Qué clientes nos dejan más ganancia?', 'Clientes que más dejan'],
        'descuentos' => ['¿Cuánto nos cuestan los descuentos y los precios puestos a mano?', 'Descuentos y precios a mano'],
        'margen_bajo' => ['¿Qué trabajos se están vendiendo con margen bajo?', 'Ventas con margen bajo'],
        'materia_prima' => ['¿Qué pasaría si sube el precio de la materia prima?', 'Si sube la materia prima'],
        'cobranza' => ['¿Cuánto de lo vendido ya cobramos?', 'Cobranza'],
        'decisiones' => ['¿Qué decisiones ayudarían a reducir los costos y mejorar las ganancias?', 'Decisiones'],
    ];

    /**
     * @return list<array{titulo: string, formato: string}>
     */
    private function columnasRentabilidad(string $quien): array
    {
        return [
            ['titulo' => $quien, 'formato' => 'texto'],
            ['titulo' => 'Ventas', 'formato' => 'cantidad'],
            ['titulo' => 'Ingresos', 'formato' => 'bs'],
            ['titulo' => 'Ganancia bruta', 'formato' => 'bs'],
            ['titulo' => 'Margen', 'formato' => 'porcentaje'],
        ];
    }

    /**
     * @return array{nombre: string, ventas: int, ingreso: float, costo: float, ganancia: float, margen: float|null}
     */
    private function rentabilidad(string $nombre, float $ingreso, float $costo, int $ventas): array
    {
        return [
            'nombre' => $nombre,
            'ventas' => $ventas,
            'ingreso' => round($ingreso, 2),
            'costo' => round($costo, 2),
            'ganancia' => round($ingreso - $costo, 2),
            'margen' => $this->margen($ingreso, $costo),
        ];
    }

    private function margen(float $ingreso, float $costo): ?float
    {
        return $ingreso > 0 ? round(($ingreso - $costo) / $ingreso * 100, 1) : null;
    }

    /**
     * Unidades de venta de un trabajo: m², metros o unidades según el
     * producto. Sin medidas, un producto por m² no se puede repartir.
     */
    private function unidades(object $trabajo): float
    {
        $cantidad = (float) $trabajo->cantidad;

        return match ($trabajo->unidad_medida) {
            'M2' => $trabajo->ancho !== null && $trabajo->alto !== null ? (float) $trabajo->ancho * (float) $trabajo->alto * $cantidad : 0.0,
            'METRO_LINEAL' => $trabajo->ancho !== null ? (float) $trabajo->ancho * $cantidad : 0.0,
            default => $cantidad,
        };
    }

    private function bs(float $valor, int $decimales = 0): string
    {
        return 'Bs '.RedaccionPronostico::numero($valor, $decimales);
    }

    private function pct(float $valor, int $decimales = 1): string
    {
        return RedaccionPronostico::numero($valor, $decimales).' %';
    }

    private function numero(float|int $valor, int $decimales): string
    {
        return RedaccionPronostico::numero($valor, $decimales);
    }

    /**
     * "a", "a y b", "a, b y c".
     *
     * @param  list<string>  $partes
     */
    private function enumerar(array $partes): string
    {
        $ultimo = array_pop($partes);

        return $partes === [] ? (string) $ultimo : implode(', ', $partes).' y '.$ultimo;
    }
}
