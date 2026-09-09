<?php

namespace App\Services\Pdf\Documentos;

use App\Models\Cotizacion;
use Illuminate\Support\Carbon;

/**
 * Presupuesto formal que se le envía al cliente.
 *
 * Imprime SOLO lo que el cliente puede ver. `costo_base`, `costo_ajustado`,
 * `margen_aplicado`, `factor_complejidad`, `it`, `iue`, `utilidad_real` y
 * `estado_margen` están cargados en el modelo pero NO entran acá: son la
 * rentabilidad de la empresa (ver el docblock de `Cotizacion::ESTADOS_MARGEN`)
 * y este documento sale por correo. Hay un test que lo fija.
 */
class CotizacionPdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly Cotizacion $cotizacion,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        return [
            'tipo' => 'Cotización',
            'numero' => $this->cotizacion->codigo_verificacion,
            'etiqueta' => [
                'texto' => $this->cotizacion->estado,
                'tono' => match ($this->cotizacion->estado) {
                    'APROBADA' => 'exito',
                    'RECHAZADA' => 'peligro',
                    'CONVERTIDA' => 'info',
                    'VENCIDA' => 'aviso',
                    default => 'neutro',
                },
            ],
        ];
    }

    protected function cuerpo(): void
    {
        $cliente = $this->cotizacion->cliente;

        $datosCliente = [['NIT:', $cliente?->nit ?? '—']];

        if ($cliente?->telefono) {
            $datosCliente[] = ['Tel:', $cliente->telefono];
        }

        if ($cliente?->email) {
            $datosCliente[] = ['Correo:', $cliente->email];
        }

        if ($cliente?->direccion) {
            $datosCliente[] = ['Dirección:', $cliente->direccion];
        }

        $this->bloqueDatos([
            [
                'rotulo' => 'Cliente',
                'titulo' => $cliente?->razon_social ?? '—',
                'lineas' => $datosCliente,
            ],
            [
                'rotulo' => 'Detalles',
                'lineas' => [
                    ['Fecha de emisión:', $this->fechaLarga($this->cotizacion->fecha)],
                    ['Válida hasta:', $this->fechaLarga($this->cotizacion->fecha_vencimiento), true],
                    ['Sucursal:', $this->cotizacion->sucursal?->nombre ?? '—'],
                    ['Vendedor:', $this->vendedor() ?: '—'],
                    ['Moneda:', 'Bolivianos (Bs)'],
                ],
            ],
        ]);

        $this->tabla(
            [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Descripción', 'ancho' => 39],
                ['titulo' => 'Medidas (m)', 'ancho' => 18, 'alineacion' => 'C'],
                ['titulo' => 'Cant.', 'ancho' => 8, 'alineacion' => 'R'],
                ['titulo' => 'P. unit.', 'ancho' => 15, 'alineacion' => 'R'],
                ['titulo' => 'Subtotal', 'ancho' => 15, 'alineacion' => 'R'],
            ],
            $this->cotizacion->detalles->values()->map(fn ($linea, $indice): array => [
                (string) ($indice + 1),
                ['texto' => $linea->descripcion, 'nota' => $linea->producto?->nombre],
                $this->medidas($linea->ancho, $linea->alto, $linea->area_m2),
                $this->numero($linea->cantidad, 0),
                $this->monto($linea->precio_unitario, moneda: false),
                ['texto' => $this->monto($linea->subtotal, moneda: false), 'negrita' => true],
            ])->all(),
        );

        $this->totales($this->filasDeTotales());

        if ($this->cotizacion->observaciones) {
            $this->seccion('Observaciones', $this->cotizacion->observaciones);
        }

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Condiciones'],
            ['tipo' => 'lista', 'items' => $this->condiciones()],
        ]);

        $this->notaLegal([
            ['texto' => sprintf(
                'Documento generado por el sistema de costos y presupuestos de %s el %s. Puedes verificar su autenticidad en ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => route('verificar', $this->cotizacion->codigo_verificacion), 'negrita' => true],
            ['texto' => ' con el código '],
            ['texto' => $this->cotizacion->codigo_verificacion, 'negrita' => true],
            ['texto' => '.'],
        ]);
    }

    /**
     * @return list<array{etiqueta: string, valor: string, principal?: bool}>
     */
    private function filasDeTotales(): array
    {
        $filas = [[
            'etiqueta' => 'Subtotal',
            'valor' => $this->monto($this->cotizacion->subtotal),
        ]];

        if ((float) $this->cotizacion->descuento > 0) {
            $filas[] = [
                'etiqueta' => 'Descuento',
                'valor' => '− '.$this->numero($this->cotizacion->descuento),
            ];
        }

        if ((float) $this->cotizacion->iva > 0) {
            $filas[] = [
                'etiqueta' => sprintf('IVA (%d%%)', (int) (config('margen.impuestos.iva') * 100)),
                'valor' => $this->monto($this->cotizacion->iva),
            ];
        }

        if ((float) $this->cotizacion->instalacion > 0) {
            $filas[] = [
                'etiqueta' => 'Instalación',
                'valor' => $this->monto($this->cotizacion->instalacion),
            ];
        }

        $filas[] = [
            'etiqueta' => 'Total',
            'valor' => $this->monto($this->cotizacion->total),
            'principal' => true,
        ];

        return $filas;
    }

    /**
     * @return list<string>
     */
    private function condiciones(): array
    {
        $condiciones = [
            'Precios en bolivianos, válidos hasta la fecha indicada arriba.',
            'Los trabajos inician una vez aprobada la cotización por escrito.',
            'El plazo de entrega se confirma al momento de la aprobación.',
        ];

        if ((float) $this->cotizacion->instalacion <= 0) {
            $condiciones[] = 'No incluye instalación ni montaje salvo que se indique en el detalle.';
        }

        return $condiciones;
    }

    private function medidas(mixed $ancho, mixed $alto, mixed $area): string|array
    {
        if (! $ancho || ! $alto) {
            return '—';
        }

        return [
            'texto' => $this->numero($ancho).' × '.$this->numero($alto),
            'nota' => $this->numero($area).' m²',
        ];
    }

    private function vendedor(): string
    {
        $empleado = $this->cotizacion->empleado;

        return $empleado
            ? trim("{$empleado->nombres} {$empleado->paterno} {$empleado->materno}")
            : '';
    }
}
