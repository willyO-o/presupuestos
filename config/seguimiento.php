<?php

/*
|--------------------------------------------------------------------------
| Seguimiento público de pedidos (/seguimiento, sin login)
|--------------------------------------------------------------------------
|
| El cliente consulta en qué etapa va su pedido con DOS datos: el número de
| pedido (`PED-AAAAMMDD-XXXXX`) y los últimos 4 dígitos del teléfono o del NIT
| registrados en su ficha. El número solo no alcanza: viaja en capturas,
| WhatsApp reenviados y documentos impresos, y el detalle lleva montos,
| pagos y el nombre del cliente.
|
| Tras editarlo en producción hay que correr `php artisan config:clear`.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limiter `seguimiento-buscar`
    |--------------------------------------------------------------------------
    |
    | Cuatro dígitos son 10.000 combinaciones: lo que los protege es el límite
    | POR PEDIDO, no el de IP. Con 20 intentos por hora contra un mismo
    | número, adivinar a fuerza bruta lleva semanas aunque el ataque se
    | reparta entre cientos de direcciones. El límite por IP frena al que
    | prueba números de pedido al azar.
    |
    */

    'throttle' => [
        // Por minuto y por IP.
        'por_minuto' => (int) env('SEGUIMIENTO_THROTTLE_MINUTO', 10),
        // Por hora y por número de pedido, venga de donde venga.
        'por_pedido_hora' => (int) env('SEGUIMIENTO_THROTTLE_PEDIDO_HORA', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pedidos recordados por sesión
    |--------------------------------------------------------------------------
    |
    | Tras verificar, el pedido queda abierto en la sesión del navegador para
    | que `/seguimiento/{numero}` se pueda recargar sin volver a pedir los
    | dígitos. Se recuerdan los últimos N (una empresa puede seguir varios).
    |
    */

    'pedidos_por_sesion' => 10,

];
