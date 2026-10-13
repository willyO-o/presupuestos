<script setup>
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';

/**
 * Mascota del asistente de IA, dibujada en SVG por partes (cabeza, visor,
 * ojos, pluma, auricular, burbuja, mano) para animar cada una por separado
 * con CSS (app.css, "Asistente: mascota"). Pesa unos KB y no pide archivos:
 * la ilustración original (PNG de 1,5 MB) se redibujó en vectores.
 *
 * - `animo`: qué está haciendo el asistente. 'reposo' parpadea y mece la
 *   pluma; 'pensando' escribe en la burbuja y mira alrededor; 'contento'
 *   celebra una vez; 'apagado' (IA no disponible) atenúa los ojos; 'quieto'
 *   no se mueve (para las muchas cabecitas de la conversación).
 * - `variante`: 'completo' (busto con burbuja y mano) o 'cabeza' (solo la
 *   cabeza, más grande: se lee bien a 20-36 px).
 * - `pulso`: cualquier valor; cada vez que cambia, la cabeza asiente una vez
 *   (el panel le pasa el texto de la etapa de espera).
 *
 * Los ids de los degradados son únicos por instancia (useId): un id repetido
 * apuntaría al degradado de otra copia, y si esa copia está oculta con
 * display:none el navegador no lo pinta.
 */
const props = defineProps({
    animo: {
        type: String,
        default: 'reposo',
        validator: (valor) => ['reposo', 'pensando', 'contento', 'apagado', 'quieto'].includes(valor),
    },
    variante: {
        type: String,
        default: 'completo',
        validator: (valor) => ['completo', 'cabeza'].includes(valor),
    },
    pulso: {
        type: [String, Number, null],
        default: null,
    },
});

const uid = useId();
const ref_ = (nombre) => `robot-${nombre}-${uid}`;
const url = (nombre) => `url(#${ref_(nombre)})`;

const completo = computed(() => props.variante === 'completo');

/* La cabeza sola se agranda y se centra en el disco. */
const transformCabeza = computed(() => (completo.value ? undefined : 'translate(60 66) scale(1.16) translate(-60 -58)'));

const asintiendo = ref(false);
let temporizador = null;

watch(() => props.pulso, (nuevo, anterior) => {
    if (anterior === undefined || nuevo === anterior) {
        return;
    }

    asintiendo.value = false;
    window.clearTimeout(temporizador);
    window.requestAnimationFrame(() => {
        asintiendo.value = true;
        temporizador = window.setTimeout(() => {
            asintiendo.value = false;
        }, 700);
    });
});

onBeforeUnmount(() => window.clearTimeout(temporizador));
</script>

