<?php

namespace App\Services\Reporte;

use App\Ai\Agents\RedactorAnomalias;
use App\Ai\Excepciones\IaNoDisponible;
use App\Ai\Seguridad\ConsultaSoloLectura;
use App\Ai\Seguridad\MinimizadorDatos;
use App\Ai\Seguridad\VerificadorCifras;
use App\Ai\ServicioIa;
use App\Models\BiAnomalia;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\AnomaliaDetectada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Detección diaria de anomalías del negocio.
 *
 * La DETECCIÓN es estadística y reproducible (mismos datos, mismas
 * alertas); la IA solo reescribe el texto. Cuatro reglas, umbrales en
 * config/bi.php (`anomalias`):
 *
 * 1. Volumen de cotizaciones de la última semana completa, por sucursal y de
 *    toda la empresa, contra la mediana de las 8 anteriores: cola de Poisson
 *    (probabilidad de una semana así por azar) y un cambio mínimo del 50 %.
 * 2. Ventas recientes con rentabilidad por debajo del percentil 10 de los
 *    últimos 6 meses, o en ROJO.
 * 3. Vendedores que llevan más del doble de su ritmo habitual sin cotizar.
 * 4. Clientes del grupo que hace el 80 % de las ventas que llevan más del
 *    doble de su intervalo habitual sin comprar.
 *
 * Cada anomalía lleva una `huella` (tipo + entidad + periodo): la misma
 * situación no se vuelve a crear al día siguiente. Lee por la conexión de
 * solo lectura y escribe solo en `bi_anomalia`.
 */
class DeteccionAnomaliasService
{
    private const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    public function __construct(private readonly ServicioIa $ia) {}

    /**
     * Detecta, guarda las nuevas (redactadas por la IA si está disponible,
     * si no con su plantilla) y notifica las de prioridad alta.
     *
     * @return Collection<int, BiAnomalia> las anomalías NUEVAS de esta corrida
     */
    public function ejecutar(?Carbon $hoy = null, ?User $solicitante = null): Collection
    {
        $hoy = ($hoy ?? Carbon::now(config('ia.zona_horaria')))->copy()->startOfDay();

        $candidatas = collect(ConsultaSoloLectura::ejecutar(null, fn () => $this->detectar($hoy)));

        $existentes = BiAnomalia::query()->whereIn('huella', $candidatas->pluck('huella'))->pluck('huella');
        $nuevas = $candidatas->reject(fn (array $c) => $existentes->contains($c['huella']))->values();

        if ($nuevas->isEmpty()) {
            return collect();
        }

        $redacciones = $this->redactar($nuevas, $solicitante);

        $guardadas = $nuevas->map(function (array $c) use ($redacciones, $hoy): BiAnomalia {
            $ia = $redacciones[$c['huella']] ?? null;

            return BiAnomalia::create([
                'huella' => $c['huella'],
                'tipo' => $c['tipo'],
                'severidad' => $c['severidad'],
                'titulo' => $c['titulo'],
                'detalle' => $ia['detalle'] ?? $c['detalle'],
                'que_revisar' => $ia['que_revisar'] ?? $c['que_revisar'],
                'hechos' => $c['hechos'],
                'sucursal_id' => $c['sucursal_id'],
                'url' => $c['url'],
                'redaccion' => $ia ? 'IA' : 'PLANTILLA',
                'estado' => 'NUEVA',
                'detectada_en' => $hoy->toDateString(),
            ]);
        });

        $this->notificar($guardadas->where('severidad', 'ALTA'));

        return $guardadas;
    }

    /**
     * Solo la parte estadística: lista de anomalías candidatas, sin guardar.
     *
     * @return list<array<string, mixed>>
     */
    public function detectar(Carbon $hoy): array
    {
        return [
            ...$this->volumenCotizaciones($hoy),
            ...$this->margenesBajos($hoy),
            ...$this->vendedoresInactivos($hoy),
            ...$this->clientesEnRiesgo($hoy),
        ];
    }

    /* -----------------------------------------------------------------
     * 1. Volumen de cotizaciones
     * -------------------------------------------------------------- */

