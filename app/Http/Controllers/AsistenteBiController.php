<?php

namespace App\Http\Controllers;

use App\Ai\AsistenteBiService;
use App\Ai\LimiteDePreguntas;
use App\Ai\ServicioIa;
use App\Http\Requests\Reporte\PreguntarAsistenteRequest;
use App\Models\IaConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints JSON del asistente de BI (panel flotante del layout). Responden
 * JSON y no visitas de Inertia: el panel conversa sin recargar la página en
 * la que está el usuario (mismo criterio que FormulaController::probar).
 */
class AsistenteBiController extends Controller
{
    public function __construct(
        private readonly AsistenteBiService $asistente,
        private readonly ServicioIa $ia,
    ) {}

    public function historial(Request $request): JsonResponse
    {
        $conversacion = $request->query('conversacion_id');

        return response()->json([
            'estado' => $this->ia->estado('asistente'),
            'consultas' => $this->asistente->historial($request->user(), is_string($conversacion) ? $conversacion : null)
                ->map(fn (IaConsulta $c) => $this->presentar($c))
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
        ];
    }
}
