<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pago\StorePagoRequest;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PagoController extends Controller
{
    public function index(Request $request): Response
    {
        $pagos = Pago::query()
            ->with('pedido:id,numero_pedido,total')
            ->estado($request->query('estado'))
            ->metodo($request->query('metodo'))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $totalCobrado = round((float) Pago::query()->sum('monto'), 2);
        $totalPedidos = round((float) Pedido::query()->where('estado', '!=', 'CANCELADO')->sum('total'), 2);

        return inertia('Pagos/Index', [
            'pagos' => $pagos,
            'resumen' => [
                'total_cobrado' => $totalCobrado,
                'por_cobrar' => round(max($totalPedidos - $totalCobrado, 0), 2),
            ],
            'metodos' => Pago::METODOS,
            'estados' => Pago::ESTADOS,
            'filters' => $request->only(['estado', 'metodo']),
            'pageTitle' => 'Pagos',
            'breadcrumbs' => ['Ventas', 'Pagos'],
        ]);
    }

    public function store(StorePagoRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $pedido = Pedido::findOrFail($datos['pedido_id']);

        $monto = round((float) $datos['monto'], 2);

        // Estado del pago = saldo del pedido DESPUÉS de este pago.
        $totalTrasPago = $pedido->totalPagado() + $monto;
        $estado = $totalTrasPago >= (float) $pedido->total ? 'PAGADO' : 'PARCIAL';

        $pedido->pagos()->create([
            'monto' => $monto,
            'fecha_pago' => $datos['fecha_pago'],
            'metodo_pago' => $datos['metodo_pago'],
            'estado' => $estado,
            'comprobante_url' => $request->hasFile('comprobante')
                ? $request->file('comprobante')->store('comprobantes-pago', 'public')
                : null,
        ]);

        return redirect()->back()
            ->with('success', "Pago registrado. Saldo del pedido {$pedido->numero_pedido}: Bs ".number_format($pedido->fresh()->saldo(), 2));
    }
}
