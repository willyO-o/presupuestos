<?php

namespace App\Services\Reporte;

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
        'preguntas' => 'Preguntas para decidir',
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
        private readonly PreguntasNegocioService $preguntas,
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
                'preguntas' => ['preguntas' => $this->preguntas->datos($usuario, $periodo)['preguntas']],
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
            'alcance' => AlcanceReporte::rotulo($usuario),
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
                    'sujeto' => 'la demanda',
                    'descripcion' => 'Cantidad de órdenes de trabajo que se registraron cada mes.',
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
                    'titulo' => 'Precio de los materiales',
                    'sujeto' => 'el índice de precios de materiales',
                    'descripcion' => 'Cuánto cuestan los materiales comparado con el inicio del periodo: 100 = el mismo precio; 110 = 10 % más caros. Es de toda la empresa.',
                    'formato' => 'indice',
                    'historico' => $costos['indice']['serie'],
                    'proyeccion' => $costos['indice']['proyeccion'],
                    'variacion_mensual' => $costos['indice']['variacion_mensual'],
                    'metodo' => $costos['indice']['metodo'],
                    'umbrales' => [['valor' => 100, 'nombre' => 'Base 100']],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'consumo',
                    'titulo' => 'Gasto en materiales',
                    'sujeto' => 'el gasto en materiales',
                    'descripcion' => 'Lo que costó el material que producción registró como usado en los pedidos de cada mes.',
                    'formato' => 'bs',
                    'historico' => $costos['consumo']['serie'],
                    'media_movil' => $costos['consumo']['media_movil'],
                    'proyeccion' => $costos['consumo']['proyeccion'],
                    'variacion_mensual' => $costos['consumo']['variacion_mensual'],
                    'metodo' => $costos['consumo']['metodo'],
                ]),
            ],
            'tabla' => [
                'titulo' => 'Precio esperado de los materiales principales',
                'nota' => 'Los materiales en los que más se gasta. Precio por unidad de compra; es de toda la empresa.',
                'columnas' => ['Material', 'Precio hoy', 'Cómo viene', 'Precio en '.($meses[count($meses) - 1] ?? ''), 'Podría quedar entre', 'Confianza'],
                'filas' => array_map(function (array $m): array {
                    $final = $m['proyeccion'] === [] ? null : $m['proyeccion'][count($m['proyeccion']) - 1];

                    return [
                        $m['material'],
                        RedaccionPronostico::formatear($m['precio_actual'], 'bs_exacto'),
                        $m['variacion_mensual'] === null ? '—' : RedaccionPronostico::variacion($m['variacion_mensual']).' al mes',
                        $final ? RedaccionPronostico::formatear($final['valor'], 'bs_exacto') : '—',
                        $final ? RedaccionPronostico::formatear($final['inferior'], 'bs_exacto').' – '.RedaccionPronostico::formatear($final['superior'], 'bs_exacto') : '—',
                        ($m['metodo']['suficiente'] ?? false) ? RedaccionPronostico::nivelConfianza($m['metodo']) : '—',
                    ];
                }, $costos['materiales']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Presupuestos y rentabilidad, con los mismos nombres y bloques que la
     * pestaña: 1) monto cotizado y aprobado, 2) aprobación y precio frente a
     * costo (`resumen`, `meses`, `precio_costo`), 3) rentabilidad sobre costo
     * y resultado calculado (`semaforo_presupuestos`). `que_revisar` sale del
     * mismo servicio que la pantalla.
     *
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
                    'bloque' => 1,
                    'titulo' => 'Monto cotizado',
                    'sujeto' => 'el monto cotizado',
                    'descripcion' => 'Suma de todos los presupuestos emitidos cada mes, se hayan aprobado o no. No es dinero cobrado.',
                    'formato' => 'bs',
                    'historico' => $pronostico['monto_emitido'],
                    'media_movil' => $pronostico['media_movil'],
                    'proyeccion' => $pronostico['proyeccion_emitido'],
                    'variacion_mensual' => $pronostico['variacion_mensual'],
                    'metodo' => $pronostico['metodo'],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'monto_aprobado',
                    'bloque' => 1,
                    'titulo' => 'Monto aprobado',
                    'sujeto' => 'el monto aprobado',
                    'descripcion' => 'Suma de los presupuestos que el cliente aprobó cada mes (incluidos los que ya pasaron a pedido). No es dinero cobrado.',
                    'formato' => 'bs',
                    'historico' => $pronostico['monto_aprobado'],
                    'proyeccion' => $pronostico['proyeccion_aprobado'],
                    'variacion_mensual' => $pronostico['variacion_aprobado'],
                    'metodo' => $pronostico['metodo_aprobado'],
                ]),
                $this->indicador($periodo, [
                    'clave' => 'rentabilidad',
                    'bloque' => 3,
                    'titulo' => 'Rentabilidad sobre costo',
                    'sujeto' => 'la rentabilidad sobre costo',
                    'descripcion' => 'De cada Bs 100 de costo ajustado de los trabajos aprobados, cuánto queda de resultado calculado, ya descontados IT e IUE. Solo cuentan los presupuestos con hoja de costos.',
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
                    'bloque' => 3,
                    'titulo' => 'Resultado calculado del mes',
                    'sujeto' => 'el resultado calculado',
                    'descripcion' => 'Resultado en bolivianos de los presupuestos aprobados cada mes: precio sin IVA menos costo ajustado, IT e IUE (solo los que tienen hoja de costos). No es dinero cobrado.',
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
            'resumen' => [
                ...$datos['resumen'],
                'rentabilidad' => $rentabilidad['rentabilidad_promedio'],
                'margen_sobre_precio' => $rentabilidad['margen_sobre_precio'],
                'resultado' => $rentabilidad['utilidad_total'],
                'analizados' => $rentabilidad['analizados'],
            ],
            'semaforo_presupuestos' => ['conteo' => $rentabilidad['semaforo'], 'umbrales' => $umbrales],
            'precio_costo' => $datos['tendencia']['lectura'],
            'que_revisar' => $datos['que_revisar'],
            // Mes a mes, para el bloque 2: cuántos se emitieron y aprobaron, y
            // el precio frente al costo promedio de los mismos presupuestos.
            'meses' => array_map(fn (string $mes, int $i): array => [
                'mes' => $mes,
                'emitidos' => $pronostico['emitidos'][$i],
                'aprobados' => $pronostico['aprobados'][$i],
                'precio' => $datos['tendencia']['ticket'][$i],
                'costo' => $datos['tendencia']['costo'][$i],
            ], $datos['labels'], array_keys($datos['labels'])),
            'notas' => $rentabilidad['excluidos_sin_costo'] > 0
                ? [sprintf(
                    'Hay %d presupuestos aprobados sin hoja de costos (son anteriores al cálculo de márgenes): no se pueden usar para medir la rentabilidad. Se analizaron %d.',
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

        // Completas (sin el enlace): el informe de la pestaña arma sus tablas
        // con la referencia, el cliente y las cifras de cada una.
        return [
            'alertas' => array_map(fn (array $a): array => collect($a)->except('url')->all(), $datos['alertas']),
            'resumen_alertas' => $datos['resumen'],
            'umbrales' => $datos['umbrales'],
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
            // Cómo se lo nombra en la lectura ("el monto cotizado promedió…").
            'sujeto' => 'el indicador',
            'bloque' => null,
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
}
