<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FiltrosDeVentas;
use App\Services\Reporte\ConsultaVentasService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * Compara una métrica entre dos periodos ("este trimestre contra el
 * anterior", "octubre contra octubre del año pasado"), en total o abierta
 * por grupo. Devuelve la diferencia y la variación ya calculadas: el modelo
 * no tiene que restar ni dividir.
 */
class CompararPeriodos extends HerramientaBi
{
    use FiltrosDeVentas;

    private const AGRUPACIONES = ['ninguno', 'familia', 'categoria', 'producto', 'sucursal', 'vendedor', 'cliente'];

    public function description(): Stringable|string
    {
        return 'Compara una métrica de ventas entre el periodo A (el más reciente o el de interés) y el periodo B (la referencia), en total o por grupo. '
            .'Devuelve valores, diferencia y variación porcentual ya calculadas.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metrica' => $this->esquemaMetrica($schema),
            'a_desde' => $schema->string()->description('Inicio del periodo A, YYYY-MM-DD.')->required(),
            'a_hasta' => $schema->string()->description('Fin del periodo A, YYYY-MM-DD.')->required(),
            'b_desde' => $schema->string()->description('Inicio del periodo B (referencia), YYYY-MM-DD.')->required(),
            'b_hasta' => $schema->string()->description('Fin del periodo B, YYYY-MM-DD.')->required(),
            'agrupar_por' => $schema->string()->enum(self::AGRUPACIONES)->required(),
            ...$this->esquemaFiltros($schema),
        ];
    }

    protected function reglas(): array
    {
        return [
            'metrica' => ['required', Rule::in(array_keys(ConsultaVentasService::METRICAS))],
            ...$this->reglasPeriodo('a_'),
            ...$this->reglasPeriodo('b_'),
            'agrupar_por' => ['required', Rule::in(self::AGRUPACIONES)],
            ...$this->reglasFiltros(),
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $servicio = app(ConsultaVentasService::class);
        $filtros = $this->filtros($datos);
        [$nombreMetrica, $formato] = ConsultaVentasService::METRICAS[$datos['metrica']];

        $aDesde = $this->fecha($datos['a_desde']);
        $aHasta = $this->fecha($datos['a_hasta']);
        $bDesde = $this->fecha($datos['b_desde']);
        $bHasta = $this->fecha($datos['b_hasta']);

        $lineasA = $servicio->lineas($this->usuario, $aDesde, $aHasta, $filtros);
        $lineasB = $servicio->lineas($this->usuario, $bDesde, $bHasta, $filtros);

        $porGrupo = fn ($lineas) => collect($servicio->agrupar($datos['metrica'], $datos['agrupar_por'], $lineas, $aDesde, $aHasta))
            ->pluck('valor', 'grupo');

        $a = $porGrupo($lineasA);
        $b = $porGrupo($lineasB);

        $filas = $a->keys()->merge($b->keys())->unique()
            ->map(function (string $grupo) use ($a, $b): array {
                $valorA = $a->get($grupo) ?? 0.0;
                $valorB = $b->get($grupo) ?? 0.0;

                return [
                    'grupo' => $grupo,
                    'periodo_a' => $valorA,
                    'periodo_b' => $valorB,
                    'diferencia' => round($valorA - $valorB, 2),
                    'variacion_pct' => $valorB != 0 ? round(($valorA - $valorB) / abs($valorB) * 100, 1) : null,
                ];
            })
            ->sortByDesc('periodo_a')
            ->values()
            ->all();

        $etiquetaA = 'A: '.$this->rango($aDesde, $aHasta);
        $etiquetaB = 'B: '.$this->rango($bDesde, $bHasta);
        $visibles = array_slice($filas, 0, 10);

        return $this->resultado(
            titulo: "{$nombreMetrica}: periodo A contra periodo B",
            calculo: "{$nombreMetrica} del periodo A ({$this->rango($aDesde, $aHasta)}) contra el periodo B ({$this->rango($bDesde, $bHasta)}), "
                .$this->describirFiltros($filtros).'. Variación = (A − B) ÷ B.',
            resumen: [
                'metrica' => $nombreMetrica,
                'formato' => $formato,
                'periodo_a' => $this->rango($aDesde, $aHasta),
                'periodo_b' => $this->rango($bDesde, $bHasta),
                'total_a' => $servicio->valor($datos['metrica'], $lineasA),
                'total_b' => $servicio->valor($datos['metrica'], $lineasB),
                'diferencia' => isset($filas[0]) && $datos['agrupar_por'] === 'ninguno' ? $filas[0]['diferencia'] : null,
                'variacion_pct' => isset($filas[0]) && $datos['agrupar_por'] === 'ninguno' ? $filas[0]['variacion_pct'] : null,
            ],
            columnas: [
                ['clave' => 'grupo', 'etiqueta' => $datos['agrupar_por'] === 'ninguno' ? 'Total' : ucfirst($datos['agrupar_por'])],
                ['clave' => 'periodo_a', 'etiqueta' => 'Periodo A', 'formato' => $formato],
                ['clave' => 'periodo_b', 'etiqueta' => 'Periodo B', 'formato' => $formato],
                ['clave' => 'variacion_pct', 'etiqueta' => 'Variación', 'formato' => 'variacion'],
            ],
            filas: $filas,
            grafico: [
                'tipo' => 'bar',
                'horizontal' => $datos['agrupar_por'] !== 'ninguno',
                'formato' => $formato,
                'labels' => array_column($visibles, 'grupo'),
                'series' => [
                    ['nombre' => $etiquetaA, 'datos' => array_column($visibles, 'periodo_a')],
                    ['nombre' => $etiquetaB, 'datos' => array_column($visibles, 'periodo_b')],
                ],
            ],
        );
    }
}
