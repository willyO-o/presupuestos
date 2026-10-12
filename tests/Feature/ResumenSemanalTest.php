<?php

use App\Ai\Agents\RedactorResumenSemanal;
use App\Models\Area;
use App\Models\BiResumen;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\ResumenSemanalDisponible;
use App\Services\Reporte\ResumenSemanalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Resumen semanal: hechos calculados en PHP (semana, promedio de 4
 * semanas, semana anterior), plantilla de respaldo, redacción de la IA
 * aceptada solo si sus cifras salen de los hechos, alcance y avisos.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-12 10:00:00'); // lunes: la semana a resumir es la del 5 al 11
    config(['ai.providers.gemini.key' => null, 'ai.providers.anthropic.key' => null]);

    $this->sucursal = Sucursal::factory()->create(['nombre' => 'Cochabamba · Centro']);
    $area = Area::factory()->create();
    $this->vendedor = Empleado::factory()->create(['sucursal_id' => $this->sucursal->id, 'area_id' => $area->id]);
    $this->cliente = Cliente::factory()->create();
    $this->servicio = app(ResumenSemanalService::class);
    $this->semana = $this->servicio->ultimaSemanaCompleta();

    // Semana a resumir: 3 presupuestos de Bs 1.000. Cada una de las 4
    // anteriores: 1 presupuesto de Bs 1.000.
    foreach (['2026-10-06', '2026-10-07', '2026-10-09'] as $fecha) {
        presupuestoDeSemana($fecha);
    }
    foreach (['2026-09-29', '2026-09-22', '2026-09-15', '2026-09-08'] as $fecha) {
        presupuestoDeSemana($fecha);
    }
});

afterEach(fn () => Carbon::setTestNow());

function presupuestoDeSemana(string $fecha): Cotizacion
{
    $test = test();

    return Cotizacion::factory()->create([
        'sucursal_id' => $test->sucursal->id,
        'empleado_id' => $test->vendedor->id,
        'cliente_id' => $test->cliente->id,
        'fecha' => $fecha,
        'total' => 1000,
    ]);
}

function kpiDelResumen(array $hechos, string $clave): array
{
    return collect($hechos['kpis'])->firstWhere('clave', $clave);
}

test('la semana a resumir es la última semana completa', function () {
    expect($this->semana->toDateString())->toBe('2026-10-05');
});

test('los hechos comparan contra el promedio de 4 semanas y contra la semana anterior', function () {
    $hechos = $this->servicio->hechos($this->semana, null);
    $emitidos = kpiDelResumen($hechos, 'presupuestos_emitidos');

    expect($emitidos['valor'])->toBe(3)
        ->and($emitidos['promedio_4_semanas'])->toBe(1.0)
        ->and($emitidos['var_promedio_pct'])->toBe(200.0)
        ->and($emitidos['semana_anterior'])->toBe(1)
        ->and(kpiDelResumen($hechos, 'monto_emitido')['valor'])->toBe(3000.0)
        // El sistema no tenía datos hace un año: no se compara.
        ->and($hechos['hay_anio_anterior'])->toBeFalse()
        ->and($emitidos['anio_anterior'])->toBeNull()
        ->and($hechos['semana']['etiqueta'])->toContain('Semana 41');
});

test('sin IA el resumen se publica igual con la plantilla', function () {
    $resumen = $this->servicio->generar($this->semana, null);

    expect($resumen->origen)->toBe('PLANTILLA')
        ->and($resumen->contenido['titular'])->not->toBeEmpty()
        ->and($resumen->contenido['destacados'])->not->toBeEmpty()
        ->and($resumen->contenido['recomendaciones'])->not->toBeEmpty()
        ->and($resumen->sucursal_id)->toBeNull();
});

test('el texto de la IA se publica solo si cada cifra sale de los hechos', function (string $titular, string $origen) {
    config(['ai.providers.gemini.key' => 'clave']);

    RedactorResumenSemanal::fake([[
        'titular' => $titular,
        'destacados' => [['texto' => 'El monto presupuestado llegó a Bs 3.000,00.', 'kpis' => ['k2', 'k99']]],
        'riesgos' => [],
        'recomendaciones' => [['accion' => 'Dar seguimiento a los presupuestos pendientes.', 'motivo' => 'Ninguno se cerró todavía.']],
    ]]);

    $resumen = $this->servicio->generar($this->semana, null);

    expect($resumen->origen)->toBe($origen);

    if ($origen === 'IA') {
        // Los ids de indicadores que no existen se descartan.
        expect($resumen->contenido['destacados'][0]['kpis'])->toBe(['k2'])
            ->and($resumen->modelo)->toBe('gemini-3.8-flash');
    }
})->with([
    'cifras de los hechos' => ['Se emitieron 3 presupuestos, 200 % sobre el promedio de las semanas anteriores.', 'IA'],
    'cifra inventada' => ['Se vendieron Bs 87.500 en la semana.', 'PLANTILLA'],
]);

test('una sucursal sin movimiento no recibe resumen', function () {
    $vacia = Sucursal::factory()->create();

    expect($this->servicio->generar($this->semana, $vacia))->toBeNull()
        ->and($this->servicio->generar($this->semana, $this->sucursal))->not->toBeNull();
});

test('regenerar la semana actualiza el resumen en vez de duplicarlo', function () {
    $this->servicio->generar($this->semana, null);
    $this->servicio->generar($this->semana, null);

    expect(BiResumen::query()->whereNull('sucursal_id')->count())->toBe(1);
});

test('cada usuario ve los resúmenes de su alcance y recibe el aviso que le toca', function () {
    Notification::fake();
    Permission::findOrCreate('reportes.bi', 'web');

    $gerencia = User::factory()->todasLasSucursales()->create();
    $gerencia->givePermissionTo('reportes.bi');

    $local = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $local->givePermissionTo('reportes.bi');
    Empleado::factory()->create(['user_id' => $local->id, 'sucursal_id' => $this->sucursal->id, 'area_id' => Area::query()->value('id')]);
    $local = $local->fresh();

    $this->servicio->generarSemana($this->semana);

    expect(BiResumen::query()->visibleResumenPara($gerencia)->count())->toBe(2)
        ->and(BiResumen::query()->visibleResumenPara($local)->pluck('sucursal_id')->all())->toBe([$this->sucursal->id]);

    // Gerencia: solo el consolidado (no uno por sucursal). Local: el suyo.
    Notification::assertSentToTimes($gerencia, ResumenSemanalDisponible::class, 1);
    Notification::assertSentToTimes($local, ResumenSemanalDisponible::class, 1);
});
