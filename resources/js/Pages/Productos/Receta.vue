<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    producto: {
        type: Object,
        required: true,
    },
    lineas: {
        type: Array,
        required: true,
    },
    materiales: {
        type: Array,
        default: () => [],
    },
    formulas: {
        type: Array,
        default: () => [],
    },
});

const headers = [
    { label: 'Material', key: 'material' },
    { label: 'Tipo', key: 'tipo', class: 'text-center', cellClass: 'text-center' },
    { label: 'Cálculo', key: 'calculo' },
];

function nombreMaterial(materialId) {
    return props.materiales.find((material) => material.id === materialId)?.nombre ?? '—';
}

/* ── Modal agregar / editar línea ────────────────────────────────────── */

const showFormModal = ref(false);
const editingLinea = ref(null);

// Los datos se pasan como funcion (no un objeto plano): Inertia v2 actualiza
// los "defaults" del form automaticamente en cada submit exitoso, asi que
// con un objeto plano form.reset() dejaba de volver a los campos vacios
// despues de crear/editar (volvia a los ultimos datos enviados). Con una
// funcion, reset() siempre re-evalua estos valores desde cero.
const form = useForm(() => ({
    material_id: props.materiales[0]?.id ?? '',
    tipo: 'estatico',
    cantidad_por_unidad: '',
    formula_id: props.formulas[0]?.id ?? '',
}));

