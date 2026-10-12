<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FiltrosDeVentas;
use App\Services\Reporte\ConsultaVentasService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * Margen bruto y rentabilidad de lo vendido, agrupados. Solo cuenta líneas
 * con hoja de costos: las previas al motor de margen guardan costo 0 y
 * aparecerían como 100 % de margen; se informan aparte.
 */
class ConsultarRentabilidad extends HerramientaBi
{
    use FiltrosDeVentas;

    private const AGRUPACIONES = ['ninguno', 'mes', 'familia', 'categoria', 'producto', 'sucursal', 'vendedor', 'cliente'];

    public function description(): Stringable|string
    {
        return 'Calcula el margen bruto (precio sin IVA − costo ajustado) y la rentabilidad sobre costo de lo vendido en un periodo, en total o por grupo. '
            .'Solo incluye líneas con hoja de costos.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial YYYY-MM-DD.')->required(),
            'hasta' => $schema->string()->description('Fecha final YYYY-MM-DD.')->required(),
            'agrupar_por' => $schema->string()->enum(self::AGRUPACIONES)->required(),
            ...$this->esquemaFiltros($schema),
        ];
    }

    protected function reglas(): array
    {
        return [
            ...$this->reglasPeriodo(),
            'agrupar_por' => ['required', Rule::in(self::AGRUPACIONES)],
            ...$this->reglasFiltros(),
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $servicio = app(ConsultaVentasService::class);
        $desde = $this->fecha($datos['desde']);
        $hasta = $this->fecha($datos['hasta']);
        $filtros = $this->filtros($datos);

        $lineas = $servicio->lineas($this->usuario, $desde, $hasta, $filtros);

        $margen = collect($servicio->agrupar('margen_bruto', $datos['agrupar_por'], $lineas, $desde, $hasta))->pluck('valor', 'grupo');
        $rentabilidad = collect($servicio->agrupar('rentabilidad_bruta', $datos['agrupar_por'], $lineas, $desde, $hasta))->pluck('valor', 'grupo');
        $vendido = collect($servicio->agrupar('monto_vendido', $datos['agrupar_por'], $lineas, $desde, $hasta))->pluck('valor', 'grupo');

        $filas = $vendido->keys()
            ->map(fn ($grupo) => [
                'grupo' => $grupo,
                'monto_vendido' => $vendido->get($grupo),
                'margen_bruto' => $margen->get($grupo),
                'rentabilidad_pct' => $rentabilidad->get($grupo),
            ])
            ->when($datos['agrupar_por'] !== 'mes', fn ($c) => $c->sortByDesc('margen_bruto'))
            ->values()
            ->all();

        $vendidas = $lineas->filter(fn ($l) => in_array($l->cotizacion?->estado, ConsultaVentasService::VENDIDOS, true));
        $sinCosto = $vendidas->filter(fn ($l) => (float) $l->costo_ajustado <= 0)->count();
        $esMes = $datos['agrupar_por'] === 'mes';
        $visibles = array_slice($filas, 0, 12);

        return $this->resultado(
            titulo: 'Rentabilidad'.($datos['agrupar_por'] === 'ninguno' ? '' : ' por '.$datos['agrupar_por']),
            calculo: 'Margen bruto = subtotal de línea sin IVA − costo ajustado, de lo vendido entre el '.$this->rango($desde, $hasta).', '
                .$this->describirFiltros($filtros).'. Rentabilidad = margen ÷ costo. No incluye impuestos (IT, IUE) ni las líneas sin hoja de costos.',
            resumen: [
                'margen_bruto' => $servicio->valor('margen_bruto', $lineas),
                'rentabilidad_pct' => $servicio->valor('rentabilidad_bruta', $lineas),
                'monto_vendido' => $servicio->valor('monto_vendido', $lineas),
                'lineas_sin_hoja_de_costos' => $sinCosto,
            ],
            columnas: [
                ['clave' => 'grupo', 'etiqueta' => $datos['agrupar_por'] === 'ninguno' ? 'Total' : ucfirst($datos['agrupar_por'])],
                ['clave' => 'monto_vendido', 'etiqueta' => 'Vendido', 'formato' => 'bs'],
                ['clave' => 'margen_bruto', 'etiqueta' => 'Margen bruto', 'formato' => 'bs'],
                ['clave' => 'rentabilidad_pct', 'etiqueta' => 'Rentabilidad', 'formato' => 'porcentaje'],
            ],
            filas: $filas,
            grafico: $datos['agrupar_por'] === 'ninguno' ? null : [
                'tipo' => $esMes ? 'line' : 'bar',
                'horizontal' => ! $esMes,
                'formato' => 'porcentaje',
                'labels' => array_column($visibles, 'grupo'),
                'series' => [['nombre' => 'Rentabilidad sobre costo', 'datos' => array_column($visibles, 'rentabilidad_pct')]],
                'umbrales' => [
                    ['valor' => round(config('margen.semaforo.umbral_verde') * 100, 2), 'nombre' => 'Verde'],
                ],
            ],
        );
    }
}
