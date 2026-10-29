<script setup>
import { computed } from 'vue';
import { numero } from '@/Utils/FormatoReporte.js';

/**
 * Qué tan confiable es una proyección del BI, en palabras y junto al
 * gráfico (no escondido en "Cómo se calcula"): el ajuste de la recta (R²) y
 * cuánto se equivocó al probarla con meses ya ocurridos. Si otro método se
 * equivocó menos, lo dice.
 *
 * Recibe el `metodo` que manda el servidor (Pronostico::analizar). Sin
 * proyección (pocos meses) no muestra nada. El color sale del ajuste:
 * débil = naranja, moderado = celeste, fuerte = verde.
 */
const props = defineProps({
    metodo: { type: Object, default: null },
});

const ALTERNATIVAS = {
    media_movil: 'El promedio de los últimos 3 meses',
    holt: 'El suavizado exponencial (Holt)',
};

const AJUSTES = {
    debil: { nombre: 'débil', tono: 'warning', icono: 'fa-solid fa-triangle-exclamation' },
    moderado: { nombre: 'moderado', tono: 'info', icono: 'fa-solid fa-circle-info' },
    fuerte: { nombre: 'fuerte', tono: 'success', icono: 'fa-solid fa-circle-check' },
};

const lectura = computed(() => {
    const m = props.metodo;
    const ajuste = AJUSTES[m?.ajuste];

    if (!m?.suficiente || !ajuste) {
        return null;
    }

    const prueba = m.validacion;
    const partes = [];

    if (prueba?.recta !== null && prueba?.recta !== undefined) {
        partes.push(`Al probarla con los últimos ${prueba.meses} meses reales, se equivocó en promedio un ${numero(prueba.recta, 1)} %.`);
    }

    if (prueba?.mejor && prueba.mejor !== 'recta' && ALTERNATIVAS[prueba.mejor] && prueba[prueba.mejor] !== null) {
        partes.push(`${ALTERNATIVAS[prueba.mejor]} se equivocó menos (${numero(prueba[prueba.mejor], 1)} %).`);
    }

    partes.push(m.ajuste === 'fuerte'
        ? 'Buen desempeño en la prueba, aunque no garantiza el resultado.'
        : 'Úsela como referencia y contrástela con los datos actuales.');

    return {
        titulo: `Proyección con ajuste ${ajuste.nombre}`,
        texto: partes.join(' '),
        tono: ajuste.tono,
        icono: ajuste.icono,
    };
});
</script>

<template>
    <div v-if="lectura" class="bi-confiabilidad" :class="`bi-confiabilidad-${lectura.tono}`">
        <i :class="lectura.icono" aria-hidden="true"></i>
        <p>
            <strong>{{ lectura.titulo }}</strong>
            <span>{{ lectura.texto }}</span>
        </p>
    </div>
</template>