    /**
     * @return list<array<string, mixed>>
     */
    private function volumenCotizaciones(Carbon $hoy): array
    {
        $cfg = config('bi.anomalias');
        $finSemana = $hoy->copy()->startOfWeek(Carbon::MONDAY)->subDay()->endOfDay();
        $inicioSemana = $finSemana->copy()->startOfWeek(Carbon::MONDAY);
        $inicioHistoria = $inicioSemana->copy()->subWeeks($cfg['semanas_referencia']);

        $cotizaciones = Cotizacion::query()
            ->whereBetween('fecha', [$inicioHistoria, $finSemana])
            ->get(['id', 'fecha', 'sucursal_id']);

        // Primera cotización de cada grupo: las semanas anteriores no cuentan
        // como "cero" (la sucursal no existía o el sistema no se usaba).
        $primeras = Cotizacion::query()
            ->selectRaw('sucursal_id, MIN(fecha) as primera')
            ->groupBy('sucursal_id')
            ->pluck('primera', 'sucursal_id');
        $primeraGlobal = $primeras->min();

        $semanas = collect(range($cfg['semanas_referencia'], 1))
            ->map(fn (int $k) => $inicioSemana->copy()->subWeeks($k));

        $grupos = $cotizaciones->groupBy('sucursal_id')->put('todas', $cotizaciones);
        $nombres = Sucursal::query()->pluck('nombre', 'id');

        $anomalias = [];

        foreach ($grupos as $clave => $grupo) {
            $primera = $clave === 'todas' ? $primeraGlobal : $primeras->get($clave);
            if ($primera === null) {
                continue;
            }

            $porSemana = $grupo->groupBy(fn ($c) => $c->fecha->copy()->startOfWeek(Carbon::MONDAY)->toDateString())->map->count();

            $historia = $semanas
                ->filter(fn (Carbon $s) => $s->copy()->endOfWeek()->toDateString() >= Carbon::parse($primera)->toDateString())
                ->map(fn (Carbon $s) => (int) $porSemana->get($s->toDateString(), 0))
                ->values()
                ->all();

            if (count($historia) < min($cfg['minimo_semanas'], $cfg['semanas_referencia'])) {
                continue;
            }

            $mediana = (float) Estadistica::mediana($historia);
            if ($mediana < $cfg['minimo_mediana']) {
                continue;
            }

            $actual = (int) $porSemana->get($inicioSemana->toDateString(), 0);
            $variacion = round(($actual - $mediana) / $mediana * 100, 1);
            $probabilidad = Estadistica::colaPoisson($actual, $mediana);

            if (abs($variacion) < $cfg['variacion_minima'] * 100 || $probabilidad >= $cfg['p_media']) {
                continue;
            }

            $cae = $actual < $mediana;
            $alcance = $clave === 'todas' ? 'toda la empresa' : 'la sucursal '.($nombres[$clave] ?? '—');
            $tipo = $cae ? 'caida_cotizaciones' : 'alza_cotizaciones';
            $rango = $inicioSemana->format('d/m').' al '.$finSemana->format('d/m/Y');
            $probabilidadPct = round($probabilidad * 100, 2);

            $anomalias[] = [
                'huella' => "{$tipo}:{$clave}:{$inicioSemana->toDateString()}",
                'tipo' => $tipo,
                // Una alza no es un problema: queda en MEDIA aunque sea
                // extrema, para que se mire (¿pico real o carga duplicada?).
                'severidad' => $cae && $probabilidad < $cfg['p_alta'] ? 'ALTA' : 'MEDIA',
                'titulo' => ($cae ? 'Caída' : 'Alza inusual').' de cotizaciones en '.$alcance.' ('.$this->pct($variacion).')',
                'detalle' => "La semana del {$rango} se emitieron {$actual} cotizaciones; lo habitual en las últimas "
                    .count($historia).' semanas es '.$this->num($mediana).' por semana. Una semana así por azar tiene una probabilidad de '
                    .$this->pct($probabilidadPct).'.',
                'que_revisar' => $cae
                    ? 'Revisar si hubo ausencias en ventas o campañas que terminaron, y dar seguimiento a las cotizaciones pendientes.'
                    : 'Confirmar que el pico es real (una campaña, una licitación) y que producción tiene capacidad para atenderlo.',
                'hechos' => [
                    'semana_inicio' => $inicioSemana->toDateString(),
                    'semana_fin' => $finSemana->toDateString(),
                    'cotizaciones' => $actual,
                    'mediana_semanal' => $mediana,
                    'semanas_comparadas' => count($historia),
                    'variacion_pct' => $variacion,
                    'probabilidad_pct' => $probabilidadPct,
                    'alcance' => $alcance,
                ],
                'sucursal_id' => $clave === 'todas' ? null : (int) $clave,
                'url' => route('cotizaciones.index'),
            ];
        }

        return $anomalias;
    }

