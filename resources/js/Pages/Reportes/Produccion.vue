<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
});

const cargaMax = computed(() => Math.max(1, ...props.datos.carga_por_area.map((a) => a.items_abiertos)));
const duracionMax = computed(() => Math.max(1, ...props.datos.duracion_por_etapa.map((e) => e.dias_promedio ?? 0)));
</script>

<template>
    <Head title="Reporte de producción" />

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
                <div class="card-header"><span class="card-title">Duración promedio por etapa</span></div>
                <div class="card-body">
                    <div v-for="e in datos.duracion_por_etapa" :key="e.etapa" class="mb-3">
                        <div class="d-flex justify-content-between fs-sm">
                            <span>{{ e.etapa }}</span>
                            <span class="fw-semibold">
                                {{ e.dias_promedio === null ? 'sin datos' : `${e.dias_promedio} días` }}
                                <span class="fs-xs text-muted">({{ e.muestras }})</span>
                            </span>
                        </div>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar"
                                :style="{ width: ((e.dias_promedio ?? 0) / duracionMax) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><span class="card-title">Carga de trabajo por área (ítems abiertos)</span></div>
                <div class="card-body">
                    <p v-if="!datos.carga_por_area.length" class="text-muted mb-0">Sin etapas abiertas.</p>
                    <div v-for="a in datos.carga_por_area" :key="a.area" class="mb-3">
                        <div class="d-flex justify-content-between fs-sm">
                            <span>{{ a.area }}</span><span class="fw-semibold">{{ a.items_abiertos }}</span>
                        </div>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar"
                                :style="{ width: (a.items_abiertos / cargaMax) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Pedidos activos por etapa</span></div>
        <div class="card-body">
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
</template>
