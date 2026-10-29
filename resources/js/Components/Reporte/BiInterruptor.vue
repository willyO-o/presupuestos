<script setup>
/**
 * Interruptor encendido/apagado con su rótulo, para mostrar u ocultar una
 * capa opcional de un gráfico (el promedio de 3 meses, el promedio de las
 * gestiones). Es un `<button role="switch">`: se maneja con teclado y el
 * lector de pantalla anuncia si está activado.
 *
 * Uso: `<BiInterruptor v-model="verPromedio" texto="Mostrar promedio" />`.
 */
const activo = defineModel({ type: Boolean, default: false });

defineProps({
    texto: { type: String, required: true },
});
</script>

<template>
    <button type="button" role="switch" class="bi-interruptor" :class="{ 'is-activo': activo }" :aria-checked="activo"
        @click="activo = !activo">
        <span class="bi-interruptor-pista" aria-hidden="true"><span class="bi-interruptor-perilla"></span></span>
        <span class="bi-interruptor-texto">{{ texto }}</span>
    </button>
</template>
