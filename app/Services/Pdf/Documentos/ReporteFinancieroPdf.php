<?php

namespace App\Services\Pdf\Documentos;

use App\Services\Reporte\InformeReportesService;
use Illuminate\Support\Carbon;

/**
 * Reporte financiero en PDF: lo mismo que la pantalla (ventas por mes y por
 * sucursal, rentabilidad real por pedido) más el detalle de cuentas por
 * cobrar, que en pantalla solo aparece como total.
 *
 * Documento INTERNO (márgenes, saldos y nombres de clientes): se rotula así
 * en el membrete. Recibe el informe ya armado por
 * `InformeReportesService::financiero()`; acá no se calcula nada.
 */
class ReporteFinancieroPdf extends Documento
{
    use DibujaGraficos;

    /** Barras del gráfico de margen: los peores, que son los que piden acción. */
    private const PEDIDOS_EN_GRAFICO = 10;

    /**
     * @param  array<string, mixed>  $informe  InformeReportesService::financiero().
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly array $informe,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        $periodo = $this->informe['periodo'];

        return [
            'tipo' => 'Reporte financiero',
            'numero' => Carbon::parse($periodo['desde'])->format('d/m/Y').' – '.Carbon::parse($periodo['hasta'])->format('d/m/Y'),
            'subtitulo' => $this->informe['alcance'],
            'etiqueta' => ['texto' => 'Uso interno', 'tono' => 'info'],
        ];
    }

    protected function cuerpo(): void
    {
        $this->datosGenerales();
        $this->espacio(3);
        $this->indicadores($this->informe['indicadores']);

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Puntos clave'],
            ['tipo' => 'lista', 'items' => $this->informe['puntos_clave']],
        ], tono: 'destacado');

        $this->ventas();

        $this->espacio(6);
        $this->rentabilidad();
        $this->cuentasPorCobrar();

        $this->notaLegal([
            ['texto' => sprintf(
                'Reporte generado por el sistema de %s el %s con los datos registrados a esa fecha. ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => 'Documento interno: contiene márgenes, saldos y datos de clientes.', 'negrita' => true],
        ]);
    }

    /* -----------------------------------------------------------------
     * Bloques
     * -------------------------------------------------------------- */

    private function datosGenerales(): void
    {
        $periodo = $this->informe['periodo'];

        $this->bloqueDatos([
            [
                'rotulo' => 'Periodo',
                'titulo' => $periodo['etiqueta'],
                'lineas' => [
                    ['Alcance:', $this->informe['alcance']],
                    ['Generado:', $this->selloDeGeneracion()],
                ],
            ],
            [
                'rotulo' => 'Qué entra en cada cifra',
                'lineas' => [
                    ['Ventas:', 'cotizaciones aprobadas o convertidas con fecha en el periodo.'],
                    ['Rentabilidad:', 'pedidos en acabado o entregados, sin filtro de fecha.'],
                    ['Por cobrar:', 'saldo de los pedidos no cancelados, a hoy.'],
                ],
            ],
        ]);
    }

