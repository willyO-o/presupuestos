<?php

namespace App\Http\Controllers;

use App\Http\Requests\Producto\StoreProductoRequest;
use App\Http\Requests\Producto\UpdateProductoRequest;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductoController extends Controller
{
    /**
     * Listado paginado, con búsqueda por nombre, filtro por categoría y por
     * estado. `withQueryString()` mantiene search/categoria/estado/page al
     * navegar entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $productos = Producto::query()
            ->with('categoriaProducto')
            ->search($request->query('search'))
            ->categoria($request->query('categoria'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Productos/Index', [
            'productos' => $productos,
            'categoriasProducto' => CategoriaProducto::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search', 'categoria', 'estado']),
            'pageTitle' => 'Productos',
            'breadcrumbs' => ['Catálogo de Productos', 'Productos'],
        ]);
    }

    public function store(StoreProductoRequest $request): RedirectResponse
    {
        Producto::create($request->validated());

        return redirect()->route('productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->delete();

        return redirect()->route('productos.index')
            ->with('success', 'Producto eliminado correctamente.');
    }
}
