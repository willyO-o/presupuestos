<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleMaterial;
use App\Models\PedidoSeguimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\TipoProyecto;
use App\Services\Calculo\MotorMargenService;
use App\Services\Calculo\PrecioSugeridoService;
use App\Services\Compra\AprobarCompraService;
use App\Services\Pedido\ConvertirCotizacionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Historial comercial de 24 meses, coherente de punta a punta, para que el
 * BI (y su IA) trabaje con datos con forma de negocio real.
 *
 * Se simula mes a mes, en orden cronológico, con las MISMAS piezas que usa
 * el sistema: AprobarCompraService (precio de material + historial),
 * PrecioSugeridoService/MotorMargenService (hoja de costos y precio),
 * ConvertirCotizacionService (pedido). `Carbon::setTestNow()` hace que cada
 * servicio deje fechas y números del día simulado.
 *
 * Supuestos de negocio (los que un analista buscaría en los gráficos):
 * - Crecimiento ~1,2 % mensual; estacionalidad boliviana: carnaval (feb),
 *   fiestas patrias (ago), fin de año (nov-dic) altos; mar-abr y ene bajos.
 * - Santa Cruz abre en el mes 8 y tarda 3 meses en llegar a su ritmo.
 * - Inflación de materiales: importados (lona, vinil, acrílico) ~1,2 %/mes
 *   con un salto cambiario de +9 % en el mes 14; metal ~0,9 %/mes y un salto
 *   de +24 % la semana pasada (alerta de costo y presupuestos pendientes
 *   desactualizados); madera ~0,6 %; químicos ~0,8 %. Mano de obra +5 % cada
 *   mayo (incremento salarial); impresión acompaña a la tinta importada.
 * - Clientes con forma Pareto: ~10 corporativos hacen la mayor parte de lo
 *   vendido; pymes y personas compran poco y esporádico.
 * - Vendedores con perfiles distintos (EmpleadoSeeder::EMPLEADOS).
 *
 * Escenarios plantados para que las alertas y anomalías tengan qué mostrar:
 * - Un vendedor de La Paz deja de cotizar hace ~5 semanas (sus clientes los
 *   atiende su compañera: la sucursal no cae por eso).
 * - La Paz casi no cotiza la última semana completa (caída de volumen).
 * - "Bebidas Tropicales del Oriente", cliente grande, no compra desde hace
 *   ~4,5 meses (cliente en riesgo).
 * - Una venta reciente con descuento excesivo (margen en rojo) y un pedido
 *   reciente con reimpresión (sobrecosto de material).
 *
 * Determinista (semilla fija). No es idempotente: se salta si ya hay
 * cotizaciones (mismo criterio que los demás seeders de volumen).
 */
class HistorialComercialSeeder extends Seeder
{
    private const SEMILLA = 20241001;

    private const MESES = 24;

    /**
     * Solo para tests: acota la simulación a menos meses (los 24 completos
     * tardan ~30 s). null = MESES.
     */
    public static ?int $mesesParaPruebas = null;

    /** Cotizaciones por semana de las cuatro sucursales a ritmo pleno, al inicio. */
    private const VOLUMEN_SEMANAL = 26.0;

    private const CRECIMIENTO_MENSUAL = 0.012;

    /** Factor estacional por mes calendario. */
    private const ESTACIONALIDAD = [
        1 => 0.82, 2 => 1.06, 3 => 0.80, 4 => 0.86, 5 => 0.97, 6 => 0.90,
        7 => 1.04, 8 => 1.16, 9 => 0.96, 10 => 1.00, 11 => 1.24, 12 => 1.18,
    ];

    /** Inflación mensual por categoría de material. */
    private const INFLACION = [
        'Gigantografía' => 0.012, 'Cerrajería' => 0.009, 'Carpintería' => 0.006,
        'Otros materiales' => 0.010, 'Pinturas' => 0.008,
    ];

    /** Cada cuántos meses se repone cada categoría. */
    private const REPOSICION = [
        'Gigantografía' => 1, 'Cerrajería' => 2, 'Carpintería' => 2, 'Otros materiales' => 2, 'Pinturas' => 3,
    ];

