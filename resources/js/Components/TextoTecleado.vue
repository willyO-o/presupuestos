<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Escribe `texto` letra por letra, rápido, con un cursor que parpadea
 * mientras escribe. Cada vez que cambia el texto vuelve a empezar.
 *
 * Es solo visual: quien lo use debe dejar el texto completo para lectores
 * de pantalla (aquí no se anuncia letra por letra) y marcar este componente
 * `aria-hidden`. Con "reducir movimiento" el texto aparece entero.
 *
 * Con `ola`, cada letra va en su propio span y una onda de opacidad recorre
 * el texto (como los puntos de carga, que se encienden en secuencia): el
 * desfase de cada letra sale de su posición (`--i`, app.css). El template
 * va en una sola línea a propósito: un salto entre spans agregaría espacios
 * visibles entre letras.
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
    /** Onda de opacidad que recorre las letras, para textos de carga. */
    ola: {
        type: Boolean,
        default: false,
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
    <span class="texto-tecleado" :class="{ 'is-ola': ola }"><template v-if="ola"><span v-for="(letra, indice) in visible" :key="indice" class="texto-tecleado-letra" :style="{ '--i': indice }">{{ letra }}</span></template><template v-else>{{ visible }}</template><span v-if="escribiendo" class="texto-tecleado-cursor"></span></span>
</template>
