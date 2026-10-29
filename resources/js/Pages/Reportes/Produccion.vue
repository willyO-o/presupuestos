<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import { userCan } from '@/Directives/Can.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Reporte de producción. Recibe el MISMO informe que el PDF
 * (`InformeReportesService::produccion()`): mismas cifras, mismos nombres de
 * etapa y la misma redacción de los hallazgos.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    sucursales: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Dos enlaces (ver y descargar) bajo el mismo permiso: se decide una vez.
const puedeExportar = userCan('reportes.exportar_produccion');

const filtro = reactive({
    sucursal_id: props.filters.sucursal_id ? Number(props.filters.sucursal_id) : '',
});

/** Quien solo ve una sucursal no tiene nada que elegir: el selector se oculta. */
const eligeSucursal = computed(() => props.sucursales.length > 1);

function parametros(extra = {}) {
    return Object.fromEntries(
        Object.entries({ ...filtro, ...extra }).filter(([, valor]) => valor !== '' && valor !== null),
    );
}

/** Sin fechas que elegir, la sucursal se aplica al elegirla. */
function cambiarSucursal() {
    router.get(route('reportes.produccion'), parametros(), { preserveState: true, preserveScroll: true });
}

function urlPdf(descargar = false) {
    return route('reportes.produccion.pdf', parametros(descargar ? { descargar: 1 } : {}));
}

const hoy = new Date().toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' });

/* -----------------------------------------------------------------
 * Formato
 * -------------------------------------------------------------- */

function numero(valor, decimales = 0) {
    return Number(valor ?? 0).toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
}

function dias(valor) {
    const n = Number(valor ?? 0);

    return `${n.toLocaleString('es-BO', { maximumFractionDigits: 1 })} ${n === 1 ? 'día' : 'días'}`;
}

function plural(n, singular, varios) {
    return `${numero(n)} ${n === 1 ? singular : varios}`;
}

/* -----------------------------------------------------------------
 * Indicadores
 * -------------------------------------------------------------- */

const cumplimiento = computed(() => props.datos.cumplimiento);
const atrasos = computed(() => props.datos.atrasos);
const areas = computed(() => props.datos.carga_por_area.filter((a) => a.items_abiertos > 0));
const pendientes = computed(() => areas.value.reduce((suma, a) => suma + a.items_abiertos, 0));

const atrasoMedio = computed(() => (atrasos.value.length
    ? atrasos.value.reduce((suma, a) => suma + a.dias, 0) / atrasos.value.length
    : null));

const kpis = computed(() => [
    {
        clave: 'a-tiempo',
        etiqueta: 'Entregas a tiempo',
        valor: cumplimiento.value.cumplimiento_pct === null ? '—' : `${numero(cumplimiento.value.cumplimiento_pct, 1)} %`,
        nota: cumplimiento.value.entregados
            ? `${numero(cumplimiento.value.a_tiempo)} de ${plural(cumplimiento.value.entregados, 'pedido entregado', 'pedidos entregados')}`
            : 'Aún no hay entregas para medir.',
        icono: 'fa-solid fa-clipboard-check',
        tono: 'success',
        ayuda: 'produccion.cumplimiento',
    },
    {
        clave: 'tarde',
        etiqueta: 'Pedidos entregados tarde',
        valor: numero(cumplimiento.value.tarde),
        nota: atrasoMedio.value === null ? 'Sin atrasos registrados.' : `Atraso medio: ${dias(atrasoMedio.value)}`,
        icono: 'fa-regular fa-clock',
        tono: 'warning',
        ayuda: 'produccion.entregados-tarde',
    },
    {
        clave: 'en-curso',
        etiqueta: 'Pedidos en curso',
        valor: numero(props.datos.total_activos),
        nota: 'Aún no entregados',
        icono: 'fa-solid fa-list-ul',
        tono: 'primary',
        ayuda: 'produccion.pedidos-en-curso',
    },
    {
        clave: 'pendientes',
        etiqueta: 'Trabajos pendientes',
        valor: numero(pendientes.value),
        nota: areas.value.length ? `Distribuidos en ${plural(areas.value.length, 'área', 'áreas')}` : 'Ninguna área con trabajo abierto.',
        icono: 'fa-regular fa-file-lines',
        tono: 'info',
        ayuda: 'produccion.trabajos-pendientes',
    },
]);

/* -----------------------------------------------------------------
 * Tiempo por etapa y trabajos por área
 * -------------------------------------------------------------- */

const vistaDuracion = ref('grafico');
const vistaCarga = ref('grafico');

/** Solo las etapas con muestras: una etapa sin datos no es una barra en cero. */
const etapasMedidas = computed(() => props.datos.duracion_por_etapa.filter((e) => e.dias_promedio !== null));

