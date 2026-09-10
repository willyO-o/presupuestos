<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { colorSerie, conAlfa, useChartTheme } from '@/Composables/UseChartTheme.js';

/**
 * Gráfico de líneas o barras sobre Chart.js 4.
 *
 * Es el ÚNICO lugar del panel donde se instancia un Chart: acá viven el tema,
 * las medidas de las marcas, el tooltip, la leyenda, la vista de tabla y la
 * exportación. Una página solo declara sus datos.
 *
 * **Chart.js se carga bajo demanda** (`import()` dentro de `onMounted`), así
 * que Vite lo deja en su propio chunk y no engorda `app.js`: las pantallas que
 * no tienen gráficos no lo descargan.
 *
 * **Qué NO es configurable, a propósito** (son las reglas que hacen que los
 * gráficos se lean parecido en todo el sistema): el grosor de las marcas, el
 * color de las series —se asignan en orden desde `--chart-1..6`, nunca se
 * ciclan—, la grilla fina y sólida, y que el texto use tokens de texto y nunca
 * el color de la serie. La identidad la lleva el punto de color al lado de la
 * etiqueta, no la etiqueta pintada.
 *
 * **Toda serie es alcanzable sin ver colores**: hay leyenda a partir de dos
 * series y siempre una vista de tabla con los mismos números. El tooltip
 * agrega, no habilita: nunca es la única forma de leer un valor.
 */

const props = defineProps({
    /** 'line' | 'bar' */
    tipo: { type: String, default: 'line' },
    /** Etiquetas del eje de categorías. */
    labels: { type: Array, required: true },
    /**
     * Series a dibujar, en orden. Cada una:
     * `{ nombre, datos, tipo?, area?, punteada?, colores? }`.
     *
     * `colores` pinta cada barra por separado y existe SOLO para polaridad o
     * estado (margen positivo/negativo, cumple/no cumple). No lo uses para
     * teñir barras según su valor: el largo de la barra ya dice cuánto, y
     * gastar el color en repetirlo deja sin canal a la identidad.
     * @type {import('vue').PropType<Array<{nombre: string, datos: Array<number|null>, tipo?: string, area?: boolean, punteada?: boolean, colores?: string[]}>>}
     */
    series: { type: Array, required: true },
    /** Formatea valores en ejes, tooltip y tabla. */
    formato: { type: Function, default: (v) => v },
    /** Nombre del gráfico: rotula la tabla y nombra el PNG exportado. */
    titulo: { type: String, required: true },
    /** Alto del área de dibujo en px (sin contar la banda del eje X). */
    alto: { type: Number, default: 260 },
    /** Barras horizontales: úsalo cuando las categorías tienen nombres largos. */
    horizontal: { type: Boolean, default: false },
    /** Apila las series (parte-de-un-todo). */
    apilado: { type: Boolean, default: false },
    /**
     * Une la línea por encima de los huecos (`null`).
     *
     * Por defecto NO: un mes sin dato es un mes sin dato, y cerrar el hueco
     * inventaría una medición. Se activa cuando el hueco significa "siguió
     * igual" y no "no se sabe" — el caso del precio de un material, que se
     * mantiene hasta la compra siguiente.
     */
    unirHuecos: { type: Boolean, default: false },
    /** Texto cuando no hay nada que graficar. */
    vacio: { type: String, default: 'Sin datos para este periodo.' },
});

const lienzo = ref(null);
const grafico = shallowRef(null);
const verTabla = ref(false);
const { tema } = useChartTheme();

const hayDatos = computed(
    () => props.labels.length > 0 && props.series.some((s) => s.datos?.some((v) => Number.isFinite(v))),
);

/** Colores resueltos por serie, en el orden en que llegan. */
const coloresSerie = computed(() => props.series.map((_, i) => colorSerie(tema.value, i)));

/* -------------------------------------------------------------------------
 * Construcción de la configuración de Chart.js
 * ---------------------------------------------------------------------- */

/**
 * Las medidas de las marcas salen de acá y de ningún otro lado.
 *
 * Línea de 2 px; puntos de al menos 8 px de diámetro con un anillo del color
 * de la superficie para que se sigan viendo donde dos series se cruzan; barras
 * de 24 px como máximo (la banda sobrante es aire, no barra más gorda) con la
 * punta redondeada 4 px y el arranque cuadrado sobre la línea base.
 */
