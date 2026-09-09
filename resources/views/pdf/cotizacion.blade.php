{{--
    Presupuesto formal que se le envía al cliente.

    Imprime SOLO lo que el cliente puede ver. `costo_base`, `costo_ajustado`,
    `margen_aplicado`, `factor_complejidad`, `it`, `iue`, `utilidad_real` y
    `estado_margen` están cargados en el modelo pero NO entran acá: son la
    rentabilidad de la empresa (ver el docblock de `Cotizacion::ESTADOS_MARGEN`)
    y este documento sale por correo.
--}}
@extends('pdf.layout')

@php
    $estadoEtiqueta = match ($cotizacion->estado) {
        'APROBADA' => 'doc-etiqueta--exito',
        'RECHAZADA' => 'doc-etiqueta--peligro',
        'CONVERTIDA' => 'doc-etiqueta--info',
        'VENCIDA' => 'doc-etiqueta--aviso',
        default => '',
    };

    $vendedor = $cotizacion->empleado
        ? trim("{$cotizacion->empleado->nombres} {$cotizacion->empleado->paterno} {$cotizacion->empleado->materno}")
        : null;
@endphp

@section('identificacion')
    <p class="doc-tipo">Cotización</p>
    <p class="doc-numero">{{ $cotizacion->codigo_verificacion }}</p>
    <span class="doc-etiqueta {{ $estadoEtiqueta }}">{{ $cotizacion->estado }}</span>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Cliente</p>
            <p class="doc-dato-titulo">{{ $cotizacion->cliente?->razon_social ?? '—' }}</p>
            <p class="doc-dato"><span class="doc-clave">NIT:</span> {{ $cotizacion->cliente?->nit ?? '—' }}</p>
            @if ($cotizacion->cliente?->telefono)
                <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $cotizacion->cliente->telefono }}</p>
            @endif
            @if ($cotizacion->cliente?->email)
                <p class="doc-dato"><span class="doc-clave">Correo:</span> {{ $cotizacion->cliente->email }}</p>
            @endif
            @if ($cotizacion->cliente?->direccion)
                <p class="doc-dato"><span class="doc-clave">Dirección:</span> {{ $cotizacion->cliente->direccion }}</p>
            @endif
        </div>

        <div>
            <p class="doc-rotulo">Detalles</p>
            <p class="doc-dato">
                <span class="doc-clave">Fecha de emisión:</span>
                {{ $cotizacion->fecha?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Válida hasta:</span>
                <strong>{{ $cotizacion->fecha_vencimiento?->translatedFormat('d \d\e F \d\e Y') ?? '—' }}</strong>
            </p>
            <p class="doc-dato"><span class="doc-clave">Sucursal:</span> {{ $cotizacion->sucursal?->nombre ?? '—' }}</p>
            <p class="doc-dato"><span class="doc-clave">Vendedor:</span> {{ $vendedor ?: '—' }}</p>
            <p class="doc-dato"><span class="doc-clave">Moneda:</span> Bolivianos (Bs)</p>
        </div>
    </section>

    <table class="doc-tabla">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 40%;">Descripción</th>
                <th class="centro" style="width: 18%;">Medidas (m)</th>
                <th class="num" style="width: 8%;">Cant.</th>
                <th class="num" style="width: 15%;">P. unit.</th>
                <th class="num" style="width: 15%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cotizacion->detalles as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>
                        {{ $linea->descripcion }}
                        @if ($linea->producto)
                            <span class="doc-linea-nota">{{ $linea->producto->nombre }}</span>
                        @endif
                    </td>
                    <td class="centro">
                        @if ($linea->ancho && $linea->alto)
                            {{ number_format((float) $linea->ancho, 2, ',', '.') }} ×
                            {{ number_format((float) $linea->alto, 2, ',', '.') }}
                            <span class="doc-linea-nota">
                                {{ number_format((float) $linea->area_m2, 2, ',', '.') }} m²
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                    <td class="num"><x-pdf.monto :valor="$linea->precio_unitario" :moneda="false" /></td>
                    <td class="num"><strong><x-pdf.monto :valor="$linea->subtotal" :moneda="false" /></strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="doc-totales">
        <div class="doc-total-fila">
            <span>Subtotal</span>
            <x-pdf.monto :valor="$cotizacion->subtotal" />
        </div>

        @if ((float) $cotizacion->descuento > 0)
            <div class="doc-total-fila">
                <span>Descuento</span>
                <span class="num">− {{ number_format((float) $cotizacion->descuento, 2, ',', '.') }}</span>
            </div>
        @endif

        @if ((float) $cotizacion->iva > 0)
            <div class="doc-total-fila">
                <span>IVA ({{ (int) (config('margen.impuestos.iva') * 100) }}%)</span>
                <x-pdf.monto :valor="$cotizacion->iva" />
            </div>
        @endif

        @if ((float) $cotizacion->instalacion > 0)
            <div class="doc-total-fila">
                <span>Instalación</span>
                <x-pdf.monto :valor="$cotizacion->instalacion" />
            </div>
        @endif

        <div class="doc-total-fila doc-total-fila--principal">
            <span>Total</span>
            <x-pdf.monto :valor="$cotizacion->total" />
        </div>
    </section>

    @if ($cotizacion->observaciones)
        <section class="doc-seccion">
            <p class="doc-rotulo">Observaciones</p>
            <p class="doc-nota doc-preserva-saltos">{{ $cotizacion->observaciones }}</p>
        </section>
    @endif

    <section class="doc-recuadro">
        <p class="doc-titulo-seccion">Condiciones</p>
        <ul class="doc-listado">
            <li>Precios en bolivianos, válidos hasta la fecha indicada arriba.</li>
            <li>Los trabajos inician una vez aprobada la cotización por escrito.</li>
            <li>El plazo de entrega se confirma al momento de la aprobación.</li>
            @if ((float) $cotizacion->instalacion <= 0)
                <li>No incluye instalación ni montaje salvo que se indique en el detalle.</li>
            @endif
        </ul>
    </section>

    <p class="doc-nota-legal">
        Documento generado por el sistema de costos y presupuestos de {{ $empresa['nombre'] }}
        el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
        Puedes verificar su autenticidad en
        <strong>{{ route('verificar', $cotizacion->codigo_verificacion) }}</strong>
        con el código <strong>{{ $cotizacion->codigo_verificacion }}</strong>.
    </p>
@endsection
