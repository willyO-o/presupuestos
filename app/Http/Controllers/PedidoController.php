<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Pedido\ActualizarEstadoRequest;
use App\Http\Requests\Pedido\AsignarAreaRequest;
use App\Http\Requests\Pedido\RegistrarConsumoRequest;
use App\Http\Requests\Pedido\StorePedidoRequest;
use App\Models\Area;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use App\Models\Sucursal;
use App\Services\Calculo\CosteoProductoService;
use App\Services\Calculo\MedidasCotizacion;
use App\Services\Pedido\ConvertirCotizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PedidoController extends Controller
{
    /**
     * Listado paginado. Filtros por estado/sucursal/cliente. El scope
     * `visiblePara` limita a la sucursal del empleado salvo que tenga
     * `pedidos.ver_todas_sucursales` (database-design.md §3.3).
     */
    public function index(Request $request): Response
    {
        $pedidos = Pedido::query()
            ->with(['cotizacion:id,codigo_verificacion,cliente_id,sucursal_id', 'cotizacion.cliente:id,razon_social', 'cotizacion.sucursal:id,nombre'])
            ->withCount('detalles')
            ->visiblePara($request->user())
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->sucursalId($request->query('sucursal'))
            ->clienteId($request->query('cliente'))
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return inertia('Pedidos/Index', [
            'pedidos' => $pedidos,
            'sucursales' => Sucursal::query()->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Pedido::ESTADOS,
            'filters' => $request->only(['search', 'estado', 'sucursal', 'cliente']),
            'pageTitle' => 'Pedidos',
            'breadcrumbs' => ['Ventas', 'Pedidos'],
        ]);
    }

    /**
     * Página para elegir una cotización aprobada y convertirla en pedido
     * (el atajo habitual es el botón "Convertir en pedido" de la ficha de
     * la cotización).
     */
    public function create(Request $request): Response
    {
        $convertibles = Cotizacion::query()
            ->where('estado', 'APROBADA')
            ->whereDoesntHave('pedido')
            ->with(['cliente:id,razon_social', 'sucursal:id,nombre'])
            ->orderByDesc('fecha')
            ->get(['id', 'codigo_verificacion', 'cliente_id', 'sucursal_id', 'fecha', 'total']);

        return inertia('Pedidos/Create', [
            'cotizaciones' => $convertibles,
            'cotizacionId' => $request->integer('cotizacion') ?: null,
            'pageTitle' => 'Nuevo pedido',
            'breadcrumbs' => ['Ventas', 'Pedidos', 'Nuevo'],
        ]);
    }

    public function store(StorePedidoRequest $request, ConvertirCotizacionService $convertir): RedirectResponse
    {
        $cotizacion = Cotizacion::findOrFail($request->validated('cotizacion_id'));

        try {
            $pedido = $convertir->convertir($cotizacion, $request->validated('fecha_entrega_estimada'));
        } catch (RuntimeException $e) {
            return redirect()->route('cotizaciones.show', $cotizacion)->with('error', $e->getMessage());
        }

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', "Pedido {$pedido->numero_pedido} generado desde la cotización {$cotizacion->codigo_verificacion}.");
    }

    public function show(Request $request, Pedido $pedido): Response
    {
        abort_unless($this->puedeVer($request, $pedido), HttpResponse::HTTP_FORBIDDEN);

        $pedido->load([
            'cotizacion.cliente',
            'cotizacion.sucursal',
            'cotizacion.empleado',
            'detalles.seguimientos.area:id,nombre',
            'detalles.seguimientos.empleado:id,nombres,paterno,materno',
            'detalles.materialesUsados.material:id,nombre,unidad_medida',
            'detalles.cotizacionDetalle.producto',
            'ordenCompra',
            'notasEntrega:id,pedido_id,numero_nota,fecha_entrega',
            'pagos:id,pedido_id,monto,fecha_pago,metodo_pago,estado',
        ]);

        return inertia('Pedidos/Show', [
            'pedido' => $pedido,
            'costos' => $this->comparativaCostos($pedido),
            'cobranza' => [
                'total' => (float) $pedido->total,
                'pagado' => $pedido->totalPagado(),
                'saldo' => $pedido->saldo(),
                'estado' => $pedido->estadoPago(),
            ],
            'metodosPago' => Pago::METODOS,
            'areas' => Area::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'empleados' => Empleado::query()->estado('ACTIVO')->orderBy('nombres')
                ->get(['id', 'nombres', 'paterno', 'materno', 'cargo']),
            'materiales' => Material::query()->estado('ACTIVO')->orderBy('nombre')
                ->get(['id', 'nombre', 'unidad_medida', 'precio_unitario']),
            'etapas' => PedidoSeguimiento::ETAPAS,
            'estadosItem' => PedidoDetalle::ESTADOS,
            'pageTitle' => "Pedido {$pedido->numero_pedido}",
            'breadcrumbs' => ['Ventas', 'Pedidos', $pedido->numero_pedido],
        ]);
    }

    /**
     * Asigna un área/responsable a un ítem y abre una etapa de seguimiento
     * (rombo "Define el área" del flujo).
     */
    public function asignarArea(AsignarAreaRequest $request, Pedido $pedido, PedidoDetalle $detalle): RedirectResponse
    {
        $this->assertPerteneceAlPedido($pedido, $detalle);

        $detalle->seguimientos()->create([
            'area_id' => $request->validated('area_id'),
            'empleado_id' => $request->validated('empleado_id'),
            'etapa' => $request->validated('etapa'),
            'fecha_inicio' => now(),
            'observaciones' => $request->validated('observaciones'),
        ]);

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', 'Área asignada al ítem.');
    }

    /**
     * Avanza el `estado_item` de un ítem, cierra la etapa de seguimiento
     * abierta y recalcula el estado global del pedido.
     */
    public function actualizarEstado(ActualizarEstadoRequest $request, Pedido $pedido, PedidoDetalle $detalle): RedirectResponse
    {
        $this->assertPerteneceAlPedido($pedido, $detalle);

        if ($pedido->estado === 'CANCELADO') {
            return redirect()->route('pedidos.show', $pedido)
                ->with('error', 'El pedido está cancelado.');
        }

        DB::transaction(function () use ($request, $pedido, $detalle): void {
            $detalle->update(['estado_item' => $request->validated('estado_item')]);

            $seguimientoAbierto = $detalle->seguimientos()
                ->whereNull('fecha_fin')
                ->latest('fecha_inicio')
                ->first();

            if ($seguimientoAbierto !== null) {
                $cambios = ['fecha_fin' => now()];

                if ($request->validated('observaciones') !== null) {
                    $cambios['observaciones'] = $request->validated('observaciones');
                }

                $seguimientoAbierto->update($cambios);
            }

            $pedido->recalcularEstado();
        });

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', 'Estado del ítem actualizado.');
    }

    /**
     * Registra consumo real de un material para un ítem (insumo del BI:
     * costo real vs. costo presupuestado del BOM).
     */
    public function registrarConsumo(RegistrarConsumoRequest $request, Pedido $pedido, PedidoDetalle $detalle): RedirectResponse
    {
        $this->assertPerteneceAlPedido($pedido, $detalle);

        $material = Material::findOrFail($request->validated('material_id'));
        $cantidad = (float) $request->validated('cantidad_usada');
        $costoReal = $request->validated('costo_real');

        $detalle->materialesUsados()->create([
            'material_id' => $material->id,
            'cantidad_usada' => $cantidad,
            'costo_real' => $costoReal !== null
                ? round((float) $costoReal, 2)
                : round($cantidad * (float) $material->precio_unitario, 2),
        ]);

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', 'Consumo de material registrado.');
    }

    public function cancelar(Request $request, Pedido $pedido): RedirectResponse
    {
        abort_unless($request->user()->can('pedidos.actualizar_estado'), HttpResponse::HTTP_FORBIDDEN);

        if (! $pedido->esCancelable()) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('error', 'No se puede cancelar un pedido entregado o ya cancelado.');
        }

        $pedido->update(['estado' => 'CANCELADO']);

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Pedido cancelado.');
    }

    private function puedeVer(Request $request, Pedido $pedido): bool
    {
        $user = $request->user();

        if ($user->hasRole('super-admin') || $user->can('pedidos.ver_todas_sucursales')) {
            return true;
        }

        return $user->empleado?->sucursal_id === $pedido->cotizacion->sucursal_id;
    }

    private function assertPerteneceAlPedido(Pedido $pedido, PedidoDetalle $detalle): void
    {
        abort_unless($detalle->pedido_id === $pedido->id, HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * Costo de materiales presupuestado (BOM de la cotización) vs. costo
     * real registrado, por ítem y total.
     *
     * @return array{items: list<array<string, mixed>>, estimado_total: float, real_total: float}
     */
    private function comparativaCostos(Pedido $pedido): array
    {
        $costeo = app(CosteoProductoService::class);
        $items = [];
        $estimadoTotal = 0.0;
        $realTotal = 0.0;

        foreach ($pedido->detalles as $detalle) {
            $producto = $detalle->cotizacionDetalle?->producto;
            $estimado = null;

            if ($producto) {
                try {
                    $estimado = $costeo->calcular(
                        $producto,
                        new MedidasCotizacion(
                            ancho: $detalle->ancho !== null ? (float) $detalle->ancho : null,
                            alto: $detalle->alto !== null ? (float) $detalle->alto : null,
                        ),
                        (float) $detalle->cantidad,
                    )->costoMaterial;
                } catch (InvalidArgumentException|FormulaInvalidaException) {
                    $estimado = null;
                }
            }

            $real = round((float) $detalle->materialesUsados->sum('costo_real'), 2);

            $estimadoTotal += $estimado ?? 0.0;
            $realTotal += $real;

            $items[] = [
                'pedido_detalle_id' => $detalle->id,
                'descripcion' => $detalle->descripcion,
                'estimado' => $estimado,
                'real' => $real,
            ];
        }

        return [
            'items' => $items,
            'estimado_total' => round($estimadoTotal, 2),
            'real_total' => round($realTotal, 2),
        ];
    }
}
