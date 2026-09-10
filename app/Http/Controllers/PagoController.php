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
        $usuario = $request->user();

        $pagos = Pago::query()
            // `withSum` deja el cobrado acumulado del pedido en la fila, para
            // que la tabla muestre su estado de cobranza sin N+1.
            ->with(['pedido' => fn ($pedido) => $pedido
                ->select('id', 'numero_pedido', 'total')
                ->withSum('pagos as total_cobrado', 'monto')])
            ->visiblePara($usuario)
            ->estadoCobranza($request->query('estado'))
            ->metodo($request->query('metodo'))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Los totales van acotados igual que el listado: si solo se filtrara la
        // tabla, las tarjetas de resumen seguirían mostrando la caja de TODA la
        // empresa a quien solo administra una sucursal — y un total es
        // justamente lo que se lee de un vistazo, sin comprobar de dónde sale.
        $totalCobrado = round((float) Pago::query()->visiblePara($usuario)->sum('monto'), 2);
        $totalPedidos = round((float) Pedido::query()
            ->visiblePara($usuario)
            ->where('estado', '!=', 'CANCELADO')
            ->sum('total'), 2);

        return inertia('Pagos/Index', [
            'pagos' => $pagos,
            'resumen' => [
                'total_cobrado' => $totalCobrado,
                'por_cobrar' => round(max($totalPedidos - $totalCobrado, 0), 2),
            ],
            'metodos' => Pago::METODOS,
            'estados' => Pago::ESTADOS_COBRANZA,
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

        $pedido->pagos()->create([
            'monto' => $monto,
            'fecha_pago' => $datos['fecha_pago'],
            'metodo_pago' => $datos['metodo_pago'],
            'comprobante_url' => $request->hasFile('comprobante')
                ? $request->file('comprobante')->store('comprobantes-pago', 'public')
                : null,
        ]);

        return redirect()->back()
            ->with('success', "Pago registrado. Saldo del pedido {$pedido->numero_pedido}: Bs ".number_format($pedido->fresh()->saldo(), 2));
    }
}
