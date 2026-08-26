<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoMaterial\StoreProductoMaterialRequest;
use App\Http\Requests\ProductoMaterial\UpdateProductoMaterialRequest;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductoMaterialController extends Controller
{
    /**
     * Receta de costo (BOM) de un producto: las líneas ya cargadas más los
     * catálogos de materiales/fórmulas activos para el formulario de
     * alta. Es una vista independiente (no un modal) porque es
     * información compleja —líneas repetibles, cada una estática o
     * dinámica— ver skill `xtrapubli-design-system` Regla 5.
     */
    public function index(Producto $producto): Response
    {
        $producto->load('categoriaProducto');

        return inertia('Productos/Receta', [
            'producto' => $producto,
            'lineas' => $producto->productoMateriales()
                ->with(['material.categoriaMaterial', 'formula'])
                ->get(),
            'materiales' => Material::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre', 'unidad_medida', 'precio_unitario']),
            'formulas' => Formula::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre', 'expresion', 'descripcion']),
            'pageTitle' => "Receta — {$producto->nombre}",
            'breadcrumbs' => ['Catálogo de Productos', 'Productos', 'Receta'],
        ]);
    }

    public function store(StoreProductoMaterialRequest $request, Producto $producto): RedirectResponse
    {
        $producto->productoMateriales()->create($request->validated());

        return redirect()->route('productos.materiales.index', $producto)
            ->with('success', 'Material agregado a la receta correctamente.');
    }

    public function update(UpdateProductoMaterialRequest $request, Producto $producto, ProductoMaterial $productoMaterial): RedirectResponse
    {
        $this->assertPerteneceAProducto($producto, $productoMaterial);

        $productoMaterial->update($request->validated());

        return redirect()->route('productos.materiales.index', $producto)
            ->with('success', 'Línea de receta actualizada correctamente.');
    }

    public function destroy(Producto $producto, ProductoMaterial $productoMaterial): RedirectResponse
    {
        $this->assertPerteneceAProducto($producto, $productoMaterial);

        $productoMaterial->delete();

        return redirect()->route('productos.materiales.index', $producto)
            ->with('success', 'Material quitado de la receta correctamente.');
    }

    /**
     * La ruta anidada no scopea el binding de `productoMaterial` a
     * `producto` automáticamente — sin esto, alguien podría editar/borrar
     * una línea de otro producto cambiando el {producto} de la URL.
     */
    private function assertPerteneceAProducto(Producto $producto, ProductoMaterial $productoMaterial): void
    {
        if ($productoMaterial->producto_id !== $producto->id) {
            throw new NotFoundHttpException;
        }
    }
}
