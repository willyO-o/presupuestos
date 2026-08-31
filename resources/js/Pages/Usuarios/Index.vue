<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    usuarios: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('usuarios.index'),
    filters: { search: props.filters.search ?? '', rol: props.filters.rol ?? '', estado: props.filters.estado ?? '' },
    mode: 'manual',
    only: ['usuarios', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'name' },
    { label: 'Email', key: 'email' },
    { label: 'Rol', key: 'roles', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const showModal = ref(false);
const editing = ref(null);

const form = useForm(() => ({
    _method: 'post',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    estado: 'ACTIVO',
    rol: props.roles[0] ?? '',
    foto: null,
}));

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.reset();
    form._method = 'post';
    showModal.value = true;
}

function openEdit(u) {
    editing.value = u;
    form.clearErrors();
    form.reset();
    form._method = 'put';
    form.name = u.name;
    form.email = u.email;
    form.estado = u.estado ?? 'ACTIVO';
    form.rol = u.roles?.[0]?.name ?? (props.roles[0] ?? '');
    showModal.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    if (editing.value) {
        form.post(route('usuarios.update', editing.value.id), options);
    } else {
        form.post(route('usuarios.store'), options);
    }
}

const deleteForm = useForm({});
async function eliminar(u) {
    if (!(await confirmation(`¿Eliminar la cuenta de <strong>${u.name}</strong>?`, 'Eliminar usuario'))) return;
    deleteForm.delete(route('usuarios.destroy', u.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre o email..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-rol">Rol</label>
                    <select id="f-rol" v-model="table.filters.rol" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
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
            <span class="card-title">Usuarios del sistema</span>
            <button v-can="'usuarios.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo usuario
            </button>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="usuarios.data" :paginator="usuarios" :loading="table.loading"
                empty-text="No hay usuarios." @page-change="table.changePage">
                <template #cell-roles="{ item }">
                    <span v-for="r in item.roles" :key="r.id" class="badge badge-soft-primary">{{ r.name }}</span>
                    <span v-if="!item.roles?.length" class="text-muted">—</span>
                </template>
                <template #cell-estado="{ item }">
                    <span class="badge" :class="item.estado === 'INACTIVO' ? 'badge-soft-danger' : 'badge-soft-success'">
                        {{ item.estado ?? 'ACTIVO' }}
                    </span>
                </template>
                <template #actions="{ item }">
                    <div class="d-flex gap-1 justify-content-end">
                        <button v-can="'usuarios.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'usuarios.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar" @click="eliminar(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <Modal :show="showModal" max-width="lg" @close="showModal = false">
        <div class="card-header">
            <span class="card-title">{{ editing ? 'Editar usuario' : 'Nuevo usuario' }}</span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="showModal = false">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form @submit.prevent="submit">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Nombre</label>
                            <input v-model="form.name" type="text" class="form-control" required />
                            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" required />
                            <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">
                                Contraseña <span v-if="editing" class="text-muted fs-xs">(vacío = sin cambio)</span>
                            </label>
                            <input v-model="form.password" type="password" class="form-control"
                                :required="!editing" autocomplete="new-password" />
                            <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Confirmar contraseña</label>
                            <input v-model="form.password_confirmation" type="password" class="form-control"
                                autocomplete="new-password" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Rol</label>
                            <select v-model="form.rol" class="form-control" required>
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p v-if="form.errors.rol" class="form-error">{{ form.errors.rol }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <select v-model="form.estado" class="form-control">
                                <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Foto de perfil (opcional)</label>
                    <input type="file" accept="image/*" class="form-control"
                        @input="form.foto = $event.target.files[0]" />
                    <p v-if="form.errors.foto" class="form-error">{{ form.errors.foto }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="showModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    {{ editing ? 'Guardar' : 'Crear' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
