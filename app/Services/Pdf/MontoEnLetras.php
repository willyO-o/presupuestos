<?php

namespace App\Services\Pdf;

/**
 * Importe en letras para el "Son:" de una cotización:
 * 2875 → "Dos Mil Ochocientos Setenta y Cinco 00/100 Bolivianos".
 *
 * Implementación propia (no NumberFormatter) para no depender de la
 * extensión intl en el hosting. Cubre hasta los cientos de millones, que
 * sobra para un presupuesto.
 */
final class MontoEnLetras
{
    /** @var list<string> 0 a 29: hasta veintinueve se escribe en una palabra. */
    private const UNIDADES = [
        'cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    /** @var array<int, string> */
    private const DECENAS = [
        3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta',
        7 => 'setenta', 8 => 'ochenta', 9 => 'noventa',
    ];

    /** @var array<int, string> */
    private const CENTENAS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
        6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos',
    ];

    public static function convertir(float|int|string|null $monto, string $moneda = 'Bolivianos'): string
    {
        $centavos = (int) round(max((float) $monto, 0) * 100);
        $entero = intdiv($centavos, 100);

        return sprintf('%s %02d/100 %s', self::capitalizar(self::numero($entero)), $centavos % 100, $moneda);
    }

    private static function numero(int $numero): string
    {
        if ($numero >= 1_000_000) {
            return self::conResto(
                intdiv($numero, 1_000_000) === 1 ? 'un millón' : self::apocopar(self::numero(intdiv($numero, 1_000_000))).' millones',
                $numero % 1_000_000,
            );
        }

        if ($numero >= 1000) {
            return self::conResto(
                intdiv($numero, 1000) === 1 ? 'mil' : self::apocopar(self::numero(intdiv($numero, 1000))).' mil',
                $numero % 1000,
            );
        }

        if ($numero >= 100) {
            return $numero === 100 ? 'cien' : self::conResto(self::CENTENAS[intdiv($numero, 100)], $numero % 100);
        }

        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        $unidad = $numero % 10;

        return self::DECENAS[intdiv($numero, 10)].($unidad > 0 ? ' y '.self::UNIDADES[$unidad] : '');
    }

    private static function conResto(string $texto, int $resto): string
    {
        return $resto > 0 ? $texto.' '.self::numero($resto) : $texto;
    }

    /** Delante de "mil"/"millones": "uno" → "un", "veintiuno" → "veintiún". */
    private static function apocopar(string $texto): string
    {
        return (string) preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $texto);
    }

    /** "Dos Mil Ochocientos Setenta y Cinco": cada palabra con mayúscula salvo la "y". */
    private static function capitalizar(string $texto): string
    {
        return implode(' ', array_map(
            fn (string $palabra): string => $palabra === 'y' ? $palabra : mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8'),
            explode(' ', $texto),
        ));
    }
}
