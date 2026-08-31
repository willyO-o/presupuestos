<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    compra: { type: Object, required: true },
});

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    PAGADA: 'badge-soft-success',
    ANULADA: 'badge-soft-secondary',
};

const esPendiente = computed(() => props.compra.estado === 'PENDIENTE');

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

async function aprobar() {
    const ok = await confirmation(
        '¿Aprobar esta compra? Se sumará el stock y se actualizará el precio de cada material comprado.',
        'Aprobar compra',
        'Sí, aprobar',
    );
    if (!ok) return;
    accionForm.post(route('compras.aprobar', props.compra.id), { preserveScroll: true });
}

async function anular() {
    const ok = await confirmation('¿Anular esta compra?', 'Anular compra', 'Sí, anular');
    if (!ok) return;
    accionForm.post(route('compras.anular', props.compra.id), { preserveScroll: true });
}

async function eliminar() {
    const ok = await confirmation(
        `¿Eliminar la compra <strong>#${props.compra.id}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar compra',
    );
    if (!ok) return;
    router.delete(route('compras.destroy', props.compra.id));
}
</script>

<template>
    <Head :title="`Compra #${compra.id}`" />

    <div class="card mb-4 compra-actions">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <Link :href="route('compras.index')" class="btn btn-soft-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
                Volver
            </Link>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-soft-secondary btn-sm" @click="imprimir">
                    <i class="fa-solid fa-print"></i>
                    Imprimir
                </button>
                <Link v-if="esPendiente" v-can="'compras.editar'" :href="route('compras.edit', compra.id)"
                    class="btn btn-soft-primary btn-sm">
                    <i class="fa-solid fa-pen"></i>
                    Editar
                </Link>
                <button v-if="esPendiente" v-can="'compras.aprobar'" type="button" class="btn btn-success btn-sm"
                    @click="aprobar">
                    <i class="fa-solid fa-check"></i>
                    Aprobar
                </button>
                <button v-if="esPendiente" v-can="'compras.aprobar'" type="button" class="btn btn-soft-warning btn-sm"
                    @click="anular">
                    <i class="fa-solid fa-ban"></i>
                    Anular
                </button>
                <button v-if="compra.estado !== 'PAGADA'" v-can="'compras.eliminar'" type="button"
                    class="btn btn-soft-danger btn-sm" @click="eliminar">
                    <i class="fa-solid fa-trash"></i>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <div class="card compra-doc">
        <div class="card-body">
            <div class="compra-doc-head">
                <div>
                    <p class="fs-xl fw-bold mb-0 text-heading">XtraPubli</p>
                    <p class="fs-sm text-muted mb-0">Registro de compra de materiales</p>
                </div>
                <div class="text-end">
                    <p class="fs-lg fw-bold mb-1">COMPRA #{{ compra.id }}</p>
                    <span class="badge" :class="estadoBadge[compra.estado] ?? 'badge-soft-secondary'">
                        {{ compra.estado }}
                    </span>
                </div>
            </div>

            <div class="compra-doc-meta">
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Proveedor</p>
                    <p class="fw-semibold mb-0">{{ compra.proveedor?.nombre ?? '—' }}</p>
                    <p v-if="compra.proveedor?.nit" class="fs-sm text-muted mb-0">NIT: {{ compra.proveedor.nit }}</p>
                    <p v-if="compra.proveedor?.telefono" class="fs-sm text-muted mb-0">
                        Tel: {{ compra.proveedor.telefono }}
                    </p>
                </div>
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Detalles</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Fecha:</span> {{ fecha(compra.fecha) }}</p>
                    <p class="fs-sm mb-0">
                        <span class="text-muted">Factura:</span> {{ compra.numero_factura ?? '—' }}
                    </p>
                    <p class="fs-sm mb-0">
                        <span class="text-muted">Responsable:</span> {{ nombreEmpleado(compra.empleado) }}
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-dashboard table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Material</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">P. unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in compra.detalles" :key="d.id">
                            <td>{{ i + 1 }}</td>
                            <td>
                                {{ d.material?.nombre ?? '—' }}
                                <span v-if="d.material?.presentacion" class="d-block fs-xs text-muted">
                                    {{ d.material.presentacion }}
                                </span>
                            </td>
                            <td class="text-end">
                                {{ Number(d.cantidad) }}
                                <span class="fs-xs text-muted">{{ d.material?.unidad_medida }}</span>
                            </td>
                            <td class="text-end">{{ money(d.precio_unitario) }}</td>
                            <td class="text-end fw-semibold">{{ money(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="compra-doc-totals">
                <div class="compra-total-row">
                    <span>Total</span><span>{{ money(compra.total) }}</span>
                </div>
            </div>

            <p v-if="esPendiente" class="fs-xs text-muted mt-4 mb-0">
                Esta compra aún no impactó el inventario. Al aprobarla se sumará la cantidad al stock de cada material
                y su precio unitario se actualizará con el de esta compra (se guarda el historial de precios).
            </p>
        </div>
    </div>
</template>
