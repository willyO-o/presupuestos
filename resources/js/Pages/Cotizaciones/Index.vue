<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    cotizaciones: { type: Object, required: true },
    clientes: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('cotizaciones.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
        cliente: props.filters.cliente ?? '',
        sucursal: props.filters.sucursal ?? '',
    },
    mode: 'manual',
    only: ['cotizaciones', 'filters'],
});

const headers = [
    { label: 'Código', key: 'codigo_verificacion' },
    { label: 'Cliente', key: 'cliente' },
    { label: 'Sucursal', key: 'sucursal', class: 'text-center', cellClass: 'text-center' },
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

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}

const accionForm = useForm({});

async function cambiarEstado(cotizacion, accion) {
    const textos = {
        aprobar: ['¿Aprobar', 'Aprobar cotización', 'Sí, aprobar'],
        rechazar: ['¿Rechazar', 'Rechazar cotización', 'Sí, rechazar'],
    };
    const [verbo, titulo, boton] = textos[accion];

    const ok = await confirmation(
        `${verbo} la cotización <strong>${cotizacion.codigo_verificacion}</strong>?`,
        titulo,
        boton,
    );
    if (!ok) return;

    accionForm.post(route(`cotizaciones.${accion}`, cotizacion.id), { preserveScroll: true });
}

async function eliminar(cotizacion) {
    const ok = await confirmation(
        `¿Eliminar la cotización <strong>${cotizacion.codigo_verificacion}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar cotización',
    );
    if (!ok) return;

    router.delete(route('cotizaciones.destroy', cotizacion.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Cotizaciones" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-3">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Código, cliente..." />
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="f-estado">Estado</label>
                    <select id="f-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-cliente">Cliente</label>
                    <select id="f-cliente" v-model="table.filters.cliente" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.razon_social }}</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="f-sucursal">Sucursal</label>
                    <select id="f-sucursal" v-model="table.filters.sucursal" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                </div>
                <div class="col-lg-2 flex items-end gap-2">
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
            <span class="card-title">Listado de cotizaciones</span>
            <Link v-can="'cotizaciones.crear'" :href="route('cotizaciones.create')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i>
                Nueva cotización
            </Link>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="cotizaciones.data" :paginator="cotizaciones" :loading="table.loading"
                empty-text="No hay cotizaciones registradas." @page-change="table.changePage">
                <template #cell-codigo_verificacion="{ item }">
                    <Link :href="route('cotizaciones.show', item.id)" class="fw-semibold text-primary">
                        {{ item.codigo_verificacion }}
                    </Link>
                </template>

                <template #cell-cliente="{ item }">
                    {{ item.cliente?.razon_social ?? '—' }}
                </template>

                <template #cell-sucursal="{ item }">
                    {{ item.sucursal?.nombre ?? '—' }}
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
                        <Link :href="route('cotizaciones.show', item.id)" class="btn btn-sm btn-icon btn-soft-info"
                            aria-label="Ver cotización">
                            <i class="fa-solid fa-eye"></i>
                        </Link>
                        <Link v-if="item.estado === 'PENDIENTE'" v-can="'cotizaciones.editar'"
                            :href="route('cotizaciones.edit', item.id)" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar cotización">
                            <i class="fa-solid fa-pen"></i>
                        </Link>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'cotizaciones.aprobar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-success" aria-label="Aprobar cotización"
                            @click="cambiarEstado(item, 'aprobar')">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'cotizaciones.aprobar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-warning" aria-label="Rechazar cotización"
                            @click="cambiarEstado(item, 'rechazar')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button v-if="item.estado !== 'CONVERTIDA'" v-can="'cotizaciones.eliminar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-danger" aria-label="Eliminar cotización"
                            @click="eliminar(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
