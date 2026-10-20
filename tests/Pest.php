<?php

use App\Services\Pdf\RespuestaPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Texto visible del informe de BI en PDF (mismo criterio que `textoDelPdf()`
 * de GeneradorPdfTest). Lo usan InformeProyeccionesTest y PreguntasNegocioTest.
 */
function textoDelInforme(RespuestaPdf $pdf): string
{
    preg_match_all('/BT [\d.-]+ ([\d.-]+) Td \((.*?)\) Tj ET/s', $pdf->sinComprimir()->contenido(), $marcas, PREG_SET_ORDER);

    $texto = '';
    $renglon = null;

    foreach ($marcas as $marca) {
        if ($renglon !== null && $marca[1] !== $renglon) {
            $texto .= ' ';
        }

        $renglon = $marca[1];
        $texto .= preg_replace('/\\\\([()\\\\])/', '$1', $marca[2]);
    }

    return mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
}
