<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Ícono de ayuda "i" (`Components/AyudaInfo.vue`) en los cuadros de
 * información del panel: todos los gráficos, y en Reportes e Inteligencia de
 * negocios también cada tarjeta con título y cada indicador.
 *
 * Es un test sobre el código fuente, no sobre una pantalla: la ayuda vive en
 * un archivo de datos (`resources/js/Data/Ayudas.js`) y una clave mal escrita
 * no rompe nada a la vista — el ícono simplemente no aparece. Este test es el
 * que avisa cuando alguien agrega un cuadro sin explicarlo o renombra una
 * clave.
 */

/**
 * Tarjetas que a propósito no llevan ícono propio, con el motivo.
 */
const TARJETAS_SIN_AYUDA = [
    // Agrupa los dos gráficos de rentabilidad, y cada uno ya trae el suyo.
    'Proyección de rentabilidad',
];

function clavesDeAyuda(): array
{
    preg_match_all("/^\s{4}'([a-z0-9.-]+)': \{/m", File::get(resource_path('js/Data/Ayudas.js')), $claves);

    return $claves[1];
}

/**
 * Archivos .vue de una carpeta, con PHP puro y no con `File`/
 * `resource_path()`: Pest arma los datasets antes de levantar la aplicación.
 *
 * @return array<string, array{0: string}>
 */
function vistasDe(string $carpeta, ?string $debeContener = null): array
{
    $raiz = dirname(__DIR__, 2).'/resources/js/'.$carpeta;
    $vistas = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->getExtension() !== 'vue') {
            continue;
        }

        if ($debeContener !== null && ! str_contains(file_get_contents($archivo->getPathname()), $debeContener)) {
            continue;
        }

        $vistas[str_replace([$raiz.DIRECTORY_SEPARATOR, '\\'], ['', '/'], $archivo->getPathname())] = [$archivo->getPathname()];
    }

    ksort($vistas);

    return $vistas;
}

/**
 * Claves usadas en las pantallas: `<AyudaInfo clave="...">`, `<BiKpi ayuda="...">`,
 * `<ChartCard ayuda="...">` y las de los indicadores del Panorama, que se
 * declaran en el script (`ayuda: '...'`) porque se arman con v-for.
 */
function clavesUsadas(): Collection
{
    return collect(File::allFiles(resource_path('js/Pages')))
        ->flatMap(function ($archivo): array {
            preg_match_all(
                "/<AyudaInfo clave=\"([^\"]+)\"|<(?:BiKpi|ChartCard)[^>]*\sayuda=\"([^\"]+)\"|\bayuda: '([^']+)'/s",
                $archivo->getContents(),
                $claves,
            );

            return array_values(array_filter([...$claves[1], ...$claves[2], ...$claves[3]]));
        });
}

test('cada grafico de la pantalla tiene su icono de ayuda', function (string $ruta) {
    $fuente = File::get($ruta);

    // `ChartCard` trae el ícono adentro: cuenta como uno.
    $iconos = substr_count($fuente, '<AyudaInfo') + preg_match_all('/<ChartCard[^>]*\s:?ayuda="/s', $fuente);

    expect($iconos)->toBeGreaterThanOrEqual(substr_count($fuente, '<BaseChart'));
})->with(vistasDe('Pages', '<BaseChart'));

test('en reportes y BI cada tarjeta con titulo tiene su icono de ayuda', function (string $ruta) {
    preg_match_all('/<span class="card-title">(.*?)<\/span>/s', File::get($ruta), $titulos);

    $sinAyuda = collect($titulos[1])
        ->reject(fn (string $titulo): bool => str_contains($titulo, '<AyudaInfo'))
        ->map(fn (string $titulo): string => trim(strip_tags($titulo)))
        ->reject(fn (string $titulo): bool => in_array($titulo, TARJETAS_SIN_AYUDA, true))
        ->values()
        ->all();

    expect($sinAyuda)->toBe([]);
})->with(vistasDe('Pages/Reportes'));

