<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import { NAV_MENU } from '@/Data/Sidebar/Nav';
import { userCan } from '@/Directives/Can';
import { tiempoRelativo } from '@/Utils/TimeAgo';

defineEmits(['toggle-sidebar']);

const page = usePage();

/* ---------------------------------------------------------------------- */
/* Buscador: salta a cualquier pantalla del menu (Data/Sidebar/Nav.js)     */
/* ---------------------------------------------------------------------- */
/* Busca sobre el MISMO menu del sidebar y con la misma regla de permisos
   (userCan), asi nunca ofrece una pantalla que el usuario no puede abrir.
   Ctrl+K (o Cmd+K) enfoca el campo desde cualquier lugar. */
const searchInput = ref(null);
const searchTerm = ref('');
const searchFocused = ref(false);
const activeIndex = ref(0);

function normalize(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const searchableLinks = computed(() => {
    const links = [];
    let section = null;

    const collect = (items) => {
        for (const item of items) {
            if (item.type === 'sub') {
                collect(item.children ?? []);
            } else if (item.type === 'link' && userCan(item.permission)) {
                links.push({ title: item.title, path: item.path, icon: item.icon ?? section?.icon, section: section?.menutitle });
            }
        }
    };

    for (const entry of NAV_MENU) {
        if (entry.menutitle) {
            section = entry;
        } else {
            collect([entry]);
        }
    }

    return links;
});

const searchResults = computed(() => {
    const term = normalize(searchTerm.value.trim());

    if (!term) {
        return [];
    }

    return searchableLinks.value
        .filter((link) => normalize(`${link.title} ${link.section ?? ''}`).includes(term))
        .slice(0, 8);
});

const showResults = computed(() => searchFocused.value && searchTerm.value.trim() !== '');

function goTo(link) {
    if (!link) {
        return;
    }

    searchTerm.value = '';
    searchInput.value?.blur();
    router.visit(link.path);
}

function onSearchKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, searchResults.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        goTo(searchResults.value[activeIndex.value]);
    } else if (event.key === 'Escape') {
        searchTerm.value = '';
        searchInput.value?.blur();
    }
}

function onSearchInput() {
    activeIndex.value = 0;
}

/* El blur espera un instante: si se cerrara de inmediato, el click sobre un
   resultado llegaria cuando la lista ya no existe. */
function onSearchBlur() {
    setTimeout(() => {
        searchFocused.value = false;
    }, 120);
}

function focusSearchShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        nextTick(() => searchInput.value?.focus());
    }
}

/* ---------------------------------------------------------------------- */
/* Accesos rapidos (icono de cuadricula)                                   */
/* ---------------------------------------------------------------------- */
const quickAccessLinks = computed(() =>
    [
        { label: 'Nueva cotización', href: route('cotizaciones.create'), icon: 'fa-solid fa-file-circle-plus', tone: 'primary', permission: 'cotizaciones.crear' },
        { label: 'Pedidos', href: route('pedidos.index'), icon: 'fa-solid fa-dolly', tone: 'cyan', permission: 'pedidos.ver' },
        { label: 'Clientes', href: route('clientes.index'), icon: 'fa-solid fa-user-group', tone: 'success', permission: 'clientes.ver' },
        { label: 'Pagos', href: '/pagos', icon: 'fa-solid fa-wallet', tone: 'violet', permission: 'pagos.ver' },
        { label: 'Inteligencia de negocios', href: '/reportes/bi', icon: 'fa-solid fa-chart-line', tone: 'navy', permission: 'reportes.bi' },
        { label: 'Notificaciones', href: route('notificaciones.index'), icon: 'fa-solid fa-bell', tone: 'warning', permission: null },
    ].filter((link) => link.permission === null || userCan(link.permission)),
);

/* ---------------------------------------------------------------------- */
/* Notificaciones (compartidas por HandleInertiaRequests en cada visita)   */
/* ---------------------------------------------------------------------- */
const noLeidas = computed(() => page.props.notificaciones?.no_leidas ?? 0);
const recientes = computed(() => page.props.notificaciones?.recientes ?? []);

function marcarTodasLeidas() {
    router.post(route('notificaciones.marcar-todas'), {}, { preserveScroll: true, preserveState: true });
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
    applyTheme(isDark.value);
}

onMounted(() => {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia(
        '(prefers-color-scheme: dark)',
    ).matches;
    isDark.value = stored ? stored === 'dark' : prefersDark;
    applyTheme(isDark.value);

    window.addEventListener('keydown', focusSearchShortcut);
});