function openCreate() {
    editingLinea.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(linea) {
    editingLinea.value = linea;
    form.clearErrors();
    form.material_id = linea.material_id;
    form.tipo = linea.formula_id ? 'formula' : 'estatico';
    form.cantidad_por_unidad = linea.cantidad_por_unidad ?? '';
    form.formula_id = linea.formula_id ?? (props.formulas[0]?.id ?? '');
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

    // Solo se manda el campo que corresponde al tipo elegido — el backend
    // exige exactamente uno de los dos (ver StoreProductoMaterialRequest).
    form.transform((data) => ({
        material_id: data.material_id,
        cantidad_por_unidad: data.tipo === 'estatico' ? data.cantidad_por_unidad : null,
        formula_id: data.tipo === 'formula' ? data.formula_id : null,
    }));

    if (editingLinea.value) {
        form.put(route('productos.materiales.update', [props.producto.id, editingLinea.value.id]), options);
    } else {
        form.post(route('productos.materiales.store', props.producto.id), options);
    }
}

/* ── Eliminar (confirmación con SweetAlert2, ver Utils/AlertUtil.js) ────── */

const deleteForm = useForm({});

async function confirmDelete(linea) {
    const confirmed = await confirmation(
        `¿Seguro que deseas quitar <strong>${nombreMaterial(linea.material_id)}</strong> de la receta? Esta acción no se puede deshacer.`,
        'Quitar material',
    );

    if (!confirmed) {
        return;
    }

    // El toast de exito/error lo dispara el listener global de
    // Composables/UseFlashNotifications.js — no hace falta onSuccess aqui.
    deleteForm.delete(route('productos.materiales.destroy', [props.producto.id, linea.id]), {
        preserveScroll: true,
    });
}
</script>

<template>

    <Head :title="`Receta — ${producto.nombre}`" />

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="fs-xs fw-semibold text-muted mb-1">
                        {{ producto.categoria_producto?.nombre ?? '—' }}
                    </p>
                    <p class="fw-semibold mb-0">{{ producto.nombre }}</p>
                    <p class="fs-sm text-muted mb-0">
                        Se cotiza por {{ producto.unidad_medida === 'M2' ? 'm²' : (producto.unidad_medida === 'METRO_LINEAL' ? 'metro lineal' : 'unidad') }}.
                        La cantidad de cada línea es la que consume UNA unidad del producto.
                    </p>
                </div>
                <Link :href="route('productos.index')" class="btn btn-soft-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    Volver a productos
                </Link>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Receta (BOM)</span>
            <button v-can="'productos.editar'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Agregar material
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="lineas" empty-text="Este producto todavía no tiene receta.">
                <template #cell-material="{ item }">
                    {{ item.material?.nombre ?? nombreMaterial(item.material_id) }}
                </template>

                <template #cell-tipo="{ item }">
                    <span class="badge" :class="item.formula_id ? 'badge-soft-info' : 'badge-soft-secondary'">
                        {{ item.formula_id ? 'Fórmula' : 'Factor fijo' }}
                    </span>
                </template>

                <template #cell-calculo="{ item }">
                    <code v-if="item.formula">{{ item.formula.nombre }}: {{ item.formula.expresion }}</code>
                    <span v-else>{{ item.cantidad_por_unidad }}</span>
                </template>

                <template #actions="{ item }">
                    <div class="d-flex gap-1">
                        <button v-can="'productos.editar'" type="button" class="btn btn-sm btn-icon btn-soft-primary"
                            aria-label="Editar línea" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'productos.editar'" type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            aria-label="Quitar línea" @click="confirmDelete(item)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal agregar / editar línea -->
    <Modal :show="showFormModal" max-width="md" @close="closeFormModal">
        <div class="card-header">
            <span class="card-title">
                {{ editingLinea ? 'Editar línea de receta' : 'Agregar material a la receta' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="material_id">Material</label>
                    <select id="material_id" v-model="form.material_id" class="form-control"
                        :class="{ 'is-invalid': form.errors.material_id }" required>
                        <option v-for="material in materiales" :key="material.id" :value="material.id">
                            {{ material.nombre }}
                        </option>
                    </select>
                    <p v-if="form.errors.material_id" class="form-error">
                        {{ form.errors.material_id }}
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">¿Cómo se calcula la cantidad?</label>
                    <div class="d-flex gap-4">
                        <label class="d-flex align-items-center gap-1">
                            <input v-model="form.tipo" type="radio" value="estatico" />
                            Factor fijo
                        </label>
                        <label class="d-flex align-items-center gap-1">
                            <input v-model="form.tipo" type="radio" value="formula" />
                            Fórmula
                        </label>
                    </div>
                </div>

                <div v-if="form.tipo === 'estatico'" class="form-group">
                    <label class="form-label" for="cantidad_por_unidad">Cantidad por unidad</label>
                    <input id="cantidad_por_unidad" v-model="form.cantidad_por_unidad" type="number" step="0.0001"
                        min="0" class="form-control" :class="{ 'is-invalid': form.errors.cantidad_por_unidad }"
                        required />
                    <p v-if="form.errors.cantidad_por_unidad" class="form-error">
                        {{ form.errors.cantidad_por_unidad }}
                    </p>
                    <p class="fs-sm text-muted mt-1">
                        Cuánto material consume 1 {{ producto.unidad_medida === 'M2' ? 'm²' : (producto.unidad_medida
                            === 'METRO_LINEAL' ? 'metro lineal' : 'unidad') }} del producto.
                    </p>
                </div>

                <div v-else class="form-group">
                    <label class="form-label" for="formula_id">Fórmula</label>
                    <select id="formula_id" v-model="form.formula_id" class="form-control"
                        :class="{ 'is-invalid': form.errors.formula_id }" required>
                        <option v-for="formula in formulas" :key="formula.id" :value="formula.id">
                            {{ formula.nombre }} — {{ formula.expresion }}
                        </option>
                    </select>
                    <p v-if="form.errors.formula_id" class="form-error">
                        {{ form.errors.formula_id }}
                    </p>
                    <p v-if="!formulas.length" class="fs-sm text-muted mt-1">
                        Todavía no hay fórmulas activas — crea una desde el catálogo de Fórmulas.
                    </p>
                </div>

                <p v-if="form.errors.cantidad_por_unidad && form.tipo === 'formula'" class="form-error">
                    {{ form.errors.cantidad_por_unidad }}
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeFormModal">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    {{ editingLinea ? 'Guardar cambios' : 'Agregar' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
