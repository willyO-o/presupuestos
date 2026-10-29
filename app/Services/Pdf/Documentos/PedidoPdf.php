<?php

namespace App\Services\Pdf\Documentos;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use Illuminate\Support\Carbon;

/**
 * Orden de trabajo: lo que baja a producción.
 *
 * Es el único documento que NO lleva precio de venta línea por línea. Va al
 * taller, y ahí lo que hace falta es qué hay que fabricar, con qué medidas y
 * en qué etapa está cada pieza — el precio solo distrae y no tiene por qué
 * circular por el galpón. El total se imprime al pie porque sirve de control
 * contra la cotización de origen.
 */
class PedidoPdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly Pedido $pedido,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        return [
            'tipo' => 'Orden de trabajo',
            'numero' => $this->pedido->numero_pedido,
            'etiqueta' => [
                'texto' => str_replace('_', ' ', $this->pedido->estado),
                'tono' => match ($this->pedido->estado) {
                    'ENTREGADO' => 'exito',
                    'CANCELADO' => 'peligro',
                    'CONTROL_CALIDAD' => 'info',
                    default => 'aviso',
                },
            ],
        ];
    }

    protected function cuerpo(): void
    {
        $cotizacion = $this->pedido->cotizacion;
        $cliente = $cotizacion?->cliente;

        $datosCliente = [];

        if ($cliente?->telefono) {
            $datosCliente[] = ['Tel:', $cliente->telefono];
        }

        if ($cliente?->direccion) {
            $datosCliente[] = ['Dirección:', $cliente->direccion];
        }

        $datosCliente[] = ['Cotización:', $cotizacion?->codigo_verificacion ?? '—'];

        $produccion = [
            ['Emitido:', $this->fechaLarga($this->pedido->fecha_pedido)],
            ['Entrega estimada:', $this->fechaLarga($this->pedido->fecha_entrega_estimada), true],
        ];

        if ($this->pedido->fecha_entrega_real) {
            $produccion[] = ['Entrega real:', $this->fechaLarga($this->pedido->fecha_entrega_real)];
        }

        $empleado = $cotizacion?->empleado;
        $produccion[] = [
            'Vendedor:',
            $empleado ? trim("{$empleado->nombres} {$empleado->paterno} {$empleado->materno}") : '—',
        ];

        $this->bloqueDatos([
            [
                'rotulo' => 'Cliente',
                'titulo' => $cliente?->razon_social ?? '—',
                'lineas' => $datosCliente,
            ],
            [
                'rotulo' => 'Producción',
                'lineas' => $produccion,
            ],
        ]);

        $this->tabla(
            [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Trabajo a producir', 'ancho' => 43],
                ['titulo' => 'Medidas (m)', 'ancho' => 20, 'alineacion' => 'C'],
                ['titulo' => 'Cant.', 'ancho' => 10, 'alineacion' => 'R'],
                ['titulo' => 'Etapa', 'ancho' => 22, 'alineacion' => 'C'],
            ],
            $this->pedido->detalles->values()->map(fn ($linea, $indice): array => [
                (string) ($indice + 1),
                $this->trabajo($linea),
                $this->medidas($linea->ancho, $linea->alto),
                $this->numero($linea->cantidad, 0),
                str_replace('_', ' ', $linea->estado_item),
            ])->all(),
        );

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Medidas de producción'],
            ['tipo' => 'nota', 'texto' => 'Las medidas de arriba son las de producción. Si un ítem se ajustó en el sistema (Pedidos → Ajustar medidas reales), su línea lo indica junto a lo que dice la cotización. La cotización y el precio acordado no cambian por un ajuste.'],
        ]);

        $this->totales([
            ['etiqueta' => 'Total del pedido', 'valor' => $this->monto($this->pedido->total), 'principal' => true],
        ]);

        // Quién firma se configura en Administración → Firmas de documentos;
        // mientras no haya responsable de producción firma el propietario,
        // una sola vez con las dos funciones.
        $this->firmasEmpresa(['orden_trabajo_produccion', 'orden_trabajo_calidad']);

        $this->notaLegal([
            ['texto' => sprintf(
                'Documento interno generado por el sistema de %s el %s. Orden ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => $this->pedido->numero_pedido, 'negrita' => true],
            ['texto' => '.'],
        ]);
    }

    /**
     * Descripción del trabajo; si producción lo ajustó, con lo cotizado debajo
     * para que el taller sepa que la medida cambió a propósito.
     *
     * @return string|array{texto: string, nota: string}
     */
    private function trabajo(PedidoDetalle $linea): string|array
    {
        $cotizado = $linea->cotizadoSiSeAjusto();

        if ($cotizado === null) {
            return $linea->descripcion;
        }

        $partes = [];

        if ($cotizado['descripcion'] !== $linea->descripcion) {
            $partes[] = $cotizado['descripcion'];
        }

        if ($cotizado['ancho'] && $cotizado['alto']) {
            $partes[] = $this->numero($cotizado['ancho']).' × '.$this->numero($cotizado['alto']).' m';
        }

        $partes[] = 'cant. '.$this->numero($cotizado['cantidad'], 0);

        return [
            'texto' => $linea->descripcion,
            'nota' => 'Ajustado en producción. Cotizado: '.implode(', ', $partes).'.',
        ];
    }

    private function medidas(mixed $ancho, mixed $alto): string|array
    {
        if (! $ancho || ! $alto) {
            return '—';
        }

        return [
            'texto' => $this->numero($ancho).' × '.$this->numero($alto),
            'nota' => $this->numero((float) $ancho * (float) $alto).' m²',
        ];
    }
}
