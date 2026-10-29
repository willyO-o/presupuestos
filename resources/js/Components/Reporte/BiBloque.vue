<script setup>
import { useId } from 'vue';

/**
 * Bloque numerado de una pestaña del BI: un número, la pregunta o tema que
 * responde y una bajada corta, con las tarjetas debajo. Ordena una pantalla
 * larga en pocos temas que se leen de arriba abajo (Demanda y ventas: 1.
 * pedidos futuros, 2. qué se vende y quién compra, 3. comportamiento
 * histórico).
 *
 * El slot `accion` va a la derecha del título (un interruptor que afecta a
 * todo el bloque); el slot por defecto, las tarjetas.
 *
 * Con `tarjeta`, el bloque ES la tarjeta: el número y el título van adentro
 * (el Simulador, donde cada bloque es un paso con su formulario). Ahí el
 * slot `ayuda` pone el ícono "i" junto al título y `etiqueta`, una insignia
 * corta al lado ("Sin cambios aplicados").
 */
defineProps({
    numero: { type: Number, required: true },
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: '' },
    tarjeta: { type: Boolean, default: false },
});

const idTitulo = useId();
</script>

<template>
    <section class="bi-bloque" :class="{ 'card bi-bloque-tarjeta': tarjeta }" :aria-labelledby="idTitulo">
        <header class="bi-bloque-cabecera">
            <span class="bi-bloque-numero" aria-hidden="true">{{ numero }}</span>
            <div class="bi-bloque-texto">
                <div class="bi-bloque-titulo-linea">
                    <h2 :id="idTitulo" class="bi-bloque-titulo">{{ titulo }} <slot name="ayuda" /></h2>
                    <slot name="etiqueta" />
                </div>
                <p v-if="subtitulo" class="bi-bloque-subtitulo">{{ subtitulo }}</p>
            </div>
            <div v-if="$slots.accion" class="bi-bloque-accion">
                <slot name="accion" />
            </div>
        </header>
        <div class="bi-bloque-cuerpo">
            <slot />
        </div>
    </section>
</template>
