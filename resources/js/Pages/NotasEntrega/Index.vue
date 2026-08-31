<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    notas: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('notas-entrega.index'),
    filters: { search: props.filters.search ?? '' },
    mode: 'manual',
    only: ['notas', 'filters'],
});

const headers = [
    { label: 'N.º nota', key: 'numero_nota' },
    { label: 'Pedido', key: 'pedido', class: 'text-center', cellClass: 'text-center' },
    { label: 'Fecha', key: 'fecha_entrega', class: 'text-center', cellClass: 'text-center' },
    { label: 'Recibió', key: 'recibido_por' },
    { label: 'Ítems', key: 'detalles_count', class: 'text-center', cellClass: 'text-center' },
];

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}

function nombreEmpleado(e) {
    if (!e) return '—';
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}
</script>

<template>
    <Head title="Notas de entrega" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="N.º de nota, pedido o quien recibió..." />
                </div>
                <div class="col-lg-6 flex items-end gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="table.loading">Buscar</button>
                    <button type="button" class="btn btn-soft-secondary" :disabled="table.loading" @click="table.reset">
                        Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Notas de entrega</span>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="notas.data" :paginator="notas" :loading="table.loading"
                empty-text="No hay notas de entrega. Se emiten desde un pedido." @page-change="table.changePage">
                <template #cell-numero_nota="{ item }">
                    <Link :href="route('notas-entrega.show', item.id)" class="fw-semibold text-primary">
                        {{ item.numero_nota }}
                    </Link>
                </template>
                <template #cell-pedido="{ item }">{{ item.pedido?.numero_pedido ?? '—' }}</template>
                <template #cell-fecha_entrega="{ item }">{{ fecha(item.fecha_entrega) }}</template>
                <template #cell-recibido_por="{ item }">
                    {{ item.recibido_por ?? nombreEmpleado(item.empleado) }}
                </template>
                <template #actions="{ item }">
                    <Link :href="route('notas-entrega.show', item.id)" class="btn btn-sm btn-icon btn-soft-info"
                        aria-label="Ver nota">
                        <i class="fa-solid fa-eye"></i>
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>
</template>
