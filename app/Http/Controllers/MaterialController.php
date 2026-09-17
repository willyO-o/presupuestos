<?php

namespace App\Http\Controllers;

use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Models\CategoriaMaterial;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
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
            ->conStockBajo($request->boolean('stock_bajo'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Materiales/Index', [
            'materiales' => $materiales,
            'categoriasMaterial' => CategoriaMaterial::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'stockBajoTotal' => Material::query()->estado('ACTIVO')->conStockBajo()->count(),
            'filters' => $request->only(['search', 'categoria', 'estado', 'stock_bajo']),
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

    /**
     * Alta rápida desde el modal embebido en otro formulario (la línea de
     * Compras, el registro de consumo en Pedidos y la receta de un
     * producto): misma validación y permiso que `store`, pero responde JSON
     * en vez de redirigir — ver ClienteController::storeRapido.
     */
    public function storeRapido(StoreMaterialRequest $request): JsonResponse
    {
        $material = Material::create($request->validated());

        return response()->json($material);
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
