<?php

namespace App\Http\Controllers;

use App\Http\Requests\Compra\StoreCompraRequest;
use App\Http\Requests\Compra\UpdateCompraRequest;
use App\Models\Compra;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Proveedor;
use App\Services\Compra\AprobarCompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class CompraController extends Controller
{
    /**
     * Listado paginado, con búsqueda (factura/proveedor) y filtros por
     * estado y proveedor. `withQueryString()` mantiene los filtros al
     * navegar entre páginas.
     */
    public function index(Request $request): Response
    {
        $compras = Compra::query()
            ->with(['proveedor:id,nombre', 'empleado:id,nombres,paterno,materno'])
            ->withCount('detalles')
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->proveedorId($request->query('proveedor'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return inertia('Compras/Index', [
            'compras' => $compras,
            'proveedores' => Proveedor::query()->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Compra::ESTADOS,
            'filters' => $request->only(['search', 'estado', 'proveedor']),
            'pageTitle' => 'Compras',
            'breadcrumbs' => ['Materiales e Insumos', 'Compras'],
        ]);
    }

    public function create(Request $request): Response
    {
        return inertia('Compras/Create', [
            ...$this->datosFormulario($request),
            'pageTitle' => 'Nueva compra',
            'breadcrumbs' => ['Materiales e Insumos', 'Compras', 'Nueva'],
        ]);
    }

    public function store(StoreCompraRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $compra = DB::transaction(function () use ($datos): Compra {
            $detalles = $this->normalizarDetalles($datos['detalles']);

            $compra = Compra::create([
                'proveedor_id' => $datos['proveedor_id'],
                'empleado_id' => $datos['empleado_id'],
                'numero_factura' => $datos['numero_factura'] ?? null,
                'fecha' => $datos['fecha'],
                'estado' => 'PENDIENTE',
                'total' => $this->calcularTotal($detalles),
            ]);

            $compra->detalles()->createMany($detalles);

            return $compra;
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra registrada correctamente.');
    }

    public function show(Compra $compra): Response
    {
        $compra->load([
            'proveedor',
            'empleado',
            'detalles.material:id,nombre,presentacion,unidad_medida',
        ]);

        return inertia('Compras/Show', [
            'compra' => $compra,
            'pageTitle' => 'Compra #'.$compra->id,
            'breadcrumbs' => ['Materiales e Insumos', 'Compras', '#'.$compra->id],
        ]);
    }

    public function edit(Request $request, Compra $compra): RedirectResponse|Response
    {
        if (! $compra->esEditable()) {
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Solo se pueden editar compras pendientes.');
        }

        $compra->load(['detalles.material:id,nombre,unidad_medida']);

        return inertia('Compras/Edit', [
            ...$this->datosFormulario($request),
            'compra' => $compra,
            'pageTitle' => 'Editar compra #'.$compra->id,
            'breadcrumbs' => ['Materiales e Insumos', 'Compras', '#'.$compra->id, 'Editar'],
        ]);
    }

    public function update(UpdateCompraRequest $request, Compra $compra): RedirectResponse
    {
        if (! $compra->esEditable()) {
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Solo se pueden editar compras pendientes.');
        }

        $datos = $request->validated();

        DB::transaction(function () use ($compra, $datos): void {
            $detalles = $this->normalizarDetalles($datos['detalles']);

            $compra->update([
                'proveedor_id' => $datos['proveedor_id'],
                'empleado_id' => $datos['empleado_id'],
                'numero_factura' => $datos['numero_factura'] ?? null,
                'fecha' => $datos['fecha'],
                'total' => $this->calcularTotal($detalles),
            ]);

            // El detalle se reemplaza entero: la compra todavía no impactó
            // el inventario (sigue PENDIENTE), así que es seguro.
            $compra->detalles()->delete();
            $compra->detalles()->createMany($detalles);
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra actualizada correctamente.');
    }

    public function destroy(Compra $compra): RedirectResponse
    {
        if ($compra->estado === 'PAGADA') {
            return redirect()->route('compras.index')
                ->with('error', 'No se puede eliminar una compra ya pagada (impactó el inventario).');
        }

        $compra->delete();

        return redirect()->route('compras.index')
            ->with('success', 'Compra eliminada correctamente.');
    }

    /**
     * Aprueba (PAGADA) una compra pendiente y recién ahí impacta el
     * inventario: sube el stock de cada material, actualiza su precio con
     * el costo de esta compra y deja una fila en `historial_precio_material`
     * (clave para el BI de evolución de costos y para no alterar
     * cotizaciones/pedidos ya cerrados).
     */
    public function aprobar(Compra $compra, AprobarCompraService $aprobarCompra): RedirectResponse
    {
        if ($compra->estado !== 'PENDIENTE') {
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Solo una compra pendiente puede aprobarse.');
        }

        $aprobarCompra->aprobar($compra);

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra aprobada: stock y precios de materiales actualizados.');
    }

    public function anular(Compra $compra): RedirectResponse
    {
        if ($compra->estado !== 'PENDIENTE') {
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Solo una compra pendiente puede anularse.');
        }

        $compra->update(['estado' => 'ANULADA']);

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra anulada.');
    }

    /**
     * Catálogos compartidos por los formularios de crear/editar.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        return [
            'proveedores' => Proveedor::query()->estado('ACTIVO')->orderBy('nombre')
                ->get(['id', 'nombre', 'nit']),
            'empleados' => Empleado::query()->estado('ACTIVO')->orderBy('nombres')
                ->get(['id', 'nombres', 'paterno', 'materno', 'cargo']),
            'materiales' => Material::query()->estado('ACTIVO')->orderBy('nombre')
                ->get(['id', 'nombre', 'presentacion', 'unidad_medida', 'precio_unitario']),
            'empleadoActualId' => $request->user()->empleado?->id,
        ];
    }

    /**
     * Normaliza cada línea: calcula el `subtotal` (cantidad × precio) en el
     * servidor, ignorando lo que haya mandado el navegador.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function normalizarDetalles(array $detalles): array
    {
        return array_map(function (array $linea): array {
            $cantidad = (float) $linea['cantidad'];
            $precioUnitario = (float) $linea['precio_unitario'];

            return [
                'material_id' => $linea['material_id'],
                'cantidad' => round($cantidad, 2),
                'precio_unitario' => round($precioUnitario, 2),
                'subtotal' => round($cantidad * $precioUnitario, 2),
            ];
        }, $detalles);
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     */
    private function calcularTotal(array $detalles): float
    {
        return round(array_sum(array_column($detalles, 'subtotal')), 2);
    }
}
