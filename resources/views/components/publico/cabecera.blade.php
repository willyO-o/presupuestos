{{--
    Cabecera del sitio público.

    El menú móvil es un <details>/<summary> nativo en vez de un botón con
    JavaScript: se abre con teclado, funciona sin JS y evita cargar un bundle
    entero para una sola interacción. El icono cambia con la variante `open:`
    de Tailwind.

    Los enlaces de sección apuntan siempre a la portada con ancla absoluta
    (route('inicio') . '#seccion'), así el mismo menú sirve en /proyectos.
--}}
@php
    $enlaces = [
        ['url' => route('inicio'), 'texto' => 'Inicio', 'activo' => request()->routeIs('inicio')],
        ['url' => route('inicio').'#que-hacemos', 'texto' => '¿Qué hacemos?', 'activo' => false],
        ['url' => route('inicio').'#como-lo-hacemos', 'texto' => '¿Cómo lo hacemos?', 'activo' => false],
        ['url' => route('proyectos'), 'texto' => 'Proyectos', 'activo' => request()->routeIs('proyectos')],
        ['url' => route('inicio').'#contacto', 'texto' => 'Contáctanos', 'activo' => false],
    ];
@endphp

<header class="sticky top-0 z-50 border-b border-slate-100 bg-white/95 shadow-sm backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-contenido items-center justify-between px-4 sm:px-6">
        <a href="{{ route('inicio') }}" class="flex shrink-0 items-center" aria-label="{{ config('sitio.empresa.nombre') }}, ir al inicio">
            <img
                src="{{ asset('img/logo/logo.webp') }}"
                alt="{{ config('sitio.empresa.nombre') }}"
                width="148"
                height="36"
                class="h-8 w-auto sm:h-9"
                fetchpriority="high"
            >
        </a>

        <nav class="hidden items-center gap-6 text-xs font-semibold uppercase tracking-wide text-slate-600 md:flex lg:text-sm" aria-label="Navegación principal">
            @foreach ($enlaces as $enlace)
                <a
                    href="{{ $enlace['url'] }}"
                    @class([
                        'transition-colors hover:text-marca-azul',
                        'font-bold text-marca-azul' => $enlace['activo'],
                    ])
                    @if ($enlace['activo']) aria-current="page" @endif
                >{{ $enlace['texto'] }}</a>
            @endforeach

            <a
                href="{{ route('login') }}"
                class="rounded-full border border-marca-azul px-4 py-1.5 text-marca-azul transition-colors hover:bg-marca-azul hover:text-white"
            >Acceder</a>
        </nav>

        <details class="group relative md:hidden">
            <summary
                class="flex cursor-pointer list-none items-center rounded-lg p-2 text-slate-700 marker:content-none hover:text-marca-azul"
                aria-label="Abrir menú de navegación"
            >
                <svg class="h-6 w-6 group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg class="hidden h-6 w-6 group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </summary>

            <nav
                class="absolute right-0 top-full z-50 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                aria-label="Navegación principal (móvil)"
            >
                @foreach ($enlaces as $enlace)
                    <a
                        href="{{ $enlace['url'] }}"
                        @class([
                            'block rounded-lg px-3 py-2.5 text-sm font-medium uppercase text-slate-700 hover:bg-slate-50 hover:text-marca-azul',
                            'font-bold text-marca-azul' => $enlace['activo'],
                        ])
                    >{{ $enlace['texto'] }}</a>
                @endforeach

                <a
                    href="{{ route('login') }}"
                    class="mt-1 block rounded-lg bg-marca-azul px-3 py-2.5 text-center text-sm font-semibold uppercase text-white hover:bg-marca-azul-oscuro"
                >Acceder al sistema</a>
            </nav>
        </details>
    </div>
</header>
