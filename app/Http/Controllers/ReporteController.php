<?php

namespace App\Http\Controllers;

use App\Ai\ServicioIa;
use App\Http\Requests\Reporte\PanoramaRequest;
use App\Http\Requests\Reporte\ReportePdfRequest;
use App\Models\BiAnomalia;
use App\Models\BiResumen;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Pdf\RespuestaPdf;
use App\Services\Reporte\AlertasNegocioService;
use App\Services\Reporte\CostosMaterialesService;
use App\Services\Reporte\InformeReportesService;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PanoramaService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\PreguntasNegocioService;
use App\Services\Reporte\PresupuestosRentabilidadService;
use App\Services\Reporte\ReporteFinancieroService;
use App\Services\Reporte\ReporteProduccionService;
use App\Services\Reporte\ResumenDashboardService;
use App\Services\Reporte\SimuladorDecisionesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Response;

class ReporteController extends Controller
{
    public function dashboard(Request $request, ResumenDashboardService $resumen): Response|RedirectResponse
    {
        // Un cliente no ve el panel interno: se lo manda a su portal.
        if ($request->user()->hasRole('cliente')) {
            return redirect()->route('portal.cotizaciones');
        }

        $primerNombre = strtok(trim($request->user()->name), ' ') ?: $request->user()->name;

        return inertia('Dashboard', [
            'resumen' => $resumen->resumen($request->user()),
            'pageTitle' => "¡Hola, {$primerNombre}!",
            'pageSubtitle' => 'Aquí tienes un resumen general de la actividad de tu negocio.',
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
     * El reporte financiero en PDF, con el mismo periodo que se ve en
     * pantalla. Como los demás PDFs del panel sale como VISTA PREVIA;
     * `?descargar=1` lo baja al disco.
     */
    public function financieroPdf(ReportePdfRequest $request, InformeReportesService $informes, GeneradorPdf $generador): RespuestaPdf
    {
        $pdf = $generador->reporteFinanciero(
            $informes->financiero($request->user(), $request->validated('desde'), $request->validated('hasta')),
        );

        return $request->boolean('descargar') ? $pdf : $generador->previsualizar($pdf);
    }

    public function produccionPdf(ReportePdfRequest $request, InformeReportesService $informes, GeneradorPdf $generador): RespuestaPdf
    {
        $pdf = $generador->reporteProduccion($informes->produccion($request->user()));

        return $request->boolean('descargar') ? $pdf : $generador->previsualizar($pdf);
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

    public function biPreguntas(Request $request, PreguntasNegocioService $preguntas): Response
    {
        return $this->paginaBi($request, 'Reportes/BiPreguntas', 'Preguntas para decidir',
            fn (PeriodoReporte $periodo) => $preguntas->datos($request->user(), $periodo));
    }

    public function biRentabilidad(Request $request, PresupuestosRentabilidadService $presupuestos): Response
    {
        return $this->paginaBi($request, 'Reportes/BiRentabilidad', 'Presupuestos y rentabilidad',
            fn (PeriodoReporte $periodo) => $presupuestos->datos($request->user(), $periodo));
    }

    public function biAlertas(Request $request, AlertasNegocioService $alertas): Response
    {
        return $this->paginaBi($request, 'Reportes/BiAlertas', 'Alertas',
            fn (PeriodoReporte $periodo) => [
                ...$alertas->datos($request->user(), $periodo),
                'anomalias' => $this->anomalias($request),
            ]);
    }

    /**
     * Resumen semanal redactado (pestaña "Resumen"). No usa el filtro de
     * fechas: cada resumen ya es una semana cerrada, y se elige del historial.
     */
    public function biResumen(Request $request, ServicioIa $ia): Response
    {
        $usuario = $request->user();

        $historial = BiResumen::query()
            ->visibleResumenPara($usuario)
            ->with('sucursal:id,nombre')
            ->orderByDesc('semana_inicio')
            ->orderByRaw('sucursal_id IS NULL DESC')
            ->limit(60)
            ->get();

        $elegido = $historial->firstWhere('id', (int) $request->query('resumen')) ?? $historial->first();

        return inertia('Reportes/BiResumen', [
            'datos' => [
                'resumen' => $elegido ? $this->presentarResumen($elegido) : null,
                'historial' => $historial->map(fn (BiResumen $r) => [
                    'id' => $r->id,
                    'semana' => $r->hechos['semana']['etiqueta'] ?? $r->semana_inicio->toDateString(),
                    'alcance' => $r->sucursal?->nombre ?? 'Toda la empresa',
                    'titular' => $r->contenido['titular'] ?? '',
                    'origen' => $r->origen,
                ])->values(),
                'puede_generar' => $usuario->can('reportes.asistente'),
                'ia' => $ia->estado('resumen_semanal'),
            ],
            'filters' => [],
            'pageTitle' => 'Inteligencia de negocios',
            'breadcrumbs' => ['Reportes', 'Inteligencia de negocios', 'Resumen semanal'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarResumen(BiResumen $resumen): array
    {
        return [
            'id' => $resumen->id,
            'alcance' => $resumen->hechos['alcance'] ?? 'Toda la empresa',
            // Mismo rótulo que el historial, para cambiar de alcance o de semana.
            'alcance_corto' => $resumen->sucursal?->nombre ?? 'Toda la empresa',
            'semana' => $resumen->hechos['semana'] ?? null,
            'kpis' => $resumen->hechos['kpis'] ?? [],
            'contexto' => $resumen->hechos['contexto'] ?? [],
            'hay_anio_anterior' => $resumen->hechos['hay_anio_anterior'] ?? false,
            'contenido' => $resumen->contenido,
            'origen' => $resumen->origen,
            'proveedor' => $resumen->proveedor,
            'modelo' => $resumen->modelo,
            'generado' => $resumen->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Anomalías visibles para el usuario, filtradas por estado (por defecto
     * las nuevas), con el conteo por estado para el filtro.
     *
     * @return array<string, mixed>
     */
    private function anomalias(Request $request): array
    {
        $usuario = $request->user();
        $estado = in_array($request->query('anomalias'), [...BiAnomalia::ESTADOS, 'TODAS'], true)
            ? $request->query('anomalias')
            : 'NUEVA';

        $base = fn () => BiAnomalia::query()->visibleAnomaliaPara($usuario);

        return [
            'estado' => $estado,
            'conteo' => $base()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
            'items' => $base()
                ->when($estado !== 'TODAS', fn ($q) => $q->where('estado', $estado))
                ->with('sucursal:id,nombre', 'revisor:id,name')
                ->orderByRaw("CASE severidad WHEN 'ALTA' THEN 0 ELSE 1 END")
                ->latest('detectada_en')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (BiAnomalia $a) => [
                    'id' => $a->id,
                    'tipo' => $a->tipo,
                    'tipo_etiqueta' => BiAnomalia::TIPOS[$a->tipo] ?? $a->tipo,
                    'severidad' => $a->severidad,
                    'titulo' => $a->titulo,
                    'detalle' => $a->detalle,
                    'que_revisar' => $a->que_revisar,
                    'alcance' => $a->sucursal?->nombre ?? 'Toda la empresa',
                    'url' => $a->url,
                    'redaccion' => $a->redaccion,
                    'estado' => $a->estado,
                    'detectada_en' => $a->detectada_en->toDateString(),
                    'revisor' => $a->revisor?->name,
                ])
                ->values(),
            'ultima_deteccion' => ($ultima = $base()->max('created_at')) ? Carbon::parse($ultima)->toIso8601String() : null,
            'puede_gestionar' => $usuario->can('reportes.asistente'),
        ];
    }

    /**
     * Panorama del BI: KPIs, tendencias, tipo de producto, embudo y rankings
     * con filtros globales (fechas, familia/categoría, cliente, sucursal).
     * Es la pestaña con la que abre el BI.
     */
    public function biPanorama(PanoramaRequest $request, PanoramaService $panorama): Response
    {
        return inertia('Reportes/BiPanorama', [
            'datos' => $panorama->datos($request->user(), $request->periodo(), $request->filtros()),
            'filters' => $request->only(['desde', 'hasta', 'familia_id', 'categoria_id', 'cliente_id', 'sucursal_id']),
            'pageTitle' => 'Inteligencia de negocios',
            'breadcrumbs' => ['Reportes', 'Inteligencia de negocios', 'Panorama'],
        ]);
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
