<script setup>
import InputError from '@/Components/InputError.vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
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
    <AuthSplitLayout title="BIENVENIDO">
        <Head title="Iniciar sesión" />

        <template #status>
            <div
                v-if="status"
                class="badge-soft-success mb-4 w-full justify-center py-2 text-sm"
            >
                {{ status }}
            </div>
        </template>

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
    </AuthSplitLayout>
</template>
