{{--
    Cierre de conversión. Va en las dos páginas públicas: quien llega por la
    portada y quien aterriza directo en la galería tienen que terminar el
    scroll con el mismo par de botones.
--}}
@php
    $empresa = config('sitio.empresa');
@endphp

<section id="contacto" class="relative overflow-hidden bg-marca-noche px-4 py-16 text-white sm:py-20">
    {{-- Va como <img> y no como background-image de CSS: así se puede diferir
         su carga y el navegador la encuentra en el preload scanner. --}}
    <img
        src="{{ asset('img/publico/fondo-contacto.jpg') }}"
        alt=""
        width="1408"
        height="768"
        loading="lazy"
        decoding="async"
        aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover opacity-20"
    >
    <div class="trama-plano pointer-events-none absolute inset-0 opacity-20" aria-hidden="true"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-marca-noche via-marca-noche/85 to-marca-noche" aria-hidden="true"></div>

    <div class="relative z-10 mx-auto max-w-3xl text-center">
        <h2 class="mb-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
            ¿Listo para ejecutar tu próxima campaña?
        </h2>
        <p class="mb-2 text-sm font-medium text-slate-300">
            Trabajemos juntos para que tu marca tenga impacto real en el punto de venta.
        </p>
        <p class="mb-8 text-sm font-light text-slate-400">
            Fabricación e instalación a nivel nacional, con garantía técnica asegurada.
        </p>

        <div class="mx-auto max-w-md rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl backdrop-blur-md sm:p-8">
            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-marca-celeste">
                Asesoría y cotizaciones
            </p>

            <div class="flex flex-col gap-3">
                <x-publico.enlace-whatsapp
                    mensaje="Hola XtraPubli, quiero cotizar un proyecto."
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-marca-whatsapp px-4 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-whatsapp-oscuro"
                >
                    <x-publico.icono nombre="whatsapp" class="h-5 w-5" />
                    Escribir por WhatsApp
                </x-publico.enlace-whatsapp>

                <a
                    href="mailto:{{ $empresa['email'] }}?subject={{ rawurlencode('Solicitud de cotización') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/20"
                >
                    <x-publico.icono nombre="correo" class="h-5 w-5 text-marca-celeste" />
                    Enviar un correo
                </a>

                <a
                    href="tel:{{ $empresa['telefono'] }}"
                    class="flex w-full items-center justify-center gap-2 px-4 py-2 text-xs font-medium text-slate-300 transition-colors hover:text-white"
                >
                    <x-publico.icono nombre="telefono" class="h-4 w-4" />
                    O llámanos: {{ $empresa['telefono_visible'] }}
                </a>
            </div>
        </div>
    </div>
</section>
