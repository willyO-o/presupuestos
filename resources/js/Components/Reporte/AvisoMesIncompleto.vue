<script setup>
import { computed } from 'vue';

/**
 * Aviso de que el último mes del periodo no terminó ("Octubre está
 * incompleto: datos hasta el día 28 de 31."), para el slot `aviso` de
 * BiEncabezado. Lo que eso significa en cada pestaña va en el slot (en
 * Demanda: que no entra en la estimación; en Presupuestos: que lo cotizado
 * no es dinero cobrado).
 *
 * Recibe `periodo.mes_incompleto` del servidor ({ mes: 'octubre 2026',
 * dias, de }); sin él no muestra nada.
 */
const props = defineProps({
    mes: { type: Object, default: null },
});

const nombre = computed(() => {
    const palabra = props.mes?.mes?.split(' ')[0] ?? '';

    return palabra.charAt(0).toUpperCase() + palabra.slice(1);
});
</script>

<template>
    <p v-if="mes" class="bi-mes-incompleto">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>
            <strong>{{ nombre }} está incompleto:</strong>
            datos hasta el día {{ mes.dias }} de {{ mes.de }}.
            <slot />
        </span>
    </p>
</template>
