<script setup>
import { ref } from 'vue';
import MainDashboardSidebar from '@/Components/MainDashboardSidebar.vue';

const isSidebarOpen = ref(true);
const isDarkMode = ref(false);

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
}
</script>

<template>
    <div class="dashboard-shell" :class="{ 'dashboard-theme-dark': isDarkMode }" @keydown.esc="isSidebarOpen = false">
        <MainDashboardSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />

        <button
            class="dashboard-backdrop"
            :class="{ 'dashboard-backdrop-visible': isSidebarOpen }"
            aria-label="Cerrar menú"
            @click="isSidebarOpen = false"
        />

        <section class="dashboard-workspace" :class="{ 'dashboard-workspace-sidebar-open': isSidebarOpen }">
            <header class="dashboard-topbar">
                <div class="dashboard-topbar-start">
                    <button class="btn-icon dashboard-icon-button" :aria-label="isSidebarOpen ? 'Cerrar menú' : 'Abrir menú'" @click="toggleSidebar"><i :class="isSidebarOpen ? 'ri-menu-fold-line' : 'ri-menu-unfold-line'" /></button>
                    <label class="dashboard-search">
                        <i class="ri-search-line" aria-hidden="true" />
                        <input type="search" placeholder="Buscar cotización, cliente..." aria-label="Buscar" />
                    </label>
                </div>

                <div class="dashboard-topbar-actions">
                    <button class="btn-icon dashboard-icon-button dashboard-desktop-action" aria-label="Pantalla completa"><i class="ri-fullscreen-line" /></button>
                    <button class="btn-icon dashboard-icon-button" :aria-label="isDarkMode ? 'Usar modo claro' : 'Usar modo oscuro'" @click="isDarkMode = !isDarkMode"><i :class="isDarkMode ? 'ri-sun-line' : 'ri-moon-line'" /></button>
                    <button class="btn-icon dashboard-icon-button dashboard-notification" aria-label="Notificaciones"><i class="ri-notification-3-line" /><span>3</span></button>
                    <div class="dashboard-user">
                        <div class="avatar-sm avatar-square"><span class="avatar-title">MA</span></div>
                        <div class="dashboard-user-copy">
                            <strong>Marco Adame</strong>
                            <span>Administrador</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-breadcrumb">
                <div><span>Panel administrativo</span><strong>Resumen general</strong></div>
                <span>Inicio / Dashboard</span>
            </div>

            <main class="dashboard-content"><slot /></main>
        </section>
    </div>
</template>
