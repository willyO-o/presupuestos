{{--
    Orden de trabajo: lo que baja a producción.

    Es el único documento que NO lleva precio de venta línea por línea. Va al
    taller, y ahí lo que hace falta es qué hay que fabricar, con qué medidas y
    en qué etapa está cada pieza — el precio solo distrae y no tiene por qué
    circular por el galpón. El total se imprime al pie porque sirve de control
    contra la cotización de origen.
--}}
@extends('pdf.layout')

@php
    $cotizacion = $pedido->cotizacion;
    $cliente = $cotizacion?->cliente;

    $estadoEtiqueta = match ($pedido->estado) {
        'ENTREGADO' => 'doc-etiqueta--exito',
        'CANCELADO' => 'doc-etiqueta--peligro',
        'CONTROL_CALIDAD' => 'doc-etiqueta--info',
        default => 'doc-etiqueta--aviso',
    };

    $vendedor = $cotizacion?->empleado
        ? trim("{$cotizacion->empleado->nombres} {$cotizacion->empleado->paterno} {$cotizacion->empleado->materno}")
        : null;
@endphp

@section('identificacion')
    <p class="doc-tipo">Orden de trabajo</p>
    <p class="doc-numero">{{ $pedido->numero_pedido }}</p>
    <span class="doc-etiqueta {{ $estadoEtiqueta }}">{{ str_replace('_', ' ', $pedido->estado) }}</span>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Cliente</p>
            <p class="doc-dato-titulo">{{ $cliente?->razon_social ?? '—' }}</p>
            @if ($cliente?->telefono)
                <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $cliente->telefono }}</p>
            @endif
            @if ($cliente?->direccion)
                <p class="doc-dato"><span class="doc-clave">Dirección:</span> {{ $cliente->direccion }}</p>
            @endif
            <p class="doc-dato">
                <span class="doc-clave">Cotización:</span>
                {{ $cotizacion?->codigo_verificacion ?? '—' }}
            </p>
        </div>

        <div>
            <p class="doc-rotulo">Producción</p>
            <p class="doc-dato">
                <span class="doc-clave">Emitido:</span>
                {{ $pedido->fecha_pedido?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Entrega estimada:</span>
                <strong>{{ $pedido->fecha_entrega_estimada?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}</strong>
            </p>
            @if ($pedido->fecha_entrega_real)
                <p class="doc-dato">
                    <span class="doc-clave">Entrega real:</span>
                    {{ $pedido->fecha_entrega_real->translatedFormat('d \d\e F \d\e Y') }}
                </p>
            @endif
            <p class="doc-dato"><span class="doc-clave">Vendedor:</span> {{ $vendedor ?: '—' }}</p>
        </div>
    </section>

    <table class="doc-tabla">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 44%;">Trabajo a producir</th>
                <th class="centro" style="width: 20%;">Medidas (m)</th>
                <th class="num" style="width: 10%;">Cant.</th>
                <th class="centro" style="width: 22%;">Etapa</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido->detalles as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $linea->descripcion }}</td>
                    <td class="centro">
                        @if ($linea->ancho && $linea->alto)
                            {{ number_format((float) $linea->ancho, 2, ',', '.') }} ×
                            {{ number_format((float) $linea->alto, 2, ',', '.') }}
                            <span class="doc-linea-nota">
                                {{ number_format((float) $linea->ancho * (float) $linea->alto, 2, ',', '.') }} m²
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                    <td class="centro">{{ str_replace('_', ' ', $linea->estado_item) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="doc-recuadro">
        <p class="doc-titulo-seccion">Medidas de producción</p>
        <p class="doc-nota">
            Las medidas de arriba son las comprometidas con el cliente. Si la pieza terminada
            difiere, se registran las medidas REALES en el sistema (Pedidos → Ajustar medidas
            reales); el precio acordado no cambia por eso.
        </p>
    </section>

    <section class="doc-totales">
        <div class="doc-total-fila doc-total-fila--principal">
            <span>Total del pedido</span>
            <x-pdf.monto :valor="$pedido->total" />
        </div>
    </section>

    <section class="doc-firmas">
        <div class="doc-firma">
            <strong>Jefe de producción</strong>
            Recibe la orden
        </div>
        <div class="doc-firma">
            <strong>Control de calidad</strong>
            Aprueba antes de entregar
        </div>
    </section>

    <p class="doc-nota-legal">
        Documento interno generado por el sistema de {{ $empresa['nombre'] }}
        el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
        Orden <strong>{{ $pedido->numero_pedido }}</strong>.
    </p>
@endsection
