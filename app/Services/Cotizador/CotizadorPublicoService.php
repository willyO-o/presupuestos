<?php

namespace App\Services\Cotizador;

use App\Exceptions\FormulaInvalidaException;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\ProductoPrecioWeb;
use App\Models\TipoProyecto;
use App\Services\Calculo\CosteoProductoService;
use App\Services\Calculo\MedidasCotizacion;
use App\Services\Calculo\MotorMargenService;
use App\Services\Calculo\PrecioSugeridoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Estimación aproximada para el sitio público (`/cotizador`).
 *
 * El precio de cada producto sale de su `modo_precio_web`, que el dueño
 * elige en el panel (Ventas → Cotizador web):
 *
 * - MATERIALES: NO es un motor nuevo. Recorre la misma cadena que usa ventas
 *   al costear una línea (PrecioSugeridoService: costo base o receta, mano
 *   de obra, MotorMargenService), para que el número del visitante y el del
 *   vendedor no puedan divergir. Si cambia el margen de un tipo de proyecto
 *   o el precio de un material, el cotizador público se entera solo.
 * - PRECIO_FIJO: `producto.precio_web` (sin IVA) por m², metro lineal o
 *   unidad.
 * - RANGOS: precio por m² según el área del trabajo (`producto_precio_web`).
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
    /** Claves de `configuracion` que el dueño edita desde el panel. */
    public const CLAVE_TIPO_PROYECTO = 'cotizador.tipo_proyecto';

    public const CLAVE_HOLGURA = 'cotizador.holgura';

    public const CLAVE_VIGENCIA_DIAS = 'cotizador.vigencia_dias';

    public function __construct(
        private readonly CosteoProductoService $costeoProducto,
        private readonly MotorMargenService $motorMargen,
        private readonly PrecioSugeridoService $precioSugerido,
    ) {}

    /**
     * Fracción del rango aproximado (0.15 = ±15 %): la del panel o, sin
     * guardar, la de `config/cotizador.php`.
     */
    public function holgura(): float
    {
        return max(0.0, (float) Configuracion::valor(self::CLAVE_HOLGURA, config('cotizador.holgura', 0.15)));
    }

    /**
     * Días de vigencia de una estimación: los del panel o los del config.
     */
    public function vigenciaDias(): int
    {
        return max(1, (int) Configuracion::valor(self::CLAVE_VIGENCIA_DIAS, config('cotizador.vigencia_dias', 7)));
    }

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
     * El panel (o, sin guardar, `config('cotizador.tipo_proyecto')`) guarda el NOMBRE (sobrevive a un
     * reseeder, a diferencia de un id). Sin configurar, o si el nombre ya no
     * existe, cae al primer tipo activo por `orden`; si tampoco hay ninguno,
     * null — y ahí `MotorMargenService::calcularCon()` aplica factor 1 con el
     * margen sugerido por defecto.
     */
    public function tipoProyecto(): ?TipoProyecto
    {
        $consulta = TipoProyecto::query()->where('estado', 'ACTIVO');

        $nombre = Configuracion::valor(self::CLAVE_TIPO_PROYECTO, config('cotizador.tipo_proyecto'));

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

            $precio = $this->precioUnitario($producto, $ancho, $alto, $tipoProyecto);
            $precioUnitario = $precio['precio'];

            $costoBase += $precio['costo_base'] * $cantidad;
            $costoAjustado += $precio['costo_ajustado'] * $cantidad;

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
        $holgura = $this->holgura();
        $vigenciaDias = $this->vigenciaDias();

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
        return today()->addDays($vigenciaDias ?? $this->vigenciaDias());
    }

    /**
     * Precio de venta sin IVA de UNA unidad del producto para el visitante,
     * según su `modo_precio_web`. Lo usa también la vista previa del panel,
     * por eso no exige que el producto ya esté publicado.
     *
     * Los costos solo sirven para el agregado de impuestos de `estimar()`;
     * en los modos de precio fijo y rangos no hay costo que informar.
     *
     * @return array{precio: float, costo_base: float, costo_ajustado: float}
     *
     * @throws InvalidArgumentException si faltan las medidas que pide el producto o no tiene de dónde sacar el precio.
     * @throws FormulaInvalidaException si una fórmula de la receta no evalúa con esas medidas.
     */
    public function precioUnitario(Producto $producto, ?float $ancho, ?float $alto, ?TipoProyecto $tipoProyecto = null): array
    {
        $medidas = new MedidasCotizacion(ancho: $ancho, alto: $alto);

        if ($producto->modo_precio_web === 'PRECIO_FIJO') {
            $precio = (float) $producto->precio_web * $this->costeoProducto->driver($producto, $medidas);

            return ['precio' => round($precio, 2), 'costo_base' => 0.0, 'costo_ajustado' => 0.0];
        }

        if ($producto->modo_precio_web === 'RANGOS') {
            $area = $medidas->area() ?? throw new InvalidArgumentException(
                "«{$producto->nombre}» se cotiza por rangos de área: faltan ancho y alto."
            );
            $precio = (float) $this->rangoDe($producto, $area)->precio_m2 * $area;

            return ['precio' => round($precio, 2), 'costo_base' => 0.0, 'costo_ajustado' => 0.0];
        }

        $calculo = $this->precioSugerido->calcular($producto, $ancho, $alto, tipoProyecto: $tipoProyecto);

        return [
            'precio' => (float) $calculo['precio_sugerido'],
            'costo_base' => (float) $calculo['motor']['costo_base'],
            'costo_ajustado' => (float) $calculo['motor']['costo_ajustado'],
        ];
    }

    /**
     * El rango que corresponde a un área: el primero cuyo tope la cubre, o el
     * último si el área supera todos los topes.
     */
    private function rangoDe(Producto $producto, float $area): ProductoPrecioWeb
    {
        $rangos = $producto->preciosWeb;

        if ($rangos->isEmpty()) {
            throw new InvalidArgumentException("«{$producto->nombre}» no tiene rangos de precio cargados.");
        }

        return $rangos->first(fn (ProductoPrecioWeb $rango): bool => $rango->area_hasta === null
            || $area <= (float) $rango->area_hasta + 1e-9) ?? $rangos->last();
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
            ->with(['productoMateriales.material', 'productoMateriales.formula', 'preciosWeb'])
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
