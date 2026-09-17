<script setup>
/**
 * Alta rápida de cliente desde OTRO formulario (por ahora, la línea
 * "Cliente" de Cotizaciones/Partials/CotizacionForm.vue). Delega el modal,
 * el guardado por axios y los errores 422 en QuickCreateModal.vue; acá solo
 * quedan los campos (compartidos con Clientes/Index.vue, ver
 * ClienteFormFields.vue) y sus valores por defecto.
 */
import QuickCreateModal from '@/Components/QuickCreateModal.vue';
import ClienteFormFields from '@/Components/Cliente/ClienteFormFields.vue';

defineProps({
    show: { type: Boolean, default: false },
});

defineEmits(['close', 'created']);

function datosVacios() {
    return {
        tipo: 'JURIDICO',
        razon_social: '',
        nit: '',
        contacto_nombre: '',
        telefono: '',
        email: '',
        direccion: '',
        ciudad: '',
        estado: 'ACTIVO',
    };
}
</script>

<template>
    <QuickCreateModal :show="show" title="Nuevo cliente" route-name="clientes.rapido" :initial-data="datosVacios"
        submit-label="Crear cliente"
        hint="Se guarda de una vez en el catálogo de clientes; al terminar queda elegido en esta cotización."
        @close="$emit('close')" @created="(cliente) => $emit('created', cliente)">
        <template #default="{ form, errors }">
            <ClienteFormFields :form="form" :errors="errors" />
        </template>
    </QuickCreateModal>
</template>
