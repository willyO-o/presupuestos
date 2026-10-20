<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\Imagen\ConvierteImagenAJpgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ConvierteImagenAJpgService $convierteImagen,
    ) {}

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
        $cliente = Cliente::create($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $cliente->update(['logo' => $this->convierteImagen->guardar($request->file('logo'), 'clientes')]);
        }

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    /**
     * Alta rápida desde el modal embebido en otro formulario (por ahora,
     * la línea "Cliente" de Cotizaciones/Partials/CotizacionForm.vue): misma
     * validación y permiso que `store`, pero responde JSON con el cliente
     * recién creado en vez de redirigir — así el formulario que lo abrió
     * puede seguir donde estaba y preseleccionarlo. Sin logo: es JSON, no
     * multipart; el logo se agrega después desde Clientes.
     */
    public function storeRapido(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->safe()->except('logo'));

        return response()->json($cliente);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->fill($request->safe()->except('logo'));

        // Logo nuevo: reemplaza al anterior y borra su archivo, para no
        // acumular imágenes sin dueño en el disco.
        if ($request->hasFile('logo')) {
            $this->convierteImagen->borrar($cliente->logo);
            $cliente->logo = $this->convierteImagen->guardar($request->file('logo'), 'clientes');
        }

        $cliente->save();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $logo = $cliente->logo;
        $cliente->delete();
        // Después del delete: si la base lo impide (cliente con historial),
        // el logo no se pierde.
        $this->convierteImagen->borrar($logo);

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }
}
