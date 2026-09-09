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
use App\Services\Pdf\RespuestaPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * App\Services\Pdf\GeneradorPdf — la única clase que genera PDFs.
 *
 * Los documentos se comprueban sobre el PDF DE VERDAD, no sobre un paso
 * intermedio: se genera con `sinComprimir()` y se leen los textos del flujo de
 * la página. Con FPDF eso cuesta milisegundos (es PHP puro, no levanta ningún
 * navegador), así que no hay motivo para testear una maqueta que después
 * alguien podría no imprimir igual. Lo que importa acá es qué dice el
 * documento, qué NO dice, y que quepa en la hoja.
 */
uses(RefreshDatabase::class);

function generador(): GeneradorPdf
{
    return app(GeneradorPdf::class);
}

/**
 * Texto de un PDF, reconstruido desde los operadores `Td`/`Tj` de FPDF.
 *
 * Cada palabra es un `Tj` con su coordenada, así que las de un mismo renglón
 * se pegan tal cual (ya traen su espacio) y entre renglones se mete uno: así
 * "Válida hasta" se puede buscar como frase aunque el documento la haya
 * dibujado palabra por palabra.
 */
function textoDelPdf(RespuestaPdf $pdf): string
{
    preg_match_all(
        '/BT [\d.-]+ ([\d.-]+) Td \((.*?)\) Tj ET/s',
        $pdf->sinComprimir()->contenido(),
        $marcas,
        PREG_SET_ORDER,
    );

    $texto = '';
    $renglon = null;

    foreach ($marcas as $marca) {
        if ($renglon !== null && $marca[1] !== $renglon) {
            $texto .= ' ';
        }

        $renglon = $marca[1];
        // FPDF escapa paréntesis y barras invertidas dentro de las cadenas.
        $texto .= preg_replace('/\\\\([()\\\\])/', '$1', $marca[2]);
    }

    return mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
}

