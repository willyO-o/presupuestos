<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import Modal from '@/Components/Modal.vue';

/**
 * Encabezado común de las pestañas de Inteligencia de negocios: navegación
 * entre secciones + el filtro de fechas que acota TODO lo de abajo.
 *
 * Cada pestaña es su propia ruta (solo calcula lo que muestra). Al cambiar de
 * pestaña se conserva el periodo elegido: las fechas viajan en la query
 * string de cada enlace, así comparar "costos" y "rentabilidad" del mismo
 * trimestre no obliga a volver a elegirlo.
 *
 * Sin `periodo` (pestaña Resumen: cada resumen ya es una semana cerrada) se
 * muestran solo las pestañas.
 */
const props = defineProps({
    /** `datos.periodo` del servicio: `{ desde, hasta, meses }`. */
    periodo: { type: Object, default: null },
    /** Filtros que llegaron en la URL (vacío = periodo por defecto). */
    filters: { type: Object, default: () => ({}) },
});

const pestanas = [
    { ruta: 'reportes.bi.panorama', texto: 'Panorama', icono: 'fa-solid fa-gauge-high' },
    { ruta: 'reportes.bi.preguntas', texto: 'Preguntas', icono: 'fa-solid fa-circle-question' },
    { ruta: 'reportes.bi.resumen', texto: 'Resumen semanal', icono: 'fa-solid fa-newspaper' },
    { ruta: 'reportes.bi', texto: 'Demanda y ventas', icono: 'fa-solid fa-chart-line' },
    { ruta: 'reportes.bi.costos', texto: 'Costos y materiales', icono: 'fa-solid fa-boxes-stacked' },
    { ruta: 'reportes.bi.rentabilidad', texto: 'Presupuestos y rentabilidad', icono: 'fa-solid fa-scale-balanced' },
    { ruta: 'reportes.bi.alertas', texto: 'Alertas', icono: 'fa-solid fa-triangle-exclamation' },
    { ruta: 'reportes.bi.simulador', texto: 'Simulador', icono: 'fa-solid fa-sliders' },
];

const rango = reactive({
    desde: props.filters.desde ?? props.periodo?.desde ?? '',
    hasta: props.filters.hasta ?? props.periodo?.hasta ?? '',
});

function aplicar() {
    router.get(route(route().current()), { ...rango }, { preserveState: true, preserveScroll: true });
}

/* ----- Informe de proyecciones (PDF / CSV) ------------------------------- */

/**
 * Secciones del informe: mismas claves que
 * InformeProyeccionesService::SECCIONES (el servidor las valida).
 */
const SECCIONES_INFORME = [
    { clave: 'preguntas', texto: 'Preguntas para decidir', detalle: 'Costo real, desvíos, rentabilidad, escenarios, cobranza y qué hacer' },
    { clave: 'demanda', texto: 'Demanda y ventas', detalle: 'Pedidos por mes' },
    { clave: 'costos', texto: 'Costos y materiales', detalle: 'Índice de costo, consumo, precio por material' },
    { clave: 'rentabilidad', texto: 'Presupuestos y rentabilidad', detalle: 'Montos emitido y vendido, rentabilidad, utilidad' },
    { clave: 'alertas', texto: 'Alertas', detalle: 'Aumentos de costo, márgenes bajos, sobrecostos' },
];

const mostrarInforme = ref(false);
const seccionesInforme = ref(SECCIONES_INFORME.map((s) => s.clave));

/**
 * El informe usa las fechas que se ven en el filtro (aunque todavía no se
 * haya pulsado "Aplicar"): es lo que el usuario cree que está pidiendo.
 */
function urlInforme(formato, extra = {}) {
    return route(`reportes.bi.informe.${formato}`, {
        desde: rango.desde || undefined,
        hasta: rango.hasta || undefined,
        secciones: seccionesInforme.value,
        ...extra,
    });
}

