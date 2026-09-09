<?php

namespace App\Services\Pdf;

use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\CotizacionPublica;
use App\Models\NotaEntrega;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Único lugar donde el sistema genera PDFs.
 *
 * Todos los documentos del negocio salen de acá —cotización, orden de trabajo,
 * nota de entrega, compra a proveedor, orden de compra del cliente y la
 * estimación del cotizador web— y todos pasan por el mismo `documento()`, que
 * fija formato, márgenes, pie con numeración y metadatos. Agregar un
 * documento nuevo es agregar un método público y una vista en
 * `resources/views/pdf/`, nunca llamar a `Pdf::` desde un controlador: si el
 * membrete o el pie cambian, tienen que cambiar en un solo sitio.
 *
 * Cada método devuelve un `PdfBuilder` ya nombrado y configurado, no un
 * archivo. Así el que llama decide qué hacer con él —`->download()` (el valor
 * por defecto), `->inline()` para verlo en el navegador, `->save($ruta)` para
 * adjuntarlo a un correo— sin duplicar la construcción del documento.
 *
 * **Motor**: `spatie/laravel-pdf` sobre Browsershot, que imprime con Chromium
 * headless. Eso exige Node y el navegador de Puppeteer EN EL SERVIDOR (ver
 * README). A cambio, la maqueta es HTML/CSS real —flexbox, tipografía,
 * saltos de página controlados— y no el subconjunto que entiende un motor PHP.
 * Si un despliegue no puede tener Node, `config/laravel-pdf.php` acepta el
 * driver `dompdf` sin tocar esta clase.
 *
 * **Cuidado con lo público**: `cotizacionPublica()` la dispara un visitante
 * anónimo, y cada llamada levanta un Chromium. Es la operación más cara que
 * expone el sitio, por eso la ruta lleva rate limiter por IP y tope de
 * emisiones por fila (ver CotizadorPublicoController::documento).
 */
class GeneradorPdf
{
    /**
     * CSS de los documentos, leído una sola vez por proceso.
     *
     * Un lote de 50 PDFs no tiene por qué tocar el disco 50 veces para leer
     * el mismo archivo.
     */
    private static ?string $estilos = null;

    /*
    |--------------------------------------------------------------------------
    | Ventas
    |--------------------------------------------------------------------------
    */

    /**
     * Presupuesto formal que se le envía al cliente.
     *
     * Imprime SOLO lo que el cliente puede ver. El costo, el margen, el factor
     * de complejidad y el semáforo de rentabilidad son información interna
     * (ver el docblock de `Cotizacion::ESTADOS_MARGEN`) y no entran en el
     * documento por más que estén cargados en el modelo.
     */
    public function cotizacion(Cotizacion $cotizacion): PdfBuilder
    {
        $cotizacion->loadMissing([
            'cliente',
            'empleado',
            'sucursal',
            'detalles.producto:id,nombre',
        ]);

        return $this->documento(
            vista: 'cotizacion',
            titulo: 'Cotización',
            archivo: $this->nombre('cotizacion', $cotizacion->codigo_verificacion),
            datos: ['cotizacion' => $cotizacion],
        );
    }

    /**
     * Orden de trabajo: lo que baja a producción. Lleva medidas y etapas, no
     * precios de venta línea por línea.
     */
    public function pedido(Pedido $pedido): PdfBuilder
    {
        $pedido->loadMissing([
            'cotizacion.cliente',
            'cotizacion.empleado',
            'detalles',
        ]);

        return $this->documento(
            vista: 'pedido',
            titulo: 'Orden de trabajo',
            archivo: $this->nombre('orden-trabajo', $pedido->numero_pedido),
            datos: ['pedido' => $pedido],
        );
    }

    /**
     * Nota de entrega (la "nota de venta" que firma quien recibe). Cierra la
     * entrega, por eso termina en dos firmas.
     */
    public function notaEntrega(NotaEntrega $nota): PdfBuilder
    {
        $nota->loadMissing([
            'pedido.cotizacion.cliente',
            'empleado',
            'detalles',
        ]);

        return $this->documento(
            vista: 'nota-entrega',
            titulo: 'Nota de entrega',
            archivo: $this->nombre('nota-entrega', $nota->numero_nota),
            datos: ['nota' => $nota],
        );
    }

