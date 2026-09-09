<?php

/*
|--------------------------------------------------------------------------
| Cotizador público (estimación en línea, sin login)
|--------------------------------------------------------------------------
|
| Parámetros de la sección `/cotizador` del sitio público: el visitante arma
| una lista de trabajos y el sistema le devuelve un PRECIO APROXIMADO usando
| exactamente el mismo motor que usa ventas (receta/BOM del producto →
| App\Services\Calculo\CosteoProductoService → MotorMargenService → IVA).
|
| Lo que NO se decide acá: las tasas de impuestos y los umbrales del semáforo
| (`config/margen.php`) ni el factor/margen de cada nivel de complejidad
| (tabla `tipo_proyecto`, CRUD). Este archivo solo define cómo se comporta la
| ventanilla pública.
|
| Tras editarlo en producción hay que correr `php artisan config:clear`.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Vigencia de la estimación
    |--------------------------------------------------------------------------
    |
    | Días que la estimación se considera válida. Es el número que se le
    | promete al visitante en pantalla ("válida hasta el ..."), así que cada
    | estimación guarda ADEMÁS su propia `vigencia_dias` y su
    | `fecha_vencimiento`: bajar este parámetro mañana no puede acortar
    | retroactivamente una estimación ya entregada.
    |
    | Es más corta que `cotizacion.dias_vencimiento` (15) a propósito: un
    | presupuesto formal lo revisó un vendedor con precios confirmados; esto
    | es una estimación automática sobre el precio de material del día.
    |
    */

    'vigencia_dias' => (int) env('COTIZADOR_VIGENCIA_DIAS', 7),

    /*
    |--------------------------------------------------------------------------
    | Holgura del rango aproximado
    |--------------------------------------------------------------------------
    |
    | Fracción hacia arriba y hacia abajo con la que se muestra el resultado
    | como RANGO (0.15 = ±15 %). El cotizador no conoce acabados, logística ni
    | instalación, así que dar un número exacto sería prometer algo que el
    | presupuesto formal no va a respetar. El rango es la forma honesta de
    | decir "por acá va la cosa".
    |
    */

    'holgura' => (float) env('COTIZADOR_HOLGURA', 0.15),

    /*
    |--------------------------------------------------------------------------
    | Descargas del documento imprimible
    |--------------------------------------------------------------------------
    |
    | Veces que se puede emitir el documento (`/cotizador/{codigo}/documento`)
    | de UNA estimación. Es un tope por fila, no un rate limit: sin él, un solo
    | código válido alcanza para pedir el documento en bucle para siempre.
    |
    | El botón "Descargar" desaparece de la página de la estimación en cuanto
    | se usa una vez. Este número es más alto que 1 a propósito: el diálogo de
    | impresión del navegador se puede cancelar sin querer, y desde el propio
    | documento se puede reintentar sin quedarse sin presupuesto. Al llegar al
    | tope, el documento deja de emitirse y queda el contacto por WhatsApp.
    |
    */

    'descargas_maximas' => (int) env('COTIZADOR_DESCARGAS_MAXIMAS', 3),

    /*
    |--------------------------------------------------------------------------
    | Nivel de complejidad aplicado
    |--------------------------------------------------------------------------
    |
    | Nombre de un `tipo_proyecto` (CRUD Tipos de proyecto) del que salen el
    | factor de complejidad y el margen mínimo. Sin valor —o si el nombre no
    | existe/está inactivo— se usa el primer tipo activo por `orden`, y si no
    | hay ninguno, `config('cotizacion.margen_sugerido')` con factor 1.
    |
    | Se guarda el NOMBRE y no el id para que sobreviva a un reseeder.
    |
    */

    'tipo_proyecto' => env('COTIZADOR_TIPO_PROYECTO'),

    /*
    |--------------------------------------------------------------------------
    | Límites de una estimación
    |--------------------------------------------------------------------------
    |
    | Techos duros que aplican los Form Requests. No son cosmética: cada línea
    | recorre el BOM del producto y evalúa fórmulas, así que sin tope un bot
    | podría pedir 10.000 líneas de 999 m y convertir el endpoint público en
    | un ataque de CPU. Un trabajo más grande que esto se cotiza hablando con
    | un vendedor, que es justo lo que queremos que pase.
    |
    */

    'limites' => [
        'lineas' => (int) env('COTIZADOR_MAX_LINEAS', 8),
        'cantidad' => (int) env('COTIZADOR_MAX_CANTIDAD', 500),
        // Metros. Una gigantografía de fachada rara vez pasa de 30 m de lado.
        'dimension' => (float) env('COTIZADOR_MAX_DIMENSION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Antiabuso
    |--------------------------------------------------------------------------
    |
    | `segundos_minimos`: tiempo mínimo entre abrir el formulario y enviarlo.
    | La marca de tiempo vive en la SESIÓN, no en un input oculto, para que no
    | se pueda falsificar desde el navegador.
    |
    | `throttle`: límites de los rate limiters registrados en
    | App\Providers\AppServiceProvider (`cotizador-calcular`,
    | `cotizador-guardar`, `cotizador-consultar`, `cotizador-descargar`).
    |
    | `guardar` y `descargar` se miden por HORA y no por minuto: nadie pide
    | cinco presupuestos distintos en sesenta segundos, y así un bot que
    | insista queda afuera durante una hora entera en vez de reintentar cada
    | minuto. `guardar_dia` es el techo diario de la misma IP.
    |
    | `guardar_global` es el techo de TODO el sitio por hora, sin importar de
    | dónde venga. Los límites por IP no frenan una avalancha repartida entre
    | cientos de direcciones; este sí, y protege la bandeja de ventas de
    | ahogarse. Ponelo bastante por encima del tráfico real: cuando salta,
    | también deja fuera a clientes legítimos.
    |
    */

    'segundos_minimos' => (int) env('COTIZADOR_SEGUNDOS_MINIMOS', 4),

    'throttle' => [
        // Por minuto y por IP (se llama a cada cambio de medida).
        'calcular' => (int) env('COTIZADOR_THROTTLE_CALCULAR', 30),
        // Por hora y por IP.
        'guardar' => (int) env('COTIZADOR_THROTTLE_GUARDAR', 5),
        // Por día y por IP.
        'guardar_dia' => (int) env('COTIZADOR_THROTTLE_GUARDAR_DIA', 15),
        // Por hora, para todo el sitio.
        'guardar_global' => (int) env('COTIZADOR_THROTTLE_GUARDAR_GLOBAL', 120),
        // Por minuto y por IP.
        'consultar' => (int) env('COTIZADOR_THROTTLE_CONSULTAR', 20),
        // Por hora y por IP: emitir el documento arma un presupuesto entero.
        'descargar' => (int) env('COTIZADOR_THROTTLE_DESCARGAR', 12),
    ],

];
