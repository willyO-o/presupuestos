{{--
    Portada: réplica de xtrapubli.com (pedido del usuario, 2026-10-29).

    Mismas secciones, textos, imágenes, colores, fuentes y medidas que el
    sitio original; los espaciados se tomaron de su CSS y de capturas a
    1440 px. Diferencias deliberadas: un solo <h1> (el original usa <h1> en
    cada título y eso confunde al buscador) y la ortografía corregida.

    Los textos de las tarjetas salen de config/sitio.php; acá solo el marcado.
--}}
@php
    $empresa = config('sitio.empresa');
@endphp

<x-publico.layout
    titulo="Exhibidores y Soluciones Visuales"
    :descripcion="$empresa['descripcion']"
    :datos="$datosEstructurados"
>
    {{-- ==================== HERO ==================== --}}
    <section id="inicio" class="relative overflow-hidden bg-sitio-marino px-4 pb-[35px] pt-16 text-center text-white sm:pt-[110px]">
        <img
            src="{{ asset('img/publico/sitio/hero-fondo.jpg') }}"
            alt=""
            width="1584"
            height="709"
            fetchpriority="high"
            aria-hidden="true"
            class="absolute inset-0 h-full w-full object-cover opacity-30"
        >

        <div class="relative mx-auto max-w-sitio">
            <h1 class="font-titulo text-[38px] font-bold leading-[1.3] sm:text-[45px]">
                {{ $empresa['lema'] }}
            </h1>
            <p class="mt-[30px] font-titulo text-xl leading-[1.3] sm:text-2xl">
                Ejecución real en punto de venta para marcas que buscan resultados.
            </p>
            <p class="mt-[26px] font-titulo text-lg leading-[1.3] sm:text-xl">
                Elaboramos e implementamos soluciones que aseguran presencia, impacto y cumplimiento en cada campaña.
            </p>

            <div class="pb-[25px] pt-[44px]">
                <x-publico.enlace-whatsapp
                    mensaje="Hola XtraPubli, deseo solicitar una cotización."
                    class="sitio-boton-cta"
                >
                    <span class="inline-flex items-center gap-1.5 text-[22px] sm:text-[27px]">
                        <x-publico.icono nombre="whatsapp" class="h-7 w-7" />
                        Solicitar Cotización
                    </span>
                    <span class="sitio-boton-cta-pie">o una visita a su oficina.</span>
                </x-publico.enlace-whatsapp>
            </div>
        </div>
    </section>

    {{-- ==================== ¿QUÉ HACEMOS? ==================== --}}
    <section id="que-hacemos" class="bg-white px-4 pb-[84px] pt-10 text-center text-black">
        <div class="mx-auto max-w-sitio">
            <h2 class="pt-[11px] font-titulo text-3xl font-bold leading-[1.3] sm:text-[40px]">¿Qué Hacemos?</h2>
            <p class="mt-[26px] font-titulo text-[22px] leading-[1.3] sm:text-[27px]">
                Ejecutamos ideas que generan impacto, no solo producimos
            </p>

            <div class="mx-auto mt-6 max-w-[1160px] font-titulo text-lg leading-normal sm:text-xl">
                <p>
                    En {{ $empresa['nombre'] }} ayudamos a las marcas a destacar en el punto de venta mediante
                    soluciones visuales, exhibidores y material POP ejecutado con calidad y responsabilidad.
                </p>
                <p>
                    Nos enfocamos en que cada proyecto funcione correctamente desde la producción hasta la
                    implementación final.
                </p>
                <p class="mt-[30px] font-bold italic">Nos aseguramos de que funcionen en el punto de venta.</p>
            </div>

            <div class="mx-auto mt-[30px] grid max-w-[1100px] gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($servicios as $servicio)
                    <article class="sitio-tarjeta bg-sitio-gris">
                        <img
                            src="{{ asset($servicio['imagen']) }}"
                            alt=""
                            width="225"
                            height="225"
                            loading="lazy"
                            decoding="async"
                            class="box-content h-auto w-16 p-2.5"
                        >
                        <h3 class="sitio-tarjeta-titulo">{{ $servicio['titulo'] }}</h3>
                        <p class="sitio-tarjeta-texto">{{ $servicio['descripcion'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== ¿CÓMO HACEMOS? ==================== --}}
    <section id="como-hacemos" class="bg-sitio-azul px-4 pb-[145px] pt-[73px] text-center">
        <div class="mx-auto max-w-sitio">
            <h2 class="font-titulo text-3xl font-bold leading-[1.3] text-white sm:text-[40px]">¿Cómo hacemos?</h2>
            <p class="mt-2.5 font-titulo text-2xl font-bold leading-[1.3] text-white sm:text-[30px]">Nos enfocamos en resultados</p>
            <span class="sitio-divisor mt-[25px] bg-sitio-gris" aria-hidden="true"></span>

            <div class="mx-auto mt-[75px] grid max-w-[1100px] gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($diferenciales as $diferencial)
                    <article class="sitio-tarjeta bg-white">
                        <img
                            src="{{ asset($diferencial['imagen']) }}"
                            alt=""
                            width="225"
                            height="225"
                            loading="lazy"
                            decoding="async"
                            class="box-content h-auto w-16 p-2.5"
                        >
                        <h3 class="sitio-tarjeta-titulo">{{ $diferencial['titulo'] }}</h3>
                        <p class="sitio-tarjeta-texto">{{ $diferencial['descripcion'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== BANNER (fondo fijo) ====================
         En el original es un fondo con `background-attachment: fixed` y un
         ícono de reproducir que no lleva a ningún lado; acá el ícono queda
         como adorno, sin enlace. --}}
    <section class="sitio-parallax flex items-center justify-center py-[180px]" aria-label="{{ $empresa['nombre'] }} en el punto de venta">
        <img
            src="{{ asset('img/publico/sitio/banner-material-pop.jpg') }}"
            alt=""
            width="1920"
            height="795"
            loading="lazy"
            decoding="async"
            aria-hidden="true"
            class="sitio-parallax-imagen"
        >
        <span class="relative py-[47px] text-sitio-play" aria-hidden="true">
            <x-publico.icono nombre="play" class="h-[72px] w-[72px]" />
        </span>
    </section>

    {{-- ==================== FRANJA DEL LOGO ==================== --}}
    <section class="bg-black px-4 py-[55px]" aria-hidden="true">
        <img
            src="{{ asset('img/publico/sitio/logo-franja.webp') }}"
            alt=""
            width="800"
            height="195"
            loading="lazy"
            decoding="async"
            class="mx-auto h-auto w-1/2 max-w-[272px]"
        >
    </section>

    {{-- ==================== ¿PARA QUIÉN ES? ==================== --}}
    <section class="bg-white px-4 pb-[85px] pt-[52px] text-center text-black">
        <div class="mx-auto max-w-sitio">
            <h2 class="font-titulo text-[35px] font-bold leading-[1.3] sm:text-[40px]">¿Para quién es?</h2>
            <span class="sitio-divisor mt-10 bg-black" aria-hidden="true"></span>
            <p class="mt-4 font-titulo text-[22px] leading-[1.3] sm:text-[27px]">Trabajamos con:</p>

            <div class="mx-auto mt-[45px] grid max-w-[1080px] gap-10 md:grid-cols-3">
                @foreach ($publicoObjetivo as $publico)
                    <article class="sitio-tarjeta px-[30px] py-5">
                        <img
                            src="{{ asset($publico['imagen']) }}"
                            alt=""
                            width="225"
                            height="225"
                            loading="lazy"
                            decoding="async"
                            class="box-content h-auto w-20 p-2.5"
                        >
                        <h3 class="sitio-tarjeta-titulo">{{ $publico['titulo'] }}</h3>
                        <p class="sitio-tarjeta-texto">{{ $publico['descripcion'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== ALGUNOS PROYECTOS REALIZADOS ==================== --}}
    <section id="proyectos" class="bg-sitio-azul px-4 pb-[50px] pt-[71px] text-center text-white">
        <div class="mx-auto max-w-sitio">
            <h2 class="font-titulo text-[35px] font-bold leading-[1.3] sm:text-[40px]">Algunos Proyectos Realizados</h2>
            <p class="mt-[22px] font-texto text-xl leading-[1.3]">
                Agradecemos la confianza de las marcas y empresas que nos permiten formar parte de sus proyectos y crecimiento.
            </p>
            <span class="sitio-divisor mt-6 bg-sitio-gris" aria-hidden="true"></span>

            <div class="mx-auto mt-[75px] grid max-w-[1100px] gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($vitrina as $trabajo)
                    <article class="sitio-tarjeta bg-white">
                        <img
                            src="{{ asset($trabajo['imagen']) }}"
                            alt="{{ $trabajo['alt'] }}"
                            width="540"
                            height="540"
                            loading="lazy"
                            decoding="async"
                            class="box-content aspect-square h-auto w-full max-w-[180px] p-2.5"
                        >
                        <p class="sitio-tarjeta-texto">{{ $trabajo['descripcion'] }}</p>
                    </article>
                @endforeach
            </div>

            <h2 class="mt-[74px] px-1.5 font-texto text-xl font-medium leading-[1.3] sm:text-[23px]">
                También desarrollamos rotulado vehicular, pasacalles y soluciones visuales para campañas y negocios.
            </h2>

            <div class="mt-2 px-1.5">
                <a href="{{ route('proyectos') }}" class="sitio-boton-galeria">
                    <span class="text-2xl sm:text-[29px]">Ver más proyectos</span>
                    <span class="text-[17px] font-normal">en nuestra galería</span>
                </a>
            </div>
        </div>
    </section>

    {{-- ==================== UN PROVEEDOR QUE RESPONDE ==================== --}}
    <section class="bg-white px-4 pb-[86px] pt-10 text-center font-texto text-black sm:pt-[75px]">
        <div class="mx-auto max-w-[854px]">
            <img
                src="{{ asset('img/publico/sitio/proveedor-acuerdo.webp') }}"
                alt="Apretón de manos que cierra un acuerdo"
                width="626"
                height="196"
                loading="lazy"
                decoding="async"
                class="mx-auto mt-2.5 h-auto w-full max-w-[626px]"
            >
            <img
                src="{{ asset('img/publico/sitio/logo.webp') }}"
                alt=""
                width="800"
                height="195"
                loading="lazy"
                decoding="async"
                class="mx-auto mt-2.5 h-auto w-[160px]"
            >
            <h2 class="mt-3.5 font-texto text-[35px] font-bold leading-[1.3] sm:text-[40px]">Un proveedor que responde</h2>
            <p class="mx-auto mt-[18px] max-w-[800px] text-lg leading-[1.3] sm:text-[19px]">
                En <strong>{{ $empresa['nombre'] }}</strong> entendemos que cada campaña es importante. Por eso
                trabajamos con <strong>responsabilidad, orden y compromiso</strong>. No somos solo proveedores,
                somos aliados en la ejecución de tus campañas.
            </p>
        </div>
    </section>

    <x-publico.cta-contacto />
</x-publico.layout>
