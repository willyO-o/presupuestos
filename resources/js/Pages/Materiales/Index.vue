<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import MaterialFormFields from '@/Components/Material/MaterialFormFields.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    materiales: {
        type: Object,
        required: true,
    },
    categoriasMaterial: {
        type: Array,
        default: () => [],
    },
    stockBajoTotal: {
        type: Number,
        default: 0,
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
    url: route('materiales.index'),
    filters: {
        search: props.filters.search ?? '',
        categoria: props.filters.categoria ?? '',
        estado: props.filters.estado ?? '',
        stock_bajo: props.filters.stock_bajo ? '1' : '',
    },
    mode: 'manual',
    only: ['materiales', 'stockBajoTotal', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'nombre' },
    { label: 'Categoría', key: 'categoria' },
    { label: 'Presentación', key: 'presentacion' },
    { label: 'Compra', key: 'redondeo_compra', class: 'text-center', cellClass: 'text-center' },
    { label: 'Unidad', key: 'unidad_medida', class: 'text-center', cellClass: 'text-center' },
    { label: 'Precio unitario', key: 'precio_unitario', class: 'text-end', cellClass: 'text-end' },
    { label: 'Stock', key: 'stock_actual', class: 'text-end', cellClass: 'text-end' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const unidadesMedida = [
    { value: 'M2', label: 'm²' },
    { value: 'METRO_LINEAL', label: 'Metro lineal' },
    { value: 'UNIDAD', label: 'Unidad' },
    { value: 'LITRO', label: 'Litro' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingMaterial = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    categoria_material_id: props.categoriasMaterial[0]?.id ?? '',
    nombre: '',
    presentacion: '',
    unidad_medida: 'M2',
    precio_presentacion: '',
    precio_unitario: '',
    stock_actual: 0,
    stock_minimo: 0,
    redondeo_compra: '',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingMaterial.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(material) {
    editingMaterial.value = material;
    form.clearErrors();
    form.categoria_material_id = material.categoria_material_id;
    form.nombre = material.nombre;
    form.presentacion = material.presentacion;
    form.unidad_medida = material.unidad_medida;
    form.precio_presentacion = material.precio_presentacion;
    form.precio_unitario = material.precio_unitario;
    form.stock_actual = material.stock_actual;
    form.stock_minimo = material.stock_minimo;
    form.redondeo_compra = material.redondeo_compra ?? '';
    form.estado = material.estado;
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

    if (editingMaterial.value) {
        form.put(route('materiales.update', editingMaterial.value.id), options);
    } else {
        form.post(route('materiales.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(material) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar el material <strong>${material.nombre}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar material',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('materiales.destroy', material.id), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head title="Materiales" />

    <div v-if="stockBajoTotal > 0 && !table.filters.stock_bajo"
        class="bg-soft-warning rounded p-3 mb-4 d-flex align-items-center gap-2 flex-wrap">
        <i class="fa-solid fa-triangle-exclamation text-warning"></i>
        <span class="fs-sm">
            Hay <strong>{{ stockBajoTotal }}</strong> material(es) activo(s) con stock en o por debajo del mínimo.
        </span>
        <button type="button" class="btn btn-sm btn-soft-warning"
            @click="table.filters.stock_bajo = '1'; table.search()">
            Ver solo esos
        </button>
    </div>

    <!-- Filtros: búsqueda + categoría + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre o presentación..." />
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-categoria">Categoría</label>
                    <select id="filter-categoria" v-model="table.filters.categoria" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="categoria in categoriasMaterial" :key="categoria.id" :value="categoria.id">
                            {{ categoria.nombre }}
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

                <div class="col-lg-3 flex items-end gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="table.loading">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Buscar
                    </button>
                    <button type="button" class="btn btn-soft-secondary" :disabled="table.loading" @click="table.reset">
                        Limpiar
                    </button>
                </div>

                <div class="col-12 mt-2">
                    <label class="d-flex align-items-center gap-2 fs-sm">
                        <input v-model="table.filters.stock_bajo" type="checkbox" true-value="1" false-value=""
                            @change="table.search()" />
                        Solo materiales con stock bajo
                    </label>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Listado de materiales</span>
            <button v-can="'materiales.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo material
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="materiales.data" :paginator="materiales" :loading="table.loading"
                empty-text="No hay materiales registrados." @page-change="table.changePage">
                <template #cell-categoria="{ item }">
                    {{ item.categoria_material?.nombre ?? '—' }}
                </template>

                <template #cell-redondeo_compra="{ value }">
                    <span v-if="value && Number(value) > 0" class="badge badge-soft-info"
                        title="La cantidad consumida se redondea hacia arriba a este múltiplo al costear">
                        × {{ Number(value) }}
                    </span>
                    <span v-else class="text-muted">exacta</span>
                </template>

                <template #cell-unidad_medida="{ item }">
                    <span class="badge badge-soft-secondary">
                        {{ unidadesMedida.find((u) => u.value === item.unidad_medida)?.label ?? item.unidad_medida }}
                    </span>
                </template>

                <template #cell-precio_unitario="{ value }">
                    Bs {{ Number(value).toFixed(2) }}
                </template>

                <template #cell-stock_actual="{ item }">
                    <span :class="{ 'text-danger fw-semibold': Number(item.stock_actual) <= Number(item.stock_minimo) }">
                        {{ Number(item.stock_actual).toFixed(2) }}
                    </span>
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
                        <button v-can="'materiales.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar material" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'materiales.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar material" @click="confirmDelete(item)">
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
                {{ editingMaterial ? 'Editar material' : 'Nuevo material' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <MaterialFormFields :form="form" :errors="form.errors" :categorias-material="categoriasMaterial" />
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeFormModal">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    {{ editingMaterial ? 'Guardar cambios' : 'Crear material' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
