<?php

namespace App\Services\Reporte;

use App\Models\Sucursal;
use App\Models\User;

/**
 * Informe de proyecciones: junta los pronósticos de las pestañas del BI en un
 * documento que se puede leer sin la pantalla (PDF) o reprocesar (CSV).
 *
 * No calcula nada propio: pide los datos a los MISMOS servicios que las
 * pestañas (con el mismo usuario, el mismo periodo y el mismo alcance por
 * sucursal), así un número del informe no puede diferir del que se ve en
 * pantalla. Lo que agrega es la forma común de cada indicador —serie,
 * proyección, ficha del método— y su lectura en texto automático
 * (`RedaccionPronostico`).
 */
class InformeProyeccionesService
{
    /** Secciones que se pueden pedir, en el orden en que salen. */
    public const SECCIONES = [
        'demanda' => 'Demanda y ventas',
        'costos' => 'Costos y materiales',
        'rentabilidad' => 'Presupuestos y rentabilidad',
        'alertas' => 'Alertas',
    ];

    public function __construct(
        private readonly InteligenciaNegociosService $demanda,
        private readonly CostosMaterialesService $costos,
        private readonly PresupuestosRentabilidadService $rentabilidad,
        private readonly AlertasNegocioService $alertas,
    ) {}

    /**
     * @param  list<string>  $secciones  Claves de SECCIONES; vacío = todas.
     * @return array<string, mixed>
     */
    public function armar(User $usuario, PeriodoReporte $periodo, array $secciones = []): array
    {
        $pedidas = array_values(array_intersect(array_keys(self::SECCIONES), $secciones ?: array_keys(self::SECCIONES)));
        $mesIncompleto = $periodo->toArray()['mes_incompleto'];

        $armadas = array_map(fn (string $clave): array => [
            'clave' => $clave,
            'titulo' => self::SECCIONES[$clave],
            ...match ($clave) {
                'demanda' => $this->seccionDemanda($usuario, $periodo),
                'costos' => $this->seccionCostos($usuario, $periodo),
                'rentabilidad' => $this->seccionRentabilidad($usuario, $periodo),
                'alertas' => $this->seccionAlertas($usuario, $periodo),
            },
        ], $pedidas);

        $armadas = array_map(function (array $seccion) use ($mesIncompleto): array {
            $seccion['indicadores'] = array_map(
                fn (array $indicador): array => $indicador + [
                    'lectura' => RedaccionPronostico::lectura($indicador, $mesIncompleto),
                    'resumen' => RedaccionPronostico::resumen($indicador),
                ],
                $seccion['indicadores'] ?? [],
            );

            return $seccion;
        }, $armadas);

        $indicadores = collect($armadas)->flatMap(fn (array $s) => $s['indicadores'])->values()->all();

        return [
            'periodo' => $periodo->toArray() + [
                'etiqueta_desde' => $periodo->meses()->first(),
                'etiqueta_hasta' => $periodo->meses()->last(),
            ],
            'meses_proyeccion' => $periodo->mesesSiguientes(config('bi.meses_proyeccion')),
            'confianza' => (int) round(config('bi.confianza') * 100),
            'alcance' => $this->alcance($usuario),
            'secciones' => $armadas,
            'conclusiones' => RedaccionPronostico::conclusiones($indicadores, $armadas),
        ];
    }

