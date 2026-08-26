<?php

use App\Exceptions\FormulaInvalidaException;
use App\Services\Calculo\FormulaCalculator;
use App\Services\Calculo\MedidasCotizacion;

test('evalua area', function () {
    $calculator = new FormulaCalculator;
    $medidas = new MedidasCotizacion(ancho: 2.0, alto: 1.5);

    expect($calculator->evaluar('ancho * alto', $medidas))->toBe(3.0);
});

test('evalua perimetro', function () {
    $calculator = new FormulaCalculator;
    $medidas = new MedidasCotizacion(ancho: 2.0, alto: 1.5);

    expect($calculator->evaluar('(ancho + alto) * 2', $medidas))->toBe(7.0);
});

test('evalua perimetro con profundidad, el caso de letras 3D', function () {
    $calculator = new FormulaCalculator;
    $medidas = new MedidasCotizacion(ancho: 0.5, alto: 0.3, profundo: 0.08);

    // (0.5+0.3)*2*0.08 = 0.128
    expect($calculator->evaluar('(ancho + alto) * 2 * profundo', $medidas))->toBe(0.128);
});

test('evalua volumen', function () {
    $calculator = new FormulaCalculator;
    $medidas = new MedidasCotizacion(ancho: 2.0, alto: 1.0, profundo: 0.5);

    expect($calculator->evaluar('ancho * alto * profundo', $medidas))->toBe(1.0);
});

test('lanza excepcion si la formula usa una variable no disponible', function () {
    $calculator = new FormulaCalculator;
    $medidas = new MedidasCotizacion(ancho: 2.0, alto: 1.0); // sin profundo

    $calculator->evaluar('ancho * alto * profundo', $medidas);
})->throws(FormulaInvalidaException::class);

test('lanza excepcion si la expresion es sintacticamente invalida', function () {
    $calculator = new FormulaCalculator;

    $calculator->evaluar('ancho * * alto', new MedidasCotizacion(ancho: 1.0, alto: 1.0));
})->throws(FormulaInvalidaException::class);

test('validar no lanza para una expresion correcta', function () {
    $calculator = new FormulaCalculator;

    $calculator->validar('(ancho + alto) * 2');

    expect(true)->toBeTrue();
});

test('mensajeError devuelve null para una expresion correcta y un mensaje para una invalida', function () {
    $calculator = new FormulaCalculator;

    expect($calculator->mensajeError('ancho * alto'))->toBeNull()
        ->and($calculator->mensajeError('ancho / 0 +'))->toBeString();
});

test('medidas cotizacion solo expone las variables calculables', function () {
    $medidas = new MedidasCotizacion(ancho: 2.0, alto: 1.0);

    expect($medidas->variables())
        ->toBe(['ancho' => 2.0, 'alto' => 1.0, 'area' => 2.0, 'perimetro' => 6.0]);
});
