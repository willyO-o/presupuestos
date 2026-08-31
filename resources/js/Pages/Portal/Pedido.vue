<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientePortalLayout from '@/Layouts/ClientePortalLayout.vue';

defineOptions({ layout: ClientePortalLayout });

const props = defineProps({
    pedido: { type: Object, required: true },
    cobranza: { type: Object, required: true },
});

const flujo = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'];

function money(v) {
    return `Bs ${Number(v ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function fecha(v) {
    return v ? new Date(v).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}
function pasoIndex(estado) {
    return flujo.indexOf(estado);
}
</script>

<template>
    <Head :title="`Pedido ${pedido.numero_pedido}`" />

    <Link :href="route('portal.pedidos')" class="btn btn-soft-secondary btn-sm mb-3">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </Link>

    <div class="card mb-4">
        <div class="card-body">
            <p class="fs-lg fw-bold mb-1">{{ pedido.numero_pedido }}</p>
            <p class="fs-sm text-muted mb-3">
                Cotización {{ pedido.cotizacion?.codigo_verificacion }} · Entrega estimada: {{ fecha(pedido.fecha_entrega_estimada) }}
                <span v-if="pedido.fecha_entrega_real"> · Entregado: {{ fecha(pedido.fecha_entrega_real) }}</span>
            </p>

            <div class="pedido-tracker">
                <div v-for="(paso, i) in flujo" :key="paso" class="pedido-tracker-step"
                    :class="{ done: pedido.estado !== 'CANCELADO' && i <= pasoIndex(pedido.estado) }">
                    <span class="pedido-tracker-dot">{{ i + 1 }}</span>
                    <span class="pedido-tracker-label">{{ paso }}</span>
                </div>
            </div>
            <p v-if="pedido.estado === 'CANCELADO'" class="text-danger fw-semibold mt-3 mb-0">Pedido cancelado</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><span class="card-title">Ítems</span></div>
                <div class="card-body">
                    <ul class="list-group">
                        <li v-for="d in pedido.detalles" :key="d.id" class="list-group-item">
                            <span class="list-group-item-title">{{ d.descripcion }}</span>
                            <span class="badge badge-soft-secondary">{{ d.estado_item }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><span class="card-title">Estado de cuenta</span></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between fw-bold mb-2">
                        <span>Total</span><span>{{ money(cobranza.total) }}</span>
                    </div>
                    <p class="fs-sm mb-1"><span class="text-muted">Pagado:</span> {{ money(cobranza.pagado) }}</p>
                    <p class="fs-sm mb-2"><span class="text-muted">Saldo:</span> {{ money(cobranza.saldo) }}</p>
                    <span class="badge"
                        :class="{ 'badge-soft-success': cobranza.estado === 'PAGADO', 'badge-soft-info': cobranza.estado === 'PARCIAL', 'badge-soft-warning': cobranza.estado === 'PENDIENTE' }">
                        {{ cobranza.estado }}
                    </span>
                </div>
            </div>
            <div v-if="pedido.notas_entrega?.length" class="card">
                <div class="card-header"><span class="card-title">Notas de entrega</span></div>
                <div class="card-body">
                    <ul class="pedido-consumo-lista">
                        <li v-for="n in pedido.notas_entrega" :key="n.id">
                            {{ n.numero_nota }} — {{ fecha(n.fecha_entrega) }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
