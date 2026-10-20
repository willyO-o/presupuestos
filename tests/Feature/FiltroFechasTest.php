<?php

/**
 * Filtro de rango de fechas (`resources/js/Components/DateRangeFilter.vue`).
 *
 * Test sobre el código fuente: el comportamiento se verificó en el navegador
 * (el componente vive en el cliente), y acá se fija lo que no puede
 * perderse sin que nadie lo note — el atajo "Últimos 3 meses", la franja
 * para saltar por mes y año, y que las pantallas usen este componente y no
 * un calendario propio sin esas dos cosas.
 */
function fuenteDelFiltro(): string
{
    return file_get_contents(resource_path('js/Components/DateRangeFilter.vue'));
}

test('ofrece el atajo ultimos 3 meses, entre este mes y los ultimos 6', function () {
    $fuente = fuenteDelFiltro();

    expect($fuente)->toContain("'Últimos 3 meses': [inicioDelDia(sumarMeses(hoy, -3)), finHoy],")
        ->and(strpos($fuente, "'Este mes'"))->toBeLessThan(strpos($fuente, "'Últimos 3 meses'"))
        ->and(strpos($fuente, "'Últimos 3 meses'"))->toBeLessThan(strpos($fuente, "'Últimos 6 meses'"));
});

test('el rango personalizado permite saltar por mes y año desde y hasta', function () {
    expect(fuenteDelFiltro())
        ->toContain('<template #header>')
        ->toContain('aria-label="Mes de inicio"')
        ->toContain('aria-label="Año de inicio"')
        ->toContain('aria-label="Mes final"')
        ->toContain('aria-label="Año final"')
        // Mueve los calendarios del paquete en vez de dejarlos donde estaban.
        ->toContain('changeLeftMonth')
        ->toContain('changeRightMonth');
});

test('las pantallas con filtro de fechas usan el componente compartido', function (string $vista) {
    expect(file_get_contents(resource_path("js/{$vista}")))->toContain('<DateRangeFilter');
})->with([
    'Pages/Reportes/Financiero.vue',
    'Components/Reporte/BiEncabezado.vue',
]);
