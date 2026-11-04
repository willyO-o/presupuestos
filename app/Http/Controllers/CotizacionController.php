<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Cotizacion\StoreCotizacionRequest;
use App\Http\Requests\Cotizacion\UpdateCotizacionRequest;
use App\Models\Area;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Producto;
use App\Models\TipoProyecto;
use App\Notifications\CotizacionAprobada;
use App\Services\Calculo\MotorMargenService;
use App\Services\Calculo\PrecioSugeridoService;
use App\Services\Imagen\ConvierteImagenAJpgService;
use App\Services\Pdf\MontoEnLetras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;
use InvalidArgumentException;

class CotizacionController extends Controller
{
    public function __construct(
        private readonly MotorMargenService $motorMargen,
        private readonly ConvierteImagenAJpgService $convierteImagen,
    ) {}

    /**
     * Listado paginado, con búsqueda (código/cliente/observaciones) y
     * filtros por estado, cliente y sucursal. `withQueryString()` mantiene
     * los filtros al navegar entre páginas.
     */
    public function index(Request $request): Response
    {
        $cotizaciones = Cotizacion::query()
            ->with(['cliente:id,razon_social', 'sucursal:id,nombre'])
            ->visiblePara($request->user())
            ->search($request->query('search'))
            ->tipo($request->query('tipo'))
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
            // El desplegable de sucursales lista solo las que el usuario
            // administra: ofrecer un filtro que siempre devuelve vacío parece
            // un error del sistema, no una restricción de permisos.
            'sucursales' => $request->user()->sucursalesDisponibles(),
            'estados' => Cotizacion::ESTADOS,
            'estadosMargen' => Cotizacion::ESTADOS_MARGEN,
            'tipos' => Cotizacion::TIPOS,
            'filters' => $request->only(['search', 'tipo', 'estado', 'cliente', 'sucursal']),
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

    /**
     * Cotizador de muebles: asistente por pasos (mueble → medidas → costo de
     * una unidad → precio y vista del cliente). Guarda con `store`, igual que
     * el formulario general, con `tipo = MUEBLE`.
     */
    public function createMueble(Request $request): Response
    {
        return inertia('Cotizaciones/Muebles/Create', [
            ...$this->datosFormulario($request, 'MUEBLE'),
            'pageTitle' => 'Cotizar mueble',
            'breadcrumbs' => ['Ventas', 'Cotizaciones', 'Cotizar mueble'],
        ]);
    }

    public function store(StoreCotizacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $cotizacion = DB::transaction(function () use ($request, $datos): Cotizacion {
            $detalles = $this->normalizarDetalles($request, $datos['detalles']);
            $instalacionConIva = $this->instalacionConIva($datos);
            $montos = $this->calcularMontos(
                $detalles,
                (float) ($datos['descuento'] ?? 0),
                ($datos['aplicar_iva'] ?? true),
                $instalacionConIva,
            );

            $cotizacion = Cotizacion::create([
                'codigo_verificacion' => $this->generarCodigoVerificacion(),
                'tipo' => $datos['tipo'] ?? 'GENERAL',
                'cliente_id' => $datos['cliente_id'],
                'empleado_id' => $datos['empleado_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'fecha' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'estado' => 'PENDIENTE',
                'observaciones' => $datos['observaciones'] ?? null,
                ...$this->condicionesDeVenta($datos),
                'instalacion_con_iva' => $instalacionConIva ? 'SI' : 'NO',
                ...$montos,
            ]);

            $this->guardarDetalles($cotizacion, $detalles);

            return $cotizacion;
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->codigo_verificacion} creada correctamente.");
    }

    public function show(Request $request, Cotizacion $cotizacion): Response
    {
        $this->assertVisible($request, $cotizacion);

        $cotizacion->load([
            'cliente',
            'empleado',
            'sucursal',
            'detalles.producto:id,nombre,unidad_medida',
            'detalles.tipoProyecto:id,nombre,factor_complejidad,margen_minimo',
            'detalles.items',
            'pedido:id,cotizacion_id,numero_pedido',
        ]);

        return inertia('Cotizaciones/Show', [
            'cotizacion' => $cotizacion,
            // Desglose de rentabilidad por línea: es información interna
            // (no se imprime en el documento del cliente), por eso viaja
            // aparte y no dentro del detalle.
            'margen' => $this->margenPorLinea($cotizacion),
            // "Son: Dos Mil … 00/100 Bolivianos", el mismo texto que el PDF.
            'montoEnLetras' => MontoEnLetras::convertir($cotizacion->total),
            // Tasas y umbrales para "Ver fórmula y criterios del semáforo".
            'config' => ['impuestos' => config('margen.impuestos'), 'semaforo' => config('margen.semaforo')],
            'pageTitle' => "Cotización {$cotizacion->codigo_verificacion}",
            'breadcrumbs' => ['Ventas', 'Cotizaciones', $cotizacion->codigo_verificacion],
        ]);
    }

    public function edit(Request $request, Cotizacion $cotizacion): RedirectResponse|Response
    {
        $this->assertVisible($request, $cotizacion);

        if (! $cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo se pueden editar cotizaciones pendientes.');
        }

        $cotizacion->load([
            'detalles.producto:id,nombre,unidad_medida,requiere_medidas',
            'detalles.items',
        ]);

        // Cada tipo se edita con la pantalla con la que se armó.
        $pagina = $cotizacion->esDeMuebles() ? 'Cotizaciones/Muebles/Edit' : 'Cotizaciones/Edit';

        return inertia($pagina, [
            ...$this->datosFormulario($request, $cotizacion->tipo),
            'cotizacion' => $cotizacion,
            'pageTitle' => "Editar cotización {$cotizacion->codigo_verificacion}",
            'breadcrumbs' => ['Ventas', 'Cotizaciones', $cotizacion->codigo_verificacion, 'Editar'],
        ]);
    }

    public function update(UpdateCotizacionRequest $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->assertVisible($request, $cotizacion);

        if (! $cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo se pueden editar cotizaciones pendientes.');
        }

        $datos = $request->validated();

        DB::transaction(function () use ($request, $cotizacion, $datos): void {
            $detalles = $this->normalizarDetalles($request, $datos['detalles']);
            $instalacionConIva = $this->instalacionConIva($datos);
            $montos = $this->calcularMontos(
                $detalles,
                (float) ($datos['descuento'] ?? 0),
                ($datos['aplicar_iva'] ?? true),
                $instalacionConIva,
            );

            $cotizacion->update([
                'cliente_id' => $datos['cliente_id'],
                'empleado_id' => $datos['empleado_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'fecha' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                ...$this->condicionesDeVenta($datos),
                'instalacion_con_iva' => $instalacionConIva ? 'SI' : 'NO',
                ...$montos,
            ]);

            // Imágenes de las líneas ANTES de borrarlas: el detalle se
            // reemplaza entero (más simple y seguro que un diff línea por
            // línea), pero el archivo físico no se va solo con la fila. Sin
            // este registro, cada guardado dejaría huérfanas en disco las
            // imágenes que no se reutilizan — justo lo contrario de
            // "aligerar el peso en el servidor".
            $imagenesPrevias = $cotizacion->detalles()->pluck('imagen')->filter()->all();

            $cotizacion->detalles()->delete();
            $this->guardarDetalles($cotizacion, $detalles);

            $imagenesConservadas = array_filter(array_column($detalles, 'imagen'));

            foreach (array_diff($imagenesPrevias, $imagenesConservadas) as $huerfana) {
                $this->convierteImagen->borrar($huerfana);
            }
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->codigo_verificacion} actualizada correctamente.");
    }

    public function destroy(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->assertVisible($request, $cotizacion);

        if ($cotizacion->estado === 'CONVERTIDA') {
            return redirect()->route('cotizaciones.index')
                ->with('error', 'No se puede eliminar una cotización ya convertida en pedido.');
        }

        foreach ($cotizacion->detalles()->pluck('imagen')->filter() as $imagen) {
            $this->convierteImagen->borrar($imagen);
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
    public function aprobar(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->assertVisible($request, $cotizacion);

        return $this->cambiarEstado($cotizacion, 'APROBADA', 'aprobada');
    }

    public function rechazar(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->assertVisible($request, $cotizacion);

        return $this->cambiarEstado($cotizacion, 'RECHAZADA', 'rechazada');
    }

    /**
     * Arma la hoja de costos de un producto para las medidas dadas (insumos
     * del BOM + precio sugerido por el motor de margen con el nivel de
     * complejidad elegido), sin guardar nada — lo usa el formulario al
     * agregar una línea (petición JSON vía axios, no un visit de Inertia).
     */
    public function costear(Request $request, PrecioSugeridoService $precioSugerido): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:producto,id'],
            'ancho' => ['nullable', 'numeric', 'min:0'],
            'alto' => ['nullable', 'numeric', 'min:0'],
            'profundo' => ['nullable', 'numeric', 'min:0'],
            'niveles' => ['nullable', 'integer', 'min:1', 'max:50'],
            'tipo_proyecto_id' => ['nullable', 'integer', 'exists:tipo_proyecto,id'],
            'instalacion' => ['nullable', 'numeric', 'min:0'],
        ]);

        $producto = Producto::findOrFail($datos['producto_id']);
        $tipoProyecto = isset($datos['tipo_proyecto_id'])
            ? TipoProyecto::find($datos['tipo_proyecto_id'])
            : null;

        try {
            $resultado = $precioSugerido->calcular(
                $producto,
                $datos['ancho'] ?? null,
                $datos['alto'] ?? null,
                $datos['profundo'] ?? null,
                $tipoProyecto,
                (float) ($datos['instalacion'] ?? 0),
                isset($datos['niveles']) ? (int) $datos['niveles'] : null,
            );
        } catch (InvalidArgumentException|FormulaInvalidaException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            ...$resultado,
            'descripcion_mano_obra' => PrecioSugeridoService::descripcionManoObraDe($producto),
            'descripcion_costo_base' => PrecioSugeridoService::DESCRIPCION_COSTO_BASE,
        ]);
    }

    /**
     * Corre el motor de margen sobre una hoja de costos escrita a mano (sin
     * producto de catálogo), para el panel en vivo del formulario. No guarda
     * nada.
     */
    public function simular(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'costo_base' => ['required', 'numeric', 'min:0'],
            'tipo_proyecto_id' => ['nullable', 'integer', 'exists:tipo_proyecto,id'],
            'instalacion' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tipoProyecto = isset($datos['tipo_proyecto_id'])
            ? TipoProyecto::find($datos['tipo_proyecto_id'])
            : null;

        return response()->json($this->motorMargen->calcularCon(
            $tipoProyecto,
            (float) $datos['costo_base'],
            (float) ($datos['instalacion'] ?? 0),
        )->toArray());
    }

    /**
     * Corta las rutas de detalle sobre una cotización de otra sucursal.
     *
     * Pregunta con el MISMO scope que usa el listado (`esVisiblePara`) en vez
     * de comparar `sucursal_id` a mano: así no puede haber una cotización que
     * aparezca en la lista y dé 403 al abrirla, ni al revés.
     */
    private function assertVisible(Request $request, Cotizacion $cotizacion): void
    {
        abort_unless($cotizacion->esVisiblePara($request->user()), 403);
    }

    /**
     * Catálogos de los formularios de crear/editar. `$tipo` decide qué
     * productos se ofrecen: el cotizador general no lista plantillas de
     * mueble y el de muebles solo lista esas.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, string $tipo = 'GENERAL'): array
    {
        $datos = [
            'clientes' => Cliente::query()->estado('ACTIVO')->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nit']),
            'empleados' => Empleado::query()->visiblePara($request->user())->estado('ACTIVO')->orderBy('nombres')
                ->get(['id', 'nombres', 'paterno', 'materno', 'cargo']),
            // Solo las sucursales que administra: es el desplegable con el que
            // se ELIGE la sucursal de la cotización, así que ofrecer una ajena
            // sería ofrecerle crear algo que después no podría ni abrir. El
            // Form Request lo valida igual (ver StoreCotizacionRequest).
            'sucursales' => $request->user()->sucursalesDisponibles(soloActivas: true),
            // 'imagen' viaja en el select para que el accesor 'imagen_url'
            // se calcule (necesita el atributo base cargado); el formulario
            // la usa para ofrecer "usar imagen del producto" por línea.
            'productos' => Producto::query()->estado('ACTIVO')->modoCotizacion($tipo)->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'unidad_medida', 'requiere_medidas', 'precio_base', 'costo_mano_obra', 'imagen']),
            'tiposProyecto' => TipoProyecto::query()->estado('ACTIVO')->ordenado()
                ->get(['id', 'nombre', 'descripcion', 'factor_complejidad', 'margen_minimo']),
            'tiposItem' => CotizacionDetalleItem::ETIQUETAS_TIPO,
            'empleadoActualId' => $request->user()->empleado?->id,
            // Solo para los modales de alta rápida (Nuevo cliente/vendedor/
            // producto embebidos en el formulario, ver
            // Components/QuickCreateModal.vue) — el formulario de cotización
            // en sí no los usa para nada más.
            'categoriasProducto' => CategoriaProducto::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'cargosEmpleado' => Empleado::cargos(),
            'config' => [
                'margen_sugerido' => (float) config('cotizacion.margen_sugerido'),
                'dias_vencimiento' => (int) config('cotizacion.dias_vencimiento'),
                'costo_hora_mano_obra' => (float) config('cotizacion.costo_hora_mano_obra'),
                'recargo_nocturno' => (float) config('cotizacion.recargo_nocturno'),
                'instalacion_con_iva' => (bool) config('cotizacion.instalacion_con_iva'),
                'impuestos' => config('margen.impuestos'),
                'semaforo' => config('margen.semaforo'),
            ],
        ];

        // El asistente de muebles ofrece el catálogo de materiales al
        // "Agregar insumo" (fierros, trupán, pintura, pegamento…) con su
        // precio vigente.
        if ($tipo === 'MUEBLE') {
            $datos['materiales'] = Material::query()
                ->where('estado', 'ACTIVO')
                ->with('categoriaMaterial:id,nombre')
                ->orderBy('nombre')
                ->get(['id', 'categoria_material_id', 'nombre', 'unidad_medida', 'precio_unitario']);
        }

        return $datos;
    }

    private function cambiarEstado(Cotizacion $cotizacion, string $nuevoEstado, string $verbo): RedirectResponse
    {
        if ($cotizacion->estado !== 'PENDIENTE') {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo una cotización pendiente puede aprobarse o rechazarse.');
        }

        $cotizacion->update(['estado' => $nuevoEstado]);

        if ($nuevoEstado === 'APROBADA' && ($vendedor = $cotizacion->vendedor()) !== null) {
            $vendedor->notify(new CotizacionAprobada($cotizacion));
        }

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$verbo} correctamente.");
    }

    /**
     * Aplica el motor de margen a cada línea del detalle, en el SERVIDOR,
     * ignorando los montos que haya mandado el navegador:
     *
     *   costo base (unitario) = suma de los insumos de la línea
     *   costo ajustado        = costo base × factor del tipo de proyecto
     *   precio unitario       = costo ajustado × (1 + margen mínimo)
     *                           …salvo que el vendedor lo haya fijado a mano
     *                           (`precio_manual = SI`), que es su prerrogativa
     *   subtotal              = precio unitario × cantidad
     *
     * El factor y el margen se copian a la línea como foto histórica: si
     * mañana cambian en el CRUD, este presupuesto sigue explicando su precio.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function normalizarDetalles(Request $request, array $detalles): array
    {
        $tipos = $this->tiposProyectoDe($detalles);
        $productosConImagen = $this->productosConImagenDe($detalles);

        return array_values(array_map(function (array $linea, int $index) use ($tipos, $request, $productosConImagen): array {
            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;
            $profundidad = isset($linea['profundidad']) ? round((float) $linea['profundidad'], 2) : null;
            $niveles = isset($linea['niveles']) ? (int) $linea['niveles'] : null;
            $cantidad = (float) $linea['cantidad'];
            $instalacionDetalle = $this->normalizarInstalacion($linea['instalacion_detalle'] ?? null);
            $instalacion = $instalacionDetalle['total'] ?? round((float) ($linea['instalacion'] ?? 0), 2);

            $items = $this->normalizarItems($linea['items'] ?? []);
            $costoBaseUnitario = round(array_sum(array_column($items, 'subtotal')), 2);

            $tipoProyecto = $tipos->get($linea['tipo_proyecto_id'] ?? null);
            $motor = $this->motorMargen->calcularCon($tipoProyecto, $costoBaseUnitario, $instalacion);

            // Sin hoja de costos no hay nada que calcular: el precio de una
            // línea suelta (reventa, servicio de terceros, ítem heredado) es
            // por definición manual.
            // Las modalidades de precio manual del cotizador de muebles
            // (por unidad o por m²) también lo son.
            $modalidad = $linea['modalidad'] ?? null;
            $precioManual = ($linea['precio_manual'] ?? 'NO') === 'SI'
                || $items === []
                || in_array($modalidad, ['PRECIO_MANUAL', 'SUPERFICIE'], true);
            $precioUnitario = $precioManual
                ? round((float) ($linea['precio_unitario'] ?? 0), 2)
                : round($motor->precio, 2);

            return [
                'producto_id' => $linea['producto_id'] ?? null,
                'tipo_proyecto_id' => $tipoProyecto?->id,
                'descripcion' => $linea['descripcion'],
                'ancho' => $ancho,
                'alto' => $alto,
                'profundidad' => $profundidad,
                'niveles' => $niveles,
                'area_m2' => ($ancho !== null && $alto !== null) ? round($ancho * $alto, 2) : null,
                'imagen' => $this->resolverImagenLinea($request, $index, $linea, $productosConImagen),
                'cantidad' => $cantidad,
                'costo_base' => round($costoBaseUnitario * $cantidad, 2),
                'factor_complejidad' => round($motor->factorComplejidad, 2),
                'margen_aplicado' => round($motor->margen, 4),
                'costo_ajustado' => round($motor->costoAjustado * $cantidad, 2),
                'precio_unitario' => $precioUnitario,
                'precio_manual' => $precioManual ? 'SI' : 'NO',
                'modalidad' => $modalidad,
                'instalacion' => $instalacion,
                'instalacion_detalle' => $instalacionDetalle,
                'subtotal' => round($precioUnitario * $cantidad, 2),
                'items' => $items,
            ];
        }, $detalles, array_keys($detalles)));
    }

    /**
     * Calcula la instalación de una línea a partir de su desglose, en el
     * SERVIDOR (igual que los insumos: nunca se confía en el monto que mandó
     * el navegador):
     *
     *   mano de obra = personas × horas × Bs/hora
     *   nocturna     = mano de obra × (1 + recargo %)   ← horas extra
     *   total        = mano de obra + transporte
     *
     * `null` si la línea no trae desglose: el monto es el escrito a mano.
     *
     * @param  array<string, mixed>|null  $detalle
     * @return array{personas: int, horas: float, costo_hora: float, nocturna: bool, recargo_nocturno: float, transporte: float, mano_obra: float, total: float}|null
     */
    private function normalizarInstalacion(?array $detalle): ?array
    {
        if ($detalle === null || $detalle === []) {
            return null;
        }

        $personas = (int) $detalle['personas'];
        $horas = round((float) $detalle['horas'], 2);
        $costoHora = round((float) $detalle['costo_hora'], 2);
        $nocturna = filter_var($detalle['nocturna'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $recargo = round((float) ($detalle['recargo_nocturno'] ?? config('cotizacion.recargo_nocturno')), 2);
        $transporte = round((float) ($detalle['transporte'] ?? 0), 2);

        $manoObra = round($personas * $horas * $costoHora * ($nocturna ? 1 + $recargo / 100 : 1), 2);

        return [
            'personas' => $personas,
            'horas' => $horas,
            'costo_hora' => $costoHora,
            'nocturna' => $nocturna,
            'recargo_nocturno' => $recargo,
            'transporte' => $transporte,
            'mano_obra' => $manoObra,
            'total' => round($manoObra + $transporte, 2),
        ];
    }

    /**
     * Forma de pago, tiempo y lugar de entrega: texto libre y opcional.
     *
     * @param  array<string, mixed>  $datos
     * @return array{forma_pago: string|null, tiempo_entrega: string|null, lugar_entrega: string|null}
     */
    private function condicionesDeVenta(array $datos): array
    {
        return [
            'forma_pago' => $datos['forma_pago'] ?? null,
            'tiempo_entrega' => $datos['tiempo_entrega'] ?? null,
            'lugar_entrega' => $datos['lugar_entrega'] ?? null,
        ];
    }

    /**
     * Si la instalación paga IVA en esta cotización: lo que marcó el vendedor
     * o, si el formulario no lo mandó, el valor por defecto de la empresa.
     *
     * @param  array<string, mixed>  $datos
     */
    private function instalacionConIva(array $datos): bool
    {
        return (bool) ($datos['instalacion_con_iva'] ?? config('cotizacion.instalacion_con_iva'));
    }

    /**
     * Resuelve la imagen referencial de una línea, en orden de prioridad:
     *
     *   1) un archivo nuevo subido para esa línea (se convierte a JPG),
     *   2) "usar imagen del producto" (se copia y re-codifica a JPG: es una
     *      foto histórica, igual que factor_complejidad/margen_aplicado —
     *      cambiar la imagen del producto después no debe alterar
     *      presupuestos ya emitidos),
     *   3) la imagen que la línea ya tenía (edición sin tocar la imagen),
     *   4) ninguna.
     *
     * @param  Collection<int, Producto>  $productosConImagen
     */
    private function resolverImagenLinea(Request $request, int $index, array $linea, Collection $productosConImagen): ?string
    {
        $archivo = $request->file("detalles.{$index}.imagen");

        if ($archivo) {
            return $this->convierteImagen->guardar($archivo, 'cotizaciones');
        }

        if ($linea['usar_imagen_producto'] ?? false) {
            $producto = $productosConImagen->get($linea['producto_id'] ?? null);

            if ($producto) {
                return $this->convierteImagen->copiarDesde($producto->imagen, 'cotizaciones');
            }
        }

        return $linea['imagen_actual'] ?? null;
    }

    /**
     * Productos referenciados por el detalle que tienen imagen, en una sola
     * consulta (igual que tiposProyectoDe: evita una query por línea dentro
     * del map).
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return Collection<int, Producto>
     */
    private function productosConImagenDe(array $detalles): Collection
    {
        $ids = array_filter(array_column($detalles, 'producto_id'));

        return $ids === []
            ? collect()
            : Producto::query()->whereNotNull('imagen')->findMany(array_unique($ids))->keyBy('id');
    }

    /**
     * Normaliza los insumos de una línea (columnas A-E de la hoja de costos):
     * el subtotal siempre es cantidad × costo unitario calculado aquí, nunca
     * el que mandó el navegador.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizarItems(array $items): array
    {
        return array_values(array_map(function (array $item): array {
            $cantidad = (float) $item['cantidad'];
            $costoUnitario = (float) $item['costo_unitario'];

            return [
                'material_id' => $item['material_id'] ?? null,
                'tipo' => $item['tipo'] ?? 'MATERIAL',
                'descripcion' => $item['descripcion'],
                'unidad' => $item['unidad'] ?? null,
                'cantidad' => round($cantidad, 4),
                'costo_unitario' => round($costoUnitario, 4),
                'subtotal' => round($cantidad * $costoUnitario, 4),
            ];
        }, $items));
    }

    /**
     * Tipos de proyecto referenciados por el detalle, en una sola consulta
     * (evita una query por línea dentro del map).
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return Collection<int, TipoProyecto>
     */
    private function tiposProyectoDe(array $detalles): Collection
    {
        $ids = array_filter(array_column($detalles, 'tipo_proyecto_id'));

        return $ids === []
            ? collect()
            : TipoProyecto::query()->findMany(array_unique($ids))->keyBy('id');
    }

    /**
     * Totaliza el presupuesto y lo pasa por el motor de margen. Como todos
     * los pasos del motor son lineales, evaluar la suma de las líneas
     * equivale a sumar las evaluaciones línea por línea.
     *
     * `descuento` reduce la base imponible, así que empuja el semáforo hacia
     * AMARILLO/ROJO: es exactamente la señal que el vendedor necesita al
     * negociar. La instalación, como en el Excel, se suma después del IVA y
     * no forma parte de la base imponible — salvo que el vendedor marque
     * "Instalación con IVA": entonces se le cobra también el IVA a la
     * instalación. Ese IVA no toca el motor (no es utilidad ni costo: se
     * cobra y se paga), así que el semáforo no cambia.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return array<string, mixed>
     */
    private function calcularMontos(array $detalles, float $descuento, bool $aplicarIva, bool $instalacionConIva = false): array
    {
        $subtotal = round(array_sum(array_column($detalles, 'subtotal')), 2);
        $descuento = round(min(max($descuento, 0), $subtotal), 2);
        $instalacion = round(array_sum(array_column($detalles, 'instalacion')), 2);
        $costoBase = round(array_sum(array_column($detalles, 'costo_base')), 2);
        $costoAjustado = round(array_sum(array_column($detalles, 'costo_ajustado')), 2);

        $motor = $this->motorMargen->evaluar(
            costoBase: $costoBase,
            costoAjustado: $costoAjustado,
            precio: $subtotal - $descuento,
            instalacion: $instalacion,
        );

        $ivaInstalacion = $aplicarIva && $instalacionConIva
            ? round($instalacion * (float) config('margen.impuestos.iva'), 2)
            : 0.0;
        $iva = $aplicarIva ? round($motor->iva, 2) + $ivaInstalacion : 0.0;

        return [
            'costo_base' => $costoBase,
            'costo_ajustado' => $costoAjustado,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'it' => round($motor->it, 2),
            'iue' => round($motor->iue, 2),
            'utilidad_real' => round($motor->utilidadReal, 2),
            'instalacion' => $instalacion,
            'estado_margen' => $motor->estado,
            'total' => round(max($subtotal - $descuento + $iva + $instalacion, 0), 2),
        ];
    }

    /**
     * Crea las líneas del detalle junto con sus insumos. `createMany` no
     * sirve tal cual porque cada línea arrastra su propia colección de items.
     *
     * @param  list<array<string, mixed>>  $detalles
     */
    private function guardarDetalles(Cotizacion $cotizacion, array $detalles): void
    {
        foreach ($detalles as $linea) {
            $items = $linea['items'];
            unset($linea['items']);

            $detalle = $cotizacion->detalles()->create($linea);

            if ($items !== []) {
                $detalle->items()->createMany($items);
            }
        }
    }

    /**
     * Rentabilidad línea por línea para la vista de detalle: el motor
     * evaluado contra el precio REAL de cada línea (que puede ser manual),
     * no contra el sugerido.
     *
     * @return list<array<string, mixed>>
     */
    private function margenPorLinea(Cotizacion $cotizacion): array
    {
        return $cotizacion->detalles->map(function ($detalle): array {
            $resultado = $this->motorMargen->evaluar(
                costoBase: (float) $detalle->costo_base,
                costoAjustado: (float) $detalle->costo_ajustado,
                precio: (float) $detalle->subtotal,
                factorComplejidad: (float) $detalle->factor_complejidad,
                instalacion: (float) $detalle->instalacion,
            );

            return ['detalle_id' => $detalle->id, ...$resultado->toArray()];
        })->all();
    }

    private function generarCodigoVerificacion(): string
    {
        do {
            $codigo = 'COT-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Cotizacion::where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }
}
