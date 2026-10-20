<?php

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\HistorialPrecioMaterial;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;

beforeEach(function () {
    $this->service = app(InteligenciaNegociosService::class);
    // Alcance TODAS: estas pruebas comprueban la MATEMATICA del reporte, no el
    // acotado por sucursal (eso vive en AlcanceReportesTest).
    $this->usuario = User::factory()->todasLasSucursales()->create();
});

test('productos mas vendidos only counts approved/converted cotizaciones', function () {
    $producto = Producto::factory()->create(['nombre' => 'Bastidor lona']);

    $aprobada = Cotizacion::factory()->aprobada()->create();
    CotizacionDetalle::factory()->for($aprobada, 'cotizacion')->create([
        'producto_id' => $producto->id, 'cantidad' => 3, 'subtotal' => 900,
    ]);

    $pendiente = Cotizacion::factory()->create(); // PENDIENTE — no debe contar
    CotizacionDetalle::factory()->for($pendiente, 'cotizacion')->create([
        'producto_id' => $producto->id, 'cantidad' => 10, 'subtotal' => 9999,
    ]);

    $datos = $this->service->datos($this->usuario);

    expect($datos['productos_mas_vendidos'])->toHaveCount(1)
        ->and($datos['productos_mas_vendidos'][0]['nombre'])->toBe('Bastidor lona')
        ->and($datos['productos_mas_vendidos'][0]['monto'])->toBe(900.0)
        ->and($datos['productos_mas_vendidos'][0]['cantidad'])->toBe(3.0);
});

test('evolucion de costos only includes materials with at least two price points', function () {
    $conHistorial = Material::factory()->create(['nombre' => 'Lona FrontLight']);
    $sinHistorial = Material::factory()->create(['nombre' => 'Vinil']);

    // Fechas dentro del periodo por defecto (últimos 12 meses): la factory
    // reparte hasta un año atrás y alguna podría caer fuera del filtro.
    foreach ([3, 2, 1] as $mesesAtras) {
        HistorialPrecioMaterial::factory()->create([
            'material_id' => $conHistorial->id,
            'vigente_desde' => now()->subMonths($mesesAtras)->toDateString(),
        ]);
    }
    HistorialPrecioMaterial::factory()->create([
        'material_id' => $sinHistorial->id,
        'vigente_desde' => now()->subMonth()->toDateString(),
    ]);

    $evolucion = $this->service->datos($this->usuario)['evolucion_costos'];

    expect($evolucion)->toHaveCount(1)
        ->and($evolucion[0]['material'])->toBe('Lona FrontLight')
        ->and($evolucion[0]['puntos'])->toHaveCount(3);
});

test('the demand projection returns three non-negative months', function () {
    Pedido::factory()->count(6)->create([
        'fecha_pedido' => now()->subMonths(2)->toDateString(),
    ]);

    $demanda = $this->service->datos($this->usuario)['demanda'];

    expect($demanda['serie'])->toHaveCount(12)
        ->and($demanda['proyeccion'])->toHaveCount(3)
        ->and(collect($demanda['proyeccion'])->every(fn ($p) => $p['pedidos_estimados'] >= 0))->toBeTrue();
});

test('el filtro de fechas acota los productos vendidos y la demanda', function () {
    $producto = Producto::factory()->create(['nombre' => 'Roll up']);

    foreach (['2026-02-10' => 500, '2025-06-10' => 9000] as $fecha => $monto) {
        $cotizacion = Cotizacion::factory()->aprobada()->create(['fecha' => $fecha]);
        CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create([
            'producto_id' => $producto->id, 'cantidad' => 1, 'subtotal' => $monto,
        ]);
    }
    Pedido::factory()->create(['fecha_pedido' => '2026-03-05']);
    Pedido::factory()->create(['fecha_pedido' => '2025-06-05']);

    $periodo = PeriodoReporte::desdeFiltros('2026-01-01', '2026-04-30');
    $datos = $this->service->datos($this->usuario, $periodo);

    expect(collect($datos['productos_mas_vendidos'])->firstWhere('nombre', 'Roll up')['monto'])->toBe(500.0)
        ->and($datos['demanda']['serie'])->toHaveCount(4)
        ->and(collect($datos['demanda']['serie'])->sum('pedidos'))->toBe(1)
        ->and($datos['periodo']['meses'])->toBe(4)
        ->and($datos['demanda']['proyeccion'][0])->toHaveKeys(['mes', 'pedidos_estimados', 'inferior', 'superior']);
});
