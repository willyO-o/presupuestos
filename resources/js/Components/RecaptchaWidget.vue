<script>
/**
 * El script de Google se carga una sola vez por pestaña, aunque el login se
 * monte varias veces (navegación de Inertia: login → panel → logout → login).
 */
let cargaRecaptcha = null;

function cargarRecaptcha() {
    if (window.grecaptcha?.render) {
        return Promise.resolve(window.grecaptcha);
    }

    if (!cargaRecaptcha) {
        cargaRecaptcha = new Promise((resolve, reject) => {
            window.onRecaptchaCargado = () => resolve(window.grecaptcha);

            const script = document.createElement('script');
            script.src =
                'https://www.google.com/recaptcha/api.js?onload=onRecaptchaCargado&render=explicit&hl=es';
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                cargaRecaptcha = null;
                script.remove();
                reject(new Error('No se pudo cargar reCAPTCHA'));
            };

            document.head.appendChild(script);
        });
    }

    return cargaRecaptcha;
}
</script>

<script setup>
import InputError from '@/Components/InputError.vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    siteKey: {
        type: String,
        required: true,
    },
});

/** Token de un solo uso que devuelve Google al marcar la casilla. */
const token = defineModel({ type: String, default: '' });

const contenedor = ref(null);
const errorCarga = ref('');
let widgetId = null;

onMounted(async () => {
    try {
        const grecaptcha = await cargarRecaptcha();

        if (!contenedor.value) {
            return;
        }

        widgetId = grecaptcha.render(contenedor.value, {
            sitekey: props.siteKey,
            callback: (respuesta) => (token.value = respuesta),
            'expired-callback': () => (token.value = ''),
            'error-callback': () => (token.value = ''),
        });
    } catch {
        errorCarga.value =
            'No se pudo cargar el captcha. Revisa tu conexión (o un bloqueador de anuncios) y recarga la página.';
    }
});

onBeforeUnmount(() => {
    widgetId = null;
});

/** Google invalida el token tras verificarlo: hay que volver a marcar la casilla. */
const reiniciar = () => {
    token.value = '';

    if (widgetId !== null) {
        window.grecaptcha?.reset(widgetId);
    }
};

defineExpose({ reiniciar });
</script>

<template>
    <div class="flex flex-col items-center">
        <div ref="contenedor"></div>
        <InputError class="mt-2 text-center" :message="errorCarga" />
    </div>
</template>
