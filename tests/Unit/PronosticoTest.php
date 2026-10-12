<?php

use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\Pronostico;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La matemática de los pronósticos del BI. Funciones puras: se prueban con
 * series a mano cuyo resultado se puede verificar en una calculadora.
 *
 * Usa TestCase solo por PeriodoReporte (`translatedFormat` necesita la app
 * levantada para el locale); no toca la base de datos.
 */
uses(TestCase::class);

test('una serie perfectamente lineal da su pendiente exacta y error cero', function () {
    $recta = Pronostico::regresion([10, 12, 14, 16]);

    expect($recta['pendiente'])->toEqualWithDelta(2.0, 1e-9)
        ->and($recta['intercepto'])->toEqualWithDelta(10.0, 1e-9)
        ->and($recta['error'])->toEqualWithDelta(0.0, 1e-9);
});

test('la proyeccion prolonga la recta y su rango se abre con el error tipico', function () {
    // y = 1, 3, 2, 4 → pendiente 0,8 · intercepto 1,3 · SSE = 1,8 · error = √(1,8/2)
    $proyeccion = Pronostico::proyectar([1, 3, 2, 4], 2);
    $error = sqrt(0.9);

    expect($proyeccion)->toHaveCount(2)
        ->and($proyeccion[0]['valor'])->toEqualWithDelta(1.3 + 0.8 * 4, 1e-9)
        ->and($proyeccion[0]['inferior'])->toEqualWithDelta(1.3 + 0.8 * 4 - $error, 1e-9)
        ->and($proyeccion[0]['superior'])->toEqualWithDelta(1.3 + 0.8 * 4 + $error, 1e-9)
        ->and($proyeccion[1]['valor'])->toEqualWithDelta(1.3 + 0.8 * 5, 1e-9);
});

test('sin tres puntos no hay proyeccion', function () {
    expect(Pronostico::proyectar([5, 8]))->toBe([])
        ->and(Pronostico::variacionMensual([5, 8]))->toBeNull();
});

test('el piso corta en cero salvo que se pida lo contrario', function () {
    $cayendo = [30, 20, 10];

    expect(collect(Pronostico::proyectar($cayendo, 3))->every(fn ($p) => $p['valor'] >= 0 && $p['inferior'] >= 0))->toBeTrue()
        ->and(Pronostico::proyectar($cayendo, 1, piso: false)[0]['valor'])->toEqualWithDelta(0.0, 1e-9)
        ->and(Pronostico::proyectar($cayendo, 2, piso: false)[1]['valor'])->toEqualWithDelta(-10.0, 1e-9);
});

test('la media movil promedia lo que hay al principio y salta los huecos', function () {
    expect(Pronostico::mediaMovil([3, 6, 9, 12]))->toBe([3.0, 4.5, 6.0, 9.0])
        ->and(Pronostico::mediaMovil([null, 4, null]))->toBe([null, 4.0, 4.0]);
});

test('la variacion mensual es la pendiente como porcentaje del promedio', function () {
    // pendiente 2 sobre promedio 13 → 15,38 % por mes
    expect(Pronostico::variacionMensual([10, 12, 14, 16]))->toEqualWithDelta(2 / 13 * 100, 1e-9)
        ->and(Pronostico::variacionMensual([0, 0, 0]))->toBeNull();
});

test('el periodo por defecto son los ultimos meses calendario hasta hoy', function () {
    Carbon::setTestNow('2026-10-12 10:00:00');

    $periodo = PeriodoReporte::desdeFiltros(null, null, 12);

    expect($periodo->desde->toDateString())->toBe('2025-11-01')
        ->and($periodo->hasta->toDateString())->toBe('2026-10-12')
        ->and($periodo->meses())->toHaveCount(12)
        ->and($periodo->meses()->keys()->first())->toBe('2025-11')
        ->and($periodo->mesesSiguientes(3))->toHaveCount(3);

    Carbon::setTestNow();
});

test('el periodo ordena fechas invertidas e ignora las que no se pueden leer', function () {
    $invertido = PeriodoReporte::desdeFiltros('2026-06-30', '2026-01-01');

    expect($invertido->desde->toDateString())->toBe('2026-01-01')
        ->and($invertido->hasta->toDateString())->toBe('2026-06-30')
        ->and($invertido->meses()->keys()->all())->toBe(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06']);

    $basura = PeriodoReporte::desdeFiltros('no-es-fecha', '2026-03-15', 3);

    expect($basura->desde->toDateString())->toBe('2026-01-01')
        ->and($basura->hasta->toDateString())->toBe('2026-03-15');
});
