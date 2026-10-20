<?php

/**
 * Todos los botones de PDF e impresión del panel van en el mismo rojo
 * (`.btn-pdf` / `.btn-pdf-solid`, pedido del usuario), para encontrarlos de
 * un vistazo. Es un test sobre el código fuente: un botón nuevo que abra un
 * PDF con otra clase no rompe nada a la vista, solo deja de reconocerse.
 */

/**
 * Cada `<a>`/`<button>` que abre o baja un PDF del sistema, o imprime.
 *
 * @return list<array{archivo: string, etiqueta: string}>
 */
function botonesDePdf(): array
{
    $raiz = resource_path('js');
    $botones = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->getExtension() !== 'vue') {
            continue;
        }

        preg_match_all('/<(?:a|button)\b[^>]*>/s', file_get_contents($archivo->getPathname()), $etiquetas);

        foreach ($etiquetas[0] as $etiqueta) {
            $esPdf = preg_match("/\.pdf'|urlPdf\(|urlInforme\('pdf'\)|@click=\"imprimir\"/", $etiqueta) === 1;

            if ($esPdf && str_contains($etiqueta, 'class="')) {
                $botones[] = ['archivo' => $archivo->getFilename(), 'etiqueta' => preg_replace('/\s+/', ' ', $etiqueta)];
            }
        }
    }

    return $botones;
}

test('todo boton de PDF o de imprimir va en rojo', function () {
    $botones = botonesDePdf();

    // Hoy son 17 (más el "PDF firmado", que abre un archivo subido): si la
    // búsqueda deja de encontrarlos, el test no estaría probando nada.
    expect(count($botones))->toBeGreaterThanOrEqual(17);

    $sinRojo = array_values(array_filter(
        $botones,
        fn (array $b): bool => preg_match('/\bbtn-pdf(-solid)?\b/', $b['etiqueta']) !== 1,
    ));

    expect($sinRojo)->toBe([]);
});

test('el rojo del PDF es propio y no el de peligro', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--c-pdf: #c62828;')
        ->toMatch('/\.btn-pdf \{\s*background-color: var\(--c-pdf-soft\);\s*color: var\(--c-pdf\);/');
});

test('el boton de PDF del cotizador publico usa el mismo rojo', function () {
    expect(file_get_contents(resource_path('views/publico/cotizacion.blade.php')))->toContain('bg-marca-pdf');
});
