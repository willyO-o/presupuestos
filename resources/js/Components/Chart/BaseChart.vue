<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { colorSerie, conAlfa, useChartTheme } from '@/Composables/UseChartTheme.js';

/**
 * Gráfico de líneas o barras sobre Apache ECharts 6.
 *
 * Es el ÚNICO lugar del panel donde se instancia un gráfico: acá viven el tema,
 * las medidas de las marcas, el tooltip, la leyenda, la vista de tabla y la
 * exportación. Una página solo declara sus datos.
 *
 * **ECharts se carga bajo demanda** (`import()` de `EchartsCore.js` dentro de
 * `onMounted`), así que Vite lo deja en su propio chunk y no engorda `app.js`:
 * las pantallas que no tienen gráficos no lo descargan. `EchartsCore.js`
 * registra solo los tipos que se usan (línea, barra) para no traer la
 * librería entera.
 *
 * **Qué NO es configurable, a propósito** (son las reglas que hacen que los
 * gráficos se lean parecido en todo el sistema): el grosor de las marcas, el
 * color de las series —se asignan en orden desde `--chart-1..6`, nunca se
 * ciclan—, la grilla fina y sólida, y que el texto use tokens de texto y nunca
 * el color de la serie. La identidad la lleva el punto de color al lado de la
 * etiqueta, no la etiqueta pintada.
 *
 * **Toda serie es alcanzable sin ver colores**: hay leyenda a partir de dos
 * series (y cada entrada prende/apaga su serie) y siempre una vista de tabla
 * con los mismos números. El tooltip agrega, no habilita: nunca es la única
 * forma de leer un valor.
 */

const props = defineProps({
    /** 'line' | 'bar' */
    tipo: { type: String, default: 'line' },
    /** Etiquetas del eje de categorías. */
    labels: { type: Array, required: true },
    /**
     * Series a dibujar, en orden. Cada una:
     * `{ nombre, datos, tipo?, area?, punteada?, colores?, eje? }`.
     *
     * `colores` pinta cada barra por separado y existe SOLO para polaridad o
     * estado (margen positivo/negativo, cumple/no cumple). No lo uses para
     * teñir barras según su valor: el largo de la barra ya dice cuánto, y
     * gastar el color en repetirlo deja sin canal a la identidad.
     *
     * `eje: 'derecho'` la mide contra un segundo eje (ver `formatoDerecho`):
     * para cruzar una cantidad con un monto o un porcentaje en el mismo tiempo.
     *
     * **Banda** (`tipo: 'banda'`, con `inferior` y `superior` en vez de
     * `datos`): el rango probable de una proyección. Va justo DESPUÉS de la
     * serie que acompaña y toma su color en un lavado suave — no gasta un
     * color de la paleta.
     * @type {import('vue').PropType<Array<{nombre: string, datos?: Array<number|null>, tipo?: string, area?: boolean, punteada?: boolean, colores?: string[], eje?: string, inferior?: Array<number|null>, superior?: Array<number|null>}>>}
     */
    series: { type: Array, required: true },
    /** Formatea valores en ejes, tooltip y tabla. */
    formato: { type: Function, default: (v) => v },
    /** Formato del eje derecho y de sus series. Sin él, se usa `formato`. */
    formatoDerecho: { type: Function, default: null },
    /** Nombre del gráfico: rotula la tabla y nombra el PNG exportado. */
    titulo: { type: String, required: true },
    /** Alto del área de dibujo en px (incluye la banda del eje X). */
    alto: { type: Number, default: 260 },
    /** Barras horizontales: úsalo cuando las categorías tienen nombres largos. */
    horizontal: { type: Boolean, default: false },
    /**
     * Etiquetas del eje X en diagonal y recortadas, para muchas categorías
     * con nombre largo en barras verticales (el Pareto de clientes). El
     * nombre completo sigue en el tooltip y en la vista de tabla.
     */
    etiquetasInclinadas: { type: Boolean, default: false },
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
    /**
     * Líneas de referencia horizontales (verticales si `horizontal`):
     * `[{ valor, nombre, eje? }]`. Para umbrales con significado —los del
     * semáforo de margen—, nunca como decoración.
     */
    umbrales: { type: Array, default: () => [] },
    /**
     * Etiqueta del eje desde la que empieza el futuro. Sombrea esa zona y la
     * rotula "Proyección": lo que todavía no pasó no puede distinguirse solo
     * por una línea punteada.
     */
    inicioProyeccion: { type: String, default: null },
    /**
     * El eje de valores cuenta cosas enteras (pedidos, presupuestos): sin
     * esto ECharts reparte pasos de 0,5 y, redondeados, se leen repetidos.
     */
    enteros: { type: Boolean, default: false },
    /**
     * El eje de valores no arranca en cero sino cerca del mínimo. SOLO para
     * índices y precios, donde lo que importa es la variación (un índice que
     * va de 100 a 106 desde cero es una línea plana). Nunca en barras: el
     * largo de una barra tiene que ser proporcional a su valor.
     */
    ajustarEscala: { type: Boolean, default: false },
    /** Texto cuando no hay nada que graficar. */
    vacio: { type: String, default: 'Sin datos para este periodo.' },
    /**
     * Formato del tooltip y de la tabla, cuando el eje usa uno compacto
     * ("Bs 574 mil" en el eje, "Bs 573.908" al pasar el mouse). Sin él,
     * se usa `formato`.
     */
    formatoTooltip: { type: Function, default: null },
});

