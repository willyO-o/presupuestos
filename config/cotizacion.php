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
 */
return [
    'margen_sugerido' => (float) env('COTIZACION_MARGEN_SUGERIDO', 0.45),
    'impuesto_porcentaje' => (float) env('COTIZACION_IMPUESTO_PORCENTAJE', 13),
    'dias_vencimiento' => (int) env('COTIZACION_DIAS_VENCIMIENTO', 15),
];
