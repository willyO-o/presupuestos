<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\NotaEntrega;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use App\Services\Pdf\GeneradorPdf;
use App\Services\Pdf\RespuestaPdf;
use Illuminate\Http\Request;

/**
 * Descarga de los documentos PDF del panel.
 *
 * Un solo controlador para los cinco documentos internos, en vez de un método
 * `pdf()` repartido por CotizacionController, PedidoController y compañía: la
 * generación ya vive centralizada en App\Services\Pdf\GeneradorPdf y tener la
 * puerta de entrada igual de junta hace evidente qué documentos existen y con
 * qué permiso se descarga cada uno.
 *
 * Cada acción solo autoriza y delega. Toda la maqueta está en el servicio y en
 * `resources/views/pdf/`; acá no se decide nada sobre el contenido.
 *
 * Los permisos son los MISMOS que para ver el documento en pantalla (`.ver`),
 * no unos nuevos: quien puede leer una cotización puede llevársela en PDF —
 * inventar un permiso `cotizaciones.pdf` aparte solo daría la ilusión de un
 * control que la pantalla ya no aplica.
 *
 * **Entrega**: por defecto el documento sale como VISTA PREVIA, para que el
 * panel lo abra en una pestaña nueva y se revise antes de mandarlo al cliente
 * (`?descargar=1` fuerza la descarga al disco). Ver `entregar()`.
 */
class DocumentoPdfController extends Controller
{
    public function __construct(
        private readonly GeneradorPdf $generador,
    ) {}

    public function cotizacion(Request $request, Cotizacion $cotizacion): RespuestaPdf
    {
        return $this->entregar($request, $this->generador->cotizacion($cotizacion));
    }

    /**
     * El PDF respeta el mismo scoping por sucursal que la pantalla del pedido
     * (`PedidoController::puedeVer`): sin esto, un usuario sin
     * `pedidos.ver_todas_sucursales` no vería el pedido en pantalla pero
     * podría descargarlo poniendo su id en la URL.
     */
    public function pedido(Request $request, Pedido $pedido): RespuestaPdf
    {
        $pedido->loadMissing('cotizacion');

        $usuario = $request->user();

        abort_unless(
            $usuario->hasRole('super-admin')
                || $usuario->can('pedidos.ver_todas_sucursales')
                || $usuario->empleado?->sucursal_id === $pedido->cotizacion->sucursal_id,
            403,
        );

        return $this->entregar($request, $this->generador->pedido($pedido));
    }

    public function notaEntrega(Request $request, NotaEntrega $notaEntrega): RespuestaPdf
    {
        return $this->entregar($request, $this->generador->notaEntrega($notaEntrega));
    }

    public function compra(Request $request, Compra $compra): RespuestaPdf
    {
        return $this->entregar($request, $this->generador->compra($compra));
    }

    public function ordenCompraCliente(Request $request, OrdenCompraCliente $ordenCompra): RespuestaPdf
    {
        return $this->entregar($request, $this->generador->ordenCompraCliente($ordenCompra));
    }

    /**
     * Decide cómo se entrega el documento.
     *
     * Vista previa (`inline`) por defecto: el flujo real del panel es mirar la
     * cotización antes de mandarla, y bajar un archivo para eso deja el disco
     * lleno de PDFs que nadie vuelve a abrir. Quien de verdad quiere el archivo
     * lo guarda desde el visor del navegador o pide `?descargar=1`.
     *
     * La descarga sigue existiendo como parámetro —y no como ruta aparte—
     * porque es la MISMA respuesta con otra cabecera: duplicar la ruta
     * duplicaría también el permiso y el scoping por sucursal del pedido.
     */
    private function entregar(Request $request, RespuestaPdf $pdf): RespuestaPdf
    {
        return $request->boolean('descargar')
            ? $pdf
            : $this->generador->previsualizar($pdf);
    }
}
