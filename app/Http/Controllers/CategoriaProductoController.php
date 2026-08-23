<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaProducto\StoreCategoriaProductoRequest;
use App\Http\Requests\CategoriaProducto\UpdateCategoriaProductoRequest;
use App\Models\CategoriaProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CategoriaProductoController extends Controller
{
    /**
     * Listado paginado, con búsqueda por nombre y filtro por estado.
     * `withQueryString()` mantiene search/estado/page al navegar entre
     * páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $categoriasProducto = CategoriaProducto::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('CategoriasProducto/Index', [
            'categoriasProducto' => $categoriasProducto,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Categorías de producto',
            'breadcrumbs' => ['Catálogos', 'Categorías de producto'],
        ]);
    }

    public function store(StoreCategoriaProductoRequest $request): RedirectResponse
    {
        CategoriaProducto::create($request->validated());

        return redirect()->route('categorias-producto.index')
            ->with('success', 'Categoría de producto creada correctamente.');
    }

    public function update(UpdateCategoriaProductoRequest $request, CategoriaProducto $categoriaProducto): RedirectResponse
    {
        $categoriaProducto->update($request->validated());

        return redirect()->route('categorias-producto.index')
            ->with('success', 'Categoría de producto actualizada correctamente.');
    }

    public function destroy(CategoriaProducto $categoriaProducto): RedirectResponse
    {
        $categoriaProducto->delete();

        return redirect()->route('categorias-producto.index')
            ->with('success', 'Categoría de producto eliminada correctamente.');
    }
}
