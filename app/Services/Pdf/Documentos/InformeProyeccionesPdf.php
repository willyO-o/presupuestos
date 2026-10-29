<?php

namespace App\Services\Pdf\Documentos;

use App\Services\Reporte\PreguntasNegocioService;
use App\Services\Reporte\RedaccionPronostico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe del BI: "En pocas palabras", las primeras acciones, qué esperar en
 * los próximos meses, una sección por área con gráfico + lectura en texto
 * automático + tabla de la estimación, y un anexo técnico con la ficha de
 * cada pronóstico.
 *
 * Está escrito para quien NO es analista (pedido del usuario, 2026-10-25):
 * el cuerpo habla de "qué pasó", "cómo viene", "qué se espera" y "confianza";
 * R², MAPE, intervalos y nombres de métodos quedan solo en el anexo.
 *
 * Es un documento INTERNO (lleva costos, márgenes y utilidades): se rotula
 * así en el membrete. Recibe el informe ya armado por
 * `InformeProyeccionesService`; acá no se calcula nada, solo se maqueta.
 */
class InformeProyeccionesPdf extends Documento
{
    use DibujaGraficos;

    /**
     * @param  array<string, mixed>  $informe  InformeProyeccionesService::armar().
     * @param  array<string, mixed>  $empresa
     */
    public function __construct(
        private readonly array $informe,
        string $titulo,
        array $empresa,
        Carbon $generadoEn,
    ) {
        // El informe de las pestañas Preguntas y Presupuestos se titula como
        // ellas (va en el pie).
        parent::__construct(match (true) {
            $this->soloPreguntas() => 'Preguntas para decidir',
            $this->soloRentabilidad() => 'Presupuestos y rentabilidad',
            $this->soloAlertas() => 'Informe de alertas',
            default => $titulo,
        }, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        $periodo = $this->informe['periodo'];

        return [
            'tipo' => $this->soloAlertas() ? 'Informe de alertas' : 'Informe del negocio',
            'numero' => mb_strtoupper("{$periodo['etiqueta_desde']} – {$periodo['etiqueta_hasta']}", 'UTF-8'),
            'subtitulo' => $this->informe['alcance'],
            'etiqueta' => ['texto' => 'Uso interno', 'tono' => 'info'],
        ];
    }

    protected function cuerpo(): void
    {
        // El informe de la pestaña Alertas es su propio documento: resumen de
        // revisión y una hoja por prioridad. Sin portada general, sin nota de
        // cierre aparte y sin anexo (no tiene proyecciones que explicar).
        if ($this->soloAlertas()) {
            $this->informeAlertas($this->informe['secciones'][0]);

            return;
        }

        // Los informes de las pestañas Preguntas y Presupuestos abren con su
        // propio resumen para la gerencia en lugar de la portada general.
        if (! $this->soloPreguntas() && ! $this->soloRentabilidad()) {
            $this->datosGenerales();

            if ($this->informe['conclusiones'] !== []) {
                $this->recuadro([
                    ['tipo' => 'titulo', 'texto' => 'En pocas palabras'],
                    ['tipo' => 'lista', 'items' => $this->informe['conclusiones']],
                ], tono: 'destacado');
            }

            $this->accionesPrincipales();
            $this->resumenEjecutivo();
            $this->comoLeer();
        }

        foreach ($this->informe['secciones'] as $seccion) {
            if (isset($seccion['preguntas'])) {
                if (! $this->soloPreguntas()) {
                    $this->AddPage();
                }

                $this->preguntas($seccion['preguntas']);

                continue;
            }

            if ($seccion['clave'] === 'rentabilidad') {
                $this->seccionRentabilidad($seccion);

                continue;
            }

            $this->AddPage();
            $this->tituloSeccion($seccion['titulo']);

            foreach ($seccion['indicadores'] ?? [] as $indicador) {
                $this->indicador($indicador);
            }

            if (isset($seccion['tabla'])) {
                $this->tablaSeccion($seccion['tabla']);
            }

            if (isset($seccion['alertas'])) {
                $this->alertas($seccion['alertas']);
            }

            foreach ($seccion['notas'] ?? [] as $nota) {
                $this->recuadro([['tipo' => 'nota', 'texto' => $nota]], tono: 'aviso');
            }
        }

        // La nota cierra el cuerpo, antes del anexo: al final del anexo solía
        // quedar sola en una hoja casi vacía.
        $this->notaLegal([
            ['texto' => sprintf(
                'Informe generado por el sistema de %s el %s con los datos registrados a esa fecha. ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => 'Las estimaciones suponen que los próximos meses se comportan como los anteriores: son una guía para planificar, no una garantía.', 'negrita' => true],
        ]);

        $this->anexoMetodologico();
    }

    /* -----------------------------------------------------------------
     * Bloques
     * -------------------------------------------------------------- */

    private function datosGenerales(): void
    {
        $periodo = $this->informe['periodo'];
        $meses = $this->informe['meses_proyeccion'];
        $incompleto = $periodo['mes_incompleto'];

        $this->bloqueDatos([
            [
                'rotulo' => 'Periodo analizado',
                'titulo' => "{$periodo['etiqueta_desde']} – {$periodo['etiqueta_hasta']}",
                'lineas' => array_values(array_filter([
                    ['Fechas:', Carbon::parse($periodo['desde'])->format('d/m/Y').' al '.Carbon::parse($periodo['hasta'])->format('d/m/Y')],
                    ['Meses:', (string) $periodo['meses']],
                    $incompleto ? ['Mes en curso:', sprintf('%s (van %d de %d días)', $incompleto['mes'], $incompleto['dias'], $incompleto['de'])] : null,
                ])),
            ],
            [
                'rotulo' => 'Estimaciones para',
                'titulo' => implode(', ', $meses),
                'lineas' => [
                    ['Se calculan con:', 'lo ocurrido en los meses analizados'],
                    ['Datos de:', $this->informe['alcance']],
                ],
            ],
        ]);
    }

    /**
     * Guía breve para quien no es analista: cómo leer los gráficos y qué
     * quiere decir "confianza". El detalle técnico queda en el anexo.
     */
    private function comoLeer(): void
    {
        $this->espacio(2);
        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Cómo leer este informe'],
            ['tipo' => 'lista', 'items' => [
                'Las cifras salen de lo registrado en el sistema durante el periodo indicado arriba.',
                'En cada gráfico, la línea azul es lo que pasó; la línea punteada, lo que se espera si todo sigue igual; y la franja celeste, el rango dentro del cual debería quedar el resultado real.',
                'Confianza alta: los meses siguen una línea clara y la estimación es confiable. Confianza baja: los meses suben y bajan mucho y la estimación es solo una referencia.',
                'Al final hay un anexo con el detalle técnico del cálculo, para quien lo necesite.',
            ]],
        ]);
    }

    /**
     * Las tres acciones con más dinero en juego, sacadas de la pregunta
     * "¿Qué acciones conviene revisar primero?" (solo si el informe incluye
     * Preguntas para decidir). Con la frase completa, no el rótulo corto de
     * su tabla.
     */
    private function accionesPrincipales(): void
    {
        $decisiones = collect($this->informe['secciones'])
            ->flatMap(fn (array $s) => $s['preguntas'] ?? [])
            ->firstWhere('clave', 'decisiones');

        $acciones = array_slice(array_column($decisiones['priorizadas'] ?? [], 'texto'), 0, 3);

        if ($acciones === []) {
            return;
        }

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Lo primero que conviene hacer'],
            ['tipo' => 'lista', 'items' => $acciones],
        ]);
    }

    private function resumenEjecutivo(): void
    {
        $filas = collect($this->informe['secciones'])
            ->flatMap(fn (array $s) => $s['indicadores'] ?? [])
            ->map(fn (array $i): array => [
                ['texto' => $i['resumen']['indicador'], 'negrita' => true],
                $i['resumen']['promedio'],
                $i['resumen']['tendencia'],
                ['texto' => $i['resumen']['proyeccion'], 'negrita' => true],
                $i['resumen']['rango'],
                $i['resumen']['confiabilidad'],
            ])
            ->all();

        if ($filas === []) {
            return;
        }

        $this->espacio(4);
        $mes = collect($this->informe['secciones'])
            ->flatMap(fn (array $s) => $s['indicadores'] ?? [])
            ->pluck('resumen.mes_proyeccion')
            ->filter()
            ->first();

        $this->rotulo($mes ? "Qué esperar en los próximos meses (estimación para {$mes})" : 'Qué esperar en los próximos meses');
        $this->tabla([
            ['titulo' => 'Indicador', 'ancho' => 22],
            ['titulo' => 'Promedio por mes', 'ancho' => 15, 'alineacion' => 'R'],
            ['titulo' => 'Cómo viene', 'ancho' => 17, 'alineacion' => 'R'],
            ['titulo' => 'Se espera', 'ancho' => 16, 'alineacion' => 'R'],
            ['titulo' => 'Podría quedar entre', 'ancho' => 20, 'alineacion' => 'R'],
            ['titulo' => 'Confianza', 'ancho' => 10, 'alineacion' => 'R'],
        ], $filas);
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private function indicador(array $indicador): void
    {
        $this->asegurarEspacio(90);
        $this->espacio(3);

        $this->fuente(10.5, 'B', self::OSCURO);
        $this->SetX(self::MARGEN_LATERAL);
        $this->Cell($this->anchoUtil(), $this->altoLinea(10.5), $this->t($indicador['titulo']), 0, 2);

        $this->fuente(7.5, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [['texto' => $indicador['descripcion']]], $this->altoLinea(7.5));

        $this->graficoLineas($this->graficoDe($indicador));

        foreach ($indicador['lectura'] as $parrafo) {
            $this->parrafo($parrafo);
        }

        $this->tablaProyeccion($indicador);
    }

    /**
     * Series del gráfico de un indicador, con el mismo criterio que la
     * pantalla (Utils/SerieProyectada.js): la proyección arranca en el último
     * mes que usó la recta y pasa por el cierre estimado del mes en curso.
     *
     * @param  array<string, mixed>  $indicador
     * @return array<string, mixed>
     */
    private function graficoDe(array $indicador): array
    {
        $historico = array_values($indicador['historico']);
        $proyeccion = $indicador['proyeccion'];
        $vacio = array_fill(0, count($proyeccion), null);
        $total = count($historico);

        // El mes en curso (fuera de la recta) va como tramo aparte, más
        // claro: con la misma línea, sus pocos días se leerían como un
        // desplome.
        $parcial = ! empty($indicador['metodo']['mes_final_excluido']) && $total > 1;
        $reales = $historico;

        if ($parcial) {
            $reales[$total - 1] = null;
        }

        $series = [[
            'nombre' => 'Lo que pasó',
            'datos' => [...$reales, ...$vacio],
            'color' => self::AZUL,
            'grosor' => 0.6,
        ]];

        if ($parcial && $historico[$total - 1] !== null) {
            $series[] = [
                'nombre' => 'Mes en curso (incompleto)',
                'datos' => [...array_fill(0, $total - 2, null), $historico[$total - 2], $historico[$total - 1], ...$vacio],
                'color' => $this->aclarar(self::AZUL, 0.55),
                'grosor' => 0.4,
            ];
        }

        if ($indicador['media_movil'] !== null) {
            $series[] = [
                'nombre' => 'Promedio últimos 3 meses',
                'datos' => [...array_values($indicador['media_movil']), ...$vacio],
                'color' => self::TENUE,
                'grosor' => 0.35,
            ];
        }

        if ($proyeccion !== []) {
            $cierre = $indicador['metodo']['cierre_estimado'] ?? [];
            $limite = $total - count($cierre);
            $ancla = null;

            for ($i = $limite - 1; $i >= 0; $i--) {
                if ($historico[$i] !== null) {
                    $ancla = $i;

                    break;
                }
            }

            $tramo = fn (string $clave): array => array_map(
                fn (int $i) => match (true) {
                    $i === $ancla => (float) $historico[$i],
                    $i >= $limite => $cierre[$i - $limite][$clave],
                    default => null,
                },
                range(0, $total - 1),
            );

            $series[] = [
                'nombre' => 'Lo que se espera',
                'datos' => [...$tramo('valor'), ...array_column($proyeccion, 'valor')],
                'color' => self::AZUL_OSCURO,
                'punteada' => true,
            ];
            $series[] = [
                'nombre' => 'Rango probable',
                'tipo' => 'banda',
                'inferior' => [...$tramo('inferior'), ...array_column($proyeccion, 'inferior')],
                'superior' => [...$tramo('superior'), ...array_column($proyeccion, 'superior')],
                'color' => self::AZUL,
            ];
        }

        $formato = $indicador['formato'];

        return [
            'labels' => [...$indicador['labels'], ...array_slice($indicador['meses_proyeccion'], 0, count($proyeccion))],
            'series' => $series,
            'inicio_proyeccion' => $proyeccion === [] ? null : $total,
            'umbrales' => $indicador['umbrales'],
            'desde_cero' => $formato !== 'indice',
            'formato' => fn (float $v): string => $this->valorEje($v, $formato),
        ];
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private function tablaProyeccion(array $indicador): void
    {
        if ($indicador['proyeccion'] === []) {
            return;
        }

        $formato = $indicador['formato'];
        $metodo = $indicador['metodo'];
        $filas = [];

        if (! empty($metodo['cierre_estimado']) && ! empty($metodo['mes_final_excluido'])) {
            $cierre = $metodo['cierre_estimado'][0];
            $filas[] = [
                ['texto' => $metodo['mes_final_excluido'], 'nota' => 'Mes en curso: cierre estimado'],
                RedaccionPronostico::formatear($cierre['valor'], $formato),
                RedaccionPronostico::formatear($cierre['inferior'], $formato),
                RedaccionPronostico::formatear($cierre['superior'], $formato),
            ];
        }

        foreach ($indicador['proyeccion'] as $i => $punto) {
            $filas[] = [
                $indicador['meses_proyeccion'][$i],
                ['texto' => RedaccionPronostico::formatear($punto['valor'], $formato), 'negrita' => true],
                RedaccionPronostico::formatear($punto['inferior'], $formato),
                RedaccionPronostico::formatear($punto['superior'], $formato),
            ];
        }

        // Tres o cuatro filas: van juntas, partidas en dos hojas no se leen.
        $this->asegurarEspacio(10 + count($filas) * 9);

        $this->tabla([
            ['titulo' => 'Mes', 'ancho' => 34],
            ['titulo' => 'Lo más probable', 'ancho' => 22, 'alineacion' => 'R'],
            ['titulo' => 'Como mínimo', 'ancho' => 22, 'alineacion' => 'R'],
            ['titulo' => 'Como máximo', 'ancho' => 22, 'alineacion' => 'R'],
        ], $filas);
    }

    /**
     * @param  array{titulo: string, nota: string, columnas: list<string>, filas: list<list<string>>}  $tabla
     */
    private function tablaSeccion(array $tabla): void
    {
        if ($tabla['filas'] === []) {
            return;
        }

        $this->asegurarEspacio(40);
        $this->espacio(4);
        $this->rotulo($tabla['titulo']);
        $this->fuente(7.5, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [['texto' => $tabla['nota']]], $this->altoLinea(7.5));

        $anchos = [24, 13, 15, 16, 21, 11];

        $this->tabla(
            array_map(fn (string $titulo, int $i): array => [
                'titulo' => $titulo,
                'ancho' => $anchos[$i] ?? 10,
                'alineacion' => $i === 0 ? 'L' : 'R',
            ], $tabla['columnas'], array_keys($tabla['columnas'])),
            $tabla['filas'],
        );
    }

    /**
     * @param  list<array{severidad: string, titulo: string, detalle: string}>  $alertas
     */
    private function alertas(array $alertas): void
    {
        if ($alertas === []) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'No hay alertas de costo ni de margen en el periodo.']]);

            return;
        }

        $this->fuente(7.5, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [[
            'texto' => 'Situaciones que pueden estar reduciendo la ganancia. Alta: conviene atenderla hoy. Media: revisarla durante la semana.',
        ]], $this->altoLinea(7.5));
        $this->espacio(2);

        $this->tabla([
            ['titulo' => 'Prioridad', 'ancho' => 12],
            ['titulo' => 'Alerta', 'ancho' => 33],
            ['titulo' => 'Detalle', 'ancho' => 55],
        ], array_map(fn (array $a): array => [
            ['texto' => $a['severidad'] === 'alta' ? 'Alta' : 'Media', 'negrita' => $a['severidad'] === 'alta'],
            ['texto' => $a['titulo'], 'negrita' => true],
            $a['detalle'],
        ], $alertas));
    }

    /**
     * Preguntas para decidir, con la forma del informe modelo que pasó el
     * usuario (2026-10-29): una hoja de resumen para la gerencia y una hoja
     * por pregunta con "Respuesta breve", "Datos que respaldan la respuesta",
     * "Acción sugerida", "Cómo interpretar el resultado" y "Datos utilizados".
     *
     * @param  list<array<string, mixed>>  $preguntas  PreguntasNegocioService::datos()['preguntas'].
     */
    private function preguntas(array $preguntas): void
    {
        if ($this->soloPreguntas()) {
            $this->tramoPie = '';
        }

        $this->resumenPreguntas($preguntas);

        foreach ($preguntas as $indice => $pregunta) {
            $this->AddPage();
            $this->pregunta($indice + 1, count($preguntas), $pregunta);
        }
    }

    /**
     * Hoja de resumen: qué conviene revisar primero (materiales, ventas en
     * rojo y cobros, con las cifras de sus preguntas), cómo está organizado
     * el informe y qué tener en cuenta antes de comparar cifras.
     *
     * @param  list<array<string, mixed>>  $preguntas
     */
    private function resumenPreguntas(array $preguntas): void
    {
        $this->cabeceraPregunta('Resumen para la gerencia', 'Preguntas para decidir', $this->bajadaPeriodo());

        $destacados = $this->destacados(collect($preguntas)->keyBy('clave')->map(fn (array $p): array => $p['cifras'] ?? [])->all());

        if ($destacados !== []) {
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Qué conviene revisar primero'],
                ...array_map(fn (array $d): array => ['tipo' => 'texto', 'trozos' => [
                    ['texto' => "{$d[0]}: ", 'negrita' => true, 'color' => self::OSCURO],
                    ['texto' => $d[1]],
                ]], $destacados),
            ], tono: 'destacado');
        }

        $this->encabezado('Cómo está organizado');

        foreach (collect($preguntas)->map(fn (array $p, int $i): array => $p + ['numero' => $i + 1])->groupBy('grupo') as $grupo => $delGrupo) {
            $numeros = $delGrupo->pluck('numero');
            $rango = match ($numeros->count()) {
                1 => "pregunta {$numeros->first()}",
                2 => "preguntas {$numeros->first()} y {$numeros->last()}",
                default => "preguntas {$numeros->first()} a {$numeros->last()}",
            };

            $this->encabezado((PreguntasNegocioService::GRUPOS[$grupo] ?? $grupo)." · {$rango}", 10);
            $this->cuerpoTexto(self::GRUPOS_INFORME[$grupo] ?? '');
        }

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => 'Antes de comparar cifras'],
            ['tipo' => 'texto', 'texto' => 'Cada pregunta indica los datos que utiliza: no todos los análisis usan los mismos trabajos o pedidos. Los costos pueden combinar materiales registrados con importes presupuestados. Los escenarios no son resultados ya ocurridos.'],
        ], tono: 'aviso');

        $this->espacio(3);
        $this->cuerpoTexto('En cada pregunta: respuesta breve, datos que la respaldan, acción sugerida y límites del cálculo. Al final, un anexo con el detalle técnico.', 7.5, self::SUAVE);
    }

    /** Qué abarca cada grupo de preguntas, para "Cómo está organizado". */
    private const GRUPOS_INFORME = [
        'costos' => 'Costo calculado, aumentos de precios, comparación con lo previsto y materiales que concentran el gasto.',
        'ganancias' => 'Resultado bruto por producto y cliente, descuentos y ventas clasificadas con margen bajo.',
        'escenarios' => 'Efectos estimados de aumentos de materiales y saldos pendientes de cobro.',
        'decisiones' => 'Recomendaciones con el significado de cada monto y la pregunta que las respalda.',
    ];

    /**
     * Las líneas de "Qué conviene revisar primero", con las cifras de las
     * preguntas 2, 8 y 10. Una línea sin datos no se escribe.
     *
     * @param  array<string, array<string, mixed>>  $cifras  Por clave de pregunta.
     * @return list<array{0: string, 1: string}>
     */
    private function destacados(array $cifras): array
    {
        $materiales = $cifras['costos_suben'] ?? [];
        $margen = $cifras['margen_bajo'] ?? [];
        $cobros = $cifras['cobranza'] ?? [];
        $bs = fn (float $valor): string => 'Bs '.RedaccionPronostico::numero($valor, 0);

        return array_values(array_filter([
            isset($materiales['mayor_material']) ? ['Materiales', sprintf(
                '%s aumentó %s y representa %s de costo adicional calculado.',
                $materiales['mayor_material'],
                RedaccionPronostico::numero($materiales['mayor_aumento'], 1).' %',
                $bs($materiales['mayor_costo']),
            )] : null,
            ($margen['rojas'] ?? 0) > 0 ? ['Ventas con margen bajo', sprintf(
                '%s en rojo, por %s. Este importe no representa una pérdida.',
                $margen['rojas'] === 1 ? '1 venta está' : RedaccionPronostico::numero($margen['rojas'], 0).' ventas están',
                $bs($margen['vendido_rojo']),
            )] : null,
            isset($cobros['por_cobrar']) ? ['Cobros', $cobros['por_cobrar'] > 0
                ? sprintf('quedan %s en %s del periodo.', $bs($cobros['por_cobrar']), $cobros['pedidos'] === 1 ? '1 pedido' : RedaccionPronostico::numero($cobros['pedidos'], 0).' pedidos')
                : 'no quedan saldos pendientes en los pedidos del periodo.'] : null,
        ]));
    }

    /**
     * Una pregunta en su hoja.
     *
     * @param  array<string, mixed>  $pregunta
     */
    private function pregunta(int $numero, int $total, array $pregunta): void
    {
        $this->cabeceraPregunta("Pregunta {$numero} de {$total}", $pregunta['pregunta']);

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => 'Respuesta breve'],
            ['tipo' => 'texto', 'trozos' => $this->respuestaDestacada($pregunta['respuesta'])],
        ], tono: 'destacado');

        if ($pregunta['tabla']['filas'] !== []) {
            $this->encabezado('Datos que respaldan la respuesta');
            $this->tablaPregunta($pregunta['tabla']['columnas'], $pregunta['tabla']['filas']);
        }

        $acciones = array_column($pregunta['acciones'], 'texto');

        if ($acciones !== []) {
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Acción sugerida'],
                ['tipo' => 'texto', 'texto' => implode(' ', $acciones)],
            ], tono: 'exito');
        }

        if ($pregunta['interpretacion'] ?? null) {
            $this->encabezado('Cómo interpretar el resultado');
            $this->cuerpoTexto($pregunta['interpretacion']);
        }

        if ($pregunta['base']) {
            $this->encabezado('Datos utilizados');
            $this->cuerpoTexto($pregunta['base'], 7.5);
        }
    }

    /**
     * La respuesta con su primera oración en negrita (lo que contesta),
     * igual que en la pantalla. Corta en un punto seguido de mayúscula o
     * número, así "S.R.L. es…" o "Bs 1.450,00" no parten la frase.
     *
     * @return list<array<string, mixed>>
     */
    private function respuestaDestacada(string $respuesta): array
    {
        if (! preg_match('/^(.+?[.!?])\s+(?=[A-ZÁÉÍÓÚÑ¿¡0-9])(.+)$/su', $respuesta, $partes)) {
            return [['texto' => $respuesta, 'negrita' => true, 'color' => self::OSCURO]];
        }

        return [
            ['texto' => $partes[1].' ', 'negrita' => true, 'color' => self::OSCURO],
            ['texto' => $partes[2]],
        ];
    }

    /**
     * La tabla de una pregunta. Los importes van sin "Bs" (la columna o
     * "Datos utilizados" ya lo dicen) y cada columna toma el ancho que pide
     * su contenido.
     *
     * @param  list<array{titulo: string, formato: string}>  $columnas
     * @param  list<list<mixed>>  $filas
     */
    private function tablaPregunta(array $columnas, array $filas): void
    {
        $textos = array_map(fn (array $fila): array => array_map(
            fn (mixed $valor, int $i): string => $this->celda($valor, $columnas[$i]['formato']),
            $fila,
            array_keys($fila),
        ), $filas);
        $anchos = $this->anchosColumnas($columnas, $textos);

        $this->tabla(
            array_map(fn (array $c, int $i): array => [
                'titulo' => $c['titulo'],
                'ancho' => $anchos[$i],
                'alineacion' => $c['formato'] === 'texto' ? 'L' : 'R',
            ], $columnas, array_keys($columnas)),
            array_map(fn (array $fila): array => array_map(
                fn (string $texto, int $i): array|string => $i === 0 ? ['texto' => $texto, 'negrita' => true] : $texto,
                $fila,
                array_keys($fila),
            ), $textos),
            resumen: true,
        );
    }

    /**
     * Ancho de cada columna (en % del área útil) según lo que ocupa su texto
     * más largo. Si todo entra, el sobrante se reparte en proporción; si no,
     * las cifras se quedan con lo que piden y las columnas de texto se
     * reparten el resto (y bajan de renglón).
     *
     * @param  list<array{titulo: string, formato: string}>  $columnas
     * @param  list<list<string>>  $textos
     * @return list<float>
     */
    private function anchosColumnas(array $columnas, array $textos): array
    {
        $util = $this->anchoUtil();
        $relleno = 2 * self::RESPIRO + 2;

        $naturales = array_map(function (array $columna, int $i) use ($textos, $relleno): float {
            $this->fuente(8.5, $i === 0 ? 'B' : '');
            $celdas = array_map(fn (array $fila): float => $this->GetStringWidth($this->t($fila[$i] ?? '')), $textos);
            $this->fuente(8.5);
            // El título puede bajar de renglón: pide como mucho 30 mm.
            $titulo = min($this->GetStringWidth($this->t($columna['titulo'])), 30.0);

            return max([$titulo, ...$celdas]) + $relleno;
        }, $columnas, array_keys($columnas));

        $total = array_sum($naturales);

        if ($total <= $util) {
            return array_map(fn (float $n): float => $n / $total * 100, $naturales);
        }

        $esTexto = array_map(fn (array $c): bool => $c['formato'] === 'texto', $columnas);
        $fijo = array_sum(array_filter($naturales, fn (float $n, int $i): bool => ! $esTexto[$i], ARRAY_FILTER_USE_BOTH));
        $deTexto = $total - $fijo;
        $resto = $util - $fijo;

        // Sin lugar ni para las cifras: todo en proporción.
        if ($deTexto <= 0 || $resto < $util * 0.3) {
            return array_map(fn (float $n): float => $n / $total * 100, $naturales);
        }

        return array_map(
            fn (float $n, int $i): float => ($esTexto[$i] ? $n / $deTexto * $resto : $n) / $util * 100,
            $naturales,
            array_keys($naturales),
        );
    }

    /**
     * Valor de una celda con el formato de su columna (los mismos nombres que
     * `Utils/FormatoReporte.js::formateador()`). Los importes, sin "Bs".
     */
    private function celda(mixed $valor, string $formato): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($formato) {
            'texto' => (string) $valor,
            'cantidad' => RedaccionPronostico::numero((float) $valor, 0),
            'variacion' => RedaccionPronostico::variacion((float) $valor),
            'bs' => RedaccionPronostico::numero((float) $valor, 0),
            'bs_exacto' => RedaccionPronostico::numero((float) $valor, 2),
            default => RedaccionPronostico::formatear((float) $valor, $formato),
        };
    }

    /**
     * "Resultados del 1 de noviembre de 2025 al 29 de octubre de 2026. Toda la
     * empresa. Octubre incluye 29 de 31 días."
     */
    private function bajadaPeriodo(): string
    {
        $periodo = $this->informe['periodo'];
        $hasta = Carbon::parse($periodo['hasta']);
        $incompleto = $periodo['mes_incompleto'];

        return sprintf(
            'Resultados del %s al %s. %s.%s',
            Carbon::parse($periodo['desde'])->translatedFormat('j \d\e F \d\e Y'),
            $hasta->translatedFormat('j \d\e F \d\e Y'),
            $this->informe['alcance'],
            $incompleto ? sprintf(' %s incluye %d de %d días.', mb_convert_case($hasta->translatedFormat('F'), MB_CASE_TITLE, 'UTF-8'), $incompleto['dias'], $incompleto['de']) : '',
        );
    }

    /* -----------------------------------------------------------------
     * Presupuestos y rentabilidad
     * -------------------------------------------------------------- */

    /**
     * Presupuestos y rentabilidad, en los tres bloques de la pestaña. Si el
     * informe es solo de esta sección, abre con un resumen para la gerencia
     * (como el de Preguntas) en lugar de la portada general.
     *
     * @param  array<string, mixed>  $seccion  InformeProyeccionesService::seccionRentabilidad().
     */
    private function seccionRentabilidad(array $seccion): void
    {
        $indicadores = collect($seccion['indicadores'])->groupBy('bloque');

        if ($this->soloRentabilidad()) {
            $this->tramoPie = '';
            $this->resumenRentabilidad($seccion);
        }

        $this->AddPage();

        if (! $this->soloRentabilidad()) {
            $this->tituloSeccion($seccion['titulo']);
        }

        $this->cabeceraPregunta('Bloque 1 de 3', 'Qué se cotizó y cuánto se aprobó',
            'El monto de los presupuestos emitidos y aprobados cada mes, y hacia dónde va.');

        foreach ($indicadores->get(1, []) as $indicador) {
            $this->indicador($indicador);
        }

        $this->AddPage();
        $this->cabeceraPregunta('Bloque 2 de 3', 'Aprobaciones y relación entre precio y costo',
            'Cuántos presupuestos se emitieron y aprobaron, y si el precio acompaña al costo.');
        $this->aprobacionYPrecio($seccion);

        // Si el bloque 2 dejó lugar (la cola de la tabla mes a mes), el 3
        // empieza en la misma hoja en vez de dejarla casi vacía.
        $this->nuevoBloque();
        $this->cabeceraPregunta('Bloque 3 de 3', 'Rentabilidad y resultado: indicadores distintos',
            'La rentabilidad es un porcentaje sobre el costo; el resultado, un monto en bolivianos.');

        foreach ($indicadores->get(3, []) as $indicador) {
            $this->indicador($indicador);

            if ($indicador['clave'] === 'rentabilidad') {
                $this->semaforoPresupuestos($seccion['semaforo_presupuestos']);
            }
        }

        foreach ($seccion['notas'] ?? [] as $nota) {
            $this->recuadro([['tipo' => 'nota', 'texto' => $nota]], tono: 'aviso');
        }
    }

    /**
     * Un bloque nuevo arranca en hoja nueva, salvo que en la actual quede
     * lugar para su cabecera y el primer gráfico (unos 150 mm): entonces
     * sigue en la misma, con aire de por medio.
     */
    private function nuevoBloque(): void
    {
        if ($this->h - self::MARGEN_INFERIOR - $this->GetY() < 150) {
            $this->AddPage();

            return;
        }

        $this->espacio(8);
    }

    /**
     * Hoja de resumen: lo principal con las cifras del periodo (las mismas
     * tarjetas de la pestaña), qué esperar, qué revisar, cómo está
     * organizado y qué tener en cuenta antes de comparar cifras.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function resumenRentabilidad(array $seccion): void
    {
        $r = $seccion['resumen'];
        $bs = fn (?float $v): string => 'Bs '.RedaccionPronostico::numero($v ?? 0, 0);
        $pct = fn (?float $v): string => $v === null ? '—' : RedaccionPronostico::numero($v, 1).' %';
        $cantidad = fn (int $v): string => RedaccionPronostico::numero($v, 0);

        $this->cabeceraPregunta('Resumen para la gerencia', 'Presupuestos y rentabilidad', $this->bajadaPeriodo());

        $decididos = $r['emitidos'] - $r['pendientes'];
        $lineas = array_values(array_filter([
            ['Presupuestos emitidos', sprintf('%s, por %s.', $cantidad($r['emitidos']), $bs($r['monto_emitido']))],
            $r['tasa_conversion'] !== null ? ['Aprobación', sprintf(
                '%s de los presupuestos decididos (%s aprobados de %s)%s.',
                $pct($r['tasa_conversion']),
                $cantidad($r['aprobados']),
                $cantidad($decididos),
                $r['pendientes'] ? sprintf('; los %s pendientes no se cuentan', $cantidad($r['pendientes'])) : '',
            )] : null,
            $r['rentabilidad'] !== null ? ['Rentabilidad sobre costo', sprintf(
                '%s. Por cada Bs 100 de costo ajustado, el resultado calculado es Bs %s, ya descontados IT e IUE.%s',
                $pct($r['rentabilidad']),
                RedaccionPronostico::numero($r['rentabilidad'], 2),
                $r['margen_sobre_precio'] !== null ? sprintf(' Sobre el precio sin IVA es el %s.', $pct($r['margen_sobre_precio'])) : '',
            )] : null,
            ['Resultado calculado', sprintf('%s en el periodo. No es dinero cobrado.', $bs($r['resultado']))],
            $seccion['precio_costo'] ? ['Precio y costo', $seccion['precio_costo']] : null,
        ]));

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => 'Lo principal'],
            ...array_map(fn (array $l): array => ['tipo' => 'texto', 'trozos' => [
                ['texto' => "{$l[0]}: ", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => $l[1]],
            ]], $lineas),
        ], tono: 'destacado');

        $this->antesDeCompararRentabilidad($seccion);

        $this->resumenEjecutivo();

        if ($seccion['que_revisar'] !== []) {
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Qué revisar'],
                ...array_map(fn (array $paso, int $i): array => ['tipo' => 'texto', 'trozos' => [
                    ['texto' => ($i + 1).'. '.$paso['titulo'].' ', 'negrita' => true, 'color' => self::OSCURO],
                    ['texto' => $paso['detalle']],
                ]], $seccion['que_revisar'], array_keys($seccion['que_revisar'])),
            ], tono: 'exito');
        }

        // Una nota y no un recuadro: así el resumen entra en una hoja.
        $trozos = [
            ['texto' => 'Cómo está organizado: ', 'negrita' => true, 'color' => self::OSCURO],
            ['texto' => 'el bloque 1 muestra el monto cotizado y el aprobado por mes, con su estimación; el bloque 2, la tasa de aprobación, el precio frente al costo y el detalle mes a mes; el bloque 3, la rentabilidad sobre costo con el semáforo y el resultado calculado. Al final, un anexo con el detalle técnico de cada estimación.'],
        ];

        $this->fuente(8, '', self::SUAVE);
        $alto = $this->flujo($this->anchoUtil(), $trozos, $this->altoLinea(8), dibujar: false);
        $this->asegurarEspacio($alto + 4);
        $this->espacio(4);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(8, '', self::SUAVE);
        $this->flujo($this->anchoUtil(), $trozos, $this->altoLinea(8));
    }

    /**
     * "Antes de comparar cifras" del resumen: qué no son los montos, sobre qué
     * se mide la rentabilidad, por qué el promedio de los meses no es el del
     * periodo y cuánta confianza merecen las estimaciones.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function antesDeCompararRentabilidad(array $seccion): void
    {
        $r = $seccion['resumen'];
        $pct = fn (?float $v): string => $v === null ? '—' : RedaccionPronostico::numero($v, 1).' %';
        $promedioMensual = collect($seccion['indicadores'])->firstWhere('clave', 'rentabilidad')['resumen']['promedio'] ?? null;
        $confianzas = collect($seccion['indicadores'])
            ->filter(fn (array $i) => $i['metodo']['suficiente'] ?? false)
            ->map(fn (array $i) => RedaccionPronostico::nivelConfianza($i['metodo']))
            ->unique();

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => 'Antes de comparar cifras'],
            ['tipo' => 'texto', 'texto' => trim(implode(' ', array_filter([
                'Los montos cotizados y aprobados no son dinero cobrado, y el resultado calculado tampoco. La rentabilidad se mide sobre el costo, no sobre las ventas.',
                $r['rentabilidad'] !== null && $promedioMensual
                    ? sprintf('El %s es del periodo completo (resultado total ÷ costo total); en el bloque 3, el promedio de los meses es %s porque cada mes pesa igual.', $pct($r['rentabilidad']), $promedioMensual)
                    : null,
                $confianzas->all() === ['Baja']
                    ? 'Todas las estimaciones de este informe tienen confianza baja: sirven solo como referencia.'
                    : 'Cada estimación indica en su bloque cuánta confianza merece.',
            ])))],
        ], tono: 'aviso');
    }

    /**
     * Bloque 2: la tasa de aprobación con su cuenta, el precio frente al
     * costo (gráfico y lectura) y el detalle mes a mes.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function aprobacionYPrecio(array $seccion): void
    {
        $r = $seccion['resumen'];
        $cantidad = fn (?int $v): string => $v === null ? '—' : RedaccionPronostico::numero($v, 0);

        if ($r['tasa_conversion'] !== null) {
            $cuenta = $r['pendientes']
                ? sprintf('%s ÷ (%s - %s)', $cantidad($r['aprobados']), $cantidad($r['emitidos']), $cantidad($r['pendientes']))
                : sprintf('%s ÷ %s', $cantidad($r['aprobados']), $cantidad($r['emitidos']));

            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Tasa de aprobación: '.RedaccionPronostico::numero($r['tasa_conversion'], 1).' %'],
                ['tipo' => 'texto', 'texto' => sprintf(
                    'Se calcula entre los presupuestos que ya tienen respuesta: %s = %s %%.%s',
                    $cuenta,
                    RedaccionPronostico::numero($r['tasa_conversion'], 1),
                    $r['pendientes'] ? sprintf(' Los %s pendientes no se cuentan porque todavía pueden aprobarse; los rechazados y vencidos, sí.', $cantidad($r['pendientes'])) : '',
                )],
            ], tono: 'destacado');
        }

        $meses = $seccion['meses'];

        $this->encabezado('Precio y costo promedio por presupuesto');
        $this->fuente(7.5, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [['texto' => 'Precio sin IVA frente a costo ajustado, de los mismos presupuestos (los que tienen hoja de costos).']], $this->altoLinea(7.5));

        $this->graficoLineas([
            'labels' => array_column($meses, 'mes'),
            'series' => [
                ['nombre' => 'Precio promedio', 'datos' => array_column($meses, 'precio'), 'color' => self::AZUL, 'grosor' => 0.6],
                ['nombre' => 'Costo promedio', 'datos' => array_column($meses, 'costo'), 'color' => self::AMBAR, 'grosor' => 0.6],
            ],
            'desde_cero' => true,
            'formato' => fn (float $v): string => $this->valorEje($v, 'bs'),
        ], 54);

        if ($seccion['precio_costo']) {
            $this->parrafo($seccion['precio_costo'].' El gráfico no confirma por sí solo la causa: un cambio en el tipo de trabajos que se cotizan también mueve los promedios.');
        }

        $this->encabezado('Mes a mes');
        $incompleto = $this->informe['periodo']['mes_incompleto'];
        $ultimo = count($meses) - 1;

        $this->tabla([
            ['titulo' => 'Mes', 'ancho' => 28],
            ['titulo' => 'Emitidos', 'ancho' => 16, 'alineacion' => 'R'],
            ['titulo' => 'Aprobados', 'ancho' => 16, 'alineacion' => 'R'],
            ['titulo' => 'Precio promedio', 'ancho' => 20, 'alineacion' => 'R'],
            ['titulo' => 'Costo promedio', 'ancho' => 20, 'alineacion' => 'R'],
        ], array_map(fn (array $m, int $i): array => [
            $incompleto && $i === $ultimo ? ['texto' => $m['mes'], 'nota' => 'Mes en curso: parcial'] : $m['mes'],
            $cantidad($m['emitidos']),
            $cantidad($m['aprobados']),
            $m['precio'] === null ? '—' : RedaccionPronostico::formatear($m['precio'], 'bs'),
            $m['costo'] === null ? '—' : RedaccionPronostico::formatear($m['costo'], 'bs'),
        ], $meses, array_keys($meses)));
    }

    /**
     * Cómo salieron los presupuestos emitidos en el semáforo del motor de
     * margen, debajo de la rentabilidad (en la pestaña va en la misma tarjeta).
     *
     * @param  array{conteo: array<string, int>, umbrales: array{verde: float, amarillo: float}}  $semaforo
     */
    private function semaforoPresupuestos(array $semaforo): void
    {
        $c = $semaforo['conteo'];
        $verde = RedaccionPronostico::numero($semaforo['umbrales']['verde'], 0).' %';
        $amarillo = RedaccionPronostico::numero($semaforo['umbrales']['amarillo'], 0).' %';
        $n = fn (int $v): string => RedaccionPronostico::numero($v, 0);
        $total = $c['VERDE'] + $c['AMARILLO'] + $c['ROJO'] + $c['SIN_COSTO'];

        if ($total === 0) {
            return;
        }

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => "Cómo salieron los {$n($total)} presupuestos emitidos"],
            ['tipo' => 'texto', 'trozos' => [
                ['texto' => "{$n($c['VERDE'])} en verde", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => " (aceptar: el resultado supera el {$verde} del costo) · "],
                ['texto' => "{$n($c['AMARILLO'])} en amarillo", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => " (revisar precio: entre {$amarillo} y {$verde}) · "],
                ['texto' => "{$n($c['ROJO'])} en rojo", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => " (no aceptar: hasta {$amarillo})"],
                ['texto' => $c['SIN_COSTO'] ? " · {$n($c['SIN_COSTO'])} sin hoja de costos." : '.'],
            ]],
            ['tipo' => 'texto', 'texto' => 'Es el mismo semáforo que se ve al cotizar. Cuenta todos los presupuestos emitidos; la rentabilidad del gráfico, solo los aprobados.'],
        ]);
    }

    /* -----------------------------------------------------------------
     * Informe de alertas
     * -------------------------------------------------------------- */

    /** Orden en que se leen los tipos dentro de cada prioridad. */
    private const ORDEN_ALERTAS = ['margen_bajo', 'sobrecosto_pedido', 'presupuesto_desactualizado', 'costo_material', 'tendencia_costos'];

    /**
     * Informe de la pestaña Alertas, con la forma del informe modelo que pasó
     * el usuario (2026-10-29): una hoja de resumen de revisión, una hoja por
     * prioridad (tablas por tipo, cada una con su acción sugerida) y, al
     * final, la nota del límite del semáforo y el alcance del documento.
     *
     * @param  array<string, mixed>  $seccion  InformeProyeccionesService::seccionAlertas().
     */
    private function informeAlertas(array $seccion): void
    {
        $this->tramoPie = '';
        $alertas = collect($seccion['alertas']);
        $altas = $alertas->where('severidad', 'alta')->values();
        $medias = $alertas->where('severidad', 'media')->values();
        $u = $seccion['umbrales'];
        $periodo = $this->informe['periodo'];

        $this->cabeceraPregunta('Resumen de revisión', 'Alertas que requieren atención', sprintf(
            'Periodo: %s al %s · %s. Información del sistema generada el %s.',
            Carbon::parse($periodo['desde'])->format('d/m/Y'),
            Carbon::parse($periodo['hasta'])->format('d/m/Y'),
            $this->informe['alcance'],
            $this->selloDeGeneracion(),
        ));

        if ($alertas->isEmpty()) {
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Sin alertas en el periodo'],
                ['tipo' => 'texto', 'texto' => 'Ningún costo subió por encima del umbral, ningún pedido superó lo presupuestado y los presupuestos vivos están en verde.'],
            ], tono: 'exito');
            $this->alcanceAlertas(0);

            return;
        }

        $this->recuadro(array_values(array_filter([
            ['tipo' => 'encabezado', 'texto' => $alertas->count().' '.($alertas->count() === 1 ? 'alerta' : 'alertas').' en el periodo'],
            $altas->isNotEmpty() ? ['tipo' => 'texto', 'trozos' => [
                ['texto' => "{$altas->count()} de prioridad alta: ", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => $this->composicionAlertas($altas, 'alta').'.'],
            ]] : null,
            $medias->isNotEmpty() ? ['tipo' => 'texto', 'trozos' => [
                ['texto' => "{$medias->count()} de prioridad media: ", 'negrita' => true, 'color' => self::OSCURO],
                ['texto' => $this->composicionAlertas($medias, 'media').'.'],
            ]] : null,
        ])), tono: 'destacado');

        $this->encabezado('Qué revisar primero');

        if ($altas->isNotEmpty()) {
            $this->parrafoRotulado('Prioridad alta:', $this->queRevisarPrimero($altas, 'alta'), 9.5);
        }

        if ($medias->isNotEmpty()) {
            $this->parrafoRotulado('Prioridad media:', $this->queRevisarPrimero($medias, 'media'), 9.5);
        }

        $this->criteriosAlertas($alertas, $u);

        $this->recuadro([
            ['tipo' => 'encabezado', 'texto' => 'Cómo leer las tablas'],
            ['tipo' => 'texto', 'texto' => 'Cada fila identifica el presupuesto o pedido, el cliente y las cifras que dispararon la alerta. *Resultado calculado: precio sin IVA menos costo ajustado, IT e IUE, como lo calcula el sistema al cotizar; no es dinero cobrado.'],
        ]);

        $this->espacio(3);
        $this->cuerpoTexto('Este informe contiene alertas por reglas de costo y rentabilidad. No incluye proyecciones ni el listado de anomalías estadísticas.', 7.5, self::SUAVE);

        foreach (['alta' => $altas, 'media' => $medias] as $prioridad => $grupo) {
            if ($grupo->isEmpty()) {
                continue;
            }

            $this->AddPage();
            $this->cabeceraPregunta(
                $grupo->count().' '.($grupo->count() === 1 ? 'alerta' : 'alertas').' · Prioridad '.$prioridad,
                $prioridad === 'alta' ? 'Revisar primero' : 'Revisar durante la semana',
            );

            foreach (self::ORDEN_ALERTAS as $tipo) {
                $delTipo = $grupo->where('tipo', $tipo)->values();

                if ($delTipo->isNotEmpty()) {
                    $this->grupoAlertas($tipo, $prioridad, $delTipo, $u);
                }
            }

            $this->espacio(3);
            $this->cuerpoTexto('*Resultado calculado por el sistema al cotizar; no es dinero cobrado.', 7.5, self::SUAVE);
        }

        $this->notaLimiteSemaforo($alertas->where('tipo', 'margen_bajo'), $u);
        $this->alcanceAlertas($alertas->count());
    }

    /**
     * "7 presupuestos con rentabilidad baja y 1 pedido con costo de material
     * superior al previsto".
     *
     * @param  Collection<int, array<string, mixed>>  $grupo
     */
    private function composicionAlertas(Collection $grupo, string $prioridad): string
    {
        $plural = fn (int $n, string $uno, string $varios): string => $n.' '.($n === 1 ? $uno : $varios);

        $partes = collect(self::ORDEN_ALERTAS)
            ->map(function (string $tipo) use ($grupo, $prioridad, $plural): ?string {
                $n = $grupo->where('tipo', $tipo)->count();

                return $n === 0 ? null : match ($tipo) {
                    'margen_bajo' => $prioridad === 'alta'
                        ? $plural($n, 'presupuesto en rojo, con rentabilidad baja', 'presupuestos en rojo, con rentabilidad baja')
                        : $plural($n, 'presupuesto en amarillo, cuyo precio conviene revisar', 'presupuestos en amarillo, cuyo precio conviene revisar'),
                    'sobrecosto_pedido' => $plural($n, 'pedido con costo de material superior al previsto', 'pedidos con costo de material superior al previsto'),
                    'presupuesto_desactualizado' => $plural($n, 'presupuesto pendiente con precios de material viejos', 'presupuestos pendientes con precios de material viejos'),
                    'costo_material' => $plural($n, 'material con un aumento de precio', 'materiales con un aumento de precio'),
                    'tendencia_costos' => 'la tendencia del costo de los materiales',
                };
            })
            ->filter()
            ->values()
            ->all();

        $ultima = array_pop($partes);

        return $partes === [] ? (string) $ultima : implode(', ', $partes).' y '.$ultima;
    }

    /**
     * Lo primero que conviene hacer en cada prioridad, tipo por tipo.
     *
     * @param  Collection<int, array<string, mixed>>  $grupo
     */
    private function queRevisarPrimero(Collection $grupo, string $prioridad): string
    {
        $cuenta = fn (string $tipo): int => $grupo->where('tipo', $tipo)->count();
        $frases = [];

        if ($n = $cuenta('margen_bajo')) {
            $frases[] = $prioridad === 'alta'
                ? ($n === 1 ? 'revisar el presupuesto en rojo antes de aceptarlo' : "revisar los {$n} presupuestos en rojo antes de aceptarlos").': revisar precio y costos y solicitar aprobación de gerencia.'
                : ($n === 1 ? 'revisar precio y costos del presupuesto en amarillo' : "revisar precios y costos de los {$n} presupuestos en amarillo").' durante la semana.';
        }

        if ($n = $cuenta('sobrecosto_pedido')) {
            $frases[] = ($frases === [] ? 'revisar ' : 'Revisar también ').($n === 1 ? 'el pedido' : "los {$n} pedidos").' con exceso de material registrado.';
        }

        if ($n = $cuenta('presupuesto_desactualizado')) {
            $frases[] = ($frases === [] ? 'recotizar ' : 'Recotizar ').($n === 1 ? 'el presupuesto con precios viejos' : "los {$n} presupuestos con precios viejos").' antes de que el cliente lo apruebe.';
        }

        if ($cuenta('costo_material') || $cuenta('tendencia_costos')) {
            $frases[] = ($frases === [] ? 'revisar ' : 'Revisar ').'los presupuestos pendientes que usan los materiales que subieron.';
        }

        return implode(' ', $frases);
    }

    /**
     * "Criterios para interpretar las alertas": solo los de los tipos que
     * aparecen en el informe.
     *
     * @param  Collection<int, array<string, mixed>>  $alertas
     * @param  array<string, float>  $u
     */
    private function criteriosAlertas(Collection $alertas, array $u): void
    {
        $this->encabezado('Criterios para interpretar las alertas');
        $pct = fn (float $v): string => RedaccionPronostico::numero($v, 0).' %';
        $margenes = $alertas->where('tipo', 'margen_bajo');

        if ($margenes->isNotEmpty()) {
            $this->parrafoRotulado('Rojo:', sprintf(
                'rentabilidad sobre costo de hasta %s. Amarillo: más de %s y hasta %s, según la clasificación del sistema. Los porcentajes se refieren al costo, no al monto de venta.',
                $pct($u['margen_amarillo']), $pct($u['margen_amarillo']), $pct($u['margen_verde']),
            ), 9.5);

            $todosPositivos = $margenes->every(fn (array $a) => ($a['resultado'] ?? 0) > 0);
            $this->parrafoRotulado('Una rentabilidad baja no significa necesariamente pérdida.', $todosPositivos
                ? 'Los resultados de los presupuestos listados son positivos, pero están por debajo o en el límite de referencia que usa el sistema.'
                : 'Los presupuestos con resultado negativo se ven con signo menos en la tabla.', 9.5);
        }

        if ($alertas->contains('tipo', 'sobrecosto_pedido')) {
            $this->parrafoRotulado('Material superior al previsto:', 'compara el costo del material registrado con el presupuestado. La causa debe verificarse; la diferencia no demuestra por sí sola desperdicio.', 9.5);
        }

        if ($alertas->contains('tipo', 'presupuesto_desactualizado')) {
            $this->parrafoRotulado('Precios viejos:', 'compara el precio actual de los materiales con el que tenía el presupuesto al cotizarse, al mismo precio de venta.', 9.5);
        }

        if ($alertas->contains(fn (array $a) => in_array($a['tipo'], ['costo_material', 'tendencia_costos'], true))) {
            $this->parrafoRotulado('Precios de material:', sprintf('un aumento de %s o más en un solo cambio, o una tendencia de %s o más por mes. Son de toda la empresa.', $pct($u['aumento_costo']), $pct($u['tendencia_mensual'])), 9.5);
        }
    }

    /**
     * Las alertas de un tipo dentro de una prioridad: frase de entrada, tabla
     * (o el detalle de cada pedido) y la acción sugerida.
     *
     * @param  Collection<int, array<string, mixed>>  $delTipo
     * @param  array<string, float>  $u
     */
    private function grupoAlertas(string $tipo, string $prioridad, Collection $delTipo, array $u): void
    {
        $n = $delTipo->count();
        $pct = fn (float $v): string => RedaccionPronostico::numero($v, 0).' %';
        $bs = fn (?float $v): string => $v === null ? '—' : RedaccionPronostico::numero($v, 2);
        $metrica = fn (array $a, int $i): string => preg_replace('/^Bs /', '', $a['metricas'][$i]['valor'] ?? '—');
        $tono = $prioridad === 'alta' ? 'alerta' : 'aviso';

        if ($tipo === 'margen_bajo') {
            $this->encabezado($prioridad === 'alta' ? 'Presupuestos en rojo' : 'Presupuestos cuyo precio conviene revisar');
            $this->cuerpoTexto($prioridad === 'alta'
                ? sprintf('%s en rojo por una rentabilidad sobre costo de hasta %s.', $n === 1 ? 'Un presupuesto está clasificado' : "{$n} presupuestos están clasificados", $pct($u['margen_amarillo']))
                : sprintf('%s en amarillo por el sistema: su rentabilidad sobre costo está entre %s y %s.', $n === 1 ? 'Este presupuesto está clasificado' : 'Estos presupuestos están clasificados', $pct($u['margen_amarillo']), $pct($u['margen_verde'])));

            $this->tabla([
                ['titulo' => 'Presupuesto', 'ancho' => 26],
                ['titulo' => 'Cliente', 'ancho' => 42],
                ['titulo' => 'Resultado (Bs)*', 'ancho' => 16, 'alineacion' => 'R'],
                ['titulo' => 'Sobre costo', 'ancho' => 16, 'alineacion' => 'R'],
            ], $delTipo->map(fn (array $a): array => [
                $a['referencia'] ?? '—',
                $a['cliente'] ?? '—',
                isset($a['resultado']) ? $bs($a['resultado']) : $metrica($a, 0),
                $a['metricas'][1]['valor'] ?? '—',
            ])->all(), resumen: true);

            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => $prioridad === 'alta' ? 'Acción sugerida para estos presupuestos' : 'Acción sugerida'],
                ['tipo' => 'texto', 'texto' => $prioridad === 'alta'
                    ? 'Revisar precio de venta y hoja de costos; solicitar aprobación de gerencia antes de aceptar las condiciones.'
                    : 'Revisar precio de venta, costos incluidos y condiciones comerciales antes de confirmar o modificar el presupuesto. La alerta orienta la revisión; no demuestra por sí sola una pérdida.'],
            ], tono: $tono);

            return;
        }

        if ($tipo === 'sobrecosto_pedido') {
            $this->encabezado($n === 1 ? 'Pedido con costo de material superior al presupuesto' : 'Pedidos con costo de material superior al presupuesto');

            foreach ($delTipo as $a) {
                $exceso = $a['valor'] ?? null;
                $this->parrafoRotulado($a['referencia'] ?? 'Pedido', sprintf(
                    '· El costo registrado fue %s mayor que el previsto%s.',
                    $exceso === null ? '' : RedaccionPronostico::numero($exceso, 1).' %',
                    $a['cliente'] ? " (cliente: {$a['cliente']})" : '',
                ), 9.5);

                if ($a['comparacion'] ?? null) {
                    $this->recuadro([
                        ['tipo' => 'encabezado', 'texto' => 'Datos que respaldan la alerta'],
                        ...array_map(fn (array $c): array => ['tipo' => 'texto', 'trozos' => [
                            ['texto' => "{$c['etiqueta']}: "],
                            ['texto' => $c['texto'].'.', 'negrita' => true, 'color' => self::OSCURO],
                        ]], $a['comparacion']),
                    ], tono: 'aviso');
                }
            }

            $this->espacio(2);
            $this->parrafoRotulado('Acción sugerida:', 'verificar consumos registrados, cambios en el trabajo y hoja de costos. Revisar cortes y sobrantes si corresponde, sin atribuir la diferencia a desperdicio antes de comprobarla.', 9.5);

            return;
        }

        if ($tipo === 'presupuesto_desactualizado') {
            $this->encabezado($n === 1 ? 'Presupuesto con precios de material viejos' : 'Presupuestos con precios de material viejos');
            $this->cuerpoTexto('Pendientes cuyos materiales cuestan hoy más que cuando se cotizaron. El resultado es el que quedaría si el cliente lo acepta hoy al mismo precio.');
            $this->tabla([
                ['titulo' => 'Presupuesto', 'ancho' => 26],
                ['titulo' => 'Cliente', 'ancho' => 38],
                ['titulo' => 'Aumento de materiales', 'ancho' => 18, 'alineacion' => 'R'],
                ['titulo' => 'Resultado hoy (Bs)*', 'ancho' => 18, 'alineacion' => 'R'],
            ], $delTipo->map(fn (array $a): array => [
                $a['referencia'] ?? '—', $a['cliente'] ?? '—', $a['metricas'][0]['valor'] ?? '—', $metrica($a, 1),
            ])->all(), resumen: true);
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Acción sugerida'],
                ['tipo' => 'texto', 'texto' => 'Recotizar con los precios actuales antes de que el cliente apruebe: revisar los precios de los materiales en la hoja de costos y el precio ofrecido.'],
            ], tono: $tono);

            return;
        }

        if ($tipo === 'costo_material') {
            $this->encabezado($n === 1 ? 'Material con un aumento de precio' : 'Materiales con un aumento de precio');
            $this->tabla([
                ['titulo' => 'Material', 'ancho' => 40],
                ['titulo' => 'Precio anterior', 'ancho' => 20, 'alineacion' => 'R'],
                ['titulo' => 'Precio actual', 'ancho' => 20, 'alineacion' => 'R'],
                ['titulo' => 'Aumento', 'ancho' => 20, 'alineacion' => 'R'],
            ], $delTipo->map(fn (array $a): array => [
                preg_replace('/ subió .*$/u', '', $a['titulo']),
                $a['metricas'][0]['valor'] ?? '—',
                $a['metricas'][1]['valor'] ?? '—',
                '+'.RedaccionPronostico::numero($a['valor'] ?? 0, 1).' %',
            ])->all(), resumen: true);
            $this->recuadro([
                ['tipo' => 'encabezado', 'texto' => 'Acción sugerida'],
                ['tipo' => 'texto', 'texto' => 'Revisar los presupuestos pendientes que usan estos materiales y comparar proveedores. Los presupuestos nuevos ya salen con el precio actualizado.'],
            ], tono: $tono);

            return;
        }

        // Tendencia de costos: una sola alerta, en prosa.
        foreach ($delTipo as $a) {
            $this->encabezado('Tendencia del costo de los materiales');
            $this->cuerpoTexto($a['detalle']);
        }
    }

    /**
     * Un presupuesto que figura con el porcentaje del límite ("30,0 %") pero
     * está del otro lado: se explica con el valor sin redondear, para que no
     * parezca un error de clasificación.
     *
     * @param  Collection<int, array<string, mixed>>  $margenes
     * @param  array<string, float>  $u
     */
    private function notaLimiteSemaforo(Collection $margenes, array $u): void
    {
        $limites = ['media' => (float) $u['margen_verde'], 'alta' => (float) $u['margen_amarillo']];

        $enElLimite = $margenes->filter(fn (array $a) => isset($a['rentabilidad'])
            && round($a['rentabilidad'], 1) >= $limites[$a['severidad']]);

        if ($enElLimite->isEmpty()) {
            return;
        }

        $this->encabezado('Nota sobre el límite del semáforo');

        foreach ($enElLimite as $a) {
            $limite = $limites[$a['severidad']];
            $color = $a['severidad'] === 'media' ? 'amarillo' : 'rojo';
            $siguiente = $a['severidad'] === 'media' ? 'verde' : 'amarillo';
            $porcentaje = fn (float $v, int $d): string => RedaccionPronostico::numero($v, $d).' %';

            // El motor clasifica con "mayor que": justo en el límite queda en
            // la zona de abajo.
            $explicacion = abs($a['rentabilidad'] - $limite) < 0.00005
                ? sprintf('sin redondear es exactamente %s, y el %s empieza por encima de ese valor', $porcentaje($limite, 0), $siguiente)
                : sprintf(
                    'sin redondear, su rentabilidad es %s, apenas por debajo del %s que lo separa del %s',
                    $porcentaje($a['rentabilidad'], round($a['rentabilidad'], 2) >= $limite ? 3 : 2),
                    $porcentaje($limite, 0),
                    $siguiente,
                );

            $this->cuerpoTexto(sprintf(
                'El presupuesto %s figura en %s con %s: %s. La clasificación del sistema es correcta.',
                $a['referencia'],
                $color,
                $porcentaje($a['rentabilidad'], 1),
                $explicacion,
            ), 8.5);
        }
    }

    private function alcanceAlertas(int $total): void
    {
        $this->encabezado('Alcance del documento');
        $this->cuerpoTexto(sprintf(
            'Este documento reúne %s del periodo indicado. Las acciones sugeridas requieren revisar cada presupuesto o pedido y sus condiciones particulares. No contiene pronósticos ni representa una auditoría de los registros.',
            $total === 1 ? '1 alerta' : "{$total} alertas",
        ), 8.5);
    }

    /**
     * Apertura de una hoja de preguntas: "PREGUNTA 2 DE 11" chico, la
     * pregunta en grande y, si hay, una bajada.
     */
    private function cabeceraPregunta(string $rotulo, string $titulo, ?string $bajada = null): void
    {
        $ancho = $this->anchoUtil();

        $this->espacio(2);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(8, '', self::OSCURO);
        $this->Cell($ancho, $this->altoLinea(8), $this->t(mb_strtoupper($rotulo, 'UTF-8')), 0, 2);

        $this->espacio(2);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(18, 'B', self::AZUL_OSCURO);
        $this->flujo($ancho, [['texto' => $titulo]], $this->altoLinea(18));

        if ($bajada !== null) {
            $this->espacio(1);
            $this->SetX(self::MARGEN_LATERAL);
            $this->fuente(9.5, '', self::TEXTO);
            $this->flujo($ancho, [['texto' => $bajada]], $this->altoLinea(9.5));
        }

        $this->espacio(1);
    }

    /** Título de un bloque de la hoja ("Datos utilizados"): azul y en negrita. */
    private function encabezado(string $texto, float $tamano = 11): void
    {
        $this->asegurarEspacio(20);
        $this->espacio($tamano >= 11 ? 5 : 3);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente($tamano, 'B', self::AZUL_OSCURO);
        $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea($tamano));
        $this->espacio(1);
    }

    /**
     * Párrafo del cuerpo de la hoja. 9,5 para lo que se lee; 7,5 para
     * "Datos utilizados" y las notas.
     *
     * @param  array<int, int>  $color
     */
    private function cuerpoTexto(string $texto, float $tamano = 9.5, array $color = self::TEXTO): void
    {
        $this->fuente($tamano, '', $color);
        $alto = $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea($tamano), dibujar: false);

        $this->asegurarEspacio($alto + 1);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente($tamano, '', $color);
        $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea($tamano));
    }

    /** El informe de la pestaña Preguntas: solo esa sección. */
    private function soloPreguntas(): bool
    {
        return array_column($this->informe['secciones'], 'clave') === ['preguntas'];
    }

    /** El informe de la pestaña Presupuestos y rentabilidad: solo esa sección. */
    private function soloRentabilidad(): bool
    {
        return array_column($this->informe['secciones'], 'clave') === ['rentabilidad'];
    }

    /** El informe de la pestaña Alertas: solo esa sección. */
    private function soloAlertas(): bool
    {
        return array_column($this->informe['secciones'], 'clave') === ['alertas'];
    }

    /**
     * Detalle técnico en apartados numerados; cada fórmula va aparte, en su
     * recuadro, en vez de perdida dentro del párrafo. Después, la ficha de
     * cada indicador. Solo describe lo que calcula `Pronostico`.
     */
    private function anexoMetodologico(): void
    {
        $this->AddPage();

        if ($this->soloPreguntas() || $this->soloRentabilidad()) {
            $this->tramoPie = 'Anexo técnico';
        }

        $this->tituloSeccion('Anexo metodológico (detalle técnico)');
        $this->parrafo('Esta sección es para quien quiera revisar cómo se calcularon las estimaciones. No hace falta leerla para entender el informe.');

        foreach ($this->apartadosDelAnexo() as $numero => $apartado) {
            $this->apartadoAnexo($numero + 1, $apartado);
        }

        $periodo = $this->informe['periodo'];
        $hayIndicadores = collect($this->informe['secciones'])->contains(fn (array $s): bool => ($s['indicadores'] ?? []) !== []);

        // Sin indicadores (informe de Preguntas) la ficha quedaría vacía.
        if (! $hayIndicadores) {
            return;
        }

        $this->subtitulo('Ficha de cada estimación', 'Los datos con que se calculó cada indicador de este informe.');

        foreach ($this->informe['secciones'] as $seccion) {
            foreach ($seccion['indicadores'] ?? [] as $indicador) {
                $ficha = RedaccionPronostico::ficha($indicador['metodo'], $periodo['mes_incompleto']);

                $this->asegurarEspacio(30);
                $this->espacio(2);
                $this->rotulo($indicador['titulo']);
                $this->tabla([
                    ['titulo' => 'Aspecto', 'ancho' => 18],
                    ['titulo' => 'Detalle', 'ancho' => 82],
                ], array_map(fn (array $r): array => [
                    ['texto' => $r['rotulo'], 'negrita' => true],
                    $r['texto'],
                ], $ficha));
            }
        }
    }

    /**
     * Los seis apartados del anexo: texto antes de la fórmula, la fórmula
     * (si tiene) y la explicación de sus términos.
     *
     * Las fórmulas se escriben con signos que existen en cp1252 (la fuente
     * del PDF): "raíz(...)" en lugar de √ e "y(x0)" en lugar de ŷ.
     *
     * @return list<array{titulo: string, texto: string, formula?: string, despues?: string}>
     */
    private function apartadosDelAnexo(): array
    {
        $meses = count($this->informe['meses_proyeccion']);
        $confianza = $this->informe['confianza'];

        return [
            [
                'titulo' => 'Método de cálculo de las proyecciones',
                'texto' => "Cada indicador se resume en un valor por mes. La tendencia es una recta de mínimos cuadrados, donde x es el número de mes (0, 1, 2…) e y el valor del mes; la recta se prolonga {$meses} meses hacia adelante.",
                'formula' => 'y = a + b · x',
                'despues' => 'Es deliberadamente simple: con el volumen de una empresa de este tamaño, un modelo más complejo no tiene datos suficientes para ser más exacto, y en cambio deja de poder explicarse.',
            ],
            [
                'titulo' => 'Tratamiento de los meses incompletos',
                'texto' => 'En los totales del mes (pedidos, montos, consumo, utilidad) el mes en curso no entra en la recta, porque sus pocos días se leerían como una caída. Se muestra igual en el gráfico y se estima cómo cerraría. Los promedios, porcentajes y precios no dependen de cuántos días pasaron y usan el mes entero.',
            ],
            [
                'titulo' => 'Rango probable de las proyecciones',
                'texto' => "Rango probable: intervalo de predicción al {$confianza} %. Para el mes x0 vale:",
                'formula' => 'y(x0) ± t × s × raíz(1 + 1/n + (x0 - media de x)² / Sxx)',
                'despues' => 'Donde t es la t de Student con n - 2 grados de libertad, s el error típico de la recta, raíz(SSE / (n - 2)), y Sxx la suma de (x - media de x)². El rango se ensancha cuanto más lejos se proyecta.',
            ],
            [
                'titulo' => 'Qué tan bien se ajusta la recta (R²)',
                'texto' => 'El coeficiente de determinación indica qué parte de la variación entre meses explica la recta:',
                'formula' => 'R² = 1 - SSE / SST',
                'despues' => 'SSE es la suma de los cuadrados de la distancia de cada mes a la recta y SST la de cada mes a su promedio. Se lee fuerte desde 0,7, moderado entre 0,4 y 0,7 y débil por debajo.',
            ],
            [
                'titulo' => 'Validación con meses reales',
                'texto' => "Se ocultan los últimos {$meses} meses reales (tantos como se proyectan), se pronostican con los anteriores y se mide el error porcentual absoluto medio (MAPE):",
                'formula' => 'MAPE = promedio de |real - estimado| / |real| × 100',
                'despues' => 'Los meses con valor real 0 no entran. Se compara la recta con Holt (suavizado exponencial doble, que adapta nivel y tendencia mes a mes, con alfa y beta elegidos por mínimo error) y con la media móvil de 3 meses (sin tendencia). Si otro modelo se equivoca menos, el informe lo advierte.',
            ],
            [
                'titulo' => 'Datos mínimos',
                'texto' => 'Se necesitan 3 meses con datos para trazar una recta y 6 meses de entrenamiento, además de los validados, para medir el error.',
            ],
        ];
    }

    /**
     * Un apartado del anexo: "01 Título", el texto, la fórmula en un
     * recuadro centrado y la explicación de sus términos. Va entero en una
     * página: una fórmula separada de su explicación no se entiende.
     *
     * @param  array{titulo: string, texto: string, formula?: string, despues?: string}  $apartado
     */
    private function apartadoAnexo(int $numero, array $apartado): void
    {
        $altoParrafo = function (string $texto): float {
            $this->fuente(8.5);

            return $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea(8.5), dibujar: false) + 1.5;
        };

        // Título + texto + recuadro (4 de margen, 6 de relleno, una línea de
        // 10 pt y 3 de respiro) + explicación.
        $this->asegurarEspacio(
            3 + $this->altoLinea(10) + 1
            + $altoParrafo($apartado['texto'])
            + (isset($apartado['formula']) ? 13 + $this->altoLinea(10) : 0)
            + (isset($apartado['despues']) ? $altoParrafo($apartado['despues']) : 0)
        );
        $this->espacio(3);

        $this->fuente(10, 'B', self::AZUL_OSCURO);
        $this->SetX(self::MARGEN_LATERAL);
        $this->Cell($this->anchoUtil(), $this->altoLinea(10), $this->t(sprintf('%02d  %s', $numero, $apartado['titulo'])), 0, 2);
        $this->espacio(1);

        $this->parrafo($apartado['texto']);

        if (isset($apartado['formula'])) {
            $this->recuadro([['tipo' => 'formula', 'texto' => $apartado['formula']]], tono: 'destacado', centrado: true);
            $this->espacio(3);
        }

        if (isset($apartado['despues'])) {
            $this->parrafo($apartado['despues']);
        }
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /**
     * Valor compacto para el eje Y: "Bs 120 mil" en vez de "Bs 120.000",
     * si no las etiquetas no entran en el margen.
     */
    private function valorEje(float $valor, string $formato): string
    {
        if ($formato === 'cantidad') {
            return RedaccionPronostico::numero($valor, floor($valor) == $valor ? 0 : 1);
        }

        if ($formato !== 'bs') {
            return RedaccionPronostico::formatear($valor, $formato);
        }

        return match (true) {
            abs($valor) >= 1_000_000 => 'Bs '.RedaccionPronostico::numero($valor / 1_000_000, 1).' M',
            abs($valor) >= 1_000 => 'Bs '.RedaccionPronostico::numero($valor / 1_000, 0).' mil',
            default => 'Bs '.RedaccionPronostico::numero($valor, 0),
        };
    }
}
