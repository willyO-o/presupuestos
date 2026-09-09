@php
    $empresa = config('sitio.empresa');
@endphp

<x-publico.layout
    titulo="Exhibidores, material POP e implementación en punto de venta"
    :descripcion="$empresa['descripcion']"
    :datos="$datosEstructurados"
>
    {{-- ==================== HERO ==================== --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-marca-azul via-marca-azul-oscuro to-marca-azul-profundo px-4 py-16 text-white sm:py-20 md:py-28">
        <div class="trama-puntos absolute inset-0" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-around opacity-10" aria-hidden="true">
            <div class="h-72 w-72 rounded-full border-8 border-white/20 blur-sm"></div>
            <div class="h-96 w-96 rounded-full border-4 border-white/10"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-3xl text-center">
            <h1 class="mb-4 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                {{ $empresa['lema'] }}
            </h1>
            <p class="mb-3 text-base font-medium leading-relaxed text-blue-50 sm:text-lg md:text-xl">
                Ejecución real en punto de venta para marcas que buscan resultados.
            </p>
            <p class="mx-auto mb-8 max-w-xl text-sm font-light text-blue-100/90 sm:text-base">
                Elaboramos e implementamos soluciones que aseguran presencia, impacto y
                cumplimiento en cada campaña.
            </p>

            <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                <x-publico.enlace-whatsapp
                    mensaje="Hola XtraPubli, deseo solicitar una cotización."
                    class="inline-flex items-center gap-3 rounded-full bg-marca-whatsapp px-6 py-3 shadow-lg transition-all hover:bg-marca-whatsapp-oscuro hover:shadow-xl active:scale-95"
                >
                    <x-publico.icono nombre="whatsapp" class="h-7 w-7 shrink-0" />
                    <span class="text-left">
                        <span class="block text-sm font-bold leading-tight sm:text-base">Solicitar cotización</span>
                        <span class="block text-[11px] font-light text-white/90 sm:text-xs">o una visita a su oficina</span>
                    </span>
                </x-publico.enlace-whatsapp>

                <a
                    href="{{ route('proyectos') }}"
                    class="inline-flex items-center gap-2 rounded-full border border-white/40 px-6 py-3.5 text-sm font-semibold transition-colors hover:bg-white/10"
                >
                    Ver nuestros proyectos
                    <x-publico.icono nombre="flecha" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </section>

    {{-- ==================== QUÉ HACEMOS ==================== --}}
    <section id="que-hacemos" class="bg-white px-4 py-14 sm:py-20">
        <div class="mx-auto max-w-contenido text-center">
            <h2 class="mb-2 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">
                ¿Qué hacemos?
            </h2>
            <p class="mb-3 text-sm font-semibold text-slate-700 sm:text-base">
                Ejecutamos ideas que generan impacto, no solo producimos
            </p>
            <p class="mx-auto mb-3 max-w-2xl text-sm leading-relaxed text-slate-500">
                En {{ $empresa['nombre'] }} ayudamos a las marcas a destacar en el punto de venta
                mediante soluciones visuales, exhibidores y material POP ejecutado con calidad y
                responsabilidad.
            </p>
            <p class="mb-10 text-sm font-bold italic text-slate-800">
                Nos aseguramos de que funcionen en el punto de venta.
            </p>

            <div class="grid grid-cols-1 gap-5 text-left sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($servicios as $servicio)
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                        <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-marca-azul/10 text-marca-azul">
                            <x-publico.icono :nombre="$servicio['icono']" class="h-7 w-7" />
                        </span>
                        <h3 class="mb-2 text-base font-bold text-slate-800">{{ $servicio['titulo'] }}</h3>
                        <p class="text-xs leading-relaxed text-slate-500">{{ $servicio['descripcion'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== CÓMO LO HACEMOS ==================== --}}
    <section id="como-lo-hacemos" class="bg-gradient-to-b from-marca-azul to-marca-azul-oscuro px-4 py-14 text-white sm:py-20">
        <div class="mx-auto max-w-contenido text-center">
            <h2 class="mb-2 text-2xl font-extrabold tracking-tight sm:text-3xl">¿Cómo lo hacemos?</h2>
            <p class="mb-10 text-sm font-medium text-blue-100 sm:text-base">Nos enfocamos en resultados</p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($diferenciales as $diferencial)
                    <div class="flex min-h-[170px] flex-col items-center justify-center rounded-xl bg-white p-6 text-center text-slate-800 shadow-md">
                        <x-publico.icono :nombre="$diferencial['icono']" class="mb-3 h-8 w-8 text-marca-azul" />
                        <h3 class="mb-1 text-base font-bold text-slate-900">{{ $diferencial['titulo'] }}</h3>
                        <p class="text-xs leading-relaxed text-slate-500">{{ $diferencial['descripcion'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== BANNER DE EJECUCIÓN ==================== --}}
    <section class="relative overflow-hidden bg-marca-oscuro">
        <div class="relative flex min-h-[360px] items-center justify-center sm:min-h-[440px]">
            <img
                src="{{ asset('img/publico/banner-ejecucion.jpg') }}"
                alt="Equipo de {{ $empresa['nombre'] }} instalando material publicitario en punto de venta"
                width="1408"
                height="768"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 h-full w-full object-cover opacity-40 mix-blend-overlay"
            >
            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-black/80" aria-hidden="true"></div>

            <div class="relative z-10 mx-auto flex max-w-4xl flex-col items-center px-4 py-12 text-center">
                <span class="mb-1 text-xs font-bold uppercase tracking-widest text-slate-300 sm:text-sm">
                    Ejecución real
                </span>
                <h2 class="mb-3 text-2xl font-extrabold tracking-tight text-marca-celeste sm:text-4xl">
                    En punto de venta
                </h2>
                <p class="max-w-lg text-sm font-light leading-relaxed text-slate-300">
                    Diseñamos, producimos e implementamos soluciones que aseguran presencia,
                    impacto y cumplimiento en cada campaña.
                </p>

                <div class="mt-6 flex flex-wrap justify-center gap-2 text-[10px] font-semibold uppercase text-white/80 sm:text-xs">
                    @foreach ($servicios as $servicio)
                        <span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 backdrop-blur-sm">
                            {{ $servicio['titulo'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== PARA QUIÉN ES ==================== --}}
    <section class="bg-white px-4 py-14 sm:py-20">
        <div class="mx-auto max-w-4xl text-center">
            <h2 class="mb-2 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">¿Para quién es?</h2>
            <div class="mx-auto mb-3 h-0.5 w-12 bg-slate-300"></div>
            <p class="mb-8 text-xs font-semibold uppercase tracking-wider text-slate-500">Trabajamos con:</p>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                @foreach ($publicoObjetivo as $publico)
                    <div class="rounded-2xl border border-slate-200 p-6 shadow-sm transition-shadow hover:shadow-md">
                        <h3 class="mb-2 text-base font-bold text-slate-800">{{ $publico['titulo'] }}</h3>
                        <p class="text-xs leading-relaxed text-slate-500">{{ $publico['descripcion'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== VITRINA DE PROYECTOS ==================== --}}
    <section id="proyectos" class="bg-marca-azul px-4 py-14 text-white sm:py-20">
        <div class="mx-auto max-w-contenido text-center">
            <h2 class="mb-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Algunos proyectos realizados</h2>
            <p class="mx-auto mb-10 max-w-2xl text-sm font-light leading-relaxed text-blue-100">
                Agradecemos la confianza de las marcas y empresas que nos permiten formar parte de
                sus proyectos y de su crecimiento.
            </p>

            <div class="mb-8 grid grid-cols-2 gap-3 text-left sm:gap-4 lg:grid-cols-4">
                @foreach ($destacados as $proyecto)
                    <x-publico.tarjeta-proyecto :proyecto="$proyecto" />
                @endforeach
            </div>

            <p class="mx-auto mb-8 max-w-xl text-sm text-blue-100">
                También desarrollamos rotulado vehicular, pasacalles y soluciones visuales para
                campañas y negocios.
            </p>

            <a
                href="{{ route('proyectos') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-marca-ambar px-8 py-3.5 text-sm font-bold text-marca-oscuro shadow-md transition-all hover:brightness-95 active:scale-95"
            >
                Ver los {{ $totalProyectos }} proyectos de la galería
                <x-publico.icono nombre="flecha" class="h-4 w-4" />
            </a>
        </div>
    </section>

    {{-- ==================== CONFIANZA ==================== --}}
    <section class="border-b border-slate-100 bg-white px-4 py-14 text-center sm:py-16">
        <div class="mx-auto max-w-2xl">
            <h2 class="mb-3 text-xl font-extrabold text-slate-800 sm:text-2xl">Un proveedor que responde</h2>
            <p class="text-sm leading-relaxed text-slate-600">
                En <strong class="font-bold text-slate-800">{{ $empresa['nombre'] }}</strong> entendemos
                que cada campaña es importante. Por eso trabajamos con
                <strong class="font-bold text-slate-800">responsabilidad, orden y compromiso</strong>.
                No somos solo proveedores: somos aliados en la ejecución de tus campañas.
            </p>
        </div>
    </section>

    <x-publico.cta-contacto />
</x-publico.layout>