    /**
     * Productos: medidas, cantidad, complejidad, insumos extra (por unidad),
     * plazo de producción y área de elaboración.
     *
     * `area` en m² o null (productos por unidad sin medidas). `receta`:
     * materiales para productos SIN BOM en el catálogo, [nombre, cantidad
     * por unidad (o 'area'/'perimetro' × factor)].
     *
     * @var array<string, array<string, mixed>>
     */
    private const PRODUCTOS = [
        'Banner lona frontlight' => ['ancho' => [1.0, 3.0], 'alto' => [0.8, 2.0], 'cant' => [1, 6], 'tipo' => 'Básico', 'imprime' => true, 'horas' => [1, 2], 'plazo' => [2, 4], 'area_prod' => 'Gigantografía'],
        'Banner roll-up 85x200cm' => ['cant' => [1, 6], 'tipo' => 'Básico', 'imprime' => 1.7, 'horas' => [1, 1], 'otros' => [['Estructura roll-up de aluminio', 165]], 'receta' => [['Lona FrontLight 13oz', 1.8]], 'plazo' => [2, 4], 'area_prod' => 'Gigantografía'],
        'Gigantografía frontlight' => ['ancho' => [3.0, 8.0], 'alto' => [2.0, 4.0], 'cant' => [1, 3], 'tipo' => 'Básico', 'imprime' => true, 'horas' => [2, 5], 'plazo' => [3, 5], 'area_prod' => 'Gigantografía', 'instala' => 0.4],
        'Gigantografía backlight' => ['ancho' => [2.0, 5.0], 'alto' => [1.0, 3.0], 'cant' => [1, 2], 'tipo' => 'Medio', 'imprime' => true, 'horas' => [3, 6], 'plazo' => [4, 6], 'area_prod' => 'Gigantografía', 'instala' => 0.3],
        'Bastidor lona PVC 1440dpi' => ['ancho' => [1.0, 3.0], 'alto' => [1.0, 2.0], 'cant' => [1, 4], 'tipo' => 'Medio', 'imprime' => true, 'horas' => [3, 5], 'plazo' => [4, 7], 'area_prod' => 'Cerrajería'],
        'Bastidor backlight retroiluminado' => ['ancho' => [1.0, 2.5], 'alto' => [0.8, 1.5], 'cant' => [1, 2], 'tipo' => 'Complejo', 'imprime' => true, 'horas' => [6, 10], 'otros' => [['Iluminación LED por m²', 'area', 95]], 'receta' => [['Lona Backlight', 'area', 1.05], ['Tubo cuadrado 40x40x1,5mm', 'perimetro', 1.0]], 'plazo' => [8, 12], 'area_prod' => 'Cerrajería', 'instala' => 0.5],
        'Rotulado vinil adhesivo' => ['ancho' => [1.0, 4.0], 'alto' => [0.5, 1.5], 'cant' => [1, 6], 'tipo' => 'Básico', 'imprime' => true, 'horas' => [1, 3], 'plazo' => [2, 4], 'area_prod' => 'Gigantografía', 'instala' => 0.3],
        'Vinil microperforado (vision control)' => ['ancho' => [1.0, 3.0], 'alto' => [0.8, 2.0], 'cant' => [1, 4], 'tipo' => 'Básico', 'imprime' => true, 'horas' => [1, 3], 'receta' => [['Vinil microperforado (vision control)', 'area', 1.05]], 'plazo' => [3, 5], 'area_prod' => 'Gigantografía', 'instala' => 0.3],
        'Exhibidor de piso MDF a medida' => ['ancho' => [0.5, 0.8], 'alto' => [1.4, 1.8], 'cant' => [2, 12], 'tipo' => 'Medio', 'imprime' => 0.6, 'horas' => [6, 10], 'otros' => [['Pintura y acabado', 38]], 'plazo' => [10, 15], 'area_prod' => 'Carpintería'],
        'Isla promocional' => ['ancho' => [1.5, 2.5], 'alto' => [1.2, 2.2], 'cant' => [1, 3], 'tipo' => 'Complejo', 'imprime' => 2.5, 'horas' => [18, 30], 'receta' => [['Melamina blanca 18mm', 3], ['MDF 9mm', 2], ['Tornillos autorroscantes', 60], ['Pintura esmalte sintético', 2]], 'otros' => [['Herrajes y ruedas', 180]], 'plazo' => [14, 20], 'area_prod' => 'Carpintería', 'instala' => 0.6],
        'Cenefa colgante' => ['cant' => [10, 60], 'tipo' => 'Básico', 'imprime' => 0.25, 'horas' => [0.2, 0.3], 'otros' => [['PVC espumado 3mm', 14]], 'plazo' => [2, 4], 'area_prod' => 'Gigantografía'],
        'Habladores/Stoppers' => ['cant' => [40, 300], 'tipo' => 'Básico', 'imprime' => 0.05, 'horas' => [0.05, 0.08], 'otros' => [['Cartulina plastificada', 2.4]], 'plazo' => [2, 3], 'area_prod' => 'Gigantografía'],
        'Toldo publicitario lona' => ['ancho' => [2.5, 6.0], 'alto' => [1.0, 1.6], 'cant' => [1, 2], 'tipo' => 'Medio', 'imprime' => true, 'horas' => [5, 9], 'plazo' => [7, 11], 'area_prod' => 'Cerrajería', 'instala' => 0.8],
        'Letrero luminoso caja de luz' => ['ancho' => [1.0, 3.0], 'alto' => [0.6, 1.2], 'cant' => [1, 3], 'tipo' => 'Complejo', 'imprime' => true, 'horas' => [8, 14], 'otros' => [['Iluminación LED y fuente', 'area', 110]], 'plazo' => [10, 16], 'area_prod' => 'Cerrajería', 'instala' => 0.85],
        'Letras corpóreas 3D iluminadas' => ['ancho' => [0.8, 3.0], 'alto' => [0.3, 0.8], 'cant' => [1, 2], 'tipo' => 'Complejo', 'profundo' => 0.08, 'horas' => [12, 24], 'otros' => [['Módulos LED y fuente', 'area', 260]], 'plazo' => [12, 18], 'area_prod' => 'Cerrajería', 'instala' => 0.9],
        'Rotulado vehicular completo' => ['cant' => [1, 4], 'tipo' => 'Medio', 'imprime' => 14, 'horas' => [10, 14], 'receta' => [['Vinil adhesivo brillante', 14.5]], 'otros' => [['Laminado protector UV', 150]], 'plazo' => [4, 7], 'area_prod' => 'Gigantografía'],
        'Rotulado vehicular parcial (puertas)' => ['cant' => [1, 6], 'tipo' => 'Básico', 'imprime' => 3.5, 'horas' => [3, 5], 'receta' => [['Vinil adhesivo brillante', 4]], 'otros' => [['Laminado protector UV', 40]], 'plazo' => [3, 5], 'area_prod' => 'Gigantografía'],
    ];

    /** Mezcla de productos por segmento (peso relativo). */
    private const MEZCLA = [
        'corporativo' => ['Gigantografía frontlight' => 14, 'Gigantografía backlight' => 6, 'Banner lona frontlight' => 10, 'Banner roll-up 85x200cm' => 8, 'Exhibidor de piso MDF a medida' => 10, 'Isla promocional' => 5, 'Habladores/Stoppers' => 12, 'Cenefa colgante' => 9, 'Rotulado vehicular completo' => 6, 'Letrero luminoso caja de luz' => 5, 'Letras corpóreas 3D iluminadas' => 3, 'Bastidor lona PVC 1440dpi' => 6, 'Rotulado vinil adhesivo' => 6],
        'agencia' => ['Gigantografía frontlight' => 14, 'Banner lona frontlight' => 14, 'Banner roll-up 85x200cm' => 12, 'Rotulado vinil adhesivo' => 10, 'Habladores/Stoppers' => 10, 'Cenefa colgante' => 8, 'Exhibidor de piso MDF a medida' => 6, 'Bastidor lona PVC 1440dpi' => 6, 'Vinil microperforado (vision control)' => 5],
        'pyme' => ['Letrero luminoso caja de luz' => 14, 'Letras corpóreas 3D iluminadas' => 8, 'Rotulado vinil adhesivo' => 14, 'Vinil microperforado (vision control)' => 8, 'Toldo publicitario lona' => 10, 'Banner lona frontlight' => 14, 'Bastidor lona PVC 1440dpi' => 8, 'Bastidor backlight retroiluminado' => 4, 'Rotulado vehicular parcial (puertas)' => 6],
        'natural' => ['Banner lona frontlight' => 30, 'Rotulado vinil adhesivo' => 15, 'Rotulado vehicular parcial (puertas)' => 15, 'Banner roll-up 85x200cm' => 10, 'Toldo publicitario lona' => 6],
    ];

    /** Frecuencia relativa de compra por segmento (peso en el sorteo de cliente). */
    private const PESO_SEGMENTO = ['corporativo' => 3.5, 'agencia' => 2.5, 'pyme' => 1.2, 'natural' => 0.6];

    private const PROB_APROBACION = ['corporativo' => 0.55, 'agencia' => 0.48, 'pyme' => 0.38, 'natural' => 0.32];

    private const PERFIL = [
        'estrella' => ['peso' => 1.5, 'aprob' => 1.15, 'desc_prob' => 0.18, 'desc' => [0.03, 0.07]],
        'regular' => ['peso' => 1.0, 'aprob' => 1.0, 'desc_prob' => 0.20, 'desc' => [0.03, 0.08]],
        'descuentero' => ['peso' => 1.15, 'aprob' => 1.1, 'desc_prob' => 0.55, 'desc' => [0.08, 0.18]],
        'inactivo_reciente' => ['peso' => 1.0, 'aprob' => 0.95, 'desc_prob' => 0.20, 'desc' => [0.03, 0.08]],
    ];

    private Carbon $inicio;

    private Carbon $hoy;

    private PrecioSugeridoService $precioSugerido;

    private MotorMargenService $motor;

    /** @var Collection<string, Producto> */
    private Collection $productos;

    /** @var Collection<string, Material> */
    private Collection $materiales;

    /** @var Collection<string, TipoProyecto> */
    private Collection $tipos;

