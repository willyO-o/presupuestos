/**
 * Descripciones de líneas de cotización / pedido / nota de entrega.
 *
 * La descripción de un exhibidor ocupa varios párrafos (estructura, acabado,
 * impresión). Los documentos la muestran completa (`.text-prewrap` respeta
 * los saltos); los títulos, tablas internas y listados usan el primer
 * renglón, que hace de "título" del ítem.
 */

const SALTO = /\r?\n/;

/**
 * @param {string|null} texto
 * @returns {string} El primer renglón ("Elaborado de Cabecera").
 */
export function primerRenglon(texto) {
    return String(texto ?? '').split(SALTO)[0];
}

/**
 * @param {string|null} texto
 * @returns {string} Todo lo que sigue al primer renglón (el detalle del ítem).
 */
export function sinPrimerRenglon(texto) {
    const [, ...resto] = String(texto ?? '').split(SALTO);

    return resto.join('\n');
}

/**
 * @param {string|null} texto
 * @returns {boolean} Si tiene más de un renglón.
 */
export function esMultilinea(texto) {
    return SALTO.test(String(texto ?? ''));
}
