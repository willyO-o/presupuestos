<?php

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\HistorialPrecioMaterial;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\Reporte\InteligenciaNegociosService;

beforeEach(function () {
    $this->service = app(InteligenciaNegociosService::class);
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

    $datos = $this->service->datos();

    expect($datos['productos_mas_vendidos'])->toHaveCount(1)
        ->and($datos['productos_mas_vendidos'][0]['nombre'])->toBe('Bastidor lona')
        ->and($datos['productos_mas_vendidos'][0]['monto'])->toBe(900.0)
        ->and($datos['productos_mas_vendidos'][0]['cantidad'])->toBe(3.0);
});

test('evolucion de costos only includes materials with at least two price points', function () {
    $conHistorial = Material::factory()->create(['nombre' => 'Lona FrontLight']);
    $sinHistorial = Material::factory()->create(['nombre' => 'Vinil']);

    HistorialPrecioMaterial::factory()->count(3)->create(['material_id' => $conHistorial->id]);
    HistorialPrecioMaterial::factory()->create(['material_id' => $sinHistorial->id]);

    $evolucion = $this->service->datos()['evolucion_costos'];

    expect($evolucion)->toHaveCount(1)
        ->and($evolucion[0]['material'])->toBe('Lona FrontLight')
        ->and($evolucion[0]['puntos'])->toHaveCount(3);
});

test('the demand projection returns three non-negative months', function () {
    Pedido::factory()->count(6)->create([
        'fecha_pedido' => now()->subMonths(2)->toDateString(),
    ]);

    $demanda = $this->service->datos()['demanda'];

    expect($demanda['serie'])->toHaveCount(12)
        ->and($demanda['proyeccion'])->toHaveCount(3)
        ->and(collect($demanda['proyeccion'])->every(fn ($p) => $p['pedidos_estimados'] >= 0))->toBeTrue()
        ->and($demanda['estacionalidad'])->toHaveCount(12);
});
