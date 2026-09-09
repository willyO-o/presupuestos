{{--
    Estimación guardada, consultada por su código.

    `indexable = false`: no es contenido público sino el presupuesto de una
    persona concreta, con su nombre y su teléfono. Además va bloqueada en
    robots.txt (`Disallow: /cotizador/`) y el código es aleatorio, para que no
    se pueda enumerar. Las tres cosas juntas, porque cada una tapa un agujero
    distinto.
--}}
@php
    $empresa = config('sitio.empresa');
    $mensajeWhatsapp = "Hola {$empresa['nombre']}, tengo la cotización {$cotizacion->codigo} y quiero continuar.";
@endphp

<x-publico.layout
    titulo="Cotización {{ $cotizacion->codigo }}"
    descripcion="Estimación aproximada generada en el cotizador en línea de {{ $empresa['nombre'] }}."
    :indexable="false"
>
    <section class="bg-slate-50 px-4 py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">

            {{-- ==================== CABECERA DEL DOCUMENTO ==================== --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-marca-azul to-marca-azul-oscuro px-5 py-6 text-white sm:px-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-widest text-blue-100">
                                Estimación aproximada
                            </span>
                            <p class="font-mono text-2xl font-extrabold tracking-tight sm:text-3xl">
                                {{ $cotizacion->codigo }}
                            </p>
                        </div>

                        @if ($vigente)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold backdrop-blur-sm">
                                <x-publico.icono nombre="check" class="h-4 w-4" />
                                Vigente
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-marca-ambar px-3 py-1.5 text-xs font-bold text-marca-oscuro">
                                <x-publico.icono nombre="alerta" class="h-4 w-4" />
                                Vencida
                            </span>
                        @endif
                    </div>
                </div>

                {{-- ==================== AVISO DE VIGENCIA ==================== --}}
                <div @class([
                    'border-b px-5 py-4 text-xs leading-relaxed sm:px-8',
                    'border-slate-100 bg-slate-50 text-slate-600' => $vigente,
                    'border-amber-200 bg-amber-50 text-amber-900' => ! $vigente,
                ])>
                    @if ($vigente)
                        Válida hasta el
                        <strong>{{ $cotizacion->fecha_vencimiento->translatedFormat('d \d\e F \d\e Y') }}</strong>
                        ({{ $cotizacion->diasRestantes() === 0 ? 'último día' : 'quedan '.$cotizacion->diasRestantes().' días' }}).
                        Los precios de material cambian, por eso cada estimación vale
                        {{ $cotizacion->vigencia_dias }} días.
                    @else
                        Esta estimación venció el
                        <strong>{{ $cotizacion->fecha_vencimiento->translatedFormat('d \d\e F \d\e Y') }}</strong>.
                        El código sigue sirviendo: escríbenos y un asesor recupera tu pedido
                        con los precios de hoy.
                    @endif
                </div>

                {{-- ==================== DETALLE ==================== --}}
                <div class="px-5 py-6 sm:px-8">
                    <h1 class="mb-5 text-lg font-extrabold tracking-tight text-slate-800">
                        Trabajos estimados
                    </h1>

                    <div class="-mx-5 mb-6 overflow-x-auto px-5 sm:mx-0 sm:px-0">
                        <table class="w-full min-w-[30rem] text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] uppercase tracking-wide text-slate-500">
                                    <th scope="col" class="pb-2 pr-3 font-semibold">Trabajo</th>
                                    <th scope="col" class="pb-2 pr-3 text-right font-semibold">Cant.</th>
                                    <th scope="col" class="pb-2 pr-3 text-right font-semibold">Precio unit.</th>
                                    <th scope="col" class="pb-2 text-right font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($cotizacion->detalle as $linea)
                                    <tr>
                                        <td class="py-2.5 pr-3 text-slate-700">{{ $linea['descripcion'] }}</td>
                                        <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600">
                                            {{ (int) $linea['cantidad'] }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600">
                                            {{ number_format($linea['precio_unitario'], 2, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 text-right font-medium tabular-nums text-slate-800">
                                            {{ number_format($linea['subtotal'], 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <dl class="ml-auto max-w-xs space-y-1.5 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd class="tabular-nums font-medium text-slate-800">Bs {{ number_format($cotizacion->subtotal, 2, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>IVA ({{ (int) (config('margen.impuestos.iva') * 100) }}%)</dt>
                            <dd class="tabular-nums font-medium text-slate-800">Bs {{ number_format($cotizacion->iva, 2, ',', '.') }}</dd>
                        </div>
                    </dl>

                    {{-- El rango es el número que manda: dar un total exacto
                         sería prometer una precisión que esta estimación no
                         tiene (ver config/cotizador.php, `holgura`). --}}
                    <div class="mt-5 rounded-xl bg-marca-azul/5 p-5 text-center">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-marca-azul">
                            Rango aproximado · IVA incluido
                        </span>
                        <span class="block text-xl font-extrabold leading-tight text-marca-azul-oscuro sm:text-2xl">
                            Bs {{ number_format($cotizacion->estimado_min, 2, ',', '.') }}
                            <span class="font-normal text-slate-400">–</span>
                            Bs {{ number_format($cotizacion->estimado_max, 2, ',', '.') }}
                        </span>
                        <span class="mt-2 block text-[11px] leading-relaxed text-slate-500">
                            No incluye instalación, transporte fuera de {{ $empresa['ciudad'] }} ni diseño gráfico.
                        </span>
                    </div>
                </div>

                {{-- ==================== CONTACTO REGISTRADO ==================== --}}
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-5 text-xs text-slate-600 sm:px-8">
                    <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        Solicitada por
                    </h2>
                    <p class="font-medium text-slate-800">
                        {{ $cotizacion->nombre }}@if ($cotizacion->empresa) · {{ $cotizacion->empresa }}@endif
                    </p>
                    <p class="text-slate-500">
                        {{ $cotizacion->telefono }}@if ($cotizacion->email) · {{ $cotizacion->email }}@endif
                    </p>

                    @if ($cotizacion->mensaje)
                        <p class="mt-2 border-l-2 border-slate-300 pl-3 italic text-slate-500">
                            {{ $cotizacion->mensaje }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- ==================== DESCARGA DEL DOCUMENTO ==================== --}}
            @if (session('error'))
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @if ($cotizacion->descargado_en === null)
                    {{-- Todavía no lo descargó: el botón es la acción principal. --}}
                    <h2 class="mb-1 text-base font-bold text-slate-800">Llévate tu cotización</h2>
                    <p class="mb-4 text-xs leading-relaxed text-slate-500">
                        Genera el documento con el formato oficial de {{ $empresa['nombre'] }} —fecha de
                        emisión, detalle y vigencia— y guárdalo como PDF desde el diálogo de impresión.
                    </p>

                    <a
                        href="{{ route('cotizador.documento', $cotizacion->codigo) }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-marca-azul px-6 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-azul-oscuro"
                    >
                        <x-publico.icono nombre="documento" class="h-5 w-5" />
                        Descargar cotización en PDF
                    </a>
                @else
                    {{-- Ya lo descargó: el botón de generar desaparece. Queda
                         la constancia de cuándo se emitió y, mientras no se
                         agote el tope, un enlace discreto de recuperación —
                         sin él, quien cancele el diálogo de impresión sin
                         querer se queda sin salida. --}}
                    <h2 class="mb-1 flex items-center gap-2 text-base font-bold text-slate-800">
                        <x-publico.icono nombre="check" class="h-5 w-5 text-marca-verde" />
                        Documento ya descargado
                    </h2>
                    <p class="text-xs leading-relaxed text-slate-500">
                        Lo generaste el
                        <strong>{{ $cotizacion->descargado_en->translatedFormat('d \d\e F \d\e Y, H:i') }}</strong>.
                        @if ($cotizacion->puedeDescargar())
                            ¿Se te cerró sin guardar?
                            <a
                                href="{{ route('cotizador.documento', $cotizacion->codigo) }}"
                                class="font-semibold text-marca-azul hover:underline"
                            >Ábrelo de nuevo</a>.
                        @else
                            Si perdiste el archivo, escríbenos por WhatsApp con tu código
                            <strong class="font-mono text-slate-700">{{ $cotizacion->codigo }}</strong>
                            y te lo reenviamos.
                        @endif
                    </p>
                @endif
            </div>

            {{-- ==================== SIGUIENTE PASO ==================== --}}
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-1 text-base font-bold text-slate-800">¿Y ahora?</h2>
                <p class="mb-5 text-xs leading-relaxed text-slate-500">
                    Guarda o comparte este enlace. Al escribirnos, menciona el código
                    <strong class="font-mono text-slate-700">{{ $cotizacion->codigo }}</strong>
                    y un asesor retoma tu pedido sin que tengas que explicarlo de nuevo.
                </p>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-publico.enlace-whatsapp
                        :mensaje="$mensajeWhatsapp"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-marca-whatsapp px-4 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-whatsapp-oscuro"
                    >
                        <x-publico.icono nombre="whatsapp" class="h-5 w-5" />
                        Continuar por WhatsApp
                    </x-publico.enlace-whatsapp>

                    <a
                        href="{{ route('cotizador') }}"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition-colors hover:border-marca-azul hover:text-marca-azul"
                    >
                        <x-publico.icono nombre="calculadora" class="h-5 w-5" />
                        Hacer otra cotización
                    </a>
                </div>

                <p class="mt-4 text-[11px] leading-relaxed text-slate-400">
                    Esta es una estimación automática y no constituye una oferta comercial en
                    firme. El presupuesto formal lo emite {{ $empresa['nombre'] }} tras revisar
                    el detalle del trabajo.
                </p>
            </div>
        </div>
    </section>
</x-publico.layout>
