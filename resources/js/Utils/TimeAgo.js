/**
 * "Hace X" en español, sin librería externa (el proyecto no tiene
 * dayjs/date-fns instalado, ver .ai/rules/js.md). Usado por el dropdown de
 * notificaciones del topbar y por Pages/Notificaciones/Index.vue.
 *
 * @param {string|null} iso Fecha en formato ISO 8601 (created_at de la notificación).
 * @returns {string}
 */
export function tiempoRelativo(iso) {
    if (!iso) {
        return '';
    }

    const segundos = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);

    if (segundos < 60) {
        return 'Hace un momento';
    }

    const minutos = Math.floor(segundos / 60);
    if (minutos < 60) {
        return `Hace ${minutos} minuto${minutos === 1 ? '' : 's'}`;
    }

    const horas = Math.floor(minutos / 60);
    if (horas < 24) {
        return `Hace ${horas} hora${horas === 1 ? '' : 's'}`;
    }

    const dias = Math.floor(horas / 24);
    if (dias === 1) {
        return 'Ayer';
    }
    if (dias < 7) {
        return `Hace ${dias} días`;
    }

    return new Date(iso).toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * Rótulo de un día para agrupar listas: "Hoy", "Ayer" o "lunes 6 de octubre"
 * (con el año solo si no es el actual). Trabaja con fechas `AAAA-MM-DD` ya
 * calculadas por el servidor en hora de la empresa, sin pasar por la zona
 * horaria del navegador: el mediodía UTC no cambia de día en ninguna zona.
 *
 * @param {string|null} dia Fecha AAAA-MM-DD.
 * @param {string|null} hoy Fecha AAAA-MM-DD de hoy, según el servidor.
 * @returns {string}
 */
export function etiquetaDia(dia, hoy) {
    if (!dia) {
        return '';
    }

    const fecha = new Date(`${dia}T12:00:00Z`);

    if (hoy) {
        const dias = Math.round((new Date(`${hoy}T12:00:00Z`) - fecha) / 86_400_000);

        if (dias === 0) {
            return 'Hoy';
        }
        if (dias === 1) {
            return 'Ayer';
        }
    }

    const mismoAnio = hoy && hoy.slice(0, 4) === dia.slice(0, 4);
    const texto = fecha.toLocaleDateString('es-BO', {
        timeZone: 'UTC',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        ...(mismoAnio ? {} : { year: 'numeric' }),
    });

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}
