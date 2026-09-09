<?php

namespace App\Services\Pdf;

use App\Services\Pdf\Documentos\Documento;
use Illuminate\Contracts\Support\Responsable;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un documento listo, con su nombre de archivo y su forma de entrega.
 *
 * `GeneradorPdf` devuelve esto y no el binario: así el que llama decide qué
 * hacer —descargarlo, previsualizarlo en el navegador, guardarlo en disco o
 * adjuntarlo a un correo— sin que la construcción del documento se duplique.
 *
 * Implementa `Responsable`, así que un controlador puede devolverlo tal cual
 * y Laravel se encarga de las cabeceras.
 *
 * El PDF se dibuja UNA sola vez, la primera que alguien pide su contenido, y
 * queda memorizado: `FPDF` acumula páginas en su propio búfer, y dibujar dos
 * veces el mismo documento daría uno con las páginas repetidas.
 */
final class RespuestaPdf implements Responsable
{
    private ?string $renderizado = null;

    private string $disposicion = 'attachment';

    private bool $comprimir = true;

    public function __construct(
        private readonly Documento $documento,
        private readonly string $archivo,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Forma de entrega
    |--------------------------------------------------------------------------
    */

    /**
     * El navegador MUESTRA el documento en su visor en vez de bajarlo.
     *
     * Es lo que usa el panel para abrir el PDF en una pestaña nueva: el
     * usuario revisa la cotización antes de mandársela al cliente y, si la
     * quiere en disco, la guarda desde el propio visor.
     */
    public function previsualizar(): self
    {
        $this->disposicion = 'inline';

        return $this;
    }

    /** El navegador baja el archivo. Es el comportamiento por defecto. */
    public function descargar(): self
    {
        $this->disposicion = 'attachment';

        return $this;
    }

    /**
     * Deja el flujo de la página sin comprimir.
     *
     * Sirve para inspeccionar el PDF: con la compresión de zlib activa el
     * contenido es binario opaco, y sin ella los textos se leen dentro del
     * archivo. Los tests lo usan para comprobar qué dice —y qué NO dice— un
     * documento sin depender de un parser de PDF.
     *
     * Revienta si el documento YA se dibujó comprimido: la compresión se
     * aplica al cerrar el PDF y no se puede deshacer, así que aceptarlo en
     * silencio devolvería un binario opaco al que pidió leerlo — un test que
     * no encuentra nada y parece que el documento estuviera vacío.
     */
    public function sinComprimir(): self
    {
        if ($this->renderizado !== null && $this->comprimir) {
            throw new LogicException(
                'El PDF ya se generó comprimido: pide sinComprimir() antes de leer su contenido.',
            );
        }

        $this->comprimir = false;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Salidas
    |--------------------------------------------------------------------------
    */

    public function contenido(): string
    {
        return $this->renderizado ??= $this->dibujar();
    }

    /** Guarda el documento en disco y devuelve la ruta. */
    public function guardar(string $ruta): string
    {
        file_put_contents($ruta, $this->contenido());

        return $ruta;
    }

    public function nombreArchivo(): string
    {
        return $this->archivo;
    }

    public function esPrevisualizacion(): bool
    {
        return $this->disposicion === 'inline';
    }

    public function esDescarga(): bool
    {
        return $this->disposicion === 'attachment';
    }

    public function toResponse($request): Response
    {
        $contenido = $this->contenido();

        return new Response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($contenido),
            'Content-Disposition' => sprintf('%s; filename="%s"', $this->disposicion, $this->archivo),
        ]);
    }

    private function dibujar(): string
    {
        $this->documento->SetCompression($this->comprimir);

        return $this->documento->armar()->Output('S');
    }
}
