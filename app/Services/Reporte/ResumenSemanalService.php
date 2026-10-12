<?php

namespace App\Services\Reporte;

use App\Ai\Agents\RedactorResumenSemanal;
use App\Ai\Excepciones\IaNoDisponible;
use App\Ai\Seguridad\ConsultaSoloLectura;
use App\Ai\Seguridad\MinimizadorDatos;
use App\Ai\Seguridad\VerificadorCifras;
use App\Ai\ServicioIa;
use App\Models\BiAnomalia;
use App\Models\BiResumen;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\FamiliaProducto;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalleMaterial;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\ResumenSemanalDisponible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Resumen semanal del BI: indicadores de la semana (lunes a domingo) contra
 * la semana anterior y contra la misma semana del año anterior, y su
 * lectura en lenguaje natural.
 *
 * Los números se calculan acá, en PHP y por la conexión de solo lectura
 * (`hechos`). La IA solo los redacta (RedactorResumenSemanal); el texto se
 * acepta únicamente si cada cifra que menciona sale de los hechos. Si la IA
 * no está disponible o el texto no pasa la verificación, se publica una
 * plantilla armada con reglas: el resumen nunca falta.
 */
class ResumenSemanalService
{
    private const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    /**
     * Variación (en %) a partir de la cual un indicador entra a la plantilla
     * como destacado o riesgo.
     */
    private const VARIACION_RELEVANTE = 10.0;

    public function __construct(private readonly ServicioIa $ia) {}

    /**
     * Lunes de la última semana completa (la que terminó el domingo).
     */
    public function ultimaSemanaCompleta(?Carbon $hoy = null): Carbon
    {
        $hoy = ($hoy ?? Carbon::now(config('ia.zona_horaria')))->copy()->startOfDay();

        return $hoy->startOfWeek(Carbon::MONDAY)->subWeek();
    }

    /**
     * Genera (o regenera) los resúmenes de una semana: el consolidado y uno
     * por cada sucursal activa que tuvo movimiento.
     *
     * @return Collection<int, BiResumen>
     */
    public function generarSemana(Carbon $inicio, ?User $solicitante = null, bool $notificar = true): Collection
    {
        $sucursales = Sucursal::query()->where('estado', 'ACTIVO')->orderBy('nombre')->get();

        $resumenes = collect([$this->generar($inicio, null, $solicitante)]);

        foreach ($sucursales as $sucursal) {
            $resumen = $this->generar($inicio, $sucursal, $solicitante);

            if ($resumen !== null) {
                $resumenes->push($resumen);
            }
        }

        $resumenes = $resumenes->filter()->values();

        if ($notificar) {
            $this->notificar($resumenes);
        }

        return $resumenes;
    }

    /**
     * Genera el resumen de una semana para la empresa (sucursal null) o una
     * sucursal. Devuelve null si la sucursal no tuvo ningún movimiento.
     */
    public function generar(Carbon $inicio, ?Sucursal $sucursal, ?User $solicitante = null): ?BiResumen
    {
        $inicio = $inicio->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $hechos = ConsultaSoloLectura::ejecutar(null, fn () => $this->hechos($inicio, $sucursal));

        if ($sucursal !== null && ! $hechos['hubo_movimiento']) {
            return null;
        }

        [$contenido, $origen, $proveedor, $modelo] = $this->redactar($hechos, $solicitante);

        // whereDate y no updateOrCreate: en sqlite la columna date guarda la
        // hora y la comparación como texto no encuentra la fila (duplicaba).
        $resumen = BiResumen::query()
            ->whereDate('semana_inicio', $inicio->toDateString())
            ->when($sucursal, fn ($q) => $q->where('sucursal_id', $sucursal->id), fn ($q) => $q->whereNull('sucursal_id'))
            ->first() ?? new BiResumen(['semana_inicio' => $inicio->toDateString(), 'sucursal_id' => $sucursal?->id]);

        $resumen->fill([
            'semana_fin' => $inicio->copy()->addDays(6)->toDateString(),
            'hechos' => $hechos,
            'contenido' => $contenido,
            'origen' => $origen,
            'proveedor' => $proveedor,
            'modelo' => $modelo,
            'generado_por' => $solicitante?->id,
        ])->save();

        return $resumen;
    }

