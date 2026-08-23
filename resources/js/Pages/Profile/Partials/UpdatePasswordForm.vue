<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
}
</script>

<template>
    <section>
        <header class="mb-4">
            <h2 class="card-title">Cambiar contraseña</h2>
            <p class="fs-sm text-muted mt-1">
                Usa una contraseña larga y única para mantener tu cuenta segura.
            </p>
        </header>

        <form class="row" @submit.prevent="updatePassword">
            <div class="col-12">
                <div class="form-group">
                    <label class="form-label" for="current_password">Contraseña actual</label>
                    <input id="current_password" ref="currentPasswordInput" v-model="form.current_password"
                        type="password" class="form-control" :class="{ 'is-invalid': form.errors.current_password }"
                        autocomplete="current-password" />
                    <p v-if="form.errors.current_password" class="form-error">{{ form.errors.current_password }}</p>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-group">
                    <label class="form-label" for="password">Nueva contraseña</label>
                    <input id="password" ref="passwordInput" v-model="form.password" type="password"
                        class="form-control" :class="{ 'is-invalid': form.errors.password }"
                        autocomplete="new-password" />
                    <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password"
                        class="form-control" :class="{ 'is-invalid': form.errors.password_confirmation }"
                        autocomplete="new-password" />
                    <p v-if="form.errors.password_confirmation" class="form-error">
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>
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
