<?php

namespace App\Services\Pdf\Documentos;

use App\Models\CotizacionPublica;
use Illuminate\Support\Carbon;

/**
 * Estimación que un visitante armó solo en `/cotizador`.
 *
 * Misma forma que la cotización formal para que el cliente reconozca el
 * documento, pero rotulada ESTIMACIÓN y con el aviso de que no es una oferta
 * en firme: el precio lo calculó el motor sin que lo revisara un vendedor, y
 * caduca. Por eso el número que se destaca es el RANGO y no el total exacto.
 */
class CotizacionPublicaPdf extends Documento
{
    /**
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly CotizacionPublica $estimacion,
        private readonly bool $vigente,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        return [
            'tipo' => 'Estimación referencial',
            'numero' => $this->estimacion->codigo,
            'etiqueta' => [
                'texto' => $this->vigente ? 'Vigente' : 'Vencida',
                'tono' => $this->vigente ? 'exito' : 'aviso',
            ],
        ];
    }

    protected function cuerpo(): void
    {
        $solicitante = [];

        if ($this->estimacion->empresa) {
            $solicitante[] = $this->estimacion->empresa;
        }

        $solicitante[] = ['Tel:', $this->estimacion->telefono];

        if ($this->estimacion->email) {
            $solicitante[] = ['Correo:', $this->estimacion->email];
        }

        $this->bloqueDatos([
            [
                'rotulo' => 'Solicitado por',
                'titulo' => $this->estimacion->nombre,
                'lineas' => $solicitante,
            ],
            [
                'rotulo' => 'Detalles',
                'lineas' => [
                    ['Fecha de emisión:', $this->fechaLarga($this->estimacion->created_at)],
                    [
                        'Válida hasta:',
                        $this->fechaLarga($this->estimacion->fecha_vencimiento)
                            .' ('.$this->estimacion->vigencia_dias.' días)',
                        true,
                    ],
                    ['Origen:', 'Cotizador en línea'],
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
            collect($this->estimacion->detalle)->values()->map(fn (array $linea, int $indice): array => [
                (string) ($indice + 1),
                (string) ($linea['producto'] ?? $linea['descripcion'] ?? '—'),
                $this->medidas($linea['ancho'] ?? null, $linea['alto'] ?? null),
                (string) (int) ($linea['cantidad'] ?? 0),
                $this->monto($linea['precio_unitario'] ?? 0, moneda: false),
                ['texto' => $this->monto($linea['subtotal'] ?? 0, moneda: false), 'negrita' => true],
            ])->all(),
        );

        $iva = (int) (config('margen.impuestos.iva') * 100);

        $this->totales([
            ['etiqueta' => 'Subtotal', 'valor' => $this->monto($this->estimacion->subtotal)],
            ['etiqueta' => sprintf('IVA (%d%%)', $iva), 'valor' => $this->monto($this->estimacion->iva)],
            ['etiqueta' => 'Total estimado', 'valor' => $this->monto($this->estimacion->total), 'principal' => true],
        ]);

        // El rango es el número que manda: dar un total exacto sería prometer
        // una precisión que esta estimación no tiene.
        $this->recuadro([
            ['tipo' => 'rotulo', 'texto' => 'Rango aproximado · IVA incluido'],
            ['tipo' => 'rango', 'texto' => sprintf(
                '%s – %s',
                $this->monto($this->estimacion->estimado_min),
                $this->monto($this->estimacion->estimado_max),
            )],
            ['tipo' => 'nota', 'texto' => sprintf(
                'Margen de ±%d%% sobre el total estimado.',
                (int) round((float) $this->estimacion->holgura * 100),
            )],
        ], tono: 'destacado', centrado: true);

        if ($this->estimacion->mensaje) {
            $this->seccion('Indicaciones del solicitante', $this->estimacion->mensaje);
        }

        $this->espacio(4);
        $this->bloqueDatos([
            ['rotulo' => 'Incluye'],
            ['rotulo' => 'No incluye'],
        ]);
        $this->dosListas(
            [
                'Materiales según la ficha técnica de cada trabajo.',
                'Fabricación en taller.',
                sprintf('IVA de ley (%d%%).', $iva),
            ],
            [
                'Instalación y montaje en punto de venta.',
                sprintf('Transporte fuera de %s y %s.', $this->empresa['ciudad'], $this->empresa['departamento']),
                'Diseño gráfico, acabados especiales y estructuras a medida.',
            ],
        );

        $this->recuadro([
            ['tipo' => 'nota', 'trozos' => [
                ['texto' => 'Aviso:', 'negrita' => true],
                ['texto' => sprintf(
                    ' este documento es una estimación automática generada por el cotizador en línea de %s a partir de los precios de material vigentes al %s. ',
                    $this->empresa['nombre'],
                    $this->estimacion->created_at->translatedFormat('d/m/Y'),
                )],
                ['texto' => 'No constituye una oferta comercial en firme.', 'negrita' => true],
                ['texto' => ' El presupuesto formal se emite tras revisar el detalle del trabajo y puede diferir de estos montos.'],
            ]],
        ], tono: 'aviso');

        $this->notaLegal([
            ['texto' => 'Generado el '.$this->selloDeGeneracion().'. Menciona el código '],
            ['texto' => $this->estimacion->codigo, 'negrita' => true],
            ['texto' => ' al escribirnos y un asesor retoma tu pedido sin que tengas que explicarlo de nuevo.'],
        ]);
    }

    /**
     * Las dos listas de "Incluye / No incluye" en columnas.
     *
     * `bloqueDatos()` solo maneja líneas de dato; acá hace falta una viñeta
     * por ítem en cada columna, así que se dibujan a mano sobre el mismo
     * reparto de anchos.
     *
     * @param  list<string>  $incluye
     * @param  list<string>  $excluye
     */
    private function dosListas(array $incluye, array $excluye): void
    {
        $separacion = 8.0;
        $ancho = ($this->anchoUtil() - $separacion) / 2;
        $inicio = $this->GetY();
        $final = $inicio;

        foreach ([$incluye, $excluye] as $indice => $items) {
            $x = self::MARGEN_LATERAL + $indice * ($ancho + $separacion);
            $this->SetXY($x, $inicio);

            foreach ($items as $item) {
                $this->fuente(8, '', self::SUAVE);
                $this->SetX($x);
                $this->Cell(3.5, $this->altoLinea(8), $this->t('·'), 0, 0);
                $this->SetX($x + 3.5);
                $this->flujo($ancho - 3.5, [['texto' => $item]], $this->altoLinea(8));
            }

            $final = max($final, $this->GetY());
        }

        $this->SetXY(self::MARGEN_LATERAL, $final);
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
