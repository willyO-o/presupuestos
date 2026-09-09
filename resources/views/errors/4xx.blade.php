{{--
    Cualquier error 4xx sin plantilla propia (400, 405, 422 fuera de un
    formulario...). Los códigos 404/500/503 NO caen acá: Laravel les da
    tratamiento dedicado, por eso tienen su propio archivo.
--}}
<x-publico.error :codigo="$exception->getStatusCode()" />
