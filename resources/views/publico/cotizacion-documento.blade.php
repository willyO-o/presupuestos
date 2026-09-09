{{--
    Documento imprimible de una estimación del cotizador público.

    Réplica de la forma del presupuesto del panel (Pages/Cotizaciones/Show.vue
    + app.css §22): mismo encabezado con la empresa a la izquierda y el número
    de documento a la derecha, mismo bloque de datos en dos columnas, misma
    tabla de detalle y misma escalera de totales. Lo que el visitante guarda
    como PDF tiene que parecerse a lo que le llega de la empresa, no a una
    página web impresa.

    Dos diferencias, y son a propósito:
    - Dice ESTIMACIÓN, no COTIZACIÓN, y lleva el aviso de que no es una oferta
      en firme. Prometer un precio cerrado calculado sin revisión humana es
      exactamente el problema que el rango ±holgura evita.
    - El total va acompañado del rango aproximado.

    NO usa `x-publico.layout`: un documento no lleva menú, pie ni botón
    flotante de WhatsApp. Es una página suelta preparada para el diálogo de
    impresión (ver `publico.css` §"documento imprimible" y el bloque
    `[data-documento-imprimible]` de `publico.js`, que lo abre solo).
--}}
@php
    $empresa = config('sitio.empresa');
    $emitido = $cotizacion->created_at;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Un documento con el contacto de una persona nunca se indexa. --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- El navegador propone este título como nombre del PDF al guardar. --}}
    <title>Estimacion {{ $cotizacion->codigo }} - {{ $empresa['nombre'] }}</title>

    <link rel="icon" type="image/png" href="{{ asset('img/logo/logo-mini.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=montserrat:600,700,800|roboto:300,400,500,700&display=swap">

    @vite(['resources/css/publico.css', 'resources/js/publico.js'])
