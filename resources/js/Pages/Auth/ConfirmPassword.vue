<script setup>
import InputError from '@/Components/InputError.vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthSplitLayout title="CONFIRMAR CONTRASEÑA">
        <Head title="Confirmar contraseña" />

        <p class="mb-5 text-center text-sm text-muted">
            Esta es un área segura de la aplicación. Confirma tu contraseña
            antes de continuar.
        </p>

        <form @submit.prevent="submit">
            <div>
                <div class="login-input-group">
                    <i class="fa-solid fa-lock login-input-icon"></i>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="login-input"
                        placeholder="Contraseña"
                        required
                        autofocus
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="login-input-eye"
                        :aria-label="
                            showPassword
                                ? 'Ocultar contraseña'
                                : 'Mostrar contraseña'
                        "
                        @click="showPassword = !showPassword"
                    >
                        <i
                            :class="
                                showPassword
                                    ? 'fa-solid fa-eye-slash'
                                    : 'fa-solid fa-eye'
                            "
                        ></i>
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <button
                type="submit"
                class="btn btn-primary mt-6 w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Confirmar
            </button>
        </form>
    </AuthSplitLayout>
</template>
