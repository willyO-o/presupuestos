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
    formulas: {
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
 * "Buscar") o al cambiar de página, no en cada tecla.
 */
const table = useServerTable({
    url: route('formulas.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['formulas', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'nombre' },
    { label: 'Expresión', key: 'expresion' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingFormula = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    nombre: '',
    expresion: '',
    descripcion: '',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingFormula.value = null;
    form.clearErrors();
    form.reset();
    resetPrueba();
    showFormModal.value = true;
}

function openEdit(formula) {
    editingFormula.value = formula;
    form.clearErrors();
    form.nombre = formula.nombre;
    form.expresion = formula.expresion;
    form.descripcion = formula.descripcion;
    form.estado = formula.estado;
    resetPrueba();
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

    if (editingFormula.value) {
        form.put(route('formulas.update', editingFormula.value.id), options);
    } else {
        form.post(route('formulas.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(formula) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar la fórmula <strong>${formula.nombre}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar fórmula',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('formulas.destroy', formula.id), {
        preserveScroll: true,
    });
}

/* ── Probar fórmula (medidas de prueba, sin guardar nada) ────────────── */

const prueba = ref({ ancho: '', alto: '', profundo: '' });
const pruebaResultado = ref(null);
const pruebaError = ref(null);
const probando = ref(false);

function resetPrueba() {
    prueba.value = { ancho: '', alto: '', profundo: '' };
    pruebaResultado.value = null;
    pruebaError.value = null;
}

async function probarFormula() {
    pruebaResultado.value = null;
    pruebaError.value = null;
    probando.value = true;

    try {
        const { data } = await window.axios.post(route('formulas.probar'), {
            expresion: form.expresion,
            ancho: prueba.value.ancho || null,
            alto: prueba.value.alto || null,
            profundo: prueba.value.profundo || null,
        });
        pruebaResultado.value = data.resultado;
    } catch (error) {
        pruebaError.value = error.response?.data?.error ?? error.response?.data?.message ?? 'No se pudo evaluar la fórmula.';
    } finally {
        probando.value = false;
    }
}
</script>

<template>

    <Head title="Fórmulas" />

    <!-- Filtros: búsqueda + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre o expresión..." />
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
            <span class="card-title">Listado de fórmulas</span>
            <button v-can="'formulas.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nueva fórmula
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="formulas.data" :paginator="formulas" :loading="table.loading"
                empty-text="No hay fórmulas registradas." @page-change="table.changePage">
                <template #cell-expresion="{ value }">
                    <code>{{ value }}</code>
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
                        <button v-can="'formulas.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar fórmula" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'formulas.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar fórmula" @click="confirmDelete(item)">
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
                {{ editingFormula ? 'Editar fórmula' : 'Nueva fórmula' }}
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
                    <label class="form-label" for="expresion">Expresión</label>
                    <input id="expresion" v-model="form.expresion" type="text" class="form-control"
                        :class="{ 'is-invalid': form.errors.expresion }" placeholder="(ancho + alto) * 2"
                        required />
                    <p v-if="form.errors.expresion" class="form-error">
                        {{ form.errors.expresion }}
                    </p>
                    <p class="fs-sm text-muted mt-1">
                        Variables disponibles: <code>ancho</code>, <code>alto</code>, <code>profundo</code>,
                        <code>area</code> (=ancho×alto), <code>perimetro</code> (=(ancho+alto)×2). Se evalúan en
                        metros; el resultado es la cantidad de material que consume UNA unidad del producto.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <textarea id="descripcion" v-model="form.descripcion" class="form-control" rows="2"
                        :class="{ 'is-invalid': form.errors.descripcion }"></textarea>
                    <p v-if="form.errors.descripcion" class="form-error">
                        {{ form.errors.descripcion }}
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="estado">Estado</label>
                    <select id="estado" v-model="form.estado" class="form-control">
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </div>

                <!-- Probar fórmula: evalúa la expresión con medidas de prueba, sin guardar nada -->
                <div class="card">
                    <div class="card-body">
                        <p class="fw-semibold mb-2">Probar fórmula</p>
                        <div class="row">
                            <div class="col-lg-3">
                                <input v-model="prueba.ancho" type="number" step="0.01" min="0" class="form-control"
                                    placeholder="Ancho (m)" />
                            </div>
                            <div class="col-lg-3">
                                <input v-model="prueba.alto" type="number" step="0.01" min="0" class="form-control"
                                    placeholder="Alto (m)" />
                            </div>
                            <div class="col-lg-3">
                                <input v-model="prueba.profundo" type="number" step="0.01" min="0" class="form-control"
                                    placeholder="Profundo (m)" />
                            </div>
                            <div class="col-lg-3">
                                <button type="button" class="btn btn-soft-info w-100"
                                    :disabled="!form.expresion || probando" @click="probarFormula">
                                    <i v-if="probando" class="fa-solid fa-spinner fa-spin"></i>
                                    Probar
                                </button>
                            </div>
                        </div>
                        <p v-if="pruebaResultado !== null" class="text-success fw-semibold mt-2 mb-0">
                            Resultado: {{ pruebaResultado }}
                        </p>
                        <p v-if="pruebaError" class="form-error mt-2 mb-0">
                            {{ pruebaError }}
                        </p>
                    </div>
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
                    {{ editingFormula ? 'Guardar cambios' : 'Crear fórmula' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