const duracion = computed(() => ({
    labels: etapasMedidas.value.map((e) => e.nombre),
    series: [{ nombre: 'Días promedio', datos: etapasMedidas.value.map((e) => e.dias_promedio) }],
}));

const etapasMasLentas = computed(() => {
    const maximo = Math.max(...etapasMedidas.value.map((e) => e.dias_promedio), 0);

    return etapasMedidas.value.filter((e) => e.dias_promedio === maximo);
});

const carga = computed(() => ({
    labels: areas.value.map((a) => a.area),
    series: [{ nombre: 'Trabajos pendientes', datos: areas.value.map((a) => a.items_abiertos) }],
}));

/** Las áreas con más trabajo; puede haber empate (mismo criterio que el PDF). */
const areasMasCargadas = computed(() => {
    const maximo = Math.max(...areas.value.map((a) => a.items_abiertos), 0);

    return areas.value.filter((a) => a.items_abiertos === maximo);
});

function enumerar(nombres) {
    return nombres.length > 1 ? `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}` : nombres[0];
}

const hallazgoCarga = computed(() => {
    const lideres = areasMasCargadas.value;

    if (!lideres.length) {
        return null;
    }

    if (areas.value.length === 1) {
        return `${lideres[0].area} concentra todos los trabajos pendientes (${numero(pendientes.value)}).`;
    }

    return lideres.length === 1
        ? `${lideres[0].area} concentra ${numero(lideres[0].items_abiertos)} de los ${numero(pendientes.value)} trabajos pendientes.`
        : `${enumerar(lideres.map((a) => a.area))} concentran ${numero(lideres[0].items_abiertos)} trabajos pendientes cada una, de ${numero(pendientes.value)} en total.`;
});

/* -----------------------------------------------------------------
 * Pedidos en curso por etapa
 * -------------------------------------------------------------- */

const iconosEtapa = {
    DISENO: 'fa-solid fa-pencil',
    ELABORACION: 'fa-solid fa-gear',
    ACABADO: 'fa-solid fa-wrench',
    CONTROL_CALIDAD: 'fa-solid fa-shield-halved',
};
</script>

