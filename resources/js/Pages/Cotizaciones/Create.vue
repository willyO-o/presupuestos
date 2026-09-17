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
    tiposProyecto: { type: Array, default: () => [] },
    tiposItem: { type: Object, default: () => ({}) },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
    categoriasProducto: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargosEmpleado: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Nueva cotización" />

    <CotizacionForm :clientes="clientes" :empleados="empleados" :sucursales="sucursales" :productos="productos" :tipos-proyecto="tiposProyecto" :tipos-item="tiposItem"
        :empleado-actual-id="empleadoActualId" :config="config" :categorias-producto="categoriasProducto" :areas="areas"
        :cargos-empleado="cargosEmpleado" />
</template>
