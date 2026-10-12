<?php

/**
 * Funciones de IA del módulo de inteligencia de negocios (App\Ai\*).
 *
 * Regla de diseño: los NÚMEROS los calcula el sistema; la IA solo redacta,
 * elige qué consulta de análisis ejecutar y explica. Ver
 * bitacora/CHANGELOG.md (2026-10-12) y .ai/rules/ia.md.
 *
 * - `habilitada`: interruptor general. En false, ninguna llamada sale a un
 *   proveedor: los resúmenes y anomalías usan su plantilla de respaldo y el
 *   asistente avisa que no está disponible.
 * - `presupuesto_mensual_usd`: tope de gasto del mes calendario (hora de la
 *   empresa). Alcanzado el tope, se corta igual que con el interruptor.
 * - `funcionalidades.*.proveedores`: cadena de failover en orden
 *   (proveedor => modelo). El primero responde; si cae por límite de uso o
 *   sobrecarga, responde el siguiente. Un proveedor sin API key en .env se
 *   salta solo.
 * - `precios`: USD por millón de tokens [entrada, salida, lectura de caché].
 *   Solo se usan para estimar el costo de cada llamada contra el tope;
 *   revisarlos cuando el proveedor cambie tarifas (Gemini 3.8 Flash duplica
 *   precio el 01/01/2027).
 */
return [
    'habilitada' => (bool) env('IA_HABILITADA', true),

    'presupuesto_mensual_usd' => (float) env('IA_PRESUPUESTO_MENSUAL_USD', 20),

    /* Porcentaje del tope a partir del cual el panel avisa que se acerca. */
    'aviso_presupuesto' => 0.8,

    'zona_horaria' => env('IA_ZONA_HORARIA', 'America/La_Paz'),

    /*
     * Conexión de solo lectura que usan las herramientas del asistente y los
     * cálculos que alimentan a la IA (ver config/database.php). En tests se
     * apunta a la conexión por defecto (sqlite en memoria).
     */
    'conexion_lectura' => env('IA_DB_CONNECTION', 'mariadb_lectura'),

    'funcionalidades' => [
        'resumen_semanal' => [
            'proveedores' => [
                'gemini' => env('IA_RESUMEN_GEMINI', 'gemini-3.8-flash'),
                'anthropic' => env('IA_RESUMEN_ANTHROPIC', 'claude-sonnet-5-5'),
            ],
            'timeout' => 90,
        ],
        'anomalias' => [
            'proveedores' => [
                'gemini' => env('IA_ANOMALIAS_GEMINI', 'gemini-3.5-flash-lite'),
                'anthropic' => env('IA_ANOMALIAS_ANTHROPIC', 'claude-haiku-5-5'),
            ],
            'timeout' => 60,
        ],
        'asistente' => [
            'proveedores' => [
                'gemini' => env('IA_ASISTENTE_GEMINI', 'gemini-3.8-flash'),
                'anthropic' => env('IA_ASISTENTE_ANTHROPIC', 'claude-sonnet-5-5'),
            ],
            'timeout' => 90,
        ],
    ],

    'asistente' => [
        /* Preguntas por usuario por hora: protege el tope de un uso desbocado. */
        'preguntas_por_hora' => (int) env('IA_ASISTENTE_PREGUNTAS_POR_HORA', 30),
        /* Pasos máximos de herramienta por pregunta (cada paso es una llamada). */
        'max_pasos' => 6,
        /* Filas máximas que una herramienta devuelve al modelo (y al panel). */
        'max_filas' => 24,
    ],

    'precios' => [
        'gemini-3.8-flash' => [0.75, 3.75, 0.075],
        'gemini-3.6-flash' => [0.75, 3.75, 0.075],
        'gemini-3.5-flash-lite' => [0.30, 2.50, 0.03],
        'gemini-3.1-pro-preview' => [2.00, 12.00, 0.20],
        'gemini-2.5-flash' => [0.30, 2.50, 0.03],
        'claude-opus-5-5' => [4.00, 20.00, 0.20],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.20],
        'claude-haiku-5-5' => [0.10, 0.50, 0.01],
    ],

    /*
     * Precio para un modelo que no figura arriba: se asume caro a propósito,
     * para que un modelo nuevo mal configurado no pase el tope sin aviso.
     */
    'precio_por_defecto' => [4.00, 20.00, 0.40],
];