    /**
     * Acuse de la orden de compra que emitió el cliente. No reemplaza al PDF
     * que él mandó (`orden_compra_cliente.archivo_pdf`): deja constancia de
     * cómo quedó registrada y si coteja con el pedido.
     */
    public function ordenCompraCliente(OrdenCompraCliente $orden): PdfBuilder
    {
        $orden->loadMissing([
            'pedido.cotizacion.cliente',
            'pedido.detalles',
        ]);

        return $this->documento(
            vista: 'orden-compra-cliente',
            titulo: 'Orden de compra del cliente',
            archivo: $this->nombre('orden-compra', $orden->numero_oc),
            datos: ['orden' => $orden],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Compras / inventario
    |--------------------------------------------------------------------------
    */

    /**
     * Orden de compra a proveedor: el documento que se le manda para pedir el
     * material, y el respaldo del ingreso a inventario cuando ya se aprobó.
     */
    public function compra(Compra $compra): PdfBuilder
    {
        $compra->loadMissing([
            'proveedor',
            'empleado',
            'detalles.material:id,nombre,presentacion,unidad_medida',
        ]);

        return $this->documento(
            vista: 'compra',
            titulo: 'Orden de compra',
            archivo: $this->nombre('compra', (string) $compra->id),
            datos: ['compra' => $compra],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sitio público
    |--------------------------------------------------------------------------
    */

    /**
     * Estimación que un visitante armó solo en `/cotizador`.
     *
     * Misma forma que la cotización formal para que el cliente reconozca el
     * documento, pero rotulada como ESTIMACIÓN y con el aviso de que no es una
     * oferta en firme: el precio se calculó sin revisión humana y caduca.
     */
    public function cotizacionPublica(CotizacionPublica $estimacion): PdfBuilder
    {
        return $this->documento(
            vista: 'cotizacion-publica',
            titulo: 'Estimación referencial',
            archivo: $this->nombre('estimacion', $estimacion->codigo),
            datos: [
                'estimacion' => $estimacion,
                'vigente' => $estimacion->estaVigente(),
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Entrega
    |--------------------------------------------------------------------------
    */

    /**
     * Devuelve el documento como vista previa: el navegador lo MUESTRA en su
     * visor (`Content-Disposition: inline`) en vez de bajarlo al disco.
     *
     * Es lo que usa el panel para abrir el PDF en una pestaña nueva: el usuario
     * revisa el documento antes de mandárselo al cliente, y si lo quiere en
     * disco lo guarda desde el propio visor. Los métodos de arriba siguen
     * saliendo con `->download()` por defecto —el comportamiento seguro si el
     * builder termina en otro lado, como el cotizador público—, así que la
     * vista previa es una decisión explícita de quien entrega la respuesta.
     *
     * El nombre se le pasa a `inline()` a propósito: sin argumento,
     * `PdfBuilder::inline()` hace `name('')` y el archivo pasa a llamarse
     * ".pdf". Ese nombre es justamente el que ve el usuario al guardar desde el
     * visor, así que perderlo arruina lo único que se gana previsualizando.
     */
    public function previsualizar(PdfBuilder $pdf): PdfBuilder
    {
        return $pdf->inline($pdf->downloadName);
    }

    /*
    |--------------------------------------------------------------------------
    | Construcción común
    |--------------------------------------------------------------------------
    */

    /**
     * Arma el documento con la configuración compartida por todos: A4 vertical,
     * márgenes que dejan sitio al pie, numeración de páginas y metadatos.
     *
     * El margen inferior (18 mm) es más grande que el superior a propósito:
     * Chromium dibuja el pie DENTRO del margen, y con menos espacio se
     * superpone con la última fila de la tabla.
     *
     * @param  string  $vista  Nombre bajo `resources/views/pdf/`.
     * @param  string  $titulo  Rótulo del documento y metadato del PDF.
     * @param  string  $archivo  Nombre del archivo que descarga el usuario.
     * @param  array<string, mixed>  $datos
     */
    private function documento(string $vista, string $titulo, string $archivo, array $datos): PdfBuilder
    {
        $empresa = config('sitio.empresa');

        return Pdf::view("pdf.{$vista}", [
            ...$datos,
            'empresa' => $empresa,
            'titulo' => $titulo,
            'estilos' => $this->estilos(),
            'generadoEn' => now(),
        ])
            ->format(Format::A4)
            ->portrait()
            // top, right, bottom, left (mm).
            ->margins(14, 12, 18, 12)
            ->footerView('pdf.pie', [
                'empresa' => $empresa,
                'titulo' => $titulo,
            ])
            ->meta(
                title: $titulo,
                author: $empresa['nombre'],
                creator: $empresa['nombre'],
            )
            ->name($archivo)
            ->download();
    }

    /**
     * CSS incrustado en cada documento, sin comentarios.
     *
     * Se lee del fuente (`resources/css/pdf.css`) y no del compilado de Vite:
     * Browsershot le pasa a Chromium el HTML como cadena, así que un <link> a
     * un asset obligaría al servidor a hacerse una petición HTTP a sí mismo —
     * frágil detrás de un proxy e imposible en los tests. Incrustado, el
     * documento se genera sin red y sin `npm run build`.
     *
     * Los comentarios se quitan porque el archivo está muy comentado (explica
     * por qué es CSS plano) y ese texto viajaría dentro de cada PDF: pesa de
     * más y, peor, aparece en el documento como texto buscable. La compresión
     * es deliberadamente conservadora —solo comentarios y espacios de sobra—
     * para no arriesgar la maqueta por unos kilobytes.
     */
    private function estilos(): string
    {
        return self::$estilos ??= $this->comprimir(file_get_contents(resource_path('css/pdf.css')));
    }

    /**
     * Quita los comentarios `/* … *\/` y colapsa los espacios repetidos.
     */
    private function comprimir(string $css): string
    {
        $sinComentarios = preg_replace('#/\*.*?\*/#s', '', $css);

        return trim(preg_replace('/\s+/', ' ', $sinComentarios));
    }

    /**
     * Nombre del archivo descargado: `xtrapubli-cotizacion-COT-20260909-A1B2C.pdf`.
     *
     * El identificador se normaliza porque termina en una cabecera
     * `Content-Disposition`: un número de documento con espacios, acentos o
     * comillas rompe la descarga en algunos navegadores.
     */
    private function nombre(string $tipo, string $identificador): string
    {
        $identificador = Str::slug($identificador) ?: 'sin-numero';

        return "xtrapubli-{$tipo}-{$identificador}.pdf";
    }
}
