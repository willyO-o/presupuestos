<script setup>
import { computed } from 'vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <AuthSplitLayout title="VERIFICA TU CORREO">
        <Head title="Verificación de correo" />

        <template #status>
            <div
                v-if="verificationLinkSent"
                class="badge-soft-success mb-4 w-full justify-center py-2 text-sm"
            >
                Se envió un nuevo enlace de verificación al correo que
                registraste.
            </div>
        </template>

        <p class="mb-6 text-center text-sm text-muted">
            ¡Gracias por registrarte! Antes de comenzar, ¿podrías verificar tu
            correo electrónico haciendo clic en el enlace que te acabamos de
            enviar? Si no lo recibiste, con gusto te enviamos otro.
        </p>

        <form @submit.prevent="submit">
            <button
                type="submit"
                class="btn btn-primary w-full justify-center rounded-full py-3 text-sm tracking-widest uppercase"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Reenviar correo de verificación
            </button>

            <div class="mt-4 text-center">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs text-muted italic hover:text-primary"
                >
                    Cerrar sesión
                </Link>
            </div>
        </form>
    </AuthSplitLayout>
</template>
