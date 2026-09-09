{{--
    Nota de entrega: el documento que se firma al dejar el trabajo.

    A diferencia de la cotización, no lleva precios: lo que se comprueba al
    recibir es QUÉ llegó y en qué estado, no cuánto costó. Termina en dos
    firmas porque es su razón de existir — sin la del cliente no prueba nada.
--}}
@extends('pdf.layout')

@php
    $cliente = $nota->pedido?->cotizacion?->cliente;

    $entrego = $nota->empleado
        ? trim("{$nota->empleado->nombres} {$nota->empleado->paterno} {$nota->empleado->materno}")
        : null;
@endphp

@section('identificacion')
    <p class="doc-tipo">Nota de entrega</p>
    <p class="doc-numero">{{ $nota->numero_nota }}</p>
    <p class="doc-subtitulo">Pedido {{ $nota->pedido?->numero_pedido ?? '—' }}</p>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Cliente</p>
            <p class="doc-dato-titulo">{{ $cliente?->razon_social ?? '—' }}</p>
            @if ($cliente?->nit)
                <p class="doc-dato"><span class="doc-clave">NIT:</span> {{ $cliente->nit }}</p>
            @endif
            @if ($cliente?->direccion)
                <p class="doc-dato"><span class="doc-clave">Dirección:</span> {{ $cliente->direccion }}</p>
            @endif
            @if ($cliente?->telefono)
                <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $cliente->telefono }}</p>
            @endif
        </div>

        <div>
            <p class="doc-rotulo">Entrega</p>
            <p class="doc-dato">
                <span class="doc-clave">Fecha:</span>
                <strong>{{ $nota->fecha_entrega?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}</strong>
            </p>
            <p class="doc-dato"><span class="doc-clave">Entregó:</span> {{ $entrego ?: '—' }}</p>
            <p class="doc-dato">
                <span class="doc-clave">Recibió:</span> {{ $nota->recibido_por ?? '—' }}
                @if ($nota->cargo_receptor)
                    <span class="doc-clave">({{ $nota->cargo_receptor }})</span>
                @endif
            </p>
        </div>
    </section>

    <table class="doc-tabla">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 46%;">Descripción del trabajo entregado</th>
                <th class="num" style="width: 10%;">Cant.</th>
                <th style="width: 25%;">Ubicación</th>
                <th class="centro" style="width: 15%;">Evidencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($nota->detalles as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $linea->descripcion }}</td>
                    <td class="num">{{ number_format((float) $linea->cantidad_entregada, 0, ',', '.') }}</td>
                    <td>{{ $linea->ubicacion ?? '—' }}</td>
                    <td class="centro">
                        {{-- Data URI y no una URL: Chromium genera el PDF sin
                             salir a la red (ver NotaEntregaDetalle::fotoIncrustada). --}}
                        @if ($foto = $linea->fotoIncrustada())
                            <img src="{{ $foto }}" alt="Evidencia de entrega" class="doc-evidencia">
                        @else
                            <span class="doc-clave">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($nota->observaciones)
        <section class="doc-seccion">
            <p class="doc-rotulo">Observaciones</p>
            <p class="doc-nota doc-preserva-saltos">{{ $nota->observaciones }}</p>
        </section>
    @endif

    <section class="doc-recuadro">
        <p class="doc-nota">
            Quien firma declara haber recibido los trabajos detallados arriba en las cantidades
            indicadas y a conformidad. Cualquier observación posterior debe comunicarse a
            {{ $empresa['nombre'] }} dentro de las 48 horas siguientes a esta entrega.
        </p>
    </section>

    {{-- La razón de ser del documento: sin la firma de quien recibe no prueba nada. --}}
    <section class="doc-firmas">
        <div class="doc-firma">
            <strong>{{ $entrego ?: 'Entregado por' }}</strong>
            {{ $empresa['nombre'] }}
        </div>
        <div class="doc-firma">
            <strong>{{ $nota->recibido_por ?: 'Recibido por' }}</strong>
            {{ $cliente?->razon_social ?? 'Cliente' }} · Firma y sello
        </div>
    </section>

    <p class="doc-nota-legal">
        Documento generado por el sistema de {{ $empresa['nombre'] }}
        el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
        Nota <strong>{{ $nota->numero_nota }}</strong>, correspondiente al pedido
        <strong>{{ $nota->pedido?->numero_pedido ?? '—' }}</strong>.
    </p>
@endsection
