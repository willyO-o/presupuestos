<?php

namespace App\Ai\Tools;

use App\Services\Reporte\CostosMaterialesService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Costos de materiales: cuánto subió el índice de costo, qué materiales
 * cambiaron de precio, su impacto y el consumo de producción. Reutiliza el
 * mismo cálculo de la pestaña "Costos y materiales".
 */
class ConsultarCostosMateriales extends HerramientaBi
{
    public function description(): Stringable|string
    {
        return 'Devuelve la variación del costo de los materiales en un periodo (índice base 100), los materiales que más subieron o bajaron de precio, '
            .'su impacto en Bs y el consumo de producción. Opcionalmente filtra por nombre de material.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial YYYY-MM-DD.')->required(),
            'hasta' => $schema->string()->description('Fecha final YYYY-MM-DD.')->required(),
            'material' => $schema->string()->description('Parte del nombre del material, por ejemplo "lona" (opcional).'),
        ];
    }

    protected function reglas(): array
    {
        return [
            ...$this->reglasPeriodo(),
            'material' => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $periodo = PeriodoReporte::desdeFiltros($datos['desde'], $datos['hasta']);
        $costos = app(CostosMaterialesService::class)->datos($this->usuario, $periodo);
        $filtro = mb_strtolower(trim($datos['material'] ?? ''));

        $coincide = fn (string $nombre) => $filtro === '' || str_contains(mb_strtolower($nombre), $filtro);

        $filas = collect($costos['impacto']['materiales'])
            ->filter(fn ($m) => $coincide($m['material']))
            ->map(fn ($m) => [
                'material' => $m['material'],
                'precio_inicio' => $m['precio_inicio'],
                'precio_fin' => $m['precio_fin'],
                'variacion_pct' => $m['variacion_pct'],
                'impacto' => $m['impacto'],
            ])
            ->values()
            ->all();

        $consumo = collect($costos['consumo']['por_material'])
            ->filter(fn ($m) => $coincide($m['material']))
            ->map(fn ($m) => ['material' => $m['material'], 'costo_real' => $m['costo_real'], 'cantidad' => $m['cantidad']])
            ->values()
            ->all();

        return $this->resultado(
            titulo: 'Variación de precios de materiales',
            calculo: 'Precio vigente de cada material al inicio y al final del periodo ('.$this->rango($periodo->desde, $periodo->hasta).'), según el historial que deja cada compra aprobada. '
                .'Impacto = (precio final − precio inicial) × cantidad consumida por producción en el periodo. Los precios son de toda la empresa.',
            resumen: [
                'indice_variacion_periodo_pct' => $costos['indice']['variacion_periodo'],
                'indice_variacion_mensual_pct' => $costos['indice']['variacion_mensual'],
                'impacto_total' => $costos['impacto']['total'],
                'materiales_que_suben' => $costos['impacto']['suben'],
                'materiales_que_bajan' => $costos['impacto']['bajan'],
                'costo_material_consumido' => $costos['consumo']['total'],
                'mas_consumidos' => array_slice($consumo, 0, 5),
            ],
            columnas: [
                ['clave' => 'material', 'etiqueta' => 'Material'],
                ['clave' => 'precio_inicio', 'etiqueta' => 'Precio inicial', 'formato' => 'bs'],
                ['clave' => 'precio_fin', 'etiqueta' => 'Precio final', 'formato' => 'bs'],
                ['clave' => 'variacion_pct', 'etiqueta' => 'Variación', 'formato' => 'variacion'],
                ['clave' => 'impacto', 'etiqueta' => 'Impacto', 'formato' => 'bs'],
            ],
            filas: $filas,
            grafico: $filas === [] ? null : [
                'tipo' => 'bar',
                'horizontal' => true,
                'formato' => 'porcentaje',
                'labels' => array_column(array_slice($filas, 0, 10), 'material'),
                'series' => [['nombre' => 'Variación de precio', 'datos' => array_column(array_slice($filas, 0, 10), 'variacion_pct')]],
            ],
        );
    }
}