onBeforeUnmount(() => window.removeEventListener('keydown', focusSearchShortcut));

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
        <div class="topbar-start">
            <button
                type="button"
                class="topbar-icon-btn"
                title="Mostrar/ocultar menú"
                aria-label="Mostrar u ocultar el menú"
                @click="$emit('toggle-sidebar')"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="topbar-search" role="search">
                <span class="topbar-search-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <path stroke-linecap="round" d="m21 21-4.3-4.3" />
                    </svg>
                </span>
                <input
                    ref="searchInput"
                    v-model="searchTerm"
                    type="search"
                    placeholder="Buscar en el sistema..."
                    aria-label="Buscar una pantalla del sistema"
                    autocomplete="off"
                    @focus="searchFocused = true"
                    @blur="onSearchBlur"
                    @input="onSearchInput"
                    @keydown="onSearchKeydown"
                />
                <kbd v-show="!searchTerm" class="topbar-search-kbd">Ctrl K</kbd>

                <div v-if="showResults" class="topbar-search-results" role="listbox">
                    <button
                        v-for="(link, index) in searchResults"
                        :key="link.path"
                        type="button"
                        role="option"
                        class="topbar-search-result"
                        :class="{ 'is-active': index === activeIndex }"
                        :aria-selected="index === activeIndex"
                        @mousedown.prevent="goTo(link)"
                        @mouseenter="activeIndex = index"
                    >
                        <span class="topbar-search-result-icon"><i :class="link.icon ?? 'fa-solid fa-circle'"></i></span>
                        <span class="truncate font-medium">{{ link.title }}</span>
                        <span v-if="link.section" class="topbar-search-result-section">{{ link.section }}</span>
                    </button>
                    <p v-if="!searchResults.length" class="topbar-search-empty">
                        Ninguna pantalla coincide con “{{ searchTerm.trim() }}”.
                    </p>
                </div>
            </div>
        </div>

        <div class="topbar-actions">
            <button
                type="button"
                class="topbar-icon-btn topbar-icon-btn-desktop"
                title="Pantalla completa"
                aria-label="Pantalla completa"
                @click="toggleFullscreen"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 4H4v5M15 4h5v5M9 20H4v-5M15 20h5v-5" />
                </svg>
            </button>

            <button
                type="button"
                class="topbar-icon-btn"
                :title="isDark ? 'Modo claro' : 'Modo oscuro'"
                :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
                @click="toggleTheme"
            >
                <svg v-if="isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="4" />
                    <path
                        stroke-linecap="round"
                        d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
                    />
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linejoin="round" d="M20.5 14.1A8.5 8.5 0 1 1 9.9 3.5a7 7 0 0 0 10.6 10.6Z" />
                </svg>
            </button>

            <!-- Notificaciones -->
            <Dropdown align="right" width="96" content-classes="p-0 bg-transparent" bare>
                <template #trigger>
                    <button type="button" class="topbar-icon-btn" title="Notificaciones" aria-label="Notificaciones">
                        <span v-if="noLeidas > 0" class="badge-dot"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"
                            />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <div class="card" style="width: 360px">
                        <div class="card-header">
                            <span class="card-title">Notificaciones</span>
                            <span v-if="noLeidas > 0" class="badge badge-soft-primary">
                                {{ noLeidas }} nueva{{ noLeidas === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <ul v-if="recientes.length" class="list-group px-5">
                            <li v-for="n in recientes" :key="n.id" class="list-group-item">
                                <Link :href="n.url" class="list-group-item-start notif-link">
                                    <span class="list-icon" :class="`stat-icon-${n.color}`">
                                        <i :class="n.icono"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="list-group-item-title" :class="{ 'fw-normal': n.leida }">
                                            {{ n.titulo }}
                                        </p>
                                        <p class="fs-xs text-muted truncate">{{ n.mensaje }}</p>
                                        <p class="fs-xs text-muted">{{ tiempoRelativo(n.creada_hace) }}</p>
                                    </div>
                                    <span v-if="!n.leida" class="notif-unread-dot"></span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="notif-empty">No tienes notificaciones todavía.</p>
                        <div class="notif-footer">
                            <button v-if="noLeidas > 0" type="button" class="card-link" @click="marcarTodasLeidas">
                                Marcar todas como leídas
                            </button>
                            <Link :href="route('notificaciones.index')" class="card-link">Ver todas</Link>
                        </div>
                    </div>
                </template>
            </Dropdown>

            <!-- Accesos rapidos -->
            <Dropdown align="right" width="96" content-classes="p-0 bg-transparent" bare>
                <template #trigger>
                    <button type="button" class="topbar-icon-btn" title="Accesos rápidos" aria-label="Accesos rápidos">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4" width="6.5" height="6.5" rx="1.6" />
                            <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6" />
                            <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6" />
                            <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6" />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <div class="quick-access">
                        <p class="quick-access-title">Accesos rápidos</p>
                        <div class="quick-access-grid">
                            <Link
                                v-for="link in quickAccessLinks"
                                :key="link.label"
                                :href="link.href"
                                class="quick-access-item"
                            >
                                <span class="stat-icon" :class="`stat-icon-${link.tone}`"><i :class="link.icon"></i></span>
                                {{ link.label }}
                            </Link>
                        </div>
                    </div>
                </template>
            </Dropdown>

            <span class="topbar-divider" aria-hidden="true"></span>

            <!-- Usuario -->
            <Dropdown align="right" width="48" content-classes="p-0 bg-transparent" bare>
                <template #trigger>
                    <button type="button" class="topbar-user" aria-label="Menú de usuario">
                        <span class="avatar avatar-sm">
                            <img
                                v-if="$page.props.auth.user?.foto_url"
                                :src="$page.props.auth.user.foto_url"
                                alt="Foto de perfil"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="avatar-title">{{
                                $page.props.auth.user?.name?.charAt(0)?.toUpperCase() ?? 'U'
                            }}</span>
                        </span>
                        <span class="hidden text-start sm:block">
                            <span class="topbar-user-name d-block">{{ $page.props.auth.user?.name ?? 'Usuario' }}</span>
                            <span class="topbar-user-role d-block capitalize">{{
                                $page.props.auth.roles?.[0]?.replaceAll('-', ' ') ?? 'Usuario'
                            }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 opacity-60 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <div class="topbar-menu">
                        <Link :href="route('profile.edit')" class="topbar-menu-item">
                            <i class="fa-regular fa-circle-user"></i> Mi perfil
                        </Link>
                        <Link :href="route('notificaciones.index')" class="topbar-menu-item">
                            <i class="fa-regular fa-bell"></i> Notificaciones
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="topbar-menu-item">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión
                        </Link>
                    </div>
                </template>
            </Dropdown>
        </div>
    </header>
</template>
