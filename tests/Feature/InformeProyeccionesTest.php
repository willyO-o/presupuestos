<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Reporte\InformeProyeccionesCsv;
use App\Services\Reporte\InformeProyeccionesService;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\RedaccionPronostico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
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
        // Pedidos sin decimales; el mes de la estimación va aparte (cabecera).
        ->and($pedidos['resumen']['proyeccion'])->toBe('18 pedidos')
        ->and($pedidos['resumen']['mes_proyeccion'])->toBe($septiembre)
        ->and($pedidos['resumen']['tendencia'])->toBe('Sube 28,6 % al mes')
        ->and($pedidos['resumen']['confiabilidad'])->toBe('Alta')
        // Redacción del informe modelo (2026-10-29): prudente y con el mes
        // en palabras.
        ->and(implode(' ', $pedidos['lectura']))
        ->toContain('Resultados registrados: de enero de 2026 a junio de 2026, la demanda promedió 7 pedidos al mes. En junio fue de 12 pedidos.')
        ->toContain('Tendencia: el modelo calcula un aumento de 2,0 pedidos por mes (+28,6 % mensual).')
        ->toContain('18 pedidos para septiembre de 2026')
        ->toContain('no son límites garantizados')
        ->toContain('Cómo usar la estimación: como una referencia confiable')
        ->and($informe['conclusiones'][0])->toBe('Pedidos: en promedio 7 pedidos por mes; van en aumento (+28,6 % al mes).');
});

test('el pdf trae resumen, secciones elegidas, lectura y anexo metodologico', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['demanda', 'alertas']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));

    expect($texto)
        ->toContain('INFORME DEL NEGOCIO')
        ->toContain('USO INTERNO')
        ->toContain('En pocas palabras')
        ->toContain('QUÉ ESPERAR EN LOS PRÓXIMOS MESES')
        ->toContain('Pedidos por mes')
        ->toContain('Rango probable')
        ->toContain('Anexo metodológico')
        // Guía para leerlo sin ser analista; lo técnico, en el anexo.
        ->toContain('Cómo leer este informe')
        ->toContain('intervalo de predicción al 80 %')
        // Sección no pedida: no aparece.
        ->not->toContain('Precio de los materiales');

    // Pedido del usuario: entendible sin formación técnica. La jerga
    // estadística solo puede aparecer en el anexo, al final.
    $cuerpo = substr($texto, 0, strpos($texto, 'Anexo metodológico'));
    foreach (['R²', 'MAPE', 'Holt', 'regresión', 'mínimos cuadrados', 'intervalo de predicción', 'grados de libertad'] as $jerga) {
        expect(mb_stripos($cuerpo, $jerga))->toBeFalse("\"{$jerga}\" aparece fuera del anexo");
    }
});

test('el anexo ordena el metodo en seis apartados numerados con cada formula aparte', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['demanda']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));
    $anexo = substr($texto, strpos($texto, 'Anexo metodológico'));

    // En orden, y la ficha de cada indicador después de los seis.
    expect($anexo)->toMatch('/01  Método de cálculo.*02  Tratamiento de los meses incompletos.*03  Rango probable'
        .'.*04  Qué tan bien se ajusta la recta.*05  Validación con meses reales.*06  Datos mínimos.*Ficha de cada estimación.*PEDIDOS/su');

    // Cada fórmula en su propio renglón, no dentro del párrafo, y con signos
    // que la fuente del PDF sí tiene (sin "?").
    foreach ([
        'y = a + b · x',
        'y(x0) ± t × s × raíz(1 + 1/n + (x0 - media de x)² / Sxx)',
        'R² = 1 - SSE / SST',
        'MAPE = promedio de |real - estimado| / |real| × 100',
    ] as $formula) {
        expect($anexo)->toContain(" {$formula} ");
    }

    expect($anexo)->not->toContain('?');
});

