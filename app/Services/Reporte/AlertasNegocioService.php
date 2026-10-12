<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\HistorialPrecioMaterial;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Calculo\MotorMargenService;
use Illuminate\Support\Collection;

/**
 * Alertas de costo y margen: lo que alguien tiene que mirar HOY.
 *
 * Cinco reglas, todas con umbrales de configuración (`config/bi.php` para
 * aumentos, `config/margen.php` para el margen — el mismo semáforo que ve el
 * vendedor al cotizar):
 *
 * 1. `costo_material`: un material subió de precio por encima del umbral en su
 *    último cambio dentro del periodo.
 * 2. `margen_bajo`: un presupuesto todavía vivo (pendiente o aprobado sin
 *    convertir) quedó en AMARILLO o ROJO.
 * 3. `presupuesto_desactualizado`: un presupuesto pendiente se armó con
 *    precios de material que ya subieron; se re-evalúa con el motor de margen
 *    para decir cómo quedaría si el cliente lo acepta hoy al mismo precio.
 * 4. `sobrecosto_pedido`: el material realmente consumido por un pedido
 *    superó lo presupuestado por encima del umbral.
 * 5. `tendencia_costos`: el índice de costo de materiales sube a un ritmo
 *    mensual sostenido.
 *
 * Se calculan al abrir la pantalla (no se guardan ni se notifican): son una
 * foto del estado actual, no un historial de avisos.
 */
class AlertasNegocioService
{
    public function __construct(
        private readonly MotorMargenService $motorMargen,
        private readonly CostosMaterialesService $costos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario, PeriodoReporte $periodo): array
    {
        $umbral = config('bi.alerta_aumento_costo');

        $alertas = collect()
            ->merge($this->aumentosDeCosto($periodo, $umbral))
            ->merge($this->margenesBajos($usuario, $periodo))
            ->merge($this->presupuestosDesactualizados($usuario, $periodo, $umbral))
            ->merge($this->sobrecostos($usuario, $periodo, $umbral))
            ->merge($this->tendenciaCostos($periodo))
            ->sortBy(fn (array $a) => [$a['severidad'] === 'alta' ? 0 : 1, $a['tipo']])
            ->values();

        return [
            'periodo' => $periodo->toArray(),
            'alertas' => $alertas->all(),
            'resumen' => [
                'total' => $alertas->count(),
                'alta' => $alertas->where('severidad', 'alta')->count(),
                'media' => $alertas->where('severidad', 'media')->count(),
                'por_tipo' => $alertas->countBy('tipo')->all(),
            ],
            'umbrales' => [
                'aumento_costo' => round($umbral * 100, 2),
                'tendencia_mensual' => round(config('bi.alerta_tendencia_mensual') * 100, 2),
                'margen_verde' => round(config('margen.semaforo.umbral_verde') * 100, 2),
                'margen_amarillo' => round(config('margen.semaforo.umbral_amarillo') * 100, 2),
            ],
        ];
    }

    /**
     * Severidad por tamaño del desvío: al doble del umbral, alta.
     */
    private function severidad(float $desvio, float $umbral): string
    {
        return $this->alcanza($desvio, 2 * $umbral) ? 'alta' : 'media';
    }

