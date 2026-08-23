<script setup>
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

/* ------------------------------------------------------------------ */
/* Datos estaticos de maquetado                                        */
/* ------------------------------------------------------------------ */
const stats = [
    { label: 'Litros despachados', value: '17.6k', icon: 'success' },
    { label: 'Presupuestos activos', value: '149', icon: 'warning' },
    { label: 'Solicitudes aprobadas', value: '24.8k', icon: 'danger' },
    { label: 'Consumo mensual', value: '54.3k', icon: 'primary' },
];

const chartData = [
    { label: 'Ene', value: 2.5 },
    { label: 'Feb', value: 3.2 },
    { label: 'Mar', value: 5 },
    { label: 'Abr', value: 10.1 },
    { label: 'May', value: 4.2 },
    { label: 'Jun', value: 3.8 },
    { label: 'Jul', value: 3 },
    { label: 'Ago', value: 2.4 },
    { label: 'Sep', value: 4 },
    { label: 'Oct', value: 1.2 },
    { label: 'Nov', value: 3.5 },
    { label: 'Dic', value: 0.8 },
];
const chartMax = Math.max(...chartData.map((item) => item.value));

const shares = [
    { label: 'Estaciones propias', value: '32k', color: 'primary' },
    { label: 'Proveedores externos', value: '13k', color: 'danger' },
    { label: 'Transporte', value: '11k', color: 'success' },
    { label: 'Flotas corporativas', value: '19k', color: 'warning' },
    { label: 'Maquinaria pesada', value: '18k', color: 'pink' },
    { label: 'Generadores', value: '26k', color: 'info' },
];

const comments = [
    {
        author: 'Diana Kohler',
        text: 'Excelente ajuste en el presupuesto, la reduccion de consumo se nota desde la primera semana.',
    },
    {
        author: 'Tonya Noble',
        text: 'Muy util el detalle por estacion, me ayuda a proyectar el siguiente trimestre sin problema.',
    },
    {
        author: 'Donald Palmer',
        text: 'El reporte de litros despachados quedo bastante claro, gracias por compartirlo.',
    },
    {
        author: 'Joseph Parker',
        text: 'Buen resumen, facil de leer y con los datos que realmente necesitamos revisar.',
    },
];

const articles = [
    {
        title: 'Optimizacion de rutas para reducir consumo',
        date: '20 Sep, 2026',
        category: 'Logistica',
        badge: 'success',
        comments: 23,
        likes: 157,
        shared: 11,
        views: '2149',
    },
    {
        title: 'Como leer tu presupuesto de combustible mensual',
        date: '11 Feb, 2026',
        category: 'Finanzas',
        badge: 'info',
        comments: 547,
        likes: 1458,
        shared: 317,
        views: '34978',
    },
    {
        title: 'Mantenimiento preventivo y ahorro de litros',
        date: '15 Sep, 2026',
        category: 'Flotas',
        badge: 'warning',
        comments: 88,
        likes: 649,
        shared: 237,
        views: '1982',
    },
];

const devices = [
    { label: 'Camiones', value: 48, color: 'var(--c-primary)' },
    { label: 'Autos', value: 32, color: 'var(--c-success)' },
    { label: 'Maquinaria', value: 20, color: 'var(--c-warning)' },
];

const donutGradient = (() => {
    let acc = 0;
    const stops = devices.map((d) => {
        const start = acc;
        acc += d.value;
        return `${d.color} ${start}% ${acc}%`;
    });
    return `conic-gradient(${stops.join(', ')})`;
})();
</script>

