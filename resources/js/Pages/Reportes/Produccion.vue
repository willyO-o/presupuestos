<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
});

function dias(value) {
    return `${Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 1 })} d`;
}

function entero(value) {
    return Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 });
}

/** Solo las etapas con muestras: una etapa sin datos no es una barra en cero. */
const duracion = computed(() => {
    const conDatos = props.datos.duracion_por_etapa.filter((e) => e.dias_promedio !== null);

    return {
        labels: conDatos.map((e) => e.etapa.replace(/_/g, ' ')),
        series: [{ nombre: 'Días promedio', datos: conDatos.map((e) => e.dias_promedio) }],
    };
});

const sinMuestras = computed(
    () => props.datos.duracion_por_etapa.filter((e) => e.dias_promedio === null).length,
);

const carga = computed(() => ({
    labels: props.datos.carga_por_area.map((a) => a.area),
    series: [{ nombre: 'Ítems abiertos', datos: props.datos.carga_por_area.map((a) => a.items_abiertos) }],
}));
</script>

<template>
    <Head title="Reporte de producción" />

    <div class="page-stack">
        <div class="row">
            <div class="col-lg-4">
                <div class="stat-card card">
                    <div class="stat-icon stat-icon-success"><i class="fa-solid fa-calendar-check"></i></div>
                    <div>
                        <p class="text-muted fs-sm mb-0">Cumplimiento de entregas</p>
                        <p class="fs-xl fw-bold mb-0">
                            {{ datos.cumplimiento.cumplimiento_pct === null ? '—' : `${datos.cumplimiento.cumplimiento_pct}%` }}
                        </p>
                        <p class="fs-xs text-muted mb-0">
                            {{ datos.cumplimiento.a_tiempo }} a tiempo / {{ datos.cumplimiento.entregados }} entregados
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Duración promedio por etapa</span>
                            <p class="card-subtitle">
                                Días entre el ingreso a una etapa y la siguiente
                                <span v-if="sinMuestras">· {{ sinMuestras }} etapa(s) todavía sin muestras</span>
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Duración promedio por etapa" tipo="bar" horizontal :labels="duracion.labels"
                            :series="duracion.series" :formato="dias" :alto="240"
                            vacio="Todavía no hay etapas cerradas para medir." />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Carga de trabajo por área</span>
                            <p class="card-subtitle">Ítems abiertos ahora mismo en cada área</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Carga de trabajo por área" tipo="bar" horizontal :labels="carga.labels"
                            :series="carga.series" :formato="entero" :alto="240" vacio="Sin etapas abiertas." />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Pedidos activos por etapa</span></div>
            <div class="card-body">
                <!-- Un puñado de números sueltos: fila de KPIs, no un gráfico. -->
                <div class="row">
                    <div v-for="e in datos.pedidos_activos_por_etapa" :key="e.etapa" class="col-lg-3 col-6">
                        <div class="reporte-kpi">
                            <span class="reporte-kpi-value">{{ e.pedidos }}</span>
                            <span class="reporte-kpi-label">{{ e.etapa }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