    /* -----------------------------------------------------------------
     * 2. Margen muy bajo en una venta
     * -------------------------------------------------------------- */

    /**
     * @return list<array<string, mixed>>
     */
    private function margenesBajos(Carbon $hoy): array
    {
        $cfg = config('bi.anomalias');

        $ventas = Cotizacion::query()
            ->whereIn('estado', self::VENDIDOS)
            ->where('costo_ajustado', '>', 0)
            ->whereBetween('fecha', [$hoy->copy()->subDays(180)->startOfDay(), $hoy->copy()->endOfDay()])
            ->with('cliente:id,razon_social')
            ->get(['id', 'codigo_verificacion', 'cliente_id', 'sucursal_id', 'fecha', 'costo_ajustado', 'utilidad_real', 'estado_margen', 'subtotal', 'descuento']);

        $rentabilidad = fn ($c): float => (float) $c->utilidad_real / (float) $c->costo_ajustado * 100;

        $desde = $hoy->copy()->subDays($cfg['margen_dias'])->startOfDay();
        [$recientes, $referencia] = $ventas->partition(fn ($c) => $c->fecha->greaterThanOrEqualTo($desde));

        if ($referencia->count() < $cfg['margen_minimo_muestras']) {
            return [];
        }

        $p10 = Estadistica::percentil($referencia->map($rentabilidad)->values()->all(), $cfg['margen_percentil']);

        return $recientes
            ->filter(fn ($c) => $c->estado_margen === 'ROJO' || $rentabilidad($c) < $p10)
            ->sortBy($rentabilidad)
            ->take($cfg['margen_maximo_por_corrida'])
            ->map(function ($c) use ($rentabilidad, $p10): array {
                $r = round($rentabilidad($c), 1);
                $descuento = round((float) $c->descuento, 2);

                return [
                    'huella' => "margen_bajo:{$c->id}",
                    'tipo' => 'margen_bajo',
                    'severidad' => $c->estado_margen === 'ROJO' || $r < 0 ? 'ALTA' : 'MEDIA',
                    'titulo' => "Venta {$c->codigo_verificacion} con margen muy bajo ({$this->pct($r)})",
                    'detalle' => "La rentabilidad sobre costo es {$this->pct($r)}; el 90 % de las ventas de los últimos 6 meses supera "
                        .$this->pct(round($p10, 1)).'.'.($c->cliente ? ' Cliente: '.rtrim($c->cliente->razon_social, '.').'.' : ''),
                    'que_revisar' => $descuento > 0
                        ? 'Revisar el descuento aplicado (Bs '.$this->num($descuento).') y la hoja de costos antes de cotizar trabajos parecidos.'
                        : 'Revisar la hoja de costos y el nivel de complejidad elegido antes de cotizar trabajos parecidos.',
                    'hechos' => [
                        'codigo' => $c->codigo_verificacion,
                        'cliente' => $c->cliente?->razon_social,
                        'rentabilidad_pct' => $r,
                        'percentil_10_pct' => round($p10, 1),
                        'utilidad_real' => round((float) $c->utilidad_real, 2),
                        'costo_ajustado' => round((float) $c->costo_ajustado, 2),
                        'descuento' => $descuento,
                        'semaforo' => $c->estado_margen,
                    ],
                    'sucursal_id' => $c->sucursal_id,
                    'url' => route('cotizaciones.show', $c->id),
                ];
            })
            ->values()
            ->all();
    }

    /* -----------------------------------------------------------------
     * 3. Vendedor sin actividad
     * -------------------------------------------------------------- */

