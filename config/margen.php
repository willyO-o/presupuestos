<?php

/**
 * Constantes del motor de margen automático (App\Services\Calculo\MotorMargenService),
 * portadas del Excel `11_Sistema_Margen_Automatico_Xtrapubli`.
 *
 * ÚNICO lugar donde viven estos números: no repetir 0.03 / 0.25 / 0.13 ni los
 * umbrales del semáforo sueltos en controladores, modelos o componentes Vue.
 *
 * Impuestos (régimen tributario boliviano), como fracción:
 * - `it`  Impuesto a las Transacciones, 3 % sobre el precio.
 * - `iue` Impuesto sobre las Utilidades de las Empresas, 25 % sobre la
 *         utilidad antes de IUE (precio − costo ajustado − IT).
 * - `iva` Impuesto al Valor Agregado, 13 % sobre el precio; es el que ve el
 *         cliente en el documento.
 *
 * Semáforo de decisión: se comparan contra el COSTO AJUSTADO (no contra el
 * precio), tal como la hoja original.
 * - utilidad real > 30 % del costo ajustado → VERDE   (ACEPTAR)
 * - utilidad real > 15 % del costo ajustado → AMARILLO (REVISAR PRECIO)
 * - en otro caso                            → ROJO     (NO ACEPTAR)
 *
 * Los niveles de complejidad (factor y margen mínimo) NO están aquí: son
 * datos administrables desde el CRUD de Tipos de Proyecto (tabla
 * `tipo_proyecto`, App\Models\TipoProyecto).
 */
return [
    'impuestos' => [
        'it' => (float) env('MARGEN_IT', 0.03),
        'iue' => (float) env('MARGEN_IUE', 0.25),
        'iva' => (float) env('MARGEN_IVA', 0.13),
    ],

    'semaforo' => [
        'umbral_verde' => (float) env('MARGEN_UMBRAL_VERDE', 0.30),
        'umbral_amarillo' => (float) env('MARGEN_UMBRAL_AMARILLO', 0.15),
    ],
];
