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
