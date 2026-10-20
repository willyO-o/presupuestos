<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleMaterial;
use App\Models\PedidoSeguimiento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Pdf\RespuestaPdf;
use App\Services\Reporte\InformeReportesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * PDF del reporte financiero y del reporte de producción.
 *
 * Como en GeneradorPdfTest, se lee el PDF DE VERDAD (`sinComprimir()` + los
 * operadores de texto). Lo que se fija: que exportar sea un permiso aparte de
 * ver, que el papel diga lo mismo que la pantalla (mismos servicios, mismo
 * alcance por sucursal) y que las cifras engañosas —un pedido sin consumo
 * registrado con "100 % de margen"— no entren en los totales.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');

    collect(['reportes.financiero', 'reportes.produccion', 'reportes.exportar_financiero', 'reportes.exportar_produccion'])
        ->each(fn (string $permiso) => Permission::findOrCreate($permiso, 'web'));

    $this->sucursal = Sucursal::factory()->create(['nombre' => 'El Alto']);
    $this->area = Area::factory()->create(['nombre' => 'Impresión']);
    $this->empleado = Empleado::factory()->recycle([$this->sucursal, $this->area])->create();
});

afterEach(fn () => Carbon::setTestNow());

/** Texto visible del PDF (mismo criterio que `textoDelPdf()` de GeneradorPdfTest). */
function textoDelReporte(RespuestaPdf $pdf): string
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

