<?php

namespace App\Http\Controllers;

use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Models\CategoriaMaterial;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MaterialController extends Controller
{
    /**
     * Listado paginado, con búsqueda (nombre/presentación), filtro por
     * categoría y por estado. `withQueryString()` mantiene
     * search/categoria/estado/page al navegar entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $materiales = Material::query()
            ->with('categoriaMaterial')
            ->search($request->query('search'))
            ->categoria($request->query('categoria'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Materiales/Index', [
            'materiales' => $materiales,
            'categoriasMaterial' => CategoriaMaterial::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search', 'categoria', 'estado']),
            'pageTitle' => 'Materiales',
            'breadcrumbs' => ['Materiales e Insumos', 'Materiales'],
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        Material::create($request->validated());

        return redirect()->route('materiales.index')
            ->with('success', 'Material creado correctamente.');
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $material->update($request->validated());

        return redirect()->route('materiales.index')
            ->with('success', 'Material actualizado correctamente.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()->route('materiales.index')
            ->with('success', 'Material eliminado correctamente.');
    }
}
