<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="relative flex min-h-screen w-full overflow-hidden bg-white">
        <!-- Blob decorativo de marca -->
        <div
            class="login-brand-blob bg-primary pointer-events-none absolute -top-[10%] -left-[14%] hidden h-[120%] w-[62%] md:block"
        ></div>

        <!-- Panel izquierdo: marca -->
        <div
            class="relative z-10 hidden w-1/2 flex-col items-center justify-center gap-6 px-10 md:flex"
        >
            <img
                src="/img/logo/logo-blanco.png"
                alt="Logo XtraPubli"
                class="login-logo-3d w-96 max-w-full"
            />
        </div>

        <!-- Panel derecho: formulario -->
        <div
            class="relative z-10 flex w-full items-center justify-center px-6 py-12 md:w-1/2"
        >
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center">
                    <span
                        class="avatar avatar-xl mb-4 overflow-hidden rounded-full shadow-lg ring-4 ring-white"
                    >
                        <img
                            src="/img/logo/logo-mini.png"
                            alt="XtraPubli"
                            class="h-full w-full object-cover"
                        />
                    </span>
                    <h1 class="text-2xl font-bold tracking-wide text-heading">
                        BIENVENIDO
                    </h1>
                </div>

                <div
                    v-if="status"
                    class="badge-soft-success mb-4 w-full justify-center py-2 text-sm"
                >
                    {{ status }}
                </div>

                <form @submit.prevent="submit">
                    <div>
                        <div class="login-input-group">
                            <i class="fa-solid fa-user login-input-icon"></i>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="login-input"
                                placeholder="Usuario"
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
                                placeholder="Contraseña"
                                required
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

                    <div class="mt-4 flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="rounded"
                                style="accent-color: var(--c-primary)"
                            />
                            <span class="text-xs text-muted">Recuérdame</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-muted italic hover:text-primary"
                        >
                            Olvidaste tu contraseña
                        </Link>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary mt-6 w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Iniciar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
