<script setup>
import { computed, ref } from 'vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import { money, moneyCorto, numero, variacion } from '@/Utils/FormatoReporte.js';

/**
 * Cuerpo de la pregunta 2 ("¿Qué materiales están subiendo de precio?") de
 * Preguntas para decidir: tres indicadores (cuántos subieron, el costo
 * adicional calculado y el de mayor impacto), el costo adicional por
 * material en gráfico o tabla y, al lado, el detalle de los precios.
 *
 * Usa la tabla y las `cifras` de `PreguntasNegocioService` (la misma tabla
 * sale en el PDF y el CSV); acá solo se reparte en dos cuadros.
 */
const props = defineProps({
    pregunta: { type: Object, required: true },
});

const vista = ref('grafico');

const cifras = computed(() => (props.pregunta.cifras?.materiales ? props.pregunta.cifras : null));

const materiales = computed(() => {
    const columna = (titulo) => props.pregunta.tabla.columnas.findIndex((c) => c.titulo === titulo);
    const [anterior, actual, aumento, costo] = ['Precio anterior', 'Precio actual', 'Aumento', 'Costo adicional'].map(columna);

    return props.pregunta.tabla.filas.map((f) => ({
        nombre: f[0],
        anterior: f[anterior],
        actual: f[actual],
        aumento: f[aumento],
        costo: f[costo],
    }));
});

const grafico = computed(() => ({
    labels: materiales.value.map((m) => m.nombre),
    series: [{ nombre: 'Costo adicional', tipo: 'bar', datos: materiales.value.map((m) => m.costo), destacar: 0, etiquetas: true }],
    alto: Math.max(200, materiales.value.length * 44),
}));

/** Los que subieron y no entran en los cuadros (la tabla trae los de mayor impacto). */
const fuera = computed(() => (cifras.value ? cifras.value.materiales - materiales.value.length : 0));
</script>

<template>
    <div class="aumento-cuerpo">
        <div v-if="cifras" class="aumento-kpis">
            <BiKpi etiqueta="Materiales con aumento" :valor="numero(cifras.materiales, 0)" icono="fa-solid fa-arrow-trend-up" tono="primary" />
            <BiKpi etiqueta="Costo adicional calculado" :valor="moneyCorto(cifras.costo_adicional)" icono="fa-solid fa-coins" tono="info"
                nota="Según cantidades consumidas" />
            <BiKpi etiqueta="Mayor impacto" :valor="cifras.mayor_material" icono="fa-solid fa-star" tono="warning"
                :nota="`${variacion(cifras.mayor_aumento)} de precio`" />
        </div>

        <div class="aumento-paneles">
            <section class="aumento-panel" aria-labelledby="aumento-costo-titulo">
                <header class="preguntas-tabla-cabecera">
                    <h3 id="aumento-costo-titulo">¿Cuáles generan mayor costo adicional?</h3>
                    <div class="financiero-vista panorama-vista" role="group" aria-label="Ver como">
                        <button type="button" :class="{ 'is-active': vista === 'grafico' }" :aria-pressed="vista === 'grafico'"
                            @click="vista = 'grafico'"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Gráfico</button>
                        <button type="button" :class="{ 'is-active': vista === 'tabla' }" :aria-pressed="vista === 'tabla'"
                            @click="vista = 'tabla'"><i class="fa-solid fa-table" aria-hidden="true"></i> Tabla</button>
                    </div>
                </header>

                <BaseChart v-if="vista === 'grafico'" titulo="Costo adicional por material" tipo="bar" horizontal
                    :labels="grafico.labels" :series="grafico.series" :formato="moneyCorto" :alto="grafico.alto" sin-tabla />

                <div v-else class="table-responsive">
                    <table class="table-dashboard table-sm aumento-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Material</th>
                                <th scope="col" class="text-end">Costo adicional</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in materiales" :key="m.nombre">
                                <td class="fw-semibold">{{ m.nombre }}</td>
                                <td class="text-end">{{ moneyCorto(m.costo) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="fuera > 0" class="aumento-nota">
                    Se muestran los {{ materiales.length }} materiales de mayor impacto; el total incluye los {{ numero(cifras.materiales, 0) }}.
                </p>
            </section>

            <section class="aumento-panel" aria-labelledby="aumento-precios-titulo">
                <header class="preguntas-tabla-cabecera">
                    <h3 id="aumento-precios-titulo">Detalle de los precios</h3>
                </header>

                <div class="table-responsive">
                    <table class="table-dashboard table-sm aumento-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Material</th>
                                <th scope="col" class="text-end">Precio anterior</th>
                                <th scope="col" class="text-end">Precio actual</th>
                                <th scope="col" class="text-end">Aumento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in materiales" :key="m.nombre">
                                <td>{{ m.nombre }}</td>
                                <td class="text-end">{{ money(m.anterior) }}</td>
                                <td class="text-end">{{ money(m.actual) }}</td>
                                <td class="text-end fw-semibold">{{ variacion(m.aumento) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
