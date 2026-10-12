<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FiltrosDeVentas;
use App\Services\Reporte\ConsultaVentasService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * Ventas y presupuestos de un periodo, con una métrica, una agrupación y
 * filtros opcionales. Es la herramienta para "¿cuánto vendimos…?",
 * "¿qué familia vende más?", "¿cómo vienen las ventas mes a mes?".
 */
class ConsultarVentas extends HerramientaBi
{
    use FiltrosDeVentas;

    public function description(): Stringable|string
    {
        return 'Calcula una métrica de ventas o presupuestos en un periodo, agrupada (por mes, semana, familia, categoría, producto, sucursal, vendedor o cliente) o como total. '
            .'Para comparar dos periodos usa CompararPeriodos.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metrica' => $this->esquemaMetrica($schema),
            'desde' => $schema->string()->description('Fecha inicial YYYY-MM-DD.')->required(),
            'hasta' => $schema->string()->description('Fecha final YYYY-MM-DD (inclusive).')->required(),
            'agrupar_por' => $schema->string()->enum(ConsultaVentasService::AGRUPACIONES)->required(),
            ...$this->esquemaFiltros($schema),
        ];
    }

    protected function reglas(): array
    {
        return [
            'metrica' => ['required', Rule::in(array_keys(ConsultaVentasService::METRICAS))],
            ...$this->reglasPeriodo(),
            'agrupar_por' => ['required', Rule::in(ConsultaVentasService::AGRUPACIONES)],
            ...$this->reglasFiltros(),
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $servicio = app(ConsultaVentasService::class);
        $desde = $this->fecha($datos['desde']);
        $hasta = $this->fecha($datos['hasta']);
        $filtros = $this->filtros($datos);
        [$nombreMetrica, $formato] = ConsultaVentasService::METRICAS[$datos['metrica']];

        $lineas = $servicio->lineas($this->usuario, $desde, $hasta, $filtros);
        $filas = $servicio->agrupar($datos['metrica'], $datos['agrupar_por'], $lineas, $desde, $hasta);
        $total = $servicio->valor($datos['metrica'], $lineas);

        $esTiempo = in_array($datos['agrupar_por'], ['mes', 'semana'], true);
        $grafico = $datos['agrupar_por'] === 'ninguno' ? null : [
            'tipo' => $esTiempo ? 'line' : 'bar',
            'horizontal' => ! $esTiempo,
            'formato' => $formato,
            'labels' => array_column(array_slice($filas, 0, 12), 'grupo'),
            'series' => [['nombre' => $nombreMetrica, 'datos' => array_column(array_slice($filas, 0, 12), 'valor')]],
        ];

        return $this->resultado(
            titulo: $nombreMetrica.($datos['agrupar_por'] === 'ninguno' ? '' : ' por '.$datos['agrupar_por']),
            calculo: "{$nombreMetrica} de presupuestos con fecha del ".$this->rango($desde, $hasta).', '
                .$this->describirFiltros($filtros).'. Vendido = presupuestos aprobados o convertidos en pedido; montos = subtotal de línea sin IVA.',
            resumen: ['metrica' => $nombreMetrica, 'formato' => $formato, 'total' => $total, 'grupos' => count($filas)],
            columnas: [
                ['clave' => 'grupo', 'etiqueta' => ucfirst($datos['agrupar_por'] === 'ninguno' ? 'total' : $datos['agrupar_por'])],
                ['clave' => 'valor', 'etiqueta' => $nombreMetrica, 'formato' => $formato],
            ],
            filas: $filas,
            grafico: $grafico,
        );
    }
}
