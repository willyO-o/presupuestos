<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClientePortalLayout from '@/Layouts/ClientePortalLayout.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: ClientePortalLayout });

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

const puedeResponder = computed(() => props.cotizacion.estado === 'PENDIENTE' && Number(props.cotizacion.total) > 0);

function money(v) {
    return `Bs ${Number(v ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function fecha(v) {
    return v ? new Date(v).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}

const form = useForm({ accion: null });

async function responder(accion) {
    const [msg, boton] = accion === 'aprobar'
        ? ['¿Aprobar este presupuesto? El equipo comenzará la producción.', 'Sí, aprobar']
        : ['¿Rechazar este presupuesto?', 'Sí, rechazar'];
    if (!(await confirmation(msg, 'Confirmar', boton))) return;
    form.accion = accion;
    form.post(route('portal.responder', props.cotizacion.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Cotización ${cotizacion.codigo_verificacion}`" />

    <Link :href="route('portal.cotizaciones')" class="btn btn-soft-secondary btn-sm mb-3">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </Link>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                <div>
                    <p class="fs-lg fw-bold mb-1">{{ cotizacion.codigo_verificacion }}</p>
                    <span class="badge" :class="estadoBadge[cotizacion.estado] ?? 'badge-soft-secondary'">
                        {{ cotizacion.estado }}
                    </span>
                </div>
                <div class="text-end fs-sm">
                    <p class="mb-0"><span class="text-muted">Fecha:</span> {{ fecha(cotizacion.fecha) }}</p>
                    <p class="mb-0"><span class="text-muted">Válida hasta:</span> {{ fecha(cotizacion.fecha_vencimiento) }}</p>
                    <p class="mb-0"><span class="text-muted">Sucursal:</span> {{ cotizacion.sucursal?.nombre ?? '—' }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-dashboard table-sm">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-center">Medidas</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">P. unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in cotizacion.detalles" :key="d.id">
                            <td>{{ d.descripcion }}</td>
                            <td class="text-center">
                                <span v-if="d.ancho && d.alto">{{ Number(d.ancho).toFixed(2) }} × {{ Number(d.alto).toFixed(2) }} m</span>
                                <span v-else>—</span>
                            </td>
                            <td class="text-end">{{ Number(d.cantidad) }}</td>
                            <td class="text-end">{{ money(d.precio_unitario) }}</td>
                            <td class="text-end fw-semibold">{{ money(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="compra-total-row mt-3">
                <span>Total</span><span>{{ money(cotizacion.total) }}</span>
            </div>

            <p v-if="cotizacion.estado === 'PENDIENTE' && Number(cotizacion.total) === 0" class="fs-sm text-muted mt-3">
                Tu solicitud fue recibida. Un asesor está preparando el presupuesto con los precios.
            </p>

            <div v-if="puedeResponder" class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-success" :disabled="form.processing" @click="responder('aprobar')">
                    <i class="fa-solid fa-check"></i> Aprobar presupuesto
                </button>
                <button type="button" class="btn btn-soft-danger" :disabled="form.processing"
                    @click="responder('rechazar')">
                    <i class="fa-solid fa-xmark"></i> Rechazar
                </button>
            </div>

            <div v-if="cotizacion.pedido" class="mt-4 fs-sm">
                <span class="text-muted">Pedido generado:</span>
                <Link :href="route('portal.pedido', cotizacion.pedido.id)" class="text-primary fw-semibold">
                    {{ cotizacion.pedido.numero_pedido }}
                </Link>
                — {{ cotizacion.pedido.estado }}
            </div>
        </div>
    </div>
</template>
