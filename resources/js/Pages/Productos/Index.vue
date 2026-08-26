<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    productos: {
        type: Object,
        required: true,
    },
    categoriasProducto: {
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
    url: route('productos.index'),
    filters: {
        search: props.filters.search ?? '',
        categoria: props.filters.categoria ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['productos', 'filters'],
});

const headers = [
    { label: 'Nombre', key: 'nombre' },
    { label: 'Categoría', key: 'categoria' },
    { label: 'Unidad', key: 'unidad_medida', class: 'text-center', cellClass: 'text-center' },
    { label: 'Precio base', key: 'precio_base', class: 'text-end', cellClass: 'text-end' },
    { label: '¿Pide medidas?', key: 'requiere_medidas', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const unidadesMedida = [
    { value: 'M2', label: 'm²' },
    { value: 'UNIDAD', label: 'Unidad' },
    { value: 'METRO_LINEAL', label: 'Metro lineal' },
];

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingProducto = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    categoria_producto_id: props.categoriasProducto[0]?.id ?? '',
    nombre: '',
    descripcion: '',
    unidad_medida: 'M2',
    precio_base: '',
    requiere_medidas: 'SI',
    estado: 'ACTIVO',
}));

function openCreate() {
    editingProducto.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(producto) {
    editingProducto.value = producto;
    form.clearErrors();
    form.categoria_producto_id = producto.categoria_producto_id;
    form.nombre = producto.nombre;
    form.descripcion = producto.descripcion;
    form.unidad_medida = producto.unidad_medida;
    form.precio_base = producto.precio_base;
    form.requiere_medidas = producto.requiere_medidas;
    form.estado = producto.estado;
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

    if (editingProducto.value) {
        form.put(route('productos.update', editingProducto.value.id), options);
    } else {
        form.post(route('productos.store'), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(producto) {
    const confirmed = await confirmation(
        `¿Seguro que deseas eliminar el producto <strong>${producto.nombre}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar producto',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('productos.destroy', producto.id), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head title="Productos" />

    <!-- Filtros: búsqueda + categoría + estado, se aplican al enviar el formulario -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-4">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre del producto..." />
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="filter-categoria">Categoría</label>
                    <select id="filter-categoria" v-model="table.filters.categoria" class="form-control">
                        <option value="">Todas</option>
                        <option v-for="categoria in categoriasProducto" :key="categoria.id" :value="categoria.id">
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
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Listado de productos</span>
            <button v-can="'productos.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo producto
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="productos.data" :paginator="productos" :loading="table.loading"
                empty-text="No hay productos registrados." @page-change="table.changePage">
                <template #cell-categoria="{ item }">
                    {{ item.categoria_producto?.nombre ?? '—' }}
                </template>

                <template #cell-unidad_medida="{ item }">
                    <span class="badge badge-soft-secondary">
                        {{ unidadesMedida.find((u) => u.value === item.unidad_medida)?.label ?? item.unidad_medida }}
                    </span>
                </template>

                <template #cell-precio_base="{ value }">
                    {{ value !== null ? `Bs ${Number(value).toFixed(2)}` : '—' }}
                </template>

                <template #cell-requiere_medidas="{ item }">
                    <span class="badge" :class="item.requiere_medidas === 'SI' ? 'badge-soft-info' : 'badge-soft-secondary'">
                        {{ item.requiere_medidas === 'SI' ? 'Sí' : 'No' }}
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
                        <Link v-can="'productos.editar'" :href="route('productos.materiales.index', item.id)"
                            class="btn btn-sm btn-icon btn-soft-info" aria-label="Receta / BOM">
                            <i class="fa-solid fa-flask"></i>
                        </Link>
                        <button v-can="'productos.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar producto" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'productos.eliminar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Eliminar producto" @click="confirmDelete(item)">
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
                {{ editingProducto ? 'Editar producto' : 'Nuevo producto' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="categoria_producto_id">Categoría</label>
                            <select id="categoria_producto_id" v-model="form.categoria_producto_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.categoria_producto_id }" required>
                                <option v-for="categoria in categoriasProducto" :key="categoria.id" :value="categoria.id">
                                    {{ categoria.nombre }}
                                </option>
                            </select>
                            <p v-if="form.errors.categoria_producto_id" class="form-error">
                                {{ form.errors.categoria_producto_id }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input id="nombre" v-model="form.nombre" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.nombre }" required autofocus />
                            <p v-if="form.errors.nombre" class="form-error">
                                {{ form.errors.nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <textarea id="descripcion" v-model="form.descripcion" class="form-control" rows="2"
                        :class="{ 'is-invalid': form.errors.descripcion }"></textarea>
                    <p v-if="form.errors.descripcion" class="form-error">
                        {{ form.errors.descripcion }}
                    </p>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="unidad_medida">Unidad de medida</label>
                            <select id="unidad_medida" v-model="form.unidad_medida" class="form-control">
                                <option v-for="unidad in unidadesMedida" :key="unidad.value" :value="unidad.value">
                                    {{ unidad.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="precio_base">Precio base (Bs)</label>
                            <input id="precio_base" v-model="form.precio_base" type="number" step="0.01" min="0"
                                class="form-control" :class="{ 'is-invalid': form.errors.precio_base }" />
                            <p v-if="form.errors.precio_base" class="form-error">
                                {{ form.errors.precio_base }}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="requiere_medidas">¿Pide medidas al cotizar?</label>
                            <select id="requiere_medidas" v-model="form.requiere_medidas" class="form-control">
                                <option value="SI">Sí</option>
                                <option value="NO">No</option>
                            </select>
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
                    {{ editingProducto ? 'Guardar cambios' : 'Crear producto' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
