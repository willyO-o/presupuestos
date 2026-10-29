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
 * - **Resultado bruto** = ingreso neto (sin IVA, con el descuento prorrateado)
 *   − costo ajustado de la hoja de costos, antes de impuestos; **margen** =
 *   resultado ÷ ingreso. Las líneas sin hoja (costo 0, anteriores al motor de
 *   margen) no entran: su margen sería 100 %. En el código sigue llamándose
 *   `ganancia`; en los textos, "resultado bruto" (no es la ganancia neta).
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
        'ganancias' => 'Rentabilidad',
        'escenarios' => 'Escenarios y cobros',
        'decisiones' => 'Acciones',
    ];

    private const UNIDADES = ['M2' => 'm²', 'METRO_LINEAL' => 'm', 'UNIDAD' => 'unidad'];

    /**
     * Qué representa el monto de una acción en "¿Qué acciones conviene revisar
     * primero?", y su tipo: un costo que ya ocurrió (`observado`), ventas o
     * descuentos en riesgo (`riesgo`), una estimación (`estimado`) o una
     * diferencia frente a una referencia (`diferencia`). La pantalla colorea
     * por tipo; así no se leen como si fueran la misma clase de dinero.
     * El tercer valor es el frente de trabajo, con el que la pregunta 11
     * arma su recomendación de por dónde empezar.
     */
    private const MONTOS = [
        'costo_adicional' => ['Costo adicional calculado', 'observado', 'materiales'],
        'exceso_material' => ['Exceso de material registrado', 'observado', 'materiales'],
        'saldo_antiguo' => ['Saldo pendiente de cobro', 'observado', 'cobros'],
        'margen_bajo' => ['Ventas con margen bajo', 'riesgo', 'comercial'],
        'sobre_tope' => ['Descuentos por encima del tope', 'riesgo', 'comercial'],
        'ahorro' => ['Ahorro estimado', 'estimado', 'materiales'],
        'mejora' => ['Mejora estimada del resultado', 'estimado', 'precios'],
        'bajo_sugerido' => ['Diferencia frente al sugerido', 'diferencia', 'comercial'],
        'desvio_presupuesto' => ['Diferencia frente al presupuesto', 'diferencia', 'hojas'],
    ];

    /** Por dónde empezar, según el frente de las acciones de la pregunta 11. */
    private const FRENTES = [
        'materiales' => 'contrastar los precios y consumos de los materiales de mayor impacto',
        'comercial' => 'revisar las autorizaciones comerciales',
        'hojas' => 'actualizar las hojas de costos cuando corresponda',
        'precios' => 'evaluar los precios con margen por debajo del promedio',
        'cobros' => 'dar seguimiento a los cobros más antiguos',
    ];

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
            '%s de %s entregados (%s) tienen consumo registrado y hoja de costos. Importes por unidad, en Bs.',
            $this->numero($trabajos->count(), 0),
            $this->cantidad($entregados, 'trabajo', 'trabajos'),
            $this->pct($entregados > 0 ? $trabajos->count() / $entregados * 100 : 0),
        );
        $aviso = 'La mano de obra es la presupuestada en la hoja de costos: no se registran horas reales trabajadas. No incluye el recargo por complejidad del tipo de proyecto.';
        $interpretacion = 'No es un costo totalmente real: la mano de obra es presupuestada y no se registran horas reales. No incluye el recargo por complejidad del proyecto. *Obra y otros provienen de la hoja de costos.';

        if ($porProducto->isEmpty()) {
            return $this->pregunta('costo_producto', 'costos', 'No hay trabajos entregados con consumo de material registrado en el periodo.', base: $base, aviso: $aviso, interpretacion: $interpretacion);
        }

        $primero = $porProducto->first();
        $respuesta = sprintf(
            'Para %s, el costo calculado es %s por %s: %s de material registrado y %s de mano de obra y otros presupuestados.',
            $primero['nombre'],
            $this->bs($primero['total'], 2),
            $primero['unidad'],
            $this->bs($primero['material_real'], 2),
            $this->bs($primero['otros'], 2),
        );

        if ($primero['desvio'] !== null && abs($primero['desvio']) >= 1) {
            $respuesta .= $primero['desvio'] > 0
                ? sprintf(' El material supera en %s lo presupuestado.', $this->pct($primero['desvio']))
                : sprintf(' El material queda %s por debajo de lo presupuestado.', $this->pct(abs($primero['desvio'])));
        }

        return $this->pregunta(
            'costo_producto',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Producto', 'formato' => 'texto'],
                ['titulo' => 'Trabajos / unidad', 'formato' => 'texto'],
                ['titulo' => 'Material registrado', 'formato' => 'bs_exacto'],
                ['titulo' => 'Material previsto', 'formato' => 'bs_exacto'],
                ['titulo' => 'Obra y otros*', 'formato' => 'bs_exacto'],
                ['titulo' => 'Total', 'formato' => 'bs_exacto'],
            ],
            filas: $porProducto->take(self::TOP)->map(fn (array $p): array => [
                $p['nombre'], $this->numero($p['trabajos'], 0).' / '.$p['unidad'], $p['material_real'], $p['material_presupuestado'], $p['otros'], $p['total'],
            ])->all(),
            acciones: [
                $this->accion('Usar este costo como referencia al cotizar. Revisar mano de obra, otros costos y margen antes de fijar el precio de venta.'),
            ],
            base: $base,
            aviso: $aviso,
            interpretacion: $interpretacion,
            // Para la cuenta "material + obra y otros = costo" de la pantalla.
            cifras: [
                'producto' => $primero['nombre'],
                'unidad' => $primero['unidad'],
                'material' => $primero['material_real'],
                'previsto' => $primero['material_presupuestado'],
                'obra' => $primero['otros'],
                'total' => $primero['total'],
            ],
        );
    }

    /**
     * 2. Materiales que subieron de precio y el costo adicional que generó la
     * subida según lo consumido. Después, en una frase cada uno, la tendencia
     * de los precios de materiales y la hora de mano de obra presupuestada.
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
        $base = sprintf(
            'Historial de precios de %s.%s Importes en Bs.',
            $this->cantidad($costos['indice']['materiales'] ?? count($impacto['materiales']), 'material', 'materiales'),
            $suben->count() > self::TOP ? sprintf(' Se muestran los %d de mayor impacto; el total incluye los %s.', self::TOP, $this->numero($suben->count(), 0)) : '',
        );
        $bajaron = match ((int) $impacto['bajan']) {
            0 => ' y ninguno bajó',
            1 => ' y 1 bajó',
            default => " y {$impacto['bajan']} bajaron",
        };

        $frases = [];
        $acciones = [];
        $cifras = [];
        $interpretacion = ['El costo adicional se calcula como aumento del precio × cantidad consumida; no equivale a una pérdida neta ni a un ahorro garantizado.'];

        if ($suben->isEmpty()) {
            $frases[] = sprintf('Ningún material subió de precio en el periodo%s.', $impacto['bajan'] > 0 ? $bajaron : '');
        } else {
            $primero = $suben->first();
            $total = (float) $suben->sum('impacto');
            $frases[] = sprintf(
                '%s %s de precio%s.',
                $this->numero($impacto['suben'], 0),
                $impacto['suben'] === 1 ? 'material aumentó' : 'materiales aumentaron',
                $bajaron,
            );
            $frases[] = $suben->count() === 1
                ? sprintf('El costo adicional calculado es %s y corresponde a %s, con un aumento de %s.', $this->bs($total), $primero['material'], $this->pct($primero['variacion_pct']))
                : sprintf(
                    'El costo adicional calculado es %s. %s tiene el mayor impacto: %s, con un aumento de %s.',
                    $this->bs($total),
                    $primero['material'],
                    $this->bs($primero['impacto']),
                    $this->pct($primero['variacion_pct']),
                );
            $acciones[] = $this->accion(
                sprintf('Negociar el precio de %s o comparar proveedores.', $primero['material']),
                $primero['impacto'] > 0 ? (float) $primero['impacto'] : null,
                sprintf('Negociar %s o evaluar proveedor', $primero['material']),
                'costo_adicional',
            );
            // Para los indicadores de la pantalla y la portada del informe.
            $cifras = [
                'materiales' => (int) $impacto['suben'],
                'costo_adicional' => round($total, 2),
                'mayor_material' => $primero['material'],
                'mayor_aumento' => (float) $primero['variacion_pct'],
                'mayor_costo' => (float) $primero['impacto'],
            ];
        }

        $mensual = $costos['indice']['variacion_mensual'] ?? null;
        if ($mensual !== null && abs($mensual) >= 0.1) {
            $interpretacion[] = sprintf('En promedio, el precio de los materiales %s %s por mes.', $mensual > 0 ? 'sube' : 'baja', $this->pct(abs($mensual)));
        }

        if ($manoDeObra !== null && abs($manoDeObra['variacion']) >= 0.5) {
            $interpretacion[] = sprintf(
                'La hora de mano de obra presupuestada pasó de %s a %s (%s).',
                $this->bs($manoDeObra['antes'], 2),
                $this->bs($manoDeObra['ahora'], 2),
                RedaccionPronostico::variacion($manoDeObra['variacion']),
            );
        }

        if ($suben->count() > 1) {
            $acciones[] = $this->accion('Revisar los precios de venta de los productos que más usan estos materiales.');
        }

        return $this->pregunta(
            'costos_suben',
            'costos',
            implode(' ', $frases),
            columnas: [
                ['titulo' => 'Material', 'formato' => 'texto'],
                ['titulo' => 'Precio anterior', 'formato' => 'bs_exacto'],
                ['titulo' => 'Precio actual', 'formato' => 'bs_exacto'],
                ['titulo' => 'Aumento', 'formato' => 'variacion'],
                ['titulo' => 'Costo adicional', 'formato' => 'bs'],
            ],
            filas: $suben->take(self::TOP)->map(fn (array $m): array => [
                $m['material'], $m['precio_inicio'], $m['precio_fin'], $m['variacion_pct'], $m['impacto'],
            ])->all(),
            acciones: $acciones,
            base: $base,
            interpretacion: implode(' ', $interpretacion),
            cifras: $cifras,
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
            '%s con consumo registrado y hoja de costos. Importes en Bs.',
            $trabajos->count() === 1 ? '1 trabajo entregado' : $this->numero($trabajos->count(), 0).' trabajos entregados',
        );
        $interpretacion = sprintf(
            'Solo se comparan materiales: es lo único que se registra como consumo real. "Excedido" significa más de %s sobre lo previsto. Los importes están redondeados.',
            $this->pct($tolerancia * 100, 0),
        );

        if ($trabajos->isEmpty()) {
            return $this->pregunta('gasto_presupuesto', 'costos', 'No hay trabajos entregados con consumo y hoja de costos para comparar en el periodo.', base: $base, interpretacion: $interpretacion);
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
            '%s En trabajos comparables, el material registrado suma %s frente a %s previstos: %s. De %s, %s el umbral de exceso definido por el sistema.',
            $desvio > $tolerancia * 100 ? 'Sí.' : ($desvio < -$tolerancia * 100 ? 'No, costó menos.' : 'En general no.'),
            $this->bs($totalReal),
            $this->bs($totalPresupuestado),
            abs($desvio) < 0.05 ? 'lo mismo' : sprintf('%s %s', $this->pct(abs($desvio)), $desvio > 0 ? 'más' : 'menos'),
            $this->cantidad($trabajos->count(), 'trabajo', 'trabajos'),
            match ($pasados) {
                0 => 'ninguno superó',
                1 => '1 superó',
                default => $this->numero($pasados, 0).' superaron',
            },
        );

        $acciones = [];
        $peorProducto = $porProducto->first(fn (array $p): bool => $p['diferencia'] > 0 && ($p['desvio'] ?? 0) > $tolerancia * 100);
        if ($peorProducto) {
            $acciones[] = $this->accion(
                sprintf('Revisar la hoja de costos de %s: %s adicionales (%s).', $peorProducto['nombre'], $this->bs($peorProducto['diferencia']), RedaccionPronostico::variacion($peorProducto['desvio'])),
                $peorProducto['diferencia'],
                sprintf('Revisar hoja de %s', $peorProducto['nombre']),
                'desvio_presupuesto',
            );
        }

        $peorMaterial = $this->materialMasDesviado($usuario, $periodo, $tolerancia);
        if ($peorMaterial) {
            $acciones[] = $this->accion(
                sprintf('Revisar cortes, sobrantes y cantidades previstas de %s: consumo %s mayor al previsto.', $peorMaterial['nombre'], $this->pct($peorMaterial['desvio'])),
                $peorMaterial['diferencia'],
                sprintf('Revisar cortes y sobrantes de %s', $peorMaterial['nombre']),
                'exceso_material',
            );
        }

        return $this->pregunta(
            'gasto_presupuesto',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Producto', 'formato' => 'texto'],
                ['titulo' => 'Trabajos / excedidos', 'formato' => 'texto'],
                ['titulo' => 'Previsto', 'formato' => 'bs'],
                ['titulo' => 'Registrado', 'formato' => 'bs'],
                ['titulo' => 'Diferencia', 'formato' => 'bs'],
                ['titulo' => 'Variación', 'formato' => 'variacion'],
            ],
            filas: $porProducto->take(self::TOP)->map(fn (array $p): array => [
                $p['nombre'], $this->numero($p['trabajos'], 0).' / '.$this->numero($p['pasados'], 0), $p['presupuestado'], $p['real'], $p['diferencia'], $p['desvio'],
            ])->all(),
            acciones: $acciones,
            base: $base,
            aviso: 'Solo se compara el material: es lo único que se registra como consumo real.',
            interpretacion: $interpretacion,
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
        $base = sprintf('Consumo registrado en pedidos del periodo: %s en %s.', $this->bs($total), $this->cantidad($consumo['materiales_distintos'], 'material', 'materiales'));
        $interpretacion = '"Parte acumulada" suma la participación de los materiales hasta esa fila. El ahorro es un escenario, no un resultado garantizado.';

        if ($total <= 0) {
            return $this->pregunta('materiales_pesan', 'costos', 'No hay consumo de material registrado en el periodo.', base: $base, interpretacion: $interpretacion);
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

        $distintos = $this->numero($consumo['materiales_distintos'], 0);
        $respuesta = match (true) {
            $principales->count() === 1 => sprintf('1 de %s materiales concentra el %s del gasto registrado: %s.', $distintos, $this->pct($parte), $nombres[0]),
            $principales->count() <= 3 => sprintf('%s de %s materiales concentran el %s del gasto registrado: %s.', $principales->count(), $distintos, $this->pct($parte), $this->enumerar($nombres)),
            default => sprintf('%s de %s materiales concentran el %s del gasto registrado. Los tres principales son %s.', $principales->count(), $distintos, $this->pct($parte), $this->enumerar($nombres)),
        };
        $cuales = match (count($nombres)) {
            1 => 'el material principal',
            2 => 'los dos materiales principales',
            default => 'los tres materiales principales',
        };
        $ahorroBs = round($principales->take(3)->sum('costo') * $ahorro, 2);

        return $this->pregunta(
            'materiales_pesan',
            'costos',
            $respuesta,
            columnas: [
                ['titulo' => 'Material', 'formato' => 'texto'],
                ['titulo' => 'Gasto registrado (Bs)', 'formato' => 'bs'],
                ['titulo' => 'Parte del gasto', 'formato' => 'porcentaje'],
                ['titulo' => 'Parte acumulada', 'formato' => 'porcentaje'],
            ],
            filas: $principales->take(8)->map(fn (array $m): array => [$m['nombre'], $m['costo'], $m['parte'], $m['acumulado']])->all(),
            acciones: [$this->accion(
                sprintf(
                    'Concentrar la negociación en %s. Una rebaja de %s representaría un ahorro estimado de %s si se mantuvieran las cantidades del periodo.',
                    $cuales,
                    $this->pct($ahorro * 100, 0),
                    $this->bs($ahorroBs),
                ),
                $ahorroBs,
                sprintf('Evaluar rebaja de %s en %s', $this->pct($ahorro * 100, 0), str_replace('los ', '', $cuales)),
                'ahorro',
            )],
            base: $base,
            interpretacion: $interpretacion,
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
        $interpretacion = 'Resultado bruto = ingreso sin IVA − costo de la hoja, antes de impuestos. Se basa en la hoja de costos: no es la ganancia neta ni un resultado medido con costos reales.';

        if ($lineas->isEmpty()) {
            return $this->pregunta('productos_rentables', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: 'Ventas del periodo con hoja de costos.', interpretacion: $interpretacion);
        }

        $minimo = (int) config('bi.preguntas.minimo_ventas');
        $porGanancia = $lineas->sortByDesc('ganancia')->values();
        $primero = $porGanancia->first();
        $mejorMargen = $lineas->where('ventas', '>=', $minimo)->sortByDesc('margen')->first();
        $margenTotal = $this->margen((float) $lineas->sum('ingreso'), (float) $lineas->sum('costo'));

        $respuesta = sprintf('%s aporta el mayor resultado bruto calculado: %s, con margen de %s.', $primero['nombre'], $this->bs($primero['ganancia']), $this->pct($primero['margen']));

        if ($mejorMargen && $mejorMargen['nombre'] !== $primero['nombre']) {
            $respuesta .= sprintf(' El mayor margen lo tiene %s (%s), aunque aporta menos porque tiene menos ventas.', $mejorMargen['nombre'], $this->pct($mejorMargen['margen']));
        } elseif ($mejorMargen) {
            $respuesta .= ' También presenta el mayor margen entre los productos analizados.';
        }

        $acciones = [];
        // De los que más se venden (por ingreso, que es lo que la acción
        // afirma), el de margen más bajo: donde un ajuste de precio rinde más.
        $flojo = $lineas->sortByDesc('ingreso')->take(10)->where('ventas', '>=', $minimo)
            ->filter(fn (array $p): bool => $margenTotal !== null && $p['margen'] < $margenTotal - 5)
            ->sortBy('margen')
            ->first();
        if ($flojo) {
            $impacto = round($flojo['ingreso'] * ($margenTotal - $flojo['margen']) / 100, 2);
            $acciones[] = $this->accion(
                sprintf('Evaluar el precio de %s: ventas de %s y margen de %s, %s puntos por debajo del promedio. Llevarlo al promedio representaría %s adicionales bajo los supuestos del cálculo.', $flojo['nombre'], $this->bs($flojo['ingreso']), $this->pct($flojo['margen']), $this->numero($margenTotal - $flojo['margen'], 1), $this->bs($impacto)),
                $impacto,
                sprintf('Evaluar precio de %s', $flojo['nombre']),
                'mejora',
            );
        }

        if ($mejorMargen && $mejorMargen['nombre'] !== $primero['nombre']) {
            $acciones[] = $this->accion(sprintf('Ofrecer %s con mayor frecuencia en las propuestas: tiene un margen de %s.', $mejorMargen['nombre'], $this->pct($mejorMargen['margen'])));
        }

        $filas = $this->filasConMencionados($porGanancia, [$primero, $mejorMargen, $flojo]);

        return $this->pregunta(
            'productos_rentables',
            'ganancias',
            $respuesta,
            columnas: $this->columnasRentabilidad('Producto'),
            filas: $filas,
            acciones: $acciones,
            base: 'Ventas del periodo con hoja de costos.'.$this->alcanceTabla($porGanancia->count(), count($filas), 'productos'),
            interpretacion: $interpretacion,
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

        $interpretacion = 'Un mayor resultado en bolivianos no implica necesariamente un mayor porcentaje de margen.';
        $cierre = ' El resultado usa el costo de la hoja, antes de impuestos.';

        if ($clientes->isEmpty()) {
            return $this->pregunta('clientes_rentables', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: 'Ventas del periodo con hoja de costos, agrupadas por cliente.', interpretacion: $interpretacion.$cierre);
        }

        $porGanancia = $clientes->sortByDesc('ganancia')->values();
        $primero = $porGanancia->first();
        $masCompra = $clientes->sortByDesc('ingreso')->first();
        $margenTotal = $this->margen((float) $clientes->sum('ingreso'), (float) $clientes->sum('costo'));

        $respuesta = sprintf('%s aporta el mayor resultado bruto calculado: %s, con margen de %s.', $primero['nombre'], $this->bs($primero['ganancia']), $this->pct($primero['margen']));

        if ($masCompra['nombre'] !== $primero['nombre']) {
            $puesto = $porGanancia->search(fn (array $c): bool => $c['nombre'] === $masCompra['nombre']) + 1;
            $respuesta .= sprintf(' El cliente con más compras, %s, ocupa el %s.º lugar en resultado: su margen es %s.', $masCompra['nombre'], $puesto, $this->pct($masCompra['margen']));
        }

        $acciones = [$this->accion(sprintf('Dar seguimiento postventa a %s y cuidar el cumplimiento de sus entregas, considerando su aporte al resultado.', $primero['nombre']))];

        $flojo = $clientes->sortByDesc('ingreso')->take(self::TOP)
            ->filter(fn (array $c): bool => $margenTotal !== null && $c['margen'] < $margenTotal - 5)
            ->sortBy('margen')
            ->first();
        if ($flojo) {
            $impacto = round($flojo['ingreso'] * ($margenTotal - $flojo['margen']) / 100, 2);
            array_unshift($acciones, $this->accion(
                sprintf('Revisar las condiciones comerciales de %s: compras de %s y margen de %s, %s puntos por debajo del promedio. Llevarlo al promedio representaría %s adicionales bajo los supuestos del cálculo.', $flojo['nombre'], $this->bs($flojo['ingreso']), $this->pct($flojo['margen']), $this->numero($margenTotal - $flojo['margen'], 1), $this->bs($impacto)),
                $impacto,
                sprintf('Revisar condiciones comerciales de %s', $flojo['nombre']),
                'mejora',
            ));
        }

        // Un ejemplo de la tabla: el de mejor margen, si no es el primero.
        $ejemplo = $porGanancia->take(self::TOP)->sortByDesc('margen')->first();
        if ($ejemplo['nombre'] !== $primero['nombre'] && $ejemplo['margen'] > $primero['margen']) {
            $interpretacion .= sprintf(' Por ejemplo, %s tiene un margen de %s.', $ejemplo['nombre'], $this->pct($ejemplo['margen']));
        }

        $filas = $this->filasConMencionados($porGanancia, [$primero, $masCompra, $flojo]);

        return $this->pregunta(
            'clientes_rentables',
            'ganancias',
            $respuesta,
            columnas: $this->columnasRentabilidad('Cliente'),
            filas: $filas,
            acciones: $acciones,
            base: 'Ventas del periodo con hoja de costos, agrupadas por cliente.'.$this->alcanceTabla($porGanancia->count(), count($filas), 'clientes'),
            interpretacion: $interpretacion.$cierre,
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

        $base = sprintf('%s del periodo. Precio sugerido = costo ajustado × (1 + margen del tipo de proyecto), según el motor del sistema.', $this->cantidad($ventas, 'venta', 'ventas'));
        $interpretacion = 'El promedio actual no determina por sí solo el descuento óptimo. La diferencia de margen entre los dos grupos no demuestra que se deba únicamente al descuento. No sumar diferencias sin revisar posibles superposiciones.';

        if ($ventas === 0) {
            return $this->pregunta('descuentos', 'ganancias', 'No hay ventas en el periodo.', base: $base, interpretacion: $interpretacion);
        }

        $frases = [$conDescuento === 0
            ? 'No se otorgaron descuentos en el periodo.'
            : sprintf('Se otorgaron %s en descuentos en %s de %s ventas, con descuento promedio de %s.', $this->bs($descontado), $this->numero($conDescuento, 0), $this->numero($ventas, 0), $this->pct($promedio))];

        if ($margenCon !== null && $margenSin !== null) {
            $frases[] = sprintf('El margen fue %s en ventas con descuento y %s en las demás.', $this->pct($margenCon), $this->pct($margenSin));
        }

        $lineas = $manual['lineas_debajo'] === 1 ? '1 línea' : $this->numero($manual['lineas_debajo'], 0).' líneas';
        if ($manual['lineas_debajo'] > 0) {
            $frases[] = sprintf('Además, %s con precio manual %s %s por debajo del sugerido.', $lineas, $manual['lineas_debajo'] === 1 ? 'quedó' : 'quedaron', $this->bs($manual['debajo']));
        }

        $acciones = [];
        if ($conDescuento > 0 && $margenCon !== null && $margenSin !== null && $margenSin - $margenCon >= 2) {
            $exceso = $this->descuentoSobreTope($usuario, $periodo, $promedio / 100);
            $acciones[] = $this->accion(
                sprintf('Evaluar un tope de descuento de %s y aprobación para superarlo: los descuentos por encima de ese valor suman %s.', $this->pct($promedio), $this->bs($exceso)),
                $exceso > 0 ? $exceso : null,
                sprintf('Evaluar tope de descuento de %s', $this->pct($promedio)),
                'sobre_tope',
            );
        }

        if ($manual['lineas_debajo'] > 0) {
            $acciones[] = $this->accion(
                $manual['lineas_debajo'] === 1
                    ? sprintf('Revisar la línea con precio manual y su diferencia de %s.', $this->bs($manual['debajo']))
                    : sprintf('Revisar las %s de precios manuales y su diferencia de %s.', $lineas, $this->bs($manual['debajo'])),
                $manual['debajo'],
                'Revisar precios manuales',
                'bajo_sugerido',
            );
        }

        $nombres = $this->nombresDeVendedores($porVendedor->pluck('id'));

        return $this->pregunta(
            'descuentos',
            'ganancias',
            implode(' ', $frases),
            columnas: [
                ['titulo' => 'Vendedor', 'formato' => 'texto'],
                ['titulo' => 'Ventas con descuento', 'formato' => 'cantidad'],
                ['titulo' => 'Descontado (Bs)', 'formato' => 'bs'],
                ['titulo' => 'Descuento medio', 'formato' => 'porcentaje'],
                ['titulo' => 'Margen', 'formato' => 'porcentaje'],
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
            interpretacion: $interpretacion,
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
        $base = 'Ventas con hoja de costos. Estos umbrales usan el costo como base y no son directamente comparables con el margen bruto sobre ingresos de las preguntas 5 y 6.';
        $interpretacion = sprintf(
            'Rojo no significa necesariamente pérdida: es la categoría del sistema para rentabilidad calculada después de impuestos menor al %s del costo. Amarillo corresponde a entre %s y %s.',
            $this->pct($semaforo['umbral_amarillo'] * 100, 0),
            $this->pct($semaforo['umbral_amarillo'] * 100, 0),
            $this->pct($semaforo['umbral_verde'] * 100, 0),
        );

        $ventas = (int) $porVendedor->sum('ventas');
        if ($ventas === 0) {
            return $this->pregunta('margen_bajo', 'ganancias', 'No hay ventas con hoja de costos en el periodo.', base: $base, interpretacion: $interpretacion);
        }

        $amarillas = (int) $porVendedor->sum('amarillas');
        $rojas = (int) $porVendedor->sum('rojas');
        $bajas = $amarillas + $rojas;
        $bsRojo = round((float) $porVendedor->sum('bs_rojo'), 2);

        $partes = array_filter([
            $amarillas > 0 ? sprintf('%s en amarillo, por %s', $this->numero($amarillas, 0), $this->bs((float) $porVendedor->sum('bs_amarillo'))) : null,
            $rojas > 0 ? sprintf('%s en rojo, por %s', $this->numero($rojas, 0), $this->bs($bsRojo)) : null,
        ]);
        $respuesta = $bajas === 0
            ? sprintf('Ninguna: las %s ventas del periodo quedaron en verde.', $this->numero($ventas, 0))
            : sprintf(
                '%s de %s ventas (%s) %s con margen bajo: %s.',
                $this->numero($bajas, 0),
                $this->numero($ventas, 0),
                $this->pct($bajas / $ventas * 100),
                $bajas === 1 ? 'quedó' : 'quedaron',
                implode(', y ', $partes),
            );

        if ($rojas > 0) {
            $interpretacion .= sprintf(' Los %s son ventas en rojo, no una pérdida por ese importe.', $this->bs($bsRojo));
        }

        $nombres = $this->nombresDeVendedores($porVendedor->pluck('id'));
        $filas = $porVendedor
            ->filter(fn (object $v): bool => (int) $v->amarillas + (int) $v->rojas > 0)
            ->sortByDesc(fn (object $v): array => [(int) $v->rojas, (int) $v->amarillas])
            ->values();

        $acciones = [];
        if ($rojas > 0) {
            $acciones[] = $this->accion(
                'Solicitar revisión de gerencia antes de aceptar ventas clasificadas en rojo.',
                $bsRojo,
                'Revisar aprobación de ventas en rojo',
                'margen_bajo',
            );
        }
        if ($filas->isNotEmpty()) {
            $v = $filas->first();
            $cuantas = (int) $v->amarillas + (int) $v->rojas;
            $acciones[] = $this->accion(sprintf('Revisar con %s %s clasificadas con margen bajo.', $nombres($v->id), $cuantas === 1 ? 'la venta' : 'las '.$this->numero($cuantas, 0).' ventas'));
        }

        return $this->pregunta(
            'margen_bajo',
            'ganancias',
            $respuesta,
            columnas: [
                ['titulo' => 'Vendedor', 'formato' => 'texto'],
                ['titulo' => 'Ventas', 'formato' => 'cantidad'],
                ['titulo' => 'Amarillo', 'formato' => 'cantidad'],
                ['titulo' => 'Rojo', 'formato' => 'cantidad'],
                ['titulo' => 'Vendido con margen bajo (Bs)', 'formato' => 'bs'],
            ],
            filas: $filas->take(self::TOP)->map(fn (object $v): array => [
                $nombres($v->id), (int) $v->ventas, (int) $v->amarillas, (int) $v->rojas, round((float) $v->bs_amarillo + (float) $v->bs_rojo, 2),
            ])->all(),
            acciones: $acciones,
            base: $base,
            interpretacion: $interpretacion,
            // Para la portada del informe.
            cifras: ['ventas' => $ventas, 'rojas' => $rojas, 'vendido_rojo' => $bsRojo],
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
        $base = 'Ventas del periodo con hoja de costos. Cambia solo el precio de los insumos de tipo material; la mano de obra, la impresión y los servicios se mantienen constantes.';
        $interpretacion = 'Es una simulación: no es una pérdida ya ocurrida. Los ajustes deben revisarse antes de aplicarlos.';

        if ($ingreso <= 0 || $costoBase <= 0) {
            return $this->pregunta('materia_prima', 'escenarios', 'No hay ventas con hoja de costos en el periodo para simular.', base: $base, interpretacion: $interpretacion);
        }

        $parte = (float) $lineas->sum('material_base') / $costoBase;
        $margenHoy = $this->margen($ingreso, $costo);
        $escenario = fn (float $subida): array => [
            'margen' => $this->margen($ingreso, $costo * (1 + $parte * $subida / 100)),
            'perdida' => round($costo * $parte * $subida / 100, 2),
            'subir_precio' => round($parte * $subida, 1),
        ];

        $filas = [['Situación actual', $margenHoy, 0.0, 0.0]];
        foreach (config('bi.preguntas.escenarios_materia_prima') as $subida) {
            $e = $escenario((float) $subida);
            $filas[] = [sprintf('Materiales +%s %%', $subida), $e['margen'], $e['perdida'], $e['subir_precio']];
        }

        $mensual = $costos['indice']['variacion_mensual'] ?? null;
        $meses = (int) config('bi.meses_proyeccion');
        if ($mensual !== null && $mensual > 0) {
            $probable = ((1 + $mensual / 100) ** $meses - 1) * 100;
            $e = $escenario($probable);
            $filas[] = [sprintf('A %d meses, si continúa %s mensual', $meses, RedaccionPronostico::variacion($mensual)), $e['margen'], $e['perdida'], $e['subir_precio']];
        }

        $diez = $escenario(10);
        $expuestos = $lineas
            ->where('ventas', '>=', (int) config('bi.preguntas.minimo_ventas'))
            ->filter(fn (array $p): bool => $p['costo_base'] > 0)
            ->sortByDesc(fn (array $p): float => $p['material_base'] / $p['costo_base'])
            ->take(2)
            ->values()
            ->map(fn (array $p, int $i): string => sprintf(
                $i === 0 ? '%s (materiales: %s del costo)' : '%s (%s)',
                $p['nombre'],
                $this->pct($p['material_base'] / $p['costo_base'] * 100, 0),
            ))
            ->all();

        $respuesta = sprintf(
            'Con un aumento de 10 %% en materiales, manteniendo los demás supuestos, el margen bruto calculado bajaría de %s a %s. El resultado sería %s menor. Para mantener el margen, el ajuste de precios estimado es de %s.',
            $this->pct($margenHoy),
            $this->pct($diez['margen']),
            $this->bs($diez['perdida']),
            $this->pct($diez['subir_precio']),
        );

        return $this->pregunta(
            'materia_prima',
            'escenarios',
            $respuesta,
            columnas: [
                ['titulo' => 'Escenario', 'formato' => 'texto'],
                ['titulo' => 'Margen bruto', 'formato' => 'porcentaje'],
                ['titulo' => 'Reducción estimada (Bs)', 'formato' => 'bs'],
                ['titulo' => 'Ajuste de precios estimado', 'formato' => 'porcentaje'],
            ],
            filas: $filas,
            // Sin "dinero en juego": es una hipótesis, no algo que ya pasó, y
            // no debe ganarle en "Acciones" a lo que sí se midió.
            acciones: [$this->accion(
                'Si se confirma un aumento, evaluar el ajuste de precios calculado y su efecto comercial.'
                    .($expuestos !== [] ? ' Revisar especialmente '.$this->enumerar($expuestos).'.' : ''),
            )],
            base: $base,
            aviso: sprintf('Hoy los materiales representan el %s del costo de lo vendido.', $this->pct($parte * 100)),
            interpretacion: $interpretacion.sprintf(' Los materiales representan el %s del costo de lo vendido.', $this->pct($parte * 100)),
            // Para el cuadro "Escenarios" de la pregunta 11.
            cifras: ['subida' => 10, 'margen_hoy' => $margenHoy, 'margen' => $diez['margen'], 'reduccion' => $diez['perdida']],
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

        $base = 'Pedidos del periodo, sin los cancelados. Este alcance puede diferir de otros reportes que incluyan todas las fechas.';
        $interpretacion = 'La antigüedad se cuenta desde la fecha del pedido, no desde el vencimiento: un pedido antiguo no necesariamente está atrasado. Los porcentajes pueden no sumar 100 % por redondeo.';

        if ($pedidos->isEmpty()) {
            return $this->pregunta('cobranza', 'escenarios', 'No hay pedidos en el periodo.', base: $base, interpretacion: $interpretacion);
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
        $dias = (int) end($tramos);
        $respuesta = $saldo <= 0
            ? sprintf('Se cobró todo: %s del periodo no tienen saldo pendiente.', $pedidos->count() === 1 ? 'el pedido' : 'los '.$this->numero($pedidos->count(), 0).' pedidos')
            : sprintf(
                'Se cobró el %s del importe analizado. Quedan %s en %s.%s',
                $this->pct(($vendido - $saldo) / $vendido * 100),
                $this->bs($saldo),
                $this->cantidad($pendientes->count(), 'pedido', 'pedidos'),
                $viejos[2] > 0 ? sprintf(' De ese saldo, %s corresponden a pedidos con más de %d días desde su fecha.', $this->bs($viejos[2]), $dias) : '',
            );

        $acciones = [];
        if ($viejos[2] > 0) {
            $acciones[] = $this->accion(
                sprintf('Revisar %s con más de %d días (%s) según los plazos acordados.', $viejos[1] === 1 ? 'el pedido' : 'los '.$this->numero($viejos[1], 0).' pedidos', $dias, $this->bs($viejos[2])),
                $viejos[2],
                sprintf('Cobrar pedidos con más de %d días', $dias),
                'saldo_antiguo',
            );
        }

        $deudor = $pendientes->groupBy('cliente')->map(fn (Collection $g): float => (float) $g->sum('saldo'))->sortDesc();
        if ($deudor->isNotEmpty()) {
            $acciones[] = $this->accion(sprintf('Dar seguimiento a %s, con %s pendientes.', $deudor->keys()->first(), $this->bs($deudor->first())));
        }

        $sinPago = $pedidos->where('entregado_sin_pago', true)->count();
        if ($sinPago > 0) {
            $acciones[] = $this->accion(sprintf('Evaluar anticipos: %s sin pago.', $sinPago === 1 ? '1 pedido se entregó' : $this->numero($sinPago, 0).' pedidos se entregaron'));
        }

        return $this->pregunta(
            'cobranza',
            'escenarios',
            $respuesta,
            columnas: [
                ['titulo' => 'Antigüedad desde el pedido', 'formato' => 'texto'],
                ['titulo' => 'Pedidos', 'formato' => 'cantidad'],
                ['titulo' => 'Saldo por cobrar (Bs)', 'formato' => 'bs'],
                ['titulo' => 'Parte del saldo', 'formato' => 'porcentaje'],
            ],
            filas: $saldo > 0 ? $filas : [],
            acciones: $acciones,
            base: $base,
            interpretacion: $interpretacion,
            // Para el cuadro "Cobros" de la pregunta 11 y la portada del
            // informe. `pedidos` son los que tienen saldo, no todos.
            cifras: [
                'por_cobrar' => round($saldo, 2),
                'pedidos' => $pendientes->count(),
                'cobrado' => $vendido > 0 ? round(($vendido - $saldo) / $vendido * 100, 1) : null,
                'antiguo' => (float) $viejos[2],
                'dias' => (int) end($tramos),
            ],
        );
    }

    /* -----------------------------------------------------------------
     * Decisiones
     * -------------------------------------------------------------- */

    /**
     * 11. Las acciones de las demás preguntas, ordenadas por el dinero que
     * mueven (`priorizadas`). No hay recomendaciones con cifras propias: cada
     * una repite la suya, dice qué representa ese monto y de qué pregunta sale
     * (`origen` y `numero`), para poder ir a los datos que la originan. Su
     * única acción propia dice por dónde empezar, según los frentes.
     *
     * @param  list<array<string, mixed>>  $preguntas
     * @return array<string, mixed>
     */
    private function decisiones(array $preguntas): array
    {
        $acciones = collect($preguntas)
            ->flatMap(fn (array $p, int $indice): array => array_map(
                fn (array $a): array => $a + ['origen' => $p['clave'], 'numero' => $indice + 1],
                $p['acciones'],
            ))
            ->filter(fn (array $a): bool => $a['impacto'] !== null && $a['impacto'] > 0)
            ->sortByDesc('impacto')
            ->take(8)
            ->values();

        $respuesta = match ($acciones->count()) {
            0 => 'Con los datos del periodo no surge ninguna acción con un monto involucrado.',
            1 => 'Se identifica 1 acción con monto involucrado. Su monto no debe interpretarse como ahorro garantizado.',
            default => sprintf('Se identifican %s acciones, ordenadas por monto involucrado. Los montos representan conceptos distintos y no deben sumarse ni interpretarse como ahorro garantizado.', $this->numero($acciones->count(), 0)),
        };

        // Por dónde empezar: los frentes de las acciones, en el orden en que
        // aparecen (el de más monto primero).
        $frentes = $acciones->pluck('frente')->filter()->unique()->take(3)->map(fn (string $f): string => self::FRENTES[$f])->values()->all();
        $recomendacion = $frentes === [] ? [] : [$this->accion(
            'Comenzar por '.array_shift($frentes).'.'.($frentes !== [] ? ' Después, '.$this->enumerar($frentes).'.' : ''),
        )];

        return [...$this->pregunta(
            'decisiones',
            'decisiones',
            $respuesta,
            columnas: [
                ['titulo' => 'Acción sugerida', 'formato' => 'texto'],
                ['titulo' => 'Qué representa', 'formato' => 'texto'],
                ['titulo' => 'Monto (Bs)', 'formato' => 'bs'],
                ['titulo' => 'Origen', 'formato' => 'texto'],
            ],
            filas: $acciones->map(fn (array $a): array => [
                $a['titulo'] ?? $a['texto'],
                $a['concepto'] ?? 'Monto involucrado',
                $a['impacto'],
                "Pregunta {$a['numero']}",
            ])->all(),
            acciones: $recomendacion,
            base: 'Cada acción proviene de una pregunta anterior y conserva su cifra. No se calcularon ahorros nuevos.',
            aviso: $acciones->isEmpty() ? null : 'Los montos tienen significados distintos y algunas acciones se superponen. No deben sumarse ni interpretarse como ahorro garantizado.',
            interpretacion: 'Algunas acciones se superponen. El orden por importe no reemplaza la evaluación de viabilidad, tiempo y efecto comercial. Las estimaciones dependen de mantener los supuestos del análisis.',
        ), 'priorizadas' => $acciones->all()];
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
     * `interpretacion`: los límites del cálculo, para "Cómo interpretar el
     * resultado" (PDF) y el plegable de la pantalla. `aviso` es la advertencia
     * corta que la pantalla muestra a la vista (solo algunas preguntas).
     * `cifras`: los números clave de la respuesta, sueltos, para los
     * indicadores de la pantalla y la portada del informe; vacío si no hay.
     *
     * @param  list<array{titulo: string, formato: string}>  $columnas
     * @param  list<list<mixed>>  $filas
     * @param  list<array{texto: string, impacto: float|null, titulo: string|null, concepto: string|null, tipo: string|null, frente: string|null}>  $acciones
     * @param  array<string, int|float|string|null>  $cifras
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
        ?string $interpretacion = null,
        array $cifras = [],
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
            'interpretacion' => $interpretacion,
            'cifras' => $cifras,
        ];
    }

    /**
     * Una acción sugerida. Las que tienen monto (`impacto`) pasan a la
     * pregunta 11, donde se muestran con su rótulo corto (`titulo`) y con
     * qué representa ese monto (`concepto`, una clave de MONTOS).
     *
     * @return array{texto: string, impacto: float|null, titulo: string|null, concepto: string|null, tipo: string|null, frente: string|null}
     */
    private function accion(string $texto, ?float $impacto = null, ?string $titulo = null, ?string $concepto = null): array
    {
        [$rotulo, $tipo, $frente] = $concepto !== null ? self::MONTOS[$concepto] : [null, null, null];

        return ['texto' => $texto, 'impacto' => $impacto, 'titulo' => $titulo, 'concepto' => $rotulo, 'tipo' => $tipo, 'frente' => $frente];
    }

    /**
     * Texto de cada pregunta y su rótulo corto (el del índice). Redacción
     * del informe que pasó el usuario (2026-10-29): "costo calculado" y
     * "resultado bruto" en vez de "costo real" y "ganancia", porque la mano
     * de obra es la presupuestada y el resultado sale de la hoja de costos.
     */
    public const PREGUNTAS = [
        'costo_producto' => ['¿Cuál es el costo calculado por producto?', 'Costo por producto'],
        'costos_suben' => ['¿Qué materiales están subiendo de precio?', 'Aumento de materiales'],
        'gasto_presupuesto' => ['¿El material utilizado costó más de lo previsto?', 'Gasto frente al presupuesto'],
        'materiales_pesan' => ['¿En qué materiales se concentra el gasto?', 'Materiales de mayor gasto'],
        'productos_rentables' => ['¿Qué productos aportan más al resultado bruto?', 'Productos con mayor margen'],
        'clientes_rentables' => ['¿Qué clientes aportan más al resultado bruto?', 'Clientes con mayor aporte'],
        'descuentos' => ['¿Cómo influyen los descuentos y los precios manuales?', 'Descuentos y precios manuales'],
        'margen_bajo' => ['¿Qué ventas quedaron clasificadas con margen bajo?', 'Ventas con margen bajo'],
        'materia_prima' => ['¿Qué cambiaría si aumenta el precio de los materiales?', 'Si sube la materia prima'],
        'cobranza' => ['¿Cuánto se cobró y cuánto falta recibir?', 'Pagos pendientes'],
        'decisiones' => ['¿Qué acciones conviene revisar primero?', 'Acciones sugeridas'],
    ];

    /**
     * Filas de una tabla de rentabilidad: los TOP de más ganancia y, además,
     * cualquiera que la respuesta o "Qué hacer" nombre aunque quede más
     * abajo. Sin esto, una acción podía hablar de un producto que no
     * aparecía en la tabla (pedido del usuario, 2026-10-24).
     *
     * @param  Collection<int, array<string, mixed>>  $porGanancia  Ya ordenado por ganancia.
     * @param  list<array<string, mixed>|null>  $mencionados
     * @return list<list<mixed>>
     */
    private function filasConMencionados(Collection $porGanancia, array $mencionados): array
    {
        $nombres = collect($mencionados)->filter()->pluck('nombre');
        $visibles = $porGanancia->take(self::TOP)->pluck('nombre');

        return $porGanancia
            ->filter(fn (array $fila, int $puesto): bool => $puesto < self::TOP
                || ($nombres->contains($fila['nombre']) && ! $visibles->contains($fila['nombre'])))
            ->map(fn (array $fila): array => [$fila['nombre'], $fila['ventas'], $fila['ingreso'], $fila['ganancia'], $fila['margen']])
            ->values()
            ->all();
    }

    /**
     * @return list<array{titulo: string, formato: string}>
     */
    private function columnasRentabilidad(string $quien): array
    {
        return [
            ['titulo' => $quien, 'formato' => 'texto'],
            ['titulo' => 'Ventas', 'formato' => 'cantidad'],
            ['titulo' => 'Ingresos (Bs)', 'formato' => 'bs'],
            ['titulo' => 'Resultado bruto (Bs)', 'formato' => 'bs'],
            ['titulo' => 'Margen', 'formato' => 'porcentaje'],
        ];
    }

    /**
     * Qué filas muestra una tabla de rentabilidad: las de mayor resultado y,
     * si las hay, las que la respuesta o la acción nombran más abajo.
     */
    private function alcanceTabla(int $total, int $mostradas, string $quienes): string
    {
        if ($total <= self::TOP) {
            return '';
        }

        $extra = $mostradas - self::TOP;

        return sprintf(
            ' Se muestran los %d %s con mayor resultado%s.',
            self::TOP,
            $quienes,
            match (true) {
                $extra === 1 => ' y el señalado en la respuesta o la recomendación',
                $extra > 1 => ' y los señalados en la respuesta o la recomendación',
                default => '',
            },
        );
    }

    /** "1 pedido", "67 pedidos". */
    private function cantidad(int $numero, string $singular, string $plural): string
    {
        return $numero === 1 ? "1 {$singular}" : $this->numero($numero, 0)." {$plural}";
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
