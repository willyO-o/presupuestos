<?php

namespace App\Services\Reporte;

/**
 * Estadística para detectar valores fuera de lo normal.
 *
 * Se usa la MEDIANA como "lo habitual" en vez del promedio: una sola semana
 * atípica (un pedido gigante, una feria) arrastra el promedio y esconde la
 * anomalía siguiente; la mediana no se mueve. Para conteos (cotizaciones por
 * semana) la rareza se mide con la cola de Poisson, que con números chicos
 * es mucho más fiel que un z.
 *
 * Funciones puras sobre listas de números, sin fechas ni modelos.
 */
final class Estadistica
{
    /**
     * @param  list<float|int>  $valores
     */
    public static function mediana(array $valores): ?float
    {
        return self::percentil($valores, 50);
    }

    /**
     * Percentil con interpolación lineal (mismo criterio que una planilla).
     *
     * @param  list<float|int>  $valores
     */
    public static function percentil(array $valores, float $p): ?float
    {
        if ($valores === []) {
            return null;
        }

        sort($valores);
        $posicion = ($p / 100) * (count($valores) - 1);
        $abajo = (int) floor($posicion);
        $arriba = (int) ceil($posicion);

        if ($abajo === $arriba) {
            return (float) $valores[$abajo];
        }

        return $valores[$abajo] + ($valores[$arriba] - $valores[$abajo]) * ($posicion - $abajo);
    }

    /**
     * P(X ≤ k) para un conteo Poisson de media λ.
     *
     * Es la prueba natural para "¿es raro emitir solo k cotizaciones en una
     * semana si lo normal es λ?": con conteos chicos, un z (incluso robusto)
     * subestima lo raro de un cero o un dos.
     */
    public static function poissonAcumulada(int $k, float $lambda): float
    {
        if ($k < 0) {
            return 0.0;
        }

        $termino = exp(-$lambda);
        $suma = $termino;

        for ($i = 1; $i <= $k; $i++) {
            $termino *= $lambda / $i;
            $suma += $termino;
        }

        return min(1.0, $suma);
    }

    /**
     * Probabilidad de un conteo tan extremo como `$k` en su dirección:
     * P(X ≤ k) si está por debajo de λ, P(X ≥ k) si está por encima.
     */
    public static function colaPoisson(int $k, float $lambda): float
    {
        return $k <= $lambda
            ? self::poissonAcumulada($k, $lambda)
            : 1.0 - self::poissonAcumulada($k - 1, $lambda);
    }

    /**
     * Días entre fechas consecutivas (ordenadas), para medir el ritmo de un
     * vendedor o de un cliente.
     *
     * @param  list<string>  $fechas  'Y-m-d'
     * @return list<int>
     */
    public static function intervalosEnDias(array $fechas): array
    {
        $fechas = array_values(array_unique($fechas));
        sort($fechas);

        $intervalos = [];
        for ($i = 1; $i < count($fechas); $i++) {
            $intervalos[] = (int) round((strtotime($fechas[$i]) - strtotime($fechas[$i - 1])) / 86400);
        }

        return $intervalos;
    }
}
