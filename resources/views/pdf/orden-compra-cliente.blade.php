{{--
    Acuse de la orden de compra que emitió el cliente.

    NO reemplaza al PDF que él mandó (`orden_compra_cliente.archivo_pdf`, que
    es su documento y se guarda tal cual): deja constancia de cómo quedó
    registrada en el sistema y, sobre todo, de si el monto coteja con el
    pedido. Ese cotejo es la única razón por la que este documento existe —
    una diferencia entre la OC y el pedido es lo que frena una facturación.
--}}
@extends('pdf.layout')

@php
    $pedido = $orden->pedido;
    $cliente = $pedido?->cotizacion?->cliente;

    $estadoEtiqueta = match ($orden->estado) {
        'VALIDADA' => 'doc-etiqueta--exito',
        'ANULADA' => 'doc-etiqueta--peligro',
        default => 'doc-etiqueta--aviso',
    };

    $difiere = $orden->difiereDelPedido();
    $diferencia = (float) $orden->monto_total - (float) ($pedido->total ?? 0);
@endphp

@section('identificacion')
    <p class="doc-tipo">Orden de compra</p>
    <p class="doc-numero">{{ $orden->numero_oc }}</p>
    <p class="doc-subtitulo">Pedido {{ $pedido?->numero_pedido ?? '—' }}</p>
    <span class="doc-etiqueta {{ $estadoEtiqueta }}">{{ $orden->estado }}</span>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Cliente emisor</p>
            <p class="doc-dato-titulo">{{ $cliente?->razon_social ?? '—' }}</p>
            @if ($cliente?->nit)
                <p class="doc-dato"><span class="doc-clave">NIT:</span> {{ $cliente->nit }}</p>
            @endif
            @if ($cliente?->telefono)
                <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $cliente->telefono }}</p>
            @endif
        </div>

        <div>
            <p class="doc-rotulo">Documento</p>
            <p class="doc-dato">
                <span class="doc-clave">Fecha de la OC:</span>
                {{ $orden->fecha?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Condición de pago:</span> {{ $orden->condicion_pago ?: '—' }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Pedido asociado:</span> {{ $pedido?->numero_pedido ?? '—' }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Cotización:</span>
                {{ $pedido?->cotizacion?->codigo_verificacion ?? '—' }}
            </p>
        </div>
    </section>

    {{-- El cotejo: es lo que se mira de este documento. --}}
    <section class="doc-totales" style="width: 60%;">
        <div class="doc-total-fila">
            <span>Monto de la orden de compra</span>
            <x-pdf.monto :valor="$orden->monto_total" />
        </div>
        <div class="doc-total-fila">
            <span>Total del pedido {{ $pedido?->numero_pedido }}</span>
            <x-pdf.monto :valor="$pedido->total ?? 0" />
        </div>
        <div class="doc-total-fila doc-total-fila--principal">
            <span>Diferencia</span>
            <span class="num">
                {{ $diferencia > 0 ? '+' : '' }}{{ number_format($diferencia, 2, ',', '.') }}
            </span>
        </div>
    </section>

    @if ($difiere)
        <section class="doc-recuadro doc-recuadro--aviso">
            <p class="doc-nota">
                <strong>El monto de la orden de compra no coincide con el total del pedido.</strong>
                Antes de facturar hay que resolver la diferencia con el cliente: puede ser un
                cambio de alcance no reflejado en el pedido o un error de transcripción.
            </p>
        </section>
    @else
        <section class="doc-recuadro">
            <p class="doc-nota">
                El monto de la orden de compra coincide con el total del pedido.
            </p>
        </section>
    @endif

    @if ($pedido?->detalles?->isNotEmpty())
        <section class="doc-seccion">
            <p class="doc-rotulo">Trabajos cubiertos por esta orden</p>
            <table class="doc-tabla">
                <thead>
                    <tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 66%;">Descripción</th>
                        <th class="centro" style="width: 18%;">Medidas (m)</th>
                        <th class="num" style="width: 12%;">Cant.</th>
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
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <p class="doc-nota-legal">
        Acuse generado por el sistema de {{ $empresa['nombre'] }}
        el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
        Este documento refleja cómo quedó registrada la orden de compra
        <strong>{{ $orden->numero_oc }}</strong> del cliente; el documento original emitido por
        {{ $cliente?->razon_social ?? 'el cliente' }} se conserva por separado.
    </p>
@endsection
