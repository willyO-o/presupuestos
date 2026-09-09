{{--
    Cualquier error 5xx sin plantilla propia (502, 504...). El mensaje es
    deliberadamente genérico: el detalle del fallo va al log, no a la pantalla
    de un visitante.
--}}
<x-publico.error :codigo="$exception->getStatusCode()" />
