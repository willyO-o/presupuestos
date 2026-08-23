<script setup>
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';

defineEmits(['toggle-sidebar']);

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
    applyTheme(isDark.value);
}

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
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button
                type="button"
                class="topbar-icon-btn"
                title="Mostrar/ocultar menu"
                @click="$emit('toggle-sidebar')"
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
                        <path stroke-linecap="round" d="m21 21-4.3-4.3" />
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

            <button type="button" class="topbar-icon-btn" @click="toggleTheme">
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
                <svg v-else viewBox="0 0 24 24" fill="currentColor">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
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
                            <span class="badge badge-soft-primary">3 Nuevas</span>
                        </div>
                        <ul class="list-group px-5">
                            <li class="list-group-item">
                                <div class="list-group-item-start">
                                    <span class="list-icon stat-icon-success"
                                        >💰</span
                                    >
                                    <div class="min-w-0">
                                        <p class="list-group-item-title">
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
                                    <span class="list-icon stat-icon-warning"
                                        >⛽</span
                                    >
                                    <div class="min-w-0">
                                        <p class="list-group-item-title">
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
                                    <span class="list-icon stat-icon-info"
                                        >📄</span
                                    >
                                    <div class="min-w-0">
                                        <p class="list-group-item-title">
                                            Nuevo reporte disponible
                                        </p>
                                        <p class="fs-xs text-muted">Ayer</p>
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
                            <img
                                v-if="$page.props.auth.user?.foto_url"
                                :src="$page.props.auth.user.foto_url"
                                alt="Foto de perfil"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="avatar-title">{{
                                $page.props.auth.user?.name
                                    ?.charAt(0)
                                    ?.toUpperCase() ?? 'U'
                            }}</span>
                        </span>
                        <span class="hidden text-start sm:block">
                            <span class="topbar-user-name d-block">{{
                                $page.props.auth.user?.name ?? 'Usuario'
                            }}</span>
                            <span class="topbar-user-role d-block capitalize">{{
                                $page.props.auth.roles?.[0]?.replaceAll('-', ' ') ?? 'Usuario'
                            }}</span>
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
</template>
