<?php

namespace App\Services\Calculo;

use App\Models\TipoProyecto;

/**
 * Motor de margen automático: reimplementación exacta del Excel
 * `11_Sistema_Margen_Automatico_Xtrapubli` que la empresa usaba para
 * presupuestar (las 5 hojas — Muebles Exhibidores, Islas Cabeceras, Sistema
 * Letreros Luminosos, Trabajos Especiales y Bastidores — comparten este mismo
 * motor; lo único que cambiaba entre hojas era la lista de insumos y si
 * llevaban o no una línea de instalación).
 *
 * Función PURA: no toca la base de datos ni la request. Recibe números y
 * devuelve un ResultadoMargen. Es el único lugar donde vive esta secuencia —
 * el controlador de cotizaciones, los seeders y el frontend consumen su
 * resultado, no la reimplementan.
 *
 * Secuencia (idéntica a la hoja de cálculo):
 *   costo ajustado = costo base × factor de complejidad
 *   precio         = costo ajustado × (1 + margen mínimo)
 *   IT             = precio × 3 %
 *   utilidad a/IUE = precio − costo ajustado − IT
 *   IUE            = utilidad a/IUE × 25 %
 *   utilidad real  = utilidad a/IUE − IUE
 *   semáforo       = utilidad real vs. 30 % / 15 % del COSTO AJUSTADO
 *   IVA            = precio × 13 %
 *   precio final   = precio + IVA
 *   total          = precio final + instalación (monto manual, sin impuestos)
 *
 * Las tasas y los umbrales viven en `config/margen.php`, el factor y el margen
 * mínimo en la tabla `tipo_proyecto` (CRUD, App\Models\TipoProyecto).
 */
class MotorMargenService
{
    public const ESTADOS = ['VERDE', 'AMARILLO', 'ROJO'];

    /**
     * Recomendación comercial que acompaña a cada color del semáforo.
     */
    public const RECOMENDACIONES = [
        'VERDE' => 'ACEPTAR',
        'AMARILLO' => 'REVISAR PRECIO',
        'ROJO' => 'NO ACEPTAR',
    ];

    /**
     * Cadena completa del Excel a partir del costo de los insumos y el nivel
     * de complejidad del trabajo.
     */
    public function calcular(
        float $costoBase,
        float $factorComplejidad,
        float $margen,
        float $instalacion = 0.0,
    ): ResultadoMargen {
        $costoAjustado = $costoBase * $factorComplejidad;

        return $this->evaluar(
            costoBase: $costoBase,
            costoAjustado: $costoAjustado,
            precio: $costoAjustado * (1 + $margen),
            factorComplejidad: $factorComplejidad,
            margen: $margen,
            instalacion: $instalacion,
        );
    }

    /**
     * Igual que `calcular()`, tomando el factor y el margen de un tipo de
     * proyecto del CRUD. Sin tipo (null) el trabajo se cotiza sin recargo de
     * complejidad y con el margen sugerido por defecto
     * (`config/cotizacion.php`), para que una línea suelta nunca quede sin
     * precio.
     */
    public function calcularCon(
        ?TipoProyecto $tipoProyecto,
        float $costoBase,
        float $instalacion = 0.0,
    ): ResultadoMargen {
        return $this->calcular(
            costoBase: $costoBase,
            factorComplejidad: (float) ($tipoProyecto?->factor_complejidad ?? 1.0),
            margen: (float) ($tipoProyecto?->margen_minimo ?? config('cotizacion.margen_sugerido', 0.45)),
            instalacion: $instalacion,
        );
    }

    /**
     * Impuestos, utilidad y semáforo de un precio YA decidido — el caso del
     * vendedor que sobreescribe el precio sugerido, y el de la cabecera de la
     * cotización, que suma varias líneas (todos los pasos del motor son
     * lineales, así que evaluar la suma equivale a sumar las evaluaciones).
     */
    public function evaluar(
        float $costoBase,
        float $costoAjustado,
        float $precio,
        float $factorComplejidad = 1.0,
        ?float $margen = null,
        float $instalacion = 0.0,
    ): ResultadoMargen {
        $impuestos = config('margen.impuestos');

        $it = $precio * $impuestos['it'];
        $utilidadAntesIue = $precio - $costoAjustado - $it;
        $iue = $utilidadAntesIue * $impuestos['iue'];
        $utilidadReal = $utilidadAntesIue - $iue;

        $iva = $precio * $impuestos['iva'];
        $precioFinal = $precio + $iva;

        $estado = $this->semaforo($utilidadReal, $costoAjustado, $precio);

        return new ResultadoMargen(
            costoBase: $costoBase,
            costoAjustado: $costoAjustado,
            factorComplejidad: $factorComplejidad,
            margen: $margen ?? ($costoAjustado > 0 ? ($precio / $costoAjustado) - 1 : 0.0),
            precio: $precio,
            it: $it,
            utilidadAntesIue: $utilidadAntesIue,
            iue: $iue,
            utilidadReal: $utilidadReal,
            estado: $estado,
            recomendacion: self::RECOMENDACIONES[$estado],
            iva: $iva,
            precioFinal: $precioFinal,
            instalacion: $instalacion,
            totalPrecioFinal: $precioFinal + $instalacion,
        );
    }

    /**
     * Color del semáforo. La utilidad real se compara contra el COSTO
     * AJUSTADO, no contra el precio — así lo hace la hoja original.
     *
     * Caso que el Excel no contempla: un ítem sin costo cargado (reventa
     * pura, o insumos todavía sin llenar). Ahí la comparación contra 0 daría
     * siempre ROJO aunque el precio sea puro margen, así que se resuelve por
     * el precio: con precio > 0 es VERDE, sin precio es ROJO.
     */
    private function semaforo(float $utilidadReal, float $costoAjustado, float $precio): string
    {
        if ($costoAjustado <= 0.0) {
            return $precio > 0.0 ? 'VERDE' : 'ROJO';
        }

        $umbrales = config('margen.semaforo');

        return match (true) {
            $utilidadReal > $umbrales['umbral_verde'] * $costoAjustado => 'VERDE',
            $utilidadReal > $umbrales['umbral_amarillo'] * $costoAjustado => 'AMARILLO',
            default => 'ROJO',
        };
    }
}
