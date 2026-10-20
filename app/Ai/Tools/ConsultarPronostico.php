<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FiltrosDeVentas;
use App\Services\Reporte\ConsultaVentasService;
use App\Services\Reporte\PeriodoReporte;
use App\Services\Reporte\Pronostico;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * Tendencia y proyección de una métrica mensual: la misma regresión lineal
 * con rango probable de las pestañas del BI (App\Services\Reporte\Pronostico).
 * El número lo calcula la estadística; el modelo solo lo explica.
 */
class ConsultarPronostico extends HerramientaBi
{
    use FiltrosDeVentas;

    private const METRICAS = ['monto_vendido', 'monto_presupuestado', 'presupuestos', 'ventas', 'unidades_vendidas'];

    public function description(): Stringable|string
    {
        return 'Proyecta una métrica mensual (ventas, presupuestos, unidades) para los próximos meses con regresión lineal sobre los últimos 12 meses completos, '
            .'con rango probable. Admite los mismos filtros que ConsultarVentas (familia, producto, sucursal...).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metrica' => $schema->string()->enum(self::METRICAS)->required(),
            'meses' => $schema->integer()->description('Meses a proyectar, de 1 a 3.')->required(),
            ...$this->esquemaFiltros($schema),
        ];
    }

    protected function reglas(): array
    {
        return [
            'metrica' => ['required', Rule::in(self::METRICAS)],
            'meses' => ['required', 'integer', 'min:1', 'max:3'],
            ...$this->reglasFiltros(),
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $servicio = app(ConsultaVentasService::class);
        $filtros = $this->filtros($datos);
        [$nombreMetrica, $formato] = ConsultaVentasService::METRICAS[$datos['metrica']];

        // Últimos 12 meses COMPLETOS: el mes en curso, a medias, tiraría la
        // recta hacia abajo.
        $hasta = Carbon::now(config('ia.zona_horaria'))->startOfMonth()->subDay()->endOfDay();
        $desde = $hasta->copy()->startOfMonth()->subMonths(11);

        $lineas = $servicio->lineas($this->usuario, $desde, $hasta, $filtros);
        $serie = $servicio->agrupar($datos['metrica'], 'mes', $lineas, $desde, $hasta);
        $valores = array_map(fn ($f) => (float) $f['valor'], $serie);

        $proyeccion = Pronostico::proyectar($valores, (int) $datos['meses']);
        $futuros = collect(range(1, (int) $datos['meses']))->map(fn ($k) => PeriodoReporte::etiquetaMes($hasta->copy()->startOfMonth()->addMonths($k)))->all();

        $filas = [
            ...array_map(fn ($f) => ['mes' => $f['grupo'], 'valor' => $f['valor'], 'tipo' => 'real', 'inferior' => null, 'superior' => null], $serie),
            ...array_map(fn ($p, $mes) => [
                'mes' => $mes,
                'valor' => round($p['valor'], 2),
                'tipo' => 'proyección',
                'inferior' => round($p['inferior'], 2),
                'superior' => round($p['superior'], 2),
            ], $proyeccion, array_slice($futuros, 0, count($proyeccion))),
        ];

        $variacion = Pronostico::variacionMensual($valores);
        $vacios = array_fill(0, count($proyeccion), null);
        $previos = array_fill(0, max(count($serie) - 1, 0), null);
        $ultimo = $valores === [] ? null : end($valores);

        return $this->resultado(
            titulo: "Proyección de {$nombreMetrica}",
            calculo: 'Regresión lineal por mínimos cuadrados sobre los 12 meses completos del '.$this->rango($desde, $hasta).', '
                .$this->describirFiltros($filtros).'. Rango probable = proyección ± error típico de la recta. Con menos de 3 meses con datos no se proyecta.',
            resumen: [
                'metrica' => $nombreMetrica,
                'formato' => $formato,
                'tendencia_mensual_pct' => $variacion === null ? null : round($variacion, 1),
                'proyeccion' => array_values(array_filter($filas, fn ($f) => $f['tipo'] === 'proyección')),
                'sin_proyeccion' => $proyeccion === [],
            ],
            columnas: [
                ['clave' => 'mes', 'etiqueta' => 'Mes'],
                ['clave' => 'tipo', 'etiqueta' => 'Tipo'],
                ['clave' => 'valor', 'etiqueta' => $nombreMetrica, 'formato' => $formato],
                ['clave' => 'inferior', 'etiqueta' => 'Mínimo probable', 'formato' => $formato],
                ['clave' => 'superior', 'etiqueta' => 'Máximo probable', 'formato' => $formato],
            ],
            filas: $filas,
            grafico: [
                'tipo' => 'line',
                'formato' => $formato,
                'labels' => array_column($filas, 'mes'),
                'inicio_proyeccion' => $futuros[0] ?? null,
                'series' => [
                    ['nombre' => $nombreMetrica, 'datos' => [...$valores, ...$vacios], 'area' => true],
                    ...($proyeccion === [] ? [] : [
                        ['nombre' => 'Proyección', 'punteada' => true, 'datos' => [...$previos, $ultimo, ...array_map(fn ($p) => round($p['valor'], 2), $proyeccion)]],
                        [
                            'nombre' => 'Rango probable',
                            'tipo' => 'banda',
                            'inferior' => [...$previos, $ultimo, ...array_map(fn ($p) => round($p['inferior'], 2), $proyeccion)],
                            'superior' => [...$previos, $ultimo, ...array_map(fn ($p) => round($p['superior'], 2), $proyeccion)],
                        ],
                    ]),
                ],
            ],
        );
    }
}
