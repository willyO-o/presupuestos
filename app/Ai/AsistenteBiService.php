<?php

namespace App\Ai;

use App\Ai\Agents\AsistenteBi;
use App\Ai\Excepciones\IaNoDisponible;
use App\Models\IaConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Orquesta una pregunta al asistente de BI: límite de uso, conversación,
 * llamada a la IA (vía ServicioIa) y registro en `ia_consulta` con los
 * resultados de las herramientas, que son de donde el panel dibuja los
 * gráficos y tablas.
 */
class AsistenteBiService
{
    /** Consultas que muestra el panel: de una conversación o del historial. */
    public const LIMITE_HISTORIAL = 20;

    public function __construct(private readonly ServicioIa $ia) {}

    /**
     * @throws LimiteDePreguntas
     */
    public function preguntar(User $usuario, string $pregunta, ?string $conversacionId = null): IaConsulta
    {
        $clave = 'ia-asistente:'.$usuario->id;
        $limite = (int) config('ia.asistente.preguntas_por_hora');

        if (RateLimiter::tooManyAttempts($clave, $limite)) {
            throw new LimiteDePreguntas(RateLimiter::availableIn($clave));
        }

        RateLimiter::hit($clave, 3600);

        // Una conversación solo se continúa si es de este usuario: el id
        // viaja desde el navegador y no se puede confiar en él.
        if ($conversacionId !== null && ! IaConsulta::query()->where('user_id', $usuario->id)->where('conversacion_id', $conversacionId)->exists()) {
            $conversacionId = null;
        }

        $agente = (new AsistenteBi($usuario))->continueOrStart($conversacionId, as: $usuario);

        try {
            $respuesta = $this->ia->ejecutar($agente, $pregunta, 'asistente', $usuario);
        } catch (IaNoDisponible $e) {
            return IaConsulta::create([
                'user_id' => $usuario->id,
                'conversacion_id' => $conversacionId,
                'pregunta' => $pregunta,
                'respuesta' => $e->getMessage(),
                'resultados' => [],
                'estado' => 'ERROR',
            ]);
        }

        return IaConsulta::create([
            'user_id' => $usuario->id,
            'conversacion_id' => $respuesta->conversationId ?? $conversacionId,
            'pregunta' => $pregunta,
            'respuesta' => trim($respuesta->text) !== '' ? trim($respuesta->text) : 'No encontré una respuesta para esa pregunta. ¿Puedes reformularla?',
            'resultados' => $this->resultados($respuesta),
            'proveedor' => $respuesta->meta->provider,
            'modelo' => $respuesta->meta->model,
            'costo_usd' => $this->ia->costo($respuesta->meta->model, $respuesta->usage),
            'estado' => 'OK',
        ]);
    }

    /**
     * Historial reciente del usuario (de la conversación indicada, o de la
     * última que tuvo), en orden cronológico.
     *
     * @return Collection<int, IaConsulta>
     */
    public function historial(User $usuario, ?string $conversacionId = null): Collection
    {
        $conversacionId ??= IaConsulta::query()
            ->where('user_id', $usuario->id)
            ->whereNotNull('conversacion_id')
            ->latest('id')
            ->value('conversacion_id');

        if ($conversacionId === null) {
            return collect();
        }

        return IaConsulta::query()
            ->where('user_id', $usuario->id)
            ->where('conversacion_id', $conversacionId)
            ->latest('id')
            ->limit(self::LIMITE_HISTORIAL)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Las últimas consultas del usuario (de todas sus conversaciones), la
     * más reciente primero. Con `$dia` se limita a ese día en hora de la
     * empresa: `created_at` está en UTC y La Paz va 4 horas atrás, así que
     * un día local cruza dos días UTC.
     *
     * @return Collection<int, IaConsulta>
     */
    public function ultimasConsultas(User $usuario, ?CarbonImmutable $dia = null): Collection
    {
        return IaConsulta::query()
            ->select(['id', 'conversacion_id', 'pregunta', 'resultados', 'estado', 'created_at'])
            ->where('user_id', $usuario->id)
            ->when($dia, fn ($q) => $q->whereBetween('created_at', [
                $dia->startOfDay()->utc(),
                $dia->endOfDay()->utc(),
            ]))
            ->latest('id')
            ->limit(self::LIMITE_HISTORIAL)
            ->get();
    }

    /**
     * La conversación que contiene la consulta, para reabrirla desde el
     * historial. Si la conversación es más larga que el límite, se devuelve
     * la ventana de consultas que rodea a la elegida (si no, una pregunta
     * vieja de una conversación larga no aparecería al abrirla). Una consulta
     * sin conversación (la IA no respondió) se devuelve sola.
     *
     * @return Collection<int, IaConsulta>|null null si la consulta no es del usuario
     */
    public function conversacionDe(User $usuario, int $consultaId): ?Collection
    {
        $consulta = IaConsulta::query()->where('user_id', $usuario->id)->find($consultaId);

        if ($consulta === null) {
            return null;
        }

        if ($consulta->conversacion_id === null) {
            return collect([$consulta]);
        }

        $ids = IaConsulta::query()
            ->where('user_id', $usuario->id)
            ->where('conversacion_id', $consulta->conversacion_id)
            ->orderBy('id')
            ->pluck('id');

        $posicion = $ids->search($consulta->id);
        $inicio = max(0, min($posicion - intdiv(self::LIMITE_HISTORIAL, 2), $ids->count() - self::LIMITE_HISTORIAL));

        return IaConsulta::query()
            ->whereIn('id', $ids->slice($inicio, self::LIMITE_HISTORIAL)->all())
            ->orderBy('id')
            ->get();
    }

    /**
     * Resultados de las herramientas que usó el modelo, listos para el
     * panel. Se descartan los fallidos y los que no traen datos dibujables;
     * la búsqueda de catálogo queda solo como paso del "cómo lo calculé".
     *
     * @return list<array<string, mixed>>
     */
    private function resultados(AgentResponse $respuesta): array
    {
        return $respuesta->toolResults
            ->filter(fn ($r) => $r instanceof ToolResult && $r->successful())
            ->map(fn (ToolResult $r) => json_decode($r->text(), true))
            ->filter(fn ($datos) => is_array($datos) && ! isset($datos['error']) && isset($datos['herramienta']))
            ->map(fn (array $datos) => [
                'herramienta' => $datos['herramienta'],
                'titulo' => $datos['titulo'] ?? '',
                'calculo' => $datos['calculo'] ?? '',
                'parametros' => $datos['parametros'] ?? [],
                'tabla' => $datos['herramienta'] === 'BuscarEnCatalogo' ? null : ($datos['tabla'] ?? null),
                'grafico' => $datos['grafico'] ?? null,
            ])
            ->unique(fn ($r) => md5(json_encode([$r['herramienta'], $r['parametros']])))
            ->values()
            ->all();
    }
}
