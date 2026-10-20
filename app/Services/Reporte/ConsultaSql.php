<?php

namespace App\Services\Reporte;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Expresiones SQL que cambian según el motor, para agregar EN LA BASE
 * (`GROUP BY` por mes) sin atar el código a MariaDB.
 *
 * Hasta el Panorama del BI, los reportes agrupaban por mes en PHP con
 * Collections precisamente para no depender de `DATE_FORMAT` (MariaDB) ni de
 * `strftime` (SQLite, la base de los tests). Para volúmenes grandes eso carga
 * todas las filas en memoria; con esta clase la agregación queda en SQL y el
 * mismo código corre en los dos motores.
 */
final class ConsultaSql
{
    /**
     * Ingreso neto de una línea de cotización: su subtotal con el descuento
     * de la cotización prorrateado. Espera `cotizacion_detalle` y la
     * cotización unida como `c`. Es el ingreso del Panorama y de las
     * Preguntas para decidir: un solo cálculo para que no difieran.
     *
     * El `* 1.0` fuerza la división real: SQLite guarda los decimales enteros
     * como enteros y `100 / 1000` daría 0.
     */
    public const INGRESO_NETO_LINEA = 'cotizacion_detalle.subtotal * (1 - COALESCE(c.descuento * 1.0 / NULLIF(c.subtotal, 0), 0))';

    /**
     * `AAAA-MM` de una columna de fecha, como texto: la misma clave que usa
     * `PeriodoReporte::meses()`.
     *
     * `$columna` tiene que ser un identificador (`tabla.columna`); se valida
     * porque termina pegado dentro de un `selectRaw`.
     */
    public static function mes(string $columna): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i', $columna) !== 1) {
            throw new InvalidArgumentException("Columna inválida: {$columna}");
        }

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$columna})",
            'pgsql' => "to_char({$columna}, 'YYYY-MM')",
            default => "DATE_FORMAT({$columna}, '%Y-%m')",
        };
    }
}
