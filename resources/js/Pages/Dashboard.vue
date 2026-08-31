<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    resumen: { type: Object, required: true },
});

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

const stats = computed(() => [
    { label: 'Cotizaciones este mes', value: props.resumen.cotizaciones_mes, icon: 'primary' },
    { label: 'Tasa de conversión', value: `${props.resumen.tasa_conversion}%`, icon: 'success' },
    { label: 'Ingresos del mes', value: money(props.resumen.ingresos_mes), icon: 'info' },
    { label: 'Materiales con stock bajo', value: props.resumen.materiales_bajo_stock, icon: 'warning' },
]);

const ventasMax = computed(() => Math.max(1, ...props.resumen.ventas_por_mes.map((m) => m.total)));

const etapas = computed(() => Object.entries(props.resumen.pedidos_por_etapa).map(([etapa, n]) => ({ etapa, n })));
const etapasMax = computed(() => Math.max(1, ...etapas.value.map((e) => e.n)));

const entregasTotal = computed(() => props.resumen.entregas.a_tiempo + props.resumen.entregas.tarde);
</script>

<template>
    <Head title="Dashboard" />

    <div class="row">
        <div v-for="stat in stats" :key="stat.label" class="col-lg-3">
            <div class="card">
                <div class="stat-card">
                    <span class="stat-icon" :class="`stat-icon-${stat.icon}`">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 17l6-6 4 4 8-8M21 7v6M21 7h-6" />
                        </svg>
                    </span>
                    <div>
                        <p class="stat-value">{{ stat.value }}</p>
                        <p class="stat-label">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <p class="card-title">Ventas por mes</p>
                        <p class="card-subtitle">Total cotizado de cotizaciones aprobadas · últimos 6 meses</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="bar-chart">
                        <div v-for="col in resumen.ventas_por_mes" :key="col.mes" class="bar-chart-col">
                            <span class="bar-chart-value">{{ money(col.total) }}</span>
                            <div class="bar-chart-bar" :style="{ height: (col.total / ventasMax) * 100 + '%' }"></div>
                            <span class="bar-chart-label">{{ col.mes }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><span class="card-title">Pedidos en producción</span></div>
                <div class="card-body">
                    <ul class="list-group">
                        <li v-for="e in etapas" :key="e.etapa" class="list-group-item">
                            <div class="list-group-item-start">
                                <span class="list-group-item-title">{{ e.etapa }}</span>
                            </div>
                            <span class="list-group-item-value">{{ e.n }}</span>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <p class="fs-sm text-muted mb-1">Entregas a tiempo</p>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar"
                                :style="{ width: (entregasTotal ? resumen.entregas.a_tiempo / entregasTotal * 100 : 0) + '%' }">
                            </div>
                        </div>
                        <p class="fs-xs text-muted mt-1">
                            {{ resumen.entregas.a_tiempo }} a tiempo · {{ resumen.entregas.tarde }} tarde
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2">
                    <Link :href="route('reportes.financiero')" v-can="'reportes.financiero'" class="btn btn-soft-primary btn-sm">
                        <i class="fa-solid fa-chart-column"></i> Reporte financiero
                    </Link>
                    <Link :href="route('reportes.produccion')" v-can="'reportes.produccion'" class="btn btn-soft-primary btn-sm">
                        <i class="fa-solid fa-industry"></i> Reporte de producción
                    </Link>
                    <Link :href="route('reportes.bi')" v-can="'reportes.bi'" class="btn btn-soft-primary btn-sm">
                        <i class="fa-solid fa-chart-line"></i> Inteligencia de negocios
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
