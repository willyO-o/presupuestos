<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaMaterial\StoreCategoriaMaterialRequest;
use App\Http\Requests\CategoriaMaterial\UpdateCategoriaMaterialRequest;
use App\Models\CategoriaMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CategoriaMaterialController extends Controller
{
    /**
     * Listado paginado, con búsqueda por nombre y filtro por estado.
     * `withQueryString()` mantiene search/estado/page al navegar entre
     * páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $categoriasMaterial = CategoriaMaterial::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('CategoriasMaterial/Index', [
            'categoriasMaterial' => $categoriasMaterial,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Categorías de material',
            'breadcrumbs' => ['Catálogos', 'Categorías de material'],
        ]);
    }

    public function store(StoreCategoriaMaterialRequest $request): RedirectResponse
    {
        CategoriaMaterial::create($request->validated());

        return redirect()->route('categorias-material.index')
            ->with('success', 'Categoría de material creada correctamente.');
    }

    public function update(UpdateCategoriaMaterialRequest $request, CategoriaMaterial $categoriaMaterial): RedirectResponse
    {
        $categoriaMaterial->update($request->validated());

        return redirect()->route('categorias-material.index')
            ->with('success', 'Categoría de material actualizada correctamente.');
    }

    public function destroy(CategoriaMaterial $categoriaMaterial): RedirectResponse
    {
        $categoriaMaterial->delete();

        return redirect()->route('categorias-material.index')
            ->with('success', 'Categoría de material eliminada correctamente.');
    }
}
