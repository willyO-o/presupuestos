<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    compras: { type: Object, required: true },
    proveedores: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('compras.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
        proveedor: props.filters.proveedor ?? '',
    },
    mode: 'manual',
    only: ['compras', 'filters'],
});

const headers = [
    { label: '#', key: 'id' },
    { label: 'Proveedor', key: 'proveedor' },
    { label: 'Factura', key: 'numero_factura', class: 'text-center', cellClass: 'text-center' },
    { label: 'Fecha', key: 'fecha', class: 'text-center', cellClass: 'text-center' },
    { label: 'Ítems', key: 'detalles_count', class: 'text-center', cellClass: 'text-center' },
    { label: 'Total', key: 'total', class: 'text-end', cellClass: 'text-end' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    PAGADA: 'badge-soft-success',
    ANULADA: 'badge-soft-secondary',
};

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}

const accionForm = useForm({});

async function aprobar(compra) {
    const ok = await confirmation(
        `¿Aprobar la compra <strong>#${compra.id}</strong>? Se sumará el stock y se actualizará el precio de los materiales.`,
        'Aprobar compra',
        'Sí, aprobar',
    );
    if (!ok) return;

    accionForm.post(route('compras.aprobar', compra.id), { preserveScroll: true });
}

async function anular(compra) {
    const ok = await confirmation(
        `¿Anular la compra <strong>#${compra.id}</strong>?`,
        'Anular compra',
        'Sí, anular',
    );
    if (!ok) return;

    accionForm.post(route('compras.anular', compra.id), { preserveScroll: true });
}

async function eliminar(compra) {
    const ok = await confirmation(
        `¿Eliminar la compra <strong>#${compra.id}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar compra',
    );
    if (!ok) return;

    router.delete(route('compras.destroy', compra.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Compras" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="N.º de factura o proveedor..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-proveedor">Proveedor</label>
                    <select id="f-proveedor" v-model="table.filters.proveedor" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.nombre }}</option>
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
            <span class="card-title">Listado de compras</span>
            <Link v-can="'compras.crear'" :href="route('compras.create')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i>
                Nueva compra
            </Link>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="compras.data" :paginator="compras" :loading="table.loading"
                empty-text="No hay compras registradas." @page-change="table.changePage">
                <template #cell-id="{ item }">
                    <Link :href="route('compras.show', item.id)" class="fw-semibold text-primary">#{{ item.id }}</Link>
                </template>

                <template #cell-proveedor="{ item }">
                    {{ item.proveedor?.nombre ?? '—' }}
                </template>

                <template #cell-numero_factura="{ item }">
                    {{ item.numero_factura ?? '—' }}
                </template>

                <template #cell-fecha="{ item }">
                    {{ fecha(item.fecha) }}
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
                        <Link :href="route('compras.show', item.id)" class="btn btn-sm btn-icon btn-soft-info"
                            aria-label="Ver compra">
                            <i class="fa-solid fa-eye"></i>
                        </Link>
                        <Link v-if="item.estado === 'PENDIENTE'" v-can="'compras.editar'"
                            :href="route('compras.edit', item.id)" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar compra">
                            <i class="fa-solid fa-pen"></i>
                        </Link>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'compras.aprobar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-success" aria-label="Aprobar compra"
                            @click="aprobar(item)">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'compras.aprobar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-warning" aria-label="Anular compra" @click="anular(item)">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                        <button v-if="item.estado !== 'PAGADA'" v-can="'compras.eliminar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-danger" aria-label="Eliminar compra"
                            @click="eliminar(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
