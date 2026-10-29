<script setup>
import { computed } from 'vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import { variacion as formatoVariacion } from '@/Utils/FormatoReporte.js';

/**
 * Tarjeta de indicador del BI: un número, qué es, y —si se pasa— hacia dónde
 * va (pendiente de la tendencia en % por mes).
 *
 * La flecha dice la DIRECCIÓN y el color dice si eso es bueno o malo: que el
 * costo suba es malo y que las ventas suban es bueno, así que la polaridad la
 * declara quien usa la tarjeta (`subirEsBueno`). La dirección nunca depende
 * solo del color: la flecha y el signo la repiten.
 */
const props = defineProps({
    etiqueta: { type: String, required: true },
    valor: { type: String, required: true },
    /** Clase Font Awesome del icono. */
    icono: { type: String, default: 'fa-solid fa-chart-simple' },
    /** primary | success | warning | danger | info (clases `.stat-icon-*`). */
    tono: { type: String, default: 'primary' },
    /** Variación en % (o null para no mostrar tendencia). */
    variacion: { type: Number, default: null },
    /** Contra qué es la variación: por defecto, la pendiente mensual. */
    sufijo: { type: String, default: 'por mes' },
    subirEsBueno: { type: Boolean, default: true },
    /** Texto chico bajo el número (contexto o aclaración). */
    nota: { type: String, default: '' },
    /** Clave de `Data/Ayudas.js`: pone el ícono "i" al lado de la etiqueta. */
    ayuda: { type: String, default: null },
});

/** Por debajo de ±0,5 %/mes se considera estable: es ruido, no tendencia. */
const direccion = computed(() => {
    if (props.variacion === null) {
        return null;
    }

    if (Math.abs(props.variacion) < 0.5) {
        return 'estable';
    }

    return props.variacion > 0 ? 'sube' : 'baja';
});

const claseTendencia = computed(() => {
    if (direccion.value === 'estable') {
        return 'bi-tendencia-neutra';
    }

    const esBueno = (direccion.value === 'sube') === props.subirEsBueno;

    return esBueno ? 'bi-tendencia-buena' : 'bi-tendencia-mala';
});

const iconoTendencia = computed(() => ({
    sube: 'fa-solid fa-arrow-trend-up',
    baja: 'fa-solid fa-arrow-trend-down',
    estable: 'fa-solid fa-arrow-right-long',
})[direccion.value]);
</script>

<template>
    <div class="card stat-card bi-kpi" :class="{ 'con-ayuda': ayuda }">
        <!-- En la esquina, siempre en el mismo lugar: al lado de una etiqueta
             de dos renglones el ícono quedaría suelto en un renglón propio. -->
        <AyudaInfo v-if="ayuda" :clave="ayuda" class="bi-kpi-ayuda" />
        <div class="stat-icon" :class="`stat-icon-${tono}`"><i :class="icono"></i></div>
        <div class="bi-kpi-cuerpo">
            <p class="stat-label mb-0">{{ etiqueta }}</p>
            <p class="stat-value mb-0">{{ valor }}</p>
            <p v-if="direccion" class="bi-tendencia" :class="claseTendencia">
                <i :class="iconoTendencia"></i>
                {{ direccion === 'estable' ? 'Estable' : `${formatoVariacion(variacion)} ${sufijo}` }}
            </p>
            <p v-if="nota" class="bi-kpi-nota">{{ nota }}</p>
        </div>
    </div>
</template>