    /**
     * `$desvio >= $umbral` sin el ruido del punto flotante: 120 / 100 − 1 da
     * 0,19999…, y un aumento de exactamente el 20 % no puede quedar por
     * debajo de un umbral del 20 %.
     */
    private function alcanza(float $desvio, float $umbral): bool
    {
        return round($desvio, 6) >= round($umbral, 6);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function aumentosDeCosto(PeriodoReporte $periodo, float $umbral): Collection
    {
        return HistorialPrecioMaterial::query()
            ->with('material:id,nombre')
            ->where('vigente_desde', '<=', $periodo->hasta)
            ->orderBy('vigente_desde')
            ->orderBy('id')
            ->get(['id', 'material_id', 'precio_unitario', 'vigente_desde'])
            ->filter(fn ($h) => $h->material)
            ->groupBy('material_id')
            ->map(function (Collection $registros) use ($periodo, $umbral): ?array {
                // Último cambio de precio que cae dentro del periodo.
                $registros = $registros->values();
                $indice = $registros->keys()->last(
                    fn (int $i) => $i > 0 && $registros[$i]->vigente_desde->greaterThanOrEqualTo($periodo->desde->copy()->startOfDay())
                );

                if ($indice === null) {
                    return null;
                }

                $anterior = (float) $registros[$indice - 1]->precio_unitario;
                $nuevo = (float) $registros[$indice]->precio_unitario;

                if ($anterior <= 0 || ! $this->alcanza($nuevo / $anterior - 1, $umbral)) {
                    return null;
                }

                $aumento = $nuevo / $anterior - 1;

                return [
                    'tipo' => 'costo_material',
                    'severidad' => $this->severidad($aumento, $umbral),
                    'titulo' => "{$registros[$indice]->material->nombre} subió ".$this->pct($aumento),
                    'detalle' => 'De Bs '.$this->bs($anterior).' a Bs '.$this->bs($nuevo).' por unidad desde el '
                        .$registros[$indice]->vigente_desde->format('d/m/Y')
                        .'. Los presupuestos nuevos que lo usen ya salen con el precio actualizado; revisar los pendientes.',
                    'valor' => round($aumento * 100, 2),
                    'url' => null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function margenesBajos(User $usuario, PeriodoReporte $periodo): Collection
    {
        return Cotizacion::query()
            ->visiblePara($usuario)
            ->whereIn('estado', ['PENDIENTE', 'APROBADA'])
            ->whereIn('estado_margen', ['AMARILLO', 'ROJO'])
            ->where('costo_ajustado', '>', 0)
            ->whereBetween('fecha', [$periodo->desde, $periodo->hasta])
            ->with('cliente:id,razon_social')
            ->get(['id', 'cliente_id', 'codigo_verificacion', 'estado', 'costo_ajustado', 'utilidad_real', 'estado_margen'])
            ->map(function (Cotizacion $c): array {
                $rentabilidad = (float) $c->utilidad_real / (float) $c->costo_ajustado;

                return [
                    'tipo' => 'margen_bajo',
                    'severidad' => $c->estado_margen === 'ROJO' ? 'alta' : 'media',
                    'titulo' => "Presupuesto {$c->codigo_verificacion} con margen ".strtolower($c->estado_margen),
                    'detalle' => ($c->cliente?->razon_social ? "{$c->cliente->razon_social} · " : '')
                        .'Utilidad real Bs '.$this->bs((float) $c->utilidad_real).' ('.$this->pct($rentabilidad).' del costo). '
                        .MotorMargenService::RECOMENDACIONES[$c->estado_margen].'.',
                    'valor' => round($rentabilidad * 100, 2),
                    'url' => route('cotizaciones.show', $c),
                ];
            })
            ->values();
    }

    /**
     * Presupuestos pendientes cuyos insumos de material cuestan hoy más que
     * cuando se cotizaron.
     *
     * Se re-evalúa con el motor al MISMO precio (lo que el cliente tiene en la
     * mano): el costo de cada línea sube por la diferencia de precio de sus
     * materiales × la complejidad de la línea.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function presupuestosDesactualizados(User $usuario, PeriodoReporte $periodo, float $umbral): Collection
    {
        return Cotizacion::query()
            ->visiblePara($usuario)
            ->where('estado', 'PENDIENTE')
            ->whereBetween('fecha', [$periodo->desde, $periodo->hasta])
            ->whereHas('detalles.items', fn ($q) => $q->whereNotNull('material_id'))
            ->with([
                'detalles:id,cotizacion_id,cantidad,factor_complejidad',
                'detalles.items' => fn ($q) => $q->whereNotNull('material_id'),
                'detalles.items.material:id,precio_unitario',
            ])
            ->get(['id', 'codigo_verificacion', 'costo_base', 'costo_ajustado', 'subtotal', 'descuento', 'utilidad_real', 'estado_margen'])
            ->map(function (Cotizacion $c) use ($umbral): ?array {
                $costoCotizado = $deltaBase = $deltaAjustado = 0.0;

                foreach ($c->detalles as $linea) {
                    foreach ($linea->items as $item) {
                        if (! $item->material) {
                            continue;
                        }

                        $unidades = (float) $item->cantidad * (float) $linea->cantidad;
                        $diferencia = ((float) $item->material->precio_unitario - (float) $item->costo_unitario) * $unidades;

                        $costoCotizado += (float) $item->costo_unitario * $unidades;
                        $deltaBase += $diferencia;
                        $deltaAjustado += $diferencia * (float) ($linea->factor_complejidad ?: 1);
                    }
                }

                if ($costoCotizado <= 0 || ! $this->alcanza($deltaBase / $costoCotizado, $umbral)) {
                    return null;
                }

                $hoy = $this->motorMargen->evaluar(
                    costoBase: (float) $c->costo_base + $deltaBase,
                    costoAjustado: (float) $c->costo_ajustado + $deltaAjustado,
                    precio: (float) $c->subtotal - (float) $c->descuento,
                );

                $aumento = $deltaBase / $costoCotizado;
                $empeora = $c->estado_margen && $hoy->estado !== $c->estado_margen;

                return [
                    'tipo' => 'presupuesto_desactualizado',
                    'severidad' => $hoy->estado === 'ROJO' ? 'alta' : $this->severidad($aumento, $umbral),
                    'titulo' => "Presupuesto {$c->codigo_verificacion}: sus materiales subieron ".$this->pct($aumento),
                    'detalle' => 'Si se acepta hoy al mismo precio, la utilidad real pasa de Bs '.$this->bs((float) $c->utilidad_real)
                        .' a Bs '.$this->bs($hoy->utilidadReal)
                        .($empeora ? " ({$c->estado_margen} → {$hoy->estado})" : '')
                        .'. Conviene recotizar antes de que el cliente lo apruebe.',
                    'valor' => round($aumento * 100, 2),
                    'url' => route('cotizaciones.show', $c),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Pedidos cuyo material consumido superó lo presupuestado (costo ajustado
     * de las líneas con hoja de costos de la cotización de origen).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sobrecostos(User $usuario, PeriodoReporte $periodo, float $umbral): Collection
    {
        return Pedido::query()
            ->visiblePara($usuario)
            ->where('estado', '!=', 'CANCELADO')
            ->whereBetween('fecha_pedido', [$periodo->desde, $periodo->hasta])
            ->whereHas('detalles.materialesUsados')
            ->with('detalles.cotizacionDetalle:id,costo_ajustado', 'detalles.materialesUsados:id,pedido_detalle_id,costo_real')
            ->get(['id', 'numero_pedido'])
            ->map(function (Pedido $pedido) use ($umbral): ?array {
                // Solo se compara contra líneas que SÍ tenían costo
                // presupuestado: una línea sin hoja de costos daría un
                // "sobrecosto" del 100 % que no es tal.
                $lineas = $pedido->detalles->filter(fn ($d) => (float) ($d->cotizacionDetalle?->costo_ajustado ?? 0) > 0);

                $presupuestado = (float) $lineas->sum(fn ($d) => $d->cotizacionDetalle->costo_ajustado);
                $real = (float) $lineas->flatMap->materialesUsados->sum('costo_real');

                if ($presupuestado <= 0 || ! $this->alcanza($real / $presupuestado - 1, $umbral)) {
                    return null;
                }

                $desvio = $real / $presupuestado - 1;

                return [
                    'tipo' => 'sobrecosto_pedido',
                    'severidad' => $this->severidad($desvio, $umbral),
                    'titulo' => "Pedido {$pedido->numero_pedido} consumió ".$this->pct($desvio).' más de lo presupuestado',
                    'detalle' => 'Material real Bs '.$this->bs($real).' contra Bs '.$this->bs($presupuestado)
                        .' presupuestados. Revisar la hoja de costos del producto o el desperdicio en taller.',
                    'valor' => round($desvio * 100, 2),
                    'url' => route('pedidos.show', $pedido),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tendenciaCostos(PeriodoReporte $periodo): Collection
    {
        $indice = $this->costos->indiceDelPeriodo($periodo);
        $ritmo = $indice['variacion_mensual'];
        $umbral = config('bi.alerta_tendencia_mensual');

        if ($ritmo === null || ! $this->alcanza($ritmo / 100, $umbral)) {
            return collect();
        }

        $meses = config('bi.meses_proyeccion');
        $proyectado = collect($indice['proyeccion'])->last()['valor'] ?? null;
        $ultimo = collect($indice['serie'])->last(fn ($v) => $v !== null);

        return collect([[
            'tipo' => 'tendencia_costos',
            'severidad' => $this->severidad($ritmo / 100, $umbral),
            'titulo' => 'El costo de materiales sube ~'.$this->pct($ritmo / 100).' por mes',
            'detalle' => "Tendencia de {$indice['materiales']} materiales con historial de precios."
                .($proyectado && $ultimo
                    ? " A este ritmo, en {$meses} meses comprar lo mismo costará ".$this->pct($proyectado / $ultimo - 1).' más que hoy.'
                    : '')
                .' Considerar revisar márgenes o adelantar compras.',
            'valor' => round($ritmo, 2),
            'url' => null,
        ]]);
    }

    private function pct(float $fraccion): string
    {
        return number_format($fraccion * 100, 1, ',', '.').' %';
    }

    private function bs(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }
}
