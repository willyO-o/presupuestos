{{--
    Tarjeta de un proyecto de la galería.

    `prioritaria` se pasa solo en las primeras tarjetas (las que entran en la
    primera pantalla): esas se cargan con prioridad y el resto en diferido, que
    es lo que mide Core Web Vitals. El width/height explícito reserva el hueco
    de la imagen antes de que baje, para que la grilla no salte (CLS).
--}}
@props(['proyecto', 'prioritaria' => false])

@php
    $categoria = config("sitio.categorias.{$proyecto['categoria']}");
@endphp

<article class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
        <img
            src="{{ asset("img/publico/proyectos/{$proyecto['slug']}.jpg") }}"
            alt="{{ $proyecto['alt'] }}"
            {{-- Medidas reales del archivo: el recorte 4/3 lo hace el contenedor. --}}
            width="800"
            height="436"
            @if ($prioritaria)
                fetchpriority="high"
            @else
                loading="lazy" decoding="async"
            @endif
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        >

        <x-publico.etiqueta :color="$categoria['color']" class="absolute left-2 top-2">
            {{ $proyecto['etiqueta'] }}
        </x-publico.etiqueta>
    </div>

    <div class="p-3">
        <h3 class="truncate text-xs font-bold text-slate-800" title="{{ $proyecto['titulo'] }}">
            {{ $proyecto['titulo'] }}
        </h3>
        <p class="truncate text-[11px] text-slate-500" title="{{ $proyecto['detalle'] }}">
            {{ $proyecto['detalle'] }}
        </p>
    </div>
</article>