test('un parrafo que salta de pagina no hereda el blanco de la etiqueta del membrete', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo);
    $contenido = app(GeneradorPdf::class)->informeProyecciones($informe)->sinComprimir()->contenido();

    // Lo único escrito en blanco es la etiqueta "USO INTERNO" de cada página.
    preg_match_all('/q 1\.000 1\.000 1\.000 rg BT [\d.-]+ [\d.-]+ Td \((.*?)\) Tj ET Q/s', $contenido, $blancos);

    expect($blancos[1])->not->toBeEmpty()
        ->and(array_unique($blancos[1]))->toBe(['USO INTERNO']);
});

test('el informe con todas las secciones se genera aunque falten datos de costos', function () {
    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));

    // Las preguntas van primero; sin ventas ni consumo, cada una lo dice.
    expect(array_column($informe['secciones'], 'clave'))->toBe(['preguntas', 'demanda', 'costos', 'rentabilidad', 'alertas'])
        ->and($texto)->toContain('Se necesitan al menos 3 meses')
        ->and($texto)->toContain('Preguntas para decidir')
        ->and($texto)->toContain('No hay consumo de material registrado en el periodo.')
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
        ->toContain('no se usa para calcular la tendencia porque aún no termina (8 de 31 días)');

    Carbon::setTestNow();
});

test('la conclusion de precio contra costo no dice crece cuando los dos bajan', function () {
    $conclusiones = fn (float $precio, float $costo): array => RedaccionPronostico::conclusiones([], [
        ['clave' => 'rentabilidad', 'tendencia' => ['ticket' => $precio, 'costo' => $costo]],
    ]);

    // Los dos bajan, pero el precio más: el margen se achica.
    expect($conclusiones(-0.5, -0.3))->toBe(['Precios y costos: el precio de los trabajos va por detrás de su costo, así que la rentabilidad tiende a achicarse.'])
        ->and($conclusiones(2.0, 1.0))->toBe(['Precios y costos: el precio de los trabajos le gana a su costo, así que la rentabilidad se sostiene o crece.'])
        // Una décima de diferencia (redondeada, como se muestra) es ruido: lo
        // mismo que dice la pestaña.
        ->and($conclusiones(-0.62, -0.52))->toBe(['Precios y costos: se mueven parecido, así que la relación entre precio y costo se mantiene.']);

    expect(RedaccionPronostico::precioFrenteACosto(-0.62, -0.52))->toBe([
        'comparacion' => 'parecido',
        'lectura' => 'El precio promedio baja 0,6 % por mes y el costo, 0,5 %. Se mueven parecido: la relación entre precio y costo se mantiene.',
    ]);
});

test('el informe de alertas tiene resumen de revision, una hoja por prioridad y no lleva anexo', function () {
    // Una en rojo (11 % del costo) y una en amarillo que se ve "30,0 %" pero
    // es 29,96 %: la nota del límite lo explica.
    Cotizacion::factory()->create([
        'fecha' => '2026-03-10', 'estado' => 'PENDIENTE', 'codigo_verificacion' => 'COT-ROJA',
        'costo_ajustado' => 1000, 'utilidad_real' => 110, 'estado_margen' => 'ROJO',
    ]);
    Cotizacion::factory()->create([
        'fecha' => '2026-04-10', 'estado' => 'PENDIENTE', 'codigo_verificacion' => 'COT-LIMITE',
        'costo_ajustado' => 1000, 'utilidad_real' => 299.6, 'estado_margen' => 'AMARILLO',
    ]);

    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['alertas']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));

    expect($texto)
        ->toContain('INFORME DE ALERTAS')
        ->toContain('Alertas que requieren atención')
        ->toContain('2 alertas en el periodo')
        ->toContain('1 de prioridad alta:')
        ->toContain('Qué revisar primero')
        ->toContain('Criterios para interpretar las alertas')
        ->toContain('Presupuestos en rojo')
        ->toContain('COT-ROJA')
        ->toContain('Presupuestos cuyo precio conviene revisar')
        ->toContain('Nota sobre el límite del semáforo')
        ->toContain('sin redondear, su rentabilidad es 29,96 %')
        ->toContain('Alcance del documento')
        ->toContain('| Informe de alertas')
        // Pedido del usuario: el informe de alertas no lleva anexo ni la
        // portada general de estimaciones.
        ->not->toContain('Anexo metodológico')
        ->not->toContain('En pocas palabras')
        ->not->toContain('QUÉ ESPERAR EN LOS PRÓXIMOS MESES');
});

