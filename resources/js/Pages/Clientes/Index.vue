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
    clientes: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

/**
 * Filtros manuales: solo se consultan al enviar el formulario (botón
 * "Buscar") o al cambiar de página, no en cada tecla. Para que el
 * filtrado sea automático (con debounce) al cambiar los datos, alcanza
 * con cambiar `mode` a 'auto' aquí.
 */
const table = useServerTable({
    url: route('clientes.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['clientes', 'filters'],
});

const headers = [
    { label: 'Razón social', key: 'razon_social' },
    { label: 'Tipo', key: 'tipo', class: 'text-center', cellClass: 'text-center' },
    { label: 'NIT', key: 'nit' },
    { label: 'Contacto', key: 'contacto_nombre' },
    { label: 'Teléfono', key: 'telefono' },
    { label: 'Ciudad', key: 'ciudad' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingCliente = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    tipo: 'JURIDICO',
    razon_social: '',
    nit: '',
    contacto_nombre: '',
    telefono: '',
    email: '',
    direccion: '',
    ciudad: '',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingCliente.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(cliente) {
    editingCliente.value = cliente;
    form.clearErrors();
    form.tipo = cliente.tipo;
    form.razon_social = cliente.razon_social;
    form.nit = cliente.nit;
    form.contacto_nombre = cliente.contacto_nombre;
    form.telefono = cliente.telefono;
    form.email = cliente.email;
    form.direccion = cliente.direccion;
    form.ciudad = cliente.ciudad;
    form.estado = cliente.estado;
    showFormModal.value = true;
}

function closeFormModal() {
    showFormModal.value = false;
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeFormModal(),
    };

    if (editingCliente.value) {
        form.put(route('clientes.update', editingCliente.value.id), options);
    } else {
        form.post(route('clientes.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(cliente) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar el cliente <strong>${cliente.razon_social}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar cliente',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('clientes.destroy', cliente.id), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head title="Clientes" />

    <!-- Filtros: búsqueda + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Razón social, NIT o contacto..." />
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-estado">Estado</label>
                    <select id="filter-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
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
            <span class="card-title">Listado de clientes</span>
            <button v-can="'clientes.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo cliente
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="clientes.data" :paginator="clientes" :loading="table.loading"
                empty-text="No hay clientes registrados." @page-change="table.changePage">
                <template #cell-tipo="{ value }">
                    <span class="badge badge-soft-secondary">
                        {{ value === 'NATURAL' ? 'Natural' : 'Jurídico' }}
                    </span>
                </template>

                <template #cell-contacto_nombre="{ value }">
                    {{ value || '—' }}
                </template>

                <template #cell-telefono="{ value }">
                    {{ value || '—' }}
                </template>

                <template #cell-ciudad="{ value }">
                    {{ value || '—' }}
                </template>

                <template #cell-estado="{ item }">
                    <span class="badge" :class="item.estado === 'ACTIVO'
                            ? 'badge-soft-success'
                            : 'badge-soft-danger'
                        ">
                        {{ item.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <div class="d-flex gap-1">
                        <button v-can="'clientes.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar cliente" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'clientes.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar cliente" @click="confirmDelete(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal crear / editar -->
    <Modal :show="showFormModal" max-width="lg" @close="closeFormModal">
        <div class="card-header">
            <span class="card-title">
                {{ editingCliente ? 'Editar cliente' : 'Nuevo cliente' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="tipo">Tipo</label>
                            <select id="tipo" v-model="form.tipo" class="form-control">
                                <option value="JURIDICO">Jurídico</option>
                                <option value="NATURAL">Natural</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="form-group">
                            <label class="form-label" for="razon_social">Razón social</label>
                            <input id="razon_social" v-model="form.razon_social" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.razon_social }" required autofocus />
                            <p v-if="form.errors.razon_social" class="form-error">
                                {{ form.errors.razon_social }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="nit">NIT</label>
                            <input id="nit" v-model="form.nit" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.nit }" required />
                            <p v-if="form.errors.nit" class="form-error">
                                {{ form.errors.nit }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="contacto_nombre">Contacto</label>
                            <input id="contacto_nombre" v-model="form.contacto_nombre" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.contacto_nombre }" />
                            <p v-if="form.errors.contacto_nombre" class="form-error">
                                {{ form.errors.contacto_nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input id="telefono" v-model="form.telefono" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.telefono }" />
                            <p v-if="form.errors.telefono" class="form-error">
                                {{ form.errors.telefono }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" v-model="form.email" type="email" class="form-control"
                                :class="{ 'is-invalid': form.errors.email }" />
                            <p v-if="form.errors.email" class="form-error">
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="direccion">Dirección</label>
                            <input id="direccion" v-model="form.direccion" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.direccion }" />
                            <p v-if="form.errors.direccion" class="form-error">
                                {{ form.errors.direccion }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="ciudad">Ciudad</label>
                            <input id="ciudad" v-model="form.ciudad" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.ciudad }" />
                            <p v-if="form.errors.ciudad" class="form-error">
                                {{ form.errors.ciudad }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="estado">Estado</label>
                    <select id="estado" v-model="form.estado" class="form-control">
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeFormModal">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    {{ editingCliente ? 'Guardar cambios' : 'Crear cliente' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