<template>
    <Head title="Reporte de producción" />

    <div class="page-stack">
        <!-- Barra de acciones: fecha de la foto, sucursal y PDF. -->
        <div class="produccion-acciones">
            <span class="produccion-fecha">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                Situación al {{ hoy }}
            </span>

            <label v-if="eligeSucursal" class="produccion-sucursal">
                <span class="sr-only">Sucursal</span>
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                <select v-model="filtro.sucursal_id" class="form-control" @change="cambiarSucursal">
                    <option value="">Todas las sucursales</option>
                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                </select>
            </label>

            <template v-if="puedeExportar">
                <a :href="urlPdf()" target="_blank" rel="noopener" class="btn btn-pdf">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                    Ver PDF
                </a>
                <a :href="urlPdf(true)" class="btn btn-pdf-solid">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    Descargar PDF
                </a>
            </template>
        </div>

        <div class="financiero-kpis">
            <article v-for="kpi in kpis" :key="kpi.clave" class="card kpi-card">
                <span class="stat-icon" :class="`stat-icon-${kpi.tono}`">
                    <i :class="kpi.icono" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <p class="kpi-label etiqueta-con-ayuda"><span>{{ kpi.etiqueta }}</span> <AyudaInfo :clave="kpi.ayuda" /></p>
                    <p class="kpi-value">{{ kpi.valor }}</p>
                    <p class="kpi-note">{{ kpi.nota }}</p>
                </div>
            </article>
        </div>

        <p class="financiero-alcance">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>
                <strong>Alcance:</strong> entregas y tiempos usan el historial registrado. La carga de trabajo muestra la
                situación actual.
                <template v-if="datos.alcance !== 'Toda la empresa'"> <strong>{{ datos.alcance }}</strong>.</template>
            </span>
        </p>

        <div class="produccion-fila">
            <section class="card">
                <div class="card-header financiero-card-header">
                    <div>
                        <h2 class="card-title">Tiempo promedio por etapa <AyudaInfo clave="produccion.duracion-por-etapa" /></h2>
                        <p class="card-subtitle">Desde el inicio hasta el cierre de cada etapa.</p>
                    </div>
                    <div class="financiero-vista" role="group" aria-label="Ver tiempo por etapa como">
                        <button type="button" :class="{ 'is-active': vistaDuracion === 'grafico' }"
                            :aria-pressed="vistaDuracion === 'grafico'" @click="vistaDuracion = 'grafico'">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Gráfico
                        </button>
                        <button type="button" :class="{ 'is-active': vistaDuracion === 'tabla' }"
                            :aria-pressed="vistaDuracion === 'tabla'" @click="vistaDuracion = 'tabla'">
                            <i class="fa-solid fa-table" aria-hidden="true"></i> Tabla
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart v-if="vistaDuracion === 'grafico'" titulo="Tiempo promedio por etapa" tipo="bar" horizontal
                        :labels="duracion.labels" :series="duracion.series" :formato="dias" :alto="240" sin-tabla
                        vacio="Todavía no hay etapas cerradas para medir." />

                    <div v-else class="table-responsive">
                        <table class="table-dashboard table-sm">
                            <thead>
                                <tr>
                                    <th>Etapa</th>
                                    <th class="text-end">Días promedio</th>
                                    <th class="text-end">Etapas analizadas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in datos.duracion_por_etapa" :key="e.etapa">
                                    <td>{{ e.nombre }}</td>
                                    <td class="text-end financiero-num">{{ e.dias_promedio === null ? 'Sin registros' : dias(e.dias_promedio) }}</td>
                                    <td class="text-end financiero-num">{{ numero(e.muestras) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="etapasMasLentas.length" class="produccion-hallazgo">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        <div>
                            <p class="produccion-hallazgo-titulo">
                                {{ enumerar(etapasMasLentas.map((e) => e.nombre)) }}
                                {{ etapasMasLentas.length === 1 ? 'tiene' : 'tienen' }} la mayor duración promedio:
                                {{ dias(etapasMasLentas[0].dias_promedio) }}.
                            </p>
                            <p>Una mayor duración no implica necesariamente un atraso.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header financiero-card-header">
                    <div>
                        <h2 class="card-title">Trabajos pendientes por área <AyudaInfo clave="produccion.carga-por-area" /></h2>
                        <p class="card-subtitle">Trabajos cuya etapa todavía no ha finalizado.</p>
                    </div>
                    <div class="financiero-vista" role="group" aria-label="Ver trabajos por área como">
                        <button type="button" :class="{ 'is-active': vistaCarga === 'grafico' }"
                            :aria-pressed="vistaCarga === 'grafico'" @click="vistaCarga = 'grafico'">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Gráfico
                        </button>
                        <button type="button" :class="{ 'is-active': vistaCarga === 'tabla' }"
                            :aria-pressed="vistaCarga === 'tabla'" @click="vistaCarga = 'tabla'">
                            <i class="fa-solid fa-table" aria-hidden="true"></i> Tabla
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart v-if="vistaCarga === 'grafico'" titulo="Trabajos pendientes por área" tipo="bar" horizontal
                        :labels="carga.labels" :series="carga.series" :formato="(v) => numero(v)" :alto="240" enteros sin-tabla
                        vacio="Ninguna área tiene trabajos pendientes." />

                    <div v-else class="table-responsive">
                        <table class="table-dashboard table-sm">
                            <thead>
                                <tr>
                                    <th>Área</th>
                                    <th class="text-end">Trabajos pendientes</th>
                                    <th class="text-end">Parte del total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in areas" :key="a.area">
                                    <td>{{ a.area }}</td>
                                    <td class="text-end financiero-num">{{ numero(a.items_abiertos) }}</td>
                                    <td class="text-end financiero-num">{{ numero((a.items_abiertos / pendientes) * 100, 1) }} %</td>
                                </tr>
                                <tr v-if="!areas.length">
                                    <td colspan="3" class="text-center text-muted">Ninguna área tiene trabajos pendientes.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="hallazgoCarga" class="produccion-hallazgo">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        <div>
                            <p class="produccion-hallazgo-titulo">{{ hallazgoCarga }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pedidos en curso por etapa <AyudaInfo clave="produccion.pedidos-activos" /></h2>
                    <p class="card-subtitle">Cada pedido se cuenta una sola vez, en la etapa menos avanzada de sus trabajos.</p>
                </div>
            </div>
            <div class="card-body">
                <ol class="produccion-flujo">
                    <li v-for="e in datos.pedidos_activos_por_etapa" :key="e.etapa" class="produccion-paso">
                        <span class="produccion-paso-icono"><i :class="iconosEtapa[e.etapa] ?? 'fa-solid fa-circle'" aria-hidden="true"></i></span>
                        <div>
                            <p class="produccion-paso-nombre">{{ e.nombre }}</p>
                            <p class="produccion-paso-valor">{{ numero(e.pedidos) }}</p>
                            <p class="produccion-paso-unidad">{{ e.pedidos === 1 ? 'pedido' : 'pedidos' }}</p>
                        </div>
                    </li>
                </ol>

                <div class="produccion-pregunta">
                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                    <div>
                        <p class="produccion-pregunta-titulo">
                            ¿Por qué hay {{ plural(datos.total_activos, 'pedido', 'pedidos') }} y {{ plural(pendientes, 'trabajo', 'trabajos') }}?
                        </p>
                        <p>Un pedido puede incluir varios trabajos. Por eso ambas cantidades pueden ser diferentes.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
