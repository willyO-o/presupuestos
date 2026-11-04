<?php

/**
 * Parámetros del módulo de Cotizaciones (presupuestos).
 *
 * - `margen_sugerido`: fracción que se suma al costo de materiales del BOM
 *   (App\Services\Calculo\CosteoProductoService) para proponer el precio
 *   unitario de una línea. El vendedor siempre puede sobrescribirlo (ver
 *   database-design.md §8: "precio_unitario tomado del cálculo del BOM +
 *   margen, editable por el vendedor").
 * - `impuesto_porcentaje`: IVA de Bolivia, usado solo como atajo en el
 *   formulario (el campo `impuesto` de `cotizacion` guarda un monto, no un
 *   porcentaje).
 * - `dias_vencimiento`: días por defecto entre `fecha` y
 *   `fecha_vencimiento` al crear una cotización.
 * - `costo_hora_mano_obra`: Bs por hora de trabajo. Lo usa el botón "Mano de
 *   obra" de la hoja de costos y la calculadora de instalación; el vendedor
 *   asigna las horas según el mueble o el trabajo.
 * - `recargo_nocturno`: porcentaje que se suma a las horas de una instalación
 *   nocturna (horas extra). Se puede corregir en cada línea.
 * - `instalacion_con_iva`: valor inicial de la casilla "Instalación con IVA"
 *   al crear una cotización. `false` reproduce el Excel de margen: la
 *   instalación se suma después del IVA.
 */
return [
    'margen_sugerido' => (float) env('COTIZACION_MARGEN_SUGERIDO', 0.45),
    'impuesto_porcentaje' => (float) env('COTIZACION_IMPUESTO_PORCENTAJE', 13),
    'dias_vencimiento' => (int) env('COTIZACION_DIAS_VENCIMIENTO', 15),
    'costo_hora_mano_obra' => (float) env('COTIZACION_COSTO_HORA_MANO_OBRA', 18),
    'recargo_nocturno' => (float) env('COTIZACION_RECARGO_NOCTURNO', 50),
    'instalacion_con_iva' => (bool) env('COTIZACION_INSTALACION_CON_IVA', false),
];
