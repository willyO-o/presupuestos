<?php

namespace App\Ai;

use App\Ai\Excepciones\IaNoDisponible;
use App\Models\IaUso;
use App\Models\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use Throwable;

/**
 * Única puerta de salida hacia los proveedores de IA.
 *
 * Toda llamada pasa por `ejecutar()`, que en este orden:
 *
 * 1. Corta si la IA está apagada, si ningún proveedor tiene API key o si el
 *    gasto del mes llegó al tope (config/ia.php).
 * 2. Llama al agente con la cadena de failover de la funcionalidad
 *    (Gemini primero, Claude de respaldo; el SDK `laravel/ai` pasa al
 *    siguiente ante un límite de uso o sobrecarga).
 * 3. Registra en `ia_uso` quién, qué funcionalidad, qué proveedor y modelo
 *    respondió de verdad, tokens, costo estimado y latencia — también cuando
 *    falla, para que la auditoría muestre los cortes.
 *
 * Los agentes nunca se invocan directo desde un controlador o servicio.
 */
class ServicioIa
{
    public function habilitada(): bool
    {
        return (bool) config('ia.habilitada');
    }

    /**
     * Cadena de failover de la funcionalidad, sin los proveedores que no
     * tienen API key: mandarle un pedido a un proveedor sin clave es un error
     * que no dispara el failover del SDK.
     *
     * @return array<string, string> proveedor => modelo, en orden
     */
    public function proveedores(string $funcionalidad): array
    {
        return collect(config("ia.funcionalidades.{$funcionalidad}.proveedores", []))
            ->filter(fn (?string $modelo, string $proveedor) => filled($modelo) && filled(config("ai.providers.{$proveedor}.key")))
            ->all();
    }

    public function gastoDelMes(): float
    {
        return IaUso::gastoDelMes();
    }

    public function presupuestoAgotado(): bool
    {
        return $this->gastoDelMes() >= (float) config('ia.presupuesto_mensual_usd');
    }

    /**
     * Estado para la interfaz: si la funcionalidad puede usarse ahora y cómo
     * va el gasto del mes.
     *
     * @return array{disponible: bool, motivo: string|null, gasto_mes: float, presupuesto: float, porcentaje: float, cerca_del_tope: bool, proveedor: string|null}
     */
    public function estado(string $funcionalidad): array
    {
        $gasto = $this->gastoDelMes();
        $tope = (float) config('ia.presupuesto_mensual_usd');
        $proveedores = $this->proveedores($funcionalidad);

        $motivo = match (true) {
            ! $this->habilitada() => IaNoDisponible::deshabilitada()->getMessage(),
            $proveedores === [] => IaNoDisponible::noConfigurada()->getMessage(),
            $gasto >= $tope => IaNoDisponible::sinPresupuesto($gasto, $tope)->getMessage(),
            default => null,
        };

        $porcentaje = $tope > 0 ? round($gasto / $tope * 100, 1) : 100.0;

        return [
            'disponible' => $motivo === null,
            'motivo' => $motivo,
            'gasto_mes' => round($gasto, 4),
            'presupuesto' => $tope,
            'porcentaje' => $porcentaje,
            'cerca_del_tope' => $porcentaje >= config('ia.aviso_presupuesto') * 100,
            'proveedor' => array_key_first($proveedores),
        ];
    }

    /**
     * @throws IaNoDisponible
     */
    public function ejecutar(Agent $agente, string $prompt, string $funcionalidad, ?User $usuario = null): AgentResponse
    {
        if (! $this->habilitada()) {
            throw IaNoDisponible::deshabilitada();
        }

        $proveedores = $this->proveedores($funcionalidad);

        if ($proveedores === []) {
            $this->registrar($funcionalidad, $usuario, 'NO_CONFIGURADA');

            throw IaNoDisponible::noConfigurada();
        }

        $gasto = $this->gastoDelMes();
        $tope = (float) config('ia.presupuesto_mensual_usd');

        if ($gasto >= $tope) {
            $this->registrar($funcionalidad, $usuario, 'SIN_PRESUPUESTO');

            throw IaNoDisponible::sinPresupuesto($gasto, $tope);
        }

        $inicio = hrtime(true);

        try {
            $respuesta = $agente->prompt(
                $prompt,
                provider: $proveedores,
                timeout: (int) config("ia.funcionalidades.{$funcionalidad}.timeout", 60),
            );
        } catch (Throwable $e) {
            $this->registrar($funcionalidad, $usuario, 'ERROR', latenciaMs: $this->milisegundos($inicio), error: $e->getMessage());

            throw IaNoDisponible::errorProveedor($e);
        }

        $modelo = $respuesta->meta->model ?? reset($proveedores);

        $this->registrar(
            $funcionalidad,
            $usuario,
            'OK',
            proveedor: $respuesta->meta->provider ?? array_key_first($proveedores),
            modelo: $modelo,
            uso: $respuesta->usage,
            latenciaMs: $this->milisegundos($inicio),
        );

        return $respuesta;
    }

    /**
     * Costo estimado en USD con los precios de config/ia.php.
     *
     * La entrada sin caché, la leída de caché y la salida (que ya incluye el
     * razonamiento) se cobran distinto; la escritura de caché se aproxima con
     * el precio de entrada.
     */
    public function costo(?string $modelo, TextUsage $uso): float
    {
        // Se indexa el arreglo y no `config("ia.precios.{$modelo}")`: los
        // nombres de modelo llevan puntos (gemini-3.8-flash) y la notación de
        // puntos de config() los partiría.
        [$entrada, $salida, $cache] = config('ia.precios')[$modelo ?? ''] ?? config('ia.precio_por_defecto');

        $leidoDeCache = $uso->cacheReadInputTokens ?? 0;
        $sinCache = max($uso->inputTokens - $leidoDeCache, 0);

        return round(
            ($sinCache * $entrada + $leidoDeCache * $cache + $uso->outputTokens * $salida) / 1_000_000,
            6,
        );
    }

    private function registrar(
        string $funcionalidad,
        ?User $usuario,
        string $estado,
        ?string $proveedor = null,
        ?string $modelo = null,
        ?TextUsage $uso = null,
        int $latenciaMs = 0,
        ?string $error = null,
    ): void {
        IaUso::create([
            'user_id' => $usuario?->id,
            'funcionalidad' => $funcionalidad,
            'proveedor' => $proveedor,
            'modelo' => $modelo,
            'tokens_entrada' => $uso?->inputTokens ?? 0,
            'tokens_salida' => $uso?->outputTokens ?? 0,
            'tokens_cache' => $uso?->cacheReadInputTokens ?? 0,
            'costo_usd' => $uso ? $this->costo($modelo, $uso) : 0,
            'latencia_ms' => $latenciaMs,
            'estado' => $estado,
            'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);
    }

    private function milisegundos(int|float $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
