<script setup>
/**
 * Campos del formulario de empleado, compartidos entre Empleados/Index.vue
 * y los modales de alta rápida embebidos en los formularios que eligen un
 * "responsable"/"vendedor"/"entregado por" (Cotizaciones, Compras, Notas de
 * entrega). `usuarios` es opcional: los formularios que solo necesitan
 * elegir un empleado (no vincular su cuenta) pueden omitirlo y el campo
 * queda en "Sin vincular".
 */
defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    sucursales: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargos: { type: Array, default: () => [] },
    usuarios: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="row">
        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-nombres">Nombres</label>
                <input id="empleado-nombres" v-model="form.nombres" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.nombres }" required autofocus />
                <p v-if="errors.nombres" class="form-error">{{ errors.nombres }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-paterno">Apellido paterno</label>
                <input id="empleado-paterno" v-model="form.paterno" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.paterno }" />
                <p v-if="errors.paterno" class="form-error">{{ errors.paterno }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-materno">Apellido materno</label>
                <input id="empleado-materno" v-model="form.materno" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.materno }" />
                <p v-if="errors.materno" class="form-error">{{ errors.materno }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="empleado-ci">CI</label>
                <input id="empleado-ci" v-model="form.ci" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.ci }" required />
                <p v-if="errors.ci" class="form-error">{{ errors.ci }}</p>
            </div>
        </div>

        <div v-if="usuarios.length" class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="empleado-user">Cuenta de usuario (opcional)</label>
                <select id="empleado-user" v-model="form.user_id" class="form-control"
                    :class="{ 'is-invalid': errors.user_id }">
                    <option value="">Sin vincular</option>
                    <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
                        {{ usuario.name }} ({{ usuario.email }})
                    </option>
                </select>
                <p v-if="errors.user_id" class="form-error">{{ errors.user_id }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="empleado-sucursal">Sucursal</label>
                <select id="empleado-sucursal" v-model="form.sucursal_id" class="form-control"
                    :class="{ 'is-invalid': errors.sucursal_id }" required>
                    <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                        {{ sucursal.nombre }}
                    </option>
                </select>
                <p v-if="errors.sucursal_id" class="form-error">{{ errors.sucursal_id }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="empleado-area">Área</label>
                <select id="empleado-area" v-model="form.area_id" class="form-control"
                    :class="{ 'is-invalid': errors.area_id }" required>
                    <option v-for="area in areas" :key="area.id" :value="area.id">
                        {{ area.nombre }}
                    </option>
                </select>
                <p v-if="errors.area_id" class="form-error">{{ errors.area_id }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-cargo">Cargo</label>
                <select id="empleado-cargo" v-model="form.cargo" class="form-control"
                    :class="{ 'is-invalid': errors.cargo }" required>
                    <option value="" disabled>Selecciona un cargo</option>
                    <option v-for="c in cargos" :key="c" :value="c">{{ c }}</option>
                </select>
                <p v-if="errors.cargo" class="form-error">{{ errors.cargo }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-telefono">Teléfono</label>
                <input id="empleado-telefono" v-model="form.telefono" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.telefono }" />
                <p v-if="errors.telefono" class="form-error">{{ errors.telefono }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="empleado-fecha-ingreso">Fecha de ingreso</label>
                <input id="empleado-fecha-ingreso" v-model="form.fecha_ingreso" type="date" class="form-control"
                    :class="{ 'is-invalid': errors.fecha_ingreso }" required />
                <p v-if="errors.fecha_ingreso" class="form-error">{{ errors.fecha_ingreso }}</p>
            </div>
        </div>
    </div>

    <div class="form-group mb-0">
        <label class="form-label" for="empleado-estado">Estado</label>
        <select id="empleado-estado" v-model="form.estado" class="form-control">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
        </select>
    </div>
</template>
