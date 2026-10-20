<script setup>
import { computed } from 'vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import { numero } from '@/Utils/FormatoReporte.js';

/**
 * Tarjeta de indicador comparada contra el periodo anterior: el número del
 * periodo, la variación con flecha y color, y contra qué se compara.
 *
 * Distinta de `BiKpi`, que muestra la TENDENCIA (%/mes de la recta): acá la
 * pregunta es "¿cómo estamos frente a antes?". El color dice si el cambio es
 * bueno o malo (`subirEsBueno`) y la flecha y el signo repiten la dirección:
 * nunca depende solo del color.
 */
const props = defineProps({
    etiqueta: { type: String, required: true },
    /** Valor ya formateado ("Bs 4,3 M", "51,6 %"). */
    valor: { type: String, required: true },
    /** Valor del periodo anterior ya formateado, o null si no hubo. */
    anterior: { type: String, default: null },
    /** Variación numérica (null = sin comparación). */
    variacion: { type: Number, default: null },
    /** '%' (variación relativa) o 'pp' (puntos, para porcentajes). */
    unidad: { type: String, default: '%' },
    subirEsBueno: { type: Boolean, default: true },
    icono: { type: String, required: true },
    /** primary | success | warning | danger | info | violet | cyan */
    tono: { type: String, default: 'primary' },
    nota: { type: String, default: '' },
    ayuda: { type: String, default: null },
});

/** Por debajo de 0,5 (% o pp) se lee como "igual": es ruido, no un cambio. */
const direccion = computed(() => {
    if (props.variacion === null) {
        return null;
    }

    if (Math.abs(props.variacion) < 0.5) {
        return 'igual';
    }

    return props.variacion > 0 ? 'sube' : 'baja';
});

const clase = computed(() => {
    if (direccion.value === 'igual') {
        return 'is-neutra';
    }

    return (direccion.value === 'sube') === props.subirEsBueno ? 'is-buena' : 'is-mala';
});

const texto = computed(() => {
    if (direccion.value === 'igual') {
        return 'Igual';
    }

    const signo = props.variacion > 0 ? '+' : '−';

    return `${signo}${numero(Math.abs(props.variacion), 1)} ${props.unidad === 'pp' ? 'pp' : '%'}`;
});
</script>

<template>
    <article class="card kpi-comparado" :class="{ 'con-ayuda': ayuda }">
        <AyudaInfo v-if="ayuda" :clave="ayuda" class="kpi-comparado-ayuda" />
        <div class="kpi-comparado-cabecera">
            <span class="stat-icon" :class="`stat-icon-${tono}`"><i :class="icono" aria-hidden="true"></i></span>
            <p class="kpi-comparado-etiqueta">{{ etiqueta }}</p>
        </div>
        <p class="kpi-comparado-valor">{{ valor }}</p>
        <p class="kpi-comparado-pie">
            <span v-if="direccion" class="kpi-comparado-variacion" :class="clase">
                <i v-if="direccion !== 'igual'" :class="direccion === 'sube' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"
                    aria-hidden="true"></i>
                {{ texto }}
            </span>
            <span v-if="anterior" class="kpi-comparado-anterior">vs. {{ anterior }}</span>
            <span v-else class="kpi-comparado-anterior">sin datos del periodo anterior</span>
        </p>
        <p v-if="nota" class="kpi-comparado-nota">{{ nota }}</p>
    </article>
</template>
