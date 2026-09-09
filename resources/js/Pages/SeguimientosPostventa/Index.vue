<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    seguimientos: { type: Object, required: true },
    resumen: { type: Object, required: true },
    estados: { type: Array, default: () => [] },
    medios: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    config: { type: Object, required: true },
});

const table = useServerTable({
    url: route('seguimientos-postventa.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? 'PENDIENTE',
    },
    mode: 'manual',
    only: ['seguimientos', 'resumen', 'filters'],
});

const headers = [
    { label: 'Pedido', key: 'pedido' },
    { label: 'Cliente', key: 'cliente' },
    { label: 'Entregado', key: 'entrega', class: 'text-center', cellClass: 'text-center' },
    { label: 'Contactar el', key: 'fecha_programada', class: 'text-center', cellClass: 'text-center' },
    { label: 'Satisfacción', key: 'satisfaccion', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    REALIZADO: 'badge-soft-success',
    NO_CONTACTADO: 'badge-soft-secondary',
};

const etiquetaMedio = {
    LLAMADA: 'Llamada',
    WHATSAPP: 'WhatsApp',
    CORREO: 'Correo',
    VISITA: 'Visita',
};

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
}

/** Un pendiente cuya fecha ya pasó: había que llamar y no se llamó. */
function estaVencido(seguimiento) {
    return seguimiento.estado === 'PENDIENTE' && new Date(seguimiento.fecha_programada) <= new Date();
}

function clienteDe(seguimiento) {
    return seguimiento.pedido?.cotizacion?.cliente ?? null;
}

/* ── Modal de registro del contacto ──────────────────────────────────── */

const showModal = ref(false);
const actual = ref(null);

// Datos como funcion factory (ver .ai/rules/pages.md): con objeto plano,
// form.reset() volveria a los ultimos datos enviados, no a los vacios.
const form = useForm(() => ({
    estado: 'REALIZADO',
    medio: 'LLAMADA',
    satisfaccion: props.config.satisfaccion_maxima,
    requiere_accion: 'NO',
    oportunidad: '',
    observaciones: '',
}));

