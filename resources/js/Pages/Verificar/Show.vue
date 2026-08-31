<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    codigo: { type: String, required: true },
    cotizacion: { type: Object, default: null },
});

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    APROBADA: 'badge-soft-success',
    RECHAZADA: 'badge-soft-danger',
    CONVERTIDA: 'badge-soft-info',
    VENCIDA: 'badge-soft-secondary',
};

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}
</script>

<template>
    <Head title="Verificación de presupuesto" />

    <div class="verificar-shell">
        <div class="card verificar-card">
            <div class="card-body text-center">
                <img src="/img/logo/logo.webp" alt="XtraPubli" class="verificar-logo" />
                <p class="fs-sm text-muted mb-4">Verificación de autenticidad de presupuesto</p>

                <template v-if="cotizacion">
                    <span class="badge" :class="estadoBadge[cotizacion.estado] ?? 'badge-soft-secondary'">
                        {{ cotizacion.estado }}
                    </span>
                    <p class="fs-lg fw-bold mt-3 mb-1">{{ cotizacion.codigo_verificacion }}</p>
                    <p class="mb-1"><span class="text-muted">Cliente:</span> {{ cotizacion.cliente ?? '—' }}</p>
                    <p class="mb-1"><span class="text-muted">Fecha:</span> {{ fecha(cotizacion.fecha) }}</p>
                    <p class="mb-1"><span class="text-muted">Válida hasta:</span> {{ fecha(cotizacion.fecha_vencimiento) }}</p>
                    <p class="fs-lg fw-bold mt-3">{{ money(cotizacion.total) }}</p>
                    <p class="fs-xs text-muted mt-3 mb-0">
                        Documento emitido por el sistema de costos y presupuestos de XtraPubli.
                    </p>
                </template>

                <template v-else>
                    <i class="fa-solid fa-circle-xmark text-danger verificar-icon"></i>
                    <p class="fw-semibold mt-3 mb-1">Código no encontrado</p>
                    <p class="fs-sm text-muted mb-0">
                        No existe ningún presupuesto con el código <strong>{{ codigo }}</strong>.
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
