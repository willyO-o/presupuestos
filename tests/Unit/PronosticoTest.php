<?php

use App\Services\Reporte\Estadistica;
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

test('la proyeccion prolonga la recta con su intervalo de prediccion', function () {
    // y = 1, 3, 2, 4 → pendiente 0,8 · intercepto 1,3 · SSE = 1,8 · s = √(1,8/2)
    // x̄ = 1,5 · Sxx = 5 · t(0,90; 2 gl) = 1,8856 (tabla t, confianza 80 %)
    $proyeccion = Pronostico::proyectar([1, 3, 2, 4], 2, piso: false);
    $s = sqrt(0.9);
    $t = 1.885618;
    $margen = fn (float $x0) => $t * $s * sqrt(1 + 1 / 4 + ($x0 - 1.5) ** 2 / 5);

    expect($proyeccion)->toHaveCount(2)
        ->and($proyeccion[0]['valor'])->toEqualWithDelta(1.3 + 0.8 * 4, 1e-9)
        ->and($proyeccion[0]['inferior'])->toEqualWithDelta(1.3 + 0.8 * 4 - $margen(4), 1e-5)
        ->and($proyeccion[0]['superior'])->toEqualWithDelta(1.3 + 0.8 * 4 + $margen(4), 1e-5)
        ->and($proyeccion[1]['valor'])->toEqualWithDelta(1.3 + 0.8 * 5, 1e-9)
        ->and($proyeccion[1]['superior'] - $proyeccion[1]['valor'])->toEqualWithDelta($margen(5), 1e-5);
});

test('el intervalo se ensancha cuanto mas lejos se proyecta', function () {
    $proyeccion = Pronostico::proyectar([10, 14, 11, 17, 15, 19], 3);
    $anchos = array_map(fn ($p) => $p['superior'] - $p['inferior'], $proyeccion);

    expect($anchos[1])->toBeGreaterThan($anchos[0])
        ->and($anchos[2])->toBeGreaterThan($anchos[1]);
});

test('el cuantil t coincide con la tabla de la t de Student', function (float $p, int $gl, float $esperado) {
    expect(Estadistica::tStudentCuantil($p, $gl))->toEqualWithDelta($esperado, 1e-3);
})->with([
    'p 0,90 · 2 gl' => [0.90, 2, 1.886],
    'p 0,90 · 10 gl' => [0.90, 10, 1.372],
    'p 0,975 · 10 gl' => [0.975, 10, 2.228],
    'p 0,95 · 1 gl' => [0.95, 1, 6.314],
    'p 0,975 · 30 gl' => [0.975, 30, 2.042],
    'p 0,10 · 10 gl (simetrica)' => [0.10, 10, -1.372],
]);

test('el r2 mide que parte de la variacion explica la recta', function () {
    expect(Pronostico::regresion([10, 12, 14, 16])['r2'])->toEqualWithDelta(1.0, 1e-9)
        // y = 1,3,2,4: SST = 5 · SSE = 1,8 → R² = 0,64
        ->and(Pronostico::regresion([1, 3, 2, 4])['r2'])->toEqualWithDelta(0.64, 1e-9)
        ->and(Pronostico::regresion([5, 5, 5])['r2'])->toBeNull()
        ->and(Pronostico::calificarAjuste(0.64))->toBe('moderado')
        ->and(Pronostico::calificarAjuste(0.2))->toBe('debil')
        ->and(Pronostico::calificarAjuste(0.9))->toBe('fuerte');
});

test('saltar un mes incompleto proyecta los mismos meses del eje y estima su cierre', function () {
    $analisis = Pronostico::analizar([10, 12, 14, 16], 2, saltar: 1);

    expect($analisis['metodo']['cierre_estimado'][0]['valor'])->toEqualWithDelta(18.0, 1e-9)
        ->and($analisis['proyeccion'][0]['valor'])->toEqualWithDelta(20.0, 1e-9)
        ->and($analisis['proyeccion'][1]['valor'])->toEqualWithDelta(22.0, 1e-9)
        ->and($analisis['metodo']['n'])->toBe(4);
});