<template>
    <svg class="robot-ia" :class="[`is-${animo}`, `robot-ia-${variante}`, { 'is-asintiendo': asintiendo }]" viewBox="0 0 120 120"
        xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient :id="ref_('fondo')" cx="50%" cy="38%" r="62%">
                <stop offset="0" stop-color="#1c55b8" />
                <stop offset="0.55" stop-color="#0d2a6b" />
                <stop offset="1" stop-color="#071637" />
            </radialGradient>
            <linearGradient :id="ref_('aro')" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#7fe0ff" />
                <stop offset="1" stop-color="#1f74e0" />
            </linearGradient>
            <linearGradient :id="ref_('blanco')" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#ffffff" />
                <stop offset="1" stop-color="#d3e0f2" />
            </linearGradient>
            <linearGradient :id="ref_('azul')" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#5cb8ff" />
                <stop offset="1" stop-color="#1562d6" />
            </linearGradient>
            <linearGradient :id="ref_('visor')" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#14306f" />
                <stop offset="1" stop-color="#050f2b" />
            </linearGradient>
            <clipPath :id="ref_('disco')">
                <circle cx="60" cy="60" r="51.5" />
            </clipPath>
        </defs>

        <!-- Disco con aro luminoso -->
        <circle class="robot-halo" cx="60" cy="60" r="56.5" fill="none" stroke="#3fb6ff" stroke-width="5" opacity="0.28" />
        <circle cx="60" cy="60" r="53" :fill="url('fondo')" :stroke="url('aro')" stroke-width="3.5" />

        <g :clip-path="url('disco')">
            <!-- Cuerpo -->
            <g v-if="completo" class="robot-cuerpo">
                <rect x="52" y="81" width="16" height="9" rx="3" fill="#0b2158" />
                <path d="M27 124 C27 98 39 88 60 88 C81 88 93 98 93 124 Z" :fill="url('blanco')" />
                <ellipse cx="32.5" cy="99" rx="8.5" ry="8.5" :fill="url('azul')" />
                <ellipse cx="87.5" cy="99" rx="8.5" ry="8.5" :fill="url('azul')" />
                <path d="M51 97 h5.5 l10.5 14 h-5.5 Z M69 97 h-5.5 l-10.5 14 h5.5 Z" :fill="url('azul')" />
            </g>

            <!-- Cabeza -->
            <g class="robot-cabeza" :transform="transformCabeza">
                <g class="robot-cabeza-movil">
                    <!-- Pluma (antena con forma del trazo de la marca) -->
                    <g class="robot-pluma">
                        <path d="M60 34 C57 23 65 13 80 10 C76.5 17 73 25 63.5 32.5 Z" :fill="url('azul')" />
                        <path d="M63 33.5 C67 25.5 76 21 87 22.5 C81.5 27.5 75 31.5 65.5 34.5 Z" fill="#7fd3f4" />
                    </g>

                    <!-- Vincha del auricular -->
                    <path d="M25.5 60 C25.5 23 94.5 23 94.5 60" fill="none" stroke="#2a86f0" stroke-width="3" stroke-linecap="round" />

                    <ellipse cx="60" cy="58" rx="33.5" ry="27.5" :fill="url('blanco')" />
                    <ellipse cx="51" cy="38.5" rx="15" ry="4.5" fill="#ffffff" opacity="0.8" />

                    <!-- Visor -->
                    <rect x="35" y="44.5" width="50" height="30.5" rx="13.5" :fill="url('visor')" />
                    <path d="M41 50 C45 47.5 52 47 58 47.5" fill="none" stroke="#ffffff" stroke-width="1.6" stroke-linecap="round" opacity="0.18" />

                    <!-- Ojos -->
                    <g class="robot-ojos">
                        <path d="M43.5 64.5 q5.75 -7.5 11.5 0 M65 64.5 q5.75 -7.5 11.5 0" fill="none" stroke="#3fc4ff" stroke-width="6.5"
                            stroke-linecap="round" opacity="0.3" />
                        <path d="M43.5 64.5 q5.75 -7.5 11.5 0 M65 64.5 q5.75 -7.5 11.5 0" fill="none" stroke="#9ff0ff" stroke-width="3.2"
                            stroke-linecap="round" />
                    </g>

                    <!-- Auricular izquierdo -->
                    <circle cx="26.5" cy="60" r="9.5" :fill="url('blanco')" />
                    <circle class="robot-auricular" cx="26.5" cy="60" r="5.6" fill="#0b2158" stroke="#3aa6ff" stroke-width="2.2" />

                    <!-- Micrófono -->
                    <g v-if="completo">
                        <path d="M88 67 C90 76 84 81 75.5 81" fill="none" stroke="#0b2158" stroke-width="2.2" stroke-linecap="round" />
                        <rect x="68.5" y="78.5" width="9" height="5" rx="2.5" fill="#0b2158" />
                    </g>
                </g>
            </g>

            <!-- Mano con el pulgar arriba -->
            <g v-if="completo" class="robot-mano">
                <path d="M88 104 L91 96" fill="none" stroke="#d3e0f2" stroke-width="9" stroke-linecap="round" />
                <!-- Pulgar: sale del borde izquierdo del puño, inclinado (no del centro). -->
                <rect x="82" y="66.5" width="6.5" height="16" rx="3.25" :fill="url('blanco')" stroke="#b9cbe6" stroke-width="0.8"
                    transform="rotate(-14 85.25 82)" />
                <!-- Dorso del puño -->
                <rect x="82" y="78" width="11" height="15.5" rx="4" :fill="url('blanco')" />
                <!-- Cuatro dedos doblados, uno sobre otro, con los nudillos hacia afuera -->
                <g :fill="url('blanco')" stroke="#b9cbe6" stroke-width="0.8">
                    <rect x="86" y="77.5" width="14" height="4.6" rx="2.3" />
                    <rect x="86" y="81.6" width="14.5" height="4.6" rx="2.3" />
                    <rect x="86" y="85.7" width="14" height="4.6" rx="2.3" />
                    <rect x="86" y="89.8" width="12.5" height="4.4" rx="2.2" />
                </g>
                <rect x="82.5" y="93" width="14.5" height="4.5" rx="2.25" :fill="url('azul')" />
            </g>
        </g>

        <!-- Burbuja de chat: escribe mientras el asistente piensa -->
        <g v-if="completo" class="robot-burbuja">
            <path d="M88 20 h18 a8 8 0 0 1 8 8 v6 a8 8 0 0 1 -8 8 h-12 l-8 8 l1 -8 a8 8 0 0 1 -7 -8 v-6 a8 8 0 0 1 8 -8 Z"
                :fill="url('azul')" stroke="#9fe2ff" stroke-width="1" />
            <circle class="robot-punto" cx="90" cy="31" r="2.6" fill="#ffffff" />
            <circle class="robot-punto" cx="97" cy="31" r="2.6" fill="#ffffff" />
            <circle class="robot-punto" cx="104" cy="31" r="2.6" fill="#ffffff" />
        </g>
    </svg>
</template>