test('en reportes y BI cada indicador tiene su icono de ayuda', function (string $ruta) {
    $fuente = File::get($ruta);

    preg_match_all('/<BiKpi\b[^>]*>/s', $fuente, $kpis);

    foreach ($kpis[0] as $kpi) {
        expect($kpi)->toMatch('/\sayuda="[^"]+"/');
    }

    // Los del Panorama (KpiCard) reciben la clave por binding desde el script.
    preg_match_all('/<KpiCard\s[^>]*>/s', $fuente, $comparados);

    foreach ($comparados[0] as $kpi) {
        expect($kpi)->toMatch('/\s:ayuda="[^"]+"/');
    }

    // Y cada ChartCard trae la suya: escrita (Panorama) o por binding desde
    // el script cuando las tarjetas salen de un v-for (Preguntas).
    preg_match_all('/<ChartCard\s[^>]*>/s', $fuente, $tarjetas);

    // Si el Panorama usa estos componentes, las comprobaciones de arriba
    // tienen que haber encontrado algo (si no, no estarían probando nada).
    if (str_contains($fuente, '<KpiCard')) {
        expect($comparados[0])->not->toBeEmpty()->and($tarjetas[0])->not->toBeEmpty();
    }

    foreach ($tarjetas[0] as $tarjeta) {
        expect($tarjeta)->toMatch('/\s:?ayuda="[^"]+"/');
    }

    // Las tarjetas de indicador escritas a mano (reportes financiero y de
    // producción) llevan la etiqueta con su ícono al lado.
    expect(substr_count($fuente, 'etiqueta-con-ayuda'))->toBe(substr_count($fuente, 'stat-card card'));
})->with(vistasDe('Pages/Reportes'));

test('toda clave de ayuda usada en una pantalla existe y no se repite', function () {
    $definidas = clavesDeAyuda();
    $usadas = clavesUsadas();

    expect($usadas)->not->toBeEmpty()
        ->and($usadas->diff($definidas)->values()->all())->toBe([])
        // Una ayuda por cuadro: la misma clave en dos lugares es un copy-paste.
        ->and($usadas->duplicates()->values()->all())->toBe([])
        // Y ninguna ayuda escrita que no use nadie.
        ->and(collect($definidas)->diff($usadas)->values()->all())->toBe([]);
});

test('cada ayuda trae que muestra y como leerlo', function () {
    $fuente = File::get(resource_path('js/Data/Ayudas.js'));

    foreach (clavesDeAyuda() as $clave) {
        preg_match("/'".preg_quote($clave, '/')."': \{(.*?)\n    \},/s", $fuente, $bloque);

        expect($bloque[1] ?? '')
            ->toContain('titulo:')
            ->toContain('que:')
            ->toContain('leer: [');
    }
});

test('los textos de ayuda no dicen plata', function () {
    // Pedido del usuario: para los montos, "dinero" o "ingresos". Solo los
    // textos: el comentario del archivo nombra la regla.
    $textos = preg_replace('#/\*.*?\*/#s', '', File::get(resource_path('js/Data/Ayudas.js')));

    expect(preg_match('/\bplata\b/iu', $textos))->toBe(0);
});

test('las ayudas y las preguntas usan un registro neutro, sin jerga', function () {
    // Pedido del usuario (2026-10-24): textos entendibles pero no coloquiales.
    // Solo los textos: los comentarios nombran la regla y sus ejemplos.
    $sinComentarios = fn (string $fuente): string => preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $fuente);
    $textos = $sinComentarios(File::get(resource_path('js/Data/Ayudas.js')))
        .$sinComentarios(File::get(app_path('Services/Reporte/PreguntasNegocioService.php')));

    foreach (['harto', 'nomás', 'chamba', 'caser', 'ahorita', 'solito', 'trancadera', 'se tranca', 'baratito', 'a full', 'nos costó', 'gastamos', 'cobramos', 'se pasaron'] as $palabra) {
        expect(mb_stripos($textos, $palabra))->toBeFalse("Quedó \"{$palabra}\" en los textos");
    }
});