    /**
     * @return list<array<string, mixed>>
     */
    private function vendedoresInactivos(Carbon $hoy): array
    {
        $cfg = config('bi.anomalias');

        $vendedores = Empleado::query()
            ->where('estado', 'ACTIVO')
            ->where('cargo', 'Vendedor')
            ->get(['id', 'nombres', 'paterno', 'materno', 'sucursal_id']);

        if ($vendedores->isEmpty()) {
            return [];
        }

        $fechas = Cotizacion::query()
            ->whereIn('empleado_id', $vendedores->pluck('id'))
            ->where('fecha', '>=', $hoy->copy()->subYear()->startOfDay())
            ->where('fecha', '<=', $hoy->copy()->endOfDay())
            ->get(['empleado_id', 'fecha'])
            ->groupBy('empleado_id')
            ->map(fn ($g) => $g->map(fn ($c) => $c->fecha->toDateString())->unique()->sort()->values()->all());

        $anomalias = [];

        foreach ($vendedores as $vendedor) {
            $suyas = $fechas->get($vendedor->id, []);

            if (count($suyas) < $cfg['vendedor_minimo_cotizaciones']) {
                continue;
            }

            $ritmo = (float) Estadistica::mediana(Estadistica::intervalosEnDias($suyas));
            $ultima = Carbon::parse(end($suyas));
            $dias = (int) $ultima->diffInDays($hoy);
            $umbral = max($cfg['vendedor_piso_dias'], (int) ceil(2 * $ritmo));

            if ($dias <= $umbral) {
                continue;
            }

            $anomalias[] = [
                'huella' => "vendedor_inactivo:{$vendedor->id}:{$ultima->toDateString()}",
                'tipo' => 'vendedor_inactivo',
                'severidad' => $dias > max(21, 3 * $ritmo) ? 'ALTA' : 'MEDIA',
                'titulo' => "{$vendedor->nombre_completo} lleva {$dias} días sin cotizar",
                'detalle' => 'Su ritmo habitual es una cotización cada '.$this->num($ritmo).' días; la última fue el '
                    .$ultima->format('d/m/Y').'.',
                'que_revisar' => 'Confirmar si está de vacaciones o con licencia; si no, revisar su cartera de clientes y las oportunidades abiertas.',
                'hechos' => [
                    'vendedor' => $vendedor->nombre_completo,
                    'dias_sin_cotizar' => $dias,
                    'ritmo_dias' => round($ritmo, 1),
                    'ultima_cotizacion' => $ultima->toDateString(),
                    'cotizaciones_ultimo_anio' => count($suyas),
                ],
                'sucursal_id' => $vendedor->sucursal_id,
                'url' => route('empleados.index'),
            ];
        }

        return $anomalias;
    }

    /* -----------------------------------------------------------------
     * 4. Cliente grande que dejó de comprar
     * -------------------------------------------------------------- */

    /**
     * @return list<array<string, mixed>>
     */
    private function clientesEnRiesgo(Carbon $hoy): array
    {
        $cfg = config('bi.anomalias');

        $compras = Cotizacion::query()
            ->whereIn('estado', self::VENDIDOS)
            ->where('fecha', '>=', $hoy->copy()->subMonths(24)->startOfDay())
            ->where('fecha', '<=', $hoy->copy()->endOfDay())
            ->with('cliente:id,razon_social')
            ->get(['id', 'cliente_id', 'sucursal_id', 'fecha', 'total']);

        $ultimoAnio = $hoy->copy()->subMonths(12)->startOfDay();
        $ventasAnio = $compras->filter(fn ($c) => $c->fecha->greaterThanOrEqualTo($ultimoAnio));
        $totalAnio = (float) $ventasAnio->sum('total');

        if ($totalAnio <= 0) {
            return [];
        }

        // Grupo Pareto: los clientes que, ordenados de mayor a menor, suman
        // el 80 % de lo vendido en el último año.
        $acumulado = 0.0;
        $pareto = $ventasAnio->groupBy('cliente_id')
            ->map(fn ($g) => (float) $g->sum('total'))
            ->sortDesc()
            ->filter(function (float $monto) use (&$acumulado, $totalAnio, $cfg): bool {
                $dentro = $acumulado < $cfg['cliente_pareto'] * $totalAnio;
                $acumulado += $monto;

                return $dentro;
            });

        $anomalias = [];

        foreach ($pareto as $clienteId => $montoAnio) {
            $suyas = $compras->where('cliente_id', $clienteId)->sortBy('fecha');
            $fechas = $suyas->map(fn ($c) => $c->fecha->toDateString())->unique()->values()->all();

            if (count($fechas) < $cfg['cliente_minimo_compras']) {
                continue;
            }

            $intervalo = (float) Estadistica::mediana(Estadistica::intervalosEnDias($fechas));
            $ultima = Carbon::parse(end($fechas));
            $dias = (int) $ultima->diffInDays($hoy);
            $umbral = max($cfg['cliente_piso_dias'], (int) ceil(2 * $intervalo));

            if ($dias <= $umbral) {
                continue;
            }

            $participacion = round($montoAnio / $totalAnio * 100, 1);
            $ultimaCompra = $suyas->last();
            $nombre = $ultimaCompra->cliente?->razon_social ?? 'Cliente';

            $anomalias[] = [
                'huella' => "cliente_en_riesgo:{$clienteId}:{$ultima->toDateString()}",
                'tipo' => 'cliente_en_riesgo',
                'severidad' => $participacion >= 10 ? 'ALTA' : 'MEDIA',
                'titulo' => "{$nombre} lleva {$dias} días sin comprar",
                'detalle' => "Representa el {$this->pct($participacion)} de las ventas del último año (Bs {$this->num(round($montoAnio, 2))}). "
                    .'Compraba cada '.$this->num($intervalo).' días; la última compra fue el '.$ultima->format('d/m/Y').'.',
                'que_revisar' => 'Agendar un contacto comercial: revisar la última entrega y su seguimiento postventa, y proponerle su próxima campaña.',
                'hechos' => [
                    'cliente' => $nombre,
                    'dias_sin_comprar' => $dias,
                    'intervalo_dias' => round($intervalo, 1),
                    'ultima_compra' => $ultima->toDateString(),
                    'ventas_ultimo_anio' => round($montoAnio, 2),
                    'participacion_pct' => $participacion,
                    'compras' => count($fechas),
                ],
                'sucursal_id' => $ultimaCompra->sucursal_id,
                'url' => route('clientes.index'),
            ];
        }

        return $anomalias;
    }

