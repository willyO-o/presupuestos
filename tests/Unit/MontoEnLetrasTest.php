<?php

use App\Services\Pdf\MontoEnLetras;

test('escribe el importe en letras como en las cotizaciones de la empresa', function (float|int|string $monto, string $esperado) {
    expect(MontoEnLetras::convertir($monto))->toBe($esperado);
})->with([
    'cabecera Kotex' => [2875, 'Dos Mil Ochocientos Setenta y Cinco 00/100 Bolivianos'],
    'isla Kotex' => [2300, 'Dos Mil Trescientos 00/100 Bolivianos'],
    'cabecera Huggies' => [3000, 'Tres Mil 00/100 Bolivianos'],
    'con centavos' => [360.47, 'Trescientos Sesenta 47/100 Bolivianos'],
    'decimal como texto (cast decimal:2)' => ['1176.48', 'Mil Ciento Setenta y Seis 48/100 Bolivianos'],
    'cien exacto' => [100, 'Cien 00/100 Bolivianos'],
    'ciento uno' => [101, 'Ciento Uno 00/100 Bolivianos'],
    'de una palabra' => [16, 'Dieciséis 00/100 Bolivianos'],
    'veintiún mil' => [21000, 'Veintiún Mil 00/100 Bolivianos'],
    'ciento un mil' => [101000, 'Ciento Un Mil 00/100 Bolivianos'],
    'un millón' => [1000000, 'Un Millón 00/100 Bolivianos'],
    'millones' => [2500000, 'Dos Millones Quinientos Mil 00/100 Bolivianos'],
    'cero' => [0, 'Cero 00/100 Bolivianos'],
    'uno con cincuenta' => [1.5, 'Uno 50/100 Bolivianos'],
]);

test('redondea a centavos sin perder un centavo por el punto flotante', function () {
    expect(MontoEnLetras::convertir(0.1 + 0.2))->toBe('Cero 30/100 Bolivianos')
        ->and(MontoEnLetras::convertir(19.999))->toBe('Veinte 00/100 Bolivianos');
});
