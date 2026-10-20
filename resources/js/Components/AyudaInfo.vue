<script>
import { ref } from 'vue';

/**
 * Popup abierto en toda la página (compartido entre instancias): abrir una
 * ayuda cierra la otra. Dos popups encimados taparían justo los cuadros que
 * se quieren comparar.
 */
const abiertoAhora = ref(null);
</script>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref as refLocal, useId, watch } from 'vue';
import { AYUDAS } from '@/Data/Ayudas.js';

/**
 * Ícono "i" al lado del título de un cuadro de información (un gráfico, una
 * tabla, un indicador, una lista) que abre un popup con qué muestra y cómo
 * leerlo, en palabras de todos los días. Los textos viven en `Data/Ayudas.js`.
 * En las tarjetas de indicador del BI va con la prop `ayuda` de `BiKpi`.
 *
 * El popup va con Teleport al <body> y `position: fixed`: las tarjetas
 * recortan su contenido y un popup absoluto adentro saldría cortado. Se
 * abre con clic (no con hover: en el celular no hay hover) y se cierra con
 * Escape, con el botón de cerrar o tocando afuera. Al abrir, el foco entra
 * al popup para que el lector de pantalla lo lea; al cerrar con teclado
 * vuelve al ícono.
 */
const props = defineProps({
    /** Clave de `AYUDAS` (`pantalla.cuadro`). */
    clave: { type: String, required: true },
});

const ayuda = computed(() => AYUDAS[props.clave] ?? null);

const id = useId();
const boton = refLocal(null);
const panel = refLocal(null);
const posicion = refLocal({});
const lado = refLocal('abajo');

const abierto = computed(() => abiertoAhora.value === id);

/** Separación del popup contra el borde de la ventana y contra el ícono, en px. */
const MARGEN = 12;
const SEPARACION = 10;

function ubicar() {
    if (!boton.value || !panel.value) {
        return;
    }

    const icono = boton.value.getBoundingClientRect();
    const ancho = panel.value.offsetWidth;
    const alto = panel.value.offsetHeight;
    const centro = icono.left + icono.width / 2;

    // Pegado al ícono por la izquierda, sin salirse de la ventana.
    const izquierda = Math.min(Math.max(MARGEN, centro - 28), window.innerWidth - ancho - MARGEN);

    // Abajo si entra; si no, arriba (gráficos al pie de la pantalla).
    const cabeAbajo = icono.bottom + SEPARACION + alto <= window.innerHeight - MARGEN;
    lado.value = cabeAbajo ? 'abajo' : 'arriba';

    posicion.value = {
        top: `${cabeAbajo ? icono.bottom + SEPARACION : Math.max(MARGEN, icono.top - SEPARACION - alto)}px`,
        left: `${izquierda}px`,
        '--ayuda-flecha': `${Math.min(Math.max(centro - izquierda, 16), ancho - 16)}px`,
    };
}

let cuadro = null;

function reubicar() {
    cancelAnimationFrame(cuadro);
    cuadro = requestAnimationFrame(ubicar);
}

async function abrir() {
    abiertoAhora.value = id;
    await nextTick();
    ubicar();
    panel.value?.focus({ preventScroll: true });
}

function cerrar({ devolverFoco = false } = {}) {
    if (!abierto.value) {
        return;
    }

    abiertoAhora.value = null;

    if (devolverFoco) {
        boton.value?.focus();
    }
}

function alternar() {
    if (abierto.value) {
        cerrar();
    } else {
        abrir();
    }
}

function alTocarAfuera(evento) {
    if (!panel.value?.contains(evento.target) && !boton.value?.contains(evento.target)) {
        cerrar();
    }
}

function alPresionarTecla(evento) {
    if (evento.key === 'Escape') {
        cerrar({ devolverFoco: true });
    }
}

// Los escuchas viven solo mientras el popup está abierto: con veinte
// gráficos en una pantalla no tiene sentido tener veinte escuchando siempre.
watch(abierto, (ahora) => {
    const metodo = ahora ? 'addEventListener' : 'removeEventListener';

    document[metodo]('pointerdown', alTocarAfuera, true);
    document[metodo]('keydown', alPresionarTecla);
    window[metodo]('scroll', reubicar, true);
    window[metodo]('resize', reubicar);
});

onBeforeUnmount(() => {
    cerrar();
    cancelAnimationFrame(cuadro);
});
</script>

<template>
    <span v-if="ayuda" class="ayuda">
        <button ref="boton" type="button" class="ayuda-boton" :class="{ 'is-open': abierto }"
            :aria-expanded="abierto" :aria-controls="`${id}-panel`" :aria-label="`¿Qué muestra «${ayuda.titulo}»?`"
            :title="abierto ? null : '¿Qué muestra este gráfico?'" @click="alternar">
            <i class="fa-solid fa-info" aria-hidden="true"></i>
        </button>

        <Teleport to="body">
            <Transition name="ayuda">
                <div v-if="abierto" :id="`${id}-panel`" ref="panel" class="ayuda-panel"
                    :class="`is-${lado}`" :style="posicion" role="dialog" :aria-labelledby="`${id}-titulo`"
                    tabindex="-1">
                    <div class="ayuda-cabecera">
                        <span class="ayuda-icono" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
                        <p :id="`${id}-titulo`" class="ayuda-titulo">{{ ayuda.titulo }}</p>
                        <button type="button" class="ayuda-cerrar" aria-label="Cerrar ayuda"
                            @click="cerrar({ devolverFoco: true })">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <p class="ayuda-que">{{ ayuda.que }}</p>

                    <template v-if="ayuda.leer?.length">
                        <p class="ayuda-rotulo">¿Cómo se lee?</p>
                        <ul class="ayuda-lista">
                            <li v-for="pista in ayuda.leer" :key="pista">{{ pista }}</li>
                        </ul>
                    </template>

                    <p v-if="ayuda.ojo" class="ayuda-ojo">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        <span><strong>Ojo:</strong> {{ ayuda.ojo }}</span>
                    </p>
                </div>
            </Transition>
        </Teleport>
    </span>
</template>
