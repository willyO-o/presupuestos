{{--
    Cotizador en línea.

    Blade como el resto del sitio público: tiene que indexarse y no puede
    cargar el bundle del panel. La interactividad (agregar líneas, estimar en
    vivo) la pone `resources/js/publico.js`, unos KB sin framework.

    Funciona sin JavaScript: el formulario postea a `cotizador.store`, el
    servidor calcula y responde con la estimación guardada. Lo único que se
    pierde sin JS es agregar más de un trabajo y ver el precio antes de
    enviar — por eso las medidas se muestran siempre y el JS las oculta donde
    el producto no las necesita, y no al revés.
--}}
@php
    $empresa = config('sitio.empresa');
    $hayCatalogo = $productos->isNotEmpty();
@endphp

<x-publico.layout
    titulo="Cotizador en línea de exhibidores, letreros y material POP"
    descripcion="Calcula en línea el precio aproximado de tu exhibidor, banner, letrero o rotulado. Recibes un código de cotización válido por {{ $vigenciaDias }} días para continuar con un asesor."
    :datos="$datosEstructurados"
    :con-js="$hayCatalogo"
>
    {{-- ==================== ENCABEZADO ==================== --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-marca-azul via-marca-azul-oscuro to-marca-azul-profundo px-4 py-12 text-white sm:py-16">
        <div class="trama-puntos absolute inset-0" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto max-w-3xl text-center">
            <nav class="mb-4 text-xs text-blue-100/80" aria-label="Migas de pan">
                <a href="{{ route('inicio') }}" class="hover:text-white hover:underline">Inicio</a>
                <span class="mx-1.5 text-white/40">/</span>
                <span class="font-medium text-white">Cotizador</span>
            </nav>

            <span class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider backdrop-blur-sm">
                <x-publico.icono nombre="calculadora" class="h-4 w-4" />
                Precio referencial al instante
            </span>

            <h1 class="mb-4 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                Cotiza tu proyecto en línea
            </h1>
            <p class="mx-auto max-w-xl text-sm leading-relaxed text-blue-50 sm:text-base">
                Arma tu lista, mira el precio aproximado al momento y llévate un código
                de cotización para continuar con un asesor. Sin registrarte.
            </p>
        </div>
    </section>

    {{-- ==================== CÓMO FUNCIONA ==================== --}}
    <section class="border-b border-slate-100 bg-white px-4 py-8">
        <ol class="mx-auto grid max-w-contenido gap-4 sm:grid-cols-3">
            @foreach ([
                ['n' => '1', 'titulo' => 'Elige qué necesitas', 'texto' => 'Selecciona el trabajo, pon las medidas en metros y la cantidad.'],
                ['n' => '2', 'titulo' => 'Mira el estimado', 'texto' => 'Calculamos con los precios de material vigentes y el IVA ya incluido.'],
                ['n' => '3', 'titulo' => 'Recibe tu código', 'texto' => "Guárdalo: vale {$vigenciaDias} días y con él un asesor retoma tu cotización."],
            ] as $paso)
                <li class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca-azul text-sm font-bold text-white">
                        {{ $paso['n'] }}
                    </span>
                    <span>
                        <span class="block text-sm font-bold text-slate-800">{{ $paso['titulo'] }}</span>
                        <span class="block text-xs leading-relaxed text-slate-500">{{ $paso['texto'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ==================== FORMULARIO ==================== --}}
    <section class="bg-slate-50 px-4 py-10 sm:py-14">
        <div class="mx-auto max-w-contenido">
            @if (! $hayCatalogo)
                {{-- Catálogo vacío: ningún producto marcado para la web (o
                     ninguno con receta cargada). Se dice, no se muestra un
                     formulario que no puede funcionar. --}}
                <div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <x-publico.icono nombre="alerta" class="mx-auto mb-3 h-10 w-10 text-marca-ambar" />
                    <h2 class="mb-2 text-lg font-bold text-slate-800">El cotizador está en mantenimiento</h2>
                    <p class="mb-6 text-sm leading-relaxed text-slate-500">
                        Estamos actualizando nuestra lista de precios. Escríbenos y te pasamos
                        una cotización a la medida el mismo día.
                    </p>
                    <x-publico.enlace-whatsapp
                        mensaje="Hola XtraPubli, quiero cotizar un proyecto."
                        class="inline-flex items-center gap-2 rounded-xl bg-marca-whatsapp px-6 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-whatsapp-oscuro"
                    >
                        <x-publico.icono nombre="whatsapp" class="h-5 w-5" />
                        Cotizar por WhatsApp
                    </x-publico.enlace-whatsapp>
                </div>
            @else
                @if (session('error'))
                    <div class="mx-auto mb-6 max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mx-auto mb-6 max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        <p class="mb-1 font-bold">Revisa estos datos:</p>
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('cotizador.store') }}"
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
                    data-cotizador
                    data-url-calcular="{{ route('cotizador.calcular') }}"
                    data-max-lineas="{{ $limites['lineas'] }}"
                >
                    @csrf

                    <div class="space-y-6">
                        {{-- ---------- Detalle ---------- --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 class="mb-1 text-lg font-bold text-slate-800">¿Qué necesitas?</h2>
                            <p class="mb-5 text-xs text-slate-500">
                                Las medidas van en <strong>metros</strong> (por ejemplo 2,50 × 1,20).
                                Puedes agregar hasta {{ $limites['lineas'] }} trabajos.
                            </p>

                            <div data-lineas class="space-y-4">
                                @include('publico.partials.cotizador-linea', ['indice' => 0, 'agrupados' => $agrupados, 'limites' => $limites])
                            </div>

                            <button
                                type="button"
                                data-agregar-linea
                                hidden
                                class="mt-4 inline-flex items-center gap-2 rounded-lg border border-dashed border-marca-azul px-4 py-2.5 text-sm font-semibold text-marca-azul transition-colors hover:bg-marca-azul/5"
                            >
                                <x-publico.icono nombre="mas" class="h-4 w-4" />
                                Agregar otro trabajo
                            </button>

                            <noscript>
                                <p class="mt-4 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                                    Tu navegador tiene JavaScript desactivado: puedes cotizar un trabajo por
                                    envío y verás el precio en la página siguiente.
                                </p>
                            </noscript>
                        </div>

                        {{-- ---------- Contacto ---------- --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 class="mb-1 text-lg font-bold text-slate-800">¿A quién le respondemos?</h2>
                            <p class="mb-5 text-xs text-slate-500">
                                Con estos datos generamos tu código y un asesor puede retomar la cotización.
                            </p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Nombre <span class="text-red-500">*</span></span>
                                    <input
                                        type="text" name="nombre" required maxlength="120"
                                        value="{{ old('nombre') }}" autocomplete="name"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
                                    >
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Empresa <span class="font-normal text-slate-400">(opcional)</span></span>
                                    <input
                                        type="text" name="empresa" maxlength="150"
                                        value="{{ old('empresa') }}" autocomplete="organization"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
                                    >
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Teléfono o WhatsApp <span class="text-red-500">*</span></span>
                                    <input
                                        type="tel" name="telefono" required maxlength="30"
                                        value="{{ old('telefono') }}" autocomplete="tel" inputmode="tel"
                                        placeholder="7XX XX XXX"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
                                    >
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Correo <span class="font-normal text-slate-400">(opcional)</span></span>
                                    <input
                                        type="email" name="email" maxlength="150"
                                        value="{{ old('email') }}" autocomplete="email"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
                                    >
                                </label>

                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">
                                        ¿Algo que debamos saber? <span class="font-normal text-slate-400">(opcional)</span>
                                    </span>
                                    <textarea
                                        name="mensaje" rows="3" maxlength="1000"
                                        placeholder="Plazo, ciudad de entrega, si necesitas instalación..."
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
                                    >{{ old('mensaje') }}</textarea>
                                </label>
                            </div>

                            {{-- Honeypot: invisible para una persona, irresistible para un
                                 bot que rellena todo lo que encuentra. `tabindex=-1` y
                                 `autocomplete=off` para que ni el teclado ni el navegador
                                 se lo ofrezcan a nadie por accidente. --}}
                            <div class="sr-only" aria-hidden="true">
                                <label>
                                    No completar este campo
                                    <input type="text" name="sitio_web" value="" tabindex="-1" autocomplete="off">
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- Panel de resultado ---------- --}}
                    <aside class="lg:sticky lg:top-24">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
                                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Tu estimado</h2>
                            </div>

                            <div class="px-5 py-5">
                                {{-- Estado inicial y sin JavaScript: no se inventa un
                                     número, se explica qué va a pasar. --}}
                                <div data-estimado-vacio class="text-center">
                                    <x-publico.icono nombre="calculadora" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                                    <p class="text-xs leading-relaxed text-slate-500">
                                        Completa el trabajo y las medidas para ver aquí el precio aproximado.
                                    </p>
                                </div>

                                <div data-estimado-error hidden class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700" role="alert"></div>

                                <div data-estimado hidden>
                                    <ul data-estimado-lineas class="mb-4 space-y-2 border-b border-slate-100 pb-4 text-xs text-slate-600"></ul>

                                    <dl class="space-y-1.5 text-xs text-slate-600">
                                        <div class="flex justify-between">
                                            <dt>Subtotal</dt>
                                            <dd data-estimado-subtotal class="font-medium text-slate-800">—</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>IVA ({{ (int) (config('margen.impuestos.iva') * 100) }}%)</dt>
                                            <dd data-estimado-iva class="font-medium text-slate-800">—</dd>
                                        </div>
                                    </dl>

                                    <div class="mt-4 rounded-xl bg-marca-azul/5 p-4 text-center">
                                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-marca-azul">
                                            Rango aproximado
                                        </span>
                                        <span data-estimado-rango class="block text-lg font-extrabold leading-tight text-marca-azul-oscuro sm:text-xl">—</span>
                                        <span class="mt-1 block text-[11px] text-slate-500">IVA incluido · Bolivianos</span>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-marca-ambar px-4 py-3 text-sm font-bold text-marca-oscuro shadow-md transition-all hover:brightness-95 active:scale-95"
                                >
                                    <x-publico.icono nombre="documento" class="h-5 w-5" />
                                    Obtener mi código
                                </button>

                                <p class="mt-3 text-center text-[11px] leading-relaxed text-slate-400">
                                    Sin compromiso. Tu código vale {{ $vigenciaDias }} días.
                                </p>
                            </div>
                        </div>
                    </aside>
                </form>
            @endif
        </div>
    </section>

    {{-- ==================== LETRA CHICA ==================== --}}
    <section class="bg-white px-4 py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">
            <h2 class="mb-4 text-xl font-extrabold tracking-tight text-slate-800 sm:text-2xl">
                Qué significa "aproximado"
            </h2>
            <p class="mb-5 text-sm leading-relaxed text-slate-600">
                El cotizador calcula con los precios de material vigentes y el margen estándar de
                {{ $empresa['nombre'] }}, y por eso muestra un <strong>rango de ±{{ (int) round($holgura * 100) }}%</strong>
                en lugar de un número exacto. Un presupuesto formal ajusta ese número cuando ya
                conocemos el detalle real del trabajo.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <x-publico.icono nombre="check" class="h-4 w-4 text-marca-verde" />
                        Sí está incluido
                    </h3>
                    <ul class="space-y-1 text-xs leading-relaxed text-slate-500">
                        <li>Materiales según la ficha técnica de cada trabajo.</li>
                        <li>Fabricación en nuestro taller.</li>
                        <li>IVA de ley ({{ (int) (config('margen.impuestos.iva') * 100) }}%).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <x-publico.icono nombre="alerta" class="h-4 w-4 text-marca-ambar" />
                        Se cotiza aparte
                    </h3>
                    <ul class="space-y-1 text-xs leading-relaxed text-slate-500">
                        <li>Instalación y montaje en punto de venta.</li>
                        <li>Transporte fuera de {{ $empresa['ciudad'] }} y {{ $empresa['departamento'] }}.</li>
                        <li>Diseño gráfico, acabados especiales y estructuras a medida.</li>
                    </ul>
                </div>
            </div>

            <p class="mt-5 text-xs leading-relaxed text-slate-500">
                El precio del material cambia, así que cada estimación vale
                <strong>{{ $vigenciaDias }} días</strong> desde que la generas. Pasado ese plazo el
                código sigue sirviendo para que un asesor recupere tu pedido, pero el monto se
                vuelve a calcular.
            </p>
        </div>
    </section>

    <x-publico.cta-contacto />

    {{-- Metadatos del catálogo para el JS: va como JSON inerte (no se ejecuta)
         en vez de inyectarse dentro de un <script> con variables sueltas. --}}
    @if ($hayCatalogo)
        <script type="application/json" data-cotizador-productos>
            @json($productos->mapWithKeys(fn ($producto): array => [$producto->id => [
                'nombre' => $producto->nombre,
                'requiere_medidas' => $producto->requiere_medidas === 'SI',
            ]]))
        </script>

        {{-- Plantilla de una línea vacía que clona el JS al "Agregar otro
             trabajo". Dentro de <template> el navegador no la renderiza ni
             manda sus campos en el POST. --}}
        <template data-plantilla-linea>
            @include('publico.partials.cotizador-linea', ['indice' => '__INDICE__', 'agrupados' => $agrupados, 'limites' => $limites])
        </template>
    @endif
</x-publico.layout>
