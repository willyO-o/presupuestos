<?php

use App\Ai\Agents\RedactorAnomalias;
use App\Models\Area;
use App\Models\BiAnomalia;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\AnomaliaDetectada;
use App\Services\Reporte\DeteccionAnomaliasService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Detección estadística de anomalías: cada regla con un escenario armado a
 * mano, deduplicación por huella, redacción por IA verificada y avisos.
 *
 * Todo cuelga de UNA sucursal, UN vendedor y UN cliente (recycle a mano):
 * las factories crean áreas con nombres únicos de una lista de siete y
 * revientan si se dejan sueltas (ver AlcanceReportesTest).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-12 10:00:00'); // lunes
    config(['ai.providers.gemini.key' => null, 'ai.providers.anthropic.key' => null]);

    $this->sucursal = Sucursal::factory()->create(['nombre' => 'La Paz · Sopocachi']);
    $this->area = Area::factory()->create();
    $this->vendedor = Empleado::factory()->create(['sucursal_id' => $this->sucursal->id, 'area_id' => $this->area->id, 'cargo' => 'Vendedor']);
    $this->cliente = Cliente::factory()->create(['razon_social' => 'Bebidas Tropicales del Oriente S.A.']);
    $this->servicio = app(DeteccionAnomaliasService::class);
});

afterEach(fn () => Carbon::setTestNow());

function cotizarParaAnomalia(array $datos): Cotizacion
{
    $test = test();

    return Cotizacion::factory()->create([
        'sucursal_id' => $test->sucursal->id,
        'empleado_id' => $test->vendedor->id,
        'cliente_id' => $test->cliente->id,
        ...$datos,
    ]);
}

test('detecta la caída de cotizaciones de una sucursal con la cola de Poisson', function () {
    // 8 semanas con 10 cotizaciones y la última semana completa con 1.
    foreach (range(1, 8) as $semanasAtras) {
        $lunes = Carbon::parse('2026-10-05')->subWeeks($semanasAtras);
        foreach (range(0, 9) as $i) {
            cotizarParaAnomalia(['fecha' => $lunes->copy()->addDays($i % 6)->toDateString()]);
        }
    }
    cotizarParaAnomalia(['fecha' => '2026-10-07']);

    $caidas = collect($this->servicio->detectar(Carbon::parse('2026-10-12')))->where('tipo', 'caida_cotizaciones');

    // Una de la sucursal y otra de toda la empresa (son los mismos datos).
    expect($caidas)->toHaveCount(2);

    $deLaSucursal = $caidas->firstWhere('sucursal_id', $this->sucursal->id);
    expect($deLaSucursal['severidad'])->toBe('ALTA') // P(X ≤ 1 | λ = 10) ≈ 0,05 %
        ->and($deLaSucursal['hechos']['cotizaciones'])->toBe(1)
        ->and($deLaSucursal['hechos']['mediana_semanal'])->toBe(10.0)
        ->and($deLaSucursal['huella'])->toBe("caida_cotizaciones:{$this->sucursal->id}:2026-10-05");
});

test('una semana normal no es anomalía', function () {
    foreach (range(0, 8) as $semanasAtras) {
        $lunes = Carbon::parse('2026-10-05')->subWeeks($semanasAtras);
        foreach (range(0, 9) as $i) {
            cotizarParaAnomalia(['fecha' => $lunes->copy()->addDays($i % 6)->toDateString()]);
        }
    }

    expect(collect($this->servicio->detectar(Carbon::parse('2026-10-12')))->whereIn('tipo', ['caida_cotizaciones', 'alza_cotizaciones']))->toBeEmpty();
});

test('detecta al vendedor que lleva más del doble de su ritmo sin cotizar', function () {
    // Una cotización cada 3 días hasta el 1 de septiembre.
    for ($dia = Carbon::parse('2026-06-01'); $dia->lessThanOrEqualTo(Carbon::parse('2026-09-01')); $dia->addDays(3)) {
        cotizarParaAnomalia(['fecha' => $dia->toDateString()]);
    }

    $anomalia = collect($this->servicio->detectar(Carbon::parse('2026-10-12')))->firstWhere('tipo', 'vendedor_inactivo');

    expect($anomalia)->not->toBeNull()
        ->and($anomalia['severidad'])->toBe('ALTA')
        ->and($anomalia['hechos']['ritmo_dias'])->toBe(3.0)
        ->and($anomalia['hechos']['dias_sin_cotizar'])->toBe(43) // última: 30/08
        ->and($anomalia['sucursal_id'])->toBe($this->sucursal->id);
});

