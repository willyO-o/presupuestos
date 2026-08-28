<script setup>
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import CotizacionForm from './Partials/CotizacionForm.vue';

defineOptions({ layout: MainDashboardLayout });

defineProps({
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
});
</script>

<template>
    <Head title="Nueva cotización" />

    <CotizacionForm :clientes="clientes" :empleados="empleados" :sucursales="sucursales" :productos="productos"
        :empleado-actual-id="empleadoActualId" :config="config" />
</template>
