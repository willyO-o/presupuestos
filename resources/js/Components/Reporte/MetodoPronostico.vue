<script setup>
import { computed } from 'vue';
import { numero } from '@/Utils/FormatoReporte.js';

/**
 * Ficha "Cómo se calcula" de un pronóstico del BI: método, meses usados,
 * ajuste (R²), qué es la banda y cuánto se equivocó la recta al validarla.
 *
 * Plegada por defecto: el gráfico es lo principal, pero quien pregunta
 * "¿de dónde sale esto?" (un gerente, un docente) tiene la respuesta a un
 * clic, con los números de ESTE gráfico. El texto lo redacta el servidor
 * (RedaccionPronostico), el mismo que imprime el anexo del informe PDF.
 */
const props = defineProps({
    /** `metodo` del servicio (Pronostico::analizar + PeriodoReporte). */
    metodo: { type: Object, default: null },
});

const AJUSTE = { fuerte: 'Ajuste fuerte', moderado: 'Ajuste moderado', debil: 'Ajuste débil' };

const chips = computed(() => {
    const m = props.metodo;

    if (!m?.suficiente) {
        return [{ texto: 'Sin proyección: faltan meses', tono: 'neutro' }];
    }

    return [
        { texto: `${m.n} meses`, tono: 'neutro' },
        m.r2 !== null ? { texto: `R² ${numero(m.r2, 2)} · ${AJUSTE[m.ajuste]}`, tono: m.ajuste } : null,
        m.validacion?.recta != null
            ? { texto: `Error de validación ${numero(m.validacion.recta, 1)} %`, tono: m.validacion.mejor === 'recta' ? 'fuerte' : 'moderado' }
            : null,
    ].filter(Boolean);
});
</script>

<template>
    <details v-if="metodo" class="bi-metodo">
        <summary class="bi-metodo-resumen">
            <span class="bi-metodo-titulo"><i class="fa-solid fa-calculator"></i> Cómo se calcula</span>
            <span class="bi-metodo-chips">
                <span v-for="chip in chips" :key="chip.texto" class="bi-metodo-chip" :class="`bi-metodo-chip-${chip.tono}`">
                    {{ chip.texto }}
                </span>
            </span>
        </summary>
        <dl class="bi-metodo-lista">
            <template v-for="renglon in metodo.explicacion ?? []" :key="renglon.rotulo">
                <dt>{{ renglon.rotulo }}</dt>
                <dd>{{ renglon.texto }}</dd>
            </template>
        </dl>
    </details>
</template>
