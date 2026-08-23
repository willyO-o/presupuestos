<script setup>
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

const indicators = [
    { value: '24', label: 'Cotizaciones activas', icon: 'document', tone: 'primary' },
    { value: '8', label: 'En producción', icon: 'tools', tone: 'warning' },
    { value: 'Bs 186k', label: 'Monto presupuestado', icon: 'money', tone: 'success' },
    { value: '92%', label: 'Margen promedio', icon: 'trend', tone: 'info' },
];

const months = [
    ['Ene', 38], ['Feb', 52], ['Mar', 45], ['Abr', 74], ['May', 61], ['Jun', 49],
    ['Jul', 68], ['Ago', 55], ['Sep', 81], ['Oct', 44], ['Nov', 64], ['Dic', 57],
];

const quotes = [
    { number: 'COT-0241', customer: 'Farmacias Chávez', project: 'Material POP campaña invierno', total: 'Bs 18.450', status: 'Enviado', tone: 'info' },
    { number: 'COT-0240', customer: 'Café Alexander', project: 'Señalética y menú exterior', total: 'Bs 8.920', status: 'Aprobado', tone: 'success' },
    { number: 'COT-0239', customer: 'Hipermaxi', project: 'Exhibidor promocional de bebidas', total: 'Bs 32.760', status: 'En revisión', tone: 'warning' },
    { number: 'COT-0238', customer: 'Entel', project: 'Rotulado de punto de venta', total: 'Bs 12.600', status: 'Borrador', tone: 'secondary' },
];
</script>

<template>
    <div class="dashboard-page">
        <Head title="Dashboard" />

        <section class="dashboard-kpis">
            <article v-for="indicator in indicators" :key="indicator.label" class="stat-card">
                <span class="dashboard-kpi-icon" :class="`dashboard-kpi-icon-${indicator.tone}`">
                    <svg v-if="indicator.icon === 'document'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l4 4v14H7zM14 3v5h5M10 12h5M10 16h5" /></svg>
                    <svg v-else-if="indicator.icon === 'tools'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.7 6.3 3-3a5 5 0 0 1-6.4 6.4L4.7 16.3a2.1 2.1 0 0 0 3 3l6.6-6.6a5 5 0 0 1 6.4-6.4l-3 3" /></svg>
                    <svg v-else-if="indicator.icon === 'money'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 9h.01M17 15h.01M12 8v8m2-6.2c-.4-.5-1.1-.8-2-.8-1.1 0-2 .7-2 1.6 0 2.4 4 1.2 4 3.7 0 .9-.9 1.7-2 1.7-.9 0-1.7-.3-2.1-.9" /></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 16 10 10l4 4 6-7M15 7h5v5" /></svg>
                </span>
                <div><p class="stat-card-value">{{ indicator.value }}</p><p class="stat-card-label">{{ indicator.label }}</p></div>
            </article>
        </section>

        <section class="dashboard-grid dashboard-grid-primary">
            <article class="card dashboard-chart-card">
                <div class="card-header"><div><h2 class="card-title">Presupuesto mensual</h2><p class="card-subtitle">Evolución del monto cotizado</p></div><button class="btn-soft-primary btn-sm">Este año⌄</button></div>
                <div class="card-body"><div class="dashboard-chart"><div v-for="month in months" :key="month[0]" class="dashboard-chart-column"><span>{{ month[1] }}k</span><i :style="{ height: `${month[1]}%` }"></i><small>{{ month[0] }}</small></div></div></div>
            </article>

            <article class="card"><div class="card-header"><div><h2 class="card-title">Órdenes por estado</h2><p class="card-subtitle">Producción en curso</p></div><button class="btn-link btn-sm">Ver todas</button></div><div class="card-body dashboard-status-list"><div><span class="badge-soft-warning">08</span><p><strong>En producción</strong><small>Ordenes activas en taller</small></p></div><div><span class="badge-soft-info">12</span><p><strong>Por confirmar</strong><small>Esperando aprobación del cliente</small></p></div><div><span class="badge-soft-success">19</span><p><strong>Completadas</strong><small>Durante el presente mes</small></p></div><div><span class="badge-soft-danger">03</span><p><strong>Con retraso</strong><small>Requieren seguimiento inmediato</small></p></div></div></article>
        </section>

        <section class="dashboard-grid dashboard-grid-secondary">
            <article class="card dashboard-table-card"><div class="card-header"><div><h2 class="card-title">Cotizaciones recientes</h2><p class="card-subtitle">Últimas propuestas creadas</p></div><button class="btn-outline-primary btn-sm">Ver cotizaciones</button></div><div class="dashboard-table-scroll"><table class="table-app"><thead><tr><th>Código</th><th>Cliente / proyecto</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="quote in quotes" :key="quote.number"><td><strong>{{ quote.number }}</strong></td><td><strong>{{ quote.customer }}</strong><small>{{ quote.project }}</small></td><td>{{ quote.total }}</td><td><span class="badge" :class="`badge-soft-${quote.tone}`">{{ quote.status }}</span></td><td><button class="btn-link btn-sm">•••</button></td></tr></tbody></table></div></article>
            <article class="card dashboard-summary-card"><div class="card-header"><div><h2 class="card-title">Resumen del mes</h2><p class="card-subtitle">Junio 2026</p></div></div><div class="card-body"><div class="dashboard-donut"><span>68<small>%</small></span></div><p class="dashboard-summary-copy">Meta mensual de cotizaciones</p><div class="dashboard-summary-metrics"><div><span>Bs 186k</span><small>Presupuestado</small></div><div><span>Bs 126k</span><small>Aprobado</small></div></div><button class="btn-primary dashboard-full-button">Crear cotización</button></div></article>
        </section>
    </div>
</template>
