<?php

namespace App\Services\Reporte;

/**
 * Matemática de pronóstico compartida por los reportes de inteligencia de
 * negocios: regresión lineal por mínimos cuadrados, media móvil y la
 * proyección con su rango probable.
 *
 * Es simple A PROPÓSITO (proyecto de grado: tiene que poder explicarse en una
 * pizarra). Con los volúmenes de una empresa de este tamaño —decenas de
 * presupuestos por mes, un par de compras por material— un modelo más fino
 * (ARIMA, suavizado exponencial con estacionalidad) no tiene datos suficientes
 * para ganarle a una recta, y además no se puede explicar.
 *
 * Funciones PURAS: reciben una lista de números en orden cronológico (un valor
 * por mes) y devuelven números. No conocen fechas ni modelos.
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
     * Recta de mínimos cuadrados `y = intercepto + pendiente·x`, con x = 0..n-1.
     *
     * `error` es el error típico de la estimación, √(SSE / (n − 2)): cuánto se
     * aparta en promedio un mes real de la recta. Es lo que da el ancho del
     * rango probable de la proyección.
     *
     * @param  list<float|int>  $valores
     * @return array{pendiente: float, intercepto: float, error: float}
     */
    public static function regresion(array $valores): array
    {
        $valores = array_values($valores);
        $n = count($valores);

        if ($n === 0) {
            return ['pendiente' => 0.0, 'intercepto' => 0.0, 'error' => 0.0];
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

        $error = 0.0;
        if ($n > 2) {
            $sse = 0.0;
            foreach ($valores as $x => $y) {
                $sse += ($y - ($intercepto + $pendiente * $x)) ** 2;
            }
            $error = sqrt($sse / ($n - 2));
        }

        return ['pendiente' => $pendiente, 'intercepto' => $intercepto, 'error' => $error];
    }

    /**
     * Media móvil de `$ventana` meses. Los primeros meses promedian lo que hay
     * (no se dejan vacíos), así la línea arranca junto con la serie.
     *
     * @param  list<float|int|null>  $valores
     * @return list<float|null>
     */
    public static function mediaMovil(array $valores, int $ventana = 3): array
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
     * Prolonga la recta `$periodos` meses después del último dato.
     *
     * Cada punto trae su rango probable (± un error típico). `$piso` corta en
     * cero: no hay ventas ni precios negativos, y una recta que baja lo
     * sugeriría.
     *
     * Devuelve `[]` con menos de MINIMO_PUNTOS datos: la pantalla lo dice en
     * vez de dibujar una tendencia inventada.
     *
     * @param  list<float|int>  $valores
     * @return list<array{valor: float, inferior: float, superior: float}>
     */
    public static function proyectar(array $valores, int $periodos = 3, bool $piso = true): array
    {
        $valores = array_values($valores);
        $n = count($valores);

        if ($n < self::MINIMO_PUNTOS) {
            return [];
        }

        ['pendiente' => $pendiente, 'intercepto' => $intercepto, 'error' => $error] = self::regresion($valores);

        $acotar = fn (float $v): float => $piso ? max($v, 0.0) : $v;

        return array_map(function (int $k) use ($n, $pendiente, $intercepto, $error, $acotar): array {
            $valor = $intercepto + $pendiente * ($n - 1 + $k);

            return [
                'valor' => $acotar($valor),
                'inferior' => $acotar($valor - $error),
                'superior' => $acotar($valor + $error),
            ];
        }, range(1, $periodos));
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
