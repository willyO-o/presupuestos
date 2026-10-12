<?php

namespace App\Ai\Tools;

use App\Ai\Seguridad\ConsultaSoloLectura;
use App\Ai\Seguridad\MinimizadorDatos;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Base de las herramientas del asistente de BI.
 *
 * Cada herramienta es una función de análisis CERRADA: el modelo elige cuál
 * usar y con qué parámetros, pero la consulta la escribe el código. Esta
 * base garantiza en todas:
 *
 * - Corre con el usuario que preguntó (`visiblePara`): ve las mismas
 *   sucursales que en pantalla.
 * - Corre dentro de ConsultaSoloLectura: no puede escribir ni borrar.
 * - Lo que devuelve pasa por MinimizadorDatos antes de llegar al modelo.
 * - Devuelve siempre la misma forma (título, cómo se calculó, tabla y
 *   gráfico), que el panel dibuja con los datos de la base, no con el texto
 *   del modelo.
 *
 * Un error de validación o de datos vuelve al modelo como mensaje, para que
 * corrija los parámetros, en vez de cortar la respuesta.
 */
abstract class HerramientaBi implements Tool
{
    public function __construct(protected readonly User $usuario) {}

    /**
     * Reglas de validación de los argumentos que manda el modelo.
     *
     * @return array<string, mixed>
     */
    abstract protected function reglas(): array;

    /**
     * La consulta. Recibe los argumentos ya validados y devuelve el
     * resultado (ver `resultado()`).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    abstract protected function ejecutar(array $datos): array;

    public function handle(Request $request): Stringable|string
    {
        try {
            $datos = $request->validate($this->reglas());
        } catch (ValidationException $e) {
            return MinimizadorDatos::json([
                'error' => 'Parámetros inválidos: '.collect($e->errors())->flatten()->implode(' '),
            ]);
        }

        try {
            $resultado = ConsultaSoloLectura::ejecutar($this->usuario, fn () => $this->ejecutar($datos));
        } catch (Throwable $e) {
            report($e);

            return MinimizadorDatos::json(['error' => 'No se pudo calcular esta consulta. Prueba con otros parámetros o un periodo distinto.']);
        }

        return MinimizadorDatos::json([
            'herramienta' => $this->nombre(),
            'parametros' => $datos,
            ...$resultado,
        ]);
    }

    /**
     * Nombre con el que el modelo ve la herramienta (mismo criterio que el
     * SDK: el nombre de la clase).
     */
    public function nombre(): string
    {
        return class_basename(static::class);
    }

    /**
     * Forma común del resultado.
     *
     * @param  list<array{clave: string, etiqueta: string, formato?: string}>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>|null  $grafico  {tipo, labels, series: [{nombre, datos}], formato, horizontal?}
     * @param  array<string, mixed>  $resumen  cifras clave para la respuesta en texto
     * @return array<string, mixed>
     */
    protected function resultado(string $titulo, string $calculo, array $resumen, array $columnas, array $filas, ?array $grafico = null): array
    {
        $maximo = (int) config('ia.asistente.max_filas', 24);

        return [
            'titulo' => $titulo,
            'calculo' => $calculo,
            'resumen' => $resumen,
            'tabla' => [
                'columnas' => $columnas,
                'filas' => array_slice($filas, 0, $maximo),
                'filas_omitidas' => max(count($filas) - $maximo, 0),
            ],
            'grafico' => $grafico,
        ];
    }

    protected function fecha(string $valor): Carbon
    {
        return Carbon::parse($valor);
    }

    protected function rango(Carbon $desde, Carbon $hasta): string
    {
        return $desde->translatedFormat('j M Y').' al '.$hasta->translatedFormat('j M Y');
    }

    /**
     * Reglas comunes de periodo (fechas ISO, hasta ≥ desde, máximo 3 años).
     *
     * @return array<string, mixed>
     */
    protected function reglasPeriodo(string $prefijo = ''): array
    {
        return [
            "{$prefijo}desde" => ['required', 'date_format:Y-m-d'],
            "{$prefijo}hasta" => ['required', 'date_format:Y-m-d', "after_or_equal:{$prefijo}desde"],
        ];
    }
}