/**
 * `seleccionar` ({ indice, etiqueta }) al hacer clic en un punto o barra:
 * el drill-down del Panorama. Solo si la página lo escucha el cursor cambia
 * a "mano"; en el resto de los gráficos un clic no hace nada.
 */
const emit = defineEmits(['seleccionar']);
// La instancia se toma acá, en el setup: dentro del computed (que se evalúa
// más tarde, al armar las opciones) `getCurrentInstance()` ya es null.
const instancia = getCurrentInstance();
const escuchaSeleccion = computed(() => Boolean(instancia?.vnode.props?.onSeleccionar));

const contenedor = ref(null);
const verTabla = ref(false);
/** Series apagadas desde la leyenda, por nombre. */
const ocultas = reactive(new Set());
const { tema } = useChartTheme();

let grafico = null;
let observador = null;

function valoresDe(serie) {
    return serie.tipo === 'banda' ? (serie.superior ?? []) : (serie.datos ?? []);
}

const hayDatos = computed(
    () => props.labels.length > 0 && props.series.some((s) => valoresDe(s).some((v) => Number.isFinite(v))),
);

/**
 * Colores resueltos por serie, en el orden en que llegan. Una banda toma el
 * color de la serie anterior y no avanza la paleta.
 */
const coloresSerie = computed(() => {
    let siguiente = 0;
    const colores = [];

    props.series.forEach((serie, i) => {
        if (serie.tipo === 'banda' && i > 0) {
            colores.push(colores[i - 1]);

            return;
        }

        // Referencia (el periodo anterior, el total de fondo): gris, para que
        // el color fuerte quede solo en lo que se quiere mirar.
        if (serie.neutra) {
            colores.push(conAlfa(tema.value?.eje ?? '#878a99', 0.55));

            return;
        }

        colores.push(colorSerie(tema.value, siguiente));
        siguiente++;
    });

    return colores;
});

const hayEjeDerecho = computed(() => props.series.some((s) => s.eje === 'derecho'));

function formatoDe(serie) {
    return serie?.eje === 'derecho' && props.formatoDerecho ? props.formatoDerecho : props.formato;
}

/** Tooltip y tabla: el detalle (`formatoTooltip`) en las series del eje izquierdo. */
function formatoDetalle(serie) {
    return serie?.eje !== 'derecho' && props.formatoTooltip ? props.formatoTooltip : formatoDe(serie);
}

function alternar(nombre) {
    if (ocultas.has(nombre)) {
        ocultas.delete(nombre);
    } else {
        ocultas.add(nombre);
    }
}

/** El tooltip es HTML: los nombres vienen de la base (productos, materiales). */
function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

/* -------------------------------------------------------------------------
 * Construcción de la opción de ECharts
 * ---------------------------------------------------------------------- */

