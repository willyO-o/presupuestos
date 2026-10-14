<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Pdf\RespuestaPdf;
use App\Services\Reporte\InformeProyeccionesCsv;
use App\Services\Reporte\InformeProyeccionesService;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Informe de proyecciones (PDF y CSV) y la ficha del método que acompaña a
 * cada pronóstico del BI.
 *
 * Periodo FIJO de seis meses completos (enero–junio 2026) con una demanda
 * perfectamente lineal —2, 4, 6, 8, 10, 12 pedidos— para que cada número del
 * texto automático se pueda verificar a mano: la recta proyecta 14, 16 y 18.
 */
beforeEach(function () {
    $this->periodo = PeriodoReporte::desdeFiltros('2026-01-01', '2026-06-30');
    // Alcance TODAS: acá se prueba el informe; el acotado tiene su test.
    $this->usuario = User::factory()->todasLasSucursales()->create();

    foreach ([1 => 2, 2 => 4, 3 => 6, 4 => 8, 5 => 10, 6 => 12] as $mes => $cantidad) {
        pedidosDelMes($cantidad, sprintf('2026-%02d-10', $mes));
    }
});

/**
 * Pedidos con UNA sucursal, área, vendedor y cliente reciclados: las
 * factories de Área y Sucursal usan `unique()` sobre listas cortas y se
 * agotan si cada pedido arma su propia cadena (ver reporte.md).
 */
function pedidosDelMes(int $cantidad, string $fecha): void
{
    $sucursal = Sucursal::query()->first() ?? Sucursal::factory()->create();
    $area = Area::query()->first() ?? Area::factory()->create();
    $empleado = Empleado::query()->first() ?? Empleado::factory()->recycle($sucursal)->recycle($area)->create();
    $cliente = Cliente::query()->first() ?? Cliente::factory()->create();

    Pedido::factory()
        ->count($cantidad)
        ->recycle([$sucursal, $area, $empleado, $cliente])
        ->create(['fecha_pedido' => $fecha]);
}

/** Texto visible del PDF (mismo criterio que `textoDelPdf()` de GeneradorPdfTest). */
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

function usuarioConBi(): User
{
    Permission::findOrCreate('reportes.bi', 'web');
    $usuario = User::factory()->todasLasSucursales()->create();
    $usuario->givePermissionTo('reportes.bi');

    return $usuario;
}

/*
|--------------------------------------------------------------------------
| Rutas
|--------------------------------------------------------------------------
*/

test('el informe pide el permiso del BI', function (string $ruta) {
    Permission::findOrCreate('reportes.bi', 'web');

    $this->actingAs(User::factory()->create())->get(route($ruta))->assertForbidden();
})->with(['reportes.bi.informe.pdf', 'reportes.bi.informe.csv']);

