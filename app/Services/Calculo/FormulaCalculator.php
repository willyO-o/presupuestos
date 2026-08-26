<?php

namespace App\Services\Calculo;

use App\Exceptions\FormulaInvalidaException;
use NXP\Exception\MathExecutorException;
use NXP\MathExecutor;
use Throwable;

/**
 * Evalúa la `expresion` de una fórmula (App\Models\Formula) contra un
 * NXP\MathExecutor, con las variables de una MedidasCotizacion. No guarda
 * estado entre llamadas: cada evaluación crea su propio MathExecutor para
 * no arrastrar variables de una fórmula a otra.
 */
class FormulaCalculator
{
    /**
     * Evalúa `$expresion` con las variables disponibles en `$medidas`.
     *
     * @throws FormulaInvalidaException si la expresión tiene sintaxis
     *                                  inválida, referencia una variable no disponible, o no devuelve un
     *                                  número (ej. una expresión vacía o con una variable de texto).
     */
    public function evaluar(string $expresion, MedidasCotizacion $medidas): float
    {
        $executor = new MathExecutor;
        $executor->setVars($medidas->variables());

        try {
            $resultado = $executor->execute($expresion);
        } catch (MathExecutorException $e) {
            throw new FormulaInvalidaException(
                "La fórmula «{$expresion}» no se pudo evaluar: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! is_numeric($resultado)) {
            throw new FormulaInvalidaException("La fórmula «{$expresion}» no devolvió un número.");
        }

        return (float) $resultado;
    }

    /**
     * Valida que `$expresion` sea evaluable: sintaxis correcta y solo usa
     * variables conocidas (ancho/alto/profundo/area/perimetro), probando
     * con medidas ficticias. Se usa al crear/editar una Formula o una línea
     * de producto_material, antes de guardar — no depende de datos reales.
     *
     * @throws FormulaInvalidaException
     */
    public function validar(string $expresion): void
    {
        $this->evaluar($expresion, new MedidasCotizacion(ancho: 1.0, alto: 1.0, profundo: 1.0));
    }

    /**
     * Como validar(), pero devuelve el mensaje de error en vez de lanzar
     * la excepción — pensado para una regla de validación de Form Request
     * (`$fail($mensaje)`).
     */
    public function mensajeError(string $expresion): ?string
    {
        try {
            $this->validar($expresion);

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
