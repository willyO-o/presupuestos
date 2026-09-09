<?php

namespace App\Services\Cotizador;

use App\Exceptions\FormulaInvalidaException;
use App\Models\Producto;
use App\Models\TipoProyecto;
use App\Services\Calculo\CosteoProductoService;
use App\Services\Calculo\MedidasCotizacion;
use App\Services\Calculo\MotorMargenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Estimación aproximada para el sitio público (`/cotizador`).
 *
 * NO es un motor nuevo: recorre exactamente la misma cadena que usa ventas al
 * armar un presupuesto —receta/BOM del producto → CosteoProductoService →
 * MotorMargenService → IVA— para que el número que ve el visitante y el que
 * calcula el sistema por dentro no puedan divergir. Si mañana cambia el
 * margen de un tipo de proyecto o el precio de un material, el cotizador
 * público se entera solo.
 *
 * Lo que este servicio agrega sobre el flujo interno es todo lo que hace que
 * un precio sea publicable:
 *
 * - **Lista blanca**: solo productos con `cotizable_web = SI` y con receta
 *   cargada. Sin BOM el costo daría 0 y el motor devolvería un precio de
 *   cero: publicar eso sería peor que no publicar nada.
 * - **Rango, no número exacto**: total ± `config('cotizador.holgura')`. El
 *   cotizador no conoce acabados, logística ni instalación.
 * - **Nada interno sale de acá**: el resultado no lleva costo, margen, factor
 *   de complejidad, IT, IUE, utilidad ni semáforo. Eso es información de
 *   rentabilidad de la empresa (ver el docblock de `Cotizacion::ESTADOS_MARGEN`),
 *   y este método alimenta una respuesta HTTP pública.
 */
class CotizadorPublicoService
{
    public function __construct(
        private readonly CosteoProductoService $costeoProducto,
        private readonly MotorMargenService $motorMargen,
    ) {}

    /**
     * Productos ofrecidos en el cotizador público, ordenados para el selector.
     *
     * Quién entra y quién no lo decide el scope `cotizableWeb` de
     * App\Models\Producto — una sola definición de la lista blanca, para que
     * el catálogo que se muestra y el que se acepta al calcular no puedan
     * separarse.
     *
     * @return Collection<int, Producto>
     */
    public function catalogo(): Collection
    {
        return Producto::query()
            ->cotizableWeb()
            ->with('categoriaProducto:id,nombre')
            ->orderBy('nombre')
            ->get(['id', 'categoria_producto_id', 'nombre', 'descripcion', 'unidad_medida', 'requiere_medidas']);
    }

    /**
     * Nivel de complejidad con el que se cotiza en la web.
     *
     * `config('cotizador.tipo_proyecto')` guarda el NOMBRE (sobrevive a un
     * reseeder, a diferencia de un id). Sin configurar, o si el nombre ya no
     * existe, cae al primer tipo activo por `orden`; si tampoco hay ninguno,
     * null — y ahí `MotorMargenService::calcularCon()` aplica factor 1 con el
     * margen sugerido por defecto.
     */
    public function tipoProyecto(): ?TipoProyecto
    {
        $consulta = TipoProyecto::query()->where('estado', 'ACTIVO');

        $nombre = config('cotizador.tipo_proyecto');

        if (is_string($nombre) && $nombre !== '') {
            $porNombre = (clone $consulta)->where('nombre', $nombre)->first();

            if ($porNombre !== null) {
                return $porNombre;
            }
        }

        return $consulta->orderBy('orden')->first();
    }

