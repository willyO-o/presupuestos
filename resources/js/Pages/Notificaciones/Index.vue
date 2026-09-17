<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { tiempoRelativo } from '@/Utils/TimeAgo';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    notificaciones: { type: Object, required: true },
    noLeidas: { type: Number, default: 0 },
});

const table = useServerTable({
    url: route('notificaciones.index'),
    only: ['notificaciones', 'noLeidas'],
});

const headers = [
    { label: 'Notificación', key: 'titulo' },
    { label: 'Fecha', key: 'created_at', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'leida', class: 'text-center', cellClass: 'text-center' },
];

function marcarTodasLeidas() {
    router.post(route('notificaciones.marcar-todas'), {}, { preserveScroll: true });
}

function fecha(value) {
    return value ? new Date(value).toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="Notificaciones" />

    <div class="card">
        <div class="card-header">
            <span class="card-title">Notificaciones</span>
            <button
                v-if="noLeidas > 0"
                type="button"
                class="btn btn-soft-primary btn-sm"
                @click="marcarTodasLeidas"
            >
                Marcar todas como leídas ({{ noLeidas }})
            </button>
        </div>
        <div class="card-body">
            <DataTable
                :headers="headers"
                :items="notificaciones.data"
                :paginator="notificaciones"
                :loading="table.loading"
                empty-text="No tienes notificaciones todavía."
                @page-change="table.changePage"
            >
                <template #tbody="{ items }">
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            :class="{ 'notif-item-unread': !item.read_at }"
                        >
                            <td>
                                <Link :href="route('notificaciones.abrir', item.id)" class="list-group-item-start notif-link">
                                    <span class="list-icon" :class="`stat-icon-${item.data.color ?? 'primary'}`">
                                        <i :class="item.data.icono ?? 'fa-solid fa-bell'"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="list-group-item-title">{{ item.data.titulo }}</p>
                                        <p class="fs-xs text-muted">{{ item.data.mensaje }}</p>
                                    </div>
                                </Link>
                            </td>
                            <td class="text-center">
                                <span class="fs-sm">{{ fecha(item.created_at) }}</span>
                                <span class="d-block fs-xs text-muted">{{ tiempoRelativo(item.created_at) }}</span>
                            </td>
                            <td class="text-center">
                                <span v-if="!item.read_at" class="badge badge-soft-primary">No leída</span>
                                <span v-else class="badge badge-soft-secondary">Leída</span>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </DataTable>
        </div>
    </div>
</template>
