<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { formatearFecha } from '@/Utils/Fecha';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    solicitudes: { type: Object, required: true },
    resumen: { type: Object, required: true },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('solicitudes-web.index'),
    filters: {
        search: props.filters.search ?? '',
        estado: props.filters.estado ?? '',
    },
    mode: 'manual',
    only: ['solicitudes', 'resumen', 'filters'],
});

const headers = [
    { label: 'Código', key: 'codigo' },
    { label: 'Contacto', key: 'contacto' },
    { label: 'Trabajos', key: 'trabajos' },
    { label: 'Estimado', key: 'estimado', class: 'text-end', cellClass: 'text-end' },
    { label: 'Vence', key: 'vence', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = {
    NUEVA: 'badge-soft-warning',
    CONTACTADA: 'badge-soft-info',
    CONVERTIDA: 'badge-soft-success',
    DESCARTADA: 'badge-soft-secondary',
};

function fecha(value) {
    return formatearFecha(value, { day: '2-digit', month: 'short', year: 'numeric' });
}

function decimal(valor) {
    return Number(valor).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function bolivianos(monto) {
    return `Bs ${decimal(monto)}`;
}

/** "2,00 × 8,00 m" (o solo el ancho si se cobra por metro lineal); null si va por unidad. */
function medidas(linea) {
    if (!linea.ancho) {
        return null;
    }

    return linea.alto ? `${decimal(linea.ancho)} × ${decimal(linea.alto)} m` : `${decimal(linea.ancho)} m`;
}

function area(linea) {
    return linea.ancho && linea.alto ? Number(linea.ancho) * Number(linea.alto) : null;
}

/** Suma de las líneas: lo que valen los trabajos ANTES del IVA y la holgura del estimado. */
function subtotalTrabajos(solicitud) {
    return (solicitud.detalle ?? []).reduce((total, linea) => total + Number(linea.subtotal), 0);
}

/** Sin atender y con la vigencia pasada: el precio que vio el cliente ya no vale. */
function estaVencida(solicitud) {
    return solicitud.estado === 'NUEVA' && new Date(solicitud.fecha_vencimiento) < new Date();
}

/** Enlace de WhatsApp con el código ya escrito: es como se atiende esto en la práctica. */
function whatsapp(solicitud) {
    const telefono = String(solicitud.telefono).replace(/\D/g, '');
    const texto = `Hola ${solicitud.nombre}, te escribimos de XtraPubli por tu cotización ${solicitud.codigo}.`;

    return `https://wa.me/${telefono}?text=${encodeURIComponent(texto)}`;
}

/* ── Modal de detalle ────────────────────────────────────────────────── */

const showModal = ref(false);
const actual = ref(null);

// Datos como funcion factory (ver .ai/rules/pages.md): con objeto plano,
// form.reset() volveria a los ultimos datos enviados, no a los vacios.
const form = useForm(() => ({ estado: 'CONTACTADA' }));

function openDetalle(solicitud) {
    actual.value = solicitud;
    form.clearErrors();
    form.reset();
    form.estado = solicitud.estado === 'NUEVA' ? 'CONTACTADA' : solicitud.estado;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function submit() {
    form.put(route('solicitudes-web.estado', actual.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}
</script>

<template>

    <Head title="Solicitudes del sitio web" />

    <!-- Qué es esta bandeja -->
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1 fw-semibold text-heading">
                <i class="fa-solid fa-globe text-primary"></i>
                Estimaciones que la gente arma sola en el cotizador del sitio
            </p>
            <p class="fs-sm text-muted mb-0">
                No son presupuestos de la empresa: son precios aproximados que el visitante calculó en
                <strong>/cotizador</strong> y que caducan. Cuando alguien escriba mencionando un código
                <strong>WEB-…</strong>, búscalo acá. El presupuesto formal se emite aparte, en Cotizaciones.
            </p>
        </div>
    </div>

    <!-- Resumen de la bandeja -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Sin atender</p>
                    <p class="fs-xl fw-bold mb-0" :class="resumen.nuevas > 0 ? 'text-warning' : 'text-heading'">
                        {{ resumen.nuevas }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Vencidas sin atender</p>
                    <p class="fs-xl fw-bold mb-0" :class="resumen.vencidas > 0 ? 'text-danger' : 'text-heading'">
                        {{ resumen.vencidas }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Contactadas</p>
                    <p class="fs-xl fw-bold mb-0 text-heading">{{ resumen.contactadas }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card">
                <div class="card-body">
                    <p class="fs-xs text-muted text-uppercase fw-semibold mb-1">Convertidas</p>
                    <p class="fs-xl fw-bold mb-0 text-success">{{ resumen.convertidas }}</p>
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
                        placeholder="Código WEB-…, nombre, empresa o teléfono..." />
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
            <span class="card-title">Solicitudes</span>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="solicitudes.data" :paginator="solicitudes" :loading="table.loading"
                empty-text="Todavía no llegaron solicitudes por el sitio web." @page-change="table.changePage">
                <template #cell-codigo="{ item }">
                    <span class="fw-semibold">{{ item.codigo }}</span>
                    <span class="d-block fs-xs text-muted">{{ fecha(item.created_at) }}</span>
                </template>

                <template #cell-contacto="{ item }">
                    <span class="fw-semibold">{{ item.nombre }}</span>
                    <span v-if="item.empresa" class="d-block fs-xs text-muted">{{ item.empresa }}</span>
                    <span class="d-block fs-xs text-muted">
                        <i class="fa-solid fa-phone"></i> {{ item.telefono }}
                    </span>
                </template>

                <template #cell-trabajos="{ item }">
                    <span class="fs-sm">{{ item.detalle?.[0]?.descripcion ?? '—' }}</span>
                    <span v-if="(item.detalle?.length ?? 0) > 1" class="d-block fs-xs text-muted">
                        y {{ item.detalle.length - 1 }} más
                    </span>
                </template>

                <template #cell-estimado="{ item }">
                    <span class="fw-semibold">{{ bolivianos(item.estimado_min) }}</span>
                    <span class="d-block fs-xs text-muted">a {{ bolivianos(item.estimado_max) }}</span>
                </template>

                <template #cell-vence="{ item }">
                    <span :class="estaVencida(item) ? 'text-danger fw-semibold' : ''">
                        {{ fecha(item.fecha_vencimiento) }}
                        <i v-if="estaVencida(item)" class="fa-solid fa-triangle-exclamation"
                            title="Venció sin que nadie la atendiera"></i>
                    </span>
                </template>

                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">
                        {{ item.estado }}
                    </span>
                    <span v-if="item.cotizacion" class="d-block fs-xs text-muted">
                        {{ item.cotizacion.codigo_verificacion }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <a :href="whatsapp(item)" target="_blank" rel="noopener noreferrer"
                        class="btn btn-sm btn-soft-success" title="Escribir por WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-soft-primary" @click="openDetalle(item)">
                        <i class="fa-solid fa-eye"></i>
                        Ver
                    </button>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal: detalle de lo que pidió el visitante -->
    <Modal :show="showModal" max-width="3xl" @close="closeModal">
        <div class="card-header">
            <span class="card-title solicitud-web-titulo">
                Solicitud
                <span class="solicitud-web-codigo">{{ actual?.codigo }}</span>
                <span v-if="actual" class="badge" :class="estadoBadge[actual.estado] ?? 'badge-soft-secondary'">
                    {{ actual.estado }}
                </span>
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="closeModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submit">
            <div v-if="actual" class="card-body">
                <!-- Quién pidió y hasta cuándo vale lo que vio -->
                <dl class="solicitud-web-ficha">
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            <strong>{{ actual.nombre }}</strong>
                            <span v-if="actual.empresa" class="d-block text-muted">{{ actual.empresa }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Contacto</dt>
                        <dd>
                            <a :href="whatsapp(actual)" target="_blank" rel="noopener noreferrer"
                                class="solicitud-web-enlace" title="Escribir por WhatsApp">
                                <i class="fa-brands fa-whatsapp"></i>
                                {{ actual.telefono }}
                            </a>
                            <a v-if="actual.email" :href="`mailto:${actual.email}`"
                                class="solicitud-web-enlace d-block text-truncate">
                                {{ actual.email }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt>Recibida</dt>
                        <dd>{{ fecha(actual.created_at) }}</dd>
                    </div>
                    <div>
                        <dt>Válida hasta</dt>
                        <dd :class="{ 'text-danger': estaVencida(actual) }">
                            {{ fecha(actual.fecha_vencimiento) }}
                            <span class="d-block text-muted fs-xs">
                                <template v-if="estaVencida(actual)">
                                    <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                                    Venció sin atender
                                </template>
                                <template v-else>{{ actual.vigencia_dias }} días de vigencia</template>
                            </span>
                        </dd>
                    </div>
                </dl>

                <blockquote v-if="actual.mensaje" class="solicitud-web-mensaje">
                    <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                    <p>{{ actual.mensaje }}</p>
                </blockquote>

                <!-- Lo que calculó en el cotizador -->
                <div class="table-responsive mb-3">
                    <table class="table-dashboard solicitud-web-tabla">
                        <thead>
                            <tr>
                                <th>Trabajo</th>
                                <th>Medidas</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">P. unitario</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(linea, i) in actual.detalle" :key="i">
                                <td class="fw-semibold text-heading">{{ linea.producto ?? linea.descripcion }}</td>
                                <td>
                                    <template v-if="medidas(linea)">
                                        {{ medidas(linea) }}
                                        <span v-if="area(linea)" class="d-block fs-xs text-muted">
                                            {{ decimal(area(linea)) }} m²
                                        </span>
                                    </template>
                                    <span v-else class="text-muted">Por unidad</span>
                                </td>
                                <td class="text-center">{{ linea.cantidad }}</td>
                                <td class="text-end">{{ bolivianos(linea.precio_unitario) }}</td>
                                <td class="text-end fw-semibold text-heading">{{ bolivianos(linea.subtotal) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end">Subtotal de los trabajos (sin IVA)</td>
                                <td class="text-end">{{ bolivianos(subtotalTrabajos(actual)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- El precio que vio el visitante: es lo que va a recordar al escribir -->
                <div class="solicitud-web-estimado">
                    <div>
                        <p class="solicitud-web-estimado-etiqueta">Estimado que vio el visitante</p>
                        <p class="solicitud-web-estimado-monto">
                            {{ bolivianos(actual.estimado_min) }}
                            <span>a</span>
                            {{ bolivianos(actual.estimado_max) }}
                        </p>
                    </div>
                    <ul class="solicitud-web-estimado-notas">
                        <li><i class="fa-solid fa-receipt"></i> IVA incluido</li>
                        <li>
                            <i class="fa-solid fa-arrows-left-right"></i>
                            ±{{ Math.round(Number(actual.holgura) * 100) }} % de margen
                        </li>
                        <li v-if="actual.descargado_en">
                            <i class="fa-solid fa-file-arrow-down"></i>
                            Descargó el documento el {{ fecha(actual.descargado_en) }}
                            ({{ actual.descargas }} {{ actual.descargas === 1 ? 'vez' : 'veces' }})
                        </li>
                        <li v-else class="text-muted">
                            <i class="fa-regular fa-file"></i>
                            No descargó el documento
                        </li>
                    </ul>
                </div>

                <div v-if="actual.estado === 'CONVERTIDA'" class="alert alert-soft-success fs-sm mt-3 mb-0">
                    Esta solicitud ya generó un presupuesto formal. Su estado no se cambia desde acá.
                </div>

                <div v-else class="form-group solicitud-web-estado" v-can="'solicitudes-web.gestionar'">
                    <label class="form-label" for="estado">Marcar como</label>
                    <select id="estado" v-model="form.estado" class="form-control">
                        <option value="NUEVA">Sin atender</option>
                        <option value="CONTACTADA">Contactada</option>
                        <option value="DESCARTADA">Descartada (spam o no prosperó)</option>
                    </select>
                    <p v-if="form.errors.estado" class="form-error">{{ form.errors.estado }}</p>
                    <p class="fs-sm text-muted mt-1">
                        Para emitir el presupuesto real, crea una cotización en Ventas → Cotizaciones con
                        estos datos.
                    </p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="closeModal">Cerrar</button>
                <button v-if="actual?.estado !== 'CONVERTIDA'" v-can="'solicitudes-web.gestionar'" type="submit"
                    class="btn btn-primary" :disabled="form.processing">
                    Guardar estado
                </button>
            </div>
        </form>
    </Modal>
</template>