function openRegistrar(seguimiento) {
    actual.value = seguimiento;
    form.clearErrors();
    form.reset();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function submit() {
    form.post(route('seguimientos-postventa.registrar', actual.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}
</script>

<template>

    <Head title="Seguimiento postventa" />

    <!-- Qué es esta bandeja -->
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1 fw-semibold text-heading">
                <i class="fa-solid fa-headset text-primary"></i>
                Cierre del proceso: contacto a los {{ config.dias_seguimiento }} días de la entrega
            </p>
            <p class="fs-sm text-muted mb-0">
                Cada pedido entregado programa automáticamente su contacto de postventa. Llamá al cliente,
                registrá su satisfacción y anotá cualquier oportunidad detectada — es el último paso del
                flujo y el que alimenta las ventas siguientes.
            </p>
        </div>
    </div>

    <!-- Resumen de la bandeja -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Vencidos</p>
                    <p class="fs-xl fw-bold mb-0" :class="resumen.vencidos > 0 ? 'text-danger' : 'text-heading'">
                        {{ resumen.vencidos }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Pendientes</p>
                    <p class="fs-xl fw-bold mb-0 text-heading">{{ resumen.pendientes }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Con reclamo abierto</p>
                    <p class="fs-xl fw-bold mb-0" :class="resumen.requieren_accion > 0 ? 'text-warning' : 'text-heading'">
                        {{ resumen.requieren_accion }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Satisfacción promedio</p>
                    <p class="fs-xl fw-bold mb-0 text-heading">
                        {{ resumen.satisfaccion_promedio
                            ? `${Number(resumen.satisfaccion_promedio).toFixed(1)} / ${config.satisfaccion_maxima}`
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="N.º de pedido o cliente..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="filter-estado">Estado</label>
                    <select id="filter-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
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
            <span class="card-title">Seguimientos</span>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="seguimientos.data" :paginator="seguimientos"
                :loading="table.loading" empty-text="No hay seguimientos con este filtro."
                @page-change="table.changePage">
                <template #cell-pedido="{ item }">
                    <Link v-if="item.pedido" :href="route('pedidos.show', item.pedido.id)" class="fw-semibold">
                        {{ item.pedido.numero_pedido }}
                    </Link>
                </template>

                <template #cell-cliente="{ item }">
                    <span class="fw-semibold">{{ clienteDe(item)?.razon_social ?? '—' }}</span>
                    <span v-if="clienteDe(item)?.telefono" class="d-block fs-xs text-muted">
                        <i class="fa-solid fa-phone"></i> {{ clienteDe(item).telefono }}
                    </span>
                </template>

                <template #cell-entrega="{ item }">
                    {{ fecha(item.pedido?.fecha_entrega_real) }}
                </template>

                <template #cell-fecha_programada="{ item }">
                    <span :class="estaVencido(item) ? 'text-danger fw-semibold' : ''">
                        {{ fecha(item.fecha_programada) }}
                        <i v-if="estaVencido(item)" class="fa-solid fa-triangle-exclamation"
                            title="Ya debía contactarse"></i>
                    </span>
                </template>

                <template #cell-satisfaccion="{ item }">
                    <span v-if="item.satisfaccion">
                        {{ item.satisfaccion }} / {{ config.satisfaccion_maxima }}
                        <span v-if="item.requiere_accion === 'SI'" class="d-block badge badge-soft-danger">
                            Requiere acción
                        </span>
                    </span>
                    <span v-else class="text-muted">—</span>
                </template>

                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">
                        {{ item.estado }}
                    </span>
                    <span v-if="item.medio" class="d-block fs-xs text-muted">{{ etiquetaMedio[item.medio] }}</span>
                </template>

                <template #actions="{ item }">
                    <button v-if="item.estado !== 'REALIZADO'" v-can="'seguimientos-postventa.registrar'" type="button"
                        class="btn btn-sm btn-soft-primary" @click="openRegistrar(item)">
                        <i class="fa-solid fa-phone-volume"></i>
                        Registrar contacto
                    </button>
                    <span v-else class="fs-xs text-muted">{{ fecha(item.fecha_contacto) }}</span>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal: registrar el resultado de la llamada -->
    <Modal :show="showModal" max-width="lg" @close="closeModal">
        <div class="card-header">
            <span class="card-title">
                Seguimiento postventa
                <span v-if="actual?.pedido" class="fs-sm text-muted">— {{ actual.pedido.numero_pedido }}</span>
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submit">
            <div class="card-body">
                <p v-if="actual" class="fs-sm text-muted mb-3">
                    Cliente: <strong>{{ clienteDe(actual)?.razon_social ?? '—' }}</strong>
                    <span v-if="clienteDe(actual)?.telefono"> · Tel: {{ clienteDe(actual).telefono }}</span>
                </p>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="estado">Resultado</label>
                            <select id="estado" v-model="form.estado" class="form-control">
                                <option value="REALIZADO">Se contactó al cliente</option>
                                <option value="NO_CONTACTADO">No se pudo contactar</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="medio">Medio</label>
                            <select id="medio" v-model="form.medio" class="form-control"
                                :class="{ 'is-invalid': form.errors.medio }" :disabled="form.estado !== 'REALIZADO'">
                                <option v-for="m in medios" :key="m" :value="m">{{ etiquetaMedio[m] }}</option>
                            </select>
                            <p v-if="form.errors.medio" class="form-error">{{ form.errors.medio }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="satisfaccion">
                                Satisfacción (1 a {{ config.satisfaccion_maxima }})
                            </label>
                            <input id="satisfaccion" v-model="form.satisfaccion" v-entero type="text"
                                inputmode="numeric" class="form-control"
                                :class="{ 'is-invalid': form.errors.satisfaccion }"
                                :disabled="form.estado !== 'REALIZADO'" />
                            <p v-if="form.errors.satisfaccion" class="form-error">{{ form.errors.satisfaccion }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="requiere_accion">¿Reportó algún problema?</label>
                            <select id="requiere_accion" v-model="form.requiere_accion" class="form-control">
                                <option value="NO">No, todo conforme</option>
                                <option value="SI">Sí, requiere acción</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="oportunidad">Oportunidad detectada</label>
                    <textarea id="oportunidad" v-model="form.oportunidad" class="form-control" rows="2"
                        :class="{ 'is-invalid': form.errors.oportunidad }"
                        placeholder="Trabajo futuro, otra sucursal, renovación..."></textarea>
                    <p v-if="form.errors.oportunidad" class="form-error">{{ form.errors.oportunidad }}</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <textarea id="observaciones" v-model="form.observaciones" class="form-control" rows="3"
                        :class="{ 'is-invalid': form.errors.observaciones }"></textarea>
                    <p v-if="form.errors.observaciones" class="form-error">{{ form.errors.observaciones }}</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeModal">Cancelar</button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    Guardar seguimiento
                </button>
            </div>
        </form>
    </Modal>
</template>
