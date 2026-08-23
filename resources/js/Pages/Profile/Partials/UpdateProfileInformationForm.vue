<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    // Ficha de empleado vinculada a esta cuenta, solo lectura (se edita
    // desde el modulo Empleados) — ver .ai/rules para el CRUD completo.
    empleado: {
        type: Object,
        default: null,
    },
});

const user = usePage().props.auth.user;

// Datos como funcion factory (no objeto plano): ver nota en
// .ai/rules/pages.md sobre por que reset() necesita esto para volver a
// los valores originales y no a los ultimos enviados.
const form = useForm(() => ({
    name: user.name,
    email: user.email,
}));

function submit() {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section>
        <header class="mb-4">
            <h2 class="card-title">Datos personales</h2>
            <p class="fs-sm text-muted mt-1">Actualiza tu nombre y tu correo electrónico.</p>
        </header>

        <form class="row" @submit.prevent="submit">
            <div class="col-lg-6">
                <div class="form-group">
                    <label class="form-label" for="name">Nombre</label>
                    <input id="name" v-model="form.name" type="text" class="form-control"
                        :class="{ 'is-invalid': form.errors.name }" required autofocus autocomplete="name" />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-group">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input id="email" v-model="form.email" type="email" class="form-control"
                        :class="{ 'is-invalid': form.errors.email }" required autocomplete="username" />
                    <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="col-12">
                <p class="fs-sm text-muted">
                    Tu correo electrónico no está verificado.
                    <Link :href="route('verification.send')" method="post" as="button" class="card-link">
                        Reenviar correo de verificación.
                    </Link>
                </p>

                <p v-show="status === 'verification-link-sent'" class="fs-sm text-success mt-2 font-medium">
                    Se envió un nuevo enlace de verificación a tu correo.
                </p>
            </div>

            <div class="col-12 flex items-center gap-3">
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    Guardar cambios
                </button>

                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <span v-if="form.recentlySuccessful" class="fs-sm text-muted">Guardado.</span>
                </Transition>
            </div>
        </form>

        <div class="profile-section-divider">
            <h3 class="card-title mb-3">Información laboral</h3>

            <p v-if="!empleado" class="fs-sm text-muted">
                Tu cuenta todavía no está vinculada a una ficha de empleado.
            </p>

            <div v-else>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-id-card"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Nombre completo</p>
                                <p class="profile-info-value">{{ empleado.nombre_completo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-address-card"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">CI</p>
                                <p class="profile-info-value">{{ empleado.ci }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-briefcase"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Cargo</p>
                                <p class="profile-info-value">{{ empleado.cargo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Teléfono</p>
                                <p class="profile-info-value">{{ empleado.telefono || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-building-user"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Sucursal</p>
                                <p class="profile-info-value">{{ empleado.sucursal?.nombre ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-building"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Área</p>
                                <p class="profile-info-value">{{ empleado.area?.nombre ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-calendar-day"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Fecha de ingreso</p>
                                <p class="profile-info-value">{{ empleado.fecha_ingreso }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="profile-info-item">
                            <span class="profile-info-icon">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-info-label">Estado</p>
                                <span class="badge" :class="empleado.estado === 'ACTIVO' ? 'badge-soft-success' : 'badge-soft-danger'">
                                    {{ empleado.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="fs-xs text-muted mt-3">
                    Estos datos se administran desde el módulo Empleados.
                </p>
            </div>
        </div>
    </section>
</template>
