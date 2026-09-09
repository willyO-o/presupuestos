<?php

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionPublica;
use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\NotaEntregaDetalle;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;

/**
 * App\Services\Pdf\GeneradorPdf — la única clase que genera PDFs.
 *
 * El contenido se comprueba sobre `getHtml()`, que solo renderiza el Blade:
 * NO levanta Chromium. Un test que de verdad imprimiera el PDF tardaría
 * segundos por caso y ataría la suite a que la máquina tenga Node y el
 * navegador de Puppeteer instalados. Lo que importa acá es qué dice el
 * documento y qué NO dice.
 */
uses(RefreshDatabase::class);

function generador(): GeneradorPdf
{
    return app(GeneradorPdf::class);
}

/** Cotización con cliente, vendedor y dos líneas con medidas y precio. */
function cotizacionCompleta(): Cotizacion
{
    $cotizacion = Cotizacion::factory()->create([
        'cliente_id' => Cliente::factory()->create(['razon_social' => 'Delizia S.A.', 'nit' => '1023456789'])->id,
        'subtotal' => 1000,
        'descuento' => 100,
        'iva' => 117,
        'instalacion' => 200,
        'total' => 1217,
        'observaciones' => 'Entrega en dos tandas.',
        // Datos internos de rentabilidad: cargados, pero NO deben imprimirse.
        'costo_base' => 400,
        'costo_ajustado' => 480,
        'utilidad_real' => 260,
        'estado_margen' => 'VERDE',
    ]);

    CotizacionDetalle::factory()->count(2)->create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Banner lona frontlight',
        'ancho' => 2,
        'alto' => 1.5,
        'area_m2' => 3,
        'cantidad' => 2,
        'precio_unitario' => 250,
        'subtotal' => 500,
    ]);

    return $cotizacion->fresh();
}

/*
|--------------------------------------------------------------------------
| Cotización
|--------------------------------------------------------------------------
*/

test('el PDF de la cotizacion trae membrete, cliente, fechas y totales', function () {
    $cotizacion = cotizacionCompleta();

    $html = generador()->cotizacion($cotizacion)->getHtml();

    expect($html)
        // Membrete de la empresa, igual en todos los documentos.
        ->toContain(config('sitio.empresa.nombre'))
        ->toContain(config('sitio.empresa.direccion'))
        // Identificación del documento.
        ->toContain($cotizacion->codigo_verificacion)
        ->toContain('Cotización')
        // Cliente y fechas.
        ->toContain('Delizia S.A.')
        ->toContain('1023456789')
        ->toContain('Fecha de emisión')
        ->toContain('Válida hasta')
        ->toContain($cotizacion->fecha->translatedFormat('d \d\e F \d\e Y'))
        // Detalle y totales.
        ->toContain('Banner lona frontlight')
        ->toContain('1.217,00');
});

test('el PDF de la cotizacion NO filtra costos ni rentabilidad', function () {
    // El documento sale por correo al cliente. El costo, el margen y el
    // semáforo son información interna de la empresa (ver el docblock de
    // Cotizacion::ESTADOS_MARGEN): que se cuelen sería regalarle la
    // estructura de costos a quien compra.
    $html = generador()->cotizacion(cotizacionCompleta())->getHtml();

    expect($html)
        ->not->toContain('Costo')
        ->not->toContain('Margen')
        ->not->toContain('Utilidad')
        ->not->toContain('VERDE')
        ->not->toContain('Rentabilidad')
        // Y tampoco los importes internos.
        ->not->toContain('480,00')
        ->not->toContain('260,00');
});

