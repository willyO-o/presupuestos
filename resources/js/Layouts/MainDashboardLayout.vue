<script setup>
import { onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';

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

/* ---------------------------------------------------------------------- */
/* Sidebar: apertura movil + acordeon de submenus                          */
/* ---------------------------------------------------------------------- */
const isSidebarOpen = ref(false);
const openMenu = ref('dashboards');

function toggleMenu(key) {
    openMenu.value = openMenu.value === key ? null : key;
}

function closeSidebar() {
    isSidebarOpen.value = false;
}

/* ---------------------------------------------------------------------- */
/* Modo oscuro                                                             */
/* ---------------------------------------------------------------------- */
const isDark = ref(false);

function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

function toggleTheme() {
    isDark.value = !isDark.value;
}

watch(isDark, (value) => applyTheme(value));

onMounted(() => {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia(
        '(prefers-color-scheme: dark)',
    ).matches;
    isDark.value = stored ? stored === 'dark' : prefersDark;
    applyTheme(isDark.value);
});

/* ---------------------------------------------------------------------- */
/* Pantalla completa                                                       */
/* ---------------------------------------------------------------------- */
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}
</script>

<template>
    <div class="app-shell">
        <!-- Overlay movil -->
        <div
            v-if="isSidebarOpen"
            class="sidebar-overlay"
            @click="closeSidebar"
        ></div>

        <!-- ============================ SIDEBAR ============================ -->
        <aside class="sidebar" :class="{ 'is-open': isSidebarOpen }">
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
                                    >Login</Link
                                >
                            </li>
                            <li>
                                <Link
                                    :href="route('register')"
                                    class="nav-sidebar-submenu-link"
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

        <!-- ============================ MAIN ============================ -->
        <div class="main-wrapper">
            <!-- Topbar -->
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button
                        type="button"
                        class="topbar-icon-btn lg:hidden"
                        @click="isSidebarOpen = !isSidebarOpen"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>

                    <div class="topbar-search">
                        <span class="topbar-search-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path
                                    stroke-linecap="round"
                                    d="m21 21-4.3-4.3"
                                />
                            </svg>
                        </span>
                        <input type="text" placeholder="Buscar..." />
                    </div>
                </div>

                <div class="topbar-actions">
                    <button
                        type="button"
                        class="topbar-icon-btn hidden sm:flex"
                        @click="toggleFullscreen"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 4H4v5M15 4h5v5M9 20H4v-5M15 20h5v-5"
                            />
                        </svg>
                    </button>

                    <button
                        type="button"
                        class="topbar-icon-btn"
                        @click="toggleTheme"
                    >
                        <svg
                            v-if="isDark"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle cx="12" cy="12" r="4" />
                            <path
                                stroke-linecap="round"
                                d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
                            />
                        </svg>
                        <svg
                            v-else
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"
                            />
                        </svg>
                    </button>

                    <Dropdown
                        align="right"
                        width="80"
                        content-classes="p-0 bg-transparent"
                    >
                        <template #trigger>
                            <button type="button" class="topbar-icon-btn">
                                <span class="badge-dot"></span>
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="card border-0 shadow-none" style="width: 320px">
                                <div class="card-header">
                                    <span class="card-title">Notificaciones</span>
                                    <span class="badge badge-soft-primary"
                                        >3 Nuevas</span
                                    >
                                </div>
                                <ul class="list-group px-5">
                                    <li class="list-group-item">
                                        <div class="list-group-item-start">
                                            <span
                                                class="list-icon stat-icon-success"
                                                >💰</span
                                            >
                                            <div class="min-w-0">
                                                <p
                                                    class="list-group-item-title"
                                                >
                                                    Presupuesto aprobado
                                                </p>
                                                <p class="fs-xs text-muted">
                                                    Hace 5 minutos
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item">
                                        <div class="list-group-item-start">
                                            <span
                                                class="list-icon stat-icon-warning"
                                                >⛽</span
                                            >
                                            <div class="min-w-0">
                                                <p
                                                    class="list-group-item-title"
                                                >
                                                    Consumo por encima del limite
                                                </p>
                                                <p class="fs-xs text-muted">
                                                    Hace 2 horas
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item">
                                        <div class="list-group-item-start">
                                            <span
                                                class="list-icon stat-icon-info"
                                                >📄</span
                                            >
                                            <div class="min-w-0">
                                                <p
                                                    class="list-group-item-title"
                                                >
                                                    Nuevo reporte disponible
                                                </p>
                                                <p class="fs-xs text-muted">
                                                    Ayer
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </Dropdown>

                    <Dropdown
                        align="right"
                        width="48"
                        content-classes="py-1 bg-white dark:bg-[#262a3d]"
                    >
                        <template #trigger>
                            <button type="button" class="topbar-user">
                                <span class="avatar avatar-sm">
                                    <span class="avatar-title">{{
                                        $page.props.auth.user?.name
                                            ?.charAt(0)
                                            ?.toUpperCase() ?? 'U'
                                    }}</span>
                                </span>
                                <span class="hidden text-start sm:block">
                                    <span
                                        class="topbar-user-name d-block"
                                        >{{
                                            $page.props.auth.user?.name ??
                                            'Usuario'
                                        }}</span
                                    >
                                    <span class="topbar-user-role d-block"
                                        >Administrador</span
                                    >
                                </span>
                                <svg
                                    class="hidden h-4 w-4 opacity-60 sm:block"
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
                            </button>
                        </template>
                        <template #content>
                            <Link
                                :href="route('profile.edit')"
                                class="d-block px-4 py-2 fs-sm text-heading hover:opacity-70"
                                >Mi perfil</Link
                            >
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="d-block w-100 px-4 py-2 text-start fs-sm text-heading hover:opacity-70"
                                >Cerrar sesion</Link
                            >
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Contenido -->
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
        </div>
    </div>
</template>
