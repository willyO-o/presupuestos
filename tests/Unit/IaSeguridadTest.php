<?php

use App\Ai\Seguridad\ConsultaSoloLectura;
use App\Ai\Seguridad\MinimizadorDatos;
use App\Ai\Seguridad\VerificadorCifras;
use App\Services\Reporte\Estadistica;

/**
 * Piezas puras de la IA del BI: estadística de anomalías, verificación de
 * cifras, minimización de datos y la lista blanca de sentencias de lectura.
 */

/*
|--------------------------------------------------------------------------
| Estadística
|--------------------------------------------------------------------------
*/

test('mediana y percentil interpolan como una planilla', function () {
    expect(Estadistica::mediana([3, 1, 2]))->toBe(2.0)
        ->and(Estadistica::mediana([1, 2, 3, 4]))->toBe(2.5)
        ->and(Estadistica::percentil([10, 20, 30, 40, 50], 10))->toEqualWithDelta(14.0, 1e-9)
        ->and(Estadistica::mediana([]))->toBeNull();
});

test('la cola de Poisson mide lo raro de un conteo chico', function () {
    // P(X ≤ 2 | λ = 8,5) = e^-8,5 · (1 + 8,5 + 36,125) ≈ 0,0093
    expect(Estadistica::poissonAcumulada(2, 8.5))->toEqualWithDelta(0.00928, 0.0001)
        ->and(Estadistica::colaPoisson(2, 8.5))->toEqualWithDelta(0.00928, 0.0001)
        // Por arriba: P(X ≥ 15 | λ = 8) ≈ 0,0173
        ->and(Estadistica::colaPoisson(15, 8.0))->toEqualWithDelta(0.0173, 0.0005)
        // Lo esperable no es raro.
        ->and(Estadistica::colaPoisson(8, 8.0))->toBeGreaterThan(0.5);
});

test('los intervalos entre fechas ignoran repetidas y desorden', function () {
    expect(Estadistica::intervalosEnDias(['2026-01-10', '2026-01-01', '2026-01-10', '2026-01-04']))->toBe([3, 6]);
});

/*
|--------------------------------------------------------------------------
| Verificador de cifras
|--------------------------------------------------------------------------
*/

test('lee números en formato boliviano y con punto decimal, sin fechas', function () {
    // El signo menos tipográfico (−) no se toma como signo: el verificador
    // acepta igual el valor absoluto de cada hecho.
    expect(VerificadorCifras::numeros('Vendimos Bs 12.450,50 (12,5 %) el 13/10 y 1234.5 más; −3'))
        ->toBe([12450.5, 12.5, 1234.5, 3.0]);
});

test('acepta cifras de los hechos redondeadas y rechaza las inventadas', function () {
    $permitidos = VerificadorCifras::permitidosDe(['monto' => 12450.37, 'variacion' => -12.4, 'texto' => 'mediana de 8,5']);

    expect(VerificadorCifras::noEncontrados('Se vendió Bs 12.450 y bajó 12 % frente a 8,5', $permitidos))->toBe([])
        ->and(VerificadorCifras::noEncontrados('Se vendió Bs 15.000', $permitidos))->toBe([15000.0])
        // Enteros chicos de redacción y años: libres.
        ->and(VerificadorCifras::noEncontrados('Las 3 familias en 2026', $permitidos))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Minimizador de datos
|--------------------------------------------------------------------------
*/

test('quita claves sensibles aunque vengan anidadas', function () {
    $limpio = MinimizadorDatos::limpiar([
        'cliente' => 'Cervecería del Altiplano',
        'email' => 'compras@x.example',
        'contacto' => ['telefono' => '7123', 'nombre' => 'Ana'],
        'nit' => '123',
        'usuarios' => [['password' => 'x', 'remember_token' => 'y', 'name' => 'Admin']],
        'monto' => 1500.5,
        'api_key' => 'sk-...',
        'Dirección' => 'Av. 6 de Marzo',
    ]);

    expect($limpio)->toBe([
        'cliente' => 'Cervecería del Altiplano',
        'contacto' => ['nombre' => 'Ana'],
        'usuarios' => [['name' => 'Admin']],
        'monto' => 1500.5,
    ]);
});

/*
|--------------------------------------------------------------------------
| Lista blanca de lectura
|--------------------------------------------------------------------------
*/

test('solo deja pasar sentencias de lectura', function (string $sql, bool $esperado) {
    expect(ConsultaSoloLectura::esLectura($sql))->toBe($esperado);
})->with([
    ['select * from cotizacion where id = ?', true],
    ['  SELECT count(*) as aggregate from pedido', true],
    ['with t as (select 1) select * from t', true],
    ['/* comentario */ select 1', true],
    ['insert into area (nombre) values (?)', false],
    ['update cotizacion set total = 0', false],
    ['delete from pago', false],
    ['drop table users', false],
    ['select 1; delete from pago', false],
    ['-- select\ndelete from pago', false],
    ['truncate table pedido', false],
]);
