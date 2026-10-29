{{--
    Cierre de conversión, igual al de xtrapubli.com: foto oscurecida de fondo,
    el botón verde de WhatsApp y el QR "Escríbenos / Scaneame" del original.

    Va en la portada, la galería y el cotizador: quien llega por cualquiera de
    ellas termina el scroll con el mismo contacto. Lleva el ancla #contacto a
    la que apunta "Contáctanos" del menú.
--}}
<section id="contacto" class="relative overflow-hidden bg-black pb-[55px] pt-10 text-white">
    {{-- Va como <img> y no como background-image: así se difiere su carga. --}}
    <img
        src="{{ asset('img/publico/sitio/contacto-fondo.jpg') }}"
        alt=""
        width="1920"
        height="1080"
        loading="lazy"
        decoding="async"
        aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover opacity-30"
    >

    <div class="relative mx-auto max-w-sitio px-4">
        <h2 class="mx-auto max-w-[854px] px-1.5 pb-2.5 pt-6 text-center font-texto text-[32px] font-bold leading-[1.3] sm:pt-[67px] sm:text-[40px]">
            ¿Listo para ejecutar tu próxima campaña?
        </h2>

        <div class="mx-auto mt-[15px] flex w-[96%] flex-col items-center border-2 border-black/70 px-1.5 py-[25px] text-center font-texto shadow-[0_0_8px_rgba(255,255,255,0.06)]">
            <p class="text-xl leading-[1.3] sm:text-[21px]">
                Trabajemos juntos para que tu marca tenga impacto real en el punto de venta.
            </p>
            <p class="mt-[25px] text-xl leading-[1.3] sm:text-[21px]">
                Contáctanos para desarrollar tu próxima implementación.
            </p>

            <x-publico.enlace-whatsapp
                mensaje="Hola XtraPubli, deseo solicitar una cotización."
                class="sitio-boton-cta mt-5"
            >
                <span class="inline-flex items-center gap-1.5 text-xl sm:text-[22px]">
                    <x-publico.icono nombre="whatsapp" class="h-6 w-6" />
                    Solicitar Cotización
                </span>
                <span class="sitio-boton-cta-pie">o una visita a su oficina.</span>
            </x-publico.enlace-whatsapp>

            <x-publico.enlace-whatsapp
                mensaje="Hola XtraPubli, deseo solicitar una cotización."
                class="mt-3 block"
                aria-label="Escríbenos por WhatsApp (código QR)"
            >
                <img
                    src="{{ asset('img/publico/sitio/qr-whatsapp.svg') }}"
                    alt="Código QR para escribirnos por WhatsApp"
                    width="290"
                    height="290"
                    loading="lazy"
                    decoding="async"
                    class="h-auto w-[250px]"
                >
            </x-publico.enlace-whatsapp>

            <img
                src="{{ asset('img/publico/sitio/estrellas.png') }}"
                alt="Cinco estrellas"
                width="300"
                height="58"
                loading="lazy"
                decoding="async"
                class="mt-1 h-auto w-[100px]"
            >
        </div>
    </div>
</section>
