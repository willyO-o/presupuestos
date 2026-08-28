<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Cotizacion\StoreCotizacionRequest;
use App\Http\Requests\Cotizacion\UpdateCotizacionRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\Calculo\PrecioSugeridoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;
use InvalidArgumentException;

class CotizacionController extends Controller
{
    /**
     * Listado paginado, con búsqueda (código/cliente/observaciones) y
     * filtros por estado, cliente y sucursal. `withQueryString()` mantiene
     * los filtros al navegar entre páginas.
     */
    public function index(Request $request): Response
    {
        $cotizaciones = Cotizacion::query()
            ->with(['cliente:id,razon_social', 'sucursal:id,nombre'])
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->clienteId($request->query('cliente'))
            ->sucursalId($request->query('sucursal'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return inertia('Cotizaciones/Index', [
            'cotizaciones' => $cotizaciones,
            'clientes' => Cliente::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'sucursales' => Sucursal::query()->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Cotizacion::ESTADOS,
            'filters' => $request->only(['search', 'estado', 'cliente', 'sucursal']),
            'pageTitle' => 'Cotizaciones',
            'breadcrumbs' => ['Ventas', 'Cotizaciones'],
        ]);
    }

    public function create(Request $request): Response
    {
        return inertia('Cotizaciones/Create', [
            ...$this->datosFormulario($request),
            'pageTitle' => 'Nueva cotización',
            'breadcrumbs' => ['Ventas', 'Cotizaciones', 'Nueva'],
        ]);
    }

    public function store(StoreCotizacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $cotizacion = DB::transaction(function () use ($datos): Cotizacion {
            $detalles = $this->normalizarDetalles($datos['detalles']);
            $montos = $this->calcularMontos($detalles, (float) ($datos['descuento'] ?? 0), (float) ($datos['impuesto'] ?? 0));

            $cotizacion = Cotizacion::create([
                'codigo_verificacion' => $this->generarCodigoVerificacion(),
                'cliente_id' => $datos['cliente_id'],
                'empleado_id' => $datos['empleado_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'fecha' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'estado' => 'PENDIENTE',
                'observaciones' => $datos['observaciones'] ?? null,
                ...$montos,
            ]);

            $cotizacion->detalles()->createMany($detalles);

            return $cotizacion;
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->codigo_verificacion} creada correctamente.");
    }

    public function show(Cotizacion $cotizacion): Response
    {
        $cotizacion->load([
            'cliente',
            'empleado',
            'sucursal',
            'detalles.producto:id,nombre,unidad_medida',
        ]);

        return inertia('Cotizaciones/Show', [
            'cotizacion' => $cotizacion,
            'pageTitle' => "Cotización {$cotizacion->codigo_verificacion}",
            'breadcrumbs' => ['Ventas', 'Cotizaciones', $cotizacion->codigo_verificacion],
        ]);
    }

    public function edit(Request $request, Cotizacion $cotizacion): RedirectResponse|Response
    {
        if (! $cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo se pueden editar cotizaciones pendientes.');
        }

        $cotizacion->load(['detalles.producto:id,nombre,unidad_medida,requiere_medidas']);

        return inertia('Cotizaciones/Edit', [
            ...$this->datosFormulario($request),
            'cotizacion' => $cotizacion,
            'pageTitle' => "Editar cotización {$cotizacion->codigo_verificacion}",
            'breadcrumbs' => ['Ventas', 'Cotizaciones', $cotizacion->codigo_verificacion, 'Editar'],
        ]);
    }

    public function update(UpdateCotizacionRequest $request, Cotizacion $cotizacion): RedirectResponse
    {
        if (! $cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo se pueden editar cotizaciones pendientes.');
        }

        $datos = $request->validated();

        DB::transaction(function () use ($cotizacion, $datos): void {
            $detalles = $this->normalizarDetalles($datos['detalles']);
            $montos = $this->calcularMontos($detalles, (float) ($datos['descuento'] ?? 0), (float) ($datos['impuesto'] ?? 0));

            $cotizacion->update([
                'cliente_id' => $datos['cliente_id'],
                'empleado_id' => $datos['empleado_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'fecha' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                ...$montos,
            ]);

            // El detalle se reemplaza entero: es más simple y seguro que
            // hacer diff línea por línea, y la cotización todavía no tiene
            // un pedido que dependa de los ids de estas líneas.
            $cotizacion->detalles()->delete();
            $cotizacion->detalles()->createMany($detalles);
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->codigo_verificacion} actualizada correctamente.");
    }

    public function destroy(Cotizacion $cotizacion): RedirectResponse
    {
        if ($cotizacion->estado === 'CONVERTIDA') {
            return redirect()->route('cotizaciones.index')
                ->with('error', 'No se puede eliminar una cotización ya convertida en pedido.');
        }

        $codigo = $cotizacion->codigo_verificacion;
        $cotizacion->delete();

        return redirect()->route('cotizaciones.index')
            ->with('success', "Cotización {$codigo} eliminada correctamente.");
    }

    /**
     * Responde el rombo "Propuesta Sí/No" del flujo: PENDIENTE → APROBADA /
     * RECHAZADA. Requiere `cotizaciones.aprobar`.
     */
    public function aprobar(Cotizacion $cotizacion): RedirectResponse
    {
        return $this->cambiarEstado($cotizacion, 'APROBADA', 'aprobada');
    }

    public function rechazar(Cotizacion $cotizacion): RedirectResponse
    {
        return $this->cambiarEstado($cotizacion, 'RECHAZADA', 'rechazada');
    }

    /**
     * Calcula el costo de materiales (BOM) y el precio unitario sugerido de
     * un producto para las medidas dadas, sin guardar nada — lo usa el
     * formulario de cotización al agregar una línea (petición JSON vía
     * axios, no un visit de Inertia).
     */
    public function costear(Request $request, PrecioSugeridoService $precioSugerido): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:producto,id'],
            'ancho' => ['nullable', 'numeric', 'min:0'],
            'alto' => ['nullable', 'numeric', 'min:0'],
            'profundo' => ['nullable', 'numeric', 'min:0'],
        ]);

        $producto = Producto::findOrFail($datos['producto_id']);

        try {
            $resultado = $precioSugerido->calcular(
                $producto,
                $datos['ancho'] ?? null,
                $datos['alto'] ?? null,
                $datos['profundo'] ?? null,
            );
        } catch (InvalidArgumentException|FormulaInvalidaException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    /**
     * Catálogos compartidos por los formularios de crear/editar.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        return [
            'clientes' => Cliente::query()->estado('ACTIVO')->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nit']),
            'empleados' => Empleado::query()->estado('ACTIVO')->orderBy('nombres')
                ->get(['id', 'nombres', 'paterno', 'materno', 'cargo']),
            'sucursales' => Sucursal::query()->estado('ACTIVO')->orderBy('nombre')
                ->get(['id', 'nombre', 'ciudad']),
            'productos' => Producto::query()->estado('ACTIVO')->orderBy('nombre')
                ->get(['id', 'nombre', 'unidad_medida', 'requiere_medidas', 'precio_base']),
            'empleadoActualId' => $request->user()->empleado?->id,
            'config' => [
                'margen_sugerido' => (float) config('cotizacion.margen_sugerido'),
                'impuesto_porcentaje' => (float) config('cotizacion.impuesto_porcentaje'),
                'dias_vencimiento' => (int) config('cotizacion.dias_vencimiento'),
            ],
        ];
    }

    private function cambiarEstado(Cotizacion $cotizacion, string $nuevoEstado, string $verbo): RedirectResponse
    {
        if ($cotizacion->estado !== 'PENDIENTE') {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo una cotización pendiente puede aprobarse o rechazarse.');
        }

        $cotizacion->update(['estado' => $nuevoEstado]);

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$verbo} correctamente.");
    }

    /**
     * Normaliza cada línea del detalle: calcula `area_m2` (ancho×alto) y el
     * `subtotal` (precio × cantidad) en el servidor, ignorando lo que haya
     * mandado el navegador.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function normalizarDetalles(array $detalles): array
    {
        return array_map(function (array $linea): array {
            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;
            $cantidad = (float) $linea['cantidad'];
            $precioUnitario = (float) $linea['precio_unitario'];

            return [
                'producto_id' => $linea['producto_id'] ?? null,
                'descripcion' => $linea['descripcion'],
                'ancho' => $ancho,
                'alto' => $alto,
                'area_m2' => ($ancho !== null && $alto !== null) ? round($ancho * $alto, 2) : null,
                'cantidad' => $cantidad,
                'precio_unitario' => round($precioUnitario, 2),
                'subtotal' => round($precioUnitario * $cantidad, 2),
            ];
        }, $detalles);
    }

    /**
     * Suma el detalle y aplica descuento/impuesto (montos, no porcentajes —
     * ver database-design.md §8): total = subtotal − descuento + impuesto,
     * nunca negativo.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return array{subtotal: float, descuento: float, impuesto: float, total: float}
     */
    private function calcularMontos(array $detalles, float $descuento, float $impuesto): array
    {
        $subtotal = round(array_sum(array_column($detalles, 'subtotal')), 2);
        $descuento = round(min($descuento, $subtotal), 2);
        $impuesto = round($impuesto, 2);

        return [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'impuesto' => $impuesto,
            'total' => round(max($subtotal - $descuento + $impuesto, 0), 2),
        ];
    }

    private function generarCodigoVerificacion(): string
    {
        do {
            $codigo = 'COT-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Cotizacion::where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }
}