test('el PDF de la cotizacion se llama con su codigo', function () {
    $cotizacion = cotizacionCompleta();

    $pdf = generador()->cotizacion($cotizacion);

    expect($pdf->downloadName)->toBe('xtrapubli-cotizacion-'.strtolower($cotizacion->codigo_verificacion).'.pdf')
        ->and($pdf->isDownload())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Estimación del cotizador web
|--------------------------------------------------------------------------
*/

test('el PDF de la estimacion web se rotula como estimacion y avisa que no es oferta', function () {
    // Comparte la forma con la cotización formal para que el cliente
    // reconozca el documento, pero no puede hacerse pasar por un presupuesto:
    // el precio lo calculó el motor sin que lo revisara un vendedor.
    $estimacion = CotizacionPublica::factory()->create(['nombre' => 'Ana Quispe']);

    $html = generador()->cotizacionPublica($estimacion)->getHtml();

    expect($html)
        ->toContain('Estimación referencial')
        ->toContain($estimacion->codigo)
        ->toContain('Ana Quispe')
        ->toContain('Fecha de emisión')
        ->toContain('Rango aproximado')
        ->toContain('No constituye una oferta comercial en firme.')
        // No se disfraza de cotización formal.
        ->not->toContain('<p class="doc-tipo">Cotización</p>');
});

test('el PDF de una estimacion vencida lo dice', function () {
    $estimacion = CotizacionPublica::factory()->vencida()->create();

    expect(generador()->cotizacionPublica($estimacion)->getHtml())->toContain('Vencida');
});

/*
|--------------------------------------------------------------------------
| Resto de documentos
|--------------------------------------------------------------------------
*/

test('el PDF de la nota de entrega no lleva precios y si dos firmas', function () {
    // Va al cliente al momento de recibir: lo que se comprueba es QUÉ llegó,
    // no cuánto costó. Y sin la firma de quien recibe no prueba nada.
    $nota = NotaEntrega::factory()->create(['recibido_por' => 'Juan Perez', 'cargo_receptor' => 'Almacén']);

    NotaEntregaDetalle::factory()->create([
        'nota_entrega_id' => $nota->id,
        'descripcion' => 'Exhibidor de piso',
        'cantidad_entregada' => 3,
    ]);

    $html = generador()->notaEntrega($nota->fresh())->getHtml();

    expect($html)
        ->toContain('Nota de entrega')
        ->toContain($nota->numero_nota)
        ->toContain('Exhibidor de piso')
        ->toContain('Juan Perez')
        ->toContain('Firma y sello')
        // Sin columna de precios: no es el documento donde se discute plata.
        ->not->toContain('P. unit.')
        ->not->toContain('Subtotal');
});

test('el PDF de la compra identifica al proveedor y su total', function () {
    $compra = Compra::factory()->create(['estado' => 'PENDIENTE', 'total' => 750]);

    CompraDetalle::factory()->create([
        'compra_id' => $compra->id,
        'cantidad' => 5,
        'precio_unitario' => 150,
        'subtotal' => 750,
    ]);

    $html = generador()->compra($compra->fresh())->getHtml();

    expect($html)
        ->toContain('Orden de compra')
        ->toContain($compra->proveedor->nombre)
        ->toContain('750,00')
        // Una compra pendiente avisa que todavía no tocó el inventario.
        ->toContain('no impactó el inventario');
});

test('el PDF de la orden de compra del cliente destaca si difiere del pedido', function () {
    // Es la única razón por la que este documento existe: una diferencia
    // entre la OC y el pedido es lo que frena una facturación.
    $pedido = Pedido::factory()->create(['total' => 1000]);

    $orden = OrdenCompraCliente::factory()->create([
        'pedido_id' => $pedido->id,
        'monto_total' => 1200,
        'numero_oc' => 'OC-11021545',
    ]);

    $html = generador()->ordenCompraCliente($orden->fresh())->getHtml();

    expect($html)
        ->toContain('OC-11021545')
        ->toContain('Diferencia')
        ->toContain('no coincide con el total del pedido');
});

test('el PDF del pedido lleva etapas y medidas, no precios por linea', function () {
    // Va al taller: ahí hace falta qué fabricar y en qué etapa está, no el
    // precio de venta de cada pieza.
    $pedido = Pedido::factory()->create(['numero_pedido' => 'PED-00042', 'total' => 3000]);

    PedidoDetalle::factory()->create([
        'pedido_id' => $pedido->id,
        'descripcion' => 'Góndola metálica',
        'ancho' => 1.2,
        'alto' => 2,
        'cantidad' => 4,
        'estado_item' => 'ELABORACION',
    ]);

    $html = generador()->pedido($pedido->fresh())->getHtml();

    expect($html)
        ->toContain('Orden de trabajo')
        ->toContain('PED-00042')
        ->toContain('Góndola metálica')
        ->toContain('ELABORACION')
        ->toContain('Etapa')
        ->not->toContain('P. unit.');
});

/*
|--------------------------------------------------------------------------
| Configuración común
|--------------------------------------------------------------------------
*/

test('todos los documentos comparten formato, pie numerado y membrete', function () {
    // La razón de que exista una sola clase: si el membrete o el pie cambian,
    // cambian en un sitio. Este test se cae si alguien arma un PDF por su
    // cuenta con otro formato.
    $documentos = [
        generador()->cotizacion(cotizacionCompleta()),
        generador()->cotizacionPublica(CotizacionPublica::factory()->create()),
        generador()->notaEntrega(NotaEntrega::factory()->create()),
        generador()->compra(Compra::factory()->create()),
        generador()->pedido(Pedido::factory()->create()),
        generador()->ordenCompraCliente(OrdenCompraCliente::factory()->create()),
    ];

    foreach ($documentos as $pdf) {
        expect($pdf->getHtml())
            ->toContain(config('sitio.empresa.nombre'))
            ->toContain(config('sitio.empresa.telefono_visible'))
            // El CSS va incrustado: Chromium imprime sin salir a la red.
            ->toContain('.doc-cabecera')
            ->not->toContain('<link rel="stylesheet"');

        // Pie con numeración de páginas en todos.
        expect($pdf->getFooterHtml())
            ->toContain('class="pageNumber"')
            ->toContain('class="totalPages"');

        expect($pdf->downloadName)->toStartWith('xtrapubli-')->toEndWith('.pdf');
    }
});

test('la vista previa no pisa el nombre del archivo', function () {
    // Trampa de la libreria: `PdfBuilder::inline()` SIN argumento hace
    // `name('')` y el documento pasa a llamarse ".pdf". Ese nombre es
    // justamente el que ve el usuario al guardar desde el visor del navegador,
    // asi que previsualizar sin cuidado arruina lo unico que se gana.
    $pdf = generador()->compra(Compra::factory()->create());
    $nombre = $pdf->downloadName;

    $previa = generador()->previsualizar($pdf);

    expect($previa->isInline())->toBeTrue();
    expect($previa->isDownload())->toBeFalse();
    expect($previa->downloadName)->toBe($nombre);
});

test('el nombre del archivo aguanta un numero de documento con espacios', function () {
    // Termina en una cabecera Content-Disposition: un número con espacios o
    // acentos rompe la descarga en algunos navegadores.
    $orden = OrdenCompraCliente::factory()->create(['numero_oc' => 'OC 1102/1545 Ñandú']);

    expect(generador()->ordenCompraCliente($orden)->downloadName)
        ->toBe('xtrapubli-orden-compra-oc-11021545-nandu.pdf');
});

/*
|--------------------------------------------------------------------------
| Rutas del panel
|--------------------------------------------------------------------------
*/

test('un invitado no descarga documentos del panel', function () {
    $cotizacion = Cotizacion::factory()->create();

    $this->get(route('cotizaciones.pdf', $cotizacion))->assertRedirect(route('login'));
});

test('sin el permiso de ver no se descarga el PDF', function () {
    // El PDF no puede ser la puerta trasera de lo que la pantalla no muestra.
    $cotizacion = Cotizacion::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('cotizaciones.pdf', $cotizacion))
        ->assertForbidden();
});

test('con permiso se descarga el PDF de la cotizacion', function () {
    Pdf::fake();

    Permission::findOrCreate('cotizaciones.ver', 'web');
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('cotizaciones.ver');

    $cotizacion = cotizacionCompleta();

    $this->actingAs($usuario)->get(route('cotizaciones.pdf', $cotizacion))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.cotizacion'
        && str_contains($pdf->getHtml(), $cotizacion->codigo_verificacion));
});