const sinSecciones = computed(() => seccionesInforme.value.length === 0);

function enlace(ruta) {
    // El resumen no usa el periodo: no se arrastran las fechas hacia ahí.
    return (props.filters.desde || props.filters.hasta) && ruta !== 'reportes.bi.resumen'
        ? route(ruta, { desde: rango.desde, hasta: rango.hasta })
        : route(ruta);
}
</script>

<template>
    <div class="card bi-encabezado">
        <nav class="nav-tabs bi-tabs" aria-label="Secciones de inteligencia de negocios">
            <Link v-for="p in pestanas" :key="p.ruta" :href="enlace(p.ruta)" class="nav-link"
                :class="{ active: route().current(p.ruta) }" :aria-current="route().current(p.ruta) ? 'page' : null"
                preserve-scroll>
                <i :class="p.icono"></i>
                <span>{{ p.texto }}</span>
            </Link>
        </nav>
        <div v-if="periodo" class="card-body">
            <form class="bi-filtro" @submit.prevent="aplicar">
                <div class="bi-filtro-rango">
                    <DateRangeFilter v-model:fecha-desde="rango.desde" v-model:fecha-hasta="rango.hasta"
                        label="Periodo analizado" />
                </div>
                <button type="submit" class="btn btn-primary">Aplicar</button>
                <button type="button" class="btn btn-soft-primary" @click="mostrarInforme = true">
                    <i class="fa-solid fa-file-lines"></i> Generar informe
                </button>
                <div class="bi-filtro-alcance">
                    <span class="fs-xs text-muted">{{ periodo.meses }} {{ periodo.meses === 1 ? 'mes' : 'meses' }}</span>
                    <EtiquetaAlcance />
                </div>
            </form>
        </div>

        <Modal :show="mostrarInforme" max-width="lg" @close="mostrarInforme = false">
            <div class="card-header">
                <span class="card-title">Informe de proyecciones</span>
                <button type="button" class="modal-close" aria-label="Cerrar" @click="mostrarInforme = false">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="card-body">
                <p class="fs-xs text-muted mb-3">
                    Periodo del filtro{{ rango.desde && rango.hasta ? `: ${rango.desde} al ${rango.hasta}` : '' }}.
                    Incluye gráficos, tablas, lectura automática y anexo metodológico.
                </p>
                <div role="group" aria-labelledby="bi-informe-secciones">
                    <p id="bi-informe-secciones" class="form-label">Secciones a incluir</p>
                    <ul class="bi-informe-secciones">
                        <li v-for="s in SECCIONES_INFORME" :key="s.clave">
                            <label class="bi-informe-seccion">
                                <input v-model="seccionesInforme" type="checkbox" :value="s.clave" />
                                <span>
                                    {{ s.texto }}
                                    <small>{{ s.detalle }}</small>
                                </span>
                            </label>
                        </li>
                    </ul>
                </div>
                <p class="fs-xs text-muted mt-3 mb-0">
                    El PDF es para leer o presentar; el CSV abre en Excel con las series mes a mes, la ficha del método
                    y las alertas, para reprocesarlas.
                </p>
            </div>
            <div class="modal-footer bi-informe-acciones">
                <span v-if="sinSecciones" class="fs-xs text-danger me-auto">Elegí al menos una sección.</span>
                <a :href="sinSecciones ? null : urlInforme('csv')" class="btn btn-outline-primary"
                    :class="{ disabled: sinSecciones }" :aria-disabled="sinSecciones">
                    <i class="fa-solid fa-file-csv"></i> Descargar CSV
                </a>
                <a :href="sinSecciones ? null : urlInforme('pdf')" target="_blank" rel="noopener" class="btn btn-pdf-solid"
                    :class="{ disabled: sinSecciones }" :aria-disabled="sinSecciones">
                    <i class="fa-solid fa-file-pdf"></i> Ver PDF
                </a>
            </div>
        </Modal>
    </div>
</template>