    /**
     * Calcula la estimación de una lista de trabajos.
     *
     * Cada línea llega ya validada (App\Http\Requests\Cotizador\*): producto
     * de la lista blanca, medidas dentro de los topes y cantidad entera. Los
     * precios NUNCA se leen de la petición — el navegador manda qué se quiere
     * cotizar, no cuánto cuesta.
     *
     * @param  list<array{producto_id: int|string, ancho?: float|string|null, alto?: float|string|null, cantidad: float|string}>  $lineas
     * @return array{lineas: list<array{producto_id: int, producto: string, descripcion: string, ancho: float|null, alto: float|null, cantidad: float, precio_unitario: float, subtotal: float}>, subtotal: float, iva: float, total: float, estimado_min: float, estimado_max: float, holgura: float, vigencia_dias: int, fecha_vencimiento: string}
     *
     * @throws InvalidArgumentException si el producto no está ofrecido en la web o le faltan las medidas que su unidad exige.
     * @throws FormulaInvalidaException si una fórmula de la receta no evalúa con esas medidas.
     */
    public function estimar(array $lineas): array
    {
        $tipoProyecto = $this->tipoProyecto();
        $productos = $this->productosDe($lineas);

        $calculadas = [];
        $costoBase = 0.0;
        $costoAjustado = 0.0;

        foreach ($lineas as $linea) {
            $productoId = (int) $linea['producto_id'];

            // Los Form Requests ya lo validan, pero este servicio no puede
            // depender de quién lo llame: sin la guarda sería un índice
            // indefinido en vez de un error legible.
            $producto = $productos[$productoId] ?? throw new InvalidArgumentException(
                "El producto {$productoId} no está disponible en el cotizador público."
            );

            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;
            $cantidad = (float) $linea['cantidad'];

            // `cantidad: 1` — la receta describe UNA unidad; la cantidad
            // pedida multiplica después, igual que en PrecioSugeridoService.
            $costeo = $this->costeoProducto->calcular(
                $producto,
                new MedidasCotizacion(ancho: $ancho, alto: $alto),
                1.0,
            );

            $motor = $this->motorMargen->calcularCon($tipoProyecto, round($costeo->costoMaterial, 2));

            $precioUnitario = round($motor->precio, 2);

            $costoBase += $motor->costoBase * $cantidad;
            $costoAjustado += $motor->costoAjustado * $cantidad;

            $calculadas[] = [
                'producto_id' => $producto->id,
                'producto' => $producto->nombre,
                'descripcion' => $this->describir($producto, $ancho, $alto),
                'ancho' => $ancho,
                'alto' => $alto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => round($precioUnitario * $cantidad, 2),
            ];
        }

        $subtotal = round(array_sum(array_column($calculadas, 'subtotal')), 2);

        // Se agrega con el motor —y no con un `* 0.13` suelto— por la misma
        // razón que CotizacionController::calcularMontos: el IVA de la
        // empresa vive en config/margen.php y en un solo lugar.
        $agregado = $this->motorMargen->evaluar(
            costoBase: round($costoBase, 2),
            costoAjustado: round($costoAjustado, 2),
            precio: $subtotal,
        );

        $iva = round($agregado->iva, 2);
        $total = round($subtotal + $iva, 2);
        $holgura = max(0.0, (float) config('cotizador.holgura', 0.15));
        $vigenciaDias = max(1, (int) config('cotizador.vigencia_dias', 7));

        return [
            'lineas' => $calculadas,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'estimado_min' => round($total * (1 - $holgura), 2),
            'estimado_max' => round($total * (1 + $holgura), 2),
            'holgura' => $holgura,
            'vigencia_dias' => $vigenciaDias,
            'fecha_vencimiento' => $this->vencimiento($vigenciaDias)->toDateString(),
        ];
    }

    /**
     * Fecha hasta la que vale una estimación emitida hoy.
     */
    public function vencimiento(?int $vigenciaDias = null): Carbon
    {
        return today()->addDays($vigenciaDias ?? max(1, (int) config('cotizador.vigencia_dias', 7)));
    }

    /**
     * Trae de una sola consulta los productos del detalle, con su receta ya
     * cargada (si no, `CosteoProductoService` dispara un `loadMissing` por
     * línea) y re-verificando la lista blanca: aunque el Form Request ya lo
     * validó, este servicio no puede depender de quién lo llame.
     *
     * @param  list<array{producto_id: int|string}>  $lineas
     * @return array<int, Producto>
     */
    private function productosDe(array $lineas): array
    {
        $ids = array_unique(array_map(fn (array $linea): int => (int) $linea['producto_id'], $lineas));

        return Producto::query()
            ->whereIn('id', $ids)
            ->cotizableWeb()
            ->with(['productoMateriales.material', 'productoMateriales.formula'])
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Texto de la línea tal como lo lee el visitante: el producto y, si las
     * tiene, sus medidas en el formato local (coma decimal).
     */
    private function describir(Producto $producto, ?float $ancho, ?float $alto): string
    {
        if ($ancho === null || $alto === null) {
            return $producto->nombre;
        }

        return sprintf(
            '%s %s × %s m',
            $producto->nombre,
            number_format($ancho, 2, ',', '.'),
            number_format($alto, 2, ',', '.'),
        );
    }
}
