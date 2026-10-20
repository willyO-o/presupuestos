<?php

namespace App\Ai\Tools;

use App\Models\Pedido;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * Pedidos (órdenes de trabajo) de un periodo: cuántos hay, en qué etapa,
 * cuántos se entregaron y cuántos a tiempo, cuántos están atrasados ahora.
 */
class ConsultarPedidos extends HerramientaBi
{
    private const AGRUPACIONES = ['ninguno', 'estado', 'mes', 'sucursal'];

    public function description(): Stringable|string
    {
        return 'Cuenta los pedidos (órdenes de trabajo) creados en un periodo, con su monto, estado actual, entregas a tiempo y atrasos. '
            .'Úsala para preguntas de producción, entregas o pedidos atrasados.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial YYYY-MM-DD (fecha del pedido).')->required(),
            'hasta' => $schema->string()->description('Fecha final YYYY-MM-DD.')->required(),
            'agrupar_por' => $schema->string()->enum(self::AGRUPACIONES)->required(),
            'sucursal_id' => $schema->integer()->description('Id de sucursal (opcional).'),
        ];
    }

    protected function reglas(): array
    {
        return [
            ...$this->reglasPeriodo(),
            'agrupar_por' => ['required', Rule::in(self::AGRUPACIONES)],
            'sucursal_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function ejecutar(array $datos): array
    {
        $desde = $this->fecha($datos['desde'])->startOfDay();
        $hasta = $this->fecha($datos['hasta'])->endOfDay();
        $hoy = Carbon::now(config('ia.zona_horaria'))->startOfDay();

        $pedidos = Pedido::query()
            ->visiblePara($this->usuario)
            ->whereBetween('fecha_pedido', [$desde, $hasta])
            ->when($datos['sucursal_id'] ?? null, fn ($q, $id) => $q->whereHas('cotizacion', fn ($c) => $c->where('sucursal_id', $id)))
            ->with('cotizacion:id,sucursal_id', 'cotizacion.sucursal:id,nombre')
            ->get(['id', 'cotizacion_id', 'numero_pedido', 'fecha_pedido', 'fecha_entrega_estimada', 'fecha_entrega_real', 'estado', 'total']);

        $metricas = function ($grupo) use ($hoy): array {
            $entregados = $grupo->where('estado', 'ENTREGADO');
            $aTiempo = $entregados->filter(fn ($p) => $p->fecha_entrega_estimada && $p->fecha_entrega_real && $p->fecha_entrega_real->lessThanOrEqualTo($p->fecha_entrega_estimada));
            $atrasados = $grupo->filter(fn ($p) => ! in_array($p->estado, ['ENTREGADO', 'CANCELADO'], true)
                && $p->fecha_entrega_estimada && $p->fecha_entrega_estimada->lessThan($hoy));

            return [
                'pedidos' => $grupo->count(),
                'monto' => round((float) $grupo->where('estado', '!=', 'CANCELADO')->sum('total'), 2),
                'entregados' => $entregados->count(),
                'a_tiempo_pct' => $entregados->isNotEmpty() ? round($aTiempo->count() / $entregados->count() * 100, 1) : null,
                'atrasados_hoy' => $atrasados->count(),
            ];
        };

        $clave = match ($datos['agrupar_por']) {
            'estado' => fn ($p) => $p->estado,
            'mes' => fn ($p) => $p->fecha_pedido->format('Y-m'),
            'sucursal' => fn ($p) => $p->cotizacion?->sucursal?->nombre ?? 'Sin sucursal',
            default => fn () => 'Total',
        };

        // Por mes se ordena con la clave `Y-m` (cronológica) y recién después
        // se rotula `Ago-2026`: ordenar por la etiqueta pondría abril antes
        // que enero.
        $porMes = $datos['agrupar_por'] === 'mes';

        $filas = $pedidos->groupBy($clave)
            ->map(fn ($g, $grupo) => ['grupo' => (string) $grupo, ...$metricas($g)])
            ->sortBy($porMes ? 'grupo' : fn ($f) => -$f['pedidos'])
            ->map(fn ($f) => $porMes ? [...$f, 'grupo' => PeriodoReporte::etiquetaMes(Carbon::createFromFormat('!Y-m', $f['grupo']))] : $f)
            ->values()
            ->all();

        return $this->resultado(
            titulo: 'Pedidos'.($datos['agrupar_por'] === 'ninguno' ? '' : ' por '.$datos['agrupar_por']),
            calculo: 'Pedidos con fecha del '.$this->rango($desde, $hasta).'. A tiempo = entregado en o antes de la fecha estimada; '
                .'atrasado = no entregado y con fecha estimada vencida al día de hoy.',
            resumen: $metricas($pedidos),
            columnas: [
                ['clave' => 'grupo', 'etiqueta' => $datos['agrupar_por'] === 'ninguno' ? 'Total' : ucfirst($datos['agrupar_por'])],
                ['clave' => 'pedidos', 'etiqueta' => 'Pedidos', 'formato' => 'numero'],
                ['clave' => 'monto', 'etiqueta' => 'Monto', 'formato' => 'bs'],
                ['clave' => 'a_tiempo_pct', 'etiqueta' => 'A tiempo', 'formato' => 'porcentaje'],
                ['clave' => 'atrasados_hoy', 'etiqueta' => 'Atrasados hoy', 'formato' => 'numero'],
            ],
            filas: $filas,
            grafico: $datos['agrupar_por'] === 'ninguno' ? null : [
                'tipo' => $datos['agrupar_por'] === 'mes' ? 'line' : 'bar',
                'horizontal' => $datos['agrupar_por'] !== 'mes',
                'formato' => 'numero',
                'labels' => array_column($filas, 'grupo'),
                'series' => [['nombre' => 'Pedidos', 'datos' => array_column($filas, 'pedidos')]],
            ],
        );
    }
}
