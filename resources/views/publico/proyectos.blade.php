{{--
    Galería: réplica de xtrapubli.com/galeria-proyectos (logo, título, raya
    azul y las fotos en columnas, cada una en su proporción).

    Los filtros por categoría no están en el original y se conservan: son
    enlaces reales (funcionan sin JS y se pueden compartir), y todas las
    variantes filtradas declaran la misma canónica para no competir entre sí.
--}}
<x-publico.layout
    :titulo="$titulo"
    :descripcion="$descripcion"
    :canonical="route('proyectos')"
    imagen="img/publico/proyectos/delizia-kefir-exhibidor.jpg"
    :datos="$datosEstructurados"
>
    {{-- ==================== ENCABEZADO ==================== --}}
    <section class="bg-white px-4 pb-6 pt-5 text-center font-texto text-black">
        <div class="mx-auto max-w-sitio">
            <img
                src="{{ asset('img/publico/sitio/logo.webp') }}"
                alt=""
                width="800"
                height="195"
                class="mx-auto h-auto w-[185px]"
            >

            <h1 class="mt-5 font-texto text-[32px] font-bold leading-[1.3] sm:text-[40px]">
                @if ($filtro)
                    {{ $titulo }}
                @else
                    Proyectos y ejecuciones realizadas
                @endif
            </h1>
            <p class="mx-auto mt-3 max-w-[900px] text-lg leading-[1.3] sm:text-xl">
                @if ($filtro)
                    {{ $descripcion }}
                @else
                    Cada proyecto refleja nuestro compromiso con la calidad, la ejecución y la presencia de marca.
                @endif
            </p>
            <span class="sitio-divisor mt-[45px] w-[175px] bg-sitio-azul" aria-hidden="true"></span>

            {{-- Filtros: enlaces de verdad, no botones con JavaScript. --}}
            <nav class="mt-8" aria-label="Filtrar proyectos por categoría">
                <ul class="flex flex-wrap items-center justify-center gap-2">
                    <li>
                        <a
                            href="{{ route('proyectos') }}"
                            @class([
                                'inline-block rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors',
                                'border-sitio-azul bg-sitio-azul text-white' => ! $filtro,
                                'border-slate-300 text-slate-700 hover:border-sitio-azul hover:text-sitio-azul' => (bool) $filtro,
                            ])
                            @if (! $filtro) aria-current="page" @endif
                        >Todos</a>
                    </li>

                    @foreach ($categorias as $slug => $categoria)
                        <li>
                            <a
                                href="{{ route('proyectos', ['categoria' => $slug]) }}"
                                @class([
                                    'inline-block rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors',
                                    'border-sitio-azul bg-sitio-azul text-white' => $filtro === $slug,
                                    'border-slate-300 text-slate-700 hover:border-sitio-azul hover:text-sitio-azul' => $filtro !== $slug,
                                ])
                                @if ($filtro === $slug) aria-current="page" @endif
                            >{{ $categoria['nombre'] }} <span class="opacity-60">({{ $categoria['total'] }})</span></a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </section>

    {{-- ==================== GALERÍA ==================== --}}
    <section class="bg-white px-4 pb-[60px] pt-4">
        <div class="mx-auto max-w-[1150px]">
            @if ($proyectos === [])
                <p class="py-16 text-center font-texto text-base text-slate-500">
                    Todavía no hay proyectos publicados en esta categoría.
                </p>
            @else
                <div class="sitio-galeria">
                    @foreach ($proyectos as $indice => $proyecto)
                        {{-- Las primeras fotos entran en la primera pantalla: se
                             cargan con prioridad y el resto en diferido. Las
                             medidas reales reservan el hueco antes de que baje
                             la foto, para que las columnas no salten. --}}
                        <figure class="sitio-galeria-item group">
                            <img
                                src="{{ asset("img/publico/proyectos/{$proyecto['slug']}.jpg") }}"
                                alt="{{ $proyecto['alt'] }}"
                                width="{{ $proyecto['ancho'] }}"
                                height="{{ $proyecto['alto'] }}"
                                @if ($indice < 5)
                                    fetchpriority="high"
                                @else
                                    loading="lazy" decoding="async"
                                @endif
                                class="block h-auto w-full"
                            >
                            <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-3 pb-2.5 pt-8 font-texto text-sm font-semibold leading-tight text-white opacity-0 transition-opacity group-hover:opacity-100">
                                {{ $proyecto['titulo'] }}
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-publico.cta-contacto />
</x-publico.layout>
