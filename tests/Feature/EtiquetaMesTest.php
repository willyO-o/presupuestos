<?php

use App\Services\Reporte\PeriodoReporte;
use Illuminate\Support\Carbon;

/**
 * Formato único de mes de los reportes: mes-año, `Ene-2026` (pedido del
 * usuario). Lo usan los ejes y tablas del BI, los PDFs y el asistente; el
 * navegador tiene su espejo en `mesAnio()` de Utils/FormatoReporte.js.
 */
test('un mes se rotula como mes-año, sin puntos ni variantes del idioma', function (string $fecha, string $esperado) {
    expect(PeriodoReporte::etiquetaMes(Carbon::parse($fecha)))->toBe($esperado);
})->with([
    'enero' => ['2026-01-15', 'Ene-2026'],
    'septiembre' => ['2025-09-01', 'Sep-2025'],
    'diciembre' => ['2026-12-31', 'Dic-2026'],
]);

test('los meses del periodo y los de la proyeccion salen en mes-año', function () {
    $periodo = PeriodoReporte::desdeFiltros('2025-11-01', '2026-02-28');

    expect($periodo->meses()->all())->toBe([
        '2025-11' => 'Nov-2025',
        '2025-12' => 'Dic-2025',
        '2026-01' => 'Ene-2026',
        '2026-02' => 'Feb-2026',
    ])
        ->and($periodo->mesesSiguientes(2))->toBe(['Mar-2026', 'Abr-2026']);
});

test('el espejo del navegador usa el mismo formato', function () {
    // Mismos nombres de mes en los dos lados: si alguien cambia uno, este
    // test lo obliga a mirar el otro.
    $js = file_get_contents(resource_path('js/Utils/FormatoReporte.js'));

    expect($js)
        ->toContain("const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];")
        ->toContain('export function mesAnio(valor)')
        ->toContain('return `${MESES[Number(partes[2]) - 1] ?? partes[2]}-${partes[1]}`;');
});
