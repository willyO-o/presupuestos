<script setup>
import { computed, ref } from 'vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import { formateador } from '@/Utils/FormatoReporte.js';

/**
 * Un resultado de herramienta del asistente de BI: gráfico, tabla y "cómo lo
 * calculé". Todo sale de los DATOS que devolvió la herramienta (consulta a la
 * base), nunca del texto del modelo: es lo que permite confiar en la cifra.
 */
const props = defineProps({
    /** `{ herramienta, titulo, calculo, parametros, tabla, grafico }` */
    resultado: { type: Object, required: true },
});

const verTabla = ref(false);

const grafico = computed(() => {
    const g = props.resultado.grafico;

    if (!g || !g.labels?.length || !g.series?.length) {
        return null;
    }

    return {
        tipo: g.tipo ?? 'bar',
        horizontal: Boolean(g.horizontal),
        labels: g.labels.map((l) => String(l)),
        series: g.series,
        formato: formateador(g.formato),
        umbrales: g.umbrales ?? [],
        inicioProyeccion: g.inicio_proyeccion ?? null,
        // Barras horizontales: alto según cuántas categorías haya.
        alto: g.horizontal ? Math.min(60 + g.labels.length * 30, 340) : 220,
    };
});

const tabla = computed(() => props.resultado.tabla ?? null);

const parametros = computed(() => Object.entries(props.resultado.parametros ?? {})
    .filter(([, v]) => v !== null && v !== '')
    .map(([clave, valor]) => `${clave.replace(/_/g, ' ')}: ${valor}`));

function celda(fila, columna) {
    const valor = fila[columna.clave];

    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    return columna.formato ? formateador(columna.formato)(valor) : valor;
}
</script>

<template>
    <section class="ia-resultado" :aria-label="resultado.titulo">
        <h4 class="ia-resultado-titulo">{{ resultado.titulo }}</h4>

        <BaseChart v-if="grafico" :titulo="resultado.titulo" :tipo="grafico.tipo" :horizontal="grafico.horizontal"
            :labels="grafico.labels" :series="grafico.series" :formato="grafico.formato" :alto="grafico.alto"
            :umbrales="grafico.umbrales" :inicio-proyeccion="grafico.inicioProyeccion" />

        <template v-else-if="tabla?.filas?.length">
            <div class="table-responsive">
                <table class="table-dashboard table-sm ia-resultado-tabla">
                    <thead>
                        <tr>
                            <th v-for="col in tabla.columnas" :key="col.clave" :class="{ 'text-end': col.formato }">{{ col.etiqueta }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(fila, i) in tabla.filas" :key="i">
                            <td v-for="col in tabla.columnas" :key="col.clave" :class="{ 'text-end chart-table-num': col.formato }">
                                {{ celda(fila, col) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div class="ia-resultado-pie">
            <button v-if="grafico && tabla?.filas?.length" type="button" class="ia-enlace" :aria-expanded="verTabla"
                @click="verTabla = !verTabla">
                <i class="fa-solid fa-table-list"></i> {{ verTabla ? 'Ocultar tabla' : 'Ver tabla' }}
            </button>
            <details class="ia-calculo">
                <summary><i class="fa-solid fa-calculator"></i> Cómo lo calculé</summary>
                <p>{{ resultado.calculo }}</p>
                <p v-if="parametros.length" class="ia-calculo-parametros">
                    <span class="ia-calculo-herramienta">{{ resultado.herramienta }}</span>
                    <span v-for="p in parametros" :key="p">{{ p }}</span>
                </p>
            </details>
        </div>

        <div v-if="verTabla && grafico" class="table-responsive">
            <table class="table-dashboard table-sm ia-resultado-tabla">
                <thead>
                    <tr>
                        <th v-for="col in tabla.columnas" :key="col.clave" :class="{ 'text-end': col.formato }">{{ col.etiqueta }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(fila, i) in tabla.filas" :key="i">
                        <td v-for="col in tabla.columnas" :key="col.clave" :class="{ 'text-end chart-table-num': col.formato }">
                            {{ celda(fila, col) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="tabla.filas_omitidas" class="fs-xs text-muted mb-0 mt-1">
                Y {{ tabla.filas_omitidas }} filas más.
            </p>
        </div>
    </section>
</template>