test('detecta al cliente grande que dejó de comprar', function () {
    foreach (['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15', '2026-05-15'] as $fecha) {
        cotizarParaAnomalia(['fecha' => $fecha, 'estado' => 'CONVERTIDA', 'total' => 20000]);
    }

    $anomalia = collect($this->servicio->detectar(Carbon::parse('2026-10-12')))->firstWhere('tipo', 'cliente_en_riesgo');

    expect($anomalia)->not->toBeNull()
        ->and($anomalia['titulo'])->toContain('Bebidas Tropicales')
        ->and($anomalia['severidad'])->toBe('ALTA') // 100 % de las ventas del año
        ->and($anomalia['hechos']['compras'])->toBe(5);
});

test('detecta una venta reciente con margen muy por debajo de lo habitual', function () {
    foreach (range(1, 16) as $i) {
        cotizarParaAnomalia([
            'fecha' => Carbon::parse('2026-10-12')->subDays(20 + $i * 9)->toDateString(),
            'estado' => 'CONVERTIDA', 'costo_ajustado' => 1000, 'utilidad_real' => 400, 'estado_margen' => 'VERDE',
        ]);
    }
    $roja = cotizarParaAnomalia([
        'fecha' => '2026-10-07', 'estado' => 'APROBADA',
        'costo_ajustado' => 1000, 'utilidad_real' => 20, 'estado_margen' => 'ROJO', 'descuento' => 900,
    ]);

    $anomalia = collect($this->servicio->detectar(Carbon::parse('2026-10-12')))->firstWhere('tipo', 'margen_bajo');

    expect($anomalia)->not->toBeNull()
        ->and($anomalia['huella'])->toBe("margen_bajo:{$roja->id}")
        ->and($anomalia['severidad'])->toBe('ALTA')
        ->and($anomalia['hechos']['rentabilidad_pct'])->toBe(2.0);
});

test('la misma anomalía no se vuelve a crear al día siguiente', function () {
    for ($dia = Carbon::parse('2026-06-01'); $dia->lessThanOrEqualTo(Carbon::parse('2026-09-01')); $dia->addDays(3)) {
        cotizarParaAnomalia(['fecha' => $dia->toDateString()]);
    }

    expect($this->servicio->ejecutar(Carbon::parse('2026-10-12')))->toHaveCount(1)
        ->and($this->servicio->ejecutar(Carbon::parse('2026-10-13')))->toHaveCount(0)
        ->and(BiAnomalia::query()->count())->toBe(1)
        ->and(BiAnomalia::query()->first()->redaccion)->toBe('PLANTILLA');
});

test('la redacción de la IA se acepta solo si no inventa cifras', function (string $detalle, string $redaccion) {
    config(['ai.providers.gemini.key' => 'clave']);
    for ($dia = Carbon::parse('2026-06-01'); $dia->lessThanOrEqualTo(Carbon::parse('2026-09-01')); $dia->addDays(3)) {
        cotizarParaAnomalia(['fecha' => $dia->toDateString()]);
    }

    RedactorAnomalias::fake(function (string $prompt) use ($detalle) {
        preg_match_all('/"huella":"([^"]+)"/', $prompt, $huellas);

        return ['anomalias' => array_map(fn ($h) => [
            'huella' => $h,
            'detalle' => $detalle,
            'que_revisar' => 'Confirmar si está de vacaciones.',
        ], $huellas[1])];
    });

    $this->servicio->ejecutar(Carbon::parse('2026-10-12'));

    $anomalia = BiAnomalia::query()->sole();
    expect($anomalia->redaccion)->toBe($redaccion);
})->with([
    'sin cifras nuevas' => ['El vendedor dejó de cotizar hace 43 días, muy por encima de su ritmo de 3 días.', 'IA'],
    'cifra inventada' => ['El vendedor perdió Bs 98.765 en ventas.', 'PLANTILLA'],
]);

test('las anomalías altas avisan a quien tiene el asistente y ve la sucursal', function () {
    Notification::fake();
    Permission::findOrCreate('reportes.asistente', 'web');

    $gerencia = User::factory()->todasLasSucursales()->create();
    $gerencia->givePermissionTo('reportes.asistente');
    $sinPermiso = User::factory()->todasLasSucursales()->create();

    for ($dia = Carbon::parse('2026-06-01'); $dia->lessThanOrEqualTo(Carbon::parse('2026-09-01')); $dia->addDays(3)) {
        cotizarParaAnomalia(['fecha' => $dia->toDateString()]);
    }

    $this->servicio->ejecutar(Carbon::parse('2026-10-12'));

    Notification::assertSentTo($gerencia, AnomaliaDetectada::class);
    Notification::assertNotSentTo($sinPermiso, AnomaliaDetectada::class);
});
