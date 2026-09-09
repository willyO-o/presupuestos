{{--
    Importe en bolivianos con el formato local (miles con punto, decimales con
    coma). Vive como componente porque aparece decenas de veces repartido por
    los seis documentos: un `number_format` copiado en cada celda es una
    invitación a que un día uno quede con el punto y la coma al revés.

    - `moneda` en false para las celdas de una tabla, donde el "Bs" ya está en
      la cabecera de la columna y repetirlo solo ensucia.
--}}
@props(['valor', 'moneda' => true])

<span class="num">{{ $moneda ? 'Bs ' : '' }}{{ number_format((float) $valor, 2, ',', '.') }}</span>
