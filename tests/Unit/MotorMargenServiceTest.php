<?php

use App\Models\TipoProyecto;
use App\Services\Calculo\MotorMargenService;
use Tests\TestCase;

/**
 * El motor lee las tasas y umbrales de `config/margen.php`, así que necesita
 * la aplicación levantada — pero no la base de datos (es una función pura).
 */
uses(TestCase::class);

function motor(): MotorMargenService
{
    return new MotorMargenService;
}

/*
|--------------------------------------------------------------------------
| Caso de referencia: hoja "Muebles Exhibidores" del Excel original
|--------------------------------------------------------------------------
|
| Insumos: 0,55×45 + 0,15×145 + 0,33×70 + 1×10 + 0,07×80 + 6×15 = 175,20
| Tipo de proyecto "Medio": factor 1,3 · margen mínimo 50 %.
| Si alguno de estos números cambia, la reimplementación dejó de coincidir
| con la hoja de cálculo que usa la empresa.
*/

test('reproduce exactamente la hoja Muebles Exhibidores del Excel', function () {
    $insumos = [
        ['cantidad' => 0.55, 'costo' => 45],   // Fierro
        ['cantidad' => 0.15, 'costo' => 145],  // Plancha
        ['cantidad' => 0.33, 'costo' => 70],   // Pintura
        ['cantidad' => 1, 'costo' => 10],      // Otros
        ['cantidad' => 0.07, 'costo' => 80],   // Impresión adhesivo
        ['cantidad' => 6, 'costo' => 15],      // Mano de obra (horas)
    ];

    $costoBase = array_sum(array_map(fn (array $i): float => $i['cantidad'] * $i['costo'], $insumos));

    expect(round($costoBase, 2))->toBe(175.20);

    $resultado = motor()->calcular(costoBase: $costoBase, factorComplejidad: 1.3, margen: 0.50);

    expect(round($resultado->costoAjustado, 4))->toBe(227.7600)
        ->and(round($resultado->precio, 4))->toBe(341.6400)
        ->and(round($resultado->it, 4))->toBe(10.2492)
        ->and(round($resultado->utilidadAntesIue, 4))->toBe(103.6308)
        ->and(round($resultado->iue, 4))->toBe(25.9077)
        ->and(round($resultado->utilidadReal, 4))->toBe(77.7231)
        ->and($resultado->estado)->toBe('VERDE')
        ->and($resultado->recomendacion)->toBe('ACEPTAR')
        ->and(round($resultado->iva, 4))->toBe(44.4132)
        ->and(round($resultado->precioFinal, 4))->toBe(386.0532)
        // Muebles Exhibidores no lleva línea de instalación.
        ->and(round($resultado->totalPrecioFinal, 4))->toBe(386.0532);
});

test('la instalacion se suma despues del IVA y no paga impuestos', function () {
    $sinInstalacion = motor()->calcular(costoBase: 175.20, factorComplejidad: 1.3, margen: 0.50);
    $conInstalacion = motor()->calcular(costoBase: 175.20, factorComplejidad: 1.3, margen: 0.50, instalacion: 100);

    expect(round($conInstalacion->iva, 4))->toBe(round($sinInstalacion->iva, 4))
        ->and(round($conInstalacion->totalPrecioFinal, 4))
        ->toBe(round($sinInstalacion->totalPrecioFinal + 100, 4));
});

/*
|--------------------------------------------------------------------------
| Semáforo
|--------------------------------------------------------------------------
|
| Se compara contra el COSTO AJUSTADO, no contra el precio.
*/

test('el semaforo clasifica por utilidad real sobre el costo ajustado', function (float $margen, string $estado, string $recomendacion) {
    $resultado = motor()->calcular(costoBase: 100, factorComplejidad: 1, margen: $margen);

    expect($resultado->estado)->toBe($estado)
        ->and($resultado->recomendacion)->toBe($recomendacion);
})->with([
    // margen 60 % → utilidad real 42,53 % del costo ajustado
    'margen alto' => [0.60, 'VERDE', 'ACEPTAR'],
    // margen 30 % → utilidad real 21,80 %
    'margen medio' => [0.30, 'AMARILLO', 'REVISAR PRECIO'],
    // margen 15 % → utilidad real 10,90 %
    'margen bajo' => [0.15, 'ROJO', 'NO ACEPTAR'],
    'sin margen' => [0.0, 'ROJO', 'NO ACEPTAR'],
]);

test('un item sin costo cargado no es ROJO si tiene precio', function () {
    $conPrecio = motor()->evaluar(costoBase: 0, costoAjustado: 0, precio: 500);
    $sinPrecio = motor()->evaluar(costoBase: 0, costoAjustado: 0, precio: 0);

    expect($conPrecio->estado)->toBe('VERDE')
        ->and($sinPrecio->estado)->toBe('ROJO');
});

/*
|--------------------------------------------------------------------------
| Tipos de proyecto (CRUD) y linealidad
|--------------------------------------------------------------------------
*/

test('calcularCon toma el factor y el margen del tipo de proyecto', function () {
    $tipo = TipoProyecto::factory()->medio()->make();

    $conTipo = motor()->calcularCon($tipo, 175.20);
    $manual = motor()->calcular(costoBase: 175.20, factorComplejidad: 1.3, margen: 0.5);

    expect(round($conTipo->precio, 4))->toBe(round($manual->precio, 4))
        ->and(round($conTipo->factorComplejidad, 2))->toBe(1.30)
        ->and(round($conTipo->margen, 4))->toBe(0.5000);
});

test('sin tipo de proyecto usa el margen sugerido por defecto y no recarga complejidad', function () {
    config()->set('cotizacion.margen_sugerido', 0.45);

    $resultado = motor()->calcularCon(null, 100);

    expect(round($resultado->factorComplejidad, 2))->toBe(1.00)
        ->and(round($resultado->precio, 2))->toBe(145.00);
});

test('evaluar la suma de varias lineas equivale a sumar sus evaluaciones', function () {
    $lineaA = motor()->calcular(costoBase: 175.20, factorComplejidad: 1.3, margen: 0.50);
    $lineaB = motor()->calcular(costoBase: 80.00, factorComplejidad: 1.8, margen: 0.70);

    $agregado = motor()->evaluar(
        costoBase: 175.20 + 80.00,
        costoAjustado: $lineaA->costoAjustado + $lineaB->costoAjustado,
        precio: $lineaA->precio + $lineaB->precio,
    );

    expect(round($agregado->it, 4))->toBe(round($lineaA->it + $lineaB->it, 4))
        ->and(round($agregado->iue, 4))->toBe(round($lineaA->iue + $lineaB->iue, 4))
        ->and(round($agregado->utilidadReal, 4))->toBe(round($lineaA->utilidadReal + $lineaB->utilidadReal, 4));
});

test('evaluar deduce el margen implicito de un precio fijado a mano', function () {
    // El vendedor cobra 300 sobre un costo ajustado de 200: margen real 50 %.
    $resultado = motor()->evaluar(costoBase: 200, costoAjustado: 200, precio: 300);

    expect(round($resultado->margen, 4))->toBe(0.5000)
        ->and(round($resultado->rentabilidad(), 4))->toBe(round($resultado->utilidadReal / 200, 4));
});
