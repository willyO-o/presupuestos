<?php

namespace App\Services\Pdf\Documentos;

use Illuminate\Support\Carbon;

/**
 * Reporte de producción en PDF: cumplimiento de entregas (con el detalle de
 * los pedidos que salieron tarde), tiempos por etapa, carga por área y
 * pedidos en curso.
 *
 * Mezcla dos tiempos y lo dice en el documento: el cumplimiento y la duración
 * por etapa son del historial completo; la carga y los pedidos en curso son
 * una foto del momento en que se generó. Recibe el informe ya armado por
 * `InformeReportesService::produccion()`; acá no se calcula nada.
 */
class ReporteProduccionPdf extends Documento
{
    use DibujaGraficos;

    /** Ancho de los rótulos de los gráficos: todas las barras arrancan en la misma línea. */
    private const ANCHO_ROTULO = 40.0;

    /** Filas de la tabla de atrasos: los peores; el resto ya está en el total. */
    private const ATRASOS_EN_TABLA = 15;

    /**
     * @param  array<string, mixed>  $informe  InformeReportesService::produccion().
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
        return [
            'tipo' => 'Reporte de producción',
            'numero' => 'AL '.$this->generadoEn->format('d/m/Y'),
            'subtitulo' => $this->informe['alcance'],
            'etiqueta' => ['texto' => 'Uso interno', 'tono' => 'info'],
        ];
    }

    protected function cuerpo(): void
    {
        $this->bloqueDatos([
            [
                'rotulo' => 'Situación al',
                'titulo' => $this->informe['fecha'],
                'lineas' => [
                    ['Alcance:', $this->informe['alcance']],
                    ['Generado:', $this->selloDeGeneracion()],
                ],
            ],
            [
                'rotulo' => 'Qué entra en cada cifra',
                'lineas' => [
                    ['Cumplimiento y tiempos:', 'historial completo de pedidos entregados y etapas cerradas.'],
                    ['Carga y pedidos en curso:', 'lo que está abierto en este momento.'],
                ],
            ],
        ]);

        $this->espacio(3);
        $this->indicadores($this->informe['indicadores']);

        $this->recuadro([
            ['tipo' => 'titulo', 'texto' => 'Puntos clave'],
            ['tipo' => 'lista', 'items' => $this->informe['puntos_clave']],
        ], tono: 'destacado');

        $this->cumplimiento();
        $this->tiemposPorEtapa();
        $this->cargaActual();

        $this->notaLegal([
            ['texto' => sprintf(
                'Reporte generado por el sistema de %s el %s con los datos registrados a esa fecha. ',
                $this->empresa['nombre'],
                $this->selloDeGeneracion(),
            )],
            ['texto' => 'Los tiempos salen de las fechas que el taller registra al asignar y cerrar cada etapa: una etapa sin registrar no tiene duración.', 'negrita' => true],
        ]);
    }

    /* -----------------------------------------------------------------
     * Bloques
     * -------------------------------------------------------------- */

