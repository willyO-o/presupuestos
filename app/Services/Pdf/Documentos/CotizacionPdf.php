<?php

namespace App\Services\Pdf\Documentos;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Services\Pdf\MontoEnLetras;
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
    /** Alto de la fila con imagen de referencia, en mm: más grande que una evidencia, se tiene que ver el diseño. */
    private const ALTO_IMAGEN_REFERENCIA = 26.0;

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

        if ($cliente?->contacto_nombre) {
            $datosCliente[] = ['Contacto:', $cliente->contacto_nombre];
        }

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

        // La imagen de referencia de cada línea (la que se carga en el
        // formulario) va en su propia columna; si ninguna línea tiene imagen
        // la columna no aparece y la descripción conserva su ancho.
        $imagenes = $this->cotizacion->detalles->values()->map(fn ($linea): ?string => $linea->imagenRuta());
        $conImagenes = $imagenes->filter()->isNotEmpty();

        $columnas = $conImagenes
            ? [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Descripción', 'ancho' => 31],
                ['titulo' => 'Imagen', 'ancho' => 17, 'alineacion' => 'C'],
                ['titulo' => 'Medidas (m)', 'ancho' => 15, 'alineacion' => 'C'],
                ['titulo' => 'Cant.', 'ancho' => 6, 'alineacion' => 'R'],
                ['titulo' => 'P. unit.', 'ancho' => 13, 'alineacion' => 'R'],
                ['titulo' => 'Subtotal', 'ancho' => 13, 'alineacion' => 'R'],
            ]
            : [
                ['titulo' => '#', 'ancho' => 5],
                ['titulo' => 'Descripción', 'ancho' => 39],
                ['titulo' => 'Medidas (m)', 'ancho' => 18, 'alineacion' => 'C'],
                ['titulo' => 'Cant.', 'ancho' => 8, 'alineacion' => 'R'],
                ['titulo' => 'P. unit.', 'ancho' => 15, 'alineacion' => 'R'],
                ['titulo' => 'Subtotal', 'ancho' => 15, 'alineacion' => 'R'],
            ];

        $this->tabla(
            $columnas,
            $this->cotizacion->detalles->values()->map(fn ($linea, $indice): array => array_values(array_filter([
                (string) ($indice + 1),
                $this->descripcion($linea->descripcion),
                $conImagenes ? ['texto' => '—', 'imagen' => $imagenes[$indice], 'alto_imagen' => self::ALTO_IMAGEN_REFERENCIA] : null,
                $this->medidas($linea),
                $this->numero($linea->cantidad, 0),
                $this->monto($linea->precio_unitario, moneda: false),
                ['texto' => $this->monto($linea->subtotal, moneda: false), 'negrita' => true],
            ], fn ($celda) => $celda !== null)))->all(),
        );

        $this->totales($this->filasDeTotales());

        $this->seccion('Son', MontoEnLetras::convertir($this->cotizacion->total));

        if ($this->cotizacion->observaciones) {
            $this->seccion('Observaciones', $this->cotizacion->observaciones);
        }

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Condiciones de venta'],
            ...$this->condicionesDeVenta(),
            ['tipo' => 'lista', 'items' => $this->condiciones()],
        ]);

        // Única aparición del nombre del propietario: al pie, como firma.
        $this->firmasEmpresa(['cotizacion']);

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
                // Guion común: el "−" tipográfico no existe en cp1252 y salía "?".
                'valor' => '- Bs '.$this->numero($this->cotizacion->descuento),
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
     * Forma de pago, tiempo y lugar de entrega de ESTA cotización, cada una
     * en su renglón con el rótulo en negrita. Las que están vacías no salen.
     *
     * @return list<array{tipo: string, trozos: list<array{texto: string, negrita?: bool}>}>
     */
    private function condicionesDeVenta(): array
    {
        $condiciones = array_filter([
            'Forma de pago' => $this->cotizacion->forma_pago,
            'Tiempo de entrega' => $this->cotizacion->tiempo_entrega,
            'Lugar de entrega' => $this->cotizacion->lugar_entrega,
        ]);

        return array_values(array_map(
            fn (string $rotulo, string $valor): array => ['tipo' => 'texto', 'trozos' => [
                ['texto' => $rotulo.': ', 'negrita' => true],
                ['texto' => $valor],
            ]],
            array_keys($condiciones),
            $condiciones,
        ));
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

    /**
     * Descripción del ítem como en las cotizaciones que la empresa armaba a
     * mano: el primer renglón es el título ("Elaborado de Cabecera") y va en
     * negrita; el resto, el detalle, en texto normal y respetando sus saltos.
     *
     * @return string|array{texto: string, trozos: list<array<string, mixed>>}
     */
    private function descripcion(string $descripcion): string|array
    {
        $renglones = preg_split('/\R/u', trim($descripcion), 2) ?: [''];

        if (count($renglones) < 2) {
            return $descripcion;
        }

        return [
            'texto' => $descripcion,
            'trozos' => [
                ['texto' => $renglones[0], 'negrita' => true],
                ['texto' => "\n".$renglones[1]],
            ],
        ];
    }

    /**
     * "ancho × alto" o "ancho × alto × profundidad", y debajo los niveles
     * de un mueble ("4 niveles"). El área (m²) y el producto del catálogo
     * son datos de cálculo internos: el cliente ve la descripción y las
     * medidas.
     */
    private function medidas(CotizacionDetalle $linea): string
    {
        $renglones = array_filter([
            $this->medidasTexto($linea->ancho, $linea->alto, $linea->profundidad),
            $this->nivelesTexto($linea->niveles),
        ]);

        return $renglones === [] ? '—' : implode("\n", $renglones);
    }

    private function vendedor(): string
    {
        $empleado = $this->cotizacion->empleado;

        return $empleado
            ? trim("{$empleado->nombres} {$empleado->paterno} {$empleado->materno}")
            : '';
    }
}
