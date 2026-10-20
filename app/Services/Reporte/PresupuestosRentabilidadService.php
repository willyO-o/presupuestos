<?php

namespace App\Services\Reporte;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Presupuestos y rentabilidad: pronóstico de presupuestos emitidos y
 * aprobados, tendencia del precio y del costo promedio, y proyección de la
 * rentabilidad que calcula el motor de margen.
 *
 * **Qué presupuestos entran a la rentabilidad**: solo los que tienen hoja de
 * costos (`costo_ajustado > 0`). Los anteriores al motor de margen guardan
 * costo 0 y su "utilidad" sería el precio entero — meterlos inflaría el
 * margen hasta el 100 %. Se cuentan aparte (`excluidos_sin_costo`) y la
 * pantalla lo dice.
 *
 * La rentabilidad es la misma razón que mira el semáforo (utilidad real ÷
 * costo ajustado), así que sus umbrales salen de `config/margen.php`.
 */
class PresupuestosRentabilidadService
{
    private const VENDIDOS = ['APROBADA', 'CONVERTIDA'];

    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario, PeriodoReporte $periodo): array
    {
        $cotizaciones = Cotizacion::query()
            ->visiblePara($usuario)
            ->whereBetween('fecha', [$periodo->desde, $periodo->hasta])
            ->get(['id', 'fecha', 'estado', 'costo_ajustado', 'subtotal', 'descuento', 'utilidad_real', 'estado_margen', 'total']);

        $porMes = $cotizaciones->groupBy(fn ($c) => $c->fecha->format('Y-m'));
        $claves = $periodo->meses()->keys();

        return [
            'periodo' => $periodo->toArray(),
            'labels' => $periodo->meses()->values()->all(),
            'meses_proyeccion' => $periodo->mesesSiguientes(config('bi.meses_proyeccion')),
            'resumen' => $this->resumen($cotizaciones),
            'pronostico' => $this->pronostico($porMes, $claves, $periodo),
            'tendencia' => $this->tendencia($porMes, $claves, $periodo),
            'rentabilidad' => $this->rentabilidad($cotizaciones, $porMes, $claves, $periodo),
        ];
    }

    /**
     * @param  Collection<int, Cotizacion>  $cotizaciones
     * @return array<string, mixed>
     */
    private function resumen(Collection $cotizaciones): array
    {
        $vendidas = $cotizaciones->whereIn('estado', self::VENDIDOS);
        // Las pendientes todavía pueden aprobarse: no cuentan ni a favor ni
        // en contra de la tasa, si no la tasa de los meses recientes se ve
        // artificialmente baja.
        $decididas = $cotizaciones->whereNotIn('estado', ['PENDIENTE']);

        return [
            'emitidos' => $cotizaciones->count(),
            'aprobados' => $vendidas->count(),
            'pendientes' => $cotizaciones->where('estado', 'PENDIENTE')->count(),
            'monto_emitido' => round((float) $cotizaciones->sum('total'), 2),
            'monto_aprobado' => round((float) $vendidas->sum('total'), 2),
            'tasa_conversion' => $decididas->isNotEmpty()
                ? round($vendidas->count() / $decididas->count() * 100, 1)
                : null,
        ];
    }

    /**
     * Presupuestos emitidos y aprobados por mes (cantidad y monto) con la
     * proyección del monto emitido y del aprobado.
     *
     * @param  Collection<string, Collection<int, Cotizacion>>  $porMes
     * @param  Collection<int, string>  $claves
     * @return array<string, mixed>
     */
    private function pronostico(Collection $porMes, Collection $claves, PeriodoReporte $periodo): array
    {
        $mes = fn (string $clave): Collection => $porMes->get($clave, collect());

        $emitidos = $claves->map(fn ($c) => $mes($c)->count())->all();
        $aprobados = $claves->map(fn ($c) => $mes($c)->whereIn('estado', self::VENDIDOS)->count())->all();
        $montoEmitido = $claves->map(fn ($c) => round((float) $mes($c)->sum('total'), 2))->all();
        $montoAprobado = $claves->map(
            fn ($c) => round((float) $mes($c)->whereIn('estado', self::VENDIDOS)->sum('total'), 2)
        )->all();

        $emitido = $periodo->pronostico($montoEmitido);
        $aprobado = $periodo->pronostico($montoAprobado);
        $cantidad = $periodo->pronostico($emitidos);

        return [
            'emitidos' => $emitidos,
            'aprobados' => $aprobados,
            'monto_emitido' => $montoEmitido,
            'monto_aprobado' => $montoAprobado,
            'media_movil' => array_map(fn ($v) => $v === null ? null : round($v, 2), Pronostico::mediaMovil($montoEmitido)),
            'proyeccion_emitido' => $this->redondearProyeccion($emitido['proyeccion']),
            'proyeccion_aprobado' => $this->redondearProyeccion($aprobado['proyeccion']),
            'proyeccion_cantidad' => $this->redondearProyeccion($cantidad['proyeccion'], 1),
            'variacion_mensual' => $this->redondear($emitido['variacion_mensual']),
            'variacion_aprobado' => $this->redondear($aprobado['variacion_mensual']),
            'metodo' => $emitido['metodo'],
            'metodo_aprobado' => $aprobado['metodo'],
        ];
    }

    /**
     * Precio promedio por presupuesto y costo ajustado promedio, mes a mes,
     * con la pendiente de cada uno expresada en % por mes. En el código se
     * sigue llamando `ticket`; en pantalla es "Precio promedio".
     *
     * Las dos líneas salen de los MISMOS presupuestos (los que tienen hoja de
     * costos) y en la misma base: el precio es el que mira el motor de
     * margen (subtotal − descuento), sin IVA ni instalación, que el costo no
     * incluye. Antes el precio era el total con IVA de todos los emitidos y
     * el espacio entre las líneas exageraba lo que se gana.
     *
     * Si el precio va por detrás del costo, el margen se está
     * comiendo: es la lectura que esta tarjeta tiene que permitir.
     *
     * @param  Collection<string, Collection<int, Cotizacion>>  $porMes
     * @param  Collection<int, string>  $claves
     * @return array<string, mixed>
     */
    private function tendencia(Collection $porMes, Collection $claves, PeriodoReporte $periodo): array
    {
        $conCosto = fn (string $clave): Collection => $porMes->get($clave, collect())
            ->filter(fn ($c) => (float) $c->costo_ajustado > 0);

        $ticket = $claves->map(function (string $clave) use ($conCosto): ?float {
            $mes = $conCosto($clave);

            return $mes->isEmpty() ? null : round((float) $mes->avg(fn ($c) => (float) $c->subtotal - (float) $c->descuento), 2);
        })->all();

        $costo = $claves->map(function (string $clave) use ($conCosto): ?float {
            $mes = $conCosto($clave);

            return $mes->isEmpty() ? null : round((float) $mes->avg('costo_ajustado'), 2);
        })->all();

        // Promedios: no dependen de cuántos días tiene el mes, entran enteros.
        return [
            'ticket' => $ticket,
            'costo' => $costo,
            'variacion_ticket' => $this->redondear($periodo->pronostico($ticket, sumaDelMes: false)['variacion_mensual']),
            'variacion_costo' => $this->redondear($periodo->pronostico($costo, sumaDelMes: false)['variacion_mensual']),
        ];
    }

    /**
     * Utilidad real y rentabilidad (utilidad real ÷ costo ajustado) por mes de
     * los presupuestos aprobados con hoja de costos, con su proyección.
     *
     * @param  Collection<int, Cotizacion>  $cotizaciones
     * @param  Collection<string, Collection<int, Cotizacion>>  $porMes
     * @param  Collection<int, string>  $claves
     * @return array<string, mixed>
     */
    private function rentabilidad(Collection $cotizaciones, Collection $porMes, Collection $claves, PeriodoReporte $periodo): array
    {
        $conCosto = fn (Collection $grupo): Collection => $grupo
            ->whereIn('estado', self::VENDIDOS)
            ->filter(fn ($c) => (float) $c->costo_ajustado > 0);

        $utilidad = $claves->map(function (string $clave) use ($porMes, $conCosto): ?float {
            $grupo = $conCosto($porMes->get($clave, collect()));

            return $grupo->isEmpty() ? null : round((float) $grupo->sum('utilidad_real'), 2);
        })->all();

        $porcentaje = $claves->map(function (string $clave) use ($porMes, $conCosto): ?float {
            $grupo = $conCosto($porMes->get($clave, collect()));
            $costo = (float) $grupo->sum('costo_ajustado');

            return $costo > 0 ? round((float) $grupo->sum('utilidad_real') / $costo * 100, 2) : null;
        })->all();

        // La rentabilidad (%) es una razón: el mes en curso vale entero. La
        // utilidad es un total del mes: el mes en curso no entra a la recta.
        // Las dos se proyectan sin piso: un margen sí puede ser negativo, y
        // esconderlo sería justo lo que no tiene que pasar.
        $analisisPorcentaje = $periodo->pronostico($porcentaje, piso: false, sumaDelMes: false);
        $analisisUtilidad = $periodo->pronostico($utilidad, piso: false);

        $vendidas = $cotizaciones->whereIn('estado', self::VENDIDOS);
        $analizadas = $conCosto($cotizaciones);
        $costoTotal = (float) $analizadas->sum('costo_ajustado');

        $semaforo = $cotizaciones->filter(fn ($c) => (float) $c->costo_ajustado > 0)->countBy('estado_margen');

        return [
            'utilidad' => $utilidad,
            'porcentaje' => $porcentaje,
            'proyeccion_porcentaje' => $this->redondearProyeccion($analisisPorcentaje['proyeccion']),
            'proyeccion_utilidad' => $this->redondearProyeccion($analisisUtilidad['proyeccion']),
            'variacion_mensual' => $this->redondear($analisisPorcentaje['variacion_mensual']),
            'metodo' => $analisisPorcentaje['metodo'],
            'metodo_utilidad' => $analisisUtilidad['metodo'],
            'utilidad_total' => round((float) $analizadas->sum('utilidad_real'), 2),
            'rentabilidad_promedio' => $costoTotal > 0
                ? round((float) $analizadas->sum('utilidad_real') / $costoTotal * 100, 2)
                : null,
            'analizados' => $analizadas->count(),
            'excluidos_sin_costo' => $vendidas->count() - $analizadas->count(),
            'umbrales' => [
                'verde' => round(config('margen.semaforo.umbral_verde') * 100, 2),
                'amarillo' => round(config('margen.semaforo.umbral_amarillo') * 100, 2),
            ],
            'semaforo' => [
                'VERDE' => $semaforo->get('VERDE', 0),
                'AMARILLO' => $semaforo->get('AMARILLO', 0),
                'ROJO' => $semaforo->get('ROJO', 0),
                'SIN_COSTO' => $cotizaciones->filter(fn ($c) => (float) $c->costo_ajustado <= 0)->count(),
            ],
        ];
    }

    /**
     * @param  list<array{valor: float, inferior: float, superior: float}>  $proyeccion
     * @return list<array{valor: float, inferior: float, superior: float}>
     */
    private function redondearProyeccion(array $proyeccion, int $decimales = 2): array
    {
        return array_map(fn (array $p): array => array_map(fn (float $v) => round($v, $decimales), $p), $proyeccion);
    }

    private function redondear(?float $valor): ?float
    {
        return $valor === null ? null : round($valor, 2);
    }
}
