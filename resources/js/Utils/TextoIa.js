/**
 * Convierte el texto de una respuesta de IA en HTML SEGURO para `v-html`.
 *
 * Primero se escapa TODO el HTML (el texto puede citar nombres de clientes
 * o productos de la base, y un modelo puede devolver etiquetas). Después se
 * admite un subconjunto mínimo de Markdown: **negrita**, listas con "-" o
 * "1." y párrafos. Nada de enlaces ni imágenes: una respuesta del asistente
 * no debe poder sacar al usuario del sistema.
 */

function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

function enLinea(texto) {
    return texto.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}

export function textoIaAHtml(texto) {
    const bloques = escapar(texto).trim().split(/\n{2,}/);

    return bloques.map((bloque) => {
        const lineas = bloque.split('\n').map((l) => l.trim()).filter(Boolean);

        if (lineas.length && lineas.every((l) => /^[-•*]\s+/.test(l))) {
            return `<ul>${lineas.map((l) => `<li>${enLinea(l.replace(/^[-•*]\s+/, ''))}</li>`).join('')}</ul>`;
        }

        if (lineas.length && lineas.every((l) => /^\d+[.)]\s+/.test(l))) {
            return `<ol>${lineas.map((l) => `<li>${enLinea(l.replace(/^\d+[.)]\s+/, ''))}</li>`).join('')}</ol>`;
        }

        return `<p>${enLinea(lineas.join('<br>'))}</p>`;
    }).join('');
}
