{{--
    Cabecera del sitio público: réplica de la de xtrapubli.com (barra blanca,
    logo a la izquierda y el menú en Barlow, en mayúsculas). No es fija, igual
    que en el original.

    Los cinco primeros enlaces son los del sitio original y apuntan a las
    secciones de la portada con ancla absoluta (route('inicio') . '#seccion'),
    así el mismo menú sirve desde cualquier página. Cotizador, Mi pedido y
    Acceder no están en el original: son las páginas propias del sistema y van
    al final, con el mismo estilo.

    El menú móvil es un <details>/<summary> nativo: se abre con teclado,
    funciona sin JavaScript y el sitio público no carga ni un byte de JS.
--}}
@php
    $enlaces = [
        ['url' => route('inicio'), 'texto' => 'Inicio', 'activo' => request()->routeIs('inicio')],
        ['url' => route('inicio').'#que-hacemos', 'texto' => '¿Qué hacemos?', 'activo' => false],
        ['url' => route('inicio').'#como-hacemos', 'texto' => '¿Cómo hacemos?', 'activo' => false],
        ['url' => route('inicio').'#proyectos', 'texto' => 'Proyectos', 'activo' => request()->routeIs('proyectos')],
        ['url' => route('inicio').'#contacto', 'texto' => 'Contáctanos', 'activo' => false],
        ['url' => route('cotizador'), 'texto' => 'Cotizador', 'activo' => request()->routeIs('cotizador*')],
        ['url' => route('seguimiento'), 'texto' => 'Mi pedido', 'activo' => request()->routeIs('seguimiento*')],
        ['url' => route('login'), 'texto' => 'Acceder', 'activo' => false],
    ];
@endphp

<header class="relative z-50 bg-white">
    <div class="mx-auto flex h-20 max-w-sitio items-center justify-between px-4">
        <a href="{{ route('inicio') }}" class="flex shrink-0 items-center" aria-label="{{ config('sitio.empresa.nombre') }}, ir al inicio">
            <img
                src="{{ asset('img/publico/sitio/logo.webp') }}"
                alt="{{ config('sitio.empresa.nombre') }}"
                width="800"
                height="195"
                class="h-auto w-[170px] sm:w-[200px]"
                fetchpriority="high"
            >
        </a>

        <nav class="hidden items-center gap-x-5 whitespace-nowrap font-texto text-sm uppercase leading-[1.3] text-black lg:flex xl:gap-x-8 xl:text-base" aria-label="Navegación principal">
            @foreach ($enlaces as $enlace)
                <a
                    href="{{ $enlace['url'] }}"
                    @class([
                        'transition-colors hover:text-sitio-azul',
                        'text-sitio-azul' => $enlace['activo'],
                    ])
                    @if ($enlace['activo']) aria-current="page" @endif
                >{{ $enlace['texto'] }}</a>
            @endforeach
        </nav>

        <details class="group lg:hidden">
            <summary
                class="flex cursor-pointer list-none items-center p-2 text-black marker:content-none"
                aria-label="Abrir menú de navegación"
            >
                <svg class="h-7 w-7 group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg class="hidden h-7 w-7 group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </summary>

            <nav
                class="absolute inset-x-0 top-full border-t border-slate-200 bg-white px-4 py-2 shadow-lg"
                aria-label="Navegación principal (móvil)"
            >
                @foreach ($enlaces as $enlace)
                    <a
                        href="{{ $enlace['url'] }}"
                        @class([
                            'block py-3 text-center font-texto text-xl uppercase text-black hover:text-sitio-azul',
                            'text-sitio-azul' => $enlace['activo'],
                        ])
                    >{{ $enlace['texto'] }}</a>
                @endforeach
            </nav>
        </details>
    </div>
</header>
