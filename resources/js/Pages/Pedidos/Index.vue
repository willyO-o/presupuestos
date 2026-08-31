<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    pedidos: { type: Object, required: true },
    sucursales: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('pedidos.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
        sucursal: props.filters.sucursal ?? '',
    },
    mode: 'manual',
    only: ['pedidos', 'filters'],
});

const headers = [
    { label: 'N.º pedido', key: 'numero_pedido' },
    { label: 'Cliente', key: 'cliente' },
    { label: 'Sucursal', key: 'sucursal', class: 'text-center', cellClass: 'text-center' },
    { label: 'Fecha', key: 'fecha_pedido', class: 'text-center', cellClass: 'text-center' },
    { label: 'Entrega est.', key: 'fecha_entrega_estimada', class: 'text-center', cellClass: 'text-center' },
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

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}
</script>

<template>
    <Head title="Pedidos" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="N.º de pedido o cliente..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-sucursal">Sucursal</label>
                    <select id="f-sucursal" v-model="table.filters.sucursal" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="f-estado">Estado</label>
                    <select id="f-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div class="col-lg-3 flex items-end gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="table.loading">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Buscar
                    </button>
                    <button type="button" class="btn btn-soft-secondary" :disabled="table.loading" @click="table.reset">
                        Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Listado de pedidos</span>
            <Link v-can="'pedidos.crear'" :href="route('pedidos.create')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i>
                Nuevo pedido
            </Link>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="pedidos.data" :paginator="pedidos" :loading="table.loading"
                empty-text="No hay pedidos registrados." @page-change="table.changePage">
                <template #cell-numero_pedido="{ item }">
                    <Link :href="route('pedidos.show', item.id)" class="fw-semibold text-primary">
                        {{ item.numero_pedido }}
                    </Link>
                </template>

                <template #cell-cliente="{ item }">
                    {{ item.cotizacion?.cliente?.razon_social ?? '—' }}
                </template>

                <template #cell-sucursal="{ item }">
                    {{ item.cotizacion?.sucursal?.nombre ?? '—' }}
                </template>

                <template #cell-fecha_pedido="{ item }">
                    {{ fecha(item.fecha_pedido) }}
                </template>

                <template #cell-fecha_entrega_estimada="{ item }">
                    {{ fecha(item.fecha_entrega_estimada) }}
                </template>

                <template #cell-total="{ item }">
                    {{ money(item.total) }}
                </template>

                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">
                        {{ item.estado }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <div class="d-flex gap-1 justify-content-end">
                        <Link :href="route('pedidos.show', item.id)" class="btn btn-sm btn-icon btn-soft-info"
                            aria-label="Ver pedido">
                            <i class="fa-solid fa-eye"></i>
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
