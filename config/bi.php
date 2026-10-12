<?php

/**
 * Parámetros de los reportes de inteligencia de negocios
 * (App\Services\Reporte\*).
 *
 * - `meses_proyeccion`: cuántos meses hacia adelante se prolonga cada
 *   tendencia. Más de 3 con una recta simple ya es adivinar.
 * - `meses_por_defecto`: periodo que se muestra cuando la pantalla se abre
 *   sin filtro de fechas.
 * - `alerta_aumento_costo`: fracción a partir de la cual el aumento del precio
 *   de un material (o del costo de un presupuesto pendiente, o el sobrecosto
 *   de un pedido) dispara una alerta. Al doble se considera alerta alta.
 * - `alerta_tendencia_mensual`: si el índice de costo de materiales sube a un
 *   ritmo igual o mayor que esta fracción POR MES, se alerta la tendencia.
 *
 * Los umbrales de margen bajo NO están aquí: son los del semáforo del motor
 * (`config/margen.php`), para que "margen bajo" signifique lo mismo en la
 * cotización y en el reporte.
 */
return [
    'meses_proyeccion' => (int) env('BI_MESES_PROYECCION', 3),
    'meses_por_defecto' => (int) env('BI_MESES_POR_DEFECTO', 12),
    'alerta_aumento_costo' => (float) env('BI_ALERTA_AUMENTO_COSTO', 0.10),
    'alerta_tendencia_mensual' => (float) env('BI_ALERTA_TENDENCIA_MENSUAL', 0.02),

    /*
     * Detección de anomalías (App\Services\Reporte\DeteccionAnomaliasService).
     * Umbrales conservadores a propósito: con poco volumen cualquier semana
     * floja parece una caída, y una alerta que se repite sin motivo se deja
     * de leer. Bajarlos cuando haya historia y se vea cuántas se descartan.
     */
    'anomalias' => [
        // Volumen semanal de cotizaciones: se compara la última semana con
        // la mediana de las `semanas_referencia` anteriores (se adapta a la
        // temporada) mediante la cola de Poisson: probabilidad de una semana
        // tan baja (o tan alta) por puro azar. Además se exige un cambio de
        // al menos `variacion_minima` para no alertar por movimientos chicos.
        'p_alta' => 0.001,
        'p_media' => 0.01,
        'variacion_minima' => 0.5,
        'semanas_referencia' => 8,
        // Mínimo de semanas con actividad para juzgar (antes no se opina).
        'minimo_semanas' => 8,
        // Mediana semanal mínima de cotizaciones: por debajo es ruido.
        'minimo_mediana' => 3,
        // Margen bajo: ventas de los últimos N días contra el percentil 10
        // de los últimos 180 días (con al menos `minimo_muestras` ventas).
        'margen_dias' => 14,
        'margen_percentil' => 10,
        'margen_minimo_muestras' => 15,
        'margen_maximo_por_corrida' => 5,
        // Vendedor sin actividad: días sin cotizar > máx(piso, 2 × su ritmo).
        'vendedor_piso_dias' => 7,
        'vendedor_minimo_cotizaciones' => 6,
        // Cliente en riesgo: del grupo que hace el 80 % de las ventas,
        // con ≥ 3 compras, sin comprar > máx(piso, 2 × su intervalo).
        'cliente_piso_dias' => 45,
        'cliente_minimo_compras' => 3,
        'cliente_pareto' => 0.8,
    ],
];
