<?php

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\HistorialPrecioMaterial;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CategoriaMaterialSeeder;
use Database\Seeders\CategoriaProductoSeeder;
use Database\Seeders\ClienteSeeder;
use Database\Seeders\EmpleadoSeeder;
use Database\Seeders\FamiliaProductoSeeder;
use Database\Seeders\FormulaSeeder;
use Database\Seeders\HistorialComercialSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ProductoMaterialSeeder;
use Database\Seeders\ProductoSeeder;
use Database\Seeders\ProveedorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Database\Seeders\TipoProyectoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Coherencia de los datos de prueba: lo que un analista vería como dato
 * imposible no puede salir del seeder. Se simulan 2 meses (los 24 tardan
 * ~30 s) con las mismas reglas.
 */
beforeEach(function () {
    HistorialComercialSeeder::$mesesParaPruebas = 2;

    $this->seed([
        RolesAndPermissionsSeeder::class, SucursalSeeder::class, AreaSeeder::class,
        CategoriaMaterialSeeder::class, CategoriaProductoSeeder::class, FamiliaProductoSeeder::class,
        ProveedorSeeder::class, ClienteSeeder::class, EmpleadoSeeder::class, MaterialSeeder::class,
        ProductoSeeder::class, FormulaSeeder::class, ProductoMaterialSeeder::class, TipoProyectoSeeder::class,
        HistorialComercialSeeder::class,
    ]);
});

afterEach(fn () => HistorialComercialSeeder::$mesesParaPruebas = null);

test('genera presupuestos con hoja de costos y precios del motor', function () {
    expect(Cotizacion::query()->count())->toBeGreaterThan(50)
        // Toda línea tiene hoja de costos: la rentabilidad del BI no queda
        // contaminada por costos en cero.
        ->and(CotizacionDetalle::query()->where('costo_ajustado', '<=', 0)->count())->toBe(0)
        ->and(CotizacionDetalle::query()->whereColumn('subtotal', '<=', 'costo_base')->count())->toBe(0)
        ->and(HistorialPrecioMaterial::query()->count())->toBeGreaterThan(10);
});

test('los pedidos son coherentes con su cotización, su producción y su cobranza', function () {
    $pedidos = Pedido::query()->with('cotizacion', 'pagos', 'detalles')->get();

    expect($pedidos)->not->toBeEmpty();

    foreach ($pedidos as $pedido) {
        expect($pedido->cotizacion->estado)->toBe('CONVERTIDA')
            ->and((float) $pedido->total)->toBe((float) $pedido->cotizacion->total)
            ->and($pedido->fecha_pedido->greaterThanOrEqualTo($pedido->cotizacion->fecha))->toBeTrue()
            ->and((float) $pedido->pagos->sum('monto'))->toBeLessThanOrEqual((float) $pedido->total + 0.01);

        if ($pedido->estado === 'ENTREGADO') {
            expect($pedido->fecha_entrega_real)->not->toBeNull()
                ->and($pedido->fecha_entrega_real->greaterThanOrEqualTo($pedido->fecha_pedido))->toBeTrue();
        }
    }

    // Solo se consume material en ítems que pasaron por elaboración.
    $sinElaborar = PedidoDetalle::query()
        ->whereIn('estado_item', ['DISENO', 'ELABORACION'])
        ->whereHas('materialesUsados')
        ->count();

    expect($sinElaborar)->toBe(0);
});

test('no quedan presupuestos pendientes viejos ni fechas futuras', function () {
    // whereDate: en sqlite las fechas se guardan con hora y "hoy 00:00:00"
    // compara como mayor que "hoy" en texto.
    expect(Cotizacion::query()->where('estado', 'PENDIENTE')->whereDate('fecha', '<', now()->subDays(16)->toDateString())->count())->toBe(0)
        ->and(Cotizacion::query()->whereDate('fecha', '>', now()->toDateString())->count())->toBe(0)
        ->and(DB::table('pago')->whereDate('fecha_pago', '>', now()->toDateString())->count())->toBe(0);
});

test('no vuelve a sembrar si ya hay cotizaciones', function () {
    $antes = Cotizacion::query()->count();

    $this->seed(HistorialComercialSeeder::class);

    expect(Cotizacion::query()->count())->toBe($antes);
});