    /* -----------------------------------------------------------------
     * Secciones
     * -------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function seccionDemanda(User $usuario, PeriodoReporte $periodo): array
    {
        $demanda = $this->demanda->datos($usuario, $periodo)['demanda'];

        return [
            'indicadores' => [
                $this->indicador($periodo, [
                    'clave' => 'pedidos',
                    'titulo' => 'Pedidos por mes',
                    'descripcion' => 'Órdenes de trabajo registradas cada mes.',
                    'formato' => 'cantidad',
                    'unidad' => 'pedidos',
                    'historico' => array_column($demanda['serie'], 'pedidos'),
                    'media_movil' => array_column($demanda['media_movil'], 'valor'),
                    'proyeccion' => array_map(fn (array $p): array => [
                        'valor' => $p['pedidos_estimados'],
                        'inferior' => $p['inferior'],
                        'superior' => $p['superior'],
                    ], $demanda['proyeccion']),
                    'variacion_mensual' => $demanda['variacion_mensual'],
                    'metodo' => $demanda['metodo'],
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seccionCostos(User $usuario, PeriodoReporte $periodo): array
    {
        $costos = $this->costos->datos($usuario, $periodo);
        $meses = $costos['meses_proyeccion'];

        return [
            'indicadores' => [
                $this->indicador($periodo, [
                    'clave' => 'indice_costo',
                    'titulo' => 'Índice de costo de materiales',
                    'descripcion' => 'Promedio de (precio del mes ÷ primer precio del periodo) × 100 de los materiales con historial. 100 = igual que al inicio. Toda la empresa.',
                    'formato' => 'indice',
                    'historico' => $costos['indice']['serie'],
                    'proyeccion' => $costos['indice']['proyeccion'],
                    'variacion_mensual' => $costos['indice']['variacion_mensual'],
                    'metodo' => $costos['indice']['metodo'],
                    'umbrales' => [['valor' => 100, 'nombre' => 'Base 100']],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'consumo',
                    'titulo' => 'Costo de material consumido',
                    'descripcion' => 'Costo del material que producción registró en los pedidos, por mes.',
                    'formato' => 'bs',
                    'historico' => $costos['consumo']['serie'],
                    'media_movil' => $costos['consumo']['media_movil'],
                    'proyeccion' => $costos['consumo']['proyeccion'],
                    'variacion_mensual' => $costos['consumo']['variacion_mensual'],
                    'metodo' => $costos['consumo']['metodo'],
                ]),
            ],
            'tabla' => [
                'titulo' => 'Proyección de precio por material',
                'nota' => 'Materiales más consumidos del periodo. Precio unitario vigente; toda la empresa.',
                'columnas' => ['Material', 'Precio actual', 'Tendencia', 'Estimado '.($meses[count($meses) - 1] ?? ''), 'Rango probable', 'R²'],
                'filas' => array_map(function (array $m): array {
                    $final = $m['proyeccion'] === [] ? null : $m['proyeccion'][count($m['proyeccion']) - 1];

                    return [
                        $m['material'],
                        RedaccionPronostico::formatear($m['precio_actual'], 'bs_exacto'),
                        $m['variacion_mensual'] === null ? '—' : RedaccionPronostico::variacion($m['variacion_mensual']).' / mes',
                        $final ? RedaccionPronostico::formatear($final['valor'], 'bs_exacto') : '—',
                        $final ? RedaccionPronostico::formatear($final['inferior'], 'bs_exacto').' – '.RedaccionPronostico::formatear($final['superior'], 'bs_exacto') : '—',
                        ($m['metodo']['r2'] ?? null) === null ? '—' : RedaccionPronostico::numero($m['metodo']['r2'], 2),
                    ];
                }, $costos['materiales']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seccionRentabilidad(User $usuario, PeriodoReporte $periodo): array
    {
        $datos = $this->rentabilidad->datos($usuario, $periodo);
        $pronostico = $datos['pronostico'];
        $rentabilidad = $datos['rentabilidad'];
        $umbrales = $rentabilidad['umbrales'];

        return [
            'indicadores' => [
                $this->indicador($periodo, [
                    'clave' => 'monto_emitido',
                    'titulo' => 'Monto presupuestado (emitido)',
                    'descripcion' => 'Suma del total de los presupuestos emitidos cada mes.',
                    'formato' => 'bs',
                    'historico' => $pronostico['monto_emitido'],
                    'media_movil' => $pronostico['media_movil'],
                    'proyeccion' => $pronostico['proyeccion_emitido'],
                    'variacion_mensual' => $pronostico['variacion_mensual'],
                    'metodo' => $pronostico['metodo'],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'monto_aprobado',
                    'titulo' => 'Monto vendido (aprobado)',
                    'descripcion' => 'Suma del total de los presupuestos aprobados o convertidos en pedido cada mes.',
                    'formato' => 'bs',
                    'historico' => $pronostico['monto_aprobado'],
                    'proyeccion' => $pronostico['proyeccion_aprobado'],
                    'variacion_mensual' => $pronostico['variacion_aprobado'],
                    'metodo' => $pronostico['metodo_aprobado'],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'rentabilidad',
                    'titulo' => 'Rentabilidad sobre costo',
                    'descripcion' => 'Utilidad real ÷ costo ajustado de los presupuestos aprobados con hoja de costos (la razón del semáforo).',
                    'formato' => 'porcentaje',
                    'historico' => $rentabilidad['porcentaje'],
                    'proyeccion' => $rentabilidad['proyeccion_porcentaje'],
                    'variacion_mensual' => $rentabilidad['variacion_mensual'],
                    'metodo' => $rentabilidad['metodo'],
                    'umbrales' => [
                        ['valor' => $umbrales['verde'], 'nombre' => 'Verde'],
                        ['valor' => $umbrales['amarillo'], 'nombre' => 'Amarillo'],
                    ],
                    'semaforo' => $umbrales,
                ]),
                $this->indicador($periodo, [
                    'clave' => 'utilidad',
                    'titulo' => 'Utilidad real',
                    'descripcion' => 'Utilidad real de los presupuestos aprobados con hoja de costos, por mes.',
                    'formato' => 'bs',
                    'historico' => $rentabilidad['utilidad'],
                    'proyeccion' => $rentabilidad['proyeccion_utilidad'],
                    'variacion_mensual' => $this->variacionRelativa($rentabilidad['metodo_utilidad']),
                    'metodo' => $rentabilidad['metodo_utilidad'],
                ]),
            ],
            'tendencia' => [
                'ticket' => $datos['tendencia']['variacion_ticket'],
                'costo' => $datos['tendencia']['variacion_costo'],
            ],
            'notas' => $rentabilidad['excluidos_sin_costo'] > 0
                ? [sprintf(
                    '%d presupuesto(s) aprobado(s) del periodo no tienen hoja de costos (son anteriores al motor de margen) y no entran en la rentabilidad; se analizan %d.',
                    $rentabilidad['excluidos_sin_costo'],
                    $rentabilidad['analizados'],
                )]
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seccionAlertas(User $usuario, PeriodoReporte $periodo): array
    {
        $datos = $this->alertas->datos($usuario, $periodo);

        return [
            'alertas' => array_map(fn (array $a): array => [
                'severidad' => $a['severidad'],
                'titulo' => $a['titulo'],
                'detalle' => $a['detalle'],
            ], $datos['alertas']),
            'resumen_alertas' => $datos['resumen'],
        ];
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /**
     * Forma común de un indicador: lo que el servicio de la pestaña ya
     * calculó, más las etiquetas del eje.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function indicador(PeriodoReporte $periodo, array $datos): array
    {
        return $datos + [
            'labels' => $periodo->meses()->values()->all(),
            'meses_proyeccion' => $periodo->mesesSiguientes(config('bi.meses_proyeccion')),
            'media_movil' => null,
            'umbrales' => [],
            'semaforo' => null,
        ];
    }

    /**
     * Ritmo de cambio de una serie que el servicio no expresa en % por mes
     * (la utilidad): pendiente ÷ |promedio|, con el promedio despejado de la
     * propia recta (intercepto + pendiente · x̄, x̄ = (n − 1) / 2).
     *
     * @param  array<string, mixed>  $metodo
     */
    private function variacionRelativa(array $metodo): ?float
    {
        if (! ($metodo['suficiente'] ?? false)) {
            return null;
        }

        $promedio = $metodo['intercepto'] + $metodo['pendiente'] * ($metodo['n'] - 1) / 2;

        return $promedio == 0 ? null : round($metodo['pendiente'] / abs($promedio) * 100, 2);
    }

    /**
     * Rótulo de qué datos cubre el informe: un total de una sola sucursal leído
     * como el de la empresa es el error que más daño hace (ver reporte.md).
     */
    private function alcance(User $usuario): string
    {
        $visibles = $usuario->sucursalesVisibles();

        if ($visibles === null) {
            return 'Toda la empresa';
        }

        $nombres = Sucursal::query()->whereIn('id', $visibles)->orderBy('nombre')->pluck('nombre');

        return $nombres->isEmpty() ? 'Ninguna sucursal asignada' : 'Sucursales: '.$nombres->implode(', ');
    }
}
