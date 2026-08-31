<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import LineChart from '@/Components/Chart/LineChart.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
});

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

const productoMax = computed(() => Math.max(1, ...props.datos.productos_mas_vendidos.map((p) => p.monto)));
const categoriaMax = computed(() => Math.max(1, ...props.datos.categorias_mas_vendidas.map((c) => c.monto)));

/* Demanda: histórico + media móvil + proyección en una sola línea temporal. */
const demandaSeries = computed(() => {
    const histLabels = props.datos.demanda.serie.map((s) => s.mes);
    const projLabels = props.datos.demanda.proyeccion.map((p) => p.mes);
    const labels = [...histLabels, ...projLabels];

    const real = props.datos.demanda.serie.map((s) => s.pedidos);
    const media = props.datos.demanda.media_movil.map((m) => m.valor);
    const proj = [
        ...Array(histLabels.length - 1).fill(null),
        real[real.length - 1] ?? null,
        ...props.datos.demanda.proyeccion.map((p) => p.pedidos_estimados),
    ];

    return {
        labels,
        series: [
            { nombre: 'Pedidos reales', color: 'var(--c-primary)', valores: real },
            { nombre: 'Media móvil 3m', color: 'var(--c-info)', valores: media },
            { nombre: 'Proyección', color: 'var(--c-warning)', valores: proj },
        ],
    };
});

const estacionalidadMax = computed(() => Math.max(1, ...props.datos.demanda.estacionalidad.map((m) => m.pedidos)));

const costosSeries = computed(() => props.datos.evolucion_costos.slice(0, 4).map((m, i) => ({
    nombre: m.material,
    color: ['var(--c-primary)', 'var(--c-info)', 'var(--c-warning)', 'var(--c-pink)'][i],
    valores: m.puntos.map((p) => p.precio),
})));
const costosLabels = computed(() => {
    const primero = props.datos.evolucion_costos[0];
    return primero ? primero.puntos.map((p) => p.fecha.slice(0, 7)) : [];
});
</script>

<template>
    <Head title="Inteligencia de negocios" />

    <div class="card mb-4">
        <div class="card-header">
            <div>
                <span class="card-title">Proyección de demanda</span>
                <p class="card-subtitle">Pedidos por mes, media móvil de 3 meses y tendencia lineal a 3 meses</p>
            </div>
        </div>
        <div class="card-body">
            <LineChart :series="demandaSeries.series" :labels="demandaSeries.labels" />
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><span class="card-title">Productos más vendidos</span></div>
                <div class="card-body">
                    <p v-if="!datos.productos_mas_vendidos.length" class="text-muted mb-0">Sin datos aún.</p>
                    <div v-for="p in datos.productos_mas_vendidos" :key="p.nombre" class="mb-3">
                        <div class="d-flex justify-content-between fs-sm">
                            <span class="text-truncate">{{ p.nombre }}</span>
                            <span class="fw-semibold">{{ money(p.monto) }}</span>
                        </div>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar" :style="{ width: (p.monto / productoMax) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><span class="card-title">Categorías más vendidas</span></div>
                <div class="card-body">
                    <p v-if="!datos.categorias_mas_vendidas.length" class="text-muted mb-0">Sin datos aún.</p>
                    <div v-for="c in datos.categorias_mas_vendidas" :key="c.nombre" class="mb-3">
                        <div class="d-flex justify-content-between fs-sm">
                            <span>{{ c.nombre }}</span><span class="fw-semibold">{{ money(c.monto) }}</span>
                        </div>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar reporte-progress-bar-info"
                                :style="{ width: (c.monto / categoriaMax) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Evolución del costo de materiales</span></div>
        <div class="card-body">
            <p v-if="!costosSeries.length" class="text-muted mb-0">
                Se necesita más de un registro de precio por material (se genera al aprobar compras).
            </p>
            <LineChart v-else :series="costosSeries" :labels="costosLabels" :format-y="money" />
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Estacionalidad (pedidos por mes calendario)</span></div>
        <div class="card-body">
            <div class="bar-chart">
                <div v-for="m in datos.demanda.estacionalidad" :key="m.mes" class="bar-chart-col">
                    <span class="bar-chart-value">{{ m.pedidos }}</span>
                    <div class="bar-chart-bar" :style="{ height: (m.pedidos / estacionalidadMax) * 100 + '%' }"></div>
                    <span class="bar-chart-label">{{ m.mes }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
