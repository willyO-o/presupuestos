<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una expresión de `formula` no se pudo evaluar: sintaxis inválida,
 * referencia una variable que no está disponible para las medidas dadas
 * (ver App\Services\Calculo\MedidasCotizacion::variables()), o no devolvió
 * un número. Se usa tanto al validar una fórmula nueva (Form Request) como
 * al calcular el costo real de una cotización.
 */
class FormulaInvalidaException extends RuntimeException {}
