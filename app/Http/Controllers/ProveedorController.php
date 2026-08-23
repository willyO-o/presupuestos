<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proveedor\StoreProveedorRequest;
use App\Http\Requests\Proveedor\UpdateProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProveedorController extends Controller
{
    /**
     * Listado paginado, con búsqueda (nombre/nit/contacto) y filtro por
     * estado. `withQueryString()` mantiene search/estado/page al navegar
     * entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $proveedores = Proveedor::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Proveedores/Index', [
            'proveedores' => $proveedores,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Proveedores',
            'breadcrumbs' => ['Materiales e Insumos', 'Proveedores'],
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        Proveedor::create($request->validated());

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($request->validated());

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor eliminado correctamente.');
    }
}
