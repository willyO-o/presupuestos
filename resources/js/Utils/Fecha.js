/**
 * Fechas de calendario (sin hora) en hora LOCAL del navegador.
 *
 * Los campos `date` de los modelos se serializan como 'YYYY-MM-DD'
 * (cast `date:Y-m-d`). `new Date('2026-11-01')` interpreta ese texto como
 * medianoche UTC y, en Bolivia (UTC-4), lo muestra como 31/10: un día
 * menos. Del mismo modo, `new Date().toISOString().slice(0, 10)` da la
 * fecha UTC, que después de las 20:00 ya es "mañana". Estas funciones
 * arman la fecha con sus partes locales para evitar ambos corrimientos.
 *
 * Los timestamps reales (created_at, '...T14:30:00.000000Z') sí son un
 * instante y se siguen convirtiendo a hora local con `new Date()`.
 */

const SOLO_FECHA = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * @param {string|Date|null} valor 'YYYY-MM-DD', timestamp ISO o Date.
 * @returns {Date|null}
 */
export function aFecha(valor) {
    if (!valor) {
        return null;
    }

    if (valor instanceof Date) {
        return valor;
    }

    const partes = SOLO_FECHA.exec(String(valor));
    const fecha = partes ? new Date(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])) : new Date(valor);

    return Number.isNaN(fecha.getTime()) ? null : fecha;
}

/**
 * Fecha legible en es-BO. Sin opciones: '01/11/2026'.
 *
 * @param {string|Date|null} valor
 * @param {Intl.DateTimeFormatOptions} [opciones]
 * @returns {string} '—' si no hay fecha.
 */
export function formatearFecha(valor, opciones = undefined) {
    const fecha = aFecha(valor);

    return fecha ? fecha.toLocaleDateString('es-BO', opciones) : '—';
}

/**
 * 'YYYY-MM-DD' de una fecha en hora local (valor para <input type="date">).
 *
 * @param {Date} fecha
 * @returns {string}
 */
export function aYmd(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${fecha.getFullYear()}-${mes}-${dia}`;
}

/**
 * Hoy (más `dias`, opcional) como 'YYYY-MM-DD' local.
 *
 * @param {number} [dias]
 * @returns {string}
 */
export function hoyYmd(dias = 0) {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + dias);

    return aYmd(fecha);
}
