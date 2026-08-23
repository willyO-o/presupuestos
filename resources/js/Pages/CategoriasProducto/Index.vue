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
    categoriasProducto: {
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
    url: route('categorias-producto.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['categoriasProducto', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'nombre' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingCategoria = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    nombre: '',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingCategoria.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(categoria) {
    editingCategoria.value = categoria;
    form.clearErrors();
    form.nombre = categoria.nombre;
    form.estado = categoria.estado;
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

    if (editingCategoria.value) {
        form.put(route('categorias-producto.update', editingCategoria.value.id), options);
    } else {
        form.post(route('categorias-producto.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(categoria) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar la categoría <strong>${categoria.nombre}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar categoría de producto',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('categorias-producto.destroy', categoria.id), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head title="Categorías de producto" />

    <!-- Filtros: búsqueda + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre de la categoría..." />
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
            <span class="card-title">Listado de categorías de producto</span>
            <button v-can="'categorias-producto.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nueva categoría
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="categoriasProducto.data" :paginator="categoriasProducto"
                :loading="table.loading" empty-text="No hay categorías de producto registradas."
                @page-change="table.changePage">
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
                        <button v-can="'categorias-producto.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar categoría" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'categorias-producto.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar categoría" @click="confirmDelete(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal crear / editar -->
    <Modal :show="showFormModal" max-width="md" @close="closeFormModal">
        <div class="card-header">
            <span class="card-title">
                {{ editingCategoria ? 'Editar categoría de producto' : 'Nueva categoría de producto' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input id="nombre" v-model="form.nombre" type="text" class="form-control"
                        :class="{ 'is-invalid': form.errors.nombre }" required autofocus />
                    <p v-if="form.errors.nombre" class="form-error">
                        {{ form.errors.nombre }}
                    </p>
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
                    {{ editingCategoria ? 'Guardar cambios' : 'Crear categoría' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
