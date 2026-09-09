{{--
    Orden de compra a proveedor.

    Cumple dos papeles según el estado: PENDIENTE es el pedido que se le manda
    al proveedor, y PAGADA es el respaldo del ingreso a inventario. Por eso el
    pie cambia con el estado en vez de repetir un texto genérico.
--}}
@extends('pdf.layout')

@php
    $estadoEtiqueta = match ($compra->estado) {
        'PAGADA' => 'doc-etiqueta--exito',
        'ANULADA' => 'doc-etiqueta--peligro',
        default => 'doc-etiqueta--aviso',
    };

    $responsable = $compra->empleado
        ? trim("{$compra->empleado->nombres} {$compra->empleado->paterno} {$compra->empleado->materno}")
        : null;
@endphp

@section('identificacion')
    <p class="doc-tipo">Orden de compra</p>
    <p class="doc-numero">OC-{{ str_pad((string) $compra->id, 5, '0', STR_PAD_LEFT) }}</p>
    <span class="doc-etiqueta {{ $estadoEtiqueta }}">{{ $compra->estado }}</span>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Proveedor</p>
            <p class="doc-dato-titulo">{{ $compra->proveedor?->nombre ?? '—' }}</p>
            @if ($compra->proveedor?->nit)
                <p class="doc-dato"><span class="doc-clave">NIT:</span> {{ $compra->proveedor->nit }}</p>
            @endif
            @if ($compra->proveedor?->telefono)
                <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $compra->proveedor->telefono }}</p>
            @endif
            @if ($compra->proveedor?->email)
                <p class="doc-dato"><span class="doc-clave">Correo:</span> {{ $compra->proveedor->email }}</p>
            @endif
        </div>

        <div>
            <p class="doc-rotulo">Detalles</p>
            <p class="doc-dato">
                <span class="doc-clave">Fecha:</span>
                {{ $compra->fecha?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}
            </p>
            <p class="doc-dato"><span class="doc-clave">Factura:</span> {{ $compra->numero_factura ?: '—' }}</p>
            <p class="doc-dato"><span class="doc-clave">Responsable:</span> {{ $responsable ?: '—' }}</p>
            <p class="doc-dato"><span class="doc-clave">Moneda:</span> Bolivianos (Bs)</p>
        </div>
    </section>

    <table class="doc-tabla">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 46%;">Material</th>
                <th class="num" style="width: 16%;">Cantidad</th>
                <th class="num" style="width: 17%;">P. unit.</th>
                <th class="num" style="width: 17%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($compra->detalles as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>
                        {{ $linea->material?->nombre ?? '—' }}
                        @if ($linea->material?->presentacion)
                            <span class="doc-linea-nota">{{ $linea->material->presentacion }}</span>
                        @endif
                    </td>
                    <td class="num">
                        {{ number_format((float) $linea->cantidad, 2, ',', '.') }}
                        <span class="doc-linea-nota">{{ $linea->material?->unidad_medida }}</span>
                    </td>
                    <td class="num"><x-pdf.monto :valor="$linea->precio_unitario" :moneda="false" /></td>
                    <td class="num"><strong><x-pdf.monto :valor="$linea->subtotal" :moneda="false" /></strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="doc-totales">
        <div class="doc-total-fila doc-total-fila--principal">
            <span>Total</span>
            <x-pdf.monto :valor="$compra->total" />
        </div>
    </section>

    @if ($compra->estado === 'PENDIENTE')
        <section class="doc-recuadro doc-recuadro--aviso">
            <p class="doc-nota">
                Esta compra todavía no impactó el inventario. Al aprobarla se sumará la cantidad al
                stock de cada material y su precio unitario se actualizará con el de esta compra,
                quedando registro en el historial de precios.
            </p>
        </section>
    @elseif ($compra->estado === 'ANULADA')
        <section class="doc-recuadro doc-recuadro--aviso">
            <p class="doc-nota">
                <strong>Compra anulada.</strong> No impactó el inventario ni los precios de los
                materiales. Se conserva únicamente como registro histórico.
            </p>
        </section>
    @else
        <section class="doc-firmas">
            <div class="doc-firma">
                <strong>{{ $responsable ?: 'Recibido por' }}</strong>
                {{ $empresa['nombre'] }}
            </div>
            <div class="doc-firma">
                <strong>{{ $compra->proveedor?->nombre ?? 'Proveedor' }}</strong>
                Firma y sello
            </div>
        </section>
    @endif

    <p class="doc-nota-legal">
        Documento generado por el sistema de {{ $empresa['nombre'] }}
        el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
    </p>
@endsection
