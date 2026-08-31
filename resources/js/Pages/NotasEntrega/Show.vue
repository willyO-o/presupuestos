<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    nota: { type: Object, required: true },
});

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
</script>

<template>
    <Head :title="`Nota de entrega ${nota.numero_nota}`" />

    <div class="card mb-4 compra-actions">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <Link :href="route('notas-entrega.index')" class="btn btn-soft-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </Link>
            <div class="d-flex flex-wrap gap-2">
                <Link :href="route('pedidos.show', nota.pedido_id)" class="btn btn-soft-info btn-sm">
                    <i class="fa-solid fa-dolly"></i> Ver pedido
                </Link>
                <a v-if="nota.archivo_url" :href="nota.archivo_url" target="_blank" rel="noopener"
                    class="btn btn-soft-secondary btn-sm">
                    <i class="fa-solid fa-file-pdf"></i> PDF firmado
                </a>
                <button type="button" class="btn btn-soft-secondary btn-sm" @click="imprimir">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>

    <div class="card compra-doc">
        <div class="card-body">
            <div class="compra-doc-head">
                <div>
                    <p class="fs-xl fw-bold mb-0 text-heading">XtraPubli</p>
                    <p class="fs-sm text-muted mb-0">Nota de entrega</p>
                </div>
                <div class="text-end">
                    <p class="fs-lg fw-bold mb-1">{{ nota.numero_nota }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Pedido:</span>
                        {{ nota.pedido?.numero_pedido ?? '—' }}</p>
                </div>
            </div>

            <div class="compra-doc-meta">
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Cliente</p>
                    <p class="fw-semibold mb-0">{{ nota.pedido?.cotizacion?.cliente?.razon_social ?? '—' }}</p>
                </div>
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Entrega</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Fecha:</span> {{ fecha(nota.fecha_entrega) }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Entregó:</span> {{ nombreEmpleado(nota.empleado) }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Recibió:</span> {{ nota.recibido_por ?? '—' }}
                        <span v-if="nota.cargo_receptor">({{ nota.cargo_receptor }})</span>
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-dashboard table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Descripción</th>
                            <th class="text-end">Cant.</th>
                            <th>Ubicación</th>
                            <th>Evidencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in nota.detalles" :key="d.id">
                            <td>{{ i + 1 }}</td>
                            <td>{{ d.descripcion }}</td>
                            <td class="text-end">{{ Number(d.cantidad_entregada) }}</td>
                            <td>{{ d.ubicacion ?? '—' }}</td>
                            <td>
                                <a v-if="d.foto_publica_url" :href="d.foto_publica_url" target="_blank" rel="noopener">
                                    <img :src="d.foto_publica_url" alt="Evidencia" class="article-thumb" />
                                </a>
                                <span v-else class="text-muted">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="nota.observaciones" class="compra-doc-notes">
                <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Observaciones</p>
                <p class="fs-sm mb-0">{{ nota.observaciones }}</p>
            </div>
        </div>
    </div>
</template>
