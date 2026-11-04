<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\CotizadorWeb\UpdateAjustesCotizadorWebRequest;
use App\Http\Requests\CotizadorWeb\UpdateProductoWebRequest;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\ProductoPrecioWeb;
use App\Models\TipoProyecto;
use App\Services\Cotizador\CotizadorPublicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Panel del dueño para el cotizador del sitio (`/cotizador`): qué productos
 * se publican, de dónde sale el precio de cada uno y los ajustes generales.
 * Antes todo eso vivía en el código y en el `.env`.
 *
 * El cálculo NO vive acá: tanto la columna "Precio de referencia" como el
 * botón "Probar" llaman a CotizadorPublicoService, el mismo que atiende al
 * visitante.
 */
class CotizadorWebController extends Controller
{
    public function __construct(
        private readonly CotizadorPublicoService $cotizador,
    ) {}

    /**
     * Productos activos de lonas, letreros e impresión (los muebles no se
     * cotizan en la web), con su receta, sus rangos y el precio que vería un
     * visitante para 1 m², 1 metro lineal o 1 unidad.
     */
    public function index(Request $request): Response
    {
        $productos = Producto::query()
            ->where('estado', 'ACTIVO')
            ->modoCotizacion('GENERAL')
            ->search($request->query('search'))
            ->when($request->query('publicado'), fn ($query, $publicado) => $query->where('cotizable_web', $publicado))
            ->with([
                'categoriaProducto:id,nombre',
                'preciosWeb',
                'productoMateriales:id,producto_id,material_id,formula_id,cantidad_por_unidad',
                'productoMateriales.material:id,nombre,unidad_medida,precio_unitario,redondeo_compra',
                'productoMateriales.formula:id,nombre,expresion',
            ])
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $publicables = Producto::query()
            ->cotizableWeb()
            ->whereIn('id', $productos->getCollection()->pluck('id'))
            ->pluck('id')
            ->all();

        $tipoProyecto = $this->cotizador->tipoProyecto();

        $productos->through(fn (Producto $producto): array => [
            ...$producto->toArray(),
            'publicado' => in_array($producto->id, $publicables, true),
            'precio_referencia' => $this->precioDeReferencia($producto, $tipoProyecto),
        ]);

        return inertia('CotizadorWeb/Index', [
            'productos' => $productos,
            'filters' => $request->only(['search', 'publicado']),
            'ajustes' => [
                'tipo_proyecto' => $tipoProyecto?->nombre,
                'holgura' => round($this->cotizador->holgura() * 100, 2),
                'vigencia_dias' => $this->cotizador->vigenciaDias(),
            ],
            'tiposProyecto' => TipoProyecto::query()
                ->where('estado', 'ACTIVO')
                ->orderBy('orden')
                ->get(['id', 'nombre', 'factor_complejidad', 'margen_minimo']),
            'iva' => (float) config('margen.impuestos.iva'),
            'pageTitle' => 'Cotizador web',
            'breadcrumbs' => ['Ventas', 'Cotizador web'],
        ]);
    }

    public function actualizarAjustes(UpdateAjustesCotizadorWebRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // Vacío (no null) = "el primero activo": un null volvería a dejar
        // mandar al `.env`, que no es lo que eligió el dueño.
        Configuracion::guardar(CotizadorPublicoService::CLAVE_TIPO_PROYECTO, $datos['tipo_proyecto'] ?? '');
        Configuracion::guardar(CotizadorPublicoService::CLAVE_HOLGURA, round((float) $datos['holgura'] / 100, 4));
        Configuracion::guardar(CotizadorPublicoService::CLAVE_VIGENCIA_DIAS, (int) $datos['vigencia_dias']);

        return redirect()->back()->with('success', 'Ajustes del cotizador web guardados.');
    }

