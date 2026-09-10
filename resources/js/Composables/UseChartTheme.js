import { onBeforeUnmount, ref, shallowRef } from 'vue';

/**
 * Colores de los gráficos, leídos de los tokens CSS y reactivos al tema.
 *
 * Chart.js dibuja en un `<canvas>`: no entiende `var(--chart-1)`, hay que
 * pasarle un color resuelto. Por eso el tema se lee con `getComputedStyle` del
 * `<html>` y se vuelve a leer cuando cambia la clase `.dark` (la pone
 * `Components/Layout/Topbar.vue`). Sin esto, al cambiar a modo oscuro las
 * etiquetas de los ejes quedan en gris claro sobre fondo oscuro.
 *
 * El observador y el estado son ÚNICOS por página aunque haya seis gráficos:
 * son seis suscriptores a un mismo `MutationObserver`, no seis observadores.
 *
 * La paleta de series (`--chart-1..6`) está validada — ver el comentario en
 * `app.css`. Acá solo se lee: no se generan colores ni se ciclan.
 */

/** @typedef {{series: string[], grid: string, eje: string, superficie: string, texto: string, positivo: string, negativo: string}} TemaGrafico */

/** @type {import('vue').ShallowRef<TemaGrafico|null>} */
const tema = shallowRef(null);

const oscuro = ref(false);

let observador = null;
let suscriptores = 0;

function token(nombre) {
    return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
}

function leer() {
    tema.value = {
        series: [1, 2, 3, 4, 5, 6].map((n) => token(`--chart-${n}`)),
        grid: token('--chart-grid'),
        eje: token('--chart-axis'),
        // Superficie de la tarjeta: es contra la que se validó el contraste de
        // la paleta, y la que separa marcas que se tocan (ver `--chart-grid`).
        superficie: token('--card-bg'),
        texto: token('--text-heading'),
        // Estado, NO identidad: solo para series que de verdad significan
        // bien/mal (un margen por encima o por debajo de cero). Usarlos como
        // "serie 7" haría que un dato neutro pareciera bueno o malo.
        positivo: token('--c-success'),
        negativo: token('--c-danger'),
    };

    oscuro.value = document.documentElement.classList.contains('dark');
}

/**
 * Devuelve el tema de gráficos y lo mantiene al día mientras el componente viva.
 *
 * @returns {{tema: import('vue').ShallowRef<TemaGrafico|null>, oscuro: import('vue').Ref<boolean>}}
 */
export function useChartTheme() {
    if (suscriptores === 0) {
        leer();

        observador = new MutationObserver(leer);
        observador.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }

    suscriptores++;

    onBeforeUnmount(() => {
        suscriptores--;

        if (suscriptores === 0) {
            observador?.disconnect();
            observador = null;
        }
    });

    return { tema, oscuro };
}

/**
 * Color de la serie `indice`, asignado en secuencia y SIN ciclar.
 *
 * A partir de la séptima serie devuelve el gris de eje en vez de repetir un
 * color: dos series del mismo color mienten sobre la identidad, y un séptimo
 * tono inventado es indistinguible de otro bajo daltonismo. Si un gráfico
 * llega acá, lo que hay que hacer es agrupar la cola en "Otros" o partirlo.
 *
 * @param {TemaGrafico|null} tema
 * @param {number} indice
 */
export function colorSerie(tema, indice) {
    if (!tema) {
        return '#068cdd';
    }

    return tema.series[indice] ?? tema.eje;
}

/** Mismo color con transparencia, para el relleno de un área (10%). */
export function conAlfa(color, alfa) {
    const hex = color.replace('#', '');

    if (hex.length !== 6) {
        return color;
    }

    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));

    return `rgba(${r}, ${g}, ${b}, ${alfa})`;
}
