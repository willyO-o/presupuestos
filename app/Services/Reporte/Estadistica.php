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
     * Valor crítico de la t de Student: el `t` tal que P(T ≤ t) = `$p` con
     * `$gradosLibertad` grados de libertad. Es el número de la tabla t que se
     * busca a mano (p. ej. t(0,90; 10) = 1,372).
     *
     * Se calcula invirtiendo la función de distribución por bisección, así
     * sirve para cualquier nivel de confianza y no solo los de una tabla.
     */
    public static function tStudentCuantil(float $p, int $gradosLibertad): float
    {
        if ($gradosLibertad < 1 || $p <= 0.0 || $p >= 1.0) {
            throw new \InvalidArgumentException('Se necesita 0 < p < 1 y al menos 1 grado de libertad.');
        }

        if ($p < 0.5) {
            return -self::tStudentCuantil(1.0 - $p, $gradosLibertad);
        }

        $abajo = 0.0;
        $arriba = 1.0;
        while (self::tStudentAcumulada($arriba, $gradosLibertad) < $p) {
            $arriba *= 2;
        }

        for ($i = 0; $i < 100 && $arriba - $abajo > 1e-10; $i++) {
            $medio = ($abajo + $arriba) / 2;

            if (self::tStudentAcumulada($medio, $gradosLibertad) < $p) {
                $abajo = $medio;
            } else {
                $arriba = $medio;
            }
        }

        return ($abajo + $arriba) / 2;
    }

    /**
     * P(T ≤ t) de la t de Student, vía la beta incompleta regularizada:
     * para t ≥ 0, F(t) = 1 − ½ · I_{ν/(ν+t²)}(ν/2, ½).
     */
    public static function tStudentAcumulada(float $t, int $gradosLibertad): float
    {
        $nu = (float) $gradosLibertad;
        $cola = 0.5 * self::betaIncompleta($nu / ($nu + $t * $t), $nu / 2, 0.5);

        return $t >= 0 ? 1.0 - $cola : $cola;
    }

    /**
     * Beta incompleta regularizada I_x(a, b), por fracción continua
     * (algoritmo clásico de Numerical Recipes, `betai`/`betacf`).
     */
    private static function betaIncompleta(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }

        if ($x >= 1.0) {
            return 1.0;
        }

        $factor = exp(
            self::logGamma($a + $b) - self::logGamma($a) - self::logGamma($b)
            + $a * log($x) + $b * log(1.0 - $x)
        );

        // La fracción continua converge rápido solo de un lado de la media.
        return $x < ($a + 1.0) / ($a + $b + 2.0)
            ? $factor * self::fraccionBeta($x, $a, $b) / $a
            : 1.0 - $factor * self::fraccionBeta(1.0 - $x, $b, $a) / $b;
    }

    private static function fraccionBeta(float $x, float $a, float $b): float
    {
        $minimo = 1e-300;
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;
        $d = abs($d) < $minimo ? $minimo : $d;
        $d = 1.0 / $d;
        $h = $d;

        for ($m = 1; $m <= 200; $m++) {
            $m2 = 2 * $m;

            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            $d = abs($d) < $minimo ? $minimo : $d;
            $c = 1.0 + $aa / $c;
            $c = abs($c) < $minimo ? $minimo : $c;
            $d = 1.0 / $d;
            $h *= $d * $c;

            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            $d = abs($d) < $minimo ? $minimo : $d;
            $c = 1.0 + $aa / $c;
            $c = abs($c) < $minimo ? $minimo : $c;
            $d = 1.0 / $d;
            $delta = $d * $c;
            $h *= $delta;

            if (abs($delta - 1.0) < 1e-12) {
                break;
            }
        }

        return $h;
    }

    /** ln Γ(x) por la aproximación de Lanczos (x > 0). */
    private static function logGamma(float $x): float
    {
        $coeficientes = [
            76.18009172947146, -86.50532032941677, 24.01409824083091,
            -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5,
        ];

        $y = $x;
        $tmp = $x + 5.5;
        $tmp -= ($x + 0.5) * log($tmp);
        $serie = 1.000000000190015;

        foreach ($coeficientes as $coeficiente) {
            $serie += $coeficiente / ++$y;
        }

        return -$tmp + log(2.5066282746310005 * $serie / $x);
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