/**
 * Barra destacada (`serie.destacar`, un índice): esa en el color de la
 * serie y el resto en gris. Es para el dato clave (el mejor mes, el tipo que
 * más vende); NO para codificar el valor en el color, que ya lo dice el largo.
 */
function colorBarra(serie, indice, color) {
    if (serie.destacar === undefined || serie.destacar === null || serie.destacar === indice) {
        return color;
    }

    return conAlfa(tema.value?.eje ?? '#878a99', 0.28);
}

/**
 * Etiquetas de datos (`serie.etiquetas`): `true` en todos los puntos,
 * `'destacada'` solo en el destacado (con muchas barras, todas las cifras
 * juntas se pisan). Con el formato del eje, que es el corto.
 */
function etiquetaDe(serie, tipo) {
    if (!serie.etiquetas) {
        return undefined;
    }

    return {
        show: serie.etiquetas === true,
        position: tipo === 'bar' ? (props.horizontal ? 'right' : 'top') : 'top',
        distance: 6,
        color: tema.value?.texto,
        fontSize: 11,
        fontWeight: 600,
        formatter: (p) => (p.value === null || p.value === undefined ? '' : formatoDe(serie)(p.value)),
    };
}

function etiquetaPunto(serie, indice) {
    if (serie.etiquetas !== 'destacada' || serie.destacar !== indice) {
        return null;
    }

    return { label: { show: true } };
}

/**
 * Las medidas de las marcas salen de acá y de ningún otro lado.
 *
 * Línea de 2 px; puntos de 8 px de diámetro con un anillo del color de la
 * superficie para que se sigan viendo donde dos series se cruzan; barras de
 * 24 px como máximo (la banda sobrante es aire, no barra más gorda) con la
 * punta redondeada 4 px y el arranque cuadrado sobre la línea base.
 *
 * Devuelve una LISTA porque una banda son dos series de ECharts apiladas: el
 * piso invisible y el ancho del rango encima.
 */
function seriesDe(serie, indice) {
    const color = coloresSerie.value[indice];
    const superficie = tema.value?.superficie ?? '#ffffff';
    const tipo = serie.tipo ?? props.tipo;
    const ejeY = serie.eje === 'derecho' ? 1 : 0;
    const meta = { indice };

    if (tipo === 'banda') {
        const inferior = serie.inferior ?? [];
        const superior = serie.superior ?? [];
        const pila = `banda-${indice}`;
        const comun = {
            type: 'line', stack: pila, yAxisIndex: ejeY, symbol: 'none', silent: true,
            lineStyle: { opacity: 0 }, emphasis: { disabled: true }, connectNulls: false,
        };

        return [
            { ...comun, id: `${indice}-piso`, name: serie.nombre, data: inferior, meta: { ...meta, piso: true } },
            {
                ...comun,
                id: `${indice}-ancho`,
                name: serie.nombre,
                data: superior.map((s, i) => (s === null || inferior[i] === null ? null : s - inferior[i])),
                areaStyle: { color: conAlfa(color, 0.16) },
                meta,
            },
        ];
    }

    if (tipo === 'bar') {
        const redondeo = (v) => {
            if (props.apilado) {
                return 0;
            }
            const negativo = Number(v) < 0;
            if (props.horizontal) {
                return negativo ? [4, 0, 0, 4] : [0, 4, 4, 0];
            }

            return negativo ? [0, 0, 4, 4] : [4, 4, 0, 0];
        };

        return [{
            id: String(indice),
            type: 'bar',
            name: serie.nombre,
            yAxisIndex: props.horizontal ? 0 : ejeY,
            stack: props.apilado ? 'total' : undefined,
            barMaxWidth: 24,
            meta,
            label: etiquetaDe(serie, tipo),
            data: (serie.datos ?? []).map((v, i) => ({
                value: v,
                ...(etiquetaPunto(serie, i) ?? {}),
                itemStyle: {
                    color: serie.colores?.[i] ?? colorBarra(serie, i, color),
                    borderRadius: redondeo(v),
                    // El separador entre segmentos apilados es un hueco del
                    // color de la superficie, no un borde de otro color.
                    ...(props.apilado ? { borderColor: superficie, borderWidth: 1 } : {}),
                },
            })),
        }];
    }

    // Con muchos puntos, un marcador por dato se convierte en una cuerda de
    // cuentas: se ocultan y solo aparece el del hover.
    const puntosVisibles = props.labels.length <= 24;

    return [{
        id: String(indice),
        type: 'line',
        name: serie.nombre,
        yAxisIndex: ejeY,
        data: serie.datos ?? [],
        meta,
        smooth: false,
        connectNulls: props.unirHuecos,
        symbol: 'circle',
        symbolSize: 8,
        showSymbol: puntosVisibles,
        lineStyle: { width: 2, color, type: serie.punteada ? [5, 4] : 'solid' },
        itemStyle: { color, borderColor: superficie, borderWidth: 2 },
        emphasis: { scale: 1.3, focus: 'none' },
        label: etiquetaDe(serie, 'line'),
        // El área es un lavado del mismo tono, nunca un bloque saturado.
        areaStyle: serie.area ? { color: conAlfa(color, 0.1) } : undefined,
    }];
}

