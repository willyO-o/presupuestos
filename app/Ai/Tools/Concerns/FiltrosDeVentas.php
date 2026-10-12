<?php

namespace App\Ai\Tools\Concerns;

use App\Services\Reporte\ConsultaVentasService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Parámetros compartidos por las herramientas que consultan ventas:
 * métrica, agrupación y los filtros por id (que salen de BuscarEnCatalogo).
 */
trait FiltrosDeVentas
{
    /**
     * @return array<string, mixed>
     */
    protected function esquemaFiltros(JsonSchema $schema): array
    {
        return [
            'familia_id' => $schema->integer()->description('Id de familia de producto (de BuscarEnCatalogo).'),
            'categoria_id' => $schema->integer()->description('Id de categoría de producto.'),
            'producto_id' => $schema->integer()->description('Id de producto.'),
            'sucursal_id' => $schema->integer()->description('Id de sucursal.'),
            'empleado_id' => $schema->integer()->description('Id del vendedor.'),
            'cliente_id' => $schema->integer()->description('Id del cliente.'),
        ];
    }

    protected function esquemaMetrica(JsonSchema $schema): mixed
    {
        return $schema->string()
            ->enum(array_keys(ConsultaVentasService::METRICAS))
            ->description('monto_vendido y unidades_vendidas cuentan presupuestos aprobados o convertidos en pedido; montos sin IVA.')
            ->required();
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasFiltros(): array
    {
        return collect(ConsultaVentasService::FILTROS)
            ->mapWithKeys(fn (string $f) => [$f => ['nullable', 'integer', 'min:1']])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, int>
     */
    protected function filtros(array $datos): array
    {
        return collect($datos)->only(ConsultaVentasService::FILTROS)->filter()->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Texto legible de los filtros aplicados, para "cómo lo calculé".
     *
     * @param  array<string, int>  $filtros
     */
    protected function describirFiltros(array $filtros): string
    {
        if ($filtros === []) {
            return 'sin filtros adicionales';
        }

        $nombres = [
            'familia_id' => 'familia', 'categoria_id' => 'categoría', 'producto_id' => 'producto',
            'sucursal_id' => 'sucursal', 'empleado_id' => 'vendedor', 'cliente_id' => 'cliente',
        ];

        return 'filtrado por '.collect($filtros)->map(fn ($id, $f) => "{$nombres[$f]} #{$id}")->implode(', ');
    }
}
