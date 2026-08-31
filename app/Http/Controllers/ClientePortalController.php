<?php

namespace App\Http\Controllers;

use App\Http\Requests\Portal\SolicitarCotizacionRequest;
use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Portal externo del cliente (rol `cliente`). Cada acción se scopea al
 * `cliente_id` de la ficha vinculada a la cuenta — no por permiso plano
 * (ver .ai/rules/config.md). Sin ficha de cliente, 403.
 */
class ClientePortalController extends Controller
{
    private function clienteId(Request $request): int
    {
        $id = $request->user()->cliente?->id;
        abort_if($id === null, HttpResponse::HTTP_FORBIDDEN, 'Esta cuenta no está vinculada a un cliente.');

        return $id;
    }

    public function cotizaciones(Request $request): Response
    {
        $cotizaciones = Cotizacion::query()
            ->where('cliente_id', $this->clienteId($request))
            ->withCount('detalles')
            ->orderByDesc('fecha')
            ->paginate(10);

        return inertia('Portal/Cotizaciones', [
            'cotizaciones' => $cotizaciones,
            'pageTitle' => 'Mis cotizaciones',
        ]);
    }

    public function cotizacion(Request $request, Cotizacion $cotizacion): Response
    {
        abort_unless($cotizacion->cliente_id === $this->clienteId($request), HttpResponse::HTTP_FORBIDDEN);

        $cotizacion->load(['detalles.producto:id,nombre', 'sucursal:id,nombre', 'pedido:id,cotizacion_id,numero_pedido,estado']);

        return inertia('Portal/Cotizacion', [
            'cotizacion' => $cotizacion,
            'pageTitle' => "Cotización {$cotizacion->codigo_verificacion}",
        ]);
    }

    public function responder(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        abort_unless($cotizacion->cliente_id === $this->clienteId($request), HttpResponse::HTTP_FORBIDDEN);

        $accion = $request->validate([
            'accion' => ['required', 'in:aprobar,rechazar'],
        ])['accion'];

        if ($cotizacion->estado !== 'PENDIENTE') {
            return redirect()->route('portal.cotizacion', $cotizacion)
                ->with('error', 'Esta cotización ya fue respondida.');
        }

        $cotizacion->update(['estado' => $accion === 'aprobar' ? 'APROBADA' : 'RECHAZADA']);

        return redirect()->route('portal.cotizacion', $cotizacion)
            ->with('success', $accion === 'aprobar' ? 'Cotización aprobada.' : 'Cotización rechazada.');
    }

    public function pedidos(Request $request): Response
    {
        $pedidos = Pedido::query()
            ->whereHas('cotizacion', fn ($q) => $q->where('cliente_id', $this->clienteId($request)))
            ->with('cotizacion:id,codigo_verificacion')
            ->orderByDesc('fecha_pedido')
            ->paginate(10);

        return inertia('Portal/Pedidos', [
            'pedidos' => $pedidos,
            'pageTitle' => 'Mis pedidos',
        ]);
    }

    public function pedido(Request $request, Pedido $pedido): Response
    {
        $pedido->load(['cotizacion:id,codigo_verificacion,cliente_id', 'detalles', 'notasEntrega:id,pedido_id,numero_nota,fecha_entrega']);

        abort_unless($pedido->cotizacion->cliente_id === $this->clienteId($request), HttpResponse::HTTP_FORBIDDEN);

        return inertia('Portal/Pedido', [
            'pedido' => $pedido,
            'cobranza' => [
                'total' => (float) $pedido->total,
                'pagado' => $pedido->totalPagado(),
                'saldo' => $pedido->saldo(),
                'estado' => $pedido->estadoPago(),
            ],
            'pageTitle' => "Pedido {$pedido->numero_pedido}",
        ]);
    }

    public function solicitar(Request $request): Response
    {
        $this->clienteId($request);

        return inertia('Portal/Solicitar', [
            'productos' => Producto::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre', 'requiere_medidas']),
            'pageTitle' => 'Solicitar cotización',
        ]);
    }

    public function solicitarStore(SolicitarCotizacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $clienteId = $request->user()->cliente->id;
        $sucursalId = Sucursal::query()->where('estado', 'ACTIVO')->value('id') ?? Sucursal::query()->value('id');

        $cotizacion = DB::transaction(function () use ($datos, $clienteId, $sucursalId): Cotizacion {
            $cotizacion = Cotizacion::create([
                'codigo_verificacion' => $this->generarCodigo(),
                'cliente_id' => $clienteId,
                'empleado_id' => null,
                'sucursal_id' => $sucursalId,
                'fecha' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays((int) config('cotizacion.dias_vencimiento', 15))->toDateString(),
                'estado' => 'PENDIENTE',
                'subtotal' => 0,
                'descuento' => 0,
                'impuesto' => 0,
                'total' => 0,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $cotizacion->detalles()->createMany(array_map(fn (array $l): array => [
                'producto_id' => $l['producto_id'] ?? null,
                'descripcion' => $l['descripcion'],
                'ancho' => $l['ancho'] ?? null,
                'alto' => $l['alto'] ?? null,
                'area_m2' => isset($l['ancho'], $l['alto']) ? round((float) $l['ancho'] * (float) $l['alto'], 2) : null,
                'cantidad' => $l['cantidad'],
                'precio_unitario' => 0,
                'subtotal' => 0,
            ], $datos['detalles']));

            return $cotizacion;
        });

        return redirect()->route('portal.cotizacion', $cotizacion)
            ->with('success', 'Solicitud enviada. Un asesor la revisará y te enviará el presupuesto.');
    }

    private function generarCodigo(): string
    {
        do {
            $codigo = 'COT-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Cotizacion::where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }
}
