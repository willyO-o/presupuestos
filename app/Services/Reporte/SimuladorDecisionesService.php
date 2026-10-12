<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\TipoProyecto;
use App\Models\User;

/**
 * Punto de partida del simulador de decisiones ("¿qué pasa si…?").
 *
 * El servidor solo arma el escenario BASE a partir de lo que realmente pasó
 * en el periodo (costo y precio promedio de los presupuestos aprobados con
 * hoja de costos, cuántos se aprueban por mes) y entrega los parámetros del
 * motor de margen. Los escenarios los recalcula el navegador con
 * `Utils/MotorMargen.js`, el mismo espejo del motor que usa el formulario de
 * cotización para su panel en vivo: una simulación no guarda nada, y mover un
 * control no debería esperar una ida y vuelta al servidor.
 */
class SimuladorDecisionesService
{
    /**
     * Base de ejemplo cuando el periodo no tiene ningún presupuesto con hoja
     * de costos: un trabajo de Bs 1.000 de costo con el margen sugerido.
     */
    private const COSTO_EJEMPLO = 1000.0;

    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario, PeriodoReporte $periodo): array
    {
        $presupuestos = Cotizacion::query()
            ->visiblePara($usuario)
            ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
            ->where('costo_ajustado', '>', 0)
            ->whereBetween('fecha', [$periodo->desde, $periodo->hasta])
            ->get(['id', 'costo_ajustado', 'subtotal', 'descuento']);

        $cantidad = $presupuestos->count();
        $meses = max($periodo->meses()->count(), 1);
        $costoTotal = (float) $presupuestos->sum('costo_ajustado');
        $precioTotal = (float) $presupuestos->sum(fn ($c) => (float) $c->subtotal - (float) $c->descuento);
        $margenSugerido = (float) config('cotizacion.margen_sugerido');

        return [
            'periodo' => $periodo->toArray(),
            'base' => [
                'desde_datos' => $cantidad > 0,
                'presupuestos' => $cantidad,
                'costo' => $cantidad > 0 ? round($costoTotal / $cantidad, 2) : self::COSTO_EJEMPLO,
                'precio' => $cantidad > 0
                    ? round($precioTotal / $cantidad, 2)
                    : round(self::COSTO_EJEMPLO * (1 + $margenSugerido), 2),
                'volumen_mensual' => $cantidad > 0 ? round($cantidad / $meses, 1) : 1.0,
            ],
            'tipos_proyecto' => TipoProyecto::query()
                ->estado('ACTIVO')
                ->ordenado()
                ->get(['id', 'nombre', 'factor_complejidad', 'margen_minimo'])
                ->map(fn (TipoProyecto $t): array => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'factor_complejidad' => (float) $t->factor_complejidad,
                    'margen_minimo' => (float) $t->margen_minimo,
                ])
                ->all(),
            'config' => [
                'impuestos' => config('margen.impuestos'),
                'semaforo' => config('margen.semaforo'),
                'margen_sugerido' => $margenSugerido,
            ],
        ];
    }
}
