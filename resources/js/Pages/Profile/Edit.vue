<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    // Ficha de empleado (con sucursal/area cargadas) vinculada a esta
    // cuenta, si existe. Solo lectura aqui: se edita desde el modulo
    // Empleados (ver app/Http/Controllers/EmpleadoController.php).
    empleado: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles ?? []);
const roleLabel = computed(() => {
    if (props.empleado?.cargo) {
        return props.empleado.cargo;
    }

    return roles.value[0]
        ? roles.value[0].replaceAll('-', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : 'Usuario';
});

// Sin tab de "Cuenta"/eliminar cuenta a propósito: las cuentas se crean y
// se dan de baja desde el módulo Usuarios (administración), no de forma
// autoservicio desde el perfil.
const tabs = [
    { key: 'personal', label: 'Datos personales', icon: 'fa-solid fa-id-card' },
    { key: 'seguridad', label: 'Cambiar contraseña', icon: 'fa-solid fa-lock' },
];
const activeTab = ref('personal');

/* ── Foto de perfil: input oculto disparado por el boton camara ────────── */

const fotoInput = ref(null);
const fotoPreview = ref(null);

// Se sube en su propio form (no comparte instancia con
// UpdateProfileInformationForm) para poder guardarla al instante, sin
// esperar a que el usuario toque "Guardar cambios" en Datos personales.
// Los datos se pasan como funcion por el mismo motivo que en el resto de
// formularios del proyecto: reset()/recreacion siempre parte de los
// valores actuales, no de los ultimos enviados.
//
// `_method: 'patch'` + form.post() en vez de form.patch(): cuando el
// payload trae un archivo, Inertia lo manda como multipart/form-data, y
// PHP/Laravel solo parsean el body (y $_FILES) de una request multipart
// si el verbo HTTP real es POST — con PATCH/PUT el archivo se pierde en
// silencio (la request llega vacia al backend, falla la validacion de
// name/email "required" con 422, y como este form no muestra esos
// errores no se ve nada raro, solo que la foto "no se actualiza"). Por
// eso se envia como POST real con _method spoofeado, igual que hace
// Laravel/Jetstream para subir avatar.
const fotoForm = useForm(() => ({
    _method: 'patch',
    name: user.value.name,
    email: user.value.email,
    foto: null,
}));

function openFotoPicker() {
    fotoInput.value?.click();
}

function onFotoChange(event) {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    fotoForm.foto = file;
    fotoForm.name = user.value.name;
    fotoForm.email = user.value.email;
    fotoPreview.value = URL.createObjectURL(file);

    fotoForm.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            fotoPreview.value = null;
        },
        onFinish: () => {
            fotoForm.foto = null;
            if (fotoInput.value) {
                fotoInput.value.value = '';
            }
        },
    });
}
</script>

<template>

    <Head title="Mi perfil" />

    <div class="profile-cover"></div>

    <div class="row profile-content-row">
        <div class="col-lg-4">
            <div class="card profile-header-card">
                <div class="profile-avatar-frame">
                    <span class="avatar avatar-xl">
                        <img v-if="fotoPreview || user.foto_url" :src="fotoPreview || user.foto_url" alt="Foto de perfil"
                            class="h-full w-full object-cover" />
                        <span v-else class="avatar-title avatar-title-lg">
                            {{ user.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                        </span>
                    </span>

                    <button type="button" class="profile-avatar-edit" title="Cambiar foto de perfil"
                        :disabled="fotoForm.processing" @click="openFotoPicker">
                        <i v-if="fotoForm.processing" class="fa-solid fa-spinner fa-spin"></i>
                        <i v-else class="fa-solid fa-camera"></i>
                    </button>
                    <input ref="fotoInput" type="file" accept="image/*" class="hidden" @change="onFotoChange" />
                </div>

                <p class="profile-widget-name mt-3">{{ user.name }}</p>
                <p class="profile-widget-role capitalize">{{ roleLabel }}</p>
                <p v-if="fotoForm.errors.foto" class="form-error text-center">{{ fotoForm.errors.foto }}</p>

                <div class="mt-4 text-start">
                    <div class="profile-info-item">
                        <span class="profile-info-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="profile-info-label">Correo</p>
                            <p class="profile-info-value">{{ user.email }}</p>
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <span class="profile-info-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="profile-info-label">Rol</p>
                            <p class="profile-info-value capitalize">{{ roles.join(', ') || 'Sin rol asignado' }}</p>
                        </div>
                    </div>

                    <div v-if="empleado" class="profile-info-item">
                        <span class="profile-info-icon">
                            <i class="fa-solid fa-building-user"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="profile-info-label">Sucursal</p>
                            <p class="profile-info-value">{{ empleado.sucursal?.nombre ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <nav class="nav-tabs">
                    <button v-for="tab in tabs" :key="tab.key" type="button" class="nav-link"
                        :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
                        <i :class="tab.icon"></i>
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="card-body">
                    <UpdateProfileInformationForm v-show="activeTab === 'personal'" :must-verify-email="mustVerifyEmail"
                        :status="status" :empleado="empleado" />

                    <UpdatePasswordForm v-show="activeTab === 'seguridad'" />
                </div>
            </div>
        </div>
    </div>
</template>
