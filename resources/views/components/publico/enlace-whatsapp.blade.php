{{--
    Enlace a WhatsApp con el número de config/sitio.php y un mensaje ya escrito.

    Centraliza dos cosas fáciles de equivocar cuando el enlace se repite por
    toda la página: el número (que cambia entre staging y producción) y el
    rawurlencode del texto, sin el cual los acentos y espacios rompen el
    prellenado del chat.
--}}
@props(['mensaje' => null])

@php
    $url = 'https://wa.me/'.config('sitio.empresa.whatsapp')
        .($mensaje ? '?text='.rawurlencode($mensaje) : '');
@endphp

<a
    href="{{ $url }}"
    target="_blank"
    rel="noopener noreferrer"
    {{ $attributes }}
>{{ $slot }}</a>
