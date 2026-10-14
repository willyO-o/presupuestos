<?php

namespace App\Services\Reporte;

/**
 * Matemática de pronóstico compartida por los reportes de inteligencia de
 * negocios: regresión lineal por mínimos cuadrados, media móvil, la
 * proyección con su intervalo de predicción y la validación que dice cuánto
 * se equivoca.
 *
 * El modelo que se DIBUJA es la recta, a propósito (proyecto de grado: tiene
 * que poder explicarse en una pizarra). Con los volúmenes de una empresa de
 * este tamaño —decenas de presupuestos por mes, un par de compras por
 * material— un modelo más fino no tiene datos suficientes para ganarle con
 * holgura. En vez de afirmarlo, `validar()` lo MIDE: reserva los últimos
 * meses reales, pronostica con la recta, con Holt (suavizado exponencial
 * doble) y con la media móvil, y compara el error de cada uno (MAPE).
 *
 * Funciones PURAS: reciben una lista de números en orden cronológico (un valor
 * por mes) y devuelven números. No conocen fechas ni modelos; el puente con el
 * calendario es `PeriodoReporte::pronostico()`.
 */
final class Pronostico
{
    /**
     * Puntos mínimos para trazar una tendencia. Con dos la recta pasa
     * exactamente por ambos y el "error" es cero, lo que sería mentir sobre
     * la confianza del pronóstico.
     */
    public const MINIMO_PUNTOS = 3;

    /**
     * Meses mínimos con los que se ENTRENA cada modelo en la validación. Con
     * menos, la comparación de errores es ruido.
     */
    public const MINIMO_VALIDACION = 6;

    /** Meses de la media móvil (la línea suavizada de los gráficos). */
    public const VENTANA_MEDIA_MOVIL = 3;

    /** Nivel de confianza del intervalo si `config('bi.confianza')` no dice otro. */
    public const CONFIANZA = 0.80;

