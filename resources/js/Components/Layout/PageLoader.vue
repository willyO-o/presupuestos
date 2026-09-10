<script setup>
/**
 * Loader de cambio de página: velo con tres puntos saltando y "Cargando...".
 *
 * Se monta UNA sola vez en la raíz (`app.js`), no en un layout: así también
 * cubre las pantallas de acceso y el portal del cliente, y sobrevive al cambio
 * de página sin remontarse.
 *
 * Dos decisiones que evitan que estorbe más de lo que ayuda:
 *
 * 1. **Espera antes de aparecer** (`RETRASO_MS`). Una navegación local tarda
 *    decenas de milisegundos; sin la espera, cada clic daría un parpadeo de
 *    velo, que se siente peor que no tener loader. Es el mismo criterio que
 *    usa la barra de progreso de Inertia.
 * 2. **Ignora las recargas parciales** (`visit.only`). Son las de
 *    `useServerTable` —buscar y filtrar en un listado—, que YA tienen su
 *    propio loader dentro de la tabla (`.table-loading-*`). Taparlas con un
 *    velo de pantalla completa escondería justo lo que el usuario mira.
 *
 * Convive con la barra de progreso de Inertia a propósito: la barra avisa "algo
 * pasa" desde el primer instante y el velo aparece solo si la cosa se alarga.
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/** Milisegundos antes de mostrar el velo. Ver el punto 1 del docblock. */
const RETRASO_MS = 300;

const visible = ref(false);
let temporizador = null;

function cancelar() {
    if (temporizador !== null) {
        clearTimeout(temporizador);
        temporizador = null;
    }
}

function alEmpezar(event) {
    // `only` no vacío = recarga parcial (filtro de tabla): no es un cambio de
    // página y tiene su propio indicador.
    if (event.detail.visit.only?.length) {
        return;
    }

    cancelar();
    temporizador = setTimeout(() => {
        visible.value = true;
    }, RETRASO_MS);
}

function alTerminar() {
    cancelar();
    visible.value = false;
}

let desconectar = [];

onMounted(() => {
    // `finish` dispara también en visitas canceladas o con error, así que el
    // velo no se puede quedar pegado.
    desconectar = [
        router.on('start', alEmpezar),
        router.on('finish', alTerminar),
    ];
});

onUnmounted(() => {
    cancelar();
    desconectar.forEach((off) => off());
});
</script>

<template>
    <Transition name="page-loader">
        <div v-if="visible" class="page-loader" role="status" aria-live="polite">
            <div class="page-loader-caja">
                <div class="page-loader-dots" aria-hidden="true">
                    <span class="page-loader-dot"></span>
                    <span class="page-loader-dot"></span>
                    <span class="page-loader-dot"></span>
                </div>
                <p class="page-loader-texto">Cargando...</p>
            </div>
        </div>
    </Transition>
</template>