/** Cuántas páginas tiene el documento. */
function paginasDelPdf(RespuestaPdf $pdf): int
{
    return preg_match_all('~/Type /Page[^s]~', $pdf->sinComprimir()->contenido());
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

/** Usuario con los permisos indicados, para las rutas de documentos del panel. */
function usuarioParaDocumentos(string ...$permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/*
|--------------------------------------------------------------------------
| Cotización
|--------------------------------------------------------------------------
*/

test('el PDF de la cotizacion trae membrete, cliente, fechas y totales', function () {
    $cotizacion = cotizacionCompleta();

    $texto = textoDelPdf(generador()->cotizacion($cotizacion));

    expect($texto)
        // Membrete de la empresa, igual en todos los documentos.
        ->toContain(config('sitio.empresa.nombre'))
        ->toContain(config('sitio.empresa.direccion'))
        // Identificación del documento.
        ->toContain($cotizacion->codigo_verificacion)
        ->toContain('COTIZACIÓN')
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
    $texto = textoDelPdf(generador()->cotizacion(cotizacionCompleta()));

    expect($texto)
        ->not->toContain('Costo')
        ->not->toContain('Margen')
        ->not->toContain('Utilidad')
        ->not->toContain('VERDE')
        ->not->toContain('Rentabilidad')
        // Y tampoco los importes internos.
        ->not->toContain('480,00')
        ->not->toContain('260,00');
});

test('el PDF de la cotizacion se llama con su codigo y sale como descarga', function () {
    $cotizacion = cotizacionCompleta();

    $pdf = generador()->cotizacion($cotizacion);

    expect($pdf->nombreArchivo())->toBe('xtrapubli-cotizacion-'.strtolower($cotizacion->codigo_verificacion).'.pdf')
        ->and($pdf->esDescarga())->toBeTrue();
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

    $texto = textoDelPdf(generador()->cotizacionPublica($estimacion));

    expect($texto)
        ->toContain('ESTIMACIÓN REFERENCIAL')
        ->toContain($estimacion->codigo)
        ->toContain('Ana Quispe')
        ->toContain('Fecha de emisión')
        ->toContain('RANGO APROXIMADO')
        ->toContain('No constituye una oferta comercial en firme.')
        // No se disfraza de cotización formal.
        ->not->toContain('COTIZACIÓN ');
});

test('el PDF de una estimacion vencida lo dice', function () {
    $estimacion = CotizacionPublica::factory()->vencida()->create();

    expect(textoDelPdf(generador()->cotizacionPublica($estimacion)))->toContain('VENCIDA');
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

    $texto = textoDelPdf(generador()->notaEntrega($nota->fresh()));

    expect($texto)
        ->toContain('NOTA DE ENTREGA')
        ->toContain($nota->numero_nota)
        ->toContain('Exhibidor de piso')
        ->toContain('Juan Perez')
        ->toContain('Firma y sello')
        // Sin columna de precios: no es el documento donde se discute plata.
        ->not->toContain('P. UNIT.')
        ->not->toContain('SUBTOTAL');
});

test('el PDF de la compra identifica al proveedor y su total', function () {
    $compra = Compra::factory()->create(['estado' => 'PENDIENTE', 'total' => 750]);

    CompraDetalle::factory()->create([
        'compra_id' => $compra->id,
        'cantidad' => 5,
        'precio_unitario' => 150,
        'subtotal' => 750,
    ]);

    $texto = textoDelPdf(generador()->compra($compra->fresh()));

    expect($texto)
        ->toContain('ORDEN DE COMPRA')
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

    $texto = textoDelPdf(generador()->ordenCompraCliente($orden->fresh()));

    expect($texto)
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

    $texto = textoDelPdf(generador()->pedido($pedido->fresh()));

    expect($texto)
        ->toContain('ORDEN DE TRABAJO')
        ->toContain('PED-00042')
        ->toContain('Góndola metálica')
        ->toContain('ELABORACION')
        ->toContain('ETAPA')
        ->not->toContain('P. UNIT.');
});

test('la nota de entrega incrusta la foto de evidencia y aguanta que falte', function () {
    // FPDF lee la imagen del disco, no por HTTP: si `fotoRuta()` devolviera
    // una URL, la celda saldría vacía sin avisar. Y si el archivo se borró a
    // mano, el documento tiene que emitirse igual — una foto perdida no puede
    // impedir que se entregue el trabajo.
    Storage::fake('public');

    $nota = NotaEntrega::factory()->create();

    $ruta = UploadedFile::fake()->image('evidencia.jpg', 240, 160)
        ->store('notas-entrega/fotos', 'public');

    NotaEntregaDetalle::factory()->create([
        'nota_entrega_id' => $nota->id,
        'descripcion' => 'Exhibidor con foto',
        'foto_url' => $ruta,
    ]);

    NotaEntregaDetalle::factory()->create([
        'nota_entrega_id' => $nota->id,
        'descripcion' => 'Exhibidor sin foto',
        'foto_url' => 'notas-entrega/fotos/borrada.jpg',
    ]);

    $pdf = generador()->notaEntrega($nota->fresh())->sinComprimir();

    // Una sola imagen incrustada: la que existe.
    expect(preg_match_all('~/Subtype /Image~', $pdf->contenido()))->toBe(1);
    expect(textoDelPdf($pdf))
        ->toContain('Exhibidor con foto')
        ->toContain('Exhibidor sin foto');
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
        $texto = textoDelPdf($pdf);

        expect($texto)
            ->toContain(config('sitio.empresa.nombre'))
            ->toContain(config('sitio.empresa.telefono_visible'))
            // Pie con numeración de páginas en todos.
            ->toContain('Página 1 de 1');

        expect($pdf->nombreArchivo())->toStartWith('xtrapubli-')->toEndWith('.pdf');
        expect($pdf->contenido())->toStartWith('%PDF-');
    }
});

test('el nombre del archivo aguanta un numero de documento con espacios', function () {
    // Termina en una cabecera Content-Disposition: un número con espacios o
    // acentos rompe la descarga en algunos navegadores.
    $orden = OrdenCompraCliente::factory()->create(['numero_oc' => 'OC 1102/1545 Ñandú']);

    expect(generador()->ordenCompraCliente($orden)->nombreArchivo())
        ->toBe('xtrapubli-orden-compra-oc-11021545-nandu.pdf');
});

test('los acentos y simbolos del castellano se imprimen, no salen como basura', function () {
    // Las fuentes del núcleo de FPDF son cp1252, no UTF-8: si alguien dibuja
    // un texto sin pasarlo por `Documento::t()`, la ñ y las tildes salen como
    // "Ã±". Este test es el que avisa.
    $cotizacion = cotizacionCompleta();

    $cotizacion->detalles->first()->update(['descripcion' => 'Señalética año 2026 — piña']);

    $texto = textoDelPdf(generador()->cotizacion($cotizacion->fresh()));

    expect($texto)
        ->toContain('Señalética año 2026 — piña')
        // Símbolos de la tabla de medidas y del membrete.
        ->toContain('m²')
        ->toContain('×')
        ->toContain('·')
        // La marca del doble encoding: si aparece, algo se dibujó sin convertir.
        ->not->toContain('Ã')
        ->not->toContain('Â');
});

test('una cotizacion larga pagina sola y repite el membrete en cada hoja', function () {
    // FPDF no sabe nada de tablas: si `Documento::tabla()` no midiera cada
    // fila antes de dibujarla, un detalle largo se saldría de la hoja o
    // partiría una fila entre dos páginas.
    $cotizacion = cotizacionCompleta();

    CotizacionDetalle::factory()->count(60)->create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Vinilo de corte para vidriera con laminado de protección UV',
        'cantidad' => 1,
        'precio_unitario' => 100,
        'subtotal' => 100,
    ]);

    $pdf = generador()->cotizacion($cotizacion->fresh());
    $texto = textoDelPdf($pdf);
    $paginas = paginasDelPdf($pdf);

    expect($paginas)->toBeGreaterThan(1);

    // El membrete y la cabecera de la tabla se repiten en todas las hojas.
    expect(substr_count($texto, $cotizacion->codigo_verificacion))->toBeGreaterThanOrEqual($paginas);
    expect(substr_count($texto, 'DESCRIPCIÓN'))->toBe($paginas);
    // Y el pie numera correctamente.
    expect($texto)->toContain('Página '.$paginas.' de '.$paginas);
});

