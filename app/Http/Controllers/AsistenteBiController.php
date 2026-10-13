<?php

namespace App\Http\Controllers;

use App\Ai\AsistenteBiService;
use App\Ai\LimiteDePreguntas;
use App\Ai\ServicioIa;
use App\Http\Requests\Reporte\HistorialAsistenteRequest;
use App\Http\Requests\Reporte\PreguntarAsistenteRequest;
use App\Models\IaConsulta;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints JSON del asistente de BI (panel flotante del layout). Responden
 * JSON y no visitas de Inertia: el panel conversa sin recargar la página en
 * la que está el usuario (mismo criterio que FormulaController::probar).
 *
 * Las fechas salen también como `dia`/`hora` en hora de la empresa
 * (config ia.zona_horaria): el panel agrupa y rotula por día con esos
 * valores, así coincide con el filtro por fecha del servidor aunque el
 * navegador tenga otra zona horaria.
 */
class AsistenteBiController extends Controller
{
    public function __construct(
        private readonly AsistenteBiService $asistente,
        private readonly ServicioIa $ia,
    ) {}

    /**
     * Conversación para el panel: la de `consulta_id` (abierta desde el
     * historial), la de `conversacion_id`, o la última del usuario.
     */
    public function historial(Request $request): JsonResponse
    {
        $consultaId = $request->integer('consulta_id');

        if ($consultaId > 0) {
            $consultas = $this->asistente->conversacionDe($request->user(), $consultaId);

            abort_if($consultas === null, 404, 'No se encontró esa consulta en tu historial.');
        } else {
            $conversacion = $request->query('conversacion_id');
            $consultas = $this->asistente->historial($request->user(), is_string($conversacion) ? $conversacion : null);
        }

        return response()->json([
            'estado' => $this->ia->estado('asistente'),
            'hoy' => $this->hoy(),
            'consultas' => $consultas->map(fn (IaConsulta $c) => $this->presentar($c))->values(),
        ]);
    }

    /**
     * Historial del usuario: sus últimas consultas, o las de un día.
     */
    public function consultas(HistorialAsistenteRequest $request): JsonResponse
    {
        $fecha = $request->validated('fecha');
        $dia = $fecha ? CarbonImmutable::parse($fecha, config('ia.zona_horaria')) : null;

        return response()->json([
            'hoy' => $this->hoy(),
            'fecha' => $fecha,
            'limite' => AsistenteBiService::LIMITE_HISTORIAL,
            'consultas' => $this->asistente->ultimasConsultas($request->user(), $dia)
                ->map(fn (IaConsulta $c) => $this->resumir($c))
                ->values(),
        ]);
    }

    public function preguntar(PreguntarAsistenteRequest $request): JsonResponse
    {
        try {
            $consulta = $this->asistente->preguntar(
                $request->user(),
                trim($request->validated('pregunta')),
                $request->validated('conversacion_id'),
            );
        } catch (LimiteDePreguntas $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json([
            'estado' => $this->ia->estado('asistente'),
            'consulta' => $this->presentar($consulta),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(IaConsulta $consulta): array
    {
        return [
            'id' => $consulta->id,
            'conversacion_id' => $consulta->conversacion_id,
            'pregunta' => $consulta->pregunta,
            'respuesta' => $consulta->respuesta,
            'resultados' => $consulta->resultados ?? [],
            'estado' => $consulta->estado,
            'proveedor' => $consulta->proveedor,
            'modelo' => $consulta->modelo,
            'fecha' => $consulta->created_at?->toIso8601String(),
            ...$this->momento($consulta),
        ];
    }

    /**
     * Fila del historial: sin el texto de la respuesta ni los datos de los
     * gráficos (se piden al abrir la consulta), solo cuántos trae.
     *
     * @return array{id: int, conversacion_id: string|null, pregunta: string, estado: string, graficos: int, tablas: int, fecha: string|null, dia: string|null, hora: string|null}
     */
    private function resumir(IaConsulta $consulta): array
    {
        $resultados = collect($consulta->resultados ?? []);

        return [
            'id' => $consulta->id,
            'conversacion_id' => $consulta->conversacion_id,
            'pregunta' => $consulta->pregunta,
            'estado' => $consulta->estado,
            'graficos' => $resultados->filter(fn (array $r) => filled($r['grafico'] ?? null))->count(),
            'tablas' => $resultados->filter(fn (array $r) => filled($r['tabla'] ?? null))->count(),
            'fecha' => $consulta->created_at?->toIso8601String(),
            ...$this->momento($consulta),
        ];
    }

    /**
     * @return array{dia: string|null, hora: string|null}
     */
    private function momento(IaConsulta $consulta): array
    {
        $local = $consulta->created_at?->copy()->setTimezone(config('ia.zona_horaria'));

        return [
            'dia' => $local?->toDateString(),
            'hora' => $local?->format('H:i'),
        ];
    }

    private function hoy(): string
    {
        return now(config('ia.zona_horaria'))->toDateString();
    }
}