    /* -----------------------------------------------------------------
     * Redacción y notificación
     * -------------------------------------------------------------- */

    /**
     * Pide a la IA el texto de todas las anomalías nuevas en una sola
     * llamada. Cada redacción se acepta solo si sus cifras salen de los
     * hechos y del texto de plantilla de esa anomalía.
     *
     * @param  Collection<int, array<string, mixed>>  $anomalias
     * @return array<string, array{detalle: string, que_revisar: string}>
     */
    private function redactar(Collection $anomalias, ?User $solicitante): array
    {
        $entrada = $anomalias->map(fn (array $a) => [
            'huella' => $a['huella'],
            'tipo' => BiAnomalia::TIPOS[$a['tipo']] ?? $a['tipo'],
            'severidad' => $a['severidad'],
            'titulo' => $a['titulo'],
            'detalle_base' => $a['detalle'],
            'que_revisar_base' => $a['que_revisar'],
            'hechos' => $a['hechos'],
        ])->values()->all();

        try {
            $respuesta = $this->ia->ejecutar(
                new RedactorAnomalias,
                "Anomalías detectadas:\n".MinimizadorDatos::json(['anomalias' => $entrada]),
                'anomalias',
                $solicitante,
            );
        } catch (IaNoDisponible) {
            return [];
        }

        $porHuella = $anomalias->keyBy('huella');
        $aceptadas = [];

        $estructurada = $respuesta instanceof StructuredAgentResponse ? $respuesta->structured : [];

        foreach ((array) ($estructurada['anomalias'] ?? []) as $item) {
            $original = $porHuella->get($item['huella'] ?? '');

            if ($original === null || blank($item['detalle'] ?? null) || blank($item['que_revisar'] ?? null)) {
                continue;
            }

            $permitidos = VerificadorCifras::permitidosDe([$original['hechos'], $original['titulo'], $original['detalle'], $original['que_revisar']]);
            $texto = $item['detalle'].' '.$item['que_revisar'];

            if (VerificadorCifras::noEncontrados($texto, $permitidos) === []) {
                $aceptadas[$item['huella']] = [
                    'detalle' => trim($item['detalle']),
                    'que_revisar' => trim($item['que_revisar']),
                ];
            }
        }

        return $aceptadas;
    }

    /**
     * Notifica las anomalías ALTA a quien tiene el asistente de BI y puede
     * ver la sucursal de la anomalía (las de toda la empresa, solo a quien
     * ve todas).
     *
     * @param  Collection<int, BiAnomalia>  $anomalias
     */
    private function notificar(Collection $anomalias): void
    {
        if ($anomalias->isEmpty()) {
            return;
        }

        $destinatarios = User::conPermiso('reportes.asistente');

        foreach ($anomalias as $anomalia) {
            $destinatarios
                ->filter(fn (User $u) => $anomalia->sucursal_id === null
                    ? $u->veTodasLasSucursales()
                    : $u->puedeVerSucursal($anomalia->sucursal_id))
                ->each(fn (User $u) => $u->notify(new AnomaliaDetectada($anomalia)));
        }
    }

    private function pct(float $valor): string
    {
        return number_format($valor, 1, ',', '.').' %';
    }

    private function num(float $valor): string
    {
        $decimales = floor($valor) === $valor ? 0 : (abs($valor) >= 100 ? 2 : 1);

        return number_format($valor, $decimales, ',', '.');
    }
}
