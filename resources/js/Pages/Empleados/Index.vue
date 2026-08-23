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
    empleados: {
        type: Object,
        required: true,
    },
    sucursales: {
        type: Array,
        default: () => [],
    },
    areas: {
        type: Array,
        default: () => [],
    },
    usuarios: {
        type: Array,
        default: () => [],
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
    url: route('empleados.index'),
    filters: {
        search: props.filters.search ?? '',
        sucursal: props.filters.sucursal ?? '',
        area: props.filters.area ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['empleados', 'filters'],
});

const headers = [
    { label: 'Nombre completo', key: 'nombre_completo' },
    { label: 'CI', key: 'ci' },
    { label: 'Cargo', key: 'cargo' },
    { label: 'Sucursal', key: 'sucursal' },
    { label: 'Área', key: 'area' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingEmpleado = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    user_id: '',
    sucursal_id: props.sucursales[0]?.id ?? '',
    area_id: props.areas[0]?.id ?? '',
    nombres: '',
    paterno: '',
    materno: '',
    ci: '',
    cargo: '',
    telefono: '',
    fecha_ingreso: '',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingEmpleado.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(empleado) {
    editingEmpleado.value = empleado;
    form.clearErrors();
    form.user_id = empleado.user_id ?? '';
    form.sucursal_id = empleado.sucursal_id;
    form.area_id = empleado.area_id;
    form.nombres = empleado.nombres;
    form.paterno = empleado.paterno;
    form.materno = empleado.materno;
    form.ci = empleado.ci;
    form.cargo = empleado.cargo;
    form.telefono = empleado.telefono;
    form.fecha_ingreso = empleado.fecha_ingreso;
    form.estado = empleado.estado;
    showFormModal.value = true;
}

function closeFormModal() {
    showFormModal.value = false;
}

function submitForm() {
    // El select "Usuario" usa '' como opcion "Sin vincular"; el backend
    // espera null para la FK nullable, no una cadena vacia.
    const options = {
        preserveScroll: true,
        onSuccess: () => closeFormModal(),
    };

    form.transform((data) => ({ ...data, user_id: data.user_id || null }));

    if (editingEmpleado.value) {
        form.put(route('empleados.update', editingEmpleado.value.id), options);
    } else {
        form.post(route('empleados.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(empleado) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar al empleado <strong>${empleado.nombre_completo}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar empleado',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('empleados.destroy', empleado.id), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head title="Empleados" />

    <!-- Filtros: búsqueda + sucursal + área + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre, CI o cargo..." />
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-sucursal">Sucursal</label>
                    <select id="filter-sucursal" v-model="table.filters.sucursal" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                            {{ sucursal.nombre }}
                        </option>
                    </select>
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-area">Área</label>
                    <select id="filter-area" v-model="table.filters.area" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">
                            {{ area.nombre }}
                        </option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label" for="filter-estado">Estado</label>
                    <select id="filter-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </div>

                <div class="col-12 flex items-end gap-2">
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
            <span class="card-title">Listado de empleados</span>
            <button v-can="'empleados.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo empleado
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="empleados.data" :paginator="empleados" :loading="table.loading"
                empty-text="No hay empleados registrados." @page-change="table.changePage">
                <template #cell-sucursal="{ item }">
                    {{ item.sucursal?.nombre ?? '—' }}
                </template>

                <template #cell-area="{ item }">
                    {{ item.area?.nombre ?? '—' }}
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
                        <button v-can="'empleados.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar empleado" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'empleados.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar empleado" @click="confirmDelete(item)">
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
                {{ editingEmpleado ? 'Editar empleado' : 'Nuevo empleado' }}
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
                            <label class="form-label" for="nombres">Nombres</label>
                            <input id="nombres" v-model="form.nombres" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.nombres }" required autofocus />
                            <p v-if="form.errors.nombres" class="form-error">
                                {{ form.errors.nombres }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="paterno">Apellido paterno</label>
                            <input id="paterno" v-model="form.paterno" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.paterno }" />
                            <p v-if="form.errors.paterno" class="form-error">
                                {{ form.errors.paterno }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="materno">Apellido materno</label>
                            <input id="materno" v-model="form.materno" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.materno }" />
                            <p v-if="form.errors.materno" class="form-error">
                                {{ form.errors.materno }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="ci">CI</label>
                            <input id="ci" v-model="form.ci" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.ci }" required />
                            <p v-if="form.errors.ci" class="form-error">
                                {{ form.errors.ci }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="user_id">Cuenta de usuario (opcional)</label>
                            <select id="user_id" v-model="form.user_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.user_id }">
                                <option value="">Sin vincular</option>
                                <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
                                    {{ usuario.name }} ({{ usuario.email }})
                                </option>
                            </select>
                            <p v-if="form.errors.user_id" class="form-error">
                                {{ form.errors.user_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="sucursal_id">Sucursal</label>
                            <select id="sucursal_id" v-model="form.sucursal_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.sucursal_id }" required>
                                <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                                    {{ sucursal.nombre }}
                                </option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="form-error">
                                {{ form.errors.sucursal_id }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="area_id">Área</label>
                            <select id="area_id" v-model="form.area_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.area_id }" required>
                                <option v-for="area in areas" :key="area.id" :value="area.id">
                                    {{ area.nombre }}
                                </option>
                            </select>
                            <p v-if="form.errors.area_id" class="form-error">
                                {{ form.errors.area_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="cargo">Cargo</label>
                            <input id="cargo" v-model="form.cargo" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.cargo }" placeholder="Diseñador Gráfico..." required />
                            <p v-if="form.errors.cargo" class="form-error">
                                {{ form.errors.cargo }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input id="telefono" v-model="form.telefono" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.telefono }" />
                            <p v-if="form.errors.telefono" class="form-error">
                                {{ form.errors.telefono }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="fecha_ingreso">Fecha de ingreso</label>
                            <input id="fecha_ingreso" v-model="form.fecha_ingreso" type="date" class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha_ingreso }" required />
                            <p v-if="form.errors.fecha_ingreso" class="form-error">
                                {{ form.errors.fecha_ingreso }}
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
                    {{ editingEmpleado ? 'Guardar cambios' : 'Crear empleado' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
