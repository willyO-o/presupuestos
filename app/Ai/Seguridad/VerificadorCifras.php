<?php

namespace App\Ai\Seguridad;

/**
 * Comprueba que un texto redactado por la IA no invente cifras.
 *
 * Extrae cada número del texto y lo busca entre los números permitidos (los
 * hechos calculados en PHP). Un número que no aparece, ni redondeado, se
 * reporta: quien llama descarta el texto y usa su plantilla de respaldo.
 *
 * Tolera el redondeo de presentación (Bs 12.450 por 12.450,37; 12 % por
 * 12,4 %) y acepta enteros chicos de redacción ("3 familias", "2 semanas").
 */
final class VerificadorCifras
{
    /**
     * Enteros que se aceptan siempre: conteos de redacción y meses.
     */
    private const ENTERO_LIBRE_MAX = 12;

    /**
     * @param  list<float|int>  $permitidos
     * @return list<float> números del texto que no salen de los permitidos
     */
    public static function noEncontrados(string $texto, array $permitidos): array
    {
        $faltantes = [];

        foreach (self::numeros($texto) as $numero) {
            if (self::esLibre($numero) || self::estaPermitido($numero, $permitidos)) {
                continue;
            }

            $faltantes[] = $numero;
        }

        return $faltantes;
    }

    /**
     * Todos los valores numéricos de una estructura (recorre arrays
     * anidados), más su valor absoluto: un texto dice "bajó 12 %" para una
     * variación de −12.
     *
     * @param  array<array-key, mixed>  $datos
     * @return list<float>
     */
    public static function permitidosDe(array $datos): array
    {
        $numeros = [];

        array_walk_recursive($datos, function ($valor) use (&$numeros): void {
            if (is_int($valor) || is_float($valor)) {
                $numeros[] = (float) $valor;
                $numeros[] = abs((float) $valor);
            } elseif (is_string($valor)) {
                foreach (self::numeros($valor) as $n) {
                    $numeros[] = $n;
                }
            }
        });

        return array_values(array_unique($numeros, SORT_REGULAR));
    }

    /**
     * Números de un texto en formato boliviano (1.234,56) o con punto
     * decimal (1234.56). Las fechas (12/10/2026) se ignoran.
     *
     * @return list<float>
     */
    public static function numeros(string $texto): array
    {
        $sinFechas = preg_replace('#\b\d{1,2}/\d{1,2}(/\d{2,4})?\b#', ' ', $texto) ?? $texto;

        preg_match_all('/(?<![\w.,])-?\d[\d.,]*/u', $sinFechas, $coincidencias);

        $numeros = [];
        foreach ($coincidencias[0] as $crudo) {
            $valor = self::interpretar(rtrim($crudo, '.,'));
            if ($valor !== null) {
                $numeros[] = $valor;
            }
        }

        return $numeros;
    }

    private static function interpretar(string $crudo): ?float
    {
        if ($crudo === '' || $crudo === '-') {
            return null;
        }

        // 12.450,50 o 12.450 → miles con punto, decimales con coma.
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $crudo)) {
            return (float) str_replace(['.', ','], ['', '.'], $crudo);
        }

        // 12,5 → coma decimal.
        if (preg_match('/^-?\d+,\d+$/', $crudo)) {
            return (float) str_replace(',', '.', $crudo);
        }

        // 12450.5 → punto decimal (el modelo a veces escribe así).
        if (preg_match('/^-?\d+(\.\d+)?$/', $crudo)) {
            return (float) $crudo;
        }

        // 1,234,567 → miles con coma.
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $crudo)) {
            return (float) str_replace(',', '', $crudo);
        }

        return null;
    }

    private static function esLibre(float $numero): bool
    {
        $esEntero = floor($numero) === $numero;

        return ($esEntero && abs($numero) <= self::ENTERO_LIBRE_MAX)
            || ($esEntero && $numero >= 2000 && $numero <= 2100);
    }

    /**
     * @param  list<float|int>  $permitidos
     */
    private static function estaPermitido(float $numero, array $permitidos): bool
    {
        foreach ($permitidos as $permitido) {
            $tolerancia = max(0.5, abs($permitido) * 0.005);

            if (abs($numero - $permitido) <= $tolerancia) {
                return true;
            }
        }

        return false;
    }
}
