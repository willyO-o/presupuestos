<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sucursal\StoreSucursalRequest;
use App\Http\Requests\Sucursal\UpdateSucursalRequest;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SucursalController extends Controller
{
    /**
     * Listado paginado, con búsqueda (nombre/dirección/teléfono) y filtro
     * por estado. `withQueryString()` mantiene search/estado/page al
     * navegar entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $sucursales = Sucursal::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(5)
            ->withQueryString();

        return inertia('Sucursales/Index', [
            'sucursales' => $sucursales,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Sucursales',
            'breadcrumbs' => ['Catálogos', 'Sucursales'],
        ]);
    }

    public function store(StoreSucursalRequest $request): RedirectResponse
    {
        Sucursal::create($request->validated());

        return redirect()->route('sucursales.index')
            ->with('success', 'Sucursal creada correctamente.');
    }

    public function update(UpdateSucursalRequest $request, Sucursal $sucursal): RedirectResponse
    {
        $sucursal->update($request->validated());

        return redirect()->route('sucursales.index')
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        $sucursal->delete();

        return redirect()->route('sucursales.index')
            ->with('success', 'Sucursal eliminada correctamente.');
    }
}