test('sin datos suficientes la ficha del metodo lo dice', function () {
    $analisis = Pronostico::analizar([4, 6], 3);

    expect($analisis['proyeccion'])->toBe([])
        ->and($analisis['metodo']['suficiente'])->toBeFalse()
        ->and($analisis['metodo']['validacion'])->toBeNull();
});

test('la validacion compara la recta con holt y la media movil sobre meses reservados', function () {
    // Recta perfecta: la recta y Holt aciertan, la media móvil (plana) no.
    $validacion = Pronostico::validar([10, 12, 14, 16, 18, 20, 22, 24, 26], 3);

    expect($validacion['meses'])->toBe(3)
        ->and($validacion['entrenamiento'])->toBe(6)
        ->and($validacion['recta'])->toEqualWithDelta(0.0, 1e-9)
        ->and($validacion['holt'])->toEqualWithDelta(0.0, 1e-6)
        ->and($validacion['media_movil'])->toBeGreaterThan(10)
        ->and($validacion['mejor'])->toBe('recta');
});

test('la validacion necesita meses suficientes para entrenar', function () {
    expect(Pronostico::validar([1, 2, 3, 4, 5, 6, 7, 8], 3))->toBeNull();
});

test('el mape ignora los meses con valor real cero', function () {
    // |10 − 8| / 10 = 20 % · el mes en cero no entra.
    expect(Pronostico::mape([10, 0], [8, 5]))->toEqualWithDelta(20.0, 1e-9)
        ->and(Pronostico::mape([0, 0], [1, 2]))->toBeNull();
});

test('holt sigue una tendencia lineal', function () {
    $pronostico = Pronostico::holt([3, 5, 7, 9, 11], 2);

    expect($pronostico[0])->toEqualWithDelta(13.0, 1e-6)
        ->and($pronostico[1])->toEqualWithDelta(15.0, 1e-6);
});

test('el pronostico del periodo deja fuera de la recta el mes en curso', function () {
    Carbon::setTestNow('2026-10-13 10:00:00');

    $periodo = PeriodoReporte::desdeFiltros('2026-06-01', null);
    // Jun-Sep crecen de a 2; octubre va por el día 13 y "cae" a 5.
    $serie = [10, 12, 14, 16, 5];

    $total = $periodo->pronostico($serie);
    $promedio = $periodo->pronostico($serie, sumaDelMes: false);

    expect($periodo->mesFinalIncompleto())->toBeTrue()
        ->and($periodo->toArray()['mes_incompleto'])->toMatchArray(['dias' => 13, 'de' => 31])
        ->and($total['metodo']['n'])->toBe(4)
        ->and($total['metodo']['mes_final_excluido'])->toBe($periodo->meses()->last())
        ->and($total['metodo']['cierre_estimado'][0]['valor'])->toEqualWithDelta(18.0, 1e-9)
        ->and($total['proyeccion'][0]['valor'])->toEqualWithDelta(20.0, 1e-9)
        ->and($total['variacion_mensual'])->toEqualWithDelta(2 / 13 * 100, 1e-9)
        // Un promedio o un precio no depende de los días: entra entero.
        ->and($promedio['metodo']['n'])->toBe(5)
        ->and($promedio['metodo']['mes_final_excluido'])->toBeNull();

    Carbon::setTestNow();
});

test('el periodo no pasa de hoy', function () {
    Carbon::setTestNow('2026-10-13 10:00:00');

    $periodo = PeriodoReporte::desdeFiltros('2026-08-01', '2026-12-31');

    expect($periodo->hasta->toDateString())->toBe('2026-10-13')
        ->and($periodo->meses())->toHaveCount(3);

    Carbon::setTestNow();
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