    private function ventas(): void
    {
        $this->espacio(4);
        $this->tituloSeccion('Ventas');

        $meses = $this->informe['ventas_por_mes'];
        $total = $this->informe['total_vendido'];

        $this->subtitulo('Ventas por mes', 'Monto de las cotizaciones aprobadas y convertidas, por el mes de su fecha. Los meses sin ventas cuentan como cero.');
        $this->graficoLineas([
            'labels' => array_column($meses, 'etiqueta'),
            'series' => [[
                'nombre' => 'Vendido',
                'datos' => array_column($meses, 'total'),
                'color' => self::AZUL,
                'grosor' => 0.6,
            ]],
            'formato' => fn (float $v): string => $this->compacto($v),
        ], 58);

        // La tabla lista solo los meses con ventas: los ceros ya se ven en el
        // gráfico, y un periodo largo con meses vacíos ocuparía hojas enteras.
        $conVentas = array_values(array_filter($meses, fn (array $m): bool => $m['total'] > 0));

        if ($conVentas !== []) {
            $this->tabla([
                ['titulo' => 'Mes con ventas', 'ancho' => 40],
                ['titulo' => 'Vendido', 'ancho' => 35, 'alineacion' => 'R'],
                ['titulo' => '% del periodo', 'ancho' => 25, 'alineacion' => 'R'],
            ], [
                ...array_map(fn (array $m): array => [
                    $m['etiqueta'],
                    $this->monto($m['total']),
                    $this->numero($m['total'] / $total * 100, 1).' %',
                ], $conVentas),
                [
                    ['texto' => 'Total del periodo', 'negrita' => true],
                    ['texto' => $this->monto($total), 'negrita' => true],
                    ['texto' => '100 %', 'negrita' => true],
                ],
            ]);
        }

        $sucursales = $this->informe['ventas_por_sucursal'];

        $this->subtitulo('Ventas por sucursal', 'De mayor a menor. La participación es sobre el total vendido en el periodo.');

        if ($sucursales === []) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'Sin ventas por sucursal en el periodo.']]);

            return;
        }

        $this->graficoBarras([
            'barras' => array_map(fn (array $s): array => ['etiqueta' => $s['sucursal'], 'valor' => $s['total']], $sucursales),
            'formato' => fn (float $v): string => $this->monto($v),
        ]);

        $this->tabla([
            ['titulo' => 'Sucursal', 'ancho' => 50],
            ['titulo' => 'Vendido', 'ancho' => 30, 'alineacion' => 'R'],
            ['titulo' => 'Participación', 'ancho' => 20, 'alineacion' => 'R'],
        ], array_map(fn (array $s): array => [
            ['texto' => $s['sucursal'], 'negrita' => true],
            $this->monto($s['total']),
            $this->numero($s['participacion'], 1).' %',
        ], $sucursales));
    }

    private function rentabilidad(): void
    {
        $this->tituloSeccion('Rentabilidad real por pedido');

        $filas = $this->informe['rentabilidad'];
        $resumen = $this->informe['rentabilidad_resumen'];

        $this->parrafo('Ingreso del pedido contra el costo del material que producción registró como realmente consumido. Es el margen que de verdad quedó, no el presupuestado en la cotización.');

        if ($filas === []) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'Todavía no hay pedidos en acabado o entregados.']]);

            return;
        }

        $medidos = array_values(array_filter($filas, fn (array $r): bool => ! $r['sin_consumo']));

        if ($medidos !== []) {
            $this->subtitulo(
                count($medidos) > self::PEDIDOS_EN_GRAFICO ? 'Los '.self::PEDIDOS_EN_GRAFICO.' pedidos con menor margen' : 'Margen por pedido',
                'De peor a mejor. En rojo, los pedidos que costaron más de lo que dejaron.',
            );

            $this->graficoBarras([
                'barras' => array_map(fn (array $r): array => [
                    'etiqueta' => $r['pedido'],
                    'valor' => $r['margen'],
                    'color' => $r['margen'] < 0 ? self::ROJO : self::VERDE,
                ], array_slice($medidos, 0, self::PEDIDOS_EN_GRAFICO)),
                'formato' => fn (float $v): string => $this->monto($v),
                'ancho_etiqueta' => 38,
            ]);
        }

        $this->subtitulo('Detalle', $resumen['sin_consumo'] > 0
            ? 'Los pedidos sin consumo de material registrado van al final y no suman en el total: su costo figura en cero hasta que producción lo cargue.'
            : null);

        $this->tabla([
            ['titulo' => 'Pedido', 'ancho' => 22],
            ['titulo' => 'Cliente', 'ancho' => 26],
            ['titulo' => 'Ingreso', 'ancho' => 15, 'alineacion' => 'R'],
            ['titulo' => 'Costo real', 'ancho' => 14, 'alineacion' => 'R'],
            ['titulo' => 'Margen', 'ancho' => 15, 'alineacion' => 'R'],
            ['titulo' => '%', 'ancho' => 8, 'alineacion' => 'R'],
        ], array_map(function (array $r): array {
            if ($r['sin_consumo']) {
                return [
                    ['texto' => $r['pedido'], 'nota' => $r['cotizacion']],
                    $r['cliente'] ?? '—',
                    $this->monto($r['ingreso']),
                    ['texto' => '—', 'nota' => 'Sin consumo'],
                    '—',
                    '—',
                ];
            }

            $color = $r['margen'] < 0 ? self::ROJO : null;

            return [
                ['texto' => $r['pedido'], 'nota' => $r['cotizacion']],
                $r['cliente'] ?? '—',
                $this->monto($r['ingreso']),
                $this->monto($r['costo_real']),
                ['texto' => $this->monto($r['margen']), 'negrita' => true, 'color' => $color],
                ['texto' => $r['margen_pct'] === null ? '—' : $this->numero($r['margen_pct'], 1).' %', 'color' => $color],
            ];
        }, $filas));

        if ($resumen['con_costo'] > 0) {
            $this->totales([
                ['etiqueta' => 'Ingreso medido', 'valor' => $this->monto($resumen['ingreso'])],
                ['etiqueta' => 'Costo real de material', 'valor' => $this->monto($resumen['costo'])],
                [
                    'etiqueta' => 'Margen real ('.$this->numero((float) $resumen['margen_pct'], 1).' %)',
                    'valor' => $this->monto($resumen['margen']),
                    'principal' => true,
                ],
            ]);
        }
    }

    private function cuentasPorCobrar(): void
    {
        $cuentas = $this->informe['cuentas_por_cobrar'];

        $this->espacio(6);
        $this->tituloSeccion('Cuentas por cobrar');

        if ($cuentas['pedidos'] === []) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'No hay saldos pendientes de cobro.']]);

            return;
        }

        $this->parrafo(sprintf(
            'Pedidos no cancelados con saldo pendiente, del más antiguo al más reciente. Los días se cuentan desde la fecha del pedido; en negrita, los de más de %d días.',
            InformeReportesService::DIAS_DEUDA_VIEJA,
        ));

        $this->tabla([
            ['titulo' => 'Pedido', 'ancho' => 22],
            ['titulo' => 'Cliente', 'ancho' => 25],
            ['titulo' => 'Fecha', 'ancho' => 11],
            ['titulo' => 'Días', 'ancho' => 7, 'alineacion' => 'R'],
            ['titulo' => 'Total', 'ancho' => 12, 'alineacion' => 'R'],
            ['titulo' => 'Pagado', 'ancho' => 11, 'alineacion' => 'R'],
            ['titulo' => 'Saldo', 'ancho' => 12, 'alineacion' => 'R'],
        ], array_map(function (array $c): array {
            $vieja = $c['dias'] > InformeReportesService::DIAS_DEUDA_VIEJA;

            return [
                $c['pedido'],
                $c['cliente'] ?? '—',
                Carbon::parse($c['fecha_pedido'])->format('d/m/Y'),
                ['texto' => (string) $c['dias'], 'negrita' => $vieja],
                $this->monto($c['total']),
                $this->monto($c['pagado']),
                ['texto' => $this->monto($c['saldo']), 'negrita' => true],
            ];
        }, $cuentas['pedidos']));

        $this->totales([
            ['etiqueta' => 'Pedidos con saldo', 'valor' => (string) count($cuentas['pedidos'])],
            ['etiqueta' => 'Total por cobrar', 'valor' => $this->monto($cuentas['total']), 'principal' => true],
        ]);
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /** "Bs 120 mil" en vez de "Bs 120.000,00": si no, el eje no entra en el margen. */
    private function compacto(float $valor): string
    {
        return match (true) {
            abs($valor) >= 1_000_000 => 'Bs '.$this->numero($valor / 1_000_000, 1).' M',
            abs($valor) >= 1_000 => 'Bs '.$this->numero($valor / 1_000, 0).' mil',
            default => 'Bs '.$this->numero($valor, 0),
        };
    }
}
