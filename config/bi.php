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
];