    /* -----------------------------------------------------------------
     * Hechos
     * -------------------------------------------------------------- */

    /**
     * Ficha de hechos de la semana. Es lo que ve la IA y lo que se guarda.
     *
     * @return array<string, mixed>
     */
    public function hechos(Carbon $inicio, ?Sucursal $sucursal): array
    {
        $fin = $inicio->copy()->addDays(6)->endOfDay();
        $anioAnterior = [$inicio->copy()->subWeeks(52), $fin->copy()->subWeeks(52)];

        $actual = $this->metricas($inicio, $fin, $sucursal);

        // Las 4 semanas anteriores: la inmediata para "contra la semana
        // pasada" y su promedio como referencia estable (un pedido corporativo
        // grande hace saltar una semana sola cientos de por ciento).
        $previas = collect(range(1, 4))->map(fn (int $k) => $this->metricas(
            $inicio->copy()->subWeeks($k),
            $fin->copy()->subWeeks($k),
            $sucursal,
        ));
        $previa = $previas->first();

        // La comparación anual solo vale si el sistema ya tenía datos ese
        // año: si no, "0 el año pasado" sería un crecimiento infinito falso.
        $primeraCotizacion = $this->cotizaciones($sucursal)->min('fecha');
        $hayAnioAnterior = $primeraCotizacion !== null && Carbon::parse($primeraCotizacion)->lessThanOrEqualTo($anioAnterior[0]);
        $anual = $hayAnioAnterior ? $this->metricas($anioAnterior[0], $anioAnterior[1], $sucursal) : null;

        $definiciones = [
            'presupuestos_emitidos' => ['Presupuestos emitidos', 'cantidad', 'sube'],
            'monto_emitido' => ['Monto presupuestado', 'bs', 'sube'],
            'ventas_cerradas' => ['Ventas cerradas (pedidos)', 'cantidad', 'sube'],
            'monto_vendido' => ['Monto vendido', 'bs', 'sube'],
            'ticket_promedio' => ['Ticket promedio por presupuesto', 'bs', 'sube'],
            'rentabilidad_pct' => ['Rentabilidad presupuestada sobre costo', 'porcentaje', 'sube'],
            'cobranza' => ['Cobranza', 'bs', 'sube'],
            'entregas' => ['Pedidos entregados', 'cantidad', 'sube'],
            'entregas_a_tiempo_pct' => ['Entregas a tiempo', 'porcentaje', 'sube'],
            'costo_material' => ['Costo de material consumido', 'bs', 'baja'],
        ];

        $kpis = [];
        $i = 1;
        foreach ($definiciones as $clave => [$nombre, $unidad, $mejorSi]) {
            $valor = $actual[$clave];
            $prev = $previa[$clave];
            $anio = $anual[$clave] ?? null;
            $conDato = $previas->pluck($clave)->filter(fn ($v) => $v !== null);
            $promedio = $conDato->isNotEmpty() ? round((float) $conDato->avg(), $unidad === 'cantidad' ? 1 : 2) : null;

            $kpis[] = [
                'id' => 'k'.$i++,
                'clave' => $clave,
                'nombre' => $nombre,
                'unidad' => $unidad,
                'mejor_si' => $mejorSi,
                'valor' => $valor,
                'promedio_4_semanas' => $promedio,
                'semana_anterior' => $prev,
                'anio_anterior' => $anio,
                'var_promedio_pct' => $this->variacion($valor, $promedio),
                'var_semana_pct' => $this->variacion($valor, $prev),
                'var_anio_pct' => $this->variacion($valor, $anio),
            ];
        }

        $anomaliasAbiertas = BiAnomalia::query()
            ->where('estado', 'NUEVA')
            ->when($sucursal, fn ($q) => $q->where('sucursal_id', $sucursal->id))
            ->get(['tipo', 'severidad', 'titulo']);

        return [
            'semana' => [
                'inicio' => $inicio->toDateString(),
                'fin' => $fin->toDateString(),
                'numero' => (int) $inicio->isoWeek(),
                'etiqueta' => 'Semana '.$inicio->isoWeek().' · '.$inicio->translatedFormat('j M').' al '.$fin->translatedFormat('j M Y'),
            ],
            'alcance' => $sucursal ? "Sucursal {$sucursal->nombre}" : 'Toda la empresa',
            'hay_anio_anterior' => $hayAnioAnterior,
            'hubo_movimiento' => $actual['presupuestos_emitidos'] > 0 || $actual['ventas_cerradas'] > 0 || $actual['cobranza'] > 0,
            'kpis' => $kpis,
            'contexto' => [
                'familias_mas_cotizadas' => $actual['familias'],
                'vendedor_destacado' => $actual['vendedor_destacado'],
                'anomalias_abiertas' => $anomaliasAbiertas->count(),
                'anomalias_altas' => $anomaliasAbiertas->where('severidad', 'ALTA')->pluck('titulo')->take(3)->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metricas(Carbon $desde, Carbon $hasta, ?Sucursal $sucursal): array
    {
        $cotizaciones = $this->cotizaciones($sucursal)
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('empleado:id,nombres,paterno')
            ->get(['id', 'empleado_id', 'fecha', 'estado', 'total', 'costo_ajustado', 'utilidad_real']);

        $pedidos = Pedido::query()
            ->when($sucursal, fn (Builder $q) => $q->whereHas('cotizacion', fn ($c) => $c->where('sucursal_id', $sucursal->id)))
            ->where('estado', '!=', 'CANCELADO')
            ->whereBetween('fecha_pedido', [$desde, $hasta])
            ->get(['id', 'total']);

        $entregados = Pedido::query()
            ->when($sucursal, fn (Builder $q) => $q->whereHas('cotizacion', fn ($c) => $c->where('sucursal_id', $sucursal->id)))
            ->where('estado', 'ENTREGADO')
            ->whereBetween('fecha_entrega_real', [$desde, $hasta])
            ->get(['id', 'fecha_entrega_estimada', 'fecha_entrega_real']);

        $cobranza = (float) Pago::query()
            ->when($sucursal, fn (Builder $q) => $q->whereHas('pedido.cotizacion', fn ($c) => $c->where('sucursal_id', $sucursal->id)))
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->sum('monto');

        $costoMaterial = (float) PedidoDetalleMaterial::query()
            ->when($sucursal, fn (Builder $q) => $q->whereHas('pedidoDetalle.pedido.cotizacion', fn ($c) => $c->where('sucursal_id', $sucursal->id)))
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('costo_real');

        $conCosto = $cotizaciones->filter(fn ($c) => (float) $c->costo_ajustado > 0);
        $costo = (float) $conCosto->sum('costo_ajustado');

        $aTiempo = $entregados->filter(
            fn ($p) => $p->fecha_entrega_estimada && $p->fecha_entrega_real->lessThanOrEqualTo($p->fecha_entrega_estimada)
        )->count();

        return [
            'presupuestos_emitidos' => $cotizaciones->count(),
            'monto_emitido' => round((float) $cotizaciones->sum('total'), 2),
            'ventas_cerradas' => $pedidos->count(),
            'monto_vendido' => round((float) $pedidos->sum('total'), 2),
            'ticket_promedio' => $cotizaciones->isNotEmpty() ? round((float) $cotizaciones->avg('total'), 2) : 0.0,
            'rentabilidad_pct' => $costo > 0 ? round((float) $conCosto->sum('utilidad_real') / $costo * 100, 1) : null,
            'cobranza' => round($cobranza, 2),
            'entregas' => $entregados->count(),
            'entregas_a_tiempo_pct' => $entregados->isNotEmpty() ? round($aTiempo / $entregados->count() * 100, 1) : null,
            'costo_material' => round($costoMaterial, 2),
            'familias' => $this->familias($cotizaciones->pluck('id')),
            'vendedor_destacado' => $this->vendedorDestacado($cotizaciones),
        ];
    }

    private function cotizaciones(?Sucursal $sucursal): Builder
    {
        return Cotizacion::query()->when($sucursal, fn (Builder $q) => $q->where('sucursal_id', $sucursal->id));
    }

    /**
     * Las tres familias con más monto presupuestado en la semana.
     *
     * @param  Collection<int, int>  $cotizacionIds
     * @return list<array{familia: string, monto: float, participacion_pct: float}>
     */
    private function familias(Collection $cotizacionIds): array
    {
        if ($cotizacionIds->isEmpty()) {
            return [];
        }

        $lineas = CotizacionDetalle::query()
            ->whereIn('cotizacion_id', $cotizacionIds)
            ->with('producto:id,categoria_producto_id', 'producto.categoriaProducto:id,familia_producto_id', 'producto.categoriaProducto.familia:id,nombre')
            ->get(['id', 'producto_id', 'subtotal']);

        $total = (float) $lineas->sum('subtotal');

        if ($total <= 0) {
            return [];
        }

        return $lineas
            ->groupBy(fn ($l) => $l->producto?->categoriaProducto?->familia?->nombre ?? FamiliaProducto::SIN_FAMILIA)
            ->map(fn ($g, string $familia) => [
                'familia' => $familia,
                'monto' => round((float) $g->sum('subtotal'), 2),
                'participacion_pct' => round((float) $g->sum('subtotal') / $total * 100, 1),
            ])
            ->sortByDesc('monto')
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Cotizacion>  $cotizaciones
     * @return array{vendedor: string, monto: float, presupuestos: int}|null
     */
    private function vendedorDestacado(Collection $cotizaciones): ?array
    {
        $mejor = $cotizaciones->filter(fn ($c) => $c->empleado)
            ->groupBy('empleado_id')
            ->sortByDesc(fn ($g) => (float) $g->sum('total'))
            ->first();

        if ($mejor === null) {
            return null;
        }

        $empleado = $mejor->first()->empleado;

        return [
            'vendedor' => trim($empleado->nombres.' '.$empleado->paterno),
            'monto' => round((float) $mejor->sum('total'), 2),
            'presupuestos' => $mejor->count(),
        ];
    }

    private function variacion(int|float|null $actual, int|float|null $base): ?float
    {
        if ($actual === null || $base === null || (float) $base == 0.0) {
            return null;
        }

        return round(($actual - $base) / abs($base) * 100, 1);
    }

    /* -----------------------------------------------------------------
     * Redacción
     * -------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $hechos
     * @return array{0: array<string, mixed>, 1: string, 2: string|null, 3: string|null}
     */
    private function redactar(array $hechos, ?User $solicitante): array
    {
        $permitidos = VerificadorCifras::permitidosDe($hechos);
        $prompt = "Ficha de hechos de la semana ({$hechos['alcance']}):\n".MinimizadorDatos::json($hechos);

        try {
            $respuesta = $this->ia->ejecutar(new RedactorResumenSemanal, $prompt, 'resumen_semanal', $solicitante);
        } catch (IaNoDisponible) {
            return [$this->plantilla($hechos), 'PLANTILLA', null, null];
        }

        $contenido = $this->normalizar($respuesta instanceof StructuredAgentResponse ? $respuesta->structured : [], $hechos);

        if ($contenido === null || VerificadorCifras::noEncontrados($this->textoDe($contenido), $permitidos) !== []) {
            return [$this->plantilla($hechos), 'PLANTILLA', null, null];
        }

        return [$contenido, 'IA', $respuesta->meta->provider, $respuesta->meta->model];
    }

    /**
     * Deja la respuesta de la IA en la forma exacta que espera la pantalla y
     * descarta ids de indicadores que no existen.
     *
     * @param  array<string, mixed>  $crudo
     * @param  array<string, mixed>  $hechos
     * @return array<string, mixed>|null
     */
    private function normalizar(array $crudo, array $hechos): ?array
    {
        if (blank($crudo['titular'] ?? null)) {
            return null;
        }

        $ids = collect($hechos['kpis'])->pluck('id');
        $kpis = fn ($lista) => collect((array) $lista)->filter(fn ($id) => $ids->contains($id))->values()->all();

        return [
            'titular' => trim((string) $crudo['titular']),
            'destacados' => collect((array) ($crudo['destacados'] ?? []))->take(4)
                ->map(fn ($d) => ['texto' => trim((string) ($d['texto'] ?? '')), 'kpis' => $kpis($d['kpis'] ?? [])])
                ->filter(fn ($d) => $d['texto'] !== '')->values()->all(),
            'riesgos' => collect((array) ($crudo['riesgos'] ?? []))->take(3)
                ->map(fn ($r) => [
                    'texto' => trim((string) ($r['texto'] ?? '')),
                    'severidad' => ($r['severidad'] ?? 'media') === 'alta' ? 'alta' : 'media',
                    'kpis' => $kpis($r['kpis'] ?? []),
                ])
                ->filter(fn ($r) => $r['texto'] !== '')->values()->all(),
            'recomendaciones' => collect((array) ($crudo['recomendaciones'] ?? []))->take(3)
                ->map(fn ($r) => ['accion' => trim((string) ($r['accion'] ?? '')), 'motivo' => trim((string) ($r['motivo'] ?? ''))])
                ->filter(fn ($r) => $r['accion'] !== '')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function textoDe(array $contenido): string
    {
        return collect([$contenido['titular']])
            ->merge(collect($contenido['destacados'])->pluck('texto'))
            ->merge(collect($contenido['riesgos'])->pluck('texto'))
            ->merge(collect($contenido['recomendaciones'])->flatMap(fn ($r) => [$r['accion'], $r['motivo']]))
            ->implode("\n");
    }

    /**
     * Resumen armado con reglas, sin IA: los indicadores que más se movieron
     * en la dirección buena son destacados; los que se movieron en la mala,
     * riesgos; cada riesgo trae su recomendación estándar.
     *
     * @param  array<string, mixed>  $hechos
     * @return array<string, mixed>
     */
    public function plantilla(array $hechos): array
    {
        $kpis = collect($hechos['kpis'])->keyBy('clave');
        $movidos = collect($hechos['kpis'])
            ->filter(fn ($k) => $k['var_promedio_pct'] !== null && abs($k['var_promedio_pct']) >= self::VARIACION_RELEVANTE);

        $esBueno = fn ($k) => ($k['var_promedio_pct'] > 0) === ($k['mejor_si'] === 'sube');
        $frente = ' frente al promedio de las 4 semanas anteriores';

        $destacados = $movidos->filter($esBueno)
            ->sortByDesc(fn ($k) => abs($k['var_promedio_pct']))
            ->take(3)
            ->map(fn ($k) => [
                'texto' => $k['nombre'].': '.$this->formatear($k['valor'], $k['unidad']).' ('.$this->signo($k['var_promedio_pct']).$frente.').',
                'kpis' => [$k['id']],
            ])->values();

        if ($destacados->isEmpty()) {
            $vendido = $kpis['monto_vendido'];
            $destacados->push([
                'texto' => 'Monto vendido de la semana: '.$this->formatear($vendido['valor'], 'bs').' en '.$kpis['ventas_cerradas']['valor'].' pedidos.',
                'kpis' => [$vendido['id'], $kpis['ventas_cerradas']['id']],
            ]);
        }

        $riesgos = $movidos->reject($esBueno)
            ->sortByDesc(fn ($k) => abs($k['var_promedio_pct']))
            ->take(3)
            ->map(fn ($k) => [
                'texto' => $k['nombre'].': '.$this->formatear($k['valor'], $k['unidad']).' ('.$this->signo($k['var_promedio_pct']).$frente.').',
                'severidad' => abs($k['var_promedio_pct']) >= 30 ? 'alta' : 'media',
                'kpis' => [$k['id']],
                'clave' => $k['clave'],
            ])->values();

        $recomendacionPorIndicador = [
            'presupuestos_emitidos' => ['Reactivar la prospección y el seguimiento de cotizaciones pendientes.', 'Se emitieron menos presupuestos que en las semanas anteriores.'],
            'monto_emitido' => ['Priorizar oportunidades de mayor ticket (letreros, exhibidores, campañas completas).', 'El monto presupuestado cayó.'],
            'ventas_cerradas' => ['Revisar las cotizaciones aprobadas que todavía no pasaron a pedido.', 'Se cerraron menos ventas.'],
            'monto_vendido' => ['Revisar las cotizaciones aprobadas que todavía no pasaron a pedido.', 'El monto vendido cayó.'],
            'ticket_promedio' => ['Ofrecer instalación y complementos en los presupuestos chicos.', 'El ticket promedio bajó.'],
            'rentabilidad_pct' => ['Revisar descuentos y el nivel de complejidad elegido en las hojas de costo.', 'La rentabilidad presupuestada bajó.'],
            'cobranza' => ['Revisar las cuentas por cobrar con saldo vencido.', 'Se cobró menos que en las semanas anteriores.'],
            'entregas' => ['Revisar la carga de producción por área.', 'Se entregaron menos pedidos.'],
            'entregas_a_tiempo_pct' => ['Revisar los pedidos atrasados y las etapas que más demoran.', 'Bajó el cumplimiento de entregas.'],
            'costo_material' => ['Comparar el consumo real con lo presupuestado en los pedidos de la semana.', 'Subió el costo de material consumido.'],
        ];

        $recomendaciones = $riesgos->map(fn ($r) => [
            'accion' => $recomendacionPorIndicador[$r['clave']][0],
            'motivo' => $recomendacionPorIndicador[$r['clave']][1],
        ])->unique('accion')->values();

        if ($hechos['contexto']['anomalias_abiertas'] > 0) {
            $recomendaciones->push([
                'accion' => 'Revisar las anomalías abiertas en la pestaña Alertas.',
                'motivo' => 'Hay '.$hechos['contexto']['anomalias_abiertas'].' anomalías sin revisar.',
            ]);
        }

        if ($recomendaciones->isEmpty()) {
            $recomendaciones->push([
                'accion' => 'Mantener el seguimiento de las cotizaciones pendientes.',
                'motivo' => 'Ningún indicador se movió más de '.(int) self::VARIACION_RELEVANTE.' %'.$frente.'.',
            ]);
        }

        $vendido = $kpis['monto_vendido'];
        $titular = $vendido['var_promedio_pct'] === null
            ? 'Semana con '.$this->formatear($vendido['valor'], 'bs').' vendidos.'
            : 'Semana con '.$this->formatear($vendido['valor'], 'bs').' vendidos, '
                .$this->pct(abs($vendido['var_promedio_pct'])).($vendido['var_promedio_pct'] >= 0 ? ' por encima' : ' por debajo')
                .' del promedio de las 4 semanas anteriores.';

        return [
            'titular' => $titular,
            'destacados' => $destacados->all(),
            'riesgos' => $riesgos->map(fn ($r) => collect($r)->except('clave')->all())->all(),
            'recomendaciones' => $recomendaciones->take(3)->all(),
        ];
    }

    /**
     * Avisa por la campanita a quien puede leer cada resumen: el consolidado
     * a quien ve toda la empresa; el de una sucursal a quien la ve pero NO
     * ve todas (gerencia recibe un aviso por semana, no uno por sucursal).
     *
     * @param  Collection<int, BiResumen>  $resumenes
     */
    private function notificar(Collection $resumenes): void
    {
        $destinatarios = User::conPermiso('reportes.bi');

        foreach ($resumenes as $resumen) {
            $destinatarios
                ->filter(fn (User $u) => $resumen->sucursal_id === null
                    ? $u->veTodasLasSucursales()
                    : (! $u->veTodasLasSucursales() && $u->puedeVerSucursal($resumen->sucursal_id)))
                ->each(fn (User $u) => $u->notify(new ResumenSemanalDisponible($resumen)));
        }
    }

    private function formatear(int|float|null $valor, string $unidad): string
    {
        if ($valor === null) {
            return 'sin dato';
        }

        return match ($unidad) {
            'bs' => 'Bs '.number_format((float) $valor, 2, ',', '.'),
            'porcentaje' => $this->pct((float) $valor),
            default => number_format((float) $valor, 0, ',', '.'),
        };
    }

    private function pct(float $valor): string
    {
        return number_format($valor, 1, ',', '.').' %';
    }

    private function signo(float $valor): string
    {
        return ($valor > 0 ? '+' : '−').$this->pct(abs($valor));
    }
}
