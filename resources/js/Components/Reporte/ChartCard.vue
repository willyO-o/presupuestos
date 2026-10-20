<script setup>
import AyudaInfo from '@/Components/AyudaInfo.vue';

/**
 * Tarjeta de un cuadro del BI: el título (el tema, como en todo el panel) y
 * debajo el HALLAZGO, lo que el gráfico dice en una frase, armado en el
 * servidor con los mismos números. Quien lee solo los títulos y sus
 * hallazgos ya se lleva las conclusiones; el gráfico es la prueba.
 *
 * Mientras se recalculan los datos (cambio de filtro, drill-down) la tarjeta
 * se atenúa y no se puede tocar: se ve que algo está pasando y no se lee un
 * número viejo como si fuera el nuevo.
 */
defineProps({
    /** El tema del cuadro ("Ventas por mes"). */
    tema: { type: String, required: true },
    /** La conclusión en una frase. */
    hallazgo: { type: String, default: '' },
    /** Clave de `Data/Ayudas.js` para el ícono "i". */
    ayuda: { type: String, default: null },
    cargando: { type: Boolean, default: false },
});
</script>

<template>
    <section class="card chart-card" :class="{ 'is-cargando': cargando }" :aria-busy="cargando">
        <header class="card-header chart-card-cabecera">
            <div class="min-w-0 flex-1">
                <h2 class="card-title">{{ tema }} <AyudaInfo v-if="ayuda" :clave="ayuda" /></h2>
                <p v-if="hallazgo" class="chart-card-hallazgo">
                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    <span>{{ hallazgo }}</span>
                </p>
            </div>
            <slot name="acciones" />
        </header>
        <div class="card-body">
            <slot />
        </div>
    </section>
</template>
