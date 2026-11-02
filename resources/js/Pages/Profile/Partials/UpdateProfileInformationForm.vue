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
    </section>
</template>
