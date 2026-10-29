{{--
    Pie del sitio público, igual al de xtrapubli.com: una franja azul con los
    cuatro datos de contacto y debajo el logo con el copyright.

    Los datos salen de config('sitio.empresa'), los mismos que alimentan el
    JSON-LD. El NIT no está en el sitio original: se agrega en una línea
    discreta bajo el copyright porque el usuario pidió publicarlo (2026-10-24).
    El nombre del propietario no se publica nunca.
--}}
@php
    $empresa = config('sitio.empresa');

    // "591 765-7-8-9-10" se muestra como en el original: "591 - 765-7-8-9-10",
    // con el número en negrita.
    [$prefijo, $numero] = array_pad(explode(' ', $empresa['telefono_visible'], 2), 2, '');
@endphp

<footer>
    <div class="bg-sitio-azul py-5 font-texto text-white">
        <address class="mx-auto grid max-w-sitio gap-4 px-4 py-[15px] text-center not-italic sm:grid-cols-2 lg:grid-cols-[25.2fr_21.1fr_27.6fr_26.1fr] lg:items-center">
            <p class="px-1.5 py-2.5 text-base leading-[1.3]">
                <x-publico.icono nombre="pin" class="inline h-4 w-4 align-[-2px]" />
                <strong>Centro de Operaciones:</strong>
                {{ $empresa['ciudad'] }} - {{ $empresa['direccion_sitio'] }}
            </p>

            <p class="px-1.5 py-2.5 font-titulo text-base leading-[1.3]">
                <a href="https://wa.me/{{ $empresa['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="hover:underline">
                    <x-publico.icono nombre="whatsapp" class="inline h-4 w-4 align-[-2px]" />
                    {{ $prefijo }} <strong>- {{ $numero }}</strong>
                </a>
            </p>

            <p class="px-1.5 py-2.5 text-base leading-[1.3]">
                <x-publico.icono nombre="sobre" class="inline h-4 w-4 align-[-2px]" />
                <strong>Email:</strong>
                <a href="mailto:{{ $empresa['email'] }}" class="break-all hover:underline">{{ $empresa['email'] }}</a>
            </p>

            <p class="px-1.5 py-2.5 text-base leading-[1.3]">
                <x-publico.icono nombre="globo" class="inline h-4 w-4 align-[-2px]" />
                <strong>Site:</strong>
                <a href="{{ $empresa['web'] }}" class="font-titulo hover:underline">{{ str_replace('https://', '', $empresa['web']) }}</a>
            </p>
        </address>
    </div>

    <div class="bg-white px-4 py-2.5 text-center font-texto text-black">
        <img
            src="{{ asset('img/publico/sitio/logo.webp') }}"
            alt="{{ $empresa['nombre'] }}"
            width="800"
            height="195"
            loading="lazy"
            decoding="async"
            class="mx-auto h-auto w-[200px]"
        >
        <p class="mt-1 text-lg font-medium leading-[1.3]">Copyright {{ date('Y') }}. All rights reserved</p>
        @if (! empty($empresa['nit']))
            <p class="pb-1 text-xs text-slate-500">NIT {{ $empresa['nit'] }}</p>
        @endif
    </div>
</footer>
