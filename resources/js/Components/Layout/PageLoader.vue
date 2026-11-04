<script setup>
/**
 * Loader de cambio de página con la marca XtraPubli: el emblema redondo dentro
 * de un anillo que gira en los colores de marca, una barra de avance y el texto
 * "Cargando...". Si la espera se alarga, el texto lo dice.
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

/** Desde cuándo la espera se considera larga y el texto lo avisa. */
const DEMORA_MS = 6000;

const EMBLEMA = '/img/logo/logo-mini.png';

const visible = ref(false);
const demorado = ref(false);
let temporizadores = [];

function cancelar() {
    temporizadores.forEach((id) => clearTimeout(id));
    temporizadores = [];
}

function alEmpezar(event) {
    // `only` no vacío = recarga parcial (filtro de tabla): no es un cambio de
    // página y tiene su propio indicador.
    if (event.detail.visit.only?.length) {
        return;
    }

    cancelar();
    demorado.value = false;
    temporizadores = [
        setTimeout(() => {
            visible.value = true;
        }, RETRASO_MS),
        setTimeout(() => {
            demorado.value = true;
        }, DEMORA_MS),
    ];
}

function alTerminar() {
    cancelar();
    visible.value = false;
}

let desconectar = [];

onMounted(() => {
    // El emblema se baja de antemano: el velo aparece justo cuando la red está
    // ocupada, y sin esto la primera vez saldría el anillo vacío.
    new Image().src = EMBLEMA;

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
                <div class="page-loader-emblema" aria-hidden="true">
                    <span class="page-loader-halo"></span>
                    <span class="page-loader-anillo"></span>
                    <img :src="EMBLEMA" alt="" class="page-loader-logo" />
                </div>

                <div class="page-loader-barra" aria-hidden="true">
                    <span class="page-loader-barra-avance"></span>
                </div>

                <p class="page-loader-texto">
                    {{ demorado ? 'Está tardando un poco más de lo normal...' : 'Cargando...' }}
                </p>
                <p class="page-loader-lema">Hacemos que tu marca venda</p>
            </div>
        </div>
    </Transition>
</template>
