<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Sidebar del dashboard. El estado de apertura movil vive en el layout
 * (MainDashboardLayout) porque el boton que la abre esta en el Topbar;
 * el acordeon de submenus es un detalle interno de este componente.
 */
defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);

const openMenu = ref('dashboards');

function toggleMenu(key) {
    openMenu.value = openMenu.value === key ? null : key;
}
</script>

<template>
    <!-- display:contents: no agrega caja propia, para no romper el
         posicionamiento fixed del overlay ni del <aside>. -->
    <div class="contents">
        <!-- Overlay movil -->
        <div v-if="isOpen" class="sidebar-overlay" @click="$emit('close')"></div>

        <aside class="sidebar" :class="{ 'is-open': isOpen }">
            <div class="sidebar-brand">
                <svg
                    class="h-8 w-8 shrink-0 text-white"
                    style="color: var(--c-primary)"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        d="M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3 6.5 3.6L12 11.5 5.5 7.9 12 4.3ZM5 9.6l6 3.3v7L5 16.6V9.6Zm8 10.3v-7l6-3.3v7l-6 3.3Z"
                    />
                </svg>
                <span class="sidebar-brand-text">FUELPRO</span>
            </div>

            <div class="sidebar-body">
                <p class="sidebar-menu-label">Menu</p>
                <ul class="nav-sidebar">
                    <!-- Dashboards (con submenu) -->
                    <li
                        class="nav-sidebar-item"
                        :class="{ 'is-expanded': openMenu === 'dashboards' }"
                    >
                        <a
                            href="#"
                            class="nav-sidebar-link"
                            :class="{ active: openMenu === 'dashboards' }"
                            @click.prevent="toggleMenu('dashboards')"
                        >
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 9.5 12 3l9 6.5M5 8.5V20a1 1 0 0 0 1 1h4v-5a2 2 0 1 1 4 0v5h4a1 1 0 0 0 1-1V8.5"
                                />
                            </svg>
                            <span class="nav-text">Dashboards</span>
                            <svg
                                class="nav-chevron"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m6 9 6 6 6-6"
                                />
                            </svg>
                        </a>
                        <ul
                            v-show="openMenu === 'dashboards'"
                            class="nav-sidebar-submenu"
                        >
                            <li>
                                <Link
                                    :href="route('dashboard')"
                                    class="nav-sidebar-submenu-link"
                                    :class="{
                                        active: route().current('dashboard'),
                                    }"
                                    @click="$emit('close')"
                                >
                                    Analytics
                                </Link>
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >CRM</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Ecommerce</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Crypto</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Projects</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >NFT</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Job</a
                                >
                            </li>
                            <li class="d-flex align-items-center gap-2">
                                <a
                                    href="#"
                                    class="nav-sidebar-submenu-link flex-fill"
                                    >Blog</a
                                >
                                <span class="badge badge-soft-success"
                                    >New</span
                                >
                            </li>
                        </ul>
                    </li>

                    <!-- Apps -->
                    <li
                        class="nav-sidebar-item"
                        :class="{ 'is-expanded': openMenu === 'apps' }"
                    >
                        <a
                            href="#"
                            class="nav-sidebar-link"
                            @click.prevent="toggleMenu('apps')"
                        >
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z"
                                />
                            </svg>
                            <span class="nav-text">Apps</span>
                            <svg
                                class="nav-chevron"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m6 9 6 6 6-6"
                                />
                            </svg>
                        </a>
                        <ul
                            v-show="openMenu === 'apps'"
                            class="nav-sidebar-submenu"
                        >
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Calendar</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Chat</a
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >File Manager</a
                                >
                            </li>
                        </ul>
                    </li>

                    <!-- Layouts -->
                    <li
                        class="nav-sidebar-item"
                        :class="{ 'is-expanded': openMenu === 'layouts' }"
                    >
                        <a
                            href="#"
                            class="nav-sidebar-link"
                            @click.prevent="toggleMenu('layouts')"
                        >
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 5h16v4H4V5Zm0 6h7v8H4v-8Zm9 0h7v8h-7v-8Z"
                                />
                            </svg>
                            <span class="nav-text">Layouts</span>
                            <span class="badge badge-soft-danger">Hot</span>
                        </a>
                    </li>
                </ul>

                <p class="sidebar-menu-label">Pages</p>
                <ul class="nav-sidebar">
                    <li
                        class="nav-sidebar-item"
                        :class="{ 'is-expanded': openMenu === 'auth' }"
                    >
                        <a
                            href="#"
                            class="nav-sidebar-link"
                            @click.prevent="toggleMenu('auth')"
                        >
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-6 6v-1a6 6 0 0 1 12 0v1"
                                />
                                <circle
                                    cx="12"
                                    cy="7"
                                    r="4"
                                    stroke-linecap="round"
                                />
                            </svg>
                            <span class="nav-text">Authentication</span>
                            <svg
                                class="nav-chevron"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m6 9 6 6 6-6"
                                />
                            </svg>
                        </a>
                        <ul
                            v-show="openMenu === 'auth'"
                            class="nav-sidebar-submenu"
                        >
                            <li>
                                <Link
                                    :href="route('login')"
                                    class="nav-sidebar-submenu-link"
                                    @click="$emit('close')"
                                    >Login</Link
                                >
                            </li>
                            <li>
                                <Link
                                    :href="route('register')"
                                    class="nav-sidebar-submenu-link"
                                    @click="$emit('close')"
                                    >Register</Link
                                >
                            </li>
                        </ul>
                    </li>

                    <li
                        class="nav-sidebar-item"
                        :class="{ 'is-expanded': openMenu === 'pages' }"
                    >
                        <a
                            href="#"
                            class="nav-sidebar-link"
                            @click.prevent="toggleMenu('pages')"
                        >
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 0v5h5"
                                />
                            </svg>
                            <span class="nav-text">Pages</span>
                            <svg
                                class="nav-chevron"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m6 9 6 6 6-6"
                                />
                            </svg>
                        </a>
                        <ul
                            v-show="openMenu === 'pages'"
                            class="nav-sidebar-submenu"
                        >
                            <li>
                                <Link
                                    :href="route('profile.edit')"
                                    class="nav-sidebar-submenu-link"
                                    @click="$emit('close')"
                                    >Profile</Link
                                >
                            </li>
                            <li>
                                <a href="#" class="nav-sidebar-submenu-link"
                                    >Pricing</a
                                >
                            </li>
                        </ul>
                    </li>
                </ul>

                <p class="sidebar-menu-label">Components</p>
                <ul class="nav-sidebar">
                    <li class="nav-sidebar-item">
                        <a href="#" class="nav-sidebar-link">
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="1"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="1"
                                />
                            </svg>
                            <span class="nav-text">Base UI</span>
                        </a>
                    </li>
                    <li class="nav-sidebar-item">
                        <a href="#" class="nav-sidebar-link">
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 2v20M2 12h20"
                                />
                            </svg>
                            <span class="nav-text">Advance UI</span>
                        </a>
                    </li>
                    <li class="nav-sidebar-item">
                        <a href="#" class="nav-sidebar-link">
                            <svg
                                class="nav-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 6h16M4 12h16M4 18h7"
                                />
                            </svg>
                            <span class="nav-text">Widgets</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</template>
