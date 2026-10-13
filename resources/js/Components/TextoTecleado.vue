<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Escribe `texto` letra por letra, rápido, con un cursor que parpadea
 * mientras escribe. Cada vez que cambia el texto vuelve a empezar.
 *
 * Es solo visual: quien lo use debe dejar el texto completo para lectores
 * de pantalla (aquí no se anuncia letra por letra) y marcar este componente
 * `aria-hidden`. Con "reducir movimiento" el texto aparece entero.
 */
const props = defineProps({
    texto: {
        type: String,
        default: '',
    },
    /** Milisegundos por letra. */
    intervalo: {
        type: Number,
        default: 24,
    },
});

const emit = defineEmits(['terminado']);

const visible = ref('');
const escribiendo = ref(false);
let temporizador = null;

function teclear(texto) {
    window.clearInterval(temporizador);

    const reducir = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    if (reducir || !texto) {
        visible.value = texto;
        escribiendo.value = false;
        emit('terminado');

        return;
    }

    let letras = 0;
    visible.value = '';
    escribiendo.value = true;

    temporizador = window.setInterval(() => {
        letras += 1;
        visible.value = texto.slice(0, letras);

        if (letras >= texto.length) {
            window.clearInterval(temporizador);
            escribiendo.value = false;
            emit('terminado');
        }
    }, props.intervalo);
}

watch(() => props.texto, teclear, { immediate: true });

onBeforeUnmount(() => window.clearInterval(temporizador));
</script>

<template>
    <span class="texto-tecleado">{{ visible }}<span v-if="escribiendo" class="texto-tecleado-cursor"></span></span>
</template>