</head>
<body class="bg-slate-100 print:bg-white" data-documento-imprimible>

    {{-- ==================== BARRA DE ACCIONES (no se imprime) ==================== --}}
    <div class="doc-acciones sticky top-0 z-10 border-b border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3">
            <a
                href="{{ route('cotizador.show', $cotizacion->codigo) }}"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition-colors hover:text-marca-azul"
            >
                <x-publico.icono nombre="flecha" class="h-4 w-4 rotate-180" />
                Volver a mi cotización
            </a>

            {{-- Reimprimir desde acá NO gasta otra emisión: el tope cuenta
                 cuántas veces se pidió la página, y ya estás en ella. Es lo
                 que salva a quien canceló el diálogo sin querer. --}}
            <button
                type="button"
                data-imprimir
                class="inline-flex items-center gap-2 rounded-xl bg-marca-azul px-5 py-2.5 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-azul-oscuro"
            >
                <x-publico.icono nombre="documento" class="h-4 w-4" />
                Descargar PDF
            </button>
        </div>

        <p class="mx-auto mt-2 max-w-3xl text-xs text-slate-500">
            En el diálogo de impresión elige <strong>«Guardar como PDF»</strong> como destino.
        </p>
    </div>

    {{-- ==================== DOCUMENTO ==================== --}}
    <main class="mx-auto max-w-3xl px-4 py-8 print:max-w-none print:px-0 print:py-0">
        <article class="doc-hoja rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">

            {{-- ---------- Encabezado ---------- --}}
            <header class="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
                <div class="flex items-start gap-3">
                    <img
                        src="{{ asset('img/logo/logo.webp') }}"
                        alt="{{ $empresa['nombre'] }}"
                        width="148"
                        height="36"
                        class="h-9 w-auto"
                    >
                </div>

                <div class="text-right">
                    <p class="text-base font-extrabold tracking-tight text-slate-800">ESTIMACIÓN REFERENCIAL</p>
                    <p class="text-sm text-slate-500">
                        N.º <span class="font-mono font-bold text-slate-800">{{ $cotizacion->codigo }}</span>
                    </p>
                    <span @class([
                        'mt-1 inline-flex items-center rounded px-2 py-0.5 text-[10px] font-bold uppercase',
                        'bg-marca-verde text-white' => $vigente,
                        'bg-marca-ambar text-marca-oscuro' => ! $vigente,
                    ])>
                        {{ $vigente ? 'Vigente' : 'Vencida' }}
                    </span>
                </div>
            </header>

            {{-- ---------- Datos ---------- ---------- --}}
            <section class="mb-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <h2 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Solicitado por</h2>
                    <p class="font-semibold text-slate-800">{{ $cotizacion->nombre }}</p>
                    @if ($cotizacion->empresa)
                        <p class="text-sm text-slate-500">{{ $cotizacion->empresa }}</p>
                    @endif
                    <p class="text-sm text-slate-500">Tel: {{ $cotizacion->telefono }}</p>
                    @if ($cotizacion->email)
                        <p class="break-all text-sm text-slate-500">{{ $cotizacion->email }}</p>
                    @endif
                </div>

                <div>
                    <h2 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Detalles</h2>
                    <p class="text-sm text-slate-600">
                        <span class="text-slate-400">Fecha de emisión:</span>
                        {{ $emitido->translatedFormat('d \d\e F \d\e Y') }}
                    </p>
                    <p class="text-sm text-slate-600">
                        <span class="text-slate-400">Válida hasta:</span>
                        {{ $cotizacion->fecha_vencimiento->translatedFormat('d \d\e F \d\e Y') }}
                        <span class="text-slate-400">({{ $cotizacion->vigencia_dias }} días)</span>
                    </p>
                    <p class="text-sm text-slate-600">
                        <span class="text-slate-400">Origen:</span> Cotizador en línea
                    </p>
                    <p class="text-sm text-slate-600">
                        <span class="text-slate-400">Moneda:</span> Bolivianos (Bs)
                    </p>
                </div>
            </section>

            {{-- ---------- Detalle ---------- --}}
            <div class="-mx-6 mb-1 overflow-x-auto px-6 sm:mx-0 sm:px-0">
                <table class="w-full min-w-[34rem] text-left text-xs">
                    <thead>
                        <tr class="border-b-2 border-slate-200 text-[11px] uppercase tracking-wide text-slate-500">
                            <th scope="col" class="pb-2 pr-2 font-semibold">#</th>
                            <th scope="col" class="pb-2 pr-3 font-semibold">Descripción</th>
                            <th scope="col" class="pb-2 pr-3 text-center font-semibold">Medidas (m)</th>
                            <th scope="col" class="pb-2 pr-3 text-right font-semibold">Cant.</th>
                            <th scope="col" class="pb-2 pr-3 text-right font-semibold">P. unit.</th>
                            <th scope="col" class="pb-2 text-right font-semibold">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cotizacion->detalle as $indice => $linea)
                            <tr>
                                <td class="py-2.5 pr-2 text-slate-400">{{ $indice + 1 }}</td>
                                <td class="py-2.5 pr-3 text-slate-700">
                                    {{ $linea['producto'] ?? $linea['descripcion'] }}
                                </td>
                                <td class="py-2.5 pr-3 text-center tabular-nums text-slate-600">
                                    @if (($linea['ancho'] ?? null) && ($linea['alto'] ?? null))
                                        {{ number_format($linea['ancho'], 2, ',', '.') }} ×
                                        {{ number_format($linea['alto'], 2, ',', '.') }}
                                        <span class="block text-[10px] text-slate-400">
                                            ({{ number_format($linea['ancho'] * $linea['alto'], 2, ',', '.') }} m²)
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600">
                                    {{ (int) $linea['cantidad'] }}
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600">
                                    {{ number_format($linea['precio_unitario'], 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 text-right font-semibold tabular-nums text-slate-800">
                                    {{ number_format($linea['subtotal'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ---------- Totales ---------- --}}
            <div class="ml-auto mt-4 w-full max-w-xs">
                <div class="flex items-center justify-between gap-3 py-2 text-sm text-slate-600">
                    <span>Subtotal</span>
                    <span class="tabular-nums">Bs {{ number_format($cotizacion->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2 text-sm text-slate-600">
                    <span>IVA ({{ (int) (config('margen.impuestos.iva') * 100) }}%)</span>
                    <span class="tabular-nums">Bs {{ number_format($cotizacion->iva, 2, ',', '.') }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-3 border-t-2 border-slate-300 pt-3 text-base font-bold text-slate-900">
                    <span>Total estimado</span>
                    <span class="tabular-nums">Bs {{ number_format($cotizacion->total, 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- ---------- Rango ---------- --}}
            <div class="mt-5 rounded-xl border border-marca-azul/20 bg-marca-azul/5 p-4 text-center">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-marca-azul">
                    Rango aproximado · IVA incluido
                </span>
                <span class="block text-base font-extrabold text-marca-azul-oscuro sm:text-lg">
                    Bs {{ number_format($cotizacion->estimado_min, 2, ',', '.') }}
                    <span class="font-normal text-slate-400">–</span>
                    Bs {{ number_format($cotizacion->estimado_max, 2, ',', '.') }}
                </span>
                <span class="mt-1 block text-[11px] text-slate-500">
                    Margen de ±{{ (int) round($cotizacion->holgura * 100) }}% sobre el total estimado.
                </span>
            </div>

            {{-- ---------- Observaciones del visitante ---------- --}}
            @if ($cotizacion->mensaje)
                <section class="mt-5 border-t border-slate-200 pt-4">
                    <h2 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Indicaciones del solicitante
                    </h2>
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $cotizacion->mensaje }}</p>
                </section>
            @endif

            {{-- ---------- Alcance ---------- --}}
            <section class="mt-5 grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <div>
                    <h2 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Incluye</h2>
                    <ul class="space-y-0.5 text-xs leading-relaxed text-slate-500">
                        <li>Materiales según la ficha técnica de cada trabajo.</li>
                        <li>Fabricación en taller.</li>
                        <li>IVA de ley ({{ (int) (config('margen.impuestos.iva') * 100) }}%).</li>
                    </ul>
                </div>
                <div>
                    <h2 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">No incluye</h2>
                    <ul class="space-y-0.5 text-xs leading-relaxed text-slate-500">
                        <li>Instalación y montaje en punto de venta.</li>
                        <li>Transporte fuera de {{ $empresa['ciudad'] }} y {{ $empresa['departamento'] }}.</li>
                        <li>Diseño gráfico, acabados especiales y estructuras a medida.</li>
                    </ul>
                </div>
            </section>

            {{-- ---------- Pie legal ---------- --}}
            <footer class="mt-6 border-t border-slate-200 pt-4">
                <p class="mb-3 text-[11px] leading-relaxed text-slate-500">
                    <strong class="text-slate-700">Aviso:</strong> este documento es una estimación
                    automática generada por el cotizador en línea de {{ $empresa['nombre'] }} a partir de
                    los precios de material vigentes al {{ $emitido->translatedFormat('d/m/Y') }}.
                    <strong class="text-slate-700">No constituye una oferta comercial en firme.</strong>
                    El presupuesto formal se emite tras revisar el detalle del trabajo y puede diferir de
                    estos montos.
                </p>

                <div class="flex flex-wrap items-end justify-between gap-4 text-[11px] text-slate-500">
                    <div>
                        <p class="font-bold text-slate-700">{{ $empresa['nombre'] }}</p>
                        <p>{{ $empresa['direccion'] }} · {{ $empresa['ciudad'] }}, {{ $empresa['departamento'] }}</p>
                        <p>{{ $empresa['telefono_visible'] }} · {{ $empresa['email'] }}</p>
                        <p>{{ str_replace('https://', '', $empresa['web']) }}</p>
                    </div>

                    <p class="text-right">
                        Código de referencia<br>
                        <span class="font-mono text-sm font-bold text-slate-800">{{ $cotizacion->codigo }}</span>
                    </p>
                </div>
            </footer>
        </article>
    </main>
</body>
</html>