    /**
     * Recta de mínimos cuadrados `y = intercepto + pendiente·x`, con x = 0..n-1.
     *
     * - `error` es el error típico de la estimación, √(SSE / (n − 2)): cuánto
     *   se aparta en promedio un mes real de la recta.
     * - `r2` es el coeficiente de determinación, 1 − SSE/SST: qué parte de la
     *   variación de la serie explica la tendencia. `null` si la serie es
     *   constante (no hay variación que explicar).
     *
     * @param  list<float|int>  $valores
     * @return array{pendiente: float, intercepto: float, error: float, r2: float|null, n: int, media_x: float, sxx: float}
     */
    public static function regresion(array $valores): array
    {
        $valores = array_values($valores);
        $n = count($valores);

        if ($n === 0) {
            return ['pendiente' => 0.0, 'intercepto' => 0.0, 'error' => 0.0, 'r2' => null, 'n' => 0, 'media_x' => 0.0, 'sxx' => 0.0];
        }

        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($valores as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominador = $n * $sumX2 - $sumX ** 2;
        $pendiente = $denominador != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominador : 0.0;
        $intercepto = ($sumY - $pendiente * $sumX) / $n;
        $mediaX = $sumX / $n;
        $mediaY = $sumY / $n;

        $sse = $sst = 0.0;
        foreach ($valores as $x => $y) {
            $sse += ($y - ($intercepto + $pendiente * $x)) ** 2;
            $sst += ($y - $mediaY) ** 2;
        }

        return [
            'pendiente' => $pendiente,
            'intercepto' => $intercepto,
            'error' => $n > 2 ? sqrt($sse / ($n - 2)) : 0.0,
            // Un SST de 1e-20 es una serie constante con ruido de redondeo.
            'r2' => $sst > 1e-12 ? max(0.0, 1 - $sse / $sst) : null,
            'n' => $n,
            'media_x' => $mediaX,
            'sxx' => $sumX2 - $n * $mediaX ** 2,
        ];
    }

    /**
     * Análisis completo de una serie mensual: la proyección con su intervalo
     * de predicción, el ritmo de cambio y la ficha del método (lo que muestra
     * la tarjeta "Cómo se calcula" y el anexo del informe).
     *
     * **Intervalo de predicción** al nivel `$confianza` (80 % por defecto):
     *
     *     ŷ₀ ± t(1−α/2; n−2) · s · √(1 + 1/n + (x₀ − x̄)² / Sxx)
     *
     * Se ensancha cuanto más lejos se proyecta (el término (x₀ − x̄)²), que es
     * lo correcto: la incertidumbre sobre la pendiente se acumula mes a mes.
     *
     * `$saltar` son meses que existen entre el último dato usado y el primer
     * mes proyectado — el mes en curso, que se excluyó por incompleto. La
     * proyección sigue cayendo en los mismos meses del eje y, además, se
     * estima cómo cerraría ese mes (`metodo.cierre_estimado`).
     *
     * Con menos de MINIMO_PUNTOS datos la proyección es `[]` (la pantalla lo
     * dice en vez de dibujar una tendencia inventada) y la ficha marca
     * `suficiente: false`.
     *
     * @param  list<float|int>  $valores
     * @return array{proyeccion: list<array{valor: float, inferior: float, superior: float}>, variacion_mensual: float|null, metodo: array<string, mixed>}
     */
    public static function analizar(array $valores, int $periodos = 3, bool $piso = true, int $saltar = 0, ?float $confianza = null): array
    {
        $valores = array_values($valores);
        $n = count($valores);
        $confianza ??= (float) config('bi.confianza', self::CONFIANZA);

        $metodo = [
            'tecnica' => 'regresion_lineal',
            'suficiente' => $n >= self::MINIMO_PUNTOS,
            'n' => $n,
            'minimo' => self::MINIMO_PUNTOS,
            'confianza' => (int) round($confianza * 100),
            'ventana_media_movil' => self::VENTANA_MEDIA_MOVIL,
            'piso' => $piso,
        ];

        if ($n < self::MINIMO_PUNTOS) {
            return [
                'proyeccion' => [],
                'variacion_mensual' => null,
                'metodo' => $metodo + ['cierre_estimado' => [], 'validacion' => null],
            ];
        }

        $recta = self::regresion($valores);
        $t = Estadistica::tStudentCuantil(1 - (1 - $confianza) / 2, $n - 2);
        $acotar = fn (float $v): float => $piso ? max($v, 0.0) : $v;

        // x₀ en coordenadas de la serie usada: el último dato es n − 1.
        $punto = function (float $x0) use ($recta, $n, $t, $acotar): array {
            $valor = $recta['intercepto'] + $recta['pendiente'] * $x0;
            $palanca = $recta['sxx'] > 0 ? ($x0 - $recta['media_x']) ** 2 / $recta['sxx'] : 0.0;
            $margen = $t * $recta['error'] * sqrt(1 + 1 / $n + $palanca);

            return [
                'valor' => $acotar($valor),
                'inferior' => $acotar($valor - $margen),
                'superior' => $acotar($valor + $margen),
            ];
        };

        $proyeccion = array_map(fn (int $k): array => $punto($n - 1 + $saltar + $k), range(1, $periodos));
        $cierre = $saltar > 0 ? array_map(fn (int $k): array => $punto($n - 1 + $k), range(1, $saltar)) : [];

        $promedio = array_sum($valores) / $n;
        $variacion = $promedio == 0 ? null : $recta['pendiente'] / abs($promedio) * 100;

        return [
            'proyeccion' => $proyeccion,
            'variacion_mensual' => $variacion,
            'metodo' => $metodo + [
                'pendiente' => round($recta['pendiente'], 4),
                'intercepto' => round($recta['intercepto'], 4),
                'r2' => $recta['r2'] === null ? null : round($recta['r2'], 3),
                'ajuste' => self::calificarAjuste($recta['r2']),
                'error_tipico' => round($recta['error'], 4),
                't' => round($t, 3),
                'grados_libertad' => $n - 2,
                'cierre_estimado' => array_map(
                    fn (array $p): array => array_map(fn (float $v) => round($v, 2), $p),
                    $cierre,
                ),
                'validacion' => self::validar($valores, $periodos, $piso),
            ],
        ];
    }

    /**
     * Prolonga la recta `$periodos` meses después del último dato, con su
     * intervalo de predicción. Atajo de `analizar()` para quien solo quiere
     * los puntos.
     *
     * @param  list<float|int>  $valores
     * @return list<array{valor: float, inferior: float, superior: float}>
     */
    public static function proyectar(array $valores, int $periodos = 3, bool $piso = true): array
    {
        return self::analizar($valores, $periodos, $piso)['proyeccion'];
    }

    /**
     * Lectura del R² en palabras, para la tarjeta y el informe. Los cortes son
     * los habituales en ciencias sociales y de gestión (no en física, donde
     * 0,7 sería pobre): con series de negocio mensuales, un R² de 0,4 ya
     * describe una tendencia que se ve a simple vista.
     */
    public static function calificarAjuste(?float $r2): ?string
    {
        return match (true) {
            $r2 === null => null,
            $r2 >= 0.7 => 'fuerte',
            $r2 >= 0.4 => 'moderado',
            default => 'debil',
        };
    }

    /**
     * Validación retrospectiva (backtesting): se esconden los últimos
     * `$horizonte` meses reales, cada modelo pronostica esos meses con lo
     * anterior, y se mide su error porcentual absoluto medio (MAPE).
     *
     * Responde la pregunta que importa —"¿cuánto se equivoca esto?"— con los
     * datos de la propia empresa, y compara la recta contra dos alternativas:
     * Holt (tendencia que se adapta) y la media móvil (sin tendencia). Si la
     * recta no es la mejor, la pantalla y el informe lo dicen.
     *
     * `null` si no hay meses suficientes para entrenar (MINIMO_VALIDACION).
     *
     * @param  list<float|int>  $valores
     * @return array{meses: int, entrenamiento: int, recta: float|null, holt: float|null, media_movil: float|null, mejor: string|null}|null
     */
    public static function validar(array $valores, int $horizonte = 3, bool $piso = true): ?array
    {
        $valores = array_values($valores);
        $n = count($valores);

        if ($horizonte < 1 || $n - $horizonte < self::MINIMO_VALIDACION) {
            return null;
        }

        $entrenamiento = array_slice($valores, 0, $n - $horizonte);
        $reales = array_slice($valores, $n - $horizonte);
        $acotar = fn (array $serie): array => $piso ? array_map(fn (float $v) => max($v, 0.0), $serie) : $serie;

        $recta = self::regresion($entrenamiento);
        $ultimos = array_slice($entrenamiento, -self::VENTANA_MEDIA_MOVIL);
        $promedio = array_sum($ultimos) / count($ultimos);

        $errores = [
            'recta' => self::mape($reales, $acotar(array_map(
                fn (int $k): float => $recta['intercepto'] + $recta['pendiente'] * (count($entrenamiento) - 1 + $k),
                range(1, $horizonte),
            ))),
            'holt' => self::mape($reales, $acotar(self::holt($entrenamiento, $horizonte))),
            'media_movil' => self::mape($reales, array_fill(0, $horizonte, (float) $promedio)),
        ];

        $conValor = array_filter($errores, fn ($e) => $e !== null);

        return [
            'meses' => $horizonte,
            'entrenamiento' => count($entrenamiento),
            ...array_map(fn ($e) => $e === null ? null : round($e, 1), $errores),
            'mejor' => $conValor === [] ? null : array_search(min($conValor), $conValor, true),
        ];
    }

    /**
     * Suavizado exponencial doble de Holt: un nivel y una tendencia que se
     * actualizan mes a mes, dando más peso a lo reciente.
     *
     *     nivel_t     = α·y_t + (1 − α)·(nivel_{t−1} + tendencia_{t−1})
     *     tendencia_t = β·(nivel_t − nivel_{t−1}) + (1 − β)·tendencia_{t−1}
     *     pronóstico  = nivel_n + k·tendencia_n
     *
     * α y β se eligen de una grilla (0,1 … 0,9) minimizando el error de
     * pronosticar cada mes con los anteriores. Sirve de contraste en la
     * validación: si los datos tuvieran un quiebre de tendencia, Holt lo
     * seguiría y la recta no.
     *
     * @param  list<float|int>  $valores
     * @return list<float>
     */
    public static function holt(array $valores, int $periodos): array
    {
        $valores = array_values(array_map('floatval', $valores));

        if (count($valores) < 2) {
            return [];
        }

        $mejor = null;

        foreach (range(1, 9) as $a) {
            foreach (range(1, 9) as $b) {
                $ajuste = self::suavizar($valores, $a / 10, $b / 10);

                if ($mejor === null || $ajuste['sse'] < $mejor['sse']) {
                    $mejor = $ajuste;
                }
            }
        }

        return array_map(fn (int $k): float => $mejor['nivel'] + $k * $mejor['tendencia'], range(1, $periodos));
    }

    /**
     * @param  list<float>  $valores
     * @return array{nivel: float, tendencia: float, sse: float}
     */
    private static function suavizar(array $valores, float $alfa, float $beta): array
    {
        $nivel = $valores[0];
        $tendencia = $valores[1] - $valores[0];
        $sse = 0.0;

        for ($i = 1; $i < count($valores); $i++) {
            $pronostico = $nivel + $tendencia;
            // El primer paso se acierta por construcción (la tendencia inicial
            // sale de él): no se cuenta.
            if ($i > 1) {
                $sse += ($valores[$i] - $pronostico) ** 2;
            }

            $anterior = $nivel;
            $nivel = $alfa * $valores[$i] + (1 - $alfa) * ($nivel + $tendencia);
            $tendencia = $beta * ($nivel - $anterior) + (1 - $beta) * $tendencia;
        }

        return ['nivel' => $nivel, 'tendencia' => $tendencia, 'sse' => $sse];
    }

    /**
     * Error porcentual absoluto medio, en %. Los meses con valor real 0 no
     * entran (el porcentaje sería infinito); `null` si no queda ninguno.
     *
     * @param  list<float|int>  $reales
     * @param  list<float>  $pronosticos
     */
    public static function mape(array $reales, array $pronosticos): ?float
    {
        $errores = [];

        foreach (array_values($reales) as $i => $real) {
            if ($real != 0 && isset($pronosticos[$i])) {
                $errores[] = abs(($real - $pronosticos[$i]) / $real);
            }
        }

        return $errores === [] ? null : array_sum($errores) / count($errores) * 100;
    }

    /**
     * Media móvil de `$ventana` meses. Los primeros meses promedian lo que hay
     * (no se dejan vacíos), así la línea arranca junto con la serie.
     *
     * @param  list<float|int|null>  $valores
     * @return list<float|null>
     */
    public static function mediaMovil(array $valores, int $ventana = self::VENTANA_MEDIA_MOVIL): array
    {
        $valores = array_values($valores);

        return array_map(function (int $i) use ($valores, $ventana): ?float {
            $tramo = array_filter(
                array_slice($valores, max(0, $i - $ventana + 1), min($i + 1, $ventana)),
                fn ($v) => $v !== null,
            );

            return $tramo === [] ? null : array_sum($tramo) / count($tramo);
        }, array_keys($valores));
    }

    /**
     * Pendiente de la recta como porcentaje del promedio de la serie: "crece
     * X % por mes". Es el número que se lee en una tarjeta de tendencia.
     *
     * `null` si no hay puntos suficientes o el promedio es cero (no hay base
     * contra la cual expresar el cambio).
     *
     * @param  list<float|int>  $valores
     */
    public static function variacionMensual(array $valores): ?float
    {
        $valores = array_values($valores);

        if (count($valores) < self::MINIMO_PUNTOS) {
            return null;
        }

        $promedio = array_sum($valores) / count($valores);

        if ($promedio == 0) {
            return null;
        }

        return self::regresion($valores)['pendiente'] / abs($promedio) * 100;
    }
}
