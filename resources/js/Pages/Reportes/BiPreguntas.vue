<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import ChartCard from '@/Components/Reporte/ChartCard.vue';
import { formateador } from '@/Utils/FormatoReporte.js';

/**
 * Preguntas para decidir: el BI contestado en castellano. Cada tarjeta es una
 * pregunta; el título es la pregunta, el hallazgo su respuesta en una frase
 * y abajo van la tabla corta, qué hacer y con qué datos se respondió.
 *
 * Todo lo arma `PreguntasNegocioService` (las mismas cifras salen en el PDF y
 * el CSV del informe); acá solo se maqueta. La última pregunta junta las
 * acciones de las demás, por eso arriba hay un atajo hacia ella.
 */
defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/** Ayuda "i" de cada pregunta (claves de `Data/Ayudas.js`). */
const AYUDAS = {
    costo_producto: { ayuda: 'bi-preguntas.costo-producto' },
    costos_suben: { ayuda: 'bi-preguntas.costos-suben' },
    gasto_presupuesto: { ayuda: 'bi-preguntas.gasto-presupuesto' },
    materiales_pesan: { ayuda: 'bi-preguntas.materiales-pesan' },
    productos_rentables: { ayuda: 'bi-preguntas.productos-rentables' },
    clientes_rentables: { ayuda: 'bi-preguntas.clientes-rentables' },
    descuentos: { ayuda: 'bi-preguntas.descuentos' },
    margen_bajo: { ayuda: 'bi-preguntas.margen-bajo' },
    materia_prima: { ayuda: 'bi-preguntas.materia-prima' },
    cobranza: { ayuda: 'bi-preguntas.cobranza' },
    decisiones: { ayuda: 'bi-preguntas.decisiones' },
};

const numeradas = computed(() => props.datos.preguntas.map((p, i) => ({ ...p, numero: i + 1 })));

const grupos = computed(() => Object.entries(props.datos.grupos)
    .map(([clave, titulo]) => ({ clave, titulo, preguntas: numeradas.value.filter((p) => p.grupo === clave) }))
    .filter((g) => g.preguntas.length > 0));

const decisiones = computed(() => numeradas.value.find((p) => p.clave === 'decisiones'));

/** "Sale de" trae el rótulo corto de la pregunta: se vuelve a su ancla. */
const claveDeCorto = computed(() => Object.fromEntries(props.datos.preguntas.map((p) => [p.corto, p.clave])));

const ancla = (clave) => `pregunta-${clave}`;

function celda(valor, formato) {
    if (valor === null || valor === undefined) {
        return '—';
    }

    return formato === 'texto' ? valor : formateador(formato)(valor);
}
</script>

<template>
    <Head title="Preguntas para decidir · Inteligencia de negocios" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="preguntas-layout">
            <nav class="card preguntas-indice" aria-label="Índice de preguntas">
                <div class="card-body">
                    <p class="preguntas-indice-titulo">{{ datos.preguntas.length }} preguntas</p>
                    <div v-for="g in grupos" :key="g.clave" class="preguntas-indice-grupo">
                        <p class="preguntas-indice-rotulo">{{ g.titulo }}</p>
                        <ol>
                            <li v-for="p in g.preguntas" :key="p.clave">
                                <a :href="`#${ancla(p.clave)}`">
                                    <span class="preguntas-indice-numero">{{ p.numero }}</span>
                                    <span>{{ p.pregunta }}</span>
                                </a>
                            </li>
                        </ol>
                    </div>
                </div>
            </nav>

            <div class="preguntas-contenido">
                <a v-if="decisiones && decisiones.tabla.filas.length" :href="`#${ancla('decisiones')}`"
                    class="preguntas-atajo">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                    <span>
                        <strong>Para ir directo a qué hacer, mira la pregunta {{ decisiones.numero }}.</strong>
                        {{ decisiones.respuesta }}
                    </span>
                    <i class="fa-solid fa-arrow-down preguntas-atajo-flecha" aria-hidden="true"></i>
                </a>

                <section v-for="g in grupos" :key="g.clave" class="preguntas-grupo" :aria-label="g.titulo">
                    <p class="preguntas-grupo-titulo">{{ g.titulo }}</p>

                    <ChartCard v-for="p in g.preguntas" :id="ancla(p.clave)" :key="p.clave" class="pregunta"
                        :class="{ 'is-decisiones': p.clave === 'decisiones' }" :tema="`${p.numero}. ${p.pregunta}`"
                        :hallazgo="p.respuesta" :ayuda="AYUDAS[p.clave]?.ayuda">
                        <ol v-if="p.clave === 'decisiones' && p.tabla.filas.length" class="decisiones-lista">
                            <li v-for="(fila, i) in p.tabla.filas" :key="i" class="decision">
                                <span class="decision-orden">{{ i + 1 }}</span>
                                <div class="decision-cuerpo">
                                    <p class="decision-texto">{{ fila[0] }}</p>
                                    <a :href="`#${ancla(claveDeCorto[fila[1]])}`" class="decision-origen">
                                        <i class="fa-solid fa-arrow-turn-up" aria-hidden="true"></i>
                                        {{ fila[1] }}
                                    </a>
                                </div>
                                <span class="decision-monto">
                                    <small>En juego</small>
                                    {{ celda(fila[2], 'bs') }}
                                </span>
                            </li>
                        </ol>

                        <div v-else-if="p.tabla.filas.length" class="table-responsive">
                            <table class="table-dashboard table-sm pregunta-tabla">
                                <thead>
                                    <tr>
                                        <th v-for="c in p.tabla.columnas" :key="c.titulo" scope="col"
                                            :class="{ 'text-end': c.formato !== 'texto' }">
                                            {{ c.titulo }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(fila, i) in p.tabla.filas" :key="i">
                                        <td v-for="(valor, j) in fila" :key="j"
                                            :class="p.tabla.columnas[j].formato === 'texto'
                                                ? { 'fw-semibold': j === 0 }
                                                : 'text-end chart-table-num'">
                                            {{ celda(valor, p.tabla.columnas[j].formato) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="pregunta-vacia">Sin datos para esta pregunta en el periodo elegido.</p>

                        <div v-if="p.acciones.length && p.clave !== 'decisiones'" class="pregunta-acciones">
                            <p class="pregunta-acciones-titulo">Qué hacer</p>
                            <ul>
                                <li v-for="(a, i) in p.acciones" :key="i">
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                    <span>{{ a.texto }}</span>
                                </li>
                            </ul>
                        </div>

                        <p v-if="p.aviso" class="pregunta-nota is-aviso">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <span>{{ p.aviso }}</span>
                        </p>
                        <p v-if="p.base" class="pregunta-nota">
                            <i class="fa-solid fa-database" aria-hidden="true"></i>
                            <span>{{ p.base }}</span>
                        </p>
                    </ChartCard>
                </section>
            </div>
        </div>
    </div>
</template>