function ejeValor(formato, secundario = false) {
    return {
        type: 'value',
        axisLine: { show: false },
        axisTick: { show: false },
        // Grilla sólida: el punteado se lee como "proyección" o "umbral"
        // cuando solo es una guía. El eje secundario no dibuja la suya: dos
        // grillas que no coinciden son ruido.
        splitLine: secundario
            ? { show: false }
            : { lineStyle: { color: tema.value?.grid, width: 1, type: 'solid' } },
        // Barras horizontales: el eje de valores va abajo, donde los montos
        // largos se pisan; con 3 divisiones entran aun en el panel angosto.
        splitNumber: props.horizontal ? 3 : 5,
        scale: props.ajustarEscala,
        ...(props.enteros ? { minInterval: 1 } : {}),
        axisLabel: { color: tema.value?.eje, fontSize: 11, margin: 8, hideOverlap: true, formatter: (v) => formato(v) },
    };
}

function ejeCategoria() {
    return {
        type: 'category',
        data: props.labels,
        // El primero arriba en barras horizontales, como se lee una lista.
        inverse: props.horizontal,
        boundaryGap: props.horizontal || props.series.some((s) => (s.tipo ?? props.tipo) === 'bar'),
        axisLine: { show: false },
        axisTick: { show: false },
        // Sin grilla en el eje de categorías: duplica la información de las
        // marcas y ensucia.
        splitLine: { show: false },
        axisLabel: {
            color: tema.value?.eje,
            fontSize: 11,
            margin: 8,
            hideOverlap: true,
            ...(props.horizontal ? { width: 140, overflow: 'truncate' } : {}),
            ...(props.etiquetasInclinadas && !props.horizontal ? { rotate: 40, width: 96, overflow: 'truncate' } : {}),
        },
    };
}

/** Umbrales y zona de proyección, colgados de la primera serie de su eje. */
function marcas(listas) {
    const primeraDe = (eje) => listas.find(
        (s) => !s.meta.piso && (props.series[s.meta.indice].eje === 'derecho' ? 'derecho' : 'izquierdo') === eje,
    );

    ['izquierdo', 'derecho'].forEach((eje) => {
        const umbrales = props.umbrales.filter((u) => (u.eje ?? 'izquierdo') === eje);
        const destino = primeraDe(eje);

        if (!umbrales.length || !destino) {
            return;
        }

        destino.markLine = {
            silent: true,
            symbol: 'none',
            animation: false,
            lineStyle: { color: tema.value?.eje, width: 1, type: [4, 4] },
            // En barras horizontales la línea es vertical: con 'insideEndTop'
            // el rótulo saldría girado encima de las barras.
            label: { color: tema.value?.eje, fontSize: 11, position: props.horizontal ? 'end' : 'insideEndTop', formatter: (p) => p.name },
            data: umbrales.map((u) => (props.horizontal ? { xAxis: u.valor, name: u.nombre } : { yAxis: u.valor, name: u.nombre })),
        };
    });

    const primera = listas.find((s) => !s.meta.piso);

    if (props.inicioProyeccion && primera && props.labels.includes(props.inicioProyeccion)) {
        primera.markArea = {
            silent: true,
            itemStyle: { color: conAlfa(tema.value?.eje ?? '#878a99', 0.08) },
            label: { show: true, position: 'insideTop', color: tema.value?.eje, fontSize: 11, formatter: 'Proyección' },
            data: [[{ xAxis: props.inicioProyeccion }, { xAxis: props.labels.at(-1) }]],
        };
    }
}

