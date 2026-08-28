<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    cotizacion: { type: Object, required: true },
});

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    APROBADA: 'badge-soft-success',
    RECHAZADA: 'badge-soft-danger',
    CONVERTIDA: 'badge-soft-info',
    VENCIDA: 'badge-soft-secondary',
};

const esPendiente = computed(() => props.cotizacion.estado === 'PENDIENTE');

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}

function nombreEmpleado(e) {
    if (!e) return '—';
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function imprimir() {
    window.print();
}

const accionForm = useForm({});

async function cambiarEstado(accion) {
    const textos = {
        aprobar: ['¿Aprobar esta cotización?', 'Aprobar cotización', 'Sí, aprobar'],
        rechazar: ['¿Rechazar esta cotización?', 'Rechazar cotización', 'Sí, rechazar'],
    };
    const [msg, titulo, boton] = textos[accion];
    if (!(await confirmation(msg, titulo, boton))) return;

    accionForm.post(route(`cotizaciones.${accion}`, props.cotizacion.id), { preserveScroll: true });
}

async function eliminar() {
    const ok = await confirmation(
        `¿Eliminar la cotización <strong>${props.cotizacion.codigo_verificacion}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar cotización',
    );
    if (!ok) return;

    router.delete(route('cotizaciones.destroy', props.cotizacion.id));
}
</script>

<template>
    <Head :title="`Cotización ${cotizacion.codigo_verificacion}`" />

    <!-- Barra de acciones (no se imprime) -->
    <div class="card mb-4 cotizacion-actions">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
                Volver
            </Link>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-soft-secondary btn-sm" @click="imprimir">
                    <i class="fa-solid fa-print"></i>
                    Imprimir
                </button>
                <Link v-if="esPendiente" v-can="'cotizaciones.editar'" :href="route('cotizaciones.edit', cotizacion.id)"
                    class="btn btn-soft-primary btn-sm">
                    <i class="fa-solid fa-pen"></i>
                    Editar
                </Link>
                <button v-if="esPendiente" v-can="'cotizaciones.aprobar'" type="button" class="btn btn-success btn-sm"
                    @click="cambiarEstado('aprobar')">
                    <i class="fa-solid fa-check"></i>
                    Aprobar
                </button>
                <button v-if="esPendiente" v-can="'cotizaciones.aprobar'" type="button" class="btn btn-soft-warning btn-sm"
                    @click="cambiarEstado('rechazar')">
                    <i class="fa-solid fa-xmark"></i>
                    Rechazar
                </button>
                <button v-if="cotizacion.estado !== 'CONVERTIDA'" v-can="'cotizaciones.eliminar'" type="button"
                    class="btn btn-soft-danger btn-sm" @click="eliminar">
                    <i class="fa-solid fa-trash"></i>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <!-- Documento -->
    <div class="card cotizacion-doc">
        <div class="card-body">
            <div class="cotizacion-doc-head">
                <div>
                    <p class="fs-xl fw-bold mb-0 text-heading">XtraPubli</p>
                    <p class="fs-sm text-muted mb-0">Publicidad Industrial · El Alto, Bolivia</p>
                </div>
                <div class="text-end">
                    <p class="fs-lg fw-bold mb-1">COTIZACIÓN</p>
                    <p class="fs-sm mb-1">
                        <span class="text-muted">N.º </span>{{ cotizacion.codigo_verificacion }}
                    </p>
                    <span class="badge" :class="estadoBadge[cotizacion.estado] ?? 'badge-soft-secondary'">
                        {{ cotizacion.estado }}
                    </span>
                </div>
            </div>

            <div class="cotizacion-doc-meta">
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Cliente</p>
                    <p class="fw-semibold mb-0">{{ cotizacion.cliente?.razon_social ?? '—' }}</p>
                    <p class="fs-sm text-muted mb-0">NIT: {{ cotizacion.cliente?.nit ?? '—' }}</p>
                    <p v-if="cotizacion.cliente?.telefono" class="fs-sm text-muted mb-0">
                        Tel: {{ cotizacion.cliente.telefono }}
                    </p>
                </div>
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Detalles</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Fecha:</span> {{ fecha(cotizacion.fecha) }}</p>
                    <p class="fs-sm mb-0">
                        <span class="text-muted">Válida hasta:</span> {{ fecha(cotizacion.fecha_vencimiento) }}
                    </p>
                    <p class="fs-sm mb-0">
                        <span class="text-muted">Sucursal:</span> {{ cotizacion.sucursal?.nombre ?? '—' }}
                    </p>
                    <p class="fs-sm mb-0">
                        <span class="text-muted">Vendedor:</span> {{ nombreEmpleado(cotizacion.empleado) }}
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-dashboard table-sm cotizacion-doc-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Descripción</th>
                            <th class="text-center">Medidas (m)</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">P. unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in cotizacion.detalles" :key="d.id">
                            <td>{{ i + 1 }}</td>
                            <td>
                                {{ d.descripcion }}
                                <span v-if="d.producto" class="d-block fs-xs text-muted">{{ d.producto.nombre }}</span>
                            </td>
                            <td class="text-center">
                                <span v-if="d.ancho && d.alto">
                                    {{ Number(d.ancho).toFixed(2) }} × {{ Number(d.alto).toFixed(2) }}
                                    <span class="fs-xs text-muted">({{ Number(d.area_m2).toFixed(2) }} m²)</span>
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="text-end">{{ Number(d.cantidad) }}</td>
                            <td class="text-end">{{ money(d.precio_unitario) }}</td>
                            <td class="text-end fw-semibold">{{ money(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="cotizacion-doc-totals">
                <div class="cotizacion-total-row"><span>Subtotal</span><span>{{ money(cotizacion.subtotal) }}</span></div>
                <div v-if="Number(cotizacion.descuento) > 0" class="cotizacion-total-row">
                    <span>Descuento</span><span>− {{ money(cotizacion.descuento) }}</span>
                </div>
                <div v-if="Number(cotizacion.impuesto) > 0" class="cotizacion-total-row">
                    <span>Impuesto</span><span>{{ money(cotizacion.impuesto) }}</span>
                </div>
                <div class="cotizacion-total-row cotizacion-total-grand">
                    <span>Total</span><span>{{ money(cotizacion.total) }}</span>
                </div>
            </div>

            <div v-if="cotizacion.observaciones" class="cotizacion-doc-notes">
                <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Observaciones</p>
                <p class="fs-sm mb-0 text-prewrap">{{ cotizacion.observaciones }}</p>
            </div>

            <p class="fs-xs text-muted mt-4 mb-0">
                Documento generado por el sistema de costos y presupuestos XtraPubli.
                Código de verificación: <strong>{{ cotizacion.codigo_verificacion }}</strong>.
            </p>
        </div>
    </div>
</template>