function usuarioDeReportes(string ...$permisos): User
{
    $usuario = User::factory()->todasLasSucursales()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * Un pedido de la sucursal y el área de la prueba (recicladas: las factories
 * de Área y Sucursal usan `unique()` sobre listas cortas, ver reporte.md).
 *
 * @param  array<string, mixed>  $pedido
 */
function pedidoDeReporte(string $cliente, float $total, ?float $costoReal, array $pedido = [], ?Sucursal $sucursal = null): Pedido
{
    $sucursal ??= test()->sucursal;

    $cotizacion = Cotizacion::factory()->recycle([$sucursal, test()->area, test()->empleado])->create([
        'cliente_id' => Cliente::factory()->create(['razon_social' => $cliente])->id,
        'sucursal_id' => $sucursal->id,
        'estado' => 'CONVERTIDA',
        'fecha' => '2026-08-10',
        'total' => $total,
    ]);

    $creado = Pedido::factory()->create([
        'cotizacion_id' => $cotizacion->id,
        'estado' => 'ENTREGADO',
        'total' => $total,
        'fecha_pedido' => '2026-08-12',
        'fecha_entrega_estimada' => '2026-08-20',
        'fecha_entrega_real' => '2026-08-20',
        ...$pedido,
    ]);

    $detalle = PedidoDetalle::factory()->recycle($cotizacion)->create(['pedido_id' => $creado->id, 'estado_item' => $creado->estado === 'CANCELADO' ? 'DISENO' : ($creado->estado === 'ENTREGADO' ? 'ENTREGADO' : $creado->estado)]);

    if ($costoReal !== null) {
        PedidoDetalleMaterial::factory()->create(['pedido_detalle_id' => $detalle->id, 'costo_real' => $costoReal]);
    }

    return $creado;
}

/*
|--------------------------------------------------------------------------
| Permisos
|--------------------------------------------------------------------------
*/

test('exportar es un permiso aparte de ver', function (string $ruta, string $ver, string $exportar) {
    $this->actingAs(usuarioDeReportes($ver))->get(route($ruta))->assertForbidden();
    $this->actingAs(usuarioDeReportes($exportar))->get(route($ruta))->assertForbidden();
    $this->actingAs(usuarioDeReportes($ver, $exportar))->get(route($ruta))->assertOk();
})->with([
    'financiero' => ['reportes.financiero.pdf', 'reportes.financiero', 'reportes.exportar_financiero'],
    'produccion' => ['reportes.produccion.pdf', 'reportes.produccion', 'reportes.exportar_produccion'],
]);

test('un invitado no exporta reportes', function (string $ruta) {
    $this->get(route($ruta))->assertRedirect(route('login'));
})->with(['reportes.financiero.pdf', 'reportes.produccion.pdf']);

test('cada rol trae los permisos de exportar que le corresponden', function () {
    $roles = config('acl.roles');

    expect($roles['administrador']['permissions'])->toContain('reportes.exportar_financiero', 'reportes.exportar_produccion')
        ->and($roles['gerente']['permissions'])->toContain('reportes.exportar_financiero', 'reportes.exportar_produccion')
        ->and($roles['contador']['permissions'])->toContain('reportes.exportar_financiero')->not->toContain('reportes.exportar_produccion')
        ->and($roles['jefe-produccion']['permissions'])->toContain('reportes.exportar_produccion')->not->toContain('reportes.exportar_financiero')
        ->and(config('acl.modules.reportes.permissions'))->toHaveKeys(['reportes.exportar_financiero', 'reportes.exportar_produccion']);
});

/*
|--------------------------------------------------------------------------
| Entrega
|--------------------------------------------------------------------------
*/

test('sale como vista previa y con descargar=1 baja con el periodo en el nombre', function () {
    $usuario = usuarioDeReportes('reportes.financiero', 'reportes.exportar_financiero');
    $parametros = ['desde' => '2026-01-01', 'hasta' => '2026-09-30'];

    $vista = $this->actingAs($usuario)->get(route('reportes.financiero.pdf', $parametros))->assertOk();
    expect($vista->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($vista->headers->get('Content-Disposition'))->toStartWith('inline');

    $descarga = $this->actingAs($usuario)->get(route('reportes.financiero.pdf', [...$parametros, 'descargar' => 1]))->assertOk();
    expect($descarga->headers->get('Content-Disposition'))
        ->toStartWith('attachment')
        ->toContain('xtrapubli-reporte-financiero-2026-01-01-al-2026-09-30.pdf');
});

test('una fecha invalida no llega a armar el documento', function () {
    $this->actingAs(usuarioDeReportes('reportes.financiero', 'reportes.exportar_financiero'))
        ->get(route('reportes.financiero.pdf', ['desde' => '2026-09-30', 'hasta' => '2026-01-01']))
        ->assertSessionHasErrors('hasta');

    $this->actingAs(usuarioDeReportes('reportes.financiero', 'reportes.exportar_financiero'))
        ->get(route('reportes.financiero.pdf', ['desde' => 'ayer']))
        ->assertSessionHasErrors('desde');
});

/*
|--------------------------------------------------------------------------
| Reporte financiero
|--------------------------------------------------------------------------
*/

test('el PDF financiero trae indicadores, ventas, rentabilidad y cuentas por cobrar', function () {
    pedidoDeReporte('Farmacias del Sur', 1000, 600);
    pedidoDeReporte('Ferretería Andina', 500, 700);
    $conSaldo = pedidoDeReporte('Delizia', 2000, 1200);
    Pago::factory()->create(['pedido_id' => $conSaldo->id, 'monto' => 800]);

    $usuario = usuarioDeReportes('reportes.financiero');
    $informe = app(InformeReportesService::class)->financiero($usuario, '2026-01-01', '2026-10-20');
    $texto = textoDelReporte(app(GeneradorPdf::class)->reporteFinanciero($informe));

    expect($texto)
        ->toContain('REPORTE FINANCIERO')
        ->toContain('USO INTERNO')
        ->toContain('Toda la empresa')
        ->toContain('Puntos clave')
        ->toContain('Bs 3.500,00')          // total vendido
        ->toContain('El Alto')
        ->toContain('Ferretería Andina')
        ->toContain('Bs -200,00')           // el pedido que perdió plata
        ->toContain('Un pedido costó más de lo que dejó')
        ->toContain('1 mes con ventas')
        ->toContain('Cuentas por cobrar')
        ->toContain('Delizia');

    expect($informe['rentabilidad_resumen'])->toMatchArray([
        'con_costo' => 3,
        'con_perdida' => 1,
        'margen' => 1000.0,
    ]);
});

test('un pedido sin consumo registrado no infla el margen', function () {
    pedidoDeReporte('Con costo', 1000, 800);
    pedidoDeReporte('Sin consumo', 5000, null);

    $informe = app(InformeReportesService::class)->financiero(usuarioDeReportes('reportes.financiero'), '2026-01-01', '2026-10-20');

    // Con el pedido sin consumo adentro el margen sería (200 + 5000) / 6000 = 86,7 %.
    expect($informe['rentabilidad_resumen'])->toMatchArray([
        'con_costo' => 1,
        'sin_consumo' => 1,
        'margen' => 200.0,
        'margen_pct' => 20.0,
    ])
        // Se lista igual, al final.
        ->and(end($informe['rentabilidad'])['cliente'])->toBe('Sin consumo')
        ->and(implode(' ', $informe['puntos_clave']))
        ->toContain('El único pedido con costo real registrado dejó un margen de 20,0 %')
        ->toContain('Un pedido terminado no tiene consumo de material registrado');
});

test('los meses sin ventas cuentan como cero y las sucursales llevan su participacion', function () {
    pedidoDeReporte('Uno', 1000, 500);

    $informe = app(InformeReportesService::class)->financiero(usuarioDeReportes('reportes.financiero'), '2026-06-01', '2026-09-30');

    expect(array_column($informe['ventas_por_mes'], 'total'))->toBe([0.0, 0.0, 1000.0, 0.0])
        ->and($informe['ventas_por_mes'][2]['etiqueta'])->toStartWith('Ago')
        ->and($informe['ventas_por_sucursal'][0])->toMatchArray(['sucursal' => 'El Alto', 'participacion' => 100.0]);
});

test('el PDF financiero respeta el alcance por sucursal y lo rotula', function () {
    $otra = Sucursal::factory()->create(['nombre' => 'Sopocachi']);
    pedidoDeReporte('Cliente de El Alto', 1000, 500);
    pedidoDeReporte('Cliente de Sopocachi', 9000, 100, sucursal: $otra);

    $acotado = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $acotado->givePermissionTo(['reportes.financiero', 'reportes.exportar_financiero']);
    Empleado::factory()->recycle($this->area)->create(['user_id' => $acotado->id, 'sucursal_id' => $this->sucursal->id]);

    $texto = textoDelReporte(app(GeneradorPdf::class)->reporteFinanciero(
        app(InformeReportesService::class)->financiero($acotado->fresh(), '2026-01-01', '2026-10-20'),
    ));

    expect($texto)
        ->toContain('Sucursales: El Alto')
        ->toContain('Cliente de El Alto')
        ->not->toContain('Cliente de Sopocachi')
        ->not->toContain('9.000,00');
});

test('el PDF financiero se genera sin ningun dato', function () {
    $texto = textoDelReporte(app(GeneradorPdf::class)->reporteFinanciero(
        app(InformeReportesService::class)->financiero(usuarioDeReportes('reportes.financiero')),
    ));

    expect($texto)
        ->toContain('No hubo ventas aprobadas en el periodo.')
        ->toContain('No hay saldos pendientes de cobro.');
});

/*
|--------------------------------------------------------------------------
| Reporte de producción
|--------------------------------------------------------------------------
*/

test('el PDF de produccion trae cumplimiento, atrasos, etapas y carga', function () {
    pedidoDeReporte('A tiempo', 1000, null);
    pedidoDeReporte('Tarde', 1000, null, ['numero_pedido' => 'PED-20260801-TARDE', 'fecha_entrega_real' => '2026-08-25']);
    $enCurso = pedidoDeReporte('En curso', 1000, null, ['estado' => 'CONTROL_CALIDAD', 'fecha_entrega_real' => null]);

    PedidoSeguimiento::factory()->recycle([$this->sucursal, $this->area, $this->empleado])->create([
        'pedido_detalle_id' => $enCurso->detalles()->first()->id,
        'etapa' => 'CONTROL_CALIDAD',
        'fecha_inicio' => '2026-10-10 08:00:00',
        'fecha_fin' => '2026-10-13 08:00:00',
    ]);
    PedidoSeguimiento::factory()->recycle([$this->sucursal, $this->area, $this->empleado])->create([
        'pedido_detalle_id' => $enCurso->detalles()->first()->id,
        'etapa' => 'ENTREGA',
        'fecha_inicio' => '2026-10-14 08:00:00',
        'fecha_fin' => null,
    ]);

    $informe = app(InformeReportesService::class)->produccion(usuarioDeReportes('reportes.produccion'));
    $texto = textoDelReporte(app(GeneradorPdf::class)->reporteProduccion($informe));

    expect($texto)
        ->toContain('REPORTE DE PRODUCCIÓN')
        ->toContain('AL 20/10/2026')
        ->toContain('Se entregó a tiempo el 50,0 % de los pedidos (1 de 2).')
        ->toContain('PED-20260801-TARDE')
        ->toContain('Control de calidad')
        ->not->toContain('CONTROL_CALIDAD')
        ->toContain('Impresión');

    expect($informe['atrasos'][0])->toMatchArray(['pedido' => 'PED-20260801-TARDE', 'dias' => 5]);
});

test('el PDF de produccion se genera sin ningun dato', function () {
    $texto = textoDelReporte(app(GeneradorPdf::class)->reporteProduccion(
        app(InformeReportesService::class)->produccion(usuarioDeReportes('reportes.produccion')),
    ));

    expect($texto)
        ->toContain('Todavía no hay pedidos entregados con fecha estimada y fecha real.')
        ->toContain('No hay pedidos en curso.');
});