/*
|--------------------------------------------------------------------------
| Entrega
|--------------------------------------------------------------------------
*/

test('la vista previa conserva el nombre del archivo', function () {
    // El nombre es el que ve el usuario al guardar desde el visor del
    // navegador: previsualizar no puede perderlo.
    $pdf = generador()->compra(Compra::factory()->create());
    $nombre = $pdf->nombreArchivo();

    $previa = generador()->previsualizar($pdf);

    expect($previa->esPrevisualizacion())->toBeTrue();
    expect($previa->esDescarga())->toBeFalse();
    expect($previa->nombreArchivo())->toBe($nombre);
});

test('el documento se dibuja una sola vez aunque se pida el contenido varias veces', function () {
    // FPDF acumula páginas en su propio búfer: dibujar dos veces el mismo
    // documento daría uno con las páginas repetidas.
    $pdf = generador()->compra(Compra::factory()->create())->sinComprimir();

    expect($pdf->contenido())->toBe($pdf->contenido());
    expect(paginasDelPdf($pdf))->toBe(1);
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

test('el panel abre el documento como vista previa y no como descarga', function () {
    // El flujo real es revisar la cotización antes de mandársela al cliente:
    // la pestaña nueva la muestra en el visor del navegador en vez de dejar un
    // archivo en el disco que nadie vuelve a abrir.
    $cotizacion = cotizacionCompleta();

    $respuesta = $this->actingAs(usuarioParaDocumentos('cotizaciones.ver'))
        ->get(route('cotizaciones.pdf', $cotizacion))
        ->assertOk();

    expect($respuesta->headers->get('content-type'))->toBe('application/pdf');
    expect($respuesta->headers->get('content-disposition'))->toBe(
        'inline; filename="xtrapubli-cotizacion-'.strtolower($cotizacion->codigo_verificacion).'.pdf"',
    );
    expect($respuesta->getContent())->toStartWith('%PDF-');
});

test('con descargar=1 el mismo documento baja como archivo', function () {
    // Misma ruta, mismo permiso, misma respuesta: solo cambia la cabecera.
    $cotizacion = cotizacionCompleta();

    $respuesta = $this->actingAs(usuarioParaDocumentos('cotizaciones.ver'))
        ->get(route('cotizaciones.pdf', ['cotizacion' => $cotizacion, 'descargar' => 1]))
        ->assertOk();

    expect($respuesta->headers->get('content-disposition'))->toStartWith('attachment; filename=');
});

test('el PDF del pedido respeta el scoping por sucursal', function () {
    // Sin esto, un usuario que no ve el pedido en pantalla podría bajárselo
    // poniendo su id en la URL.
    $pedido = Pedido::factory()->create();

    $ajeno = usuarioParaDocumentos('pedidos.ver');
    // Empleado de otra sucursal: ve el módulo, no este pedido.
    Empleado::factory()->create(['user_id' => $ajeno->id]);

    $this->actingAs($ajeno)->get(route('pedidos.pdf', $pedido))->assertForbidden();

    $global = usuarioParaDocumentos('pedidos.ver', 'pedidos.ver_todas_sucursales');

    $this->actingAs($global)->get(route('pedidos.pdf', $pedido))->assertOk();
});
