<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    pagos: { type: Object, required: true },
    resumen: { type: Object, required: true },
    metodos: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('pagos.index'),
    filters: { estado: props.filters.estado ?? '', metodo: props.filters.metodo ?? '' },
    mode: 'manual',
    only: ['pagos', 'resumen', 'filters'],
});

const headers = [
    { label: 'Pedido', key: 'pedido' },
    { label: 'Fecha', key: 'fecha_pago', class: 'text-center', cellClass: 'text-center' },
    { label: 'Método', key: 'metodo_pago', class: 'text-center', cellClass: 'text-center' },
    { label: 'Monto', key: 'monto', class: 'text-end', cellClass: 'text-end' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = { PENDIENTE: 'badge-soft-warning', PARCIAL: 'badge-soft-info', PAGADO: 'badge-soft-success' };

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}
</script>

<template>
    <Head title="Pagos" />

    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-success"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div>
                    <p class="text-muted fs-sm mb-0">Total cobrado</p>
                    <p class="fs-xl fw-bold mb-0">{{ money(resumen.total_cobrado) }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <p class="text-muted fs-sm mb-0">Por cobrar (pedidos activos)</p>
                    <p class="fs-xl fw-bold mb-0">{{ money(resumen.por_cobrar) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-3">
                    <label class="form-label" for="f-estado">Estado</label>
                    <select id="f-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-metodo">Método</label>
                    <select id="f-metodo" v-model="table.filters.metodo" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="m in metodos" :key="m" :value="m">{{ m }}</option>
                    </select>
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
        <div class="card-header"><span class="card-title">Pagos registrados</span></div>
        <div class="card-body">
            <DataTable :headers="headers" :items="pagos.data" :paginator="pagos" :loading="table.loading"
                empty-text="No hay pagos registrados. Se registran desde un pedido." @page-change="table.changePage">
                <template #cell-pedido="{ item }">
                    <Link :href="route('pedidos.show', item.pedido_id)" class="fw-semibold text-primary">
                        {{ item.pedido?.numero_pedido ?? '—' }}
                    </Link>
                </template>
                <template #cell-fecha_pago="{ item }">{{ fecha(item.fecha_pago) }}</template>
                <template #cell-metodo_pago="{ item }">
                    <span class="badge badge-soft-secondary">{{ item.metodo_pago }}</span>
                </template>
                <template #cell-monto="{ item }">{{ money(item.monto) }}</template>
                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">{{ item.estado }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
