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
    categoriasMaterial: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargosEmpleado: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Nueva compra" />

    <CompraForm :proveedores="proveedores" :empleados="empleados" :materiales="materiales"
        :empleado-actual-id="empleadoActualId" :categorias-material="categoriasMaterial" :sucursales="sucursales"
        :areas="areas" :cargos-empleado="cargosEmpleado" />
</template>
