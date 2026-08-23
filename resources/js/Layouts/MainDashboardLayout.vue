<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Layout/Sidebar.vue';
import Topbar from '@/Components/Layout/Topbar.vue';
import Footer from '@/Components/Layout/Footer.vue';

/**
 * pageTitle / breadcrumbs pueden sobrescribirse enviandolos como prop de
 * Inertia desde el controlador (Inertia::render('X', ['pageTitle' => ...])),
 * ya que el layout persistente recibe las mismas props que la pagina.
 */
defineProps({
    pageTitle: {
        type: String,
        default: 'Dashboard',
    },
    breadcrumbs: {
        type: Array,
        default: () => ['Dashboards', 'Dashboard'],
    },
});

/* Visibilidad del sidebar: vive aqui porque el boton que la dispara esta en
   el Topbar y el panel que la consume esta en el Sidebar. El boton esta
   disponible en todos los tamanos de pantalla; solo cambia el valor inicial:
   visible por defecto en desktop, oculto por defecto en movil/tablet para no
   tapar el contenido. A partir de ahi el usuario controla el estado. */
const isSidebarOpen = ref(window.innerWidth >= 1024);
</script>

<template>
    <div class="app-shell">
        <Sidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />

        <div class="main-wrapper" :class="{ 'sidebar-open': isSidebarOpen }">
            <Topbar @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />

            <main class="page-content">
                <div class="page-title-box">
                    <h1 class="page-title">{{ pageTitle }}</h1>
                    <nav class="breadcrumb">
                        <span
                            v-for="(crumb, index) in breadcrumbs"
                            :key="index"
                            class="d-flex align-items-center gap-2"
                        >
                            <span
                                class="breadcrumb-item"
                                :class="{
                                    active: index === breadcrumbs.length - 1,
                                }"
                                >{{ crumb }}</span
                            >
                            <span v-if="index < breadcrumbs.length - 1"
                                >/</span
                            >
                        </span>
                    </nav>
                </div>

                <slot />
            </main>

            <Footer />
        </div>
    </div>
</template>