test('el informe de presupuestos y rentabilidad abre con un resumen y sigue los tres bloques de la pestana', function () {
    // Cuatro meses con presupuestos aprobados y hoja de costos (alcanza para
    // estimar la rentabilidad) y uno pendiente.
    foreach (['2026-01-10', '2026-02-10', '2026-03-10', '2026-04-10'] as $i => $fecha) {
        Cotizacion::factory()->aprobada()->create([
            'fecha' => $fecha, 'subtotal' => 1400 + $i * 50, 'descuento' => 0, 'total' => 1582,
            'costo_ajustado' => 1000, 'utilidad_real' => 300 - $i * 20, 'estado_margen' => 'VERDE',
        ]);
    }
    Cotizacion::factory()->create(['fecha' => '2026-05-10', 'estado' => 'PENDIENTE', 'total' => 500]);

    $informe = app(InformeProyeccionesService::class)->armar($this->usuario, $this->periodo, ['rentabilidad']);
    $texto = textoDelInforme(app(GeneradorPdf::class)->informeProyecciones($informe));
    $cuerpo = substr($texto, 0, strpos($texto, 'Anexo metodológico'));

    expect($texto)
        ->toContain('RESUMEN PARA LA GERENCIA')
        ->toContain('Lo principal')
        ->toContain('Por cada Bs 100 de costo ajustado, el resultado calculado es Bs')
        ->toContain('Qué revisar')
        ->toContain('Dar seguimiento al presupuesto pendiente.')
        ->toContain('BLOQUE 1 DE 3')
        ->toContain('Qué se cotizó y cuánto se aprobó')
        ->toContain('BLOQUE 2 DE 3')
        ->toContain('Tasa de aprobación')
        ->toContain('Mes a mes')
        ->toContain('BLOQUE 3 DE 3')
        ->toContain('Cómo salieron los 5 presupuestos emitidos')
        ->toContain('Semáforo del sistema: verde por encima de 30,0 %')
        ->toContain('Ficha de cada estimación')
        // El pie lleva el nombre de la pestaña, no "Informe del negocio".
        ->toContain('| Presupuestos y rentabilidad')
        // La portada general no va: el resumen la reemplaza.
        ->not->toContain('En pocas palabras')
        // Los mismos nombres que la pantalla.
        ->and($cuerpo)->not->toContain('Ganancia')
        ->and($cuerpo)->toContain('Monto aprobado')
        ->and($cuerpo)->toContain('Resultado calculado del mes');
});

test('cada pestana del BI abre el informe con su propia seccion', function () {
    // Pedido del usuario: el botón "Generar informe" daba el mismo PDF en
    // todas las pestañas porque siempre salía con las cinco secciones.
    $fuente = File::get(resource_path('js/Components/Reporte/BiEncabezado.vue'));

    foreach ([
        'reportes.bi.preguntas' => 'preguntas',
        'reportes.bi' => 'demanda',
        'reportes.bi.costos' => 'costos',
        'reportes.bi.rentabilidad' => 'rentabilidad',
        'reportes.bi.alertas' => 'alertas',
    ] as $ruta => $seccion) {
        expect($fuente)->toContain("'{$ruta}': '{$seccion}'")
            ->and(array_keys(InformeProyeccionesService::SECCIONES))->toContain($seccion)
            ->and(Route::has($ruta))->toBeTrue();
    }

    expect($fuente)->toContain('seccionPropia ? [seccionPropia]');
});
