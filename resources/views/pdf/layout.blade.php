{{--
    Envoltorio de todos los documentos PDF (App\Services\Pdf\GeneradorPdf).

    Trae el membrete de la empresa, que es igual en los seis documentos: si
    cambia la dirección o el teléfono, cambia acá y no en seis archivos.

    Es herencia de plantilla (`@extends`) y no un componente con slots a
    propósito: así las variables que inyecta `GeneradorPdf::documento()`
    —$empresa, $titulo, $estilos, $generadoEn— llegan solas a la plantilla y
    cada documento no tiene que reenviarlas una por una en cada uso.

    Las fuentes se declaran por nombre y NO se descargan de internet: Chromium
    genera el PDF sin salir a la red (ver GeneradorPdf::estilos), y esperar a
    un CDN de tipografías en cada documento sumaría segundos y lo dejaría a
    merced de que el servidor tenga salida. Sin Montserrat/Roboto instaladas,
    `pdf.css` cae a la pila del sistema, que es perfectamente legible.

    Secciones:
    - `identificacion`  Bloque derecho del membrete: tipo de documento, número
                        y etiqueta de estado.
    - `contenido`       Cuerpo del documento.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} · {{ $empresa['nombre'] }}</title>

    {{-- El CSS se incrusta en vez de enlazarse: ver GeneradorPdf::estilos(). --}}
    <style>{!! $estilos !!}</style>
</head>
<body>
    <header class="doc-cabecera">
        <div>
            <p class="doc-marca-nombre">{{ $empresa['nombre'] }}</p>
            <p class="doc-marca-lema">{{ $empresa['lema'] }}</p>
            <p class="doc-marca-contacto">
                {{ $empresa['direccion'] }} · {{ $empresa['ciudad'] }}, {{ $empresa['departamento'] }}<br>
                {{ $empresa['telefono_visible'] }} · {{ $empresa['email'] }}
            </p>
        </div>

        <div class="doc-identificacion">
            @yield('identificacion')
        </div>
    </header>

    @yield('contenido')
</body>
</html>
