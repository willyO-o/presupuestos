<script setup>
/**
 * Campos del formulario de categoría de producto, compartidos entre
 * CategoriasProducto/Index.vue y el modal de alta rápida embebido en
 * Productos/Index.vue (ver .ai/rules — mismo patrón de ClienteFormFields).
 */
defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="form-group">
        <label class="form-label" for="categoria-producto-nombre">Nombre</label>
        <input id="categoria-producto-nombre" v-model="form.nombre" type="text" class="form-control"
            :class="{ 'is-invalid': errors.nombre }" required autofocus />
        <p v-if="errors.nombre" class="form-error">{{ errors.nombre }}</p>
    </div>

    <div class="form-group mb-0">
        <label class="form-label" for="categoria-producto-estado">Estado</label>
        <select id="categoria-producto-estado" v-model="form.estado" class="form-control">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
        </select>
    </div>
</template>
