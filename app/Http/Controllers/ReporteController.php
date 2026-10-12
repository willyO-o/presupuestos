<?php

namespace App\Http\Controllers;

use App\Services\Reporte\AlertasNegocioService;
use App\Services\Reporte\CostosMaterialesService;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\PresupuestosRentabilidadService;
use App\Services\Reporte\ReporteFinancieroService;
use App\Services\Reporte\ReporteProduccionService;
use App\Services\Reporte\ResumenDashboardService;
use App\Services\Reporte\SimuladorDecisionesService;
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

    /**
     * Inteligencia de negocios — Demanda y ventas (la pestaña original).
     *
     * Las cinco pestañas del BI comparten permiso (`reportes.bi`) y filtro de
     * fechas: cada una es su propia ruta para que solo calcule lo que muestra.
     */
    public function bi(Request $request, InteligenciaNegociosService $bi): Response
    {
        return $this->paginaBi($request, 'Reportes/Bi', 'Demanda y ventas',
            fn (PeriodoReporte $periodo) => $bi->datos($request->user(), $periodo));
    }

    public function biCostos(Request $request, CostosMaterialesService $costos): Response
    {
        return $this->paginaBi($request, 'Reportes/BiCostos', 'Costos y materiales',
            fn (PeriodoReporte $periodo) => $costos->datos($request->user(), $periodo));
    }

    public function biRentabilidad(Request $request, PresupuestosRentabilidadService $presupuestos): Response
    {
        return $this->paginaBi($request, 'Reportes/BiRentabilidad', 'Presupuestos y rentabilidad',
            fn (PeriodoReporte $periodo) => $presupuestos->datos($request->user(), $periodo));
    }

    public function biAlertas(Request $request, AlertasNegocioService $alertas): Response
    {
        return $this->paginaBi($request, 'Reportes/BiAlertas', 'Alertas',
            fn (PeriodoReporte $periodo) => $alertas->datos($request->user(), $periodo));
    }

    public function biSimulador(Request $request, SimuladorDecisionesService $simulador): Response
    {
        return $this->paginaBi($request, 'Reportes/BiSimulador', 'Simulador de decisiones',
            fn (PeriodoReporte $periodo) => $simulador->datos($request->user(), $periodo));
    }

    /**
     * Arma una pestaña del BI: periodo desde la query string, datos del
     * servicio y el encabezado común.
     *
     * @param  callable(PeriodoReporte): array<string, mixed>  $datos
     */
    private function paginaBi(Request $request, string $componente, string $seccion, callable $datos): Response
    {
        $periodo = PeriodoReporte::desdeFiltros(
            $request->query('desde'),
            $request->query('hasta'),
            config('bi.meses_por_defecto'),
        );

        return inertia($componente, [
            'datos' => $datos($periodo),
            'filters' => $request->only(['desde', 'hasta']),
            'pageTitle' => 'Inteligencia de negocios',
            'breadcrumbs' => ['Reportes', 'Inteligencia de negocios', $seccion],
        ]);
    }
}
