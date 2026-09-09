{{-- Todas las variantes filtradas (?categoria=) declaran la misma canónica:
     es el mismo catálogo recortado, no cinco páginas distintas compitiendo
     entre sí por el mismo contenido. --}}
<x-publico.layout
    :titulo="$titulo"
    :descripcion="$descripcion"
    :canonical="route('proyectos')"
    imagen="img/publico/proyectos/implementacion-de-pasillo.jpg"
    :datos="$datosEstructurados"
>
    {{-- ==================== ENCABEZADO ==================== --}}
    <section class="bg-white px-4 pb-8 pt-10 text-center">
        <div class="mx-auto max-w-4xl">
            <nav class="mb-4 text-xs text-slate-500" aria-label="Migas de pan">
                <a href="{{ route('inicio') }}" class="hover:text-marca-azul hover:underline">Inicio</a>
                <span class="mx-1.5 text-slate-300">/</span>
                @if ($filtro)
                    <a href="{{ route('proyectos') }}" class="hover:text-marca-azul hover:underline">Proyectos</a>
                    <span class="mx-1.5 text-slate-300">/</span>
                    <span class="font-medium text-slate-700">{{ $titulo }}</span>
                @else
                    <span class="font-medium text-slate-700">Proyectos</span>
                @endif
            </nav>

            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl md:text-4xl">
                @if ($filtro)
                    {{ $titulo }}
                @else
                    Proyectos y ejecuciones realizadas
                @endif
            </h1>
            <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-600 sm:text-base">
                {{ $descripcion }}
            </p>
        </div>
    </section>

    {{-- ==================== FILTROS ====================
         Son enlaces reales, no botones con JavaScript: se pueden compartir,
         funcionan sin JS y el navegador los recuerda en el historial. --}}
    <nav class="sticky top-16 z-30 border-y border-slate-100 bg-white/95 px-4 py-3 backdrop-blur-md" aria-label="Filtrar proyectos por categoría">
        <ul class="mx-auto flex max-w-contenido flex-wrap items-center justify-center gap-2">
            <li>
                <a
                    href="{{ route('proyectos') }}"
                    @class([
                        'inline-block rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors',
                        'border-marca-azul bg-marca-azul text-white' => ! $filtro,
                        'border-slate-200 text-slate-600 hover:border-marca-azul hover:text-marca-azul' => (bool) $filtro,
                    ])
                    @if (! $filtro) aria-current="page" @endif
                >Todos</a>
            </li>

            @foreach ($categorias as $slug => $categoria)
                <li>
                    <a
                        href="{{ route('proyectos', ['categoria' => $slug]) }}"
                        @class([
                            'inline-block rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors',
                            'border-marca-azul bg-marca-azul text-white' => $filtro === $slug,
                            'border-slate-200 text-slate-600 hover:border-marca-azul hover:text-marca-azul' => $filtro !== $slug,
                        ])
                        @if ($filtro === $slug) aria-current="page" @endif
                    >{{ $categoria['nombre'] }} <span class="opacity-60">({{ $categoria['total'] }})</span></a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- ==================== GALERÍA ==================== --}}
    <section class="mx-auto w-full max-w-contenido px-3 py-8 sm:px-4 sm:py-10">
        @if ($proyectos === [])
            <p class="py-16 text-center text-sm text-slate-500">
                Todavía no hay proyectos publicados en esta categoría.
            </p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($proyectos as $indice => $proyecto)
                    {{-- Las primeras cuatro entran en la primera pantalla: se cargan
                         con prioridad y el resto en diferido. --}}
                    <x-publico.tarjeta-proyecto :proyecto="$proyecto" :prioritaria="$indice < 4" />
                @endforeach
            </div>
        @endif
    </section>

    <x-publico.cta-contacto />
</x-publico.layout>
