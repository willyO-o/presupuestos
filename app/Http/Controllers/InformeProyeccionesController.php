<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reporte\InformeProyeccionesRequest;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Pdf\RespuestaPdf;
use App\Services\Reporte\InformeProyeccionesCsv;
use App\Services\Reporte\InformeProyeccionesService;
use Illuminate\Http\Response;

/**
 * Informe de proyecciones del BI en PDF (para leer y presentar) y en CSV
 * (para reprocesar en una planilla).
 *
 * Solo autoriza y delega: los datos los arma `InformeProyeccionesService` con
 * los mismos servicios (y el mismo alcance por sucursal) que las pestañas,
 * y el PDF sale de `GeneradorPdf` como todos los documentos del sistema.
 */
class InformeProyeccionesController extends Controller
{
    public function __construct(
        private readonly InformeProyeccionesService $informes,
    ) {}

    /**
     * Como los demás PDFs del panel, sale como VISTA PREVIA (se abre en una
     * pestaña); `?descargar=1` lo baja al disco.
     */
    public function pdf(InformeProyeccionesRequest $request, GeneradorPdf $generador): RespuestaPdf
    {
        $pdf = $generador->informeProyecciones($this->armar($request));

        return $request->boolean('descargar') ? $pdf : $generador->previsualizar($pdf);
    }

    public function csv(InformeProyeccionesRequest $request): Response
    {
        $informe = $this->armar($request);
        $periodo = $informe['periodo'];

        return response(InformeProyeccionesCsv::generar($informe), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="xtrapubli-informe-proyecciones-%s-al-%s.csv"',
                $periodo['desde'],
                $periodo['hasta'],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function armar(InformeProyeccionesRequest $request): array
    {
        return $this->informes->armar($request->user(), $request->periodo(), $request->secciones());
    }
}
