<?php

namespace App\Http\Controllers;

use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\ReporteFinancieroService;
use App\Services\Reporte\ReporteProduccionService;
use App\Services\Reporte\ResumenDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ReporteController extends Controller
{
    public function dashboard(Request $request, ResumenDashboardService $resumen): Response|RedirectResponse
    {
        // Un cliente no ve el panel interno: se lo manda a su portal.
        if ($request->user()->hasRole('cliente')) {
            return redirect()->route('portal.cotizaciones');
        }

        return inertia('Dashboard', [
            'resumen' => $resumen->resumen(),
        ]);
    }

    public function financiero(Request $request, ReporteFinancieroService $financiero): Response
    {
        return inertia('Reportes/Financiero', [
            'datos' => $financiero->datos($request->query('desde'), $request->query('hasta')),
            'filters' => $request->only(['desde', 'hasta']),
            'pageTitle' => 'Reporte financiero',
            'breadcrumbs' => ['Reportes', 'Financiero'],
        ]);
    }

    public function produccion(ReporteProduccionService $produccion): Response
    {
        return inertia('Reportes/Produccion', [
            'datos' => $produccion->datos(),
            'pageTitle' => 'Reporte de producción',
            'breadcrumbs' => ['Reportes', 'Producción'],
        ]);
    }

    public function bi(InteligenciaNegociosService $bi): Response
    {
        return inertia('Reportes/Bi', [
            'datos' => $bi->datos(),
            'pageTitle' => 'Inteligencia de negocios',
            'breadcrumbs' => ['Reportes', 'Inteligencia de negocios'],
        ]);
    }
}
