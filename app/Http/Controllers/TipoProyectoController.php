<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoProyecto\StoreTipoProyectoRequest;
use App\Http\Requests\TipoProyecto\UpdateTipoProyectoRequest;
use App\Models\TipoProyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * CRUD de los niveles de complejidad que alimentan el motor de margen
 * (App\Services\Calculo\MotorMargenService). Sustituye la cadena de IF
 * anidados del Excel: los factores y márgenes mínimos son datos editables,
 * sin límite de niveles.
 */
class TipoProyectoController extends Controller
{
    /**
     * Listado paginado con búsqueda por nombre/descripción y filtro por
     * estado. Trae `cotizacion_detalles_count` para poder avisar en la UI
     * cuáles ya tienen historial y no se pueden borrar.
     */
    public function index(Request $request): Response
    {
        $tiposProyecto = TipoProyecto::query()
            ->withCount('cotizacionDetalles')
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->ordenado()
            ->paginate(10)
            ->withQueryString();

        return inertia('TiposProyecto/Index', [
            'tiposProyecto' => $tiposProyecto,
            'filters' => $request->only(['search', 'estado']),
            'config' => [
                'impuestos' => config('margen.impuestos'),
                'semaforo' => config('margen.semaforo'),
            ],
            'pageTitle' => 'Tipos de proyecto',
            'breadcrumbs' => ['Catálogo de Productos', 'Tipos de proyecto'],
        ]);
    }

    public function store(StoreTipoProyectoRequest $request): RedirectResponse
    {
        TipoProyecto::create($this->normalizar($request->validated()));

        return redirect()->route('tipos-proyecto.index')
            ->with('success', 'Tipo de proyecto creado correctamente.');
    }

    public function update(UpdateTipoProyectoRequest $request, TipoProyecto $tipoProyecto): RedirectResponse
    {
        $tipoProyecto->update($this->normalizar($request->validated()));

        return redirect()->route('tipos-proyecto.index')
            ->with('success', 'Tipo de proyecto actualizado correctamente.');
    }

    /**
     * La FK de `cotizacion_detalle` es `restrictOnDelete`, así que borrar un
     * tipo con historial reventaría con un error de base de datos: se avisa
     * antes y se sugiere desactivarlo (queda fuera del selector de nuevas
     * cotizaciones sin perder las que ya lo usaron).
     */
    public function destroy(TipoProyecto $tipoProyecto): RedirectResponse
    {
        if ($tipoProyecto->cotizacionDetalles()->exists()) {
            return redirect()->route('tipos-proyecto.index')
                ->with('error', "«{$tipoProyecto->nombre}» ya se usó en cotizaciones: desactívalo en lugar de eliminarlo.");
        }

        $nombre = $tipoProyecto->nombre;
        $tipoProyecto->delete();

        return redirect()->route('tipos-proyecto.index')
            ->with('success', "Tipo de proyecto «{$nombre}» eliminado correctamente.");
    }

    /**
     * El formulario pide el margen mínimo en PORCENTAJE (50) porque es como
     * lo piensa el usuario; la tabla lo guarda como fracción (0.5000), que es
     * lo que consume el motor de margen.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        return [
            ...$datos,
            'margen_minimo' => round((float) $datos['margen_minimo'] / 100, 4),
        ];
    }
}
