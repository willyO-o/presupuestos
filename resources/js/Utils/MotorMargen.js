/**
 * Espejo en el navegador del motor de margen del backend
 * (App\Services\Calculo\MotorMargenService) para el panel EN VIVO del
 * formulario de cotización: mientras el vendedor teclea insumos, ve el precio
 * y el semáforo sin ir al servidor en cada tecla.
 *
 * IMPORTANTE: esto es solo previsualización. El precio, los impuestos y el
 * semáforo que se GUARDAN los calcula siempre el servidor
 * (CotizacionController::normalizarDetalles / calcularMontos), que ignora lo
 * que mande el navegador. Si esta función y el servicio PHP se separaran, la
 * verdad es la del servidor.
 *
 * Las tasas y umbrales no se escriben acá: llegan como prop `config` desde
 * `config/margen.php`, para que exista un único lugar donde cambiarlas.
 */

/**
 * @typedef {Object} ConfigMargen
 * @property {{ it: number, iue: number, iva: number }} impuestos
 * @property {{ umbral_verde: number, umbral_amarillo: number }} semaforo
 */

export const RECOMENDACIONES = {
    VERDE: 'ACEPTAR',
    AMARILLO: 'REVISAR PRECIO',
    ROJO: 'NO ACEPTAR',
};

export const CLASES_SEMAFORO = {
    VERDE: 'semaforo semaforo-verde',
    AMARILLO: 'semaforo semaforo-amarillo',
    ROJO: 'semaforo semaforo-rojo',
};

/**
 * Suma de los insumos de una línea (columna Subtotal de la hoja de costos).
 *
 * @param {Array<{cantidad: number|string, costo_unitario: number|string}>} items
 * @returns {number}
 */
export function costoBaseDe(items = []) {
    return items.reduce(
        (total, item) => total + Number(item.cantidad || 0) * Number(item.costo_unitario || 0),
        0,
    );
}

/**
 * Impuestos, utilidad y semáforo de un precio ya decidido. Todos los pasos
 * son lineales, así que también sirve para la cabecera (suma de líneas).
 *
 * @param {ConfigMargen} config
 * @param {number} costoAjustado
 * @param {number} precio
 * @param {number} instalacion
 */
export function evaluarMargen(config, costoAjustado, precio, instalacion = 0) {
    const { impuestos, semaforo } = config;

    const it = precio * impuestos.it;
    const utilidadAntesIue = precio - costoAjustado - it;
    const iue = utilidadAntesIue * impuestos.iue;
    const utilidadReal = utilidadAntesIue - iue;

    const iva = precio * impuestos.iva;
    const precioFinal = precio + iva;

    // Un ítem sin costo cargado (reventa pura) no es ROJO si tiene precio:
    // misma excepción que documenta MotorMargenService::semaforo().
    let estado;
    if (costoAjustado <= 0) {
        estado = precio > 0 ? 'VERDE' : 'ROJO';
    } else if (utilidadReal > semaforo.umbral_verde * costoAjustado) {
        estado = 'VERDE';
    } else if (utilidadReal > semaforo.umbral_amarillo * costoAjustado) {
        estado = 'AMARILLO';
    } else {
        estado = 'ROJO';
    }

    return {
        costoAjustado,
        precio,
        it,
        utilidadAntesIue,
        iue,
        utilidadReal,
        rentabilidad: costoAjustado > 0 ? utilidadReal / costoAjustado : 0,
        estado,
        recomendacion: RECOMENDACIONES[estado],
        iva,
        precioFinal,
        instalacion,
        totalPrecioFinal: precioFinal + instalacion,
    };
}

/**
 * Cadena completa desde el costo de los insumos: costo ajustado → precio →
 * impuestos → semáforo.
 *
 * @param {ConfigMargen} config
 * @param {number} costoBase
 * @param {number} factorComplejidad
 * @param {number} margen  Fracción (0.45), no porcentaje.
 * @param {number} instalacion
 */
export function calcularMargen(config, costoBase, factorComplejidad, margen, instalacion = 0) {
    const costoAjustado = Number(costoBase || 0) * Number(factorComplejidad || 1);

    return {
        costoBase: Number(costoBase || 0),
        factorComplejidad: Number(factorComplejidad || 1),
        margen: Number(margen || 0),
        ...evaluarMargen(config, costoAjustado, costoAjustado * (1 + Number(margen || 0)), instalacion),
    };
}
