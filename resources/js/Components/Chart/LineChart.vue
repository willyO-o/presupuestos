<script setup>
import { computed } from 'vue';

/**
 * Gráfico de líneas inline-SVG, sin librería. Hereda el color de marca vía
 * `currentColor` (se pone con una clase de texto en el contenedor). Cada
 * serie: { nombre, color?, valores: number[] }. `labels` alinea el eje X.
 */
const props = defineProps({
    series: { type: Array, required: true },
    labels: { type: Array, default: () => [] },
    height: { type: Number, default: 180 },
    formatY: { type: Function, default: (v) => v },
});

const W = 640;
const H = computed(() => props.height);
const PAD = { t: 12, r: 12, b: 24, l: 44 };

const allValues = computed(() => props.series.flatMap((s) => s.valores).filter((v) => Number.isFinite(v)));
const maxY = computed(() => Math.max(1, ...allValues.value));
const minY = computed(() => Math.min(0, ...allValues.value));

const count = computed(() => Math.max(...props.series.map((s) => s.valores.length), 1));

function x(i) {
    if (count.value <= 1) return PAD.l;
    return PAD.l + (i / (count.value - 1)) * (W - PAD.l - PAD.r);
}

function y(v) {
    const range = maxY.value - minY.value || 1;
    return H.value - PAD.b - ((v - minY.value) / range) * (H.value - PAD.t - PAD.b);
}

const paths = computed(() => props.series.map((s) => ({
    color: s.color ?? 'var(--c-primary)',
    nombre: s.nombre,
    d: s.valores.map((v, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' '),
    puntos: s.valores.map((v, i) => ({ cx: x(i), cy: y(v) })),
})));

const gridY = computed(() => {
    const steps = 4;
    return Array.from({ length: steps + 1 }, (_, i) => {
        const v = minY.value + (i / steps) * (maxY.value - minY.value);
        return { v, y: y(v) };
    });
});
</script>

<template>
    <div>
        <svg :viewBox="`0 0 ${W} ${H}`" class="w-100" :style="{ height: `${H}px` }" preserveAspectRatio="none"
            role="img">
            <g>
                <line v-for="g in gridY" :key="g.y" :x1="PAD.l" :x2="W - PAD.r" :y1="g.y" :y2="g.y"
                    stroke="var(--border-subtle)" stroke-width="1" />
                <text v-for="g in gridY" :key="`t-${g.y}`" :x="PAD.l - 6" :y="g.y + 3" text-anchor="end"
                    font-size="10" fill="var(--text-muted)">{{ formatY(Math.round(g.v)) }}</text>
            </g>
            <g v-for="p in paths" :key="p.nombre">
                <path :d="p.d" fill="none" :stroke="p.color" stroke-width="2" stroke-linejoin="round" />
                <circle v-for="(pt, i) in p.puntos" :key="i" :cx="pt.cx" :cy="pt.cy" r="2.5" :fill="p.color" />
            </g>
            <text v-for="(lbl, i) in labels" :key="`x-${i}`" :x="x(i)" :y="H - 6" text-anchor="middle" font-size="10"
                fill="var(--text-muted)">{{ lbl }}</text>
        </svg>
        <div v-if="series.length > 1" class="d-flex flex-wrap gap-3 mt-2">
            <span v-for="s in series" :key="s.nombre" class="d-flex align-items-center gap-1 fs-xs text-muted">
                <span class="reporte-legend-dot" :style="{ backgroundColor: s.color ?? 'var(--c-primary)' }"></span>
                {{ s.nombre }}
            </span>
        </div>
    </div>
</template>
