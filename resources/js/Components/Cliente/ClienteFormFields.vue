<script setup>
/**
 * Campos del formulario de cliente, compartidos entre Clientes/Index.vue
 * (CRUD completo, con Inertia `useForm`) y ClienteQuickCreateModal.vue (alta
 * rápida desde otro formulario, ver CotizacionForm.vue). Un solo lugar para
 * el layout: agregar un campo acá lo agrega en los dos formularios a la vez.
 *
 * `form` es el objeto reactivo con los valores (Inertia `useForm` u otro
 * `reactive()`) y `errors` un objeto plano `{ campo: 'mensaje' }` — la misma
 * forma que expone `form.errors` en Inertia, así que desde ese lado alcanza
 * con pasar `:errors="form.errors"`.
 */
defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="row">
        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="cliente-tipo">Tipo</label>
                <select id="cliente-tipo" v-model="form.tipo" class="form-control">
                    <option value="JURIDICO">Jurídico</option>
                    <option value="NATURAL">Natural</option>
                </select>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="form-group">
                <label class="form-label" for="cliente-razon-social">Razón social</label>
                <input id="cliente-razon-social" v-model="form.razon_social" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.razon_social }" required autofocus />
                <p v-if="errors.razon_social" class="form-error">{{ errors.razon_social }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-nit">NIT</label>
                <input id="cliente-nit" v-model="form.nit" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.nit }" required />
                <p v-if="errors.nit" class="form-error">{{ errors.nit }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-contacto">Contacto</label>
                <input id="cliente-contacto" v-model="form.contacto_nombre" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.contacto_nombre }" />
                <p v-if="errors.contacto_nombre" class="form-error">{{ errors.contacto_nombre }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-telefono">Teléfono</label>
                <input id="cliente-telefono" v-model="form.telefono" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.telefono }" />
                <p v-if="errors.telefono" class="form-error">{{ errors.telefono }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-email">Email</label>
                <input id="cliente-email" v-model="form.email" type="email" class="form-control"
                    :class="{ 'is-invalid': errors.email }" />
                <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-direccion">Dirección</label>
                <input id="cliente-direccion" v-model="form.direccion" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.direccion }" />
                <p v-if="errors.direccion" class="form-error">{{ errors.direccion }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="cliente-ciudad">Ciudad</label>
                <input id="cliente-ciudad" v-model="form.ciudad" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.ciudad }" />
                <p v-if="errors.ciudad" class="form-error">{{ errors.ciudad }}</p>
            </div>
        </div>
    </div>

    <div class="form-group mb-0">
        <label class="form-label" for="cliente-estado">Estado</label>
        <select id="cliente-estado" v-model="form.estado" class="form-control">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
        </select>
    </div>
</template>
