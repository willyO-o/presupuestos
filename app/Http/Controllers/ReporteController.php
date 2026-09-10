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
            'resumen' => $resumen->resumen($request->user()),
        ]);
    }

    public function financiero(Request $request, ReporteFinancieroService $financiero): Response
    {
        return inertia('Reportes/Financiero', [
            'datos' => $financiero->datos(
                $request->user(),
                $request->query('desde'),
                $request->query('hasta'),
            ),
            'filters' => $request->only(['desde', 'hasta']),
            'pageTitle' => 'Reporte financiero',
            'breadcrumbs' => ['Reportes', 'Financiero'],
        ]);
    }

    public function produccion(Request $request, ReporteProduccionService $produccion): Response
    {
        return inertia('Reportes/Produccion', [
            'datos' => $produccion->datos($request->user()),
            'pageTitle' => 'Reporte de producción',
            'breadcrumbs' => ['Reportes', 'Producción'],
        ]);
    }

    public function bi(Request $request, InteligenciaNegociosService $bi): Response
    {
        return inertia('Reportes/Bi', [
            'datos' => $bi->datos($request->user()),
            'pageTitle' => 'Inteligencia de negocios',
            'breadcrumbs' => ['Reportes', 'Inteligencia de negocios'],
        ]);
    }
}
