<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientePortalLayout from '@/Layouts/ClientePortalLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: ClientePortalLayout });

defineProps({
    cotizaciones: { type: Object, required: true },
});

const headers = [
    { label: 'Código', key: 'codigo_verificacion' },
    { label: 'Fecha', key: 'fecha', class: 'text-center', cellClass: 'text-center' },
    { label: 'Total', key: 'total', class: 'text-end', cellClass: 'text-end' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    APROBADA: 'badge-soft-success',
    RECHAZADA: 'badge-soft-danger',
    CONVERTIDA: 'badge-soft-info',
    VENCIDA: 'badge-soft-secondary',
};

function money(v) {
    return `Bs ${Number(v ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function fecha(v) {
    return v ? new Date(v).toLocaleDateString('es-BO') : '—';
}
function irPagina(page) {
    router.get(route('portal.cotizaciones'), { page }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mis cotizaciones" />

    <div class="card">
        <div class="card-header">
            <span class="card-title">Cotizaciones</span>
            <Link :href="route('portal.solicitar')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Solicitar cotización
            </Link>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="cotizaciones.data" :paginator="cotizaciones"
                empty-text="Aún no tienes cotizaciones." @page-change="irPagina">
                <template #cell-codigo_verificacion="{ item }">
                    <Link :href="route('portal.cotizacion', item.id)" class="fw-semibold text-primary">
                        {{ item.codigo_verificacion }}
                    </Link>
                </template>
                <template #cell-fecha="{ item }">{{ fecha(item.fecha) }}</template>
                <template #cell-total="{ item }">{{ money(item.total) }}</template>
                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">{{ item.estado }}</span>
                </template>
                <template #actions="{ item }">
                    <Link :href="route('portal.cotizacion', item.id)" class="btn btn-sm btn-icon btn-soft-info">
                        <i class="fa-solid fa-eye"></i>
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>
</template>
