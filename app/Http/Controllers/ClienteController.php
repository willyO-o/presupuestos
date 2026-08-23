<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ClienteController extends Controller
{
    /**
     * Listado paginado, con búsqueda (razón social/NIT/contacto) y filtro
     * por estado. `withQueryString()` mantiene search/estado/page al
     * navegar entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $clientes = Cliente::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('razon_social')
            ->paginate(10)
            ->withQueryString();

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Clientes',
            'breadcrumbs' => ['Clientes', 'Clientes'],
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        Cliente::create($request->validated());

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }
}
