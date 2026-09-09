<?php

namespace App\Http\Controllers;

use App\Models\CotizacionPublica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Bandeja de las estimaciones que llegan por el cotizador público
 * (`/cotizador`, tabla `cotizacion_publica`).
 *
 * Sin esta pantalla el cotizador sería un formulario que no lleva a ninguna
 * parte: el visitante se va con un código y en la empresa nadie sabe que
 * existe. Acá es donde ventas lo busca cuando esa persona escribe por
 * WhatsApp diciendo "tengo la cotización WEB-...".
 *
 * Solo se leen y se marcan: no se crean ni se editan montos. Lo que un
 * visitante pidió es un hecho, y el presupuesto formal se emite aparte, en
 * Cotizaciones — al hacerlo se enlaza con `cotizacion_id` y la solicitud
 * queda CONVERTIDA.
 */
class SolicitudWebController extends Controller
{
    /**
     * Listado paginado con búsqueda por código/nombre/empresa/teléfono y
     * filtro por estado. Por defecto muestra TODO y no solo las nuevas: la
     * bandeja se usa sobre todo para buscar un código concreto que el cliente
     * acaba de dictar por teléfono.
     */
    public function index(Request $request): Response
    {
        $solicitudes = CotizacionPublica::query()
            ->with('cotizacion:id,codigo_verificacion')
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return inertia('SolicitudesWeb/Index', [
            'solicitudes' => $solicitudes,
            'resumen' => $this->resumen(),
            'estados' => CotizacionPublica::ESTADOS,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Solicitudes del sitio web',
            'breadcrumbs' => ['Ventas', 'Solicitudes del sitio web'],
        ]);
    }

    /**
     * Marca en qué punto está la solicitud.
     *
     * CONVERTIDA no se pone a mano: la escribe el flujo que emite el
     * presupuesto formal, junto con `cotizacion_id`. Dejar que alguien la
     * marque desde acá crearía solicitudes "convertidas" sin cotización
     * detrás, que es justo lo que hace inútil un estado.
     */
    public function actualizarEstado(Request $request, CotizacionPublica $solicitudWeb): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['NUEVA', 'CONTACTADA', 'DESCARTADA'])],
        ]);

        if ($solicitudWeb->estado === 'CONVERTIDA') {
            return redirect()->back()
                ->with('error', 'Esta solicitud ya generó un presupuesto formal: su estado no se cambia a mano.');
        }

        $solicitudWeb->update($datos);

        return redirect()->back()
            ->with('success', "Solicitud {$solicitudWeb->codigo} marcada como ".strtolower($datos['estado']).'.');
    }

    /**
     * Contadores de la bandeja. `vencidas` son las que siguen sin atender y
     * además se les pasó la vigencia: son las que peor quedan si el cliente
     * llama, porque el precio que vio ya no vale.
     *
     * @return array<string, int>
     */
    private function resumen(): array
    {
        return [
            'nuevas' => CotizacionPublica::query()->where('estado', 'NUEVA')->count(),
            'vencidas' => CotizacionPublica::query()
                ->where('estado', 'NUEVA')
                ->whereDate('fecha_vencimiento', '<', today())
                ->count(),
            'contactadas' => CotizacionPublica::query()->where('estado', 'CONTACTADA')->count(),
            'convertidas' => CotizacionPublica::query()->where('estado', 'CONVERTIDA')->count(),
        ];
    }
}