function datasetDe(serie, indice) {
    const color = coloresSerie.value[indice];
    const superficie = tema.value?.superficie ?? '#ffffff';
    const tipo = serie.tipo ?? props.tipo;
    // Con muchos puntos, un marcador por dato se convierte en una cuerda de
    // cuentas: se ocultan y solo aparece el del hover.
    const puntosVisibles = props.labels.length <= 24;

    if (tipo === 'bar') {
        return {
            type: 'bar',
            label: serie.nombre,
            data: serie.datos,
            backgroundColor: serie.colores ?? color,
            maxBarThickness: 24,
            borderRadius: 4,
            borderSkipped: 'start',
            // El separador entre segmentos apilados es un hueco del color de la
            // superficie, no un borde dibujado: un borde agrega tinta que no es
            // dato. Chart.js lo consigue con un "borde" de 2 px de ese color.
            ...(props.apilado
                ? { borderColor: superficie, borderWidth: props.horizontal ? { right: 2 } : { top: 2 } }
                : {}),
        };
    }

    return {
        type: 'line',
        label: serie.nombre,
        data: serie.datos,
        borderColor: color,
        borderWidth: 2,
        borderDash: serie.punteada ? [5, 4] : undefined,
        // El área es un lavado del mismo tono, nunca un bloque saturado.
        fill: serie.area ? { target: 'origin', above: conAlfa(color, 0.1) } : false,
        tension: 0,
        spanGaps: props.unirHuecos,
        pointRadius: puntosVisibles ? 4 : 0,
        pointHoverRadius: 6,
        pointBackgroundColor: color,
        pointBorderColor: superficie,
        pointBorderWidth: 2,
        pointHoverBorderWidth: 2,
    };
}

function ejeValor() {
    return {
        stacked: props.apilado,
        beginAtZero: true,
        border: { display: false },
        grid: {
            color: tema.value?.grid,
            lineWidth: 1,
            drawTicks: false,
            // Grilla sólida: el punteado se lee como "proyección" o "umbral"
            // cuando solo es una guía.
            borderDash: [],
        },
        ticks: {
            color: tema.value?.eje,
            font: { size: 11 },
            padding: 8,
            maxTicksLimit: 6,
            callback: (v) => props.formato(v),
        },
    };
}

function ejeCategoria() {
    return {
        stacked: props.apilado,
        border: { display: false },
        // Sin grilla en el eje de categorías: duplica la información de las
        // marcas y ensucia.
        grid: { display: false },
        ticks: {
            color: tema.value?.eje,
            font: { size: 11 },
            padding: 6,
            autoSkip: true,
            maxRotation: 0,
        },
    };
}

/**
 * Línea guía vertical bajo el cursor.
 *
 * Chart.js no la trae: es lo que convierte un tooltip en una lectura del punto
 * exacto cuando hay varias series encimadas.
 */
