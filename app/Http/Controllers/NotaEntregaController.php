<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotaEntrega\StoreNotaEntregaRequest;
use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;

class NotaEntregaController extends Controller
{
    public function index(Request $request): Response
    {
        $notas = NotaEntrega::query()
            ->with(['pedido:id,numero_pedido', 'empleado:id,nombres,paterno,materno'])
            ->withCount('detalles')
            ->search($request->query('search'))
            ->orderByDesc('fecha_entrega')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return inertia('NotasEntrega/Index', [
            'notas' => $notas,
            'filters' => $request->only(['search']),
            'pageTitle' => 'Notas de entrega',
            'breadcrumbs' => ['Ventas', 'Notas de entrega'],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $pedido = Pedido::with(['cotizacion.cliente:id,razon_social', 'detalles'])
            ->findOrFail($request->integer('pedido'));

        if (in_array($pedido->estado, ['CANCELADO'], true)) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('error', 'No se puede emitir una nota de entrega de un pedido cancelado.');
        }

        return inertia('NotasEntrega/Create', [
            'pedido' => $pedido,
            'empleados' => Empleado::query()->estado('ACTIVO')->orderBy('nombres')
                ->get(['id', 'nombres', 'paterno', 'materno', 'cargo']),
            'empleadoActualId' => $request->user()->empleado?->id,
            'pageTitle' => "Nota de entrega · {$pedido->numero_pedido}",
            'breadcrumbs' => ['Ventas', 'Notas de entrega', 'Nueva'],
        ]);
    }

    public function store(StoreNotaEntregaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $pedido = Pedido::with('detalles')->findOrFail($datos['pedido_id']);

        $nota = DB::transaction(function () use ($request, $datos, $pedido): NotaEntrega {
            $nota = $pedido->notasEntrega()->create([
                'empleado_id' => $datos['empleado_id'],
                'numero_nota' => $this->generarNumero(),
                'fecha_entrega' => $datos['fecha_entrega'],
                'recibido_por' => $datos['recibido_por'] ?? null,
                'cargo_receptor' => $datos['cargo_receptor'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'archivo_pdf' => $request->hasFile('archivo_pdf')
                    ? $request->file('archivo_pdf')->store('notas-entrega', 'public')
                    : null,
            ]);

            $idsEntregados = [];

            foreach ($datos['detalles'] as $i => $linea) {
                $foto = $request->file("detalles.{$i}.foto");

                $nota->detalles()->create([
                    'pedido_detalle_id' => $linea['pedido_detalle_id'],
                    'descripcion' => $linea['descripcion'],
                    'cantidad_entregada' => $linea['cantidad_entregada'],
                    'ubicacion' => $linea['ubicacion'] ?? null,
                    'foto_url' => $foto?->store('notas-entrega/fotos', 'public'),
                ]);

                $idsEntregados[] = $linea['pedido_detalle_id'];
            }

            $pedido->detalles()
                ->whereIn('id', $idsEntregados)
                ->where('pedido_id', $pedido->id)
                ->update(['estado_item' => 'ENTREGADO']);

            $pedido->recalcularEstado();

            return $nota;
        });

        return redirect()->route('notas-entrega.show', $nota)
            ->with('success', "Nota de entrega {$nota->numero_nota} emitida.");
    }

    public function show(NotaEntrega $notasEntrega): Response
    {
        $notasEntrega->load([
            'pedido.cotizacion.cliente',
            'empleado',
            'detalles',
        ]);

        return inertia('NotasEntrega/Show', [
            'nota' => $notasEntrega,
            'pageTitle' => "Nota de entrega {$notasEntrega->numero_nota}",
            'breadcrumbs' => ['Ventas', 'Notas de entrega', $notasEntrega->numero_nota],
        ]);
    }

    private function generarNumero(): string
    {
        do {
            $numero = 'NE-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (NotaEntrega::where('numero_nota', $numero)->exists());

        return $numero;
    }
}
