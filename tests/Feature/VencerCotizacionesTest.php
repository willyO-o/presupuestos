<?php

use App\Models\Cotizacion;

test('the command only expires pending cotizaciones past their due date', function () {
    $vencida = Cotizacion::factory()->create([
        'estado' => 'PENDIENTE',
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);
    $vigente = Cotizacion::factory()->create([
        'estado' => 'PENDIENTE',
        'fecha_vencimiento' => now()->addDays(5)->toDateString(),
    ]);
    $aprobada = Cotizacion::factory()->aprobada()->create([
        'fecha_vencimiento' => now()->subDays(10)->toDateString(),
    ]);

    $this->artisan('cotizaciones:vencer')->assertSuccessful();

    expect($vencida->fresh()->estado)->toBe('VENCIDA')
        ->and($vigente->fresh()->estado)->toBe('PENDIENTE')
        ->and($aprobada->fresh()->estado)->toBe('APROBADA');
});
