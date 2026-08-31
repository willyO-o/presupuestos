<script setup>
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import CompraForm from './Partials/CompraForm.vue';

defineOptions({ layout: MainDashboardLayout });

defineProps({
    proveedores: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    materiales: { type: Array, default: () => [] },
    empleadoActualId: { type: [Number, String], default: null },
});
</script>

<template>
    <Head title="Nueva compra" />

    <CompraForm :proveedores="proveedores" :empleados="empleados" :materiales="materiales"
        :empleado-actual-id="empleadoActualId" />
</template>
