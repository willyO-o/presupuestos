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
     * Página donde empieza cada capítulo, para el índice de la página 1.
     *
     * @var array{ventas: int, margen: int, anexo: int}|null
     */
    private ?array $paginas = null;

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

    /**
     * Tres hojas de resultados redactadas para la gerencia (resumen, ventas,
     * margen y cobros) y, detrás, el anexo con el detalle completo para
     * verificar cada cifra. Cada capítulo empieza hoja.
     */
    protected function cuerpo(): void
    {
        $this->tramoPie = 'Resumen de resultados';

        $this->resumenGerencia();

        $this->AddPage();
        $this->ventas();

        $this->AddPage();
        $this->margenYCobros();

        $this->AddPage();
        $this->tramoPie = 'Anexo de pedidos';
        $this->rentabilidad();

        $this->AddPage();
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
     * Hojas de resultados
     * -------------------------------------------------------------- */

    private function resumenGerencia(): void
    {
        $resumen = $this->informe['rentabilidad_resumen'];
        $cuentas = $this->informe['cuentas_por_cobrar'];
        $mesesConVentas = count($this->mesesConVentas());

        $this->encabezadoCapitulo('01', 'Resumen para la gerencia', '¿Qué muestran los resultados?', sprintf(
            'Ventas del %s. Información generada el %s. Alcance: %s.',
            $this->informe['periodo']['etiqueta'],
            $this->selloDeGeneracion(),
            mb_strtolower(mb_substr($this->informe['alcance'], 0, 1)).mb_substr($this->informe['alcance'], 1),
        ));

        $this->fichas([
            [
                'rotulo' => 'Ventas del período',
                'valor' => $this->monto($this->informe['total_vendido']),
                'explicacion' => $mesesConVentas === 0
                    ? 'No hubo ventas aprobadas en el periodo.'
                    : 'Valor de las cotizaciones aprobadas o convertidas durante el período. Se registraron ventas en '
                        .($mesesConVentas === 1 ? '1 mes.' : "{$mesesConVentas} meses."),
            ],
            [
                'rotulo' => 'Margen sobre materiales',
                'valor' => $resumen['margen_pct'] === null ? '—' : $this->numero($resumen['margen_pct'], 1).' %',
                'explicacion' => $resumen['con_costo'] === 0
                    ? 'Todavía no hay pedidos terminados con consumo de material registrado para medirlo.'
                    : 'Diferencia entre el ingreso de los pedidos y el material consumido registrado. Considera '
                        .$this->pedidos($resumen['con_costo']).', sin filtro de fecha.',
                'tono' => $resumen['margen'] < 0 ? 'peligro' : null,
            ],
            [
                'rotulo' => 'Saldo pendiente de cobro',
                'valor' => $this->monto($cuentas['total']),
                'explicacion' => $cuentas['pedidos'] === []
                    ? 'No hay saldos pendientes de cobro.'
                    : 'Importe pendiente en '.$this->pedidos(count($cuentas['pedidos'])).' al cierre del informe.'
                        .($cuentas['viejas'] > 0
                            ? sprintf(' Hay %s con más de %d días desde su fecha.', $this->pedidos($cuentas['viejas']), InformeReportesService::DIAS_DEUDA_VIEJA)
                            : ''),
            ],
        ]);

        $this->apartado('Lo que requiere atención');

        $perdidas = $this->informe['pedidos_con_perdida'];

        if ($perdidas !== []) {
            $peor = $perdidas[0];

            $this->parrafoRotulado(
                count($perdidas) === 1 ? '1 pedido con pérdida:' : count($perdidas).' pedidos con pérdida:',
                count($perdidas) === 1
                    ? sprintf(
                        'revisar el precio y los materiales registrados del pedido %s, de %s. La diferencia negativa es de %s.',
                        $peor['pedido'],
                        rtrim($peor['cliente'] ?? 'cliente sin nombre', '.'),
                        $this->monto(abs($peor['margen'])),
                    )
                    : sprintf(
                        'revisar el precio y los materiales registrados de cada uno. La mayor diferencia negativa es la del pedido %s, de %s, por %s. El listado completo está en el anexo.',
                        $peor['pedido'],
                        rtrim($peor['cliente'] ?? 'cliente sin nombre', '.'),
                        $this->monto(abs($peor['margen'])),
                    ),
            );
        }

        if ($resumen['sin_consumo'] > 0) {
            $this->parrafoRotulado(
                $resumen['sin_consumo'] === 1 ? '1 pedido terminado sin consumo registrado:' : "{$resumen['sin_consumo']} pedidos terminados sin consumo registrado:",
                'completar su información antes de evaluar su margen. Un costo en cero por falta de registro no significa que producirlos no haya costado.',
            );
        }

        if ($perdidas === [] && $resumen['sin_consumo'] === 0) {
            $this->parrafoRotulado(null, 'Ningún pedido terminado dio pérdida y todos tienen su consumo de material registrado.');
        }

        $this->apartado('Cómo leer este documento');
        $this->parrafoRotulado(null, sprintf(
            'Página %s: ventas por mes y sucursal. Página %s: explicación del margen y de los cobros. Desde la página %s: anexo con gráficos y detalle completo de pedidos.',
            $this->paginas()['ventas'],
            $this->paginas()['margen'],
            $this->paginas()['anexo'],
        ), 8);
        $this->parrafoRotulado('Atención al alcance:', 'las ventas tienen filtro de fecha; el margen no. Por eso sus importes no se comparan directamente.', 8);
    }

    private function ventas(): void
    {
        $total = $this->informe['total_vendido'];
        $periodo = $this->informe['periodo'];

        $this->encabezadoCapitulo('02', 'Ventas', '¿Cuánto se vendió y dónde?', 'Las cifras representan cotizaciones aprobadas o convertidas según su fecha. No representan necesariamente dinero cobrado.');

        $conVentas = $this->mesesConVentas();

        if ($conVentas === []) {
            $this->parrafoRotulado(null, 'No hubo ventas aprobadas en el periodo.');

            return;
        }

        $unSoloAnio = substr($periodo['desde'], 0, 4) === substr($periodo['hasta'], 0, 4);

        // La tabla lista solo los meses con ventas: un periodo largo con meses
        // vacíos ocuparía la hoja entera. Los vacíos se nombran debajo.
        $this->tabla([
            ['titulo' => $unSoloAnio ? 'Mes de '.substr($periodo['desde'], 0, 4) : 'Mes', 'ancho' => 40],
            ['titulo' => 'Ventas (Bs)', 'ancho' => 34],
            ['titulo' => 'Parte del total', 'ancho' => 26],
        ], [
            ...array_map(fn (array $m): array => [
                $this->nombreMes($m),
                $this->monto($m['total'], false),
                $this->numero($m['total'] / $total * 100, 1).' %',
            ], $conVentas),
            ['TOTAL', $this->monto($total, false), '100 %'],
        ], resumen: true);

        $this->parrafoRotulado('Lectura del resultado:', $this->lecturaMeses($conVentas, $total));

        $sucursales = $this->informe['ventas_por_sucursal'];

        if ($sucursales === []) {
            return;
        }

        $this->apartado('Participación de las sucursales');

        $this->tabla([
            ['titulo' => 'Sucursal', 'ancho' => 40],
            ['titulo' => 'Ventas (Bs)', 'ancho' => 34],
            ['titulo' => 'Parte del total', 'ancho' => 26],
        ], array_map(fn (array $s): array => [
            $s['sucursal'],
            $this->monto($s['total'], false),
            $this->numero($s['participacion'], 1).' %',
        ], $sucursales), resumen: true);

        $lider = $sucursales[0];

        $this->parrafoRotulado('Lectura del resultado:', count($sucursales) === 1
            ? sprintf('todas las ventas del alcance corresponden a %s.', $lider['sucursal'])
            : sprintf(
                '%s aporta la mayor proporción de las ventas (%s %%). La participación permite comparar el aporte de cada sucursal al total.',
                $lider['sucursal'],
                $this->numero($lider['participacion'], 1),
            ));
    }

    private function margenYCobros(): void
    {
        $resumen = $this->informe['rentabilidad_resumen'];
        $cuentas = $this->informe['cuentas_por_cobrar'];
        $perdidas = count($this->informe['pedidos_con_perdida']);

        $this->encabezadoCapitulo('03', 'Margen y cobros', '¿Qué queda y cuánto falta cobrar?', 'Estos indicadores responden a preguntas diferentes. El margen compara ingresos y materiales; el saldo por cobrar muestra pagos pendientes.');

        // --- A. Margen
        $this->apartado('A. Margen de los pedidos con materiales registrados');

        if ($resumen['con_costo'] === 0) {
            $this->parrafoRotulado(null, 'Todavía no hay pedidos en acabado o entregados con consumo de material registrado.');
        } else {
            $porcentaje = $this->numero((float) $resumen['margen_pct'], 1);

            $this->tabla([
                ['titulo' => 'Concepto', 'ancho' => 66],
                ['titulo' => 'Importe', 'ancho' => 34],
            ], [
                ['Ingreso de '.$this->pedidos($resumen['con_costo']).' evaluados', $this->monto($resumen['ingreso'])],
                ['Menos: costo real de materiales', $this->monto($resumen['costo'])],
                ['Diferencia: margen sobre materiales', $this->monto($resumen['margen'])],
                ['Margen como porcentaje del ingreso', $porcentaje.' %'],
            ], resumen: true);

            $this->parrafoRotulado("¿Qué significa el {$porcentaje} %?", $resumen['margen'] >= 0
                ? sprintf(
                    'Por cada Bs 100 de ingreso de los pedidos evaluados, quedan aproximadamente Bs %s después de descontar el material registrado. Este indicador no es la utilidad neta de la empresa: solo descuenta materiales.',
                    $this->numero((float) $resumen['margen_pct'], 2),
                )
                : sprintf(
                    'Por cada Bs 100 de ingreso de los pedidos evaluados, el material registrado costó aproximadamente Bs %s: los ingresos no alcanzan a cubrir los materiales.',
                    $this->numero(100 - (float) $resumen['margen_pct'], 2),
                ));

            $this->parrafoRotulado('Cómo se calcula:', 'margen = ingreso del pedido - costo del material registrado. Porcentaje = margen ÷ ingreso × 100. Se incluyen pedidos en acabado o entregados, sin filtro de fecha.', 8);

            if ($resumen['sin_consumo'] > 0) {
                $this->parrafoRotulado('Limitación del resultado:', sprintf(
                    '%s sin consumo de material registrado se muestran al final del detalle y se excluyen del cálculo del margen.',
                    $resumen['sin_consumo'] === 1 ? 'el pedido' : "los {$resumen['sin_consumo']} pedidos",
                ), 8);
            }
        }

        // --- B. Cobros
        $this->apartado('B. Dinero pendiente de cobro');

        if ($cuentas['pedidos'] === []) {
            $this->parrafoRotulado(null, 'No hay saldos pendientes de cobro.');
        } else {
            $this->tabla([
                ['titulo' => 'Indicador al '.$this->generadoEn->format('d/m/Y'), 'ancho' => 66],
                ['titulo' => 'Resultado', 'ancho' => 34],
            ], [
                ['Saldo total pendiente', $this->monto($cuentas['total'])],
                ['Pedidos con saldo pendiente', (string) count($cuentas['pedidos'])],
                [sprintf('Pedidos con más de %d días desde su fecha', InformeReportesService::DIAS_DEUDA_VIEJA), (string) $cuentas['viejas']],
            ], resumen: true);

            $this->parrafoRotulado('Cómo leer la tabla del anexo:', 'Total es el importe del pedido; Pagado es lo abonado; Saldo es lo que falta pagar. La columna Días cuenta el tiempo desde la fecha del pedido hasta el cierre del informe.');
            $this->parrafoRotulado(
                sprintf('Más de %d días no significa necesariamente mora:', InformeReportesService::DIAS_DEUDA_VIEJA),
                'para determinar atraso debe revisarse el plazo de pago acordado con cada cliente.',
                8,
            );
        }

        // --- Acciones: solo las que salen de los números de arriba.
        $acciones = array_values(array_filter([
            match (true) {
                $perdidas === 1 => 'revisar el pedido con pérdida',
                $perdidas > 1 => "revisar los {$perdidas} pedidos con pérdida",
                default => null,
            },
            $resumen['sin_consumo'] > 0 ? 'completar los consumos de materiales pendientes' : null,
            $cuentas['pedidos'] !== [] ? 'dar seguimiento a los saldos según su antigüedad y las condiciones de pago' : null,
        ]));

        if ($acciones !== []) {
            $frase = match (count($acciones)) {
                1 => $acciones[0],
                2 => "{$acciones[0]} y {$acciones[1]}",
                default => "{$acciones[0]}; {$acciones[1]}; y {$acciones[2]}",
            };

            $this->apartado('Acciones sugeridas');
            $this->parrafoRotulado(null, mb_strtoupper(mb_substr($frase, 0, 1)).mb_substr($frase, 1).'.');
        }

        $this->espacio(1);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(8, '', self::OSCURO);
        $this->Cell($this->anchoUtil(), $this->altoLinea(8), $this->t(sprintf(
            'ANEXO DESDE LA PÁGINA %s · Detalle completo para consulta y verificación',
            $this->paginas()['anexo'],
        )), 0, 2);
    }

    /* -----------------------------------------------------------------
     * Anexo
     * -------------------------------------------------------------- */

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
                'De menor a mayor margen. En rojo, los pedidos que costaron más de lo que dejaron.',
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

    /**
     * Dónde empieza cada capítulo. La página 1 los cita antes de que existan,
     * así que se dibujan ventas y margen en un borrador para contar sus
     * hojas: las ventas de un periodo largo pueden ocupar más de una. El
     * resumen es siempre una hoja (su contenido tiene tope).
     *
     * @return array{ventas: int, margen: int, anexo: int}
     */
    private function paginas(): array
    {
        if ($this->paginas !== null) {
            return $this->paginas;
        }

        $borrador = new self($this->informe, $this->titulo, $this->empresa, $this->generadoEn);
        $borrador->paginas = ['ventas' => 0, 'margen' => 0, 'anexo' => 0];

        $borrador->AddPage();
        $borrador->ventas();
        $hojasVentas = $borrador->PageNo();

        $borrador->AddPage();
        $borrador->margenYCobros();

        return $this->paginas = [
            'ventas' => 2,
            'margen' => 2 + $hojasVentas,
            'anexo' => 2 + $borrador->PageNo(),
        ];
    }

    /**
     * Meses del periodo que tuvieron ventas.
     *
     * @return list<array{mes: string, etiqueta: string, nombre: string, parcial: ?string, total: float}>
     */
    private function mesesConVentas(): array
    {
        return array_values(array_filter($this->informe['ventas_por_mes'], fn (array $m): bool => $m['total'] > 0));
    }

    /** "Octubre (al día 27)": el aviso de mes cortado va pegado al nombre. */
    private function nombreMes(array $mes): string
    {
        return $mes['nombre'].($mes['parcial'] !== null ? " ({$mes['parcial']})" : '');
    }

    /**
     * Lectura de la tabla de meses: el mejor mes, los meses cortados por el
     * periodo y los que no tuvieron ventas. Todo sale de la misma tabla.
     *
     * @param  list<array<string, mixed>>  $conVentas
     */
    private function lecturaMeses(array $conVentas, float $total): string
    {
        $mejor = collect($conVentas)->sortByDesc('total')->first();

        $frases = [count($conVentas) === 1
            ? sprintf('todas las ventas del periodo se registraron en %s: %s.', mb_strtolower($mejor['nombre']), $this->monto($mejor['total']))
            : sprintf(
                '%s fue el mes con mayores ventas: %s, equivalente al %s %% del total.',
                mb_strtolower($mejor['nombre']),
                $this->monto($mejor['total']),
                $this->numero($mejor['total'] / $total * 100, 1),
            )];

        // Solo los meses de la tabla: avisar que un mes sin ventas es parcial
        // no le dice nada al lector.
        foreach ($conVentas as $mes) {
            if ($mes['parcial'] !== null) {
                $frases[] = sprintf('%s es un mes parcial, con información %s.', $mes['nombre'], str_starts_with($mes['parcial'], 'al') ? 'hasta el día '.mb_substr($mes['parcial'], 7) : $mes['parcial']);
            }
        }

        // Los meses vacíos se nombran si son pocos; si son muchos, se cuentan.
        $sinVentas = array_values(array_filter($this->informe['ventas_por_mes'], fn (array $m): bool => $m['total'] <= 0));

        if ($sinVentas !== [] && count($conVentas) > 1) {
            $frases[] = count($sinVentas) <= 3
                ? 'No hubo ventas en '.$this->enumerar(array_map(fn (array $m): string => mb_strtolower($m['nombre']), $sinVentas)).'.'
                : sprintf('Hubo %d meses sin ventas.', count($sinVentas));
        }

        return implode(' ', $frases);
    }

    /** @param  array<int, string>  $partes */
    private function enumerar(array $partes): string
    {
        $partes = array_values($partes);
        $ultima = array_pop($partes);

        return $partes === [] ? $ultima : implode(', ', $partes).' y '.$ultima;
    }

    private function pedidos(int $cantidad): string
    {
        return $cantidad === 1 ? '1 pedido' : "{$cantidad} pedidos";
    }
}
