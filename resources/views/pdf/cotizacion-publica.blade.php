{{--
    Estimación que un visitante armó solo en `/cotizador`.

    Misma forma que `pdf/cotizacion.blade.php` para que el cliente reconozca el
    documento, pero rotulada ESTIMACIÓN y con el aviso de que no es una oferta
    en firme: el precio lo calculó el motor sin que lo revisara un vendedor, y
    caduca. Por eso el número que se destaca es el RANGO y no el total exacto.
--}}
@extends('pdf.layout')

@section('identificacion')
    <p class="doc-tipo">Estimación referencial</p>
    <p class="doc-numero">{{ $estimacion->codigo }}</p>
    <span class="doc-etiqueta {{ $vigente ? 'doc-etiqueta--exito' : 'doc-etiqueta--aviso' }}">
        {{ $vigente ? 'Vigente' : 'Vencida' }}
    </span>
@endsection

@section('contenido')
    <section class="doc-datos">
        <div>
            <p class="doc-rotulo">Solicitado por</p>
            <p class="doc-dato-titulo">{{ $estimacion->nombre }}</p>
            @if ($estimacion->empresa)
                <p class="doc-dato">{{ $estimacion->empresa }}</p>
            @endif
            <p class="doc-dato"><span class="doc-clave">Tel:</span> {{ $estimacion->telefono }}</p>
            @if ($estimacion->email)
                <p class="doc-dato"><span class="doc-clave">Correo:</span> {{ $estimacion->email }}</p>
            @endif
        </div>

        <div>
            <p class="doc-rotulo">Detalles</p>
            <p class="doc-dato">
                <span class="doc-clave">Fecha de emisión:</span>
                {{ $estimacion->created_at->translatedFormat('d \d\e F \d\e Y') }}
            </p>
            <p class="doc-dato">
                <span class="doc-clave">Válida hasta:</span>
                <strong>{{ $estimacion->fecha_vencimiento->translatedFormat('d \d\e F \d\e Y') }}</strong>
                <span class="doc-clave">({{ $estimacion->vigencia_dias }} días)</span>
            </p>
            <p class="doc-dato"><span class="doc-clave">Origen:</span> Cotizador en línea</p>
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
            @foreach ($estimacion->detalle as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $linea['producto'] ?? $linea['descripcion'] }}</td>
                    <td class="centro">
                        @if (($linea['ancho'] ?? null) && ($linea['alto'] ?? null))
                            {{ number_format($linea['ancho'], 2, ',', '.') }} ×
                            {{ number_format($linea['alto'], 2, ',', '.') }}
                            <span class="doc-linea-nota">
                                {{ number_format($linea['ancho'] * $linea['alto'], 2, ',', '.') }} m²
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ (int) $linea['cantidad'] }}</td>
                    <td class="num"><x-pdf.monto :valor="$linea['precio_unitario']" :moneda="false" /></td>
                    <td class="num"><strong><x-pdf.monto :valor="$linea['subtotal']" :moneda="false" /></strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="doc-totales">
        <div class="doc-total-fila">
            <span>Subtotal</span>
            <x-pdf.monto :valor="$estimacion->subtotal" />
        </div>
        <div class="doc-total-fila">
            <span>IVA ({{ (int) (config('margen.impuestos.iva') * 100) }}%)</span>
            <x-pdf.monto :valor="$estimacion->iva" />
        </div>
        <div class="doc-total-fila doc-total-fila--principal">
            <span>Total estimado</span>
            <x-pdf.monto :valor="$estimacion->total" />
        </div>
    </section>

    {{-- El rango es el número que manda: dar un total exacto sería prometer
         una precisión que esta estimación no tiene. --}}
    <section class="doc-recuadro doc-recuadro--destacado">
        <p class="doc-rotulo">Rango aproximado · IVA incluido</p>
        <p class="doc-rango">
            Bs {{ number_format((float) $estimacion->estimado_min, 2, ',', '.') }}
            – Bs {{ number_format((float) $estimacion->estimado_max, 2, ',', '.') }}
        </p>
        <p class="doc-nota">
            Margen de ±{{ (int) round((float) $estimacion->holgura * 100) }}% sobre el total estimado.
        </p>
    </section>

    @if ($estimacion->mensaje)
        <section class="doc-seccion">
            <p class="doc-rotulo">Indicaciones del solicitante</p>
            <p class="doc-nota doc-preserva-saltos">{{ $estimacion->mensaje }}</p>
        </section>
    @endif

    <section class="doc-datos" style="margin-top: 14px;">
        <div>
            <p class="doc-rotulo">Incluye</p>
            <ul class="doc-listado">
                <li>Materiales según la ficha técnica de cada trabajo.</li>
                <li>Fabricación en taller.</li>
                <li>IVA de ley ({{ (int) (config('margen.impuestos.iva') * 100) }}%).</li>
            </ul>
        </div>
        <div>
            <p class="doc-rotulo">No incluye</p>
            <ul class="doc-listado">
                <li>Instalación y montaje en punto de venta.</li>
                <li>Transporte fuera de {{ $empresa['ciudad'] }} y {{ $empresa['departamento'] }}.</li>
                <li>Diseño gráfico, acabados especiales y estructuras a medida.</li>
            </ul>
        </div>
    </section>

    <section class="doc-recuadro doc-recuadro--aviso">
        <p class="doc-nota">
            <strong>Aviso:</strong> este documento es una estimación automática generada por el
            cotizador en línea de {{ $empresa['nombre'] }} a partir de los precios de material
            vigentes al {{ $estimacion->created_at->translatedFormat('d/m/Y') }}.
            <strong>No constituye una oferta comercial en firme.</strong>
            El presupuesto formal se emite tras revisar el detalle del trabajo y puede diferir de
            estos montos.
        </p>
    </section>

    <p class="doc-nota-legal">
        Generado el {{ $generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i') }}.
        Menciona el código <strong>{{ $estimacion->codigo }}</strong> al escribirnos y un asesor
        retoma tu pedido sin que tengas que explicarlo de nuevo.
    </p>
@endsection
