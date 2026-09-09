<?php

/**
 * Parámetros del seguimiento postventa (Proceso 3 del flujo de la empresa:
 * "5. Seguimiento 7 días").
 *
 * - `dias_seguimiento`: días entre la entrega real del pedido y la fecha en
 *   que hay que contactar al cliente. Lo usa
 *   App\Services\Pedido\ProgramarPostventaService al programar el contacto.
 * - `satisfaccion_maxima`: tope de la escala de satisfacción que se registra
 *   en la llamada (1 a N).
 */
return [
    'dias_seguimiento' => (int) env('POSTVENTA_DIAS_SEGUIMIENTO', 7),
    'satisfaccion_maxima' => (int) env('POSTVENTA_SATISFACCION_MAXIMA', 5),
];