const guia = {
    id: 'guiaVertical',
    afterDatasetsDraw(chart) {
        const activos = chart.tooltip?.getActiveElements?.() ?? [];

        if (!activos.length || chart.options.indexAxis === 'y') {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = activos[0].element.x;

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = tema.value?.grid ?? 'rgba(0,0,0,.1)';
        ctx.stroke();
        ctx.restore();
    },
};

/**
 * Pinta la superficie antes que los datos.
 *
 * Un canvas es transparente: sin esto el PNG exportado sale con fondo negro en
 * cualquier visor que componga sobre oscuro.
 */
const fondo = {
    id: 'fondoOpaco',
    beforeDraw(chart) {
        const { ctx } = chart;
        ctx.save();
        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = tema.value?.superficie ?? '#ffffff';
        ctx.fillRect(0, 0, chart.width, chart.height);
        ctx.restore();
    },
};

function configuracion() {
    return {
        type: props.tipo,
        data: {
            labels: props.labels,
            datasets: props.series.map(datasetDe),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: props.horizontal ? 'y' : 'x',
            layout: { padding: { top: 8, right: 8 } },
            // 'index' + sin exigir acierto exacto: el área sensible es toda la
            // columna, no el píxel del punto.
            interaction: { mode: props.horizontal ? 'nearest' : 'index', intersect: false, axis: 'x' },
            scales: props.horizontal
                ? { x: ejeValor(), y: ejeCategoria() }
                : { x: ejeCategoria(), y: ejeValor() },
            plugins: {
                // La leyenda se dibuja en HTML (abajo): se puede seleccionar,
                // la lee un lector de pantalla y usa tokens de texto.
                legend: { display: false },
                tooltip: {
                    backgroundColor: tema.value?.texto,
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: true,
                    usePointStyle: true,
                    boxPadding: 4,
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${props.formato(ctx.parsed[props.horizontal ? 'x' : 'y'])}`,
                    },
                },
            },
        },
        plugins: [guia, fondo],
    };
}

/* -------------------------------------------------------------------------
 * Ciclo de vida
 * ---------------------------------------------------------------------- */

async function montar() {
    if (!hayDatos.value || !lienzo.value) {
        return;
    }

    const {
        Chart, LineController, BarController, LineElement, PointElement, BarElement,
        LinearScale, CategoryScale, Tooltip, Filler,
    } = await import('chart.js');

    Chart.register(
        LineController, BarController, LineElement, PointElement, BarElement,
        LinearScale, CategoryScale, Tooltip, Filler,
    );

    // El componente pudo desmontarse mientras se descargaba el chunk.
    if (!lienzo.value) {
        return;
    }

    grafico.value = new Chart(lienzo.value, configuracion());
}

function redibujar() {
    if (!grafico.value) {
        montar();

        return;
    }

    const nueva = configuracion();
    grafico.value.data = nueva.data;
    grafico.value.options = nueva.options;
    grafico.value.update();
}

onMounted(montar);

onBeforeUnmount(() => {
    grafico.value?.destroy();
    grafico.value = null;
});

// El tema entra acá: al pasar a oscuro se rehacen colores de ejes y grilla.
watch([tema, () => props.series, () => props.labels], async () => {
    await nextTick();
    redibujar();
}, { deep: true });

watch(hayDatos, async (hay) => {
    if (hay) {
        await nextTick();
        montar();
    }
});

/* -------------------------------------------------------------------------
 * Exportar
 * ---------------------------------------------------------------------- */

function exportarPng() {
    if (!grafico.value) {
        return;
    }

    const enlace = document.createElement('a');
    enlace.href = grafico.value.toBase64Image('image/png', 1);
    enlace.download = `${props.titulo.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}.png`;
    enlace.click();
}
</script>

<template>
    <div class="chart">
        <div v-if="hayDatos" class="chart-toolbar">
            <!-- Leyenda: presente desde dos series. Con una sola, el título de
                 la tarjeta ya dice qué se está mirando y una caja con un solo
                 cuadrito solo gasta espacio. -->
            <ul v-if="series.length > 1" class="chart-legend">
                <li v-for="(s, i) in series" :key="s.nombre" class="chart-legend-item">
                    <span class="chart-legend-key" :style="{ backgroundColor: coloresSerie[i] }"></span>
                    {{ s.nombre }}
                </li>
            </ul>
            <span v-else></span>

            <div class="chart-actions">
                <button type="button" class="chart-action" :class="{ 'is-active': verTabla }"
                    :aria-pressed="verTabla" @click="verTabla = !verTabla">
                    <i class="fa-solid fa-table-list"></i>
                    <span class="chart-action-text">Tabla</span>
                </button>
                <button type="button" class="chart-action" @click="exportarPng">
                    <i class="fa-solid fa-download"></i>
                    <span class="chart-action-text">PNG</span>
                </button>
            </div>
        </div>

        <p v-if="!hayDatos" class="chart-empty">{{ vacio }}</p>

        <div v-show="hayDatos && !verTabla" class="chart-canvas" :style="{ height: `${alto}px` }">
            <canvas ref="lienzo" role="img" :aria-label="titulo"></canvas>
        </div>

        <!-- Gemela accesible del gráfico: los mismos números sin depender del
             color ni del hover. -->
        <div v-if="verTabla && hayDatos" class="table-responsive">
            <table class="table-dashboard table-sm">
                <caption class="chart-table-caption">{{ titulo }}</caption>
                <thead>
                    <tr>
                        <th></th>
                        <th v-for="s in series" :key="s.nombre" class="text-end">{{ s.nombre }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(lbl, fila) in labels" :key="lbl">
                        <td>{{ lbl }}</td>
                        <td v-for="s in series" :key="s.nombre" class="text-end chart-table-num">
                            {{ s.datos[fila] === null || s.datos[fila] === undefined ? '—' : formato(s.datos[fila]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
