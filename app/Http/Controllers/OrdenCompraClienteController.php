<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrdenCompraCliente\StoreOrdenCompraClienteRequest;
use App\Http\Requests\OrdenCompraCliente\UpdateOrdenCompraClienteRequest;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class OrdenCompraClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $ordenes = OrdenCompraCliente::query()
            ->with(['cliente:id,razon_social', 'pedido:id,numero_pedido'])
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return inertia('OrdenesCompraCliente/Index', [
            'ordenes' => $ordenes,
            'pedidosSinOc' => Pedido::query()
                ->whereDoesntHave('ordenCompra')
                ->where('estado', '!=', 'CANCELADO')
                ->with('cotizacion:id,cliente_id')
                ->orderByDesc('fecha_pedido')
                ->get(['id', 'numero_pedido', 'total', 'cotizacion_id']),
            'estados' => OrdenCompraCliente::ESTADOS,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Órdenes de compra de cliente',
            'breadcrumbs' => ['Ventas', 'Órdenes de compra'],
        ]);
    }

    public function store(StoreOrdenCompraClienteRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $pedido = Pedido::with('cotizacion:id,cliente_id')->findOrFail($datos['pedido_id']);

        OrdenCompraCliente::create([
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cotizacion->cliente_id,
            'numero_oc' => $datos['numero_oc'],
            'fecha' => $datos['fecha'],
            'monto_total' => $datos['monto_total'],
            'condicion_pago' => $datos['condicion_pago'] ?? null,
            'archivo_pdf' => $request->hasFile('archivo_pdf')
                ? $request->file('archivo_pdf')->store('ordenes-compra', 'public')
                : null,
            'estado' => 'PENDIENTE',
        ]);

        return redirect()->route('ordenes-compra-cliente.index')
            ->with('success', 'Orden de compra registrada.');
    }

    public function update(UpdateOrdenCompraClienteRequest $request, OrdenCompraCliente $ordenCompra): RedirectResponse
    {
        $datos = $request->validated();

        if ($request->hasFile('archivo_pdf')) {
            if ($ordenCompra->archivo_pdf) {
                Storage::disk('public')->delete($ordenCompra->archivo_pdf);
            }
            $datos['archivo_pdf'] = $request->file('archivo_pdf')->store('ordenes-compra', 'public');
        } else {
            unset($datos['archivo_pdf']);
        }

        $ordenCompra->update($datos);

        return redirect()->route('ordenes-compra-cliente.index')
            ->with('success', 'Orden de compra actualizada.');
    }

    public function validar(OrdenCompraCliente $ordenCompra): RedirectResponse
    {
        return $this->cambiarEstado($ordenCompra, 'VALIDADA', 'validada');
    }

    public function anular(OrdenCompraCliente $ordenCompra): RedirectResponse
    {
        return $this->cambiarEstado($ordenCompra, 'ANULADA', 'anulada');
    }

    private function cambiarEstado(OrdenCompraCliente $orden, string $nuevoEstado, string $verbo): RedirectResponse
    {
        if ($orden->estado !== 'PENDIENTE') {
            return redirect()->route('ordenes-compra-cliente.index')
                ->with('error', 'Solo una orden de compra pendiente puede validarse o anularse.');
        }

        $orden->update(['estado' => $nuevoEstado]);

        return redirect()->route('ordenes-compra-cliente.index')
            ->with('success', "Orden de compra {$verbo}.");
    }
}
