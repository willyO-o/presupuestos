<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    tiposProyecto: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    config: { type: Object, required: true },
});

const table = useServerTable({
    url: route('tipos-proyecto.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['tiposProyecto', 'filters'],
});

const headers = [
    { label: '#', key: 'orden', class: 'text-center', cellClass: 'text-center' },
    { label: 'Nivel', key: 'nombre' },
    { label: 'Factor', key: 'factor_complejidad', class: 'text-end', cellClass: 'text-end' },
    { label: 'Margen mínimo', key: 'margen_minimo', class: 'text-end', cellClass: 'text-end' },
    { label: 'Ejemplo sobre Bs 100', key: 'ejemplo', class: 'text-end', cellClass: 'text-end' },
    { label: 'Usos', key: 'cotizacion_detalles_count', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

/**
 * Precio que saldría de un costo base de Bs 100 con este nivel — la forma
 * más directa de entender qué hace el factor combinado con el margen, sin
 * tener que abrir una cotización de prueba.
 */
function precioEjemplo(tipo) {
    return 100 * Number(tipo.factor_complejidad) * (1 + Number(tipo.margen_minimo));
}

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function porcentaje(fraccion) {
    return `${(Number(fraccion || 0) * 100).toFixed(2).replace(/\.00$/, '')} %`;
}

const impuestos = computed(() => props.config.impuestos);
const semaforo = computed(() => props.config.semaforo);

/* ── Modal crear / editar ────────────────────────────────────────────── */

const showFormModal = ref(false);
const editingTipo = ref(null);

// Datos como funcion factory, no objeto plano: si no, form.reset() vuelve a
// los ultimos datos enviados en vez de a los campos vacios (ver .ai/rules/pages.md).
const form = useForm(() => ({
    nombre: '',
    descripcion: '',
    factor_complejidad: 1,
    // El usuario piensa en porcentaje; el backend lo guarda como fraccion.
    margen_minimo: 45,
    orden: 0,
    estado: 'ACTIVO',
}));

const previsualizacion = computed(() => {
    const costoBase = 100;
    const costoAjustado = costoBase * Number(form.factor_complejidad || 0);
    const precio = costoAjustado * (1 + Number(form.margen_minimo || 0) / 100);
    const it = precio * impuestos.value.it;
    const utilidadAntesIue = precio - costoAjustado - it;
    const utilidadReal = utilidadAntesIue - utilidadAntesIue * impuestos.value.iue;
    const rentabilidad = costoAjustado > 0 ? utilidadReal / costoAjustado : 0;

    const estado = rentabilidad > semaforo.value.umbral_verde
        ? 'VERDE'
        : rentabilidad > semaforo.value.umbral_amarillo
            ? 'AMARILLO'
            : 'ROJO';

    return { costoAjustado, precio, utilidadReal, rentabilidad, estado };
});

const claseSemaforo = {
    VERDE: 'semaforo semaforo-verde',
    AMARILLO: 'semaforo semaforo-amarillo',
    ROJO: 'semaforo semaforo-rojo',
};

const recomendacion = {
    VERDE: 'Aceptar',
    AMARILLO: 'Revisar precio',
    ROJO: 'No aceptar',
};

function openCreate() {
    editingTipo.value = null;
    form.clearErrors();
    form.reset();
    showFormModal.value = true;
}

function openEdit(tipo) {
    editingTipo.value = tipo;
    form.clearErrors();
    form.nombre = tipo.nombre;
    form.descripcion = tipo.descripcion ?? '';
    form.factor_complejidad = Number(tipo.factor_complejidad);
    form.margen_minimo = Number((Number(tipo.margen_minimo) * 100).toFixed(2));
    form.orden = tipo.orden;
    form.estado = tipo.estado;
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

    if (editingTipo.value) {
        form.put(route('tipos-proyecto.update', editingTipo.value.id), options);
    } else {
        form.post(route('tipos-proyecto.store'), options);
    }
}

/* ── Eliminar ────────────────────────────────────────────────────────── */

const deleteForm = useForm({});

async function confirmDelete(tipo) {
    const confirmed = await confirmation(
        `¿Eliminar el nivel <strong>${tipo.nombre}</strong>? Las cotizaciones ya emitidas conservan el factor y el margen con que se calcularon.`,
        'Eliminar tipo de proyecto',
    );

    if (!confirmed) {
        return;
    }

    deleteForm.delete(route('tipos-proyecto.destroy', tipo.id), { preserveScroll: true });
}
</script>

<template>

    <Head title="Tipos de proyecto" />

    <!-- Qué hace este catálogo: sin esta explicación, "factor" y "margen mínimo" no dicen nada -->
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1 fw-semibold text-heading">
                <i class="fa-solid fa-sliders text-primary"></i>
                Niveles de complejidad del motor de precios
            </p>
            <p class="fs-sm text-muted mb-0">
                Cada línea de una cotización se cotiza con uno de estos niveles:
                <strong>costo ajustado</strong> = costo de los insumos × <strong>factor</strong>, y
                <strong>precio</strong> = costo ajustado × (1 + <strong>margen mínimo</strong>).
                Sobre ese precio el sistema aplica IT {{ porcentaje(impuestos.it) }},
                IUE {{ porcentaje(impuestos.iue) }} e IVA {{ porcentaje(impuestos.iva) }} y evalúa el
                semáforo de rentabilidad. Agregá, editá o desactivá niveles sin tocar el código.
            </p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre o descripción..." />
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
            <span class="card-title">Tipos de proyecto</span>
            <button v-can="'tipos-proyecto.crear'" type="button" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Nuevo nivel
            </button>
        </div>

        <div class="card-body">
            <DataTable :headers="headers" :items="tiposProyecto.data" :paginator="tiposProyecto"
                :loading="table.loading" empty-text="No hay tipos de proyecto registrados."
                @page-change="table.changePage">
                <template #cell-nombre="{ item }">
                    <span class="fw-semibold">{{ item.nombre }}</span>
                    <span v-if="item.descripcion" class="d-block fs-xs text-muted">{{ item.descripcion }}</span>
                </template>

                <template #cell-factor_complejidad="{ value }">
                    × {{ Number(value).toFixed(2) }}
                </template>

                <template #cell-margen_minimo="{ value }">
                    {{ porcentaje(value) }}
                </template>

                <template #cell-ejemplo="{ item }">
                    <span class="fw-semibold">{{ money(precioEjemplo(item)) }}</span>
                </template>

                <template #cell-cotizacion_detalles_count="{ value }">
                    <span class="badge badge-soft-secondary">{{ value }}</span>
                </template>

                <template #cell-estado="{ item }">
                    <span class="badge"
                        :class="item.estado === 'ACTIVO' ? 'badge-soft-success' : 'badge-soft-danger'">
                        {{ item.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <div class="d-flex gap-1">
                        <button v-can="'tipos-proyecto.editar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-primary" aria-label="Editar tipo de proyecto"
                            @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-can="'tipos-proyecto.eliminar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-danger" aria-label="Eliminar tipo de proyecto"
                            :disabled="item.cotizacion_detalles_count > 0"
                            :title="item.cotizacion_detalles_count > 0 ? 'Ya se usó en cotizaciones: desactívalo' : ''"
                            @click="confirmDelete(item)">
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
                {{ editingTipo ? 'Editar tipo de proyecto' : 'Nuevo tipo de proyecto' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeFormModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre del nivel</label>
                    <input id="nombre" v-model="form.nombre" type="text" class="form-control"
                        :class="{ 'is-invalid': form.errors.nombre }" placeholder="Básico, Medio, Complejo..." required
                        autofocus />
                    <p v-if="form.errors.nombre" class="form-error">{{ form.errors.nombre }}</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="descripcion">¿Cuándo se usa?</label>
                    <textarea id="descripcion" v-model="form.descripcion" class="form-control" rows="2"
                        :class="{ 'is-invalid': form.errors.descripcion }"
                        placeholder="Ej.: requiere estructura metálica o instalación en altura"></textarea>
                    <p v-if="form.errors.descripcion" class="form-error">{{ form.errors.descripcion }}</p>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="factor_complejidad">Factor de complejidad</label>
                            <input id="factor_complejidad" v-model="form.factor_complejidad" v-decimal="2" type="text"
                                inputmode="decimal" class="form-control"
                                :class="{ 'is-invalid': form.errors.factor_complejidad }" required />
                            <p class="fs-xs text-muted mt-1 mb-0">1 = sin recargo</p>
                            <p v-if="form.errors.factor_complejidad" class="form-error">
                                {{ form.errors.factor_complejidad }}
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="margen_minimo">Margen mínimo (%)</label>
                            <input id="margen_minimo" v-model="form.margen_minimo" v-decimal="2" type="text"
                                inputmode="decimal" class="form-control"
                                :class="{ 'is-invalid': form.errors.margen_minimo }" required />
                            <p v-if="form.errors.margen_minimo" class="form-error">{{ form.errors.margen_minimo }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="orden">Orden</label>
                            <input id="orden" v-model="form.orden" v-entero type="text" inputmode="numeric"
                                class="form-control" :class="{ 'is-invalid': form.errors.orden }" />
                            <p v-if="form.errors.orden" class="form-error">{{ form.errors.orden }}</p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="estado">Estado</label>
                    <select id="estado" v-model="form.estado" class="form-control">
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo (no aparece al cotizar)</option>
                    </select>
                </div>

                <!-- Previsualización: qué haría este nivel con un costo de Bs 100 -->
                <div class="margen-panel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <span class="fw-semibold fs-sm">Simulación sobre un costo de Bs 100</span>
                        <span :class="claseSemaforo[previsualizacion.estado]">
                            <span class="semaforo-punto"></span>
                            {{ recomendacion[previsualizacion.estado] }}
                        </span>
                    </div>
                    <div class="margen-row">
                        <span>Costo ajustado</span>
                        <span>{{ money(previsualizacion.costoAjustado) }}</span>
                    </div>
                    <div class="margen-row">
                        <span>Precio (antes de IVA)</span>
                        <span>{{ money(previsualizacion.precio) }}</span>
                    </div>
                    <div class="margen-row margen-row-destacada">
                        <span>Utilidad real (después de IT e IUE)</span>
                        <span>
                            {{ money(previsualizacion.utilidadReal) }}
                            <span class="fs-xs text-muted">
                                ({{ (previsualizacion.rentabilidad * 100).toFixed(1) }} % del costo)
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeFormModal">Cancelar</button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    {{ editingTipo ? 'Guardar cambios' : 'Crear nivel' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
