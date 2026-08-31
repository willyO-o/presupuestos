<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { showError } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    rol: { type: Object, default: null },
    modulos: { type: Array, default: () => [] },
});

const esEdicion = computed(() => !!props.rol);

const form = useForm(() => ({
    name: props.rol?.name ?? '',
    permissions: [...(props.rol?.permissions ?? [])],
}));

function permisosDeModulo(modulo) {
    return modulo.permisos.map((p) => p.name);
}

function todosMarcados(modulo) {
    return permisosDeModulo(modulo).every((p) => form.permissions.includes(p));
}

function alternarModulo(modulo) {
    const permisos = permisosDeModulo(modulo);
    if (todosMarcados(modulo)) {
        form.permissions = form.permissions.filter((p) => !permisos.includes(p));
    } else {
        form.permissions = [...new Set([...form.permissions, ...permisos])];
    }
}

function submit() {
    const options = { onError: (errors) => showError(errors) };
    if (esEdicion.value) {
        form.put(route('roles.update', props.rol.id), options);
    } else {
        form.post(route('roles.store'), options);
    }
}
</script>

<template>
    <Head :title="esEdicion ? `Editar rol ${rol.name}` : 'Nuevo rol'" />

    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Datos del rol</span></div>
            <div class="card-body">
                <div class="form-group form-narrow">
                    <label class="form-label" for="name">Nombre (kebab-case)</label>
                    <input id="name" v-model="form.name" type="text" class="form-control"
                        :class="{ 'is-invalid': form.errors.name }" placeholder="jefe-taller" required />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Permisos</span>
                <span class="text-muted fs-sm">{{ form.permissions.length }} seleccionados</span>
            </div>
            <div class="card-body">
                <div v-for="modulo in modulos" :key="modulo.clave" class="rol-modulo">
                    <label class="d-flex align-items-center gap-2 fw-semibold mb-2">
                        <input type="checkbox" :checked="todosMarcados(modulo)" @change="alternarModulo(modulo)" />
                        {{ modulo.label }}
                    </label>
                    <div class="rol-permisos">
                        <label v-for="permiso in modulo.permisos" :key="permiso.name"
                            class="d-flex align-items-center gap-2 fs-sm">
                            <input v-model="form.permissions" type="checkbox" :value="permiso.name" />
                            {{ permiso.label }}
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('roles.index')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-floppy-disk"></i>
                {{ esEdicion ? 'Guardar cambios' : 'Crear rol' }}
            </button>
        </div>
    </form>
</template>
