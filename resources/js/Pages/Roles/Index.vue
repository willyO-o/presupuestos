<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

defineProps({
    roles: { type: Array, default: () => [] },
});

const headers = [
    { label: 'Rol', key: 'name' },
    { label: 'Permisos', key: 'permissions_count', class: 'text-center', cellClass: 'text-center' },
    { label: 'Usuarios', key: 'users_count', class: 'text-center', cellClass: 'text-center' },
];

async function eliminar(rol) {
    if (!(await confirmation(`¿Eliminar el rol <strong>${rol.name}</strong>?`, 'Eliminar rol'))) return;
    router.delete(route('roles.destroy', rol.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Roles y permisos" />

    <div class="card">
        <div class="card-header">
            <span class="card-title">Roles del sistema</span>
            <Link v-can="'roles.crear'" :href="route('roles.create')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i>
                Nuevo rol
            </Link>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="roles" empty-text="No hay roles.">
                <template #cell-name="{ item }">
                    <span class="fw-semibold">{{ item.name }}</span>
                    <span v-if="item.name === 'super-admin'" class="badge badge-soft-dark ms-2">acceso total</span>
                </template>
                <template #actions="{ item }">
                    <div class="d-flex gap-1 justify-content-end">
                        <Link v-if="item.name !== 'super-admin'" v-can="'roles.editar'"
                            :href="route('roles.edit', item.id)" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar rol">
                            <i class="fa-solid fa-pen"></i>
                        </Link>
                        <button v-if="item.name !== 'super-admin' && item.users_count === 0" v-can="'roles.eliminar'"
                            type="button" class="btn btn-sm btn-icon btn-soft-danger" aria-label="Eliminar rol"
                            @click="eliminar(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
