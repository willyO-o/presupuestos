{{--
    Seguimiento de pedido: el pedido de un cliente ya verificado.

    Dos columnas en escritorio: el detalle a la izquierda y la línea de tiempo
    fija a la derecha, que es la respuesta a "¿en qué va?". En el HTML la
    línea de tiempo va PRIMERO: en el teléfono queda arriba del detalle y un
    lector de pantalla la lee antes; en escritorio la grilla la manda a la
    derecha.

    `indexable = false`: lleva el nombre del cliente, montos y pagos. Además
    va bloqueada en robots.txt y se sirve con `Cache-Control: no-store`.
    Los datos los arma `SeguimientoPublicoService::resumen()`; acá no se
    consulta nada.
--}}
@php
    $empresa = config('sitio.empresa');
    $s = $seguimiento;
    $flujo = array_slice($s['pasos'], 1);
    $indiceEtapa = collect($flujo)->search(fn ($paso) => $paso['clave'] === $s['etapa']['clave']);
    $completados = collect($s['pasos'])->where('estado', 'completo')->count();
    $dias = $s['fechas']['dias_restantes'];
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
    $mensajeWhatsapp = "Hola {$empresa['nombre']}, consulto por mi pedido {$s['numero']}.";

    $estadoPago = [
        'PAGADO' => ['texto' => 'Pagado', 'clase' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'PARCIAL' => ['texto' => 'Pago parcial', 'clase' => 'bg-amber-50 text-amber-800 ring-amber-600/25'],
        'PENDIENTE' => ['texto' => 'Sin pagos', 'clase' => 'bg-slate-100 text-slate-600 ring-slate-500/20'],
    ][$s['cobranza']['estado']];

    $porcentajePagado = $s['cobranza']['total'] > 0
        ? min(100, round($s['cobranza']['pagado'] / $s['cobranza']['total'] * 100))
        : 0;
@endphp

<x-publico.layout
    titulo="Pedido {{ $s['numero'] }}"
    descripcion="Estado de un pedido de {{ $empresa['nombre'] }}."
    :indexable="false"
>
    {{-- ==================== ENCABEZADO ==================== --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-marca-azul via-marca-azul-oscuro to-marca-azul-profundo px-4 py-8 text-white sm:py-10">
        <div class="trama-puntos absolute inset-0" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto max-w-contenido">
            <nav class="mb-4 text-xs text-blue-100/80" aria-label="Migas de pan">
                <a href="{{ route('inicio') }}" class="hover:text-white hover:underline">Inicio</a>
                <span class="mx-1.5 text-white/40">/</span>
                <a href="{{ route('seguimiento') }}" class="hover:text-white hover:underline">Seguimiento de pedido</a>
                <span class="mx-1.5 text-white/40">/</span>
                <span class="font-medium text-white">{{ $s['numero'] }}</span>
            </nav>

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="break-all font-mono text-2xl font-bold tracking-tight sm:text-3xl">
                        <span class="sr-only">Pedido </span>{{ $s['numero'] }}
                    </h1>
                    <p class="mt-1.5 text-sm text-blue-50">
                        <span class="font-semibold text-white">{{ $s['cliente'] }}</span>
                        <span class="mx-1 text-white/40" aria-hidden="true">·</span>
                        Pedido del {{ $s['fechas']['pedido']->translatedFormat('j \d\e F \d\e Y') }}
                    </p>
                </div>

                @if ($s['cancelado'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-red-700">
                        <x-publico.icono nombre="cancelado" class="h-4 w-4" />
                        Cancelado
                    </span>
                @elseif ($s['entregado'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-marca-verde px-3 py-1.5 text-xs font-bold text-white">
                        <x-publico.icono nombre="check" class="h-4 w-4" />
                        Entregado
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold ring-1 ring-inset ring-white/25">
                        <span class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-marca-celeste opacity-75 motion-reduce:hidden"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-marca-celeste"></span>
                        </span>
                        En curso
                    </span>
                @endif
            </div>
        </div>
    </section>

    <section class="bg-slate-50 px-4 py-8 sm:py-10">
        <div class="mx-auto grid max-w-contenido gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">

            {{-- ==================== LÍNEA DE TIEMPO (sidebar) ==================== --}}
            <aside
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1"
                aria-labelledby="titulo-seguimiento"
            >
                <div class="mb-6 flex items-baseline justify-between gap-3">
                    <h2 id="titulo-seguimiento" class="text-base font-bold text-slate-800">Seguimiento</h2>
                    <p class="text-xs tabular-nums text-slate-500">
                        {{ $completados }} de {{ count($s['pasos']) }} pasos
                    </p>
                </div>

                <ol>
                    @foreach ($s['pasos'] as $paso)
                        @php
                            $siguiente = $s['pasos'][$loop->index + 1] ?? null;
                            $tramoRecorrido = $siguiente && $siguiente['estado'] !== 'pendiente';
                            $etiqueta = [
                                'completo' => 'Completado',
                                'actual' => 'En curso',
                                'detenido' => 'Detenido',
                                'pendiente' => 'Pendiente',
                            ][$paso['estado']];
                        @endphp

                        <li
                            class="relative flex gap-4 pb-6 last:pb-0"
                            @if ($paso['estado'] === 'actual') aria-current="step" @endif
                        >
                            @if ($siguiente)
                                <span
                                    @class([
                                        'absolute bottom-0 left-[1.125rem] top-10 w-0.5 rounded-full',
                                        'bg-marca-azul' => $tramoRecorrido && $siguiente['estado'] !== 'detenido',
                                        'bg-red-300' => $siguiente['estado'] === 'detenido',
                                        'bg-slate-200' => ! $tramoRecorrido,
                                    ])
                                    aria-hidden="true"
                                ></span>
                            @endif

                            <span
                                @class([
                                    'relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                    'bg-marca-azul text-white' => $paso['estado'] === 'completo',
                                    'seguimiento-pulso bg-white text-marca-azul ring-2 ring-marca-azul' => $paso['estado'] === 'actual',
                                    'bg-red-50 text-red-600 ring-2 ring-red-500' => $paso['estado'] === 'detenido',
                                    'bg-white text-slate-400 ring-1 ring-slate-200' => $paso['estado'] === 'pendiente',
                                ])
                                aria-hidden="true"
                            >
                                <x-publico.icono
                                    :nombre="match ($paso['estado']) { 'completo' => 'check', 'detenido' => 'cancelado', default => $paso['icono'] }"
                                    class="h-5 w-5"
                                />
                            </span>

                            <div class="min-w-0 flex-1 pt-0.5">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <h3 @class([
                                        'text-sm font-bold',
                                        'text-slate-800' => $paso['estado'] !== 'pendiente',
                                        'text-slate-500' => $paso['estado'] === 'pendiente',
                                    ])>{{ $paso['titulo'] }}</h3>

                                    @if ($paso['estado'] === 'actual')
                                        <span class="rounded-full bg-marca-azul/10 px-2 py-0.5 text-[11px] font-bold text-marca-azul">En curso</span>
                                    @elseif ($paso['estado'] === 'detenido')
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-700">Detenido</span>
                                    @else
                                        <span class="sr-only">— {{ $etiqueta }}</span>
                                    @endif
                                </div>

                                @if ($paso['estado'] === 'completo' && $paso['fecha'])
                                    <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                                        <time datetime="{{ $paso['fecha']->toDateString() }}">{{ $paso['fecha']->translatedFormat('j M Y') }}</time>
                                    </p>
                                @endif

                                @if (in_array($paso['estado'], ['actual', 'detenido'], true))
                                    <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ $paso['texto'] }}</p>

                                    @if ($paso['fecha'])
                                        <p class="mt-1 text-xs tabular-nums text-slate-500">
                                            Desde el <time datetime="{{ $paso['fecha']->toDateString() }}">{{ $paso['fecha']->translatedFormat('j \d\e F') }}</time>
                                        </p>
                                    @endif

                                    @if ($paso['avance'])
                                        <p class="mt-2 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600">
                                            <strong class="tabular-nums text-slate-800">{{ $paso['avance']['listos'] }} de {{ $paso['avance']['total'] }}</strong>
                                            trabajos ya pasaron esta etapa.
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <form method="POST" action="{{ route('seguimiento.cerrar') }}" class="mt-6 border-t border-slate-100 pt-4">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 underline-offset-4 hover:text-marca-azul hover:underline">
                        <x-publico.icono nombre="candado" class="h-4 w-4" />
                        Cerrar consulta en este dispositivo
                    </button>
                </form>
            </aside>

            {{-- ==================== DETALLE ==================== --}}
            <div class="min-w-0 space-y-6 lg:col-start-1 lg:row-start-1">

                {{-- ---------- Estado actual ---------- --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    @if ($s['cancelado'])
                        <div class="flex gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                                <x-publico.icono nombre="cancelado" class="h-6 w-6" />
                            </span>
                            <div>
                                <h2 class="text-xl font-extrabold tracking-tight text-slate-800 sm:text-2xl">Este pedido fue cancelado</h2>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                    Se detuvo en la etapa de <strong>{{ mb_strtolower($s['etapa']['titulo']) }}</strong>.
                                    Si no esperabas este cambio, escríbenos y lo revisamos contigo.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex gap-4">
                            <span @class([
                                'flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl',
                                'bg-emerald-50 text-marca-verde' => $s['entregado'],
                                'bg-marca-azul/10 text-marca-azul' => ! $s['entregado'],
                            ])>
                                <x-publico.icono :nombre="$s['etapa']['icono']" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0">
                                @if ($s['entregado'])
                                    <h2 class="text-xl font-extrabold tracking-tight text-slate-800 sm:text-2xl">Tu pedido fue entregado</h2>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                        Gracias por confiar en {{ $empresa['nombre'] }}. Abajo están las notas de entrega y el estado de los pagos.
                                    </p>
                                @else
                                    <p class="text-sm text-slate-500">Tu pedido está en</p>
                                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">{{ $s['etapa']['titulo'] }}</h2>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $s['etapa']['texto'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Barra de etapas: la misma información que la línea de
                         tiempo, en una sola mirada. Para el lector de
                         pantalla basta la frase de abajo. --}}
                    <div class="mt-6">
                        <div class="flex gap-1.5" aria-hidden="true">
                            @foreach ($flujo as $paso)
                                <span @class([
                                    'h-2 flex-1 rounded-full',
                                    'bg-marca-azul' => $paso['estado'] === 'completo',
                                    'seguimiento-barra-actual' => $paso['estado'] === 'actual',
                                    'bg-red-400' => $paso['estado'] === 'detenido',
                                    'bg-slate-200' => $paso['estado'] === 'pendiente',
                                ])></span>
                            @endforeach
                        </div>
                        <div class="mt-2 hidden justify-between text-[11px] font-medium text-slate-500 sm:flex" aria-hidden="true">
                            @foreach ($flujo as $paso)
                                <span @class(['flex-1 text-center first:text-left last:text-right', 'font-bold text-slate-800' => $loop->index === $indiceEtapa])>{{ $paso['titulo'] }}</span>
                            @endforeach
                        </div>
                        <p class="sr-only">Etapa {{ $indiceEtapa + 1 }} de {{ count($flujo) }}: {{ $s['etapa']['titulo'] }}.</p>
                    </div>

                    <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-slate-500">Fecha de pedido</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-slate-800">
                                {{ $s['fechas']['pedido']->translatedFormat('j \d\e F \d\e Y') }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-slate-500">Entrega estimada</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-slate-800">
                                {{ $s['fechas']['estimada']?->translatedFormat('j \d\e F \d\e Y') ?? 'Por confirmar' }}
                            </dd>
                            @if ($dias !== null)
                                <dd @class([
                                    'mt-0.5 text-xs',
                                    'text-slate-500' => $dias > 0,
                                    'font-semibold text-marca-azul' => $dias === 0,
                                    'text-amber-700' => $dias < 0,
                                ])>
                                    @if ($dias > 1)
                                        Faltan {{ $dias }} días
                                    @elseif ($dias === 1)
                                        Mañana
                                    @elseif ($dias === 0)
                                        Hoy
                                    @else
                                        La fecha pasó: escríbenos y te confirmamos la nueva.
                                    @endif
                                </dd>
                            @endif
                        </div>

                        <div>
                            <dt class="text-xs text-slate-500">Entregado el</dt>
                            <dd @class([
                                'mt-0.5 font-semibold tabular-nums',
                                'text-slate-800' => $s['fechas']['real'],
                                'text-slate-400' => ! $s['fechas']['real'],
                            ])>
                                {{ $s['fechas']['real']?->translatedFormat('j \d\e F \d\e Y') ?? ($s['cancelado'] ? 'No aplica' : 'Todavía no') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- ---------- Trabajos ---------- --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-baseline justify-between gap-3 px-5 pt-5 sm:px-7 sm:pt-6">
                        <h2 class="text-base font-bold text-slate-800">Trabajos del pedido</h2>
                        <p class="text-xs tabular-nums text-slate-500">
                            {{ count($s['trabajos']) }} {{ count($s['trabajos']) === 1 ? 'trabajo' : 'trabajos' }}
                        </p>
                    </div>

                    @if (count($s['trabajos']) === 0)
                        <p class="px-5 pb-6 pt-3 text-sm text-slate-500 sm:px-7">
                            Este pedido todavía no tiene trabajos cargados.
                        </p>
                    @else
                        <ul class="mt-3 divide-y divide-slate-100">
                            @foreach ($s['trabajos'] as $trabajo)
                                <li class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:px-7">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-800">{{ $trabajo['descripcion'] }}</p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs tabular-nums text-slate-500">
                                            @if ($trabajo['medidas'])
                                                <span class="inline-flex items-center gap-1">
                                                    <x-publico.icono nombre="regla" class="h-3.5 w-3.5 text-slate-400" />
                                                    {{ $trabajo['medidas'] }}
                                                </span>
                                            @endif
                                            <span>Cantidad: {{ $trabajo['cantidad'] }}</span>
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-3 sm:flex-col sm:items-end sm:gap-1.5">
                                        <span @class([
                                            'order-2 text-xs font-semibold sm:order-none',
                                            'text-marca-verde' => $trabajo['indice'] === count($flujo) - 1,
                                            'text-slate-700' => $trabajo['indice'] < count($flujo) - 1,
                                        ])>{{ $trabajo['etapa'] }}</span>
                                        <span class="flex gap-1" aria-hidden="true">
                                            @foreach ($flujo as $paso)
                                                <span @class([
                                                    'h-1.5 w-5 rounded-full',
                                                    'bg-marca-azul' => $loop->index < $trabajo['indice'] || $trabajo['indice'] === count($flujo) - 1,
                                                    'bg-marca-celeste' => $loop->index === $trabajo['indice'] && $trabajo['indice'] < count($flujo) - 1,
                                                    'bg-slate-200' => $loop->index > $trabajo['indice'],
                                                ])></span>
                                            @endforeach
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- ---------- Entregas y pagos ---------- --}}
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="mb-4 flex items-center gap-2 text-base font-bold text-slate-800">
                            <x-publico.icono nombre="camion" class="h-5 w-5 text-slate-400" />
                            Entregas
                        </h2>

                        @forelse ($s['entregas'] as $entrega)
                            <div @class(['flex items-center justify-between gap-3 text-sm', 'mt-3 border-t border-slate-100 pt-3' => ! $loop->first])>
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-xs font-semibold text-slate-800">{{ $entrega['numero'] }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $entrega['trabajos'] }} {{ $entrega['trabajos'] === 1 ? 'trabajo' : 'trabajos' }}
                                    </p>
                                </div>
                                <time datetime="{{ $entrega['fecha']->toDateString() }}" class="shrink-0 text-xs tabular-nums text-slate-600">
                                    {{ $entrega['fecha']->translatedFormat('j M Y') }}
                                </time>
                            </div>
                        @empty
                            <p class="text-sm leading-relaxed text-slate-500">
                                Todavía no hay entregas. Cuando entreguemos, la nota de entrega aparece acá.
                            </p>
                        @endforelse
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="flex items-center gap-2 text-base font-bold text-slate-800">
                                <x-publico.icono nombre="billete" class="h-5 w-5 text-slate-400" />
                                Pagos
                            </h2>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $estadoPago['clase'] }}">
                                {{ $estadoPago['texto'] }}
                            </span>
                        </div>

                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Total del pedido</dt>
                                <dd class="font-medium tabular-nums text-slate-800">{{ $bs($s['cobranza']['total']) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Pagado</dt>
                                <dd class="font-medium tabular-nums text-slate-800">{{ $bs($s['cobranza']['pagado']) }}</dd>
                            </div>
                        </dl>

                        <div class="my-3 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-marca-verde" style="width: {{ $porcentajePagado }}%"></div>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-700">Saldo</span>
                            <span @class([
                                'text-lg font-extrabold tabular-nums',
                                'text-slate-800' => $s['cobranza']['saldo'] > 0,
                                'text-marca-verde' => $s['cobranza']['saldo'] <= 0,
                            ])>{{ $bs($s['cobranza']['saldo']) }}</span>
                        </div>
                    </div>
                </div>

                {{-- ---------- Ayuda ---------- --}}
                <div class="flex flex-col gap-4 rounded-2xl bg-marca-azul-profundo p-5 text-white sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div>
                        <h2 class="text-base font-bold">¿Tienes una duda sobre tu pedido?</h2>
                        <p class="mt-0.5 text-sm text-blue-100">
                            Escríbenos con tu número <span class="font-mono font-semibold text-white">{{ $s['numero'] }}</span> y te respondemos.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                        <x-publico.enlace-whatsapp
                            :mensaje="$mensajeWhatsapp"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-marca-whatsapp px-5 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-whatsapp-oscuro"
                        >
                            <x-publico.icono nombre="whatsapp" class="h-5 w-5" />
                            Escribir por WhatsApp
                        </x-publico.enlace-whatsapp>
                        <a href="{{ route('seguimiento') }}" class="text-center text-xs font-medium text-blue-100 underline-offset-4 hover:text-white hover:underline">
                            Consultar otro pedido
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-publico.layout>
