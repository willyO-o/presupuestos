<?php

namespace App\Services\Pdf;

use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\CotizacionPublica;
use App\Models\NotaEntrega;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use App\Services\Pdf\Documentos\CompraPdf;
use App\Services\Pdf\Documentos\CotizacionPdf;
use App\Services\Pdf\Documentos\CotizacionPublicaPdf;
use App\Services\Pdf\Documentos\Documento;
use App\Services\Pdf\Documentos\NotaEntregaPdf;
use App\Services\Pdf\Documentos\OrdenCompraClientePdf;
use App\Services\Pdf\Documentos\PedidoPdf;
use Illuminate\Support\Str;

/**
 * Único lugar donde el sistema genera PDFs.
 *
 * Todos los documentos del negocio salen de acá —cotización, orden de trabajo,
 * nota de entrega, compra a proveedor, orden de compra del cliente y la
 * estimación del cotizador web— y todos se arman igual: un `Documento` de
 * `App\Services\Pdf\Documentos`, que hereda membrete, pie numerado y ladrillos
 * de maqueta de la clase base. Agregar un documento nuevo es agregar un método
 * público acá y una clase allá; nunca instanciar FPDF desde un controlador, o
 * el membrete deja de estar en un solo sitio.
 *
 * Cada método devuelve una `RespuestaPdf` ya nombrada, no un archivo. Así el
 * que llama decide qué hacer con ella —descargarla (lo de por defecto),
 * `previsualizar()` para verla en el navegador, `guardar()` para adjuntarla a
 * un correo— sin duplicar la construcción del documento.
 *
 * **Motor**: `setasign/fpdf`, PHP puro. Se eligió sobre un motor HTML
 * (Browsershot/Chromium) porque el sistema se despliega en hosting compartido,
 * donde no hay Node ni se puede ejecutar un navegador headless: FPDF funciona
 * en cualquier servidor con PHP, sin binarios externos y con muy poca memoria.
 * El costo es que la maqueta se programa en vez de escribirse en CSS, y por eso
 * vive concentrada en `Documentos\Documento`.
 *
 * **Cuidado con lo público**: `cotizacionPublica()` la dispara un visitante
 * anónimo. Es barata comparada con levantar un navegador, pero sigue armando
 * un documento entero por petición, así que la ruta lleva rate limiter por IP
 * y tope de emisiones por fila (ver CotizadorPublicoController::documento).
 */
class GeneradorPdf
{
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
    public function cotizacion(Cotizacion $cotizacion): RespuestaPdf
    {
        $cotizacion->loadMissing([
            'cliente',
            'empleado',
            'sucursal',
            'detalles.producto:id,nombre',
        ]);

        return $this->entregar(
            new CotizacionPdf($cotizacion, 'Cotización', $this->empresa(), now()),
            $this->nombre('cotizacion', $cotizacion->codigo_verificacion),
        );
    }

    /**
     * Orden de trabajo: lo que baja a producción. Lleva medidas y etapas, no
     * precios de venta línea por línea.
     */
    public function pedido(Pedido $pedido): RespuestaPdf
    {
        $pedido->loadMissing([
            'cotizacion.cliente',
            'cotizacion.empleado',
            'detalles',
        ]);

        return $this->entregar(
            new PedidoPdf($pedido, 'Orden de trabajo', $this->empresa(), now()),
            $this->nombre('orden-trabajo', $pedido->numero_pedido),
        );
    }

    /**
     * Nota de entrega (la "nota de venta" que firma quien recibe). Cierra la
     * entrega, por eso termina en dos firmas.
     */
    public function notaEntrega(NotaEntrega $nota): RespuestaPdf
    {
        $nota->loadMissing([
            'pedido.cotizacion.cliente',
            'empleado',
            'detalles',
        ]);

        return $this->entregar(
            new NotaEntregaPdf($nota, 'Nota de entrega', $this->empresa(), now()),
            $this->nombre('nota-entrega', $nota->numero_nota),
        );
    }

    /**
     * Acuse de la orden de compra que emitió el cliente. No reemplaza al PDF
     * que él mandó (`orden_compra_cliente.archivo_pdf`): deja constancia de
     * cómo quedó registrada y si coteja con el pedido.
     */
    public function ordenCompraCliente(OrdenCompraCliente $orden): RespuestaPdf
    {
        $orden->loadMissing([
            'pedido.cotizacion.cliente',
            'pedido.detalles',
        ]);

        return $this->entregar(
            new OrdenCompraClientePdf($orden, 'Orden de compra del cliente', $this->empresa(), now()),
            $this->nombre('orden-compra', $orden->numero_oc),
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
    public function compra(Compra $compra): RespuestaPdf
    {
        $compra->loadMissing([
            'proveedor',
            'empleado',
            'detalles.material:id,nombre,presentacion,unidad_medida',
        ]);

        return $this->entregar(
            new CompraPdf($compra, 'Orden de compra', $this->empresa(), now()),
            $this->nombre('compra', (string) $compra->id),
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
    public function cotizacionPublica(CotizacionPublica $estimacion): RespuestaPdf
    {
        return $this->entregar(
            new CotizacionPublicaPdf(
                $estimacion,
                $estimacion->estaVigente(),
                'Estimación referencial',
                $this->empresa(),
                now(),
            ),
            $this->nombre('estimacion', $estimacion->codigo),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Entrega
    |--------------------------------------------------------------------------
    */

    /**
     * Convierte un documento en vista previa: el navegador lo MUESTRA en su
     * visor en vez de bajarlo al disco.
     *
     * Vive acá y no en el controlador porque es una decisión sobre el
     * documento, no sobre la ruta; y los métodos de arriba salen como descarga
     * por defecto, que es el comportamiento seguro si la respuesta termina en
     * otro lado (el cotizador público, un correo).
     */
    public function previsualizar(RespuestaPdf $pdf): RespuestaPdf
    {
        return $pdf->previsualizar();
    }

    /**
     * @return array<string, mixed>
     */
    private function empresa(): array
    {
        return config('sitio.empresa');
    }

    private function entregar(Documento $documento, string $archivo): RespuestaPdf
    {
        return (new RespuestaPdf($documento, $archivo))->descargar();
    }

    /**
     * Nombre del archivo descargado: `xtrapubli-cotizacion-cot-20260909-a1b2c.pdf`.
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
