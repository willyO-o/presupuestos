<?php

namespace App\Services\Pdf\Documentos;

use App\Models\Compra;
use Illuminate\Support\Carbon;

/**
 * Orden de compra a proveedor.
 *
 * Cumple dos papeles según el estado: PENDIENTE es el pedido que se le manda
 * al proveedor, y PAGADA es el respaldo del ingreso a inventario. Por eso el
 * cierre cambia con el estado en vez de repetir un texto genérico.
 */
class CompraPdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly Compra $compra,
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
            'numero' => 'OC-'.str_pad((string) $this->compra->id, 5, '0', STR_PAD_LEFT),
            'etiqueta' => [
                'texto' => $this->compra->estado,
                'tono' => match ($this->compra->estado) {
                    'PAGADA' => 'exito',
                    'ANULADA' => 'peligro',
                    default => 'aviso',
                },
            ],
        ];
    }

    protected function cuerpo(): void
    {
        $proveedor = $this->compra->proveedor;
        $responsable = $this->responsable();

        $datosProveedor = [];

        if ($proveedor?->nit) {
            $datosProveedor[] = ['NIT:', $proveedor->nit];
        }

        if ($proveedor?->telefono) {
            $datosProveedor[] = ['Tel:', $proveedor->telefono];
        }

        if ($proveedor?->email) {
            $datosProveedor[] = ['Correo:', $proveedor->email];
        }

        $this->bloqueDatos([
            [
                'rotulo' => 'Proveedor',
                'titulo' => $proveedor?->nombre ?? '—',
                'lineas' => $datosProveedor,
            ],
            [
                'rotulo' => 'Detalles',
                'lineas' => [
                    ['Fecha:', $this->fechaLarga($this->compra->fecha)],
                    ['Factura:', $this->compra->numero_factura ?: '—'],
                    ['Responsable:', $responsable ?: '—'],
                    ['Moneda:', 'Bolivianos (Bs)'],
                ],
            ],
        ]);

        $this->tabla(
            [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Material', 'ancho' => 45],
                ['titulo' => 'Cantidad', 'ancho' => 16, 'alineacion' => 'R'],
                ['titulo' => 'P. unit.', 'ancho' => 17, 'alineacion' => 'R'],
                ['titulo' => 'Subtotal', 'ancho' => 17, 'alineacion' => 'R'],
            ],
            $this->compra->detalles->values()->map(fn ($linea, $indice): array => [
                (string) ($indice + 1),
                [
                    'texto' => $linea->material?->nombre ?? '—',
                    'nota' => $linea->material?->presentacion,
                ],
                [
                    'texto' => $this->numero($linea->cantidad),
                    'nota' => $linea->material?->unidad_medida,
                ],
                $this->monto($linea->precio_unitario, moneda: false),
                ['texto' => $this->monto($linea->subtotal, moneda: false), 'negrita' => true],
            ])->all(),
        );

        $this->totales([
            ['etiqueta' => 'Total', 'valor' => $this->monto($this->compra->total), 'principal' => true],
        ]);

        $this->cierre($responsable);

        $this->notaLegal([
            ['texto' => sprintf(
                'Documento generado por el sistema de %s el %s.',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
        ]);
    }

    /**
     * El pie del documento depende del estado: una compra que todavía no tocó
     * el inventario necesita advertirlo, y una anulada necesita decir que no
     * lo tocó nunca. Solo la ya aprobada se firma.
     */
    private function cierre(string $responsable): void
    {
        if ($this->compra->estado === 'PENDIENTE') {
            $this->recuadro([
                ['tipo' => 'nota', 'texto' => 'Esta compra todavía no impactó el inventario. Al aprobarla se sumará la cantidad al stock de cada material y su precio unitario se actualizará con el de esta compra, quedando registro en el historial de precios.'],
            ], tono: 'aviso');

            return;
        }

        if ($this->compra->estado === 'ANULADA') {
            $this->recuadro([
                ['tipo' => 'nota', 'trozos' => [
                    ['texto' => 'Compra anulada.', 'negrita' => true],
                    ['texto' => ' No impactó el inventario ni los precios de los materiales. Se conserva únicamente como registro histórico.'],
                ]],
            ], tono: 'aviso');

            return;
        }

        $this->firmas([
            [
                'titulo' => $responsable ?: 'Recibido por',
                'pie' => $this->empresa['nombre'],
            ],
            [
                'titulo' => $this->compra->proveedor?->nombre ?? 'Proveedor',
                'pie' => 'Firma y sello',
            ],
        ]);
    }

    private function responsable(): string
    {
        $empleado = $this->compra->empleado;

        return $empleado
            ? trim("{$empleado->nombres} {$empleado->paterno} {$empleado->materno}")
            : '';
    }
}