<template>
    <Head title="Dashboard" />

    <div class="row">
        <!-- ================= STAT CARDS ================= -->
        <div v-for="stat in stats" :key="stat.label" class="col-lg-3">
            <div class="card">
                <div class="stat-card">
                    <span
                        class="stat-icon"
                        :class="`stat-icon-${stat.icon}`"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 15c3-6 7-9 8-9s5 3 8 9M4 15l8 5 8-5M4 15v3l8 5 8-5v-3"
                            />
                        </svg>
                    </span>
                    <div>
                        <p class="stat-value">{{ stat.value }}</p>
                        <p class="stat-label">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= BAR CHART ================= -->
        <div class="col-lg-6">
            <div class="card ">
                <div class="card-header">
                    <div>
                        <p class="card-title">Visitas al sitio</p>
                        <p class="card-subtitle">Consumo estimado por mes</p>
                    </div>
                    <span class="card-header-action">Semana actual</span>
                </div>
                <div class="card-body">
                    <div class="bar-chart">
                        <div
                            v-for="col in chartData"
                            :key="col.label"
                            class="bar-chart-col"
                        >
                            <span class="bar-chart-value">{{ col.value }}%</span>
                            <div
                                class="bar-chart-bar"
                                :style="{
                                    height: (col.value / chartMax) * 100 + '%',
                                }"
                            ></div>
                            <span class="bar-chart-label">{{
                                col.label
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TOP SHARES ================= -->
        <div class="col-lg-3">
            <div class="card ">
                <div class="card-header">
                    <span class="card-title">Principales canales</span>
                    <button type="button" class="btn-icon">
                        <svg
                            class="h-4 w-4 text-muted"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <circle cx="12" cy="5" r="1.5" />
                            <circle cx="12" cy="12" r="1.5" />
                            <circle cx="12" cy="19" r="1.5" />
                        </svg>
                    </button>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li
                            v-for="share in shares"
                            :key="share.label"
                            class="list-group-item"
                        >
                            <div class="list-group-item-start">
                                <span
                                    class="list-icon"
                                    :class="`stat-icon-${share.color}`"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <circle cx="12" cy="12" r="8" />
                                    </svg>
                                </span>
                                <span class="list-group-item-title">{{
                                    share.label
                                }}</span>
                            </div>
                            <span class="list-group-item-value">{{
                                share.value
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ================= RECENT COMMENTS ================= -->
        <div class="col-lg-3">
            <div class="card ">
                <div class="card-header">
                    <span class="card-title">Comentarios recientes</span>
                    <a href="#" class="card-link">Ver todo</a>
                </div>
                <div class="card-body">
                    <div class="comment-list">
                        <div
                            v-for="comment in comments"
                            :key="comment.author"
                            class="comment-item"
                        >
                            <span class="avatar avatar-xs">
                                <span class="avatar-title">{{
                                    comment.author.charAt(0)
                                }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="comment-author">
                                    {{ comment.author }}
                                    <span class="comment-meta"
                                        >ha comentado</span
                                    >
                                </p>
                                <p class="comment-text">
                                    &laquo;{{ comment.text }}&raquo;
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= RECENT ARTICLES TABLE ================= -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Reportes recientes</span>
                    <span class="card-header-action">Mas populares</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table-dashboard">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Titulo</th>
                                    <th>Fecha</th>
                                    <th>Categoria</th>
                                    <th>Comentarios</th>
                                    <th>Likes</th>
                                    <th>Vistas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(article, index) in articles"
                                    :key="article.title"
                                >
                                    <td class="text-muted">{{
                                        String(index + 1).padStart(2, '0')
                                    }}</td>
                                    <td>
                                        <span class="article-title">{{
                                            article.title
                                        }}</span>
                                    </td>
                                    <td class="text-muted fs-sm">{{
                                        article.date
                                    }}</td>
                                    <td>
                                        <span
                                            class="badge badge-pill"
                                            :class="`badge-soft-${article.badge}`"
                                            >{{ article.category }}</span
                                        >
                                    </td>
                                    <td class="text-muted fs-sm">{{
                                        article.comments
                                    }}</td>
                                    <td class="text-muted fs-sm">{{
                                        article.likes
                                    }}</td>
                                    <td class="fw-semibold fs-sm">{{
                                        article.views
                                    }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= PROFILE WIDGET ================= -->
        <div class="col-lg-3">
            <div class="card">
                <div class="profile-widget-header">
                    <span class="fs-xs fw-semibold text-muted">Perfil</span>
                    <button type="button" class="btn btn-light btn-sm">
                        Ajustes
                    </button>
                </div>
                <div class="profile-widget-body">
                    <span class="avatar avatar-md">
                        <span class="avatar-title fs-lg">{{
                            $page.props.auth.user?.name?.charAt(0) ?? 'U'
                        }}</span>
                    </span>
                    <p class="profile-widget-name">{{
                        $page.props.auth.user?.name ?? 'Usuario'
                    }}</p>
                    <p class="profile-widget-role">Administrador</p>
                </div>
                <div class="profile-stats">
                    <div class="profile-stat">
                        <span class="profile-stat-value">26</span>
                        <span class="profile-stat-label">Reportes</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">17k</span>
                        <span class="profile-stat-label">Estaciones</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">487k</span>
                        <span class="profile-stat-label">Litros</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= USED DEVICE (DONUT) ================= -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Vehiculos activos</span>
                    <button type="button" class="btn-icon">
                        <svg
                            class="h-4 w-4 text-muted"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <circle cx="12" cy="5" r="1.5" />
                            <circle cx="12" cy="12" r="1.5" />
                            <circle cx="12" cy="19" r="1.5" />
                        </svg>
                    </button>
                </div>
                <div class="card-body">
                    <div
                        class="donut-chart"
                        :style="{ background: donutGradient }"
                    >
                        <div class="donut-chart-center">
                            <span class="donut-value">100%</span>
                            <span class="donut-label">Total</span>
                        </div>
                    </div>
                    <div class="donut-legend mt-4">
                        <div
                            v-for="device in devices"
                            :key="device.label"
                            class="donut-legend-item"
                        >
                            <span class="d-flex align-items-center gap-2">
                                <span
                                    class="donut-legend-dot"
                                    :style="{ backgroundColor: device.color }"
                                ></span>
                                {{ device.label }}
                            </span>
                            <span class="fw-semibold">{{
                                device.value
                            }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
