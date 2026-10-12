/**
 * Formatos de números de los reportes de inteligencia de negocios.
 *
 * Una sola definición para las pestañas del BI: un mismo monto no puede verse
 * "Bs 12.400" en una tarjeta y "12400.00" en el tooltip de al lado.
 * Configuración regional `es-BO` (punto de miles, coma decimal).
 */

const LOCALE = 'es-BO';

/** Importe con centavos: tablas, tarjetas, tooltips. */
export function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Importe sin centavos: en los ejes, si no, las etiquetas se pisan. */
export function moneyCorto(value) {
    return `Bs ${Number(value ?? 0).toLocaleString(LOCALE, { maximumFractionDigits: 0 })}`;
}

/** Cantidad con hasta `decimales` decimales. */
export function numero(value, decimales = 1) {
    return Number(value ?? 0).toLocaleString(LOCALE, { maximumFractionDigits: decimales });
}

/** Porcentaje ya expresado en 0-100 (no fracción). */
export function porcentaje(value, decimales = 1) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${Number(value).toLocaleString(LOCALE, { maximumFractionDigits: decimales })} %`;
}

/** Variación con signo explícito: "+3,2 %" / "−1,5 %". */
export function variacion(value, decimales = 1) {
    if (value === null || value === undefined) {
        return '—';
    }

    const signo = value > 0 ? '+' : value < 0 ? '−' : '';

    return `${signo}${Math.abs(Number(value)).toLocaleString(LOCALE, { maximumFractionDigits: decimales })} %`;
}

/**
 * Formateador por nombre, para datos que llegan del servidor con su formato
 * declarado (resultados de las herramientas del asistente, indicadores del
 * resumen semanal): 'bs' | 'numero' | 'porcentaje' | 'variacion' | 'cantidad'.
 */
export function formateador(formato) {
    return {
        bs: moneyCorto,
        bs_exacto: money,
        numero: (v) => numero(v, 1),
        cantidad: (v) => numero(v, 0),
        porcentaje: (v) => porcentaje(v),
        variacion: (v) => variacion(v),
    }[formato] ?? ((v) => (v === null || v === undefined ? '—' : String(v)));
}
