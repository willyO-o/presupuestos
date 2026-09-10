<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Pedido\ActualizarEstadoRequest;
use App\Http\Requests\Pedido\ActualizarMedidasRequest;
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
            // `visiblePara` falla cerrado: sin alcance el listado sale vacío y
            // se avisa en vez de dejar creer que no hay pedidos.
            //
            // La condición mira el ALCANCE, no la ficha de empleado: con
            // alcance ASIGNADAS y nada marcado la cuenta tampoco ve nada, y
            // antes ese caso pasaba desapercibido porque sí tenía ficha.
            // (El layout muestra además un aviso general — este es el de la
            // pantalla, que explica el listado vacío que se está mirando.)
            'sinFichaEmpleado' => $request->user()->sucursalesVisibles('pedidos') === [],
            // Solo las que administra: un filtro que siempre devuelve vacio
            // parece un error del sistema, no una restriccion de permisos.
            'sucursales' => $request->user()->sucursalesDisponibles(),
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
        // Se acota con las reglas de COTIZACIÓN, no con las de pedido: el
        // override `pedidos.ver_todas_sucursales` es un permiso de LECTURA de
        // pedidos ajenos, y convertir la cotización de otra sucursal en pedido
        // es escribir sobre su cartera. Quien deba hacerlo necesita alcance
        // sobre esa sucursal, no el override.
        $convertibles = Cotizacion::query()
            ->visiblePara($request->user())
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
            'pagos:id,pedido_id,monto,fecha_pago,metodo_pago',
            'seguimientoPostventa.empleado:id,nombres,paterno,materno',
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
            'empleados' => Empleado::query()->visiblePara($request->user())->estado('ACTIVO')->orderBy('nombres')
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
        $this->assertPuedeOperarSobre($request, $pedido, $detalle);

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
    /**
     * Corrige las medidas y la cantidad REALES con que se está fabricando el
     * ítem.
     *
     * Es lo que justifica que `pedido_detalle` guarde copia de
     * descripción/ancho/alto/cantidad en vez de leerlas de la cotización: el
     * taller mide la pieza terminada y ese dato es el que necesita
     * producción, mientras la cotización queda intacta como documento
     * histórico de lo que se le prometió al cliente.
     *
     * El precio NO se recalcula: lo acordado con el cliente no cambia porque
     * la pieza haya salido dos centímetros más grande. La diferencia queda
     * anotada en la bitácora de seguimiento del ítem.
     */
    public function actualizarMedidas(ActualizarMedidasRequest $request, Pedido $pedido, PedidoDetalle $detalle): RedirectResponse
    {
        $this->assertPuedeOperarSobre($request, $pedido, $detalle);

        if (! $pedido->esCancelable()) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('error', 'No se pueden ajustar las medidas de un pedido entregado o cancelado.');
        }

        $datos = $request->validated();
        $anterior = $detalle->only(['descripcion', 'ancho', 'alto', 'cantidad']);

        $detalle->update([
            'descripcion' => $datos['descripcion'],
            'ancho' => $datos['ancho'] ?? null,
            'alto' => $datos['alto'] ?? null,
            'cantidad' => $datos['cantidad'],
        ]);

        $this->registrarAjusteDeMedidas($detalle, $anterior, $datos['motivo'] ?? null);

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', 'Medidas de producción actualizadas.');
    }

    /**
     * Deja constancia del ajuste en la etapa de seguimiento abierta del ítem
     * (o en la última cerrada), para que quede quién lo pidió y por qué.
     *
     * @param  array<string, mixed>  $anterior
     */
    private function registrarAjusteDeMedidas(PedidoDetalle $detalle, array $anterior, ?string $motivo): void
    {
        $seguimiento = $detalle->seguimientos()->latest('id')->first();

        if ($seguimiento === null) {
            return;
        }

        $nota = sprintf(
            'Ajuste de medidas: %s (%s x %s, cant. %s) → %s (%s x %s, cant. %s).%s',
            $anterior['descripcion'],
            $anterior['ancho'] ?? '—',
            $anterior['alto'] ?? '—',
            $anterior['cantidad'],
            $detalle->descripcion,
            $detalle->ancho ?? '—',
            $detalle->alto ?? '—',
            $detalle->cantidad,
            $motivo !== null ? " Motivo: {$motivo}" : '',
        );

        $seguimiento->update([
            'observaciones' => trim(($seguimiento->observaciones ?? '').'
'.$nota),
        ]);
    }

    public function actualizarEstado(ActualizarEstadoRequest $request, Pedido $pedido, PedidoDetalle $detalle): RedirectResponse
    {
        $this->assertPuedeOperarSobre($request, $pedido, $detalle);

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
        $this->assertPuedeOperarSobre($request, $pedido, $detalle);

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
        abort_unless($this->puedeVer($request, $pedido), HttpResponse::HTTP_FORBIDDEN);

        if (! $pedido->esCancelable()) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('error', 'No se puede cancelar un pedido entregado o ya cancelado.');
        }

        $pedido->update(['estado' => 'CANCELADO']);

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Pedido cancelado.');
    }

    /**
     * Mismo scope que el listado (`Pedido::visiblePara`), no una segunda copia
     * de la regla: antes esto comparaba `sucursal_id` a mano y el mismo `if`
     * estaba repetido en DocumentoPdfController.
     */
    private function puedeVer(Request $request, Pedido $pedido): bool
    {
        return $pedido->esVisiblePara($request->user());
    }

    /**
     * Puerta de todas las acciones sobre un ítem del pedido (asignar área,
     * cambiar estado, ajustar medidas, registrar consumo).
     *
     * Dos comprobaciones, las dos obligatorias:
     *
     * 1. El detalle es de ESTE pedido — el binding anidado de Laravel no lo
     *    scopea solo, así que `/pedidos/1/detalle/999` resolvería un detalle
     *    de otro pedido (404).
     * 2. El pedido es de una sucursal que el usuario administra. Faltaba: la
     *    fase 3 acotó `show` pero estas cuatro rutas cambiaban el estado de un
     *    pedido ajeno con solo poner su id en la URL, sin pasar por la
     *    pantalla.
     */
    private function assertPuedeOperarSobre(Request $request, Pedido $pedido, PedidoDetalle $detalle): void
    {
        abort_unless($detalle->pedido_id === $pedido->id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->puedeVer($request, $pedido), HttpResponse::HTTP_FORBIDDEN);
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
