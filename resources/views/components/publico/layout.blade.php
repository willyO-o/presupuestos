{{--
    Layout del sitio público.

    Es Blade y no Inertia a propósito: estas dos páginas tienen que llegar
    renderizadas en el HTML de la primera respuesta para que Google las indexe
    sin depender de que ejecute JavaScript. Todo el <head> de SEO se arma acá
    (title, description, canonical, Open Graph, Twitter y JSON-LD) para que
    ninguna página pública pueda olvidarse de una etiqueta.

    Props:
    - titulo      Se concatena con el nombre de la empresa en el <title>.
    - descripcion Meta description. Google recorta ~155 caracteres.
    - canonical   URL canónica. Por defecto la actual SIN query string, para
                  que /proyectos?categoria=x no compita con /proyectos.
    - imagen      Ruta pública de la imagen de Open Graph (1200x630 idealmente).
    - datos       Array asociativo que se serializa como JSON-LD schema.org.
    - indexable   false en páginas que no son contenido público (una
                  estimación con el contacto de una persona, un error): sale
                  `noindex` aunque el sitio esté en producción.
    - conJs       true solo en el cotizador. El resto del sitio público no
                  carga ni un byte de JavaScript, y así debe seguir: es la
                  mitad de la razón por la que estas páginas son Blade.
--}}
@props([
    'titulo',
    'descripcion',
    'canonical' => null,
    'imagen' => 'img/publico/banner-ejecucion.jpg',
    'datos' => null,
    'indexable' => true,
    'conJs' => false,
])

@php
    $empresa = config('sitio.empresa');
    $urlCanonica = $canonical ?? url()->current();
    $urlImagen = asset($imagen);

    // Fuera de produccion se pide explicitamente no indexar: un staging
    // indexado compite con el sitio real por las mismas busquedas y cuesta
    // mucho sacarlo despues (ver SitioPublicoController::robots).
    $robots = ($indexable && app()->isProduction())
        ? 'index, follow, max-image-preview:large'
        : 'noindex, nofollow';
@endphp

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $titulo }} | {{ $empresa['nombre'] }}</title>
    <meta name="description" content="{{ $descripcion }}">
    <link rel="canonical" href="{{ $urlCanonica }}">
    <meta name="robots" content="{{ $robots }}">
    <meta name="theme-color" content="#1c7fc4">
    <meta name="author" content="{{ $empresa['nombre'] }}">
    <meta name="geo.region" content="BO-L">
    <meta name="geo.placename" content="{{ $empresa['ciudad'] }}">

    {{-- Open Graph: lo que se ve al compartir el enlace por WhatsApp o Facebook. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $empresa['nombre'] }}">
    <meta property="og:locale" content="es_BO">
    <meta property="og:title" content="{{ $titulo }} | {{ $empresa['nombre'] }}">
    <meta property="og:description" content="{{ $descripcion }}">
    <meta property="og:url" content="{{ $urlCanonica }}">
    <meta property="og:image" content="{{ $urlImagen }}">
    <meta property="og:image:alt" content="Trabajos de {{ $empresa['nombre'] }} en punto de venta">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $titulo }} | {{ $empresa['nombre'] }}">
    <meta name="twitter:description" content="{{ $descripcion }}">
    <meta name="twitter:image" content="{{ $urlImagen }}">

    <link rel="icon" type="image/png" href="{{ asset('img/logo/logo-mini.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo/logo-mini.png') }}">

    {{-- Bunny Fonts en vez de Google Fonts: mismo catálogo, sin cookies ni
         tracking (y ya es lo que usa el panel en app.blade.php). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=montserrat:600,700,800|roboto:300,400,500,700&display=swap">

    {{-- Una sola llamada a @vite con las entradas que la página necesita: dos
         llamadas separadas inyectan el cliente de HMR dos veces en desarrollo. --}}
    @vite($conJs ? ['resources/css/publico.css', 'resources/js/publico.js'] : 'resources/css/publico.css')

    @if ($datos)
        <script type="application/ld+json">
            {!! json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
</head>
<body class="flex min-h-screen flex-col">
    <a href="#contenido" class="salto-contenido">Saltar al contenido</a>

    <x-publico.cabecera />

    <main id="contenido" class="flex-grow">
        {{ $slot }}
    </main>

    <x-publico.pie />
    <x-publico.whatsapp-flotante />
</body>
</html>