    /** @var array<int, array{fecha: string, precio: float}[]> historial de precio en memoria por material */
    private array $preciosHistoricos = [];

    /** @var array<int, float> precio de arranque por material */
    private array $preciosBase = [];

    /** @var array<string, int> */
    private array $areas = [];

    /** @var array<string, list<int>> empleados por área */
    private array $personalPorArea = [];

    public function run(): void
    {
        if (Cotizacion::query()->exists()) {
            $this->command?->warn('HistorialComercialSeeder: ya hay cotizaciones, se omite (usar migrate:fresh --seed).');

            return;
        }

        mt_srand(self::SEMILLA);

        $meses = self::$mesesParaPruebas ?? self::MESES;
        $this->hoy = Carbon::now()->startOfDay();
        $this->inicio = $this->hoy->copy()->startOfMonth()->subMonths($meses);
        $this->precioSugerido = app(PrecioSugeridoService::class);
        $this->motor = app(MotorMargenService::class);
        $this->tipos = TipoProyecto::query()->get()->keyBy('nombre');
        $this->areas = Area::query()->pluck('id', 'nombre')->all();
        $this->personalPorArea = Empleado::query()->get(['id', 'area_id'])->groupBy('area_id')
            ->map(fn ($g) => $g->pluck('id')->all())->all();

        $sucursales = $this->sucursales();
        $clientes = $this->clientes();
        $vendedores = $this->vendedores();

        if ($sucursales->isEmpty() || $clientes->isEmpty() || $vendedores->isEmpty()) {
            $this->command?->warn('HistorialComercialSeeder: faltan sucursales, clientes o vendedores sembrados.');

            return;
        }

        Model::unguarded(function () use ($meses, $sucursales, $clientes, $vendedores): void {
            try {
                for ($mes = 0; $mes <= $meses; $mes++) {
                    DB::transaction(function () use ($mes, $meses, $sucursales, $clientes, $vendedores): void {
                        $this->comprasDelMes($mes, $mes === $meses);
                        $this->recargarCatalogo();
                        $this->cotizacionesDelMes($mes, $sucursales, $clientes, $vendedores);
                    });
                }

                $this->escenariosPlantados($clientes, $vendedores);
            } finally {
                Carbon::setTestNow();
            }
        });
    }

    /* =================================================================
     * Catálogos
     * ============================================================== */

    /**
     * @return Collection<int, array{modelo: Sucursal, abre_mes: int, peso: float, ciudad: string}>
     */
    private function sucursales(): Collection
    {
        $definidas = collect(SucursalSeeder::SUCURSALES)->keyBy('nombre');

        return Sucursal::query()->where('estado', 'ACTIVO')->get()->map(fn (Sucursal $s) => [
            'modelo' => $s,
            'abre_mes' => $definidas[$s->nombre]['abre_mes'] ?? 0,
            'peso' => $definidas[$s->nombre]['peso'] ?? 0.2,
            'ciudad' => $s->ciudad,
        ])->values();
    }

