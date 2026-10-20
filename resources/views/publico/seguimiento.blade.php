{{--
    Seguimiento de pedido: formulario de consulta.

    Blade sin JavaScript, como la portada: es un formulario de dos campos y
    funciona en cualquier teléfono. Se indexa (es la puerta que un cliente
    busca como "seguimiento pedido XtraPubli"); lo que no se indexa es el
    pedido en sí, `/seguimiento/{numero}`.

    Debajo del formulario se explican las etapas: es lo primero que alguien
    quiere saber antes de consultar ("¿qué significa que esté en acabado?") y
    es el contenido que le da sentido a la página para un buscador.
--}}
@php
    $empresa = config('sitio.empresa');
    $numero = old('numero', $numeroSugerido);
    $errorBusqueda = $errors->first('busqueda');
    $errorNumero = $errors->first('numero');
    $errorDigitos = $errors->first('digitos');
@endphp

<x-publico.layout
    titulo="Seguimiento de pedido"
    descripcion="Consulta en qué etapa está tu pedido de {{ $empresa['nombre'] }}: diseño, elaboración, acabado, control de calidad o entrega. Solo necesitas tu número de pedido."
>
    {{-- ==================== ENCABEZADO ==================== --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-marca-azul via-marca-azul-oscuro to-marca-azul-profundo px-4 pb-32 pt-12 text-white sm:pt-16">
        <div class="trama-puntos absolute inset-0" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto max-w-3xl text-center">
            <nav class="mb-4 text-xs text-blue-100/80" aria-label="Migas de pan">
                <a href="{{ route('inicio') }}" class="hover:text-white hover:underline">Inicio</a>
                <span class="mx-1.5 text-white/40">/</span>
                <span class="font-medium text-white">Seguimiento de pedido</span>
            </nav>

            <h1 class="mb-4 text-balance text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                ¿En qué va tu pedido?
            </h1>
            <p class="mx-auto max-w-xl text-pretty text-sm leading-relaxed text-blue-50 sm:text-base">
                Escribe tu número de pedido y los últimos 4 dígitos del teléfono o NIT que nos
                diste. Te mostramos la etapa en la que está, cada trabajo y sus fechas.
            </p>
        </div>
    </section>

    {{-- ==================== FORMULARIO ==================== --}}
    <section class="bg-slate-50 px-4 pb-14">
        <div class="relative z-10 mx-auto -mt-20 max-w-2xl">
            <form
                method="POST"
                action="{{ route('seguimiento.buscar') }}"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl shadow-marca-azul-profundo/10 sm:p-8"
                novalidate
            >
                @csrf

                @if (session('estado'))
                    <p class="mb-5 flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600" role="status">
                        <x-publico.icono nombre="check" class="h-4 w-4 shrink-0 text-marca-verde" />
                        {{ session('estado') }}
                    </p>
                @endif

                @if ($errorBusqueda)
                    <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        <x-publico.icono nombre="alerta" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                        <div>
                            <p class="font-semibold">{{ $errorBusqueda }}</p>
                            <p class="mt-0.5 text-red-700">
                                Los dígitos son los del teléfono o NIT con que te registró tu asesor.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="grid gap-x-4 gap-y-5 sm:grid-cols-[minmax(0,1fr)_10.5rem]">
                    <div>
                        <label for="numero" class="mb-1.5 block text-sm font-semibold text-slate-800">
                            Número de pedido
                        </label>
                        <input
                            id="numero"
                            name="numero"
                            type="text"
                            value="{{ $numero }}"
                            placeholder="PED-20261020-AB12C"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            maxlength="24"
                            required
                            @if (! $numero) autofocus @endif
                            aria-describedby="ayuda-numero{{ $errorNumero ? ' error-numero' : '' }}"
                            @if ($errorNumero) aria-invalid="true" @endif
                            @class([
                                'block w-full rounded-xl border bg-white px-4 py-3 font-mono text-base uppercase tracking-wide text-slate-900 placeholder:normal-case placeholder:tracking-normal placeholder:text-slate-400 focus:outline-none focus:ring-4',
                                'border-slate-300 focus:border-marca-azul focus:ring-marca-azul/15' => ! $errorNumero,
                                'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errorNumero,
                            ])
                        >
                        @if ($errorNumero)
                            <p id="error-numero" class="mt-1.5 text-xs font-medium text-red-700">{{ $errorNumero }}</p>
                        @endif
                        <p id="ayuda-numero" class="mt-1.5 text-xs text-slate-500">
                            Está en tu orden de trabajo y en los mensajes de tu asesor.
                        </p>
                    </div>

                    <div>
                        <label for="digitos" class="mb-1.5 block text-sm font-semibold text-slate-800">
                            Últimos 4 dígitos
                        </label>
                        <input
                            id="digitos"
                            name="digitos"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{4}"
                            maxlength="4"
                            placeholder="0000"
                            autocomplete="off"
                            required
                            @if ($numero) autofocus @endif
                            aria-describedby="ayuda-digitos{{ $errorDigitos ? ' error-digitos' : '' }}"
                            @if ($errorDigitos) aria-invalid="true" @endif
                            @class([
                                'block w-full rounded-xl border bg-white px-4 py-3 text-center font-mono text-base tracking-[0.5em] text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-4',
                                'border-slate-300 focus:border-marca-azul focus:ring-marca-azul/15' => ! $errorDigitos,
                                'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errorDigitos,
                            ])
                        >
                        @if ($errorDigitos)
                            <p id="error-digitos" class="mt-1.5 text-xs font-medium text-red-700">{{ $errorDigitos }}</p>
                        @endif
                        <p id="ayuda-digitos" class="mt-1.5 text-xs text-slate-500">
                            De tu teléfono o NIT.
                        </p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-marca-azul px-6 py-3.5 text-sm font-bold text-white shadow-md shadow-marca-azul/25 transition-colors hover:bg-marca-azul-oscuro active:bg-marca-azul-profundo"
                >
                    <x-publico.icono nombre="buscar" class="h-5 w-5" />
                    Ver mi pedido
                </button>

                <p class="mt-4 flex items-start justify-center gap-2 text-center text-xs leading-relaxed text-slate-500">
                    <x-publico.icono nombre="candado" class="mt-px h-4 w-4 shrink-0 text-slate-400" />
                    Pedimos los 4 dígitos para que solo tú puedas ver el detalle y los pagos de tu pedido.
                </p>
            </form>
        </div>

        {{-- ==================== ETAPAS ==================== --}}
        <div class="mx-auto mt-16 max-w-contenido">
            <div class="mx-auto mb-10 max-w-2xl text-center">
                <h2 class="mb-3 text-balance text-2xl font-extrabold tracking-tight text-slate-800">
                    Así avanza tu pedido
                </h2>
                <p class="text-pretty text-sm leading-relaxed text-slate-500">
                    Cada trabajo pasa por estas etapas en nuestro taller. Si tu pedido tiene
                    varios, se muestra en la etapa del que va más atrás: así sabes qué falta
                    para que llegue completo.
                </p>
            </div>

            <ol class="grid gap-0 lg:grid-cols-6 lg:gap-4">
                @foreach ($pasos as $paso)
                    <li class="relative flex gap-4 pb-8 last:pb-0 lg:flex-col lg:gap-3 lg:pb-0">
                        @unless ($loop->last)
                            <span
                                class="absolute bottom-0 left-5 top-11 w-px bg-slate-300 lg:bottom-auto lg:left-14 lg:right-0 lg:top-5 lg:h-px lg:w-auto"
                                aria-hidden="true"
                            ></span>
                        @endunless

                        <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-marca-azul/20 bg-white text-marca-azul shadow-sm">
                            <x-publico.icono :nombre="$paso['icono']" class="h-5 w-5" />
                        </span>

                        <span class="pt-1.5 lg:pt-0">
                            <span class="block text-sm font-bold text-slate-800">{{ $paso['titulo'] }}</span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $paso['texto'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            <p class="mt-12 text-center text-sm text-slate-500">
                ¿No encuentras tu número de pedido?
                <x-publico.enlace-whatsapp
                    mensaje="Hola {{ $empresa['nombre'] }}, quiero saber el número de mi pedido."
                    class="font-semibold text-marca-azul underline-offset-4 hover:underline"
                >Escríbenos por WhatsApp</x-publico.enlace-whatsapp>
                y te lo pasamos.
            </p>
        </div>
    </section>
</x-publico.layout>
