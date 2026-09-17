<script setup>
/**
 * Campos del formulario de proveedor, compartidos entre
 * Proveedores/Index.vue y el modal de alta rápida embebido en
 * Compras/Partials/CompraForm.vue.
 */
defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="form-group">
        <label class="form-label" for="proveedor-nombre">Nombre</label>
        <input id="proveedor-nombre" v-model="form.nombre" type="text" class="form-control"
            :class="{ 'is-invalid': errors.nombre }" required autofocus />
        <p v-if="errors.nombre" class="form-error">{{ errors.nombre }}</p>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="proveedor-nit">NIT</label>
                <input id="proveedor-nit" v-model="form.nit" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.nit }" />
                <p v-if="errors.nit" class="form-error">{{ errors.nit }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="proveedor-contacto">Contacto</label>
                <input id="proveedor-contacto" v-model="form.contacto" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.contacto }" />
                <p v-if="errors.contacto" class="form-error">{{ errors.contacto }}</p>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="proveedor-direccion">Dirección</label>
        <input id="proveedor-direccion" v-model="form.direccion" type="text" class="form-control"
            :class="{ 'is-invalid': errors.direccion }" />
        <p v-if="errors.direccion" class="form-error">{{ errors.direccion }}</p>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label class="form-label" for="proveedor-telefono">Teléfono</label>
                <input id="proveedor-telefono" v-model="form.telefono" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.telefono }" />
                <p v-if="errors.telefono" class="form-error">{{ errors.telefono }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label class="form-label" for="proveedor-estado">Estado</label>
                <select id="proveedor-estado" v-model="form.estado" class="form-control">
                    <option value="ACTIVO">Activo</option>
                    <option value="INACTIVO">Inactivo</option>
                </select>
            </div>
        </div>
    </div>
</template>
