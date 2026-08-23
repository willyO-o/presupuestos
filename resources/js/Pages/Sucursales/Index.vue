<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    sucursales: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

/**
 * Filtros manuales: solo se consultan al enviar el formulario (botón
 * "Buscar") o al cambiar de página, no en cada tecla. Para que el
 * filtrado sea automático (con debounce) al cambiar los datos, alcanza
 * con cambiar `mode` a 'auto' aquí.
 */
const table = useServerTable({
    url: route('sucursales.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['sucursales', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'nombre' },
    { label: 'Dirección', key: 'direccion' },
    { label: 'Teléfono', key: 'telefono' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingSucursal = ref(null);

const form = useForm({
    nombre: '',
    direccion: '',
    telefono: '',
    estado: 'ACTIVO',
});

function openCreate() {
    editingSucursal.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(sucursal) {
    editingSucursal.value = sucursal;
    form.clearErrors();
    form.nombre = sucursal.nombre;
    form.direccion = sucursal.direccion;
    form.telefono = sucursal.telefono;
    form.estado = sucursal.estado;
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

    if (editingSucursal.value) {
        form.put(route('sucursales.update', editingSucursal.value.id), options);
    } else {
        form.post(route('sucursales.store'), options);
    }
}

/* ── Modal confirmar eliminación ─────────────────────────────────────── */

const deletingSucursal = ref(null);
const deleteForm = useForm({});

function destroySucursal() {
    deleteForm.delete(route('sucursales.destroy', deletingSucursal.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingSucursal.value = null;
        },
    });
}
</script>

<template>
    <Head title="Sucursales" />

    <div
        v-if="flashSuccess"
        class="bg-soft-success mb-4 flex items-center gap-2 rounded-lg px-4 py-3 text-sm"
    >
        <i class="fa-solid fa-circle-check"></i>
        {{ flashSuccess }}
    </div>

    <!-- Filtros: búsqueda + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input
                        id="filter-search"
                        v-model="table.filters.search"
                        type="text"
                        class="form-control"
                        placeholder="Nombre, dirección o teléfono..."
                    />
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-estado">Estado</label>
                    <select
                        id="filter-estado"
                        v-model="table.filters.estado"
                        class="form-control"
                    >
                        <option value="">Todos</option>
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </div>

                <div class="col-lg-3 flex items-end gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="table.loading"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Buscar
                    </button>
                    <button
                        type="button"
                        class="btn btn-soft-secondary"
                        :disabled="table.loading"
                        @click="table.reset"
                    >
                        Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Listado de sucursales</span>
            <button
                v-can="'sucursales.crear'"
                type="button"
                class="btn btn-primary btn-sm"
                @click="openCreate"
            >
                <i class="fa-solid fa-plus"></i>
                Nueva sucursal
            </button>
        </div>

        <div class="card-body">
            <DataTable
                :headers="headers"
                :items="sucursales.data"
                :paginator="sucursales"
                :loading="table.loading"
                empty-text="No hay sucursales registradas."
                @page-change="table.changePage"
            >
                <template #cell-estado="{ item }">
                    <span
                        class="badge"
                        :class="
                            item.estado === 'ACTIVO'
                                ? 'badge-soft-success'
                                : 'badge-soft-danger'
                        "
                    >
                        {{ item.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <button
                        v-can="'sucursales.editar'"
                        type="button"
                        class="btn btn-icon btn-soft-primary"
                        aria-label="Editar sucursal"
                        @click="openEdit(item)"
                    >
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button
                        v-can="'sucursales.eliminar'"
                        type="button"
                        class="btn btn-icon btn-soft-danger"
                        aria-label="Eliminar sucursal"
                        @click="deletingSucursal = item"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal crear / editar -->
    <Modal :show="showFormModal" max-width="md" @close="closeFormModal">
        <div class="card-header">
            <span class="card-title">
                {{ editingSucursal ? 'Editar sucursal' : 'Nueva sucursal' }}
            </span>
            <button
                type="button"
                class="modal-close"
                aria-label="Cerrar"
                @click="closeFormModal"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input
                        id="nombre"
                        v-model="form.nombre"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': form.errors.nombre }"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.nombre" class="form-error">
                        {{ form.errors.nombre }}
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="direccion">Dirección</label>
                    <input
                        id="direccion"
                        v-model="form.direccion"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': form.errors.direccion }"
                        required
                    />
                    <p v-if="form.errors.direccion" class="form-error">
                        {{ form.errors.direccion }}
                    </p>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input
                                id="telefono"
                                v-model="form.telefono"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.telefono }"
                                required
                            />
                            <p v-if="form.errors.telefono" class="form-error">
                                {{ form.errors.telefono }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="estado">Estado</label>
                            <select
                                id="estado"
                                v-model="form.estado"
                                class="form-control"
                            >
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-soft-secondary"
                    @click="closeFormModal"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="btn btn-primary"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    {{ editingSucursal ? 'Guardar cambios' : 'Crear sucursal' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Modal confirmar eliminación -->
    <Modal
        :show="!!deletingSucursal"
        max-width="sm"
        @close="deletingSucursal = null"
    >
        <div class="card-header">
            <span class="card-title">Eliminar sucursal</span>
            <button
                type="button"
                class="modal-close"
                aria-label="Cerrar"
                @click="deletingSucursal = null"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="card-body">
            <p class="text-sm">
                ¿Seguro que deseas eliminar la sucursal
                <strong>{{ deletingSucursal?.nombre }}</strong>? Esta acción
                no se puede deshacer.
            </p>
        </div>

        <div class="modal-footer">
            <button
                type="button"
                class="btn btn-soft-secondary"
                @click="deletingSucursal = null"
            >
                Cancelar
            </button>
            <button
                type="button"
                class="btn btn-danger"
                :class="{ 'opacity-50': deleteForm.processing }"
                :disabled="deleteForm.processing"
                @click="destroySucursal"
            >
                Eliminar
            </button>
        </div>
    </Modal>
</template>