    /**
     * @return Collection<int, array{modelo: Cliente, segmento: string, ciudad: string, peso: float}>
     */
    private function clientes(): Collection
    {
        $definidos = collect(ClienteSeeder::CLIENTES)->keyBy('nit');

        return Cliente::query()->where('estado', 'ACTIVO')->get()->map(function (Cliente $c) use ($definidos) {
            $segmento = $definidos[$c->nit]['segmento'] ?? ($c->tipo === 'NATURAL' ? 'natural' : 'pyme');
            // Cliente estrella: pesa el doble que un corporativo promedio.
            $extra = str_starts_with($c->razon_social, 'Bebidas Tropicales') ? 2.0 : 1.0;

            return [
                'modelo' => $c,
                'segmento' => $segmento,
                'ciudad' => (string) $c->ciudad,
                'peso' => self::PESO_SEGMENTO[$segmento] * $extra * $this->aleatorio(0.7, 1.3),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{modelo: Empleado, perfil: string, sucursal_id: int}>
     */
    private function vendedores(): Collection
    {
        $perfiles = collect(EmpleadoSeeder::EMPLEADOS)->keyBy('ci');

        return Empleado::query()->where('cargo', 'Vendedor')->where('estado', 'ACTIVO')->get()->map(fn (Empleado $e) => [
            'modelo' => $e,
            'perfil' => $perfiles[$e->ci]['perfil'] ?? 'regular',
            'sucursal_id' => $e->sucursal_id,
        ])->values();
    }

    /**
     * Productos con su receta y materiales con su precio vigente: se recargan
     * después de cada ronda de compras (los precios cambiaron).
     */
    private function recargarCatalogo(): void
    {
        $this->productos = Producto::query()
            ->whereIn('nombre', array_keys(self::PRODUCTOS))
            ->with('productoMateriales.material', 'productoMateriales.formula')
            ->get()
            ->keyBy('nombre');

        $this->materiales = Material::query()->with('categoriaMaterial:id,nombre')->get()->keyBy('nombre');
    }

    /* =================================================================
     * Compras
     * ============================================================== */

    private function comprasDelMes(int $mes, bool $esUltimo): void
    {
        $materiales = Material::query()->with('categoriaMaterial:id,nombre')->get();
        $proveedores = Proveedor::query()->get()->keyBy('nit');
        $comprador = Empleado::query()->where('cargo', 'Contador')->value('id') ?? Empleado::query()->value('id');
        $aprobar = app(AprobarCompraService::class);
        $primerDia = $this->inicio->copy()->addMonths($mes);

        // Precio "de lista" del mes por material: base × inflación acumulada
        // × saltos. Solo se materializa cuando hay compra (el precio de un
        // material cambia con cada compra, como en el sistema real).
        $porCategoria = $materiales->groupBy(fn ($m) => $m->categoriaMaterial?->nombre ?? 'Otros materiales');

        foreach ($porCategoria as $categoria => $grupo) {
            $cada = self::REPOSICION[$categoria] ?? 2;

            if (($mes % $cada) !== ($categoria === 'Pinturas' ? 1 : 0) % $cada) {
                continue;
            }

            $fecha = $primerDia->copy()->addDays($this->entero(2, 8));
            if ($fecha->greaterThan($this->hoy)) {
                continue;
            }

            $proveedor = $proveedores[ProveedorSeeder::PROVEEDORES[$categoria]['nit'] ?? ''] ?? $proveedores->first();

            $lineas = $grupo->map(fn (Material $m) => [
                'material_id' => $m->id,
                'cantidad' => $this->cantidadDeCompra($m),
                'precio_unitario' => $this->precioDeLista($m, $categoria, $mes),
            ])->all();

            $this->registrarCompra($fecha, $proveedor, $comprador, $lineas, $aprobar);
        }

        // Salto de metal de la última semana (siderurgia): +24 % en tubos.
        if ($esUltimo) {
            $fecha = $this->hoy->copy()->subDays(6);
            $tubos = $materiales->filter(fn ($m) => str_starts_with($m->nombre, 'Tubo cuadrado'));

            $this->registrarCompra(
                $fecha,
                $proveedores[ProveedorSeeder::PROVEEDORES['Cerrajería']['nit']] ?? $proveedores->first(),
                $comprador,
                $tubos->map(fn (Material $m) => [
                    'material_id' => $m->id,
                    'cantidad' => 120,
                    'precio_unitario' => round((float) $m->precio_unitario * 1.24, 2),
                ])->values()->all(),
                $aprobar,
            );

            // Una compra todavía sin aprobar (queda PENDIENTE en el listado).
            $lona = $materiales->firstWhere('nombre', 'Lona FrontLight 13oz');
            if ($lona) {
                Carbon::setTestNow($this->hoy->copy()->subDay()->setTime(10, 0));
                $compra = Compra::create([
                    'proveedor_id' => ($proveedores[ProveedorSeeder::PROVEEDORES['Gigantografía']['nit']] ?? $proveedores->first())->id,
                    'empleado_id' => $comprador,
                    'numero_factura' => 'F-'.$this->entero(30000, 39999),
                    'fecha' => $this->hoy->copy()->subDay()->toDateString(),
                    'estado' => 'PENDIENTE',
                    'total' => round(320 * (float) $lona->precio_unitario, 2),
                ]);
                $compra->detalles()->create([
                    'material_id' => $lona->id,
                    'cantidad' => 320,
                    'precio_unitario' => (float) $lona->precio_unitario,
                    'subtotal' => round(320 * (float) $lona->precio_unitario, 2),
                ]);
            }
        }
    }

    /**
     * @param  list<array{material_id: int, cantidad: float, precio_unitario: float}>  $lineas
     */
    private function registrarCompra(Carbon $fecha, Proveedor $proveedor, ?int $comprador, array $lineas, AprobarCompraService $aprobar): void
    {
        if ($lineas === []) {
            return;
        }

        Carbon::setTestNow($fecha->copy()->setTime(9, 30));

        $detalles = array_map(fn ($l) => [...$l, 'subtotal' => round($l['cantidad'] * $l['precio_unitario'], 2)], $lineas);

        $compra = Compra::create([
            'proveedor_id' => $proveedor->id,
            'empleado_id' => $comprador,
            'numero_factura' => 'F-'.$this->entero(10000, 29999),
            'fecha' => $fecha->toDateString(),
            'estado' => 'PENDIENTE',
            'total' => round(array_sum(array_column($detalles, 'subtotal')), 2),
        ]);
        $compra->detalles()->createMany($detalles);

        $aprobar->aprobar($compra->fresh());

        foreach ($lineas as $linea) {
            $this->preciosHistoricos[$linea['material_id']][] = ['fecha' => $fecha->toDateString(), 'precio' => (float) $linea['precio_unitario']];
        }
    }

    private function precioDeLista(Material $material, string $categoria, int $mes): float
    {
        $base = $this->precioBase($material);
        $inflacion = (1 + (self::INFLACION[$categoria] ?? 0.008)) ** $mes;

        $importado = in_array($categoria, ['Gigantografía'], true) || str_starts_with($material->nombre, 'Acrílico');
        $salto = $importado && $mes >= 14 ? 1.09 : 1.0;

        return round($base * $inflacion * $salto * $this->aleatorio(0.985, 1.015), 2);
    }

    /**
     * Precio de arranque: el que dejó MaterialSeeder (o el actual si no hay
     * historial todavía). Se memoriza para que la serie no derive.
     */
    private function precioBase(Material $material): float
    {
        return $this->preciosBase[$material->id] ??= (float) $material->precio_unitario;
    }

    private function cantidadDeCompra(Material $material): float
    {
        return match ($material->unidad_medida) {
            'M2' => (float) ($this->entero(1, 3) * 160),
            'METRO_LINEAL' => (float) ($this->entero(10, 30) * 6),
            'LITRO' => round($this->entero(2, 6) * 3.78, 2),
            default => (float) ($material->precio_unitario < 2 ? $this->entero(5, 15) * 100 : $this->entero(10, 40)),
        };
    }

    /**
     * Precio de un material vigente en una fecha (según las compras ya
     * simuladas): para valorizar el consumo real de producción.
     */
    private function precioEn(int $materialId, string $fecha): float
    {
        $precio = null;

        foreach ($this->preciosHistoricos[$materialId] ?? [] as $registro) {
            if ($registro['fecha'] > $fecha) {
                break;
            }
            $precio = $registro['precio'];
        }

        return $precio ?? (float) ($this->materiales->firstWhere('id', $materialId)?->precio_unitario ?? 0);
    }

    /* =================================================================
     * Cotizaciones
     * ============================================================== */

    /**
     * @param  Collection<int, array<string, mixed>>  $sucursales
     * @param  Collection<int, array<string, mixed>>  $clientes
     * @param  Collection<int, array<string, mixed>>  $vendedores
     */
    private function cotizacionesDelMes(int $mes, Collection $sucursales, Collection $clientes, Collection $vendedores): void
    {
        $dia = $this->inicio->copy()->addMonths($mes);
        $fin = $dia->copy()->endOfMonth()->min($this->hoy);
        $tendencia = (1 + self::CRECIMIENTO_MENSUAL) ** $mes;
        $ultimaSemana = $this->hoy->copy()->startOfWeek(Carbon::MONDAY)->subWeek();

        while ($dia->lessThanOrEqualTo($fin)) {
            $factorDia = match ($dia->dayOfWeekIso) {
                7 => 0.0,
                6 => 0.55,
                default => 1.0,
            };

            if ($factorDia > 0) {
                foreach ($sucursales as $sucursal) {
                    if ($mes < $sucursal['abre_mes']) {
                        continue;
                    }

                    // Rampa de apertura (solo sucursales nuevas): 3 meses
                    // hasta el ritmo pleno.
                    $rampa = $sucursal['abre_mes'] > 0 ? min(1.0, 0.4 + 0.2 * ($mes - $sucursal['abre_mes'])) : 1.0;
                    $lambda = self::VOLUMEN_SEMANAL / 5.55 * $sucursal['peso'] * $factorDia
                        * self::ESTACIONALIDAD[$dia->month] * $tendencia * $rampa;

                    // Escenario: La Paz casi no cotiza la última semana completa.
                    if (str_starts_with($sucursal['modelo']->nombre, 'La Paz') && $dia->between($ultimaSemana, $ultimaSemana->copy()->endOfWeek())) {
                        $lambda *= 0.1;
                    }

                    for ($n = $this->poisson($lambda); $n > 0; $n--) {
                        $this->crearCotizacion($dia->copy(), $sucursal, $clientes, $vendedores);
                    }
                }
            }

            $dia->addDay();
        }
    }

    /**
     * @param  array<string, mixed>  $sucursal
     * @param  Collection<int, array<string, mixed>>  $clientes
     * @param  Collection<int, array<string, mixed>>  $vendedores
     */
    private function crearCotizacion(Carbon $fecha, array $sucursal, Collection $clientes, Collection $vendedores, array $forzar = []): ?Cotizacion
    {
        $cliente = $forzar['cliente'] ?? $this->elegirCliente($fecha, $sucursal, $clientes);
        $vendedor = $forzar['vendedor'] ?? $this->elegirVendedor($fecha, $sucursal, $vendedores);

        if ($cliente === null || $vendedor === null) {
            return null;
        }

        $segmento = $cliente['segmento'];
        $perfil = self::PERFIL[$vendedor['perfil']];

        $lineas = [];
        $numLineas = $forzar['lineas'] ?? match ($segmento) {
            'corporativo' => $this->elegirPonderado([1 => 40, 2 => 35, 3 => 25]),
            'agencia' => $this->elegirPonderado([1 => 50, 2 => 35, 3 => 15]),
            default => $this->elegirPonderado([1 => 80, 2 => 20]),
        };

        $productosElegidos = $forzar['productos'] ?? [];
        for ($i = count($productosElegidos); $i < $numLineas; $i++) {
            $productosElegidos[] = $this->elegirProducto($segmento, $fecha);
        }

        foreach (array_unique($productosElegidos) as $nombre) {
            $linea = $this->armarLinea($nombre, $segmento, $fecha);
            if ($linea !== null) {
                $lineas[] = $linea;
            }
        }

        if ($lineas === []) {
            return null;
        }

        $descuento = 0.0;
        $subtotal = array_sum(array_column($lineas, 'subtotal'));
        if (isset($forzar['descuento'])) {
            $descuento = round($subtotal * $forzar['descuento'], 2);
        } elseif ($this->aleatorio() < $perfil['desc_prob']) {
            $descuento = round($subtotal * $this->aleatorio(...$perfil['desc']), 2);
        }

        $aplicaIva = match ($segmento) {
            'corporativo', 'agencia' => true,
            'pyme' => $this->aleatorio() < 0.7,
            default => $this->aleatorio() < 0.3,
        };

        Carbon::setTestNow($fecha->copy()->setTime($this->entero(8, 18), $this->entero(0, 59)));

        $cotizacion = Cotizacion::create([
            'codigo_verificacion' => 'COT-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
            'cliente_id' => $cliente['modelo']->id,
            'empleado_id' => $vendedor['modelo']->id,
            'sucursal_id' => $sucursal['modelo']->id,
            'fecha' => $fecha->toDateString(),
            'fecha_vencimiento' => $fecha->copy()->addDays(15)->toDateString(),
            'estado' => 'PENDIENTE',
            'observaciones' => null,
            ...$this->totalizar($lineas, $descuento, $aplicaIva),
        ]);

        $items = [];
        foreach ($lineas as $linea) {
            $insumos = $linea['items'];
            unset($linea['items']);
            $detalle = $cotizacion->detalles()->create($linea);

            foreach ($insumos as $insumo) {
                $items[] = [...$insumo, 'cotizacion_detalle_id' => $detalle->id, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        CotizacionDetalleItem::query()->insert($items);

        $this->resolverDestino($cotizacion, $segmento, $perfil, $forzar);

        return $cotizacion;
    }

    /**
     * Línea con hoja de costos completa: materiales (BOM del catálogo o
     * receta), impresión, mano de obra y otros insumos → motor de margen.
     *
     * @return array<string, mixed>|null
     */
    private function armarLinea(string $nombre, string $segmento, Carbon $fecha): ?array
    {
        $spec = self::PRODUCTOS[$nombre] ?? null;
        $producto = $this->productos[$nombre] ?? null;

        if ($spec === null || $producto === null) {
            return null;
        }

        $escala = match ($segmento) {
            'corporativo' => 1.25,
            'agencia' => 1.1,
            'natural' => 0.7,
            default => 0.9,
        };

        $ancho = isset($spec['ancho']) ? round(min($spec['ancho'][1], $this->aleatorio(...$spec['ancho']) * $escala), 2) : null;
        $alto = isset($spec['alto']) ? round(min($spec['alto'][1], $this->aleatorio(...$spec['alto']) * sqrt($escala)), 2) : null;
        $area = ($ancho !== null && $alto !== null) ? $ancho * $alto : null;
        $perimetro = ($ancho !== null && $alto !== null) ? 2 * ($ancho + $alto) : null;
        $cantidad = (float) max(1, (int) round($this->aleatorio(...$spec['cant']) * ($segmento === 'corporativo' ? 1.4 : 1.0)));

        $items = $this->insumosMateriales($producto, $spec, $ancho, $alto, $area, $perimetro);

        if (($spec['imprime'] ?? false) !== false) {
            $m2 = $spec['imprime'] === true ? ($area ?? 1.0) : (float) $spec['imprime'];
            $items[] = $this->item(null, 'IMPRESION', 'Impresión digital', 'M2', $m2, $this->costoImpresion($fecha));
        }

        foreach ($spec['otros'] ?? [] as $otro) {
            [$descripcion, $a, $b] = array_pad($otro, 3, null);
            [$cant, $costo, $unidad] = $a === 'area' ? [$area ?? 1.0, (float) $b, 'M2'] : [1.0, (float) $a, 'UNIDAD'];
            $items[] = $this->item(null, 'OTRO', $descripcion, $unidad, $cant, round($costo * $this->inflacionGeneral($fecha), 2));
        }

        $horas = round($this->aleatorio(...$spec['horas']), 2);
        $items[] = $this->item(null, 'MANO_OBRA', 'Horas de taller', 'HORA', $horas, $this->tarifaHora($fecha));

        $costoUnitario = round(array_sum(array_column($items, 'subtotal')), 2);
        $tipo = $this->tipos[$this->tipoDeLinea($spec, $area)] ?? null;
        $instalacion = $this->aleatorio() < ($spec['instala'] ?? 0) ? round($this->aleatorio(150, 650) * $escala, 2) : 0.0;

        $resultado = $this->motor->calcularCon($tipo, $costoUnitario, $instalacion);

        // ~6 % de las líneas: el vendedor redondea el precio hacia abajo.
        $manual = $this->aleatorio() < 0.06;
        $precio = $manual ? round($resultado->precio * $this->aleatorio(0.86, 0.95), 0) : round($resultado->precio, 2);

        return [
            'producto_id' => $producto->id,
            'tipo_proyecto_id' => $tipo?->id,
            'descripcion' => $this->descripcion($nombre, $ancho, $alto),
            'ancho' => $ancho,
            'alto' => $alto,
            'area_m2' => $area !== null ? round($area, 2) : null,
            'cantidad' => $cantidad,
            'costo_base' => round($costoUnitario * $cantidad, 2),
            'factor_complejidad' => round($resultado->factorComplejidad, 2),
            'margen_aplicado' => round($resultado->margen, 4),
            'costo_ajustado' => round($resultado->costoAjustado * $cantidad, 2),
            'precio_unitario' => $precio,
            'precio_manual' => $manual ? 'SI' : 'NO',
            'instalacion' => $instalacion,
            'subtotal' => round($precio * $cantidad, 2),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function insumosMateriales(Producto $producto, array $spec, ?float $ancho, ?float $alto, ?float $area, ?float $perimetro): array
    {
        if ($producto->productoMateriales->isNotEmpty() && ! isset($spec['receta'])) {
            try {
                $insumos = $this->precioSugerido->calcular($producto, $ancho, $alto, $spec['profundo'] ?? null)['insumos'];

                return array_map(fn ($i) => $this->item($i['material_id'], 'MATERIAL', $i['descripcion'], $i['unidad'], (float) $i['cantidad'], (float) $i['costo_unitario']), $insumos);
            } catch (Throwable) {
                // Receta incompleta para estas medidas: sigue con la receta
                // propia de abajo (si la hay).
            }
        }

        $items = [];
        foreach ($spec['receta'] ?? [] as $linea) {
            [$materialNombre, $a, $b] = array_pad($linea, 3, null);
            $material = $this->materiales[$materialNombre] ?? null;

            if ($material === null) {
                continue;
            }

            $cantidad = match ($a) {
                'area' => ($area ?? 1.0) * (float) $b,
                'perimetro' => ($perimetro ?? 4.0) * (float) $b,
                default => (float) $a,
            };

            $items[] = $this->item($material->id, 'MATERIAL', $material->nombre, $material->unidad_medida, round($cantidad, 4), (float) $material->precio_unitario);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(?int $materialId, string $tipo, string $descripcion, string $unidad, float $cantidad, float $costo): array
    {
        return [
            'material_id' => $materialId,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'unidad' => $unidad,
            'cantidad' => round($cantidad, 4),
            'costo_unitario' => round($costo, 4),
            'subtotal' => round($cantidad * $costo, 4),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function tipoDeLinea(array $spec, ?float $area): string
    {
        // Trabajos grandes con instalación en altura suben de nivel.
        if ($spec['tipo'] === 'Complejo' && $area !== null && $area > 2.2) {
            return 'Crítico';
        }

        return $spec['tipo'];
    }

    private function descripcion(string $producto, ?float $ancho, ?float $alto): string
    {
        return $ancho !== null && $alto !== null
            ? sprintf('%s %s × %s m', $producto, number_format($ancho, 2, ',', ''), number_format($alto, 2, ',', ''))
            : $producto;
    }

    /** Mano de obra: Bs 18/h, +5 % cada mayo (incremento salarial). */
    private function tarifaHora(Carbon $fecha): float
    {
        $mayos = collect(range($this->inicio->year, $fecha->year))
            ->filter(fn (int $anio) => Carbon::create($anio, 5, 1)->between($this->inicio, $fecha))
            ->count();

        return round(18 * 1.05 ** $mayos, 2);
    }

    /** Impresión por m²: acompaña a la tinta importada (~1 %/mes + salto del mes 14). */
    private function costoImpresion(Carbon $fecha): float
    {
        $mes = (int) $this->inicio->diffInMonths($fecha);

        return round(12 * 1.01 ** $mes * ($mes >= 14 ? 1.06 : 1.0), 2);
    }

    private function inflacionGeneral(Carbon $fecha): float
    {
        return 1.007 ** (int) $this->inicio->diffInMonths($fecha);
    }

    /**
     * Totales de cabecera: misma agregación que
     * CotizacionController::calcularMontos.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private function totalizar(array $lineas, float $descuento, bool $aplicaIva): array
    {
        $subtotal = round(array_sum(array_column($lineas, 'subtotal')), 2);
        $descuento = round(min($descuento, $subtotal), 2);
        $instalacion = round(array_sum(array_column($lineas, 'instalacion')), 2);
        $costoBase = round(array_sum(array_column($lineas, 'costo_base')), 2);
        $costoAjustado = round(array_sum(array_column($lineas, 'costo_ajustado')), 2);

        $resultado = $this->motor->evaluar(costoBase: $costoBase, costoAjustado: $costoAjustado, precio: $subtotal - $descuento, instalacion: $instalacion);
        $iva = $aplicaIva ? round($resultado->iva, 2) : 0.0;

        return [
            'costo_base' => $costoBase,
            'costo_ajustado' => $costoAjustado,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'it' => round($resultado->it, 2),
            'iue' => round($resultado->iue, 2),
            'utilidad_real' => round($resultado->utilidadReal, 2),
            'instalacion' => $instalacion,
            'estado_margen' => $resultado->estado,
            'total' => round(max($subtotal - $descuento + $iva + $instalacion, 0), 2),
        ];
    }

    /* =================================================================
     * Destino de la cotización: aprobación, pedido, producción, cobranza
     * ============================================================== */

    /**
     * @param  array<string, mixed>  $perfil
     * @param  array<string, mixed>  $forzar
     */
    private function resolverDestino(Cotizacion $cotizacion, string $segmento, array $perfil, array $forzar): void
    {
        $edad = (int) $cotizacion->fecha->diffInDays($this->hoy);

        if (! isset($forzar['estado'])) {
            // Las recientes todavía pueden estar esperando respuesta.
            if (($edad < 6 && $this->aleatorio() < 0.75) || ($edad < 15 && $this->aleatorio() < 0.35)) {
                return;
            }
        }

        // Un descuento visible ayuda a cerrar.
        $probabilidad = self::PROB_APROBACION[$segmento] * $perfil['aprob']
            + ((float) $cotizacion->descuento > 0.08 * (float) $cotizacion->subtotal ? 0.08 : 0.0);

        $aprobada = isset($forzar['estado']) ? $forzar['estado'] !== 'RECHAZADA' : $this->aleatorio() < $probabilidad;

        if (! $aprobada) {
            $vencible = $cotizacion->fecha_vencimiento->lessThan($this->hoy);
            $cotizacion->update(['estado' => $vencible && $this->aleatorio() < 0.35 ? 'VENCIDA' : 'RECHAZADA']);

            return;
        }

        $cotizacion->update(['estado' => 'APROBADA']);

        // El cliente tarda en decidir y mandar su orden de compra: el pedido
        // nace entre 3 y 12 días después de emitida la cotización.
        $diasHastaPedido = $this->entero(3, 12);
        $fechaPedido = $cotizacion->fecha->copy()->addDays($diasHastaPedido);

        if ($fechaPedido->greaterThan($this->hoy) || ($forzar['estado'] ?? null) === 'APROBADA' || $this->aleatorio() < 0.08) {
            return;
        }

        $this->crearPedido($cotizacion, $fechaPedido, $segmento, $forzar);
    }

    /**
     * @param  array<string, mixed>  $forzar
     */
    private function crearPedido(Cotizacion $cotizacion, Carbon $fechaPedido, string $segmento, array $forzar): void
    {
        $cotizacion->loadMissing('detalles.items', 'detalles.producto');

        $plazo = $cotizacion->detalles->map(function ($linea) {
            $spec = self::PRODUCTOS[$linea->producto?->nombre ?? ''] ?? ['plazo' => [5, 8]];

            return $this->entero(...$spec['plazo']);
        })->max();

        Carbon::setTestNow($fechaPedido->copy()->setTime(10, 0));
        $pedido = app(ConvertirCotizacionService::class)->convertir(
            $cotizacion,
            $fechaPedido->copy()->addDays($plazo)->toDateString(),
        );

        // Duración real: más lenta en temporada alta.
        $alta = in_array($fechaPedido->month, [11, 12], true);
        $factor = $alta ? $this->aleatorio(0.9, 1.4) : $this->aleatorio(0.75, 1.12);
        $duracion = max(1, (int) round($plazo * $factor));
        $entregaReal = $fechaPedido->copy()->addDays($duracion);

        $cancelado = ! isset($forzar['estado']) && $entregaReal->lessThan($this->hoy) && $this->aleatorio() < 0.025;

        if ($cancelado) {
            $pedido->update(['estado' => 'CANCELADO']);

            return;
        }

        $transcurrido = $fechaPedido->diffInDays($this->hoy) / max($duracion, 1);
        $etapaFinal = match (true) {
            $transcurrido >= 1 => 'ENTREGADO',
            $transcurrido >= 0.85 => 'CONTROL_CALIDAD',
            $transcurrido >= 0.6 => 'ACABADO',
            $transcurrido >= 0.2 => 'ELABORACION',
            default => 'DISENO',
        };

        $pedido->load('detalles');
        foreach ($pedido->detalles as $detalle) {
            $linea = $cotizacion->detalles->firstWhere('id', $detalle->cotizacion_detalle_id);
            $this->avanzarItem($detalle, $linea, $fechaPedido, $duracion, $etapaFinal, (float) ($forzar['desperdicio'] ?? 0));
        }

        $pedido->update([
            'estado' => $etapaFinal,
            'fecha_entrega_real' => $etapaFinal === 'ENTREGADO' ? $entregaReal->toDateString() : null,
        ]);

        $this->registrarPagos($pedido, $fechaPedido, $etapaFinal === 'ENTREGADO' ? $entregaReal : null, $segmento);
    }

    /**
     * Bitácora de etapas con fechas que suman la duración real, y consumo de
     * material cuando el ítem pasó por elaboración.
     */
    private function avanzarItem(PedidoDetalle $detalle, $linea, Carbon $inicio, int $duracion, string $etapaFinal, float $desperdicioForzado): void
    {
        $tramos = ['DISENO' => 0.15, 'ELABORACION' => 0.5, 'ACABADO' => 0.2, 'CONTROL_CALIDAD' => 0.05, 'ENTREGA' => 0.10];
        $orden = ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD', 'ENTREGADO'];
        $limite = array_search($etapaFinal, $orden, true);
        $areaElaboracion = self::PRODUCTOS[$linea?->producto?->nombre ?? '']['area_prod'] ?? 'Gigantografía';

        $areaPorEtapa = [
            'DISENO' => 'Diseño', 'ELABORACION' => $areaElaboracion, 'ACABADO' => 'Acabado',
            'CONTROL_CALIDAD' => 'Acabado', 'ENTREGA' => 'Acabado',
        ];

        $filas = [];
        $cursor = $inicio->copy()->setTime(9, 0);
        $finElaboracion = null;

        foreach (array_keys($tramos) as $i => $etapa) {
            if ($i > $limite) {
                break;
            }

            $horas = max(4, (int) round($duracion * 24 * $tramos[$etapa] * 0.45));
            $cerrada = $i < $limite || $etapaFinal === 'ENTREGADO';
            $fin = $cursor->copy()->addHours($horas);
            $areaId = $this->areas[$areaPorEtapa[$etapa]] ?? reset($this->areas);
            $personal = $this->personalPorArea[$areaId] ?? [Empleado::query()->value('id')];

            $filas[] = [
                'pedido_detalle_id' => $detalle->id,
                'area_id' => $areaId,
                'empleado_id' => $personal[array_rand($personal)],
                'etapa' => $etapa,
                'fecha_inicio' => $cursor->toDateTimeString(),
                'fecha_fin' => $cerrada ? $fin->toDateTimeString() : null,
                'observaciones' => null,
                'created_at' => $cursor->toDateTimeString(),
                'updated_at' => ($cerrada ? $fin : $cursor)->toDateTimeString(),
            ];

            if ($etapa === 'ELABORACION' && $cerrada) {
                $finElaboracion = $fin->copy();
            }

            $cursor = $fin->copy()->addHours((int) round($duracion * 24 * $tramos[$etapa] * 0.55));
        }

        PedidoSeguimiento::query()->insert($filas);
        $detalle->update(['estado_item' => $etapaFinal]);

        if ($finElaboracion !== null && $linea !== null) {
            $this->registrarConsumo($detalle, $linea, $finElaboracion, $desperdicioForzado);
        }
    }

    private function registrarConsumo(PedidoDetalle $detalle, $linea, Carbon $fecha, float $desperdicioForzado): void
    {
        $filas = [];

        foreach ($linea->items->whereNotNull('material_id') as $item) {
            // Desperdicio habitual 0-12 %; 5 % de los trabajos con retrabajo.
            $desperdicio = $desperdicioForzado > 0
                ? $desperdicioForzado
                : ($this->aleatorio() < 0.05 ? $this->aleatorio(1.2, 1.35) : $this->aleatorio(0.98, 1.12));

            $cantidad = round((float) $item->cantidad * (float) $linea->cantidad * $desperdicio, 2);
            $precio = $this->precioEn($item->material_id, $fecha->toDateString());

            $filas[] = [
                'pedido_detalle_id' => $detalle->id,
                'material_id' => $item->material_id,
                'cantidad_usada' => max($cantidad, 0.01),
                'costo_real' => round(max($cantidad, 0.01) * $precio, 2),
                'created_at' => $fecha->toDateTimeString(),
                'updated_at' => $fecha->toDateTimeString(),
            ];
        }

        if ($filas !== []) {
            PedidoDetalleMaterial::query()->insert($filas);
        }
    }

    private function registrarPagos(Pedido $pedido, Carbon $fechaPedido, ?Carbon $entrega, string $segmento): void
    {
        $total = (float) $pedido->total;
        $metodos = match ($segmento) {
            'corporativo' => ['TRANSFERENCIA' => 70, 'CHEQUE' => 30],
            'agencia' => ['TRANSFERENCIA' => 80, 'QR' => 20],
            'pyme' => ['QR' => 40, 'TRANSFERENCIA' => 30, 'EFECTIVO' => 30],
            default => ['EFECTIVO' => 55, 'QR' => 45],
        };

        // Corporativos con crédito: sin anticipo, pagan a 15-45 días.
        $conCredito = $segmento === 'corporativo' && $this->aleatorio() < 0.6;
        $filas = [];
        $pagado = 0.0;

        if (! $conCredito) {
            $anticipo = round($total * 0.5, 2);
            $fecha = $fechaPedido->copy()->addDays($this->entero(0, 2));
            if ($fecha->lessThanOrEqualTo($this->hoy)) {
                $filas[] = ['monto' => $anticipo, 'fecha' => $fecha];
                $pagado = $anticipo;
            }
        }

        if ($entrega !== null) {
            $dias = match ($segmento) {
                'corporativo' => $this->entero(15, 45),
                'agencia' => $this->entero(7, 30),
                'pyme' => $this->entero(0, 6),
                default => 0,
            };
            $fecha = $entrega->copy()->addDays($dias);

            // 2 % de morosos: el saldo nunca llega.
            if ($fecha->lessThanOrEqualTo($this->hoy) && $this->aleatorio() > 0.02) {
                $filas[] = ['monto' => round($total - $pagado, 2), 'fecha' => $fecha];
            }
        }

        $metodo = $this->elegirPonderado($metodos);

        Pago::query()->insert(array_map(fn ($p) => [
            'pedido_id' => $pedido->id,
            'monto' => $p['monto'],
            'fecha_pago' => $p['fecha']->toDateString(),
            'metodo_pago' => $metodo,
            'comprobante_url' => null,
            'created_at' => $p['fecha']->toDateTimeString(),
            'updated_at' => $p['fecha']->toDateTimeString(),
        ], array_filter($filas, fn ($p) => $p['monto'] > 0)));
    }

    /* =================================================================
     * Selección de cliente, vendedor y producto
     * ============================================================== */

    /**
     * @param  array<string, mixed>  $sucursal
     * @param  Collection<int, array<string, mixed>>  $clientes
     * @return array<string, mixed>|null
     */
    private function elegirCliente(Carbon $fecha, array $sucursal, Collection $clientes): ?array
    {
        // Escenario: el cliente grande de Santa Cruz deja de comprar ~4,5
        // meses antes de hoy (ni siquiera pide presupuestos).
        $corte = $this->hoy->copy()->subDays(138);

        $candidatos = $clientes->filter(function ($c) use ($fecha, $corte) {
            if (str_starts_with($c['modelo']->razon_social, 'Bebidas Tropicales') && $fecha->greaterThan($corte)) {
                return false;
            }

            return true;
        });

        // 85 % de las veces, un cliente de la ciudad de la sucursal.
        $locales = $candidatos->where('ciudad', $sucursal['ciudad']);
        $pool = ($locales->isNotEmpty() && $this->aleatorio() < 0.85) ? $locales : $candidatos;

        return $this->elegirDeColeccion($pool, 'peso');
    }

    /**
     * @param  array<string, mixed>  $sucursal
     * @param  Collection<int, array<string, mixed>>  $vendedores
     * @return array<string, mixed>|null
     */
    private function elegirVendedor(Carbon $fecha, array $sucursal, Collection $vendedores): ?array
    {
        $inactivoDesde = $this->hoy->copy()->subDays(36);

        $pool = $vendedores
            ->where('sucursal_id', $sucursal['modelo']->id)
            ->filter(fn ($v) => $v['modelo']->fecha_ingreso === null || $v['modelo']->fecha_ingreso->lessThanOrEqualTo($fecha))
            // El vendedor que se ausenta deja de cotizar; su compañera
            // absorbe la cartera (la sucursal no cae por eso).
            ->reject(fn ($v) => $v['perfil'] === 'inactivo_reciente' && $fecha->greaterThan($inactivoDesde))
            ->map(fn ($v) => [...$v, 'peso' => self::PERFIL[$v['perfil']]['peso']]);

        if ($pool->isEmpty()) {
            $pool = $vendedores->map(fn ($v) => [...$v, 'peso' => 1.0]);
        }

        return $this->elegirDeColeccion($pool, 'peso');
    }

    private function elegirProducto(string $segmento, Carbon $fecha): string
    {
        $mezcla = self::MEZCLA[$segmento];

        // Temporada: fin de año y carnaval empujan POP y gigantografías;
        // agosto (fiestas patrias), banners.
        foreach ($mezcla as $producto => $peso) {
            $mezcla[$producto] = $peso * match (true) {
                in_array($fecha->month, [11, 12], true) && in_array($producto, ['Habladores/Stoppers', 'Cenefa colgante', 'Gigantografía frontlight', 'Isla promocional'], true) => 1.6,
                $fecha->month === 2 && in_array($producto, ['Gigantografía frontlight', 'Banner lona frontlight', 'Toldo publicitario lona'], true) => 1.5,
                $fecha->month === 8 && in_array($producto, ['Banner lona frontlight', 'Banner roll-up 85x200cm'], true) => 1.5,
                default => 1.0,
            };
        }

        return $this->elegirPonderado($mezcla);
    }

    /* =================================================================
     * Escenarios plantados (recientes)
     * ============================================================== */

    /**
     * @param  Collection<int, array<string, mixed>>  $clientes
     * @param  Collection<int, array<string, mixed>>  $vendedores
     */
    private function escenariosPlantados(Collection $clientes, Collection $vendedores): void
    {
        $sucursales = $this->sucursales()->keyBy(fn ($s) => $s['modelo']->nombre);
        $laPaz = $sucursales->first(fn ($s, $nombre) => str_starts_with($nombre, 'La Paz'));
        $elAlto = $sucursales->first(fn ($s, $nombre) => str_starts_with($nombre, 'El Alto'));
        $descuentera = $vendedores->firstWhere('perfil', 'descuentero');
        $estrella = $vendedores->first(fn ($v) => $v['perfil'] === 'estrella' && $v['sucursal_id'] === $elAlto['modelo']->id);
        $corporativo = $clientes->first(fn ($c) => $c['segmento'] === 'corporativo' && $c['ciudad'] === 'La Paz');

        DB::transaction(function () use ($laPaz, $elAlto, $descuentera, $estrella, $corporativo, $clientes): void {
            // 1. Venta reciente con descuento excesivo: margen en rojo.
            if ($laPaz && $descuentera && $corporativo) {
                $this->crearCotizacion($this->hoy->copy()->subDays(9), $laPaz, $clientes, collect(), [
                    'cliente' => $corporativo,
                    'vendedor' => $descuentera,
                    'productos' => ['Gigantografía frontlight', 'Exhibidor de piso MDF a medida'],
                    'lineas' => 2,
                    'descuento' => 0.27,
                    'estado' => 'APROBADA',
                ]);
            }

            // 2. Pedido reciente con reimpresión: sobrecosto de material.
            if ($elAlto && $estrella) {
                $cliente = $clientes->first(fn ($c) => $c['segmento'] === 'corporativo' && $c['ciudad'] === 'El Alto');
                $this->crearCotizacion($this->hoy->copy()->subDays(34), $elAlto, $clientes, collect(), [
                    'cliente' => $cliente,
                    'vendedor' => $estrella,
                    'productos' => ['Gigantografía frontlight'],
                    'lineas' => 1,
                    'estado' => 'CONVERTIDA',
                    'desperdicio' => 3.3,
                ]);
            }
        });
    }

    /* =================================================================
     * Aleatoriedad determinista
     * ============================================================== */

    private function aleatorio(float $min = 0.0, float $max = 1.0): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private function entero(int|float $min, int|float $max): int
    {
        return mt_rand((int) round($min), (int) round($max));
    }

    /**
     * Conteo de eventos con media `$lambda` (algoritmo de Knuth).
     */
    private function poisson(float $lambda): int
    {
        $limite = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= $this->aleatorio();
        } while ($p > $limite);

        return $k - 1;
    }

    /**
     * @param  array<string|int, float|int>  $pesos
     */
    private function elegirPonderado(array $pesos): string|int
    {
        $total = array_sum($pesos);
        $tiro = $this->aleatorio(0, $total);

        foreach ($pesos as $opcion => $peso) {
            $tiro -= $peso;
            if ($tiro <= 0) {
                return $opcion;
            }
        }

        return array_key_last($pesos);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $coleccion
     * @return array<string, mixed>|null
     */
    private function elegirDeColeccion(Collection $coleccion, string $clavePeso): ?array
    {
        if ($coleccion->isEmpty()) {
            return null;
        }

        $indice = $this->elegirPonderado($coleccion->values()->mapWithKeys(fn ($c, $i) => [$i => $c[$clavePeso]])->all());

        return $coleccion->values()->get($indice);
    }
}