/**
 * Tooltip: título del punto y una fila por serie visible, formateada como
 * en la tabla. Una banda se lee como rango ("inf – sup"), no como dos números.
 */
function contenidoTooltip(puntos) {
    const lista = Array.isArray(puntos) ? puntos : [puntos];

    if (!lista.length) {
        return '';
    }

    const fila = lista[0].dataIndex;
    const filas = lista
        .filter((p) => p.seriesId && !p.seriesId.endsWith('-piso'))
        .map((p) => {
            const indice = Number.parseInt(p.seriesId, 10);
            const serie = props.series[indice];
            const formato = formatoDetalle(serie);
            let valor;

            if (serie.tipo === 'banda') {
                const inf = serie.inferior?.[fila];
                const sup = serie.superior?.[fila];
                if (inf === null || inf === undefined || sup === null || sup === undefined) {
                    return '';
                }
                valor = `${formato(inf)} – ${formato(sup)}`;
            } else {
                const crudo = serie.datos?.[fila];
                if (crudo === null || crudo === undefined) {
                    return '';
                }
                valor = formato(crudo);
            }

            const punto = `<span class="chart-tooltip-key" style="background:${coloresSerie.value[indice]}"></span>`;

            return `<div class="chart-tooltip-row">${punto}<span>${escapar(serie.nombre)}</span><b>${escapar(valor)}</b></div>`;
        })
        .join('');

    return filas ? `<div class="chart-tooltip-title">${escapar(props.labels[fila])}</div>${filas}` : '';
}

function opcion() {
    const visibles = props.series
        .map((serie, indice) => ({ serie, indice }))
        .filter(({ serie }) => !ocultas.has(serie.nombre));

    const listas = visibles.flatMap(({ serie, indice }) => seriesDe(serie, indice));
    marcas(listas);

    const valor = ejeValor(props.formato);
    const categoria = ejeCategoria();
    const ejesY = props.horizontal
        ? categoria
        : [valor, ...(hayEjeDerecho.value ? [ejeValor(props.formatoDerecho ?? props.formato, true)] : [])];

    return {
        // Entrada suave, una sola vez; con "reducir movimiento" no se anima.
        animation: !reduceMovimiento(),
        animationDuration: 650,
        animationEasing: 'cubicOut',
        animationDurationUpdate: 300,
        textStyle: { fontFamily: tema.value?.fuente },
        aria: { enabled: true, label: { description: props.titulo } },
        grid: {
            left: 4,
            right: hayEjeDerecho.value ? 4 : 12,
            top: props.inicioProyeccion || props.umbrales.length ? 24 : 12,
            bottom: 4,
            outerBoundsMode: 'same',
            outerBoundsContain: 'axisLabel',
        },
        xAxis: props.horizontal ? valor : categoria,
        yAxis: ejesY,
        tooltip: {
            trigger: 'axis',
            confine: true,
            // La guía bajo el cursor convierte el tooltip en la lectura del
            // punto exacto cuando hay varias series encimadas.
            axisPointer: props.horizontal
                ? { type: 'shadow', shadowStyle: { color: conAlfa(tema.value?.eje ?? '#878a99', 0.08) } }
                : { type: 'line', lineStyle: { color: tema.value?.grid, width: 1, type: 'solid' } },
            backgroundColor: tema.value?.texto,
            borderWidth: 0,
            padding: [8, 10],
            textStyle: { color: tema.value?.superficie, fontSize: 12 },
            extraCssText: 'border-radius:6px;box-shadow:none;',
            formatter: contenidoTooltip,
        },
        series: listas.map(({ meta, ...resto }) => ({ ...resto, cursor: escuchaSeleccion.value ? 'pointer' : 'default' })),
    };
}

