<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';

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
                <div class="bi-filtro-alcance">
                    <span class="fs-xs text-muted">{{ periodo.meses }} {{ periodo.meses === 1 ? 'mes' : 'meses' }}</span>
                    <EtiquetaAlcance />
                </div>
            </form>
        </div>
    </div>
</template>
