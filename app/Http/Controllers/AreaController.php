<?php

namespace App\Http\Controllers;

use App\Http\Requests\Area\StoreAreaRequest;
use App\Http\Requests\Area\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AreaController extends Controller
{
    /**
     * Listado paginado, con búsqueda por nombre y filtro por estado.
     * `withQueryString()` mantiene search/estado/page al navegar entre
     * páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $areas = Area::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Areas/Index', [
            'areas' => $areas,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Áreas',
            'breadcrumbs' => ['Organización', 'Áreas'],
        ]);
    }

    public function store(StoreAreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return redirect()->route('areas.index')
            ->with('success', 'Área creada correctamente.');
    }

    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return redirect()->route('areas.index')
            ->with('success', 'Área actualizada correctamente.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $area->delete();

        return redirect()->route('areas.index')
            ->with('success', 'Área eliminada correctamente.');
    }
}
