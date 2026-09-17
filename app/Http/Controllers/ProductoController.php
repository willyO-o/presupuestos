<?php

namespace App\Http\Controllers;

use App\Http\Requests\Producto\StoreProductoRequest;
use App\Http\Requests\Producto\UpdateProductoRequest;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Services\Imagen\ConvierteImagenAJpgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductoController extends Controller
{
    public function __construct(
        private readonly ConvierteImagenAJpgService $convierteImagen,
    ) {}

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
        $producto = Producto::create($request->safe()->except('imagen'));

        if ($request->hasFile('imagen')) {
            $producto->imagen = $this->convierteImagen->guardar($request->file('imagen'), 'productos');
            $producto->save();
        }

        return redirect()->route('productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    /**
     * Alta rápida desde el modal embebido en la línea "Producto" de
     * Cotizaciones/Partials/CotizacionForm.vue: misma validación y permiso
     * que `store`, pero responde JSON en vez de redirigir — ver
     * ClienteController::storeRapido. Sin imagen: es JSON, no multipart: la
     * imagen se agrega después desde Productos si hace falta.
     */
    public function storeRapido(StoreProductoRequest $request): JsonResponse
    {
        $producto = Producto::create($request->safe()->except('imagen'));

        return response()->json($producto);
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->fill($request->safe()->except('imagen'));

        if ($request->hasFile('imagen')) {
            $this->convierteImagen->borrar($producto->imagen);
            $producto->imagen = $this->convierteImagen->guardar($request->file('imagen'), 'productos');
        }

        $producto->save();

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $this->convierteImagen->borrar($producto->imagen);
        $producto->delete();

        return redirect()->route('productos.index')
            ->with('success', 'Producto eliminado correctamente.');
    }
}