    public function actualizarProducto(UpdateProductoWebRequest $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($producto, $datos): void {
            $producto->update([
                'cotizable_web' => $datos['cotizable_web'],
                'modo_precio_web' => $datos['modo_precio_web'],
                'precio_web' => $datos['precio_web'] ?? null,
            ]);

            // Los rangos se guardan solo en su modo; en los otros quedan como
            // estaban, por si el dueño vuelve a ese modo.
            if ($datos['modo_precio_web'] === 'RANGOS') {
                $producto->preciosWeb()->delete();
                $producto->preciosWeb()->createMany($this->rangosDe($datos['rangos'] ?? []));
            }
        });

        $estado = $datos['cotizable_web'] === 'SI' ? 'publicado' : 'guardado sin publicar';

        return redirect()->back()->with('success', "«{$producto->nombre}» {$estado} en el cotizador web.");
    }

    /**
     * Lo que vería el visitante con la configuración del modal, ANTES de
     * guardarla: se aplica sobre una copia en memoria del producto.
     */
    public function probar(Request $request, Producto $producto): JsonResponse
    {
        $datos = $request->validate([
            'modo_precio_web' => ['required', Rule::in(Producto::MODOS_PRECIO_WEB)],
            'precio_web' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rangos' => ['nullable', 'array', 'max:10'],
            'rangos.*.area_hasta' => ['nullable', 'numeric', 'gt:0'],
            'rangos.*.precio_m2' => ['nullable', 'numeric', 'min:0'],
            'ancho' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'alto' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $copia = $producto->replicate();
        $copia->id = $producto->id;
        $copia->fill(['modo_precio_web' => $datos['modo_precio_web'], 'precio_web' => $datos['precio_web'] ?? null]);
        $copia->setRelation('preciosWeb', collect($this->rangosDe($datos['rangos'] ?? []))
            ->map(fn (array $rango): ProductoPrecioWeb => new ProductoPrecioWeb($rango))
            ->sortBy(fn (ProductoPrecioWeb $rango): float => $rango->area_hasta === null ? INF : (float) $rango->area_hasta)
            ->values());

        try {
            $precio = $this->cotizador->precioUnitario(
                $copia,
                isset($datos['ancho']) ? (float) $datos['ancho'] : null,
                isset($datos['alto']) ? (float) $datos['alto'] : null,
                $this->cotizador->tipoProyecto(),
            );
        } catch (InvalidArgumentException|FormulaInvalidaException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $subtotal = round($precio['precio'] * $datos['cantidad'], 2);
        $total = round($subtotal * (1 + (float) config('margen.impuestos.iva')), 2);
        $holgura = $this->cotizador->holgura();

        return response()->json([
            'precio_unitario' => $precio['precio'],
            'subtotal' => $subtotal,
            'total' => $total,
            'estimado_min' => round($total * (1 - $holgura), 2),
            'estimado_max' => round($total * (1 + $holgura), 2),
        ]);
    }

    /**
     * Precio sin IVA que vería el visitante por 1 m², 1 metro lineal o 1
     * unidad. `null` si con esa medida no se puede calcular (falta receta,
     * la fórmula pide profundidad, etc.).
     */
    private function precioDeReferencia(Producto $producto, ?TipoProyecto $tipoProyecto): ?float
    {
        $medida = $producto->requiere_medidas === 'SI' ? 1.0 : null;

        try {
            return $this->cotizador->precioUnitario($producto, $medida, $medida, $tipoProyecto)['precio'];
        } catch (InvalidArgumentException|FormulaInvalidaException) {
            return null;
        }
    }

    /**
     * @param  array<int, array{area_hasta?: float|string|null, precio_m2?: float|string|null}>  $rangos
     * @return list<array{area_hasta: float|null, precio_m2: float}>
     */
    private function rangosDe(array $rangos): array
    {
        return array_values(array_map(fn (array $rango): array => [
            'area_hasta' => isset($rango['area_hasta']) && $rango['area_hasta'] !== '' ? (float) $rango['area_hasta'] : null,
            'precio_m2' => (float) ($rango['precio_m2'] ?? 0),
        ], $rangos));
    }
}