    private function cumplimiento(): void
    {
        $cumplimiento = $this->informe['cumplimiento'];
        $atrasos = $this->informe['atrasos'];

        $this->espacio(4);
        $this->tituloSeccion('Cumplimiento de entregas');

        if ($cumplimiento['entregados'] === 0) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'Todavía no hay pedidos entregados con fecha estimada y fecha real.']]);

            return;
        }

        $this->subtitulo('Entregas a tiempo y tarde', 'Pedidos entregados que tenían fecha estimada. A tiempo = entregado ese día o antes.');
        $this->graficoBarras([
            'barras' => [
                ['etiqueta' => 'A tiempo', 'valor' => $cumplimiento['a_tiempo'], 'color' => self::VERDE],
                ['etiqueta' => 'Tarde', 'valor' => $cumplimiento['tarde'], 'color' => self::AMBAR],
            ],
            'formato' => fn (float $v): string => $this->conPorcentaje((int) $v, $cumplimiento['entregados']),
            'ancho_etiqueta' => self::ANCHO_ROTULO,
        ]);

        if ($atrasos === []) {
            return;
        }

        $mostrados = array_slice($atrasos, 0, self::ATRASOS_EN_TABLA);

        $this->subtitulo(
            count($atrasos) > count($mostrados) ? 'Los '.count($mostrados).' pedidos con más atraso' : 'Pedidos entregados tarde',
            'Del mayor atraso al menor. Días de diferencia entre la fecha estimada y la de entrega.',
        );

        $this->tabla([
            ['titulo' => 'Pedido', 'ancho' => 34],
            ['titulo' => 'Entrega estimada', 'ancho' => 24],
            ['titulo' => 'Entrega real', 'ancho' => 24],
            ['titulo' => 'Días de atraso', 'ancho' => 18, 'alineacion' => 'R'],
        ], array_map(fn (array $a): array => [
            ['texto' => $a['pedido'], 'negrita' => true],
            Carbon::parse($a['estimada'])->format('d/m/Y'),
            Carbon::parse($a['real'])->format('d/m/Y'),
            ['texto' => (string) $a['dias'], 'negrita' => true, 'color' => self::ROJO],
        ], $mostrados));
    }

    private function tiemposPorEtapa(): void
    {
        $etapas = $this->informe['duracion_por_etapa'];
        $conDatos = array_values(array_filter($etapas, fn (array $e): bool => $e['dias_promedio'] !== null));

        $this->espacio(4);
        $this->tituloSeccion('Tiempo promedio por etapa');
        $this->parrafo('Días entre que un ítem entra a una etapa y la cierra, promediados sobre todas las etapas cerradas. Las etapas sin registros no se grafican: no son una etapa que tarde cero.');

        if ($conDatos !== []) {
            $this->graficoBarras([
                'barras' => array_map(fn (array $e): array => ['etiqueta' => $e['nombre'], 'valor' => $e['dias_promedio']], $conDatos),
                'formato' => fn (float $v): string => $this->numero($v, 1).' días',
                'ancho_etiqueta' => self::ANCHO_ROTULO,
            ]);
        }

        $this->tabla([
            ['titulo' => 'Etapa', 'ancho' => 50],
            ['titulo' => 'Días promedio', 'ancho' => 25, 'alineacion' => 'R'],
            ['titulo' => 'Registros', 'ancho' => 25, 'alineacion' => 'R'],
        ], array_map(fn (array $e): array => [
            ['texto' => $e['nombre'], 'negrita' => true],
            $e['dias_promedio'] === null ? ['texto' => 'Sin registros', 'color' => self::TENUE] : $this->numero($e['dias_promedio'], 1),
            (string) $e['muestras'],
        ], $etapas));
    }

    private function cargaActual(): void
    {
        $areas = $this->informe['carga_por_area'];
        $activos = $this->informe['pedidos_activos_por_etapa'];

        $this->espacio(4);
        $this->tituloSeccion('Carga de trabajo actual');

        $this->subtitulo('Ítems abiertos por área', 'Etapas asignadas a un área que todavía no se cerraron.');

        if ($areas === []) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'No hay etapas abiertas en ninguna área.']]);
        } else {
            $total = array_sum(array_column($areas, 'items_abiertos'));

            $this->graficoBarras([
                'barras' => array_map(fn (array $a): array => ['etiqueta' => $a['area'], 'valor' => $a['items_abiertos']], $areas),
                'formato' => fn (float $v): string => $this->conPorcentaje((int) $v, $total),
                'color' => self::AZUL_OSCURO,
                'ancho_etiqueta' => self::ANCHO_ROTULO,
            ]);
        }

        $this->subtitulo('Pedidos en curso por etapa', 'Cada pedido cuenta en la etapa de su ítem menos avanzado.');

        if ($this->informe['total_activos'] === 0) {
            $this->recuadro([['tipo' => 'nota', 'texto' => 'No hay pedidos en curso.']]);

            return;
        }

        $this->graficoBarras([
            'barras' => array_map(fn (array $e): array => ['etiqueta' => $e['nombre'], 'valor' => $e['pedidos']], $activos),
            'formato' => fn (float $v): string => $this->conPorcentaje((int) $v, $this->informe['total_activos']),
            'ancho_etiqueta' => self::ANCHO_ROTULO,
        ]);
    }

    /* -----------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------- */

    /** "12 (40 %)": la cantidad y su peso en el total, al final de la barra. */
    private function conPorcentaje(int $cantidad, int $total): string
    {
        return $total > 0
            ? sprintf('%d (%s %%)', $cantidad, $this->numero($cantidad / $total * 100, 0))
            : (string) $cantidad;
    }
}
