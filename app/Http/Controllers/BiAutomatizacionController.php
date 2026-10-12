<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reporte\ActualizarAnomaliaRequest;
use App\Models\BiAnomalia;
use App\Services\Reporte\DeteccionAnomaliasService;
use App\Services\Reporte\ResumenSemanalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Acciones manuales sobre lo que el sistema genera solo: correr la detección
 * de anomalías o el resumen semanal fuera de horario, y marcar una anomalía
 * como revisada o descartada. Todo bajo `reportes.asistente` (delegable).
 */
class BiAutomatizacionController extends Controller
{
    public function generarResumen(Request $request, ResumenSemanalService $resumenes): RedirectResponse
    {
        $semana = $resumenes->ultimaSemanaCompleta();
        $generados = $resumenes->generarSemana($semana, $request->user());

        $conIa = $generados->where('origen', 'IA')->count();

        return redirect()->route('reportes.bi.resumen')->with('success', $generados->isEmpty()
            ? 'No hubo movimiento en la semana para resumir.'
            : "Resumen de la semana generado ({$generados->count()} en total, {$conIa} redactados por IA).");
    }

    public function detectarAnomalias(Request $request, DeteccionAnomaliasService $deteccion): RedirectResponse
    {
        $nuevas = $deteccion->ejecutar(solicitante: $request->user());

        return redirect()->to(route('reportes.bi.alertas', $request->only(['desde', 'hasta'])).'#anomalias')
            ->with('success', $nuevas->isEmpty()
                ? 'Detección completada: no hay anomalías nuevas.'
                : "Detección completada: {$nuevas->count()} anomalías nuevas.");
    }

    public function actualizarAnomalia(ActualizarAnomaliaRequest $request, BiAnomalia $anomalia): RedirectResponse
    {
        // Misma regla que el listado: solo se marca lo que el usuario puede ver.
        abort_unless(
            BiAnomalia::query()->whereKey($anomalia->id)->visibleAnomaliaPara($request->user())->exists(),
            403,
        );

        $estado = $request->validated('estado');

        $anomalia->update([
            'estado' => $estado,
            'revisada_por' => $estado === 'NUEVA' ? null : $request->user()->id,
            'revisada_en' => $estado === 'NUEVA' ? null : now(),
        ]);

        return back()->with('success', match ($estado) {
            'REVISADA' => 'Anomalía marcada como revisada.',
            'DESCARTADA' => 'Anomalía descartada.',
            default => 'Anomalía reabierta.',
        });
    }
}
