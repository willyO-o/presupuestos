<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { showError } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    cotizaciones: { type: Array, default: () => [] },
    cotizacionId: { type: [Number, String], default: null },
});

const form = useForm(() => ({
    cotizacion_id: props.cotizacionId ?? (props.cotizaciones[0]?.id ?? ''),
    fecha_entrega_estimada: '',
}));

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function submit() {
    form.post(route('pedidos.store'), { onError: (errors) => showError(errors) });
}
</script>

<template>
    <Head title="Nuevo pedido" />

    <form class="card form-narrow" @submit.prevent="submit">
        <div class="card-header">
            <span class="card-title">Generar pedido desde una cotización aprobada</span>
        </div>
        <div class="card-body">
            <p v-if="!cotizaciones.length" class="text-muted mb-0">
                No hay cotizaciones aprobadas pendientes de convertir. Aprueba una cotización primero.
            </p>

            <template v-else>
                <div class="form-group">
                    <label class="form-label" for="cotizacion_id">Cotización</label>
                    <select id="cotizacion_id" v-model="form.cotizacion_id" class="form-control"
                        :class="{ 'is-invalid': form.errors.cotizacion_id }" required>
                        <option value="" disabled>Selecciona una cotización</option>
                        <option v-for="c in cotizaciones" :key="c.id" :value="c.id">
                            {{ c.codigo_verificacion }} — {{ c.cliente?.razon_social ?? '—' }} — {{ money(c.total) }}
                        </option>
                    </select>
                    <p v-if="form.errors.cotizacion_id" class="form-error">{{ form.errors.cotizacion_id }}</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fecha_entrega_estimada">Fecha de entrega estimada (opcional)</label>
                    <input id="fecha_entrega_estimada" v-model="form.fecha_entrega_estimada" type="date"
                        class="form-control" :class="{ 'is-invalid': form.errors.fecha_entrega_estimada }" />
                    <p v-if="form.errors.fecha_entrega_estimada" class="form-error">
                        {{ form.errors.fecha_entrega_estimada }}
                    </p>
                </div>
            </template>
        </div>
        <div class="card-body d-flex justify-content-end gap-2">
            <Link :href="route('pedidos.index')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :disabled="form.processing || !cotizaciones.length">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-dolly"></i>
                Generar pedido
            </button>
        </div>
    </form>
</template>
