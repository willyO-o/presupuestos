<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Logo del cliente, o sus iniciales cuando no tiene (que al principio va a
 * ser la mayoría: el logo es opcional).
 *
 * El logo se guarda convertido a JPG (ConvierteImagenAJpgService), que
 * aplana la transparencia sobre blanco: por eso va siempre sobre una
 * plaquita blanca con borde, que se ve bien en claro y en oscuro.
 *
 * Las iniciales llevan un color fijo POR CLIENTE (sale del nombre, no del
 * puesto en una lista): el mismo cliente se reconoce con el mismo color en
 * cualquier pantalla y aunque cambie de puesto en el ranking.
 */
const props = defineProps({
    nombre: { type: String, required: true },
    logoUrl: { type: String, default: null },
    /** sm (listados) | md (ranking del BI) */
    tamano: { type: String, default: 'md' },
});

// Si la imagen no carga (archivo borrado a mano), caen las iniciales.
const fallo = ref(false);
watch(() => props.logoUrl, () => { fallo.value = false; });

/** Palabras que no aportan a las iniciales de una razón social. */
const IGNORAR = new Set(['s.a.', 's.r.l.', 'sa', 'srl', 'ltda.', 'ltda', 'de', 'del', 'la', 'las', 'los', 'y', 'e']);

const iniciales = computed(() => {
    const palabras = props.nombre
        .split(/\s+/)
        .filter((p) => p && !IGNORAR.has(p.toLowerCase()));

    return (palabras.slice(0, 2).map((p) => p[0]).join('') || props.nombre.slice(0, 2)).toUpperCase();
});

/** Uno de los 6 tonos de `.cliente-avatar-tono-*`, estable para cada nombre. */
const tono = computed(() => {
    let hash = 0;
    for (const letra of props.nombre) hash = (hash * 31 + letra.charCodeAt(0)) >>> 0;
    return hash % 6;
});
</script>

<template>
    <span class="cliente-avatar" :class="[`cliente-avatar-${tamano}`, logoUrl && !fallo ? 'cliente-avatar-logo' : `cliente-avatar-tono-${tono}`]"
        aria-hidden="true">
        <img v-if="logoUrl && !fallo" :src="logoUrl" alt="" loading="lazy" @error="fallo = true" />
        <template v-else>{{ iniciales }}</template>
    </span>
</template>