test('el panel abre el documento como vista previa y no como descarga', function () {
    // El flujo real es revisar la cotizacion antes de mandarsela al cliente:
    // la pestaña nueva la muestra en el visor del navegador en vez de dejar un
    // archivo en el disco que nadie vuelve a abrir.
    Pdf::fake();

    Permission::findOrCreate('cotizaciones.ver', 'web');
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('cotizaciones.ver');

    $cotizacion = cotizacionCompleta();

    $this->actingAs($usuario)->get(route('cotizaciones.pdf', $cotizacion))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => $pdf->isInline()
        && $pdf->downloadName === 'xtrapubli-cotizacion-'.strtolower($cotizacion->codigo_verificacion).'.pdf');
});

test('con descargar=1 el mismo documento baja como archivo', function () {
    // Misma ruta, mismo permiso, misma respuesta: solo cambia la cabecera.
    Pdf::fake();

    Permission::findOrCreate('cotizaciones.ver', 'web');
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('cotizaciones.ver');

    $cotizacion = cotizacionCompleta();

    $this->actingAs($usuario)
        ->get(route('cotizaciones.pdf', ['cotizacion' => $cotizacion, 'descargar' => 1]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => $pdf->isDownload()
        && $pdf->downloadName === 'xtrapubli-cotizacion-'.strtolower($cotizacion->codigo_verificacion).'.pdf');
});

test('el PDF del pedido respeta el scoping por sucursal', function () {
    // Sin esto, un usuario que no ve el pedido en pantalla podría bajárselo
    // poniendo su id en la URL.
    Pdf::fake();

    collect(['pedidos.ver', 'pedidos.ver_todas_sucursales'])
        ->each(fn (string $permiso) => Permission::findOrCreate($permiso, 'web'));

    $pedido = Pedido::factory()->create();

    $ajeno = User::factory()->create();
    $ajeno->givePermissionTo('pedidos.ver');
    // Empleado de otra sucursal: ve el módulo, no este pedido.
    Empleado::factory()->create(['user_id' => $ajeno->id]);

    $this->actingAs($ajeno)->get(route('pedidos.pdf', $pedido))->assertForbidden();

    $global = User::factory()->create();
    $global->givePermissionTo(['pedidos.ver', 'pedidos.ver_todas_sucursales']);

    $this->actingAs($global)->get(route('pedidos.pdf', $pedido))->assertOk();
});
