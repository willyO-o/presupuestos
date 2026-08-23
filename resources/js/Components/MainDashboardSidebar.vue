<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const navigation = [
    { label: 'Resumen', icon: 'ri-dashboard-line', active: true },
    { label: 'Cotizaciones', icon: 'ri-file-list-3-line' },
    { label: 'Órdenes de producción', icon: 'ri-tools-line' },
    { label: 'Materiales', icon: 'ri-stack-line' },
    { label: 'Clientes', icon: 'ri-team-line' },
];
</script>

<template>
    <aside class="dashboard-sidebar" :class="{ 'dashboard-sidebar-open': isOpen }">
        <div class="dashboard-brand">
            <span class="dashboard-brand-mark">X</span>
            <span>XtraPubli</span>
            <button class="btn-icon dashboard-sidebar-close" aria-label="Cerrar menú" @click="emit('close')">
                <i class="ri-close-line" />
            </button>
        </div>

        <nav class="dashboard-nav" aria-label="Navegación principal">
            <p class="dashboard-nav-label">MENÚ</p>
            <Link
                v-for="item in navigation"
                :key="item.label"
                :href="item.active ? route('dashboard') : '#'"
                class="dashboard-nav-link"
                :class="{ 'dashboard-nav-link-active': item.active }"
                @click="emit('close')"
            >
                <i class="dashboard-nav-icon" :class="item.icon" aria-hidden="true" />
                {{ item.label }}
            </Link>

            <p class="dashboard-nav-label dashboard-nav-label-spaced">SISTEMA</p>
            <a class="dashboard-nav-link" href="#configuracion">
                <i class="dashboard-nav-icon ri-settings-3-line" aria-hidden="true" />
                Configuración
            </a>
        </nav>

        <div class="dashboard-sidebar-footer">
            <span class="badge-soft-info">Panel interno</span>
            <p>Gestión de costos y presupuestos</p>
        </div>
    </aside>
</template>