function reduceMovimiento() {
    return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}

/* -------------------------------------------------------------------------
 * Ciclo de vida
 * ---------------------------------------------------------------------- */

async function montar() {
    if (grafico || !hayDatos.value || !contenedor.value) {
        return;
    }

    const { default: echarts } = await import('./EchartsCore.js');

    // El componente pudo desmontarse mientras se descargaba el chunk.
    if (!contenedor.value || grafico) {
        return;
    }

    grafico = echarts.init(contenedor.value, null, { renderer: 'canvas' });
    grafico.setOption(opcion());
    grafico.on('click', (p) => {
        if (escuchaSeleccion.value && Number.isInteger(p.dataIndex)) {
            emit('seleccionar', { indice: p.dataIndex, etiqueta: props.labels[p.dataIndex] });
        }
    });

    // ECharts no sigue solo el tamaño de su caja: el sidebar plegado, el
    // cambio de tabla a gráfico o un giro de pantalla lo dejarían cortado.
    observador = new ResizeObserver(() => grafico?.resize());
    observador.observe(contenedor.value);
}

function redibujar() {
    if (!grafico) {
        montar();

        return;
    }

    grafico.setOption(opcion(), { notMerge: true });
}

onMounted(montar);

onBeforeUnmount(() => {
    observador?.disconnect();
    observador = null;
    grafico?.dispose();
    grafico = null;
});

// El tema entra acá: al pasar a oscuro se rehacen colores de ejes y grilla.
watch([tema, () => props.series, () => props.labels, () => props.umbrales, () => [...ocultas]], async () => {
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
    if (!grafico) {
        return;
    }

    const enlace = document.createElement('a');
    // Fondo opaco: un PNG transparente se ve negro en cualquier visor que
    // componga sobre oscuro.
    enlace.href = grafico.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: tema.value?.superficie ?? '#ffffff' });
    enlace.download = `${props.titulo.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}.png`;
    enlace.click();
}

/** Celda de la tabla gemela. */
function celda(serie, fila) {
    const formato = formatoDetalle(serie);

    if (serie.tipo === 'banda') {
        const inf = serie.inferior?.[fila];
        const sup = serie.superior?.[fila];

        return inf === null || inf === undefined || sup === null || sup === undefined
            ? '—'
            : `${formato(inf)} – ${formato(sup)}`;
    }

    const valor = serie.datos?.[fila];

    return valor === null || valor === undefined ? '—' : formato(valor);
}
</script>

<template>
    <div class="chart">
        <div v-if="hayDatos" class="chart-toolbar">
            <!-- Leyenda: presente desde dos series. Con una sola, el título de
                 la tarjeta ya dice qué se está mirando y una caja con un solo
                 cuadrito solo gasta espacio. Cada entrada apaga/prende su
                 serie (la tabla las sigue mostrando todas). -->
            <ul v-if="series.length > 1" class="chart-legend">
                <li v-for="(s, i) in series" :key="s.nombre">
                    <button type="button" class="chart-legend-item" :class="{ 'is-off': ocultas.has(s.nombre) }"
                        :aria-pressed="!ocultas.has(s.nombre)" @click="alternar(s.nombre)">
                        <span class="chart-legend-key" :class="{ 'is-band': s.tipo === 'banda', 'is-dashed': s.punteada }"
                            :style="{ backgroundColor: coloresSerie[i], color: coloresSerie[i] }"></span>
                        {{ s.nombre }}
                    </button>
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

        <div v-show="hayDatos && !verTabla" ref="contenedor" class="chart-canvas" :style="{ height: `${alto}px` }"
            role="img" :aria-label="titulo"></div>

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
                    <tr v-for="(lbl, fila) in labels" :key="`${fila}-${lbl}`">
                        <td>{{ lbl }}</td>
                        <td v-for="s in series" :key="s.nombre" class="text-end chart-table-num">
                            {{ celda(s, fila) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
