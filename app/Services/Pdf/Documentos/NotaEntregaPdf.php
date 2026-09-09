<?php

namespace App\Services\Pdf\Documentos;

use App\Models\NotaEntrega;
use Illuminate\Support\Carbon;

/**
 * Nota de entrega: el documento que se firma al dejar el trabajo.
 *
 * A diferencia de la cotización, no lleva precios: lo que se comprueba al
 * recibir es QUÉ llegó y en qué estado, no cuánto costó. Termina en dos
 * firmas porque es su razón de existir — sin la del cliente no prueba nada.
 */
class NotaEntregaPdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly NotaEntrega $nota,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        return [
            'tipo' => 'Nota de entrega',
            'numero' => $this->nota->numero_nota,
            'subtitulo' => 'Pedido '.($this->nota->pedido?->numero_pedido ?? '—'),
        ];
    }

    protected function cuerpo(): void
    {
        $cliente = $this->nota->pedido?->cotizacion?->cliente;
        $entrego = $this->entrego();

        $datosCliente = [];

        if ($cliente?->nit) {
            $datosCliente[] = ['NIT:', $cliente->nit];
        }

        if ($cliente?->direccion) {
            $datosCliente[] = ['Dirección:', $cliente->direccion];
        }

        if ($cliente?->telefono) {
            $datosCliente[] = ['Tel:', $cliente->telefono];
        }

        $recibio = $this->nota->recibido_por ?? '—';

        if ($this->nota->cargo_receptor) {
            $recibio .= ' ('.$this->nota->cargo_receptor.')';
        }

        $this->bloqueDatos([
            [
                'rotulo' => 'Cliente',
                'titulo' => $cliente?->razon_social ?? '—',
                'lineas' => $datosCliente,
            ],
            [
                'rotulo' => 'Entrega',
                'lineas' => [
                    ['Fecha:', $this->fechaLarga($this->nota->fecha_entrega), true],
                    ['Entregó:', $entrego ?: '—'],
                    ['Recibió:', $recibio],
                ],
            ],
        ]);

        $this->tabla(
            [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Descripción del trabajo entregado', 'ancho' => 45],
                ['titulo' => 'Cant.', 'ancho' => 10, 'alineacion' => 'R'],
                ['titulo' => 'Ubicación', 'ancho' => 25],
                ['titulo' => 'Evidencia', 'ancho' => 15, 'alineacion' => 'C'],
            ],
            $this->nota->detalles->values()->map(fn ($linea, $indice): array => [
                (string) ($indice + 1),
                $linea->descripcion,
                $this->numero($linea->cantidad_entregada, 0),
                $linea->ubicacion ?? '—',
                ['texto' => '—', 'imagen' => $linea->fotoRuta()],
            ])->all(),
        );

        if ($this->nota->observaciones) {
            $this->seccion('Observaciones', $this->nota->observaciones);
        }

        $this->recuadro([
            ['tipo' => 'nota', 'texto' => sprintf(
                'Quien firma declara haber recibido los trabajos detallados arriba en las cantidades indicadas y a conformidad. Cualquier observación posterior debe comunicarse a %s dentro de las 48 horas siguientes a esta entrega.',
                $this->empresa['nombre'],
            )],
        ]);

        // La razón de ser del documento: sin la firma de quien recibe no
        // prueba nada.
        $this->firmas([
            [
                'titulo' => $entrego ?: 'Entregado por',
                'pie' => $this->empresa['nombre'],
            ],
            [
                'titulo' => $this->nota->recibido_por ?: 'Recibido por',
                'pie' => ($cliente?->razon_social ?? 'Cliente').' · Firma y sello',
            ],
        ]);

        $this->notaLegal([
            ['texto' => sprintf(
                'Documento generado por el sistema de %s el %s. Nota ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => $this->nota->numero_nota, 'negrita' => true],
            ['texto' => ', correspondiente al pedido '],
            ['texto' => $this->nota->pedido?->numero_pedido ?? '—', 'negrita' => true],
            ['texto' => '.'],
        ]);
    }

    private function entrego(): string
    {
        $empleado = $this->nota->empleado;

        return $empleado
            ? trim("{$empleado->nombres} {$empleado->paterno} {$empleado->materno}")
            : '';
    }
}
