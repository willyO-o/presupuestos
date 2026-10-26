<?php

namespace App\Services\Pdf\Documentos;

use App\Services\Reporte\PreguntasNegocioService;
use App\Services\Reporte\RedaccionPronostico;
use Illuminate\Support\Carbon;

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
        parent::__construct($titulo, $empresa, $generadoEn);
    }

    protected function identificacion(): array
    {
        $periodo = $this->informe['periodo'];

        return [
            'tipo' => 'Informe del negocio',
            'numero' => mb_strtoupper("{$periodo['etiqueta_desde']} – {$periodo['etiqueta_hasta']}", 'UTF-8'),
            'subtitulo' => $this->informe['alcance'],
            'etiqueta' => ['texto' => 'Uso interno', 'tono' => 'info'],
        ];
    }

    protected function cuerpo(): void
    {
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

        foreach ($this->informe['secciones'] as $seccion) {
            $this->AddPage();
            $this->tituloSeccion($seccion['titulo']);

            if (isset($seccion['preguntas'])) {
                $this->preguntas($seccion['preguntas']);
            }

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

        $this->anexoMetodologico();

        $this->notaLegal([
            ['texto' => sprintf(
                'Informe generado por el sistema de %s el %s con los datos registrados a esa fecha. ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => 'Las estimaciones suponen que los próximos meses se comportan como los anteriores: son una guía para planificar, no una garantía.', 'negrita' => true],
        ]);
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
     * "¿Qué decisiones…?" (solo si el informe incluye Preguntas para decidir).
     */
    private function accionesPrincipales(): void
    {
        $decisiones = collect($this->informe['secciones'])
            ->flatMap(fn (array $s) => $s['preguntas'] ?? [])
            ->firstWhere('clave', 'decisiones');

        $acciones = array_slice(array_column($decisiones['tabla']['filas'] ?? [], 0), 0, 3);

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
     * Preguntas para decidir: cada una con su respuesta destacada, la tabla
     * corta, qué hacer y con qué datos se respondió.
     *
     * @param  list<array<string, mixed>>  $preguntas  PreguntasNegocioService::datos()['preguntas'].
     */
    private function preguntas(array $preguntas): void
    {
        $this->fuente(7.5, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [[
            'texto' => 'Cada respuesta se basa en los datos registrados en el periodo e incluye su cifra. Las acciones de la última pregunta resumen las de las anteriores.',
        ]], $this->altoLinea(7.5));

        $grupo = null;

        foreach ($preguntas as $indice => $pregunta) {
            if ($pregunta['grupo'] !== $grupo) {
                $grupo = $pregunta['grupo'];
                $this->asegurarEspacio(50);
                $this->espacio(3);
                $this->rotulo(PreguntasNegocioService::GRUPOS[$grupo] ?? $grupo);
            }

            $this->pregunta($indice + 1, $pregunta);
        }
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    private function pregunta(int $numero, array $pregunta): void
    {
        $this->subtitulo("{$numero}. {$pregunta['pregunta']}");
        $this->recuadro([['tipo' => 'nota', 'texto' => $pregunta['respuesta']]], tono: 'destacado');

        $columnas = $pregunta['tabla']['columnas'];

        if ($pregunta['tabla']['filas'] !== []) {
            $this->espacio(2);
            $this->tabla(
                array_map(fn (array $c, int $i): array => [
                    'titulo' => $c['titulo'],
                    'ancho' => $this->anchoColumna($i, count($columnas)),
                    'alineacion' => $c['formato'] === 'texto' ? 'L' : 'R',
                ], $columnas, array_keys($columnas)),
                array_map(fn (array $fila): array => array_map(
                    fn (mixed $valor, int $i): array|string => $i === 0
                        ? ['texto' => (string) $valor, 'negrita' => true]
                        : $this->celda($valor, $columnas[$i]['formato']),
                    $fila,
                    array_keys($fila),
                ), $pregunta['tabla']['filas']),
            );
        }

        $acciones = array_column($pregunta['acciones'], 'texto');

        if ($acciones !== [] && $pregunta['clave'] !== 'decisiones') {
            $this->recuadro([
                ['tipo' => 'titulo', 'texto' => 'Qué hacer'],
                ['tipo' => 'lista', 'items' => $acciones],
            ]);
        }

        foreach (array_filter([$pregunta['aviso'] ? "Importante: {$pregunta['aviso']}" : null, $pregunta['base'] ? "Datos utilizados: {$pregunta['base']}" : null]) as $nota) {
            $this->fuente(7, '', self::SUAVE);
            $this->SetX(self::MARGEN_LATERAL);
            $this->flujo($this->anchoUtil(), [['texto' => $nota]], $this->altoLinea(7));
        }

        $this->espacio(3);
    }

    /**
     * Ancho (en % del área útil) de cada columna: la primera es el nombre o
     * la acción y se lleva más; el resto se reparte.
     */
    private function anchoColumna(int $indice, int $total): float
    {
        $primera = match (true) {
            $total <= 3 => 60.0,
            $total <= 5 => 32.0,
            default => 26.0,
        };

        return $indice === 0 ? $primera : (100 - $primera) / max($total - 1, 1);
    }

    /**
     * Valor de una celda con el formato de su columna (los mismos nombres que
     * `Utils/FormatoReporte.js::formateador()`).
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
            default => RedaccionPronostico::formatear((float) $valor, $formato),
        };
    }

    private function anexoMetodologico(): void
    {
        $this->AddPage();
        $this->tituloSeccion('Anexo metodológico (detalle técnico)');
        $this->parrafo('Esta sección es para quien quiera revisar cómo se calcularon las estimaciones. No hace falta leerla para entender el informe.');

        $confianza = $this->informe['confianza'];

        foreach ([
            'Cada indicador se resume en un valor por mes. La tendencia es una recta de mínimos cuadrados, y = a + b·x, donde x es el número de mes (0, 1, 2…) e y el valor del mes; la recta se prolonga '.count($this->informe['meses_proyeccion']).' meses hacia adelante. Es deliberadamente simple: con el volumen de una empresa de este tamaño, un modelo más complejo no tiene datos suficientes para ser más exacto, y en cambio deja de poder explicarse.',
            'Meses incompletos: en los totales del mes (pedidos, montos, consumo, utilidad) el mes en curso no entra en la recta, porque sus pocos días se leerían como una caída. Se muestra igual en el gráfico y se estima cómo cerraría. Los promedios, porcentajes y precios no dependen de cuántos días pasaron y usan el mes entero.',
            "Rango probable: intervalo de predicción al {$confianza} %. Para el mes x0 vale y(x0) ± t × s × raíz(1 + 1/n + (x0 - media de x)² / Sxx), con t de Student de n - 2 grados de libertad, s el error típico de la recta (raíz de SSE / (n - 2)) y Sxx la suma de (x - media de x)². Se ensancha cuanto más lejos se proyecta.",
            'R² (coeficiente de determinación) = 1 - SSE / SST: qué parte de la variación entre meses explica la recta. Se lee fuerte desde 0,7, moderado entre 0,4 y 0,7 y débil por debajo.',
            'Validación retrospectiva: se ocultan los últimos meses reales (tantos como se proyectan), se pronostican con los anteriores y se mide el error porcentual absoluto medio (MAPE). Se compara la recta con Holt (suavizado exponencial doble, que adapta nivel y tendencia mes a mes, con alfa y beta elegidos por mínimo error) y con la media móvil de 3 meses (sin tendencia). Si otro modelo se equivoca menos, el informe lo advierte.',
            'Mínimos: se necesitan 3 meses con datos para trazar una recta y 6 meses de entrenamiento, además de los validados, para medir el error.',
        ] as $parrafo) {
            $this->parrafo($parrafo);
        }

        $periodo = $this->informe['periodo'];

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
