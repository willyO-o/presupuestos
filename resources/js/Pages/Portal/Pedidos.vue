<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ClientePortalLayout from '@/Layouts/ClientePortalLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';

defineOptions({ layout: ClientePortalLayout });

defineProps({
    pedidos: { type: Object, required: true },
});

const headers = [
    { label: 'N.º pedido', key: 'numero_pedido' },
    { label: 'Fecha', key: 'fecha_pedido', class: 'text-center', cellClass: 'text-center' },
    { label: 'Entrega estimada', key: 'fecha_entrega_estimada', class: 'text-center', cellClass: 'text-center' },
    { label: 'Total', key: 'total', class: 'text-end', cellClass: 'text-end' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = {
    DISENO: 'badge-soft-secondary',
    ELABORACION: 'badge-soft-info',
    ACABADO: 'badge-soft-warning',
    ENTREGADO: 'badge-soft-success',
    CANCELADO: 'badge-soft-danger',
};

function money(v) {
    return `Bs ${Number(v ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function fecha(v) {
    return v ? new Date(v).toLocaleDateString('es-BO') : '—';
}
function irPagina(page) {
    router.get(route('portal.pedidos'), { page }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mis pedidos" />

    <div class="card">
        <div class="card-header"><span class="card-title">Pedidos</span></div>
        <div class="card-body">
            <DataTable :headers="headers" :items="pedidos.data" :paginator="pedidos"
                empty-text="Aún no tienes pedidos en producción." @page-change="irPagina">
                <template #cell-numero_pedido="{ item }">
                    <Link :href="route('portal.pedido', item.id)" class="fw-semibold text-primary">
                        {{ item.numero_pedido }}
                    </Link>
                </template>
                <template #cell-fecha_pedido="{ item }">{{ fecha(item.fecha_pedido) }}</template>
                <template #cell-fecha_entrega_estimada="{ item }">{{ fecha(item.fecha_entrega_estimada) }}</template>
                <template #cell-total="{ item }">{{ money(item.total) }}</template>
                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">{{ item.estado }}</span>
                </template>
                <template #actions="{ item }">
                    <Link :href="route('portal.pedido', item.id)" class="btn btn-sm btn-icon btn-soft-info">
                        <i class="fa-solid fa-eye"></i>
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>
</template>
