<script setup>
import InputError from '@/Components/InputError.vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthSplitLayout title="RECUPERAR CONTRASEÑA">
        <Head title="Recuperar contraseña" />

        <template #status>
            <div
                v-if="status"
                class="badge-soft-success mb-4 w-full justify-center py-2 text-sm"
            >
                {{ status }}
            </div>
        </template>

        <p class="mb-5 text-center text-sm text-muted">
            ¿Olvidaste tu contraseña? No hay problema. Indícanos tu correo y
            te enviaremos un enlace para elegir una nueva.
        </p>

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

            <button
                type="submit"
                class="btn btn-primary mt-6 w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Enviar enlace de restablecimiento
            </button>
        </form>
    </AuthSplitLayout>
</template>
