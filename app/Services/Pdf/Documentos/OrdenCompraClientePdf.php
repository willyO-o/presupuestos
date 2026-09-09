<?php

namespace App\Services\Pdf\Documentos;

use App\Models\OrdenCompraCliente;
use Illuminate\Support\Carbon;

/**
 * Acuse de la orden de compra que emitió el cliente.
 *
 * NO reemplaza al PDF que él mandó (`orden_compra_cliente.archivo_pdf`, que
 * es su documento y se guarda tal cual): deja constancia de cómo quedó
 * registrada en el sistema y, sobre todo, de si el monto coteja con el
 * pedido. Ese cotejo es la única razón por la que este documento existe —
 * una diferencia entre la OC y el pedido es lo que frena una facturación.
 */
class OrdenCompraClientePdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly OrdenCompraCliente $orden,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        return [
            'tipo' => 'Orden de compra',
            'numero' => $this->orden->numero_oc,
            'subtitulo' => 'Pedido '.($this->orden->pedido?->numero_pedido ?? '—'),
            'etiqueta' => [
                'texto' => $this->orden->estado,
                'tono' => match ($this->orden->estado) {
                    'VALIDADA' => 'exito',
                    'ANULADA' => 'peligro',
                    default => 'aviso',
                },
            ],
        ];
    }

    protected function cuerpo(): void
    {
        $pedido = $this->orden->pedido;
        $cliente = $pedido?->cotizacion?->cliente;

        $datosCliente = [];

        if ($cliente?->nit) {
            $datosCliente[] = ['NIT:', $cliente->nit];
        }

        if ($cliente?->telefono) {
            $datosCliente[] = ['Tel:', $cliente->telefono];
        }

        $this->bloqueDatos([
            [
                'rotulo' => 'Cliente emisor',
                'titulo' => $cliente?->razon_social ?? '—',
                'lineas' => $datosCliente,
            ],
            [
                'rotulo' => 'Documento',
                'lineas' => [
                    ['Fecha de la OC:', $this->fechaLarga($this->orden->fecha)],
                    ['Condición de pago:', $this->orden->condicion_pago ?: '—'],
                    ['Pedido asociado:', $pedido?->numero_pedido ?? '—'],
                    ['Cotización:', $pedido?->cotizacion?->codigo_verificacion ?? '—'],
                ],
            ],
        ]);

        // El cotejo: es lo que se mira de este documento.
        $diferencia = (float) $this->orden->monto_total - (float) ($pedido->total ?? 0);

        $this->totales([
            [
                'etiqueta' => 'Monto de la orden de compra',
                'valor' => $this->monto($this->orden->monto_total),
            ],
            [
                'etiqueta' => 'Total del pedido '.($pedido?->numero_pedido ?? ''),
                'valor' => $this->monto($pedido->total ?? 0),
            ],
            [
                'etiqueta' => 'Diferencia',
                'valor' => ($diferencia > 0 ? '+' : '').$this->numero($diferencia),
                'principal' => true,
            ],
        ], porcentaje: 60);

        if ($this->orden->difiereDelPedido()) {
            $this->recuadro([
                ['tipo' => 'nota', 'trozos' => [
                    ['texto' => 'El monto de la orden de compra no coincide con el total del pedido.', 'negrita' => true],
                    ['texto' => ' Antes de facturar hay que resolver la diferencia con el cliente: puede ser un cambio de alcance no reflejado en el pedido o un error de transcripción.'],
                ]],
            ], tono: 'aviso');
        } else {
            $this->recuadro([
                ['tipo' => 'nota', 'texto' => 'El monto de la orden de compra coincide con el total del pedido.'],
            ]);
        }

        if ($pedido?->detalles?->isNotEmpty()) {
            $this->espacio(4);
            $this->rotulo('Trabajos cubiertos por esta orden');

            $this->tabla(
                [
                    ['titulo' => '#', 'ancho' => 5],
                    ['titulo' => 'Descripción', 'ancho' => 65],
                    ['titulo' => 'Medidas (m)', 'ancho' => 18, 'alineacion' => 'C'],
                    ['titulo' => 'Cant.', 'ancho' => 12, 'alineacion' => 'R'],
                ],
                $pedido->detalles->values()->map(fn ($linea, $indice): array => [
                    (string) ($indice + 1),
                    $linea->descripcion,
                    $linea->ancho && $linea->alto
                        ? $this->numero($linea->ancho).' × '.$this->numero($linea->alto)
                        : '—',
                    $this->numero($linea->cantidad, 0),
                ])->all(),
            );
        }

        $this->notaLegal([
            ['texto' => sprintf(
                'Acuse generado por el sistema de %s el %s. Este documento refleja cómo quedó registrada la orden de compra ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => $this->orden->numero_oc, 'negrita' => true],
            ['texto' => sprintf(
                ' del cliente; el documento original emitido por %s se conserva por separado.',
                $cliente?->razon_social ?? 'el cliente',
            )],
        ]);
    }
}