test('el pdf sale como vista previa y se puede forzar la descarga', function () {
    $this->actingAs(usuarioConBi());
    $parametros = ['desde' => '2026-01-01', 'hasta' => '2026-06-30'];

    $vista = $this->get(route('reportes.bi.informe.pdf', $parametros))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($vista->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($vista->getContent())->toStartWith('%PDF');

    $descarga = $this->get(route('reportes.bi.informe.pdf', [...$parametros, 'descargar' => 1]))->assertOk();

    expect($descarga->headers->get('Content-Disposition'))
        ->toStartWith('attachment;')
        ->toContain('xtrapubli-informe-proyecciones-2026-01-01-al-2026-06-30.pdf');
});

test('el csv sale para excel en espanol', function () {
    $respuesta = $this->actingAs(usuarioConBi())
        ->get(route('reportes.bi.informe.csv', ['desde' => '2026-01-01', 'hasta' => '2026-06-30', 'secciones' => ['demanda']]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $respuesta->getContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Sección;Indicador;Formato;Mes;Tipo;Valor')
        // Junio real (12) y la proyección de septiembre (18), con coma decimal
        // y el rango probable en las columnas de al lado.
        ->toContain(';Real;12;')
        ->toMatch('/;Proyección;18;;\d+(,\d+)?;\d+(,\d+)?/u')
        ->toContain('Error validación recta %');
});

test('una seccion desconocida se rechaza', function () {
    $this->actingAs(usuarioConBi())
        ->get(route('reportes.bi.informe.pdf', ['secciones' => ['demanda', 'sueldos']]))
        ->assertSessionHasErrors('secciones.1');
});

/*
|--------------------------------------------------------------------------
| Contenido
|--------------------------------------------------------------------------
*/

test('el texto automatico usa las cifras de la proyeccion', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['demanda']);
    $pedidos = $informe['secciones'][0]['indicadores'][0];
    $septiembre = $this->periodo->mesesSiguientes(3)[2];

    expect($informe['secciones'])->toHaveCount(1)
        ->and($pedidos['proyeccion'][2]['valor'])->toEqualWithDelta(18.0, 1e-9)
        ->and($pedidos['metodo']['r2'])->toEqualWithDelta(1.0, 1e-9)
        ->and($pedidos['resumen']['proyeccion'])->toBe("18,0 pedidos ({$septiembre})")
        ->and(implode(' ', $pedidos['lectura']))
        ->toContain('promedió 7,0 pedidos por mes')
        ->toContain('La tendencia es creciente')
        ->toContain("para {$septiembre} 18,0 pedidos")
        ->toContain('ajuste fuerte')
        ->and($informe['conclusiones'][0])->toContain('La demanda (pedidos) crece');
});

test('el pdf trae resumen, secciones elegidas, lectura y anexo metodologico', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['demanda', 'alertas']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));

    expect($texto)
        ->toContain('INFORME DE PROYECCIONES')
        ->toContain('USO INTERNO')
        ->toContain('Conclusiones')
        ->toContain('RESUMEN EJECUTIVO')
        ->toContain('Pedidos por mes')
        ->toContain('Rango probable (80 %)')
        ->toContain('Anexo metodológico')
        ->toContain('Intervalo de predicción al 80 %')
        // Sección no pedida: no aparece.
        ->not->toContain('Índice de costo de materiales');
});

test('el informe con todas las secciones se genera aunque falten datos de costos', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));

    expect(array_column($informe['secciones'], 'clave'))->toBe(['demanda', 'costos', 'rentabilidad', 'alertas'])
        ->and($texto)->toContain('Se necesitan al menos 3 meses')
        ->and(InformeProyeccionesCsv::generar($informe))->toContain('Costos y materiales');
});

/*
|--------------------------------------------------------------------------
| Mes en curso
|--------------------------------------------------------------------------
*/

test('la demanda deja fuera de la recta el mes en curso y estima su cierre', function () {
    Carbon::setTestNow('2026-07-08 10:00:00');
    // Julio va por el día 8: apenas 3 pedidos.
    pedidosDelMes(3, '2026-07-05');

    $periodo = PeriodoReporte::desdeFiltros('2026-01-01', null);
    $demanda = app(InteligenciaNegociosService::class)->datos($this->usuario, $periodo)['demanda'];

    expect($demanda['metodo']['mes_final_excluido'])->toBe($periodo->meses()->last())
        ->and($demanda['metodo']['n'])->toBe(6)
        // Con julio adentro la recta caería; sin él, julio cerraría en 14.
        ->and($demanda['metodo']['cierre_estimado'][0]['valor'])->toEqualWithDelta(14.0, 1e-9)
        ->and($demanda['proyeccion'][0]['pedidos_estimados'])->toEqualWithDelta(16.0, 1e-9)
        ->and(collect($demanda['metodo']['explicacion'])->pluck('texto')->implode(' '))
        ->toContain('no entra en la recta porque está incompleto (8 de 31 días)');

    Carbon::setTestNow();
});
