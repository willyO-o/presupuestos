{{--
    Etiqueta de categoría de la galería.

    El config guarda un color semántico ('azul', 'oscuro', ...) y la traducción
    a clases vive acá: así config/sitio.php no acumula CSS y Tailwind ve las
    clases literales al escanear (una clase armada por concatenación en PHP no
    la detectaría y se purgaría del bundle).
--}}
@props(['color' => 'azul'])

@php
    $clases = match ($color) {
        'oscuro' => 'bg-marca-oscuro text-white',
        'turquesa' => 'bg-marca-turquesa text-white',
        'ambar' => 'bg-marca-ambar text-marca-oscuro',
        'verde' => 'bg-marca-verde text-white',
        default => 'bg-marca-azul text-white',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold shadow-sm {$clases}"]) }}>
    {{ $slot }}
</span>
