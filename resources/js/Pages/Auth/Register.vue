<script setup>
import InputError from '@/Components/InputError.vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthSplitLayout title="CREAR CUENTA">
        <Head title="Registro" />

        <form @submit.prevent="submit">
            <div>
                <div class="login-input-group">
                    <i class="fa-solid fa-user login-input-icon"></i>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="login-input"
                        placeholder="Nombre"
                        required
                        autofocus
                        autocomplete="name"
                    />
                </div>

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-5">
                <div class="login-input-group">
                    <i class="fa-solid fa-envelope login-input-icon"></i>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="login-input"
                        placeholder="Correo electrónico"
                        required
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
                        placeholder="Contraseña"
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

            <div class="mt-4 text-center">
                <Link
                    :href="route('login')"
                    class="text-xs text-muted italic hover:text-primary"
                >
                    ¿Ya tienes una cuenta? Inicia sesión
                </Link>
            </div>

            <button
                type="submit"
                class="btn btn-primary mt-4 w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Registrarse
            </button>
        </form>
    </AuthSplitLayout>
</template>
