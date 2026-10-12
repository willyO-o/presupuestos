<?php

namespace App\Ai\Excepciones;

use RuntimeException;
use Throwable;

/**
 * La IA no pudo responder: está apagada, no hay ningún proveedor con API
 * key, se alcanzó el tope de gasto del mes o el proveedor falló.
 *
 * Quien la recibe decide el respaldo: el resumen semanal y las anomalías
 * usan su texto de plantilla; el asistente muestra el motivo. Nunca debe
 * romper la pantalla ni el job que la llamó.
 */
class IaNoDisponible extends RuntimeException
{
    public const DESHABILITADA = 'deshabilitada';

    public const NO_CONFIGURADA = 'no_configurada';

    public const SIN_PRESUPUESTO = 'sin_presupuesto';

    public const ERROR_PROVEEDOR = 'error_proveedor';

    public function __construct(public readonly string $motivo, string $mensaje, ?Throwable $previa = null)
    {
        parent::__construct($mensaje, 0, $previa);
    }

    public static function deshabilitada(): self
    {
        return new self(self::DESHABILITADA, 'La IA está desactivada en la configuración (IA_HABILITADA).');
    }

    public static function noConfigurada(): self
    {
        return new self(self::NO_CONFIGURADA, 'No hay ningún proveedor de IA con clave configurada (GEMINI_API_KEY o ANTHROPIC_API_KEY).');
    }

    public static function sinPresupuesto(float $gasto, float $tope): self
    {
        return new self(self::SIN_PRESUPUESTO, sprintf(
            'Se alcanzó el tope de gasto de IA del mes (USD %.2f de %.2f). Vuelve a estar disponible el 1 del mes siguiente.',
            $gasto,
            $tope,
        ));
    }

    public static function errorProveedor(Throwable $previa): self
    {
        return new self(self::ERROR_PROVEEDOR, 'El proveedor de IA no respondió. Intenta de nuevo en unos minutos.', $previa);
    }
}
