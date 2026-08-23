<script setup>
import InputError from '@/Components/InputError.vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthSplitLayout title="RESTABLECER CONTRASEÑA">
        <Head title="Restablecer contraseña" />

        <form @submit.prevent="submit">
            <div>
                <div class="login-input-group">
                    <i class="fa-solid fa-envelope login-input-icon"></i>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="login-input"
                        placeholder="Correo electrónico"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </div>

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5">
                <div class="login-input-group">
                    <i class="fa-solid fa-lock login-input-icon"></i>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="login-input"
                        placeholder="Nueva contraseña"
                        required
                        autocomplete="new-password"
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

            <div class="mt-5">
                <div class="login-input-group">
                    <i class="fa-solid fa-lock login-input-icon"></i>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        class="login-input"
                        placeholder="Confirmar contraseña"
                        required
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="login-input-eye"
                        :aria-label="
                            showPasswordConfirmation
                                ? 'Ocultar contraseña'
                                : 'Mostrar contraseña'
                        "
                        @click="
                            showPasswordConfirmation = !showPasswordConfirmation
                        "
                    >
                        <i
                            :class="
                                showPasswordConfirmation
                                    ? 'fa-solid fa-eye-slash'
                                    : 'fa-solid fa-eye'
                            "
                        ></i>
                    </button>
                </div>

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <button
                type="submit"
                class="btn btn-primary mt-6 w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Restablecer contraseña
            </button>
        </form>
    </AuthSplitLayout>
</template>
