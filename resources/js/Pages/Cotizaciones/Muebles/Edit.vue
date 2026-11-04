<script setup>
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import CotizacionMuebleForm from '../Partials/CotizacionMuebleForm.vue';

defineOptions({ layout: MainDashboardLayout });

defineProps({
    cotizacion: { type: Object, required: true },
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
    materiales: { type: Array, default: () => [] },
    tiposProyecto: { type: Array, default: () => [] },
    tiposItem: { type: Object, default: () => ({}) },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Editar ${cotizacion.codigo_verificacion}`" />

    <CotizacionMuebleForm :cotizacion="cotizacion" :clientes="clientes" :empleados="empleados"
        :sucursales="sucursales" :productos="productos" :materiales="materiales" :tipos-proyecto="tiposProyecto" :tipos-item="tiposItem"
        :empleado-actual-id="empleadoActualId" :config="config" />
</template>
