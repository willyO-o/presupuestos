<script setup>
/**
 * Campos del formulario de categoría de producto, compartidos entre
 * CategoriasProducto/Index.vue y el modal de alta rápida embebido en
 * Productos/Index.vue (ver .ai/rules — mismo patrón de ClienteFormFields).
 */
defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    /**
     * Familias comerciales (agrupación del BI). Opcional: el alta rápida desde
     * Productos no la pide, y la categoría queda "Sin familia" hasta que se
     * clasifique desde el CRUD de categorías.
     */
    familias: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="form-group">
        <label class="form-label" for="categoria-producto-nombre">Nombre</label>
        <input id="categoria-producto-nombre" v-model="form.nombre" type="text" class="form-control"
            :class="{ 'is-invalid': errors.nombre }" required autofocus />
        <p v-if="errors.nombre" class="form-error">{{ errors.nombre }}</p>
    </div>

    <div v-if="familias.length" class="form-group">
        <label class="form-label" for="categoria-producto-familia">Familia</label>
        <select id="categoria-producto-familia" v-model="form.familia_producto_id" class="form-control"
            :class="{ 'is-invalid': errors.familia_producto_id }">
            <option :value="null">Sin familia</option>
            <option v-for="familia in familias" :key="familia.id" :value="familia.id">{{ familia.nombre }}</option>
        </select>
        <p class="fs-xs text-muted mt-1 mb-0">Agrupa la categoría en los reportes de inteligencia de negocios.</p>
        <p v-if="errors.familia_producto_id" class="form-error">{{ errors.familia_producto_id }}</p>
    </div>

    <div class="form-group mb-0">
        <label class="form-label" for="categoria-producto-estado">Estado</label>
        <select id="categoria-producto-estado" v-model="form.estado" class="form-control">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
        </select>
    </div>
</template>
