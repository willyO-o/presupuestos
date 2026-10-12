<?php

namespace App\Ai\Seguridad;

/**
 * Última barrera antes de que un dato salga hacia el proveedor de IA.
 *
 * Las herramientas y los servicios ya arman sus respuestas con columnas
 * elegidas a mano (montos, cantidades, nombres de producto, razón social);
 * esto quita cualquier clave sensible que se haya colado por un cambio
 * futuro: contraseñas, tokens, claves, correos, teléfonos, documentos de
 * identidad, direcciones.
 */
final class MinimizadorDatos
{
    /**
     * Se compara contra el nombre de la clave en minúsculas y sin acentos.
     */
    private const PATRON = '/(password|contrasena|clave|token|secret|api_?key|remember|hash|email|correo|telefono|celular|whatsapp|^nit$|^ci$|carnet|documento|direccion|domicilio|ip$|user_agent|session)/';

    /**
     * @param  array<array-key, mixed>  $datos
     * @return array<array-key, mixed>
     */
    public static function limpiar(array $datos): array
    {
        $limpio = [];

        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && self::esSensible($clave)) {
                continue;
            }

            $limpio[$clave] = is_array($valor) ? self::limpiar($valor) : $valor;
        }

        return $limpio;
    }

    public static function esSensible(string $clave): bool
    {
        $normalizada = strtr(mb_strtolower($clave), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return (bool) preg_match(self::PATRON, $normalizada);
    }

    /**
     * JSON listo para el prompt, ya limpio.
     *
     * @param  array<array-key, mixed>  $datos
     */
    public static function json(array $datos): string
    {
        return json_encode(self::limpiar($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';
    }
}
