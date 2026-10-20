<script setup>
import { computed, reactive } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { NAV_MENU } from '@/Data/Sidebar/Nav';

/**
 * Sidebar del dashboard. El menu (items, permisos, iconos) vive en
 * resources/js/Data/Sidebar/Nav.js — este componente solo sabe renderizarlo,
 * hasta 3 niveles de profundidad (item -> children -> children.children).
 *
 * Dos formas de seccion:
 * - Sin `icon` en el separador (ej. "Principal"): rotulo fijo y enlaces con
 *   su propio icono.
 * - Con `icon`: acordeon. El rotulo es un boton (icono + chevron) y sus
 *   enlaces llevan un punto en vez de icono, como en la referencia del tema.
 *
 * El estado de apertura movil llega del layout (el boton esta en el Topbar);
 * que secciones/submenus estan expandidos es un detalle interno de este
 * componente.
 */
defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

/* Al hacer click en un enlace, el sidebar solo se debe auto-cerrar en
   pantallas chicas (ahi tapa el contenido, como un drawer). En desktop
   (>= 1024px, breakpoint 'lg') el usuario lo cierra a mano con el boton del
   Topbar si quiere — un click de navegacion no deberia esconderlo solo. */
function closeOnMobile() {
    if (window.innerWidth < 1024) {
        emit('close');
    }
}

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

function isActive(path) {
    if (!path) {
        return false;
    }
    return currentPath.value === path || currentPath.value.startsWith(`${path}/`);
}

/* Agrupa el arreglo plano de NAV_MENU (separadores + items intercalados) en
   secciones, para poder renderizar un bloque por seccion con su titulo. */
const sections = computed(() => {
    const result = [];
    let current = null;

    for (const entry of NAV_MENU) {
        if (entry.menutitle) {
            current = { label: entry.menutitle, icon: entry.icon, permission: entry.permission, items: [] };
            result.push(current);
        } else if (current) {
            current.items.push(entry);
        }
    }

    return result;
});

/* ---------------------------------------------------------------------- */
/* Secciones con acordeon                                                  */
/* ---------------------------------------------------------------------- */
/* Por defecto todas abiertas (asi se ve el menu completo de un vistazo). Se
   recuerdan las que el usuario CERRO, no las abiertas: una seccion nueva en
   Nav.js aparece abierta sin tocar nada. */
const CLOSED_SECTIONS_KEY = 'sidebar.secciones-cerradas';

function readClosedSections() {
    try {
        const stored = JSON.parse(localStorage.getItem(CLOSED_SECTIONS_KEY) ?? '[]');
        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
}

const closedSections = reactive(new Set(readClosedSections()));

function isSectionOpen(label) {
    return !closedSections.has(label);
}

function toggleSection(label) {
    if (closedSections.has(label)) {
        closedSections.delete(label);
    } else {
        closedSections.add(label);
    }

    try {
        localStorage.setItem(CLOSED_SECTIONS_KEY, JSON.stringify([...closedSections]));
    } catch {
        // Sin almacenamiento (modo privado): el acordeon funciona igual, solo
        // no se recuerda entre visitas.
    }
}

/* ---------------------------------------------------------------------- */
/* Submenus (items type 'sub')                                             */
/* ---------------------------------------------------------------------- */
/* Guarda el titulo de cada item tipo 'sub' que esta expandido (a cualquier
   nivel). Se usa el titulo como key porque debe ser unico dentro del menu. */
const openKeys = reactive(new Set());

function isOpenKey(title) {
    return openKeys.has(title);
}

function toggleKey(title) {
    if (openKeys.has(title)) {
        openKeys.delete(title);
    } else {
        openKeys.add(title);
    }
}

function containsCurrentRoute(items) {
    return items.some((item) =>
        item.type === 'sub' ? containsCurrentRoute(item.children ?? []) : isActive(item.path),
    );
}

/* Al cargar, expande automaticamente la seccion y los submenus que contienen
   la ruta actual, para que el usuario vea donde esta parado aunque haya
   cerrado esa seccion antes. */
for (const section of sections.value) {
    if (containsCurrentRoute(section.items)) {
        closedSections.delete(section.label);
    }
}

(function openAncestorsOfCurrentRoute(items) {
    for (const item of items) {
        if (item.type === 'sub') {
            if (openAncestorsOfCurrentRoute(item.children ?? [])) {
                openKeys.add(item.title);
                return true;
            }
        } else if (item.type === 'link' && isActive(item.path)) {
            return true;
        }
    }
    return false;
})(NAV_MENU);
</script>

<template>
    <!-- display:contents: no agrega caja propia, para no romper el
         posicionamiento fixed del overlay ni del <aside>. -->
    <div class="contents">
        <!-- Overlay movil -->
        <div v-if="isOpen" class="sidebar-overlay" @click="$emit('close')"></div>

        <aside class="sidebar" :class="{ 'is-open': isOpen }" aria-label="Menú principal">
            <div class="sidebar-brand">
                <Link :href="route('dashboard')" class="sidebar-brand-logo">
                    <img src="/img/logo/logo-blanco.png" alt="XtraPubli" />
                </Link>
            </div>

            <nav class="sidebar-body">
                <div
                    v-for="section in sections"
                    :key="section.label"
                    v-can="section.permission"
                    :class="section.icon ? ['nav-section', { 'is-expanded': isSectionOpen(section.label) }] : null"
                >
                    <!-- Seccion con acordeon -->
                    <button
                        v-if="section.icon"
                        type="button"
                        class="nav-section-toggle"
                        :aria-expanded="isSectionOpen(section.label)"
                        @click="toggleSection(section.label)"
                    >
                        <i :class="section.icon" class="nav-icon-i" aria-hidden="true"></i>
                        <span class="nav-text">{{ section.label }}</span>
                        <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <!-- Seccion fija -->
                    <p v-else class="sidebar-menu-label">{{ section.label }}</p>

                    <ul
                        v-show="!section.icon || isSectionOpen(section.label)"
                        :class="section.icon ? 'nav-section-list' : 'nav-sidebar'"
                    >
                        <li
                            v-for="item in section.items"
                            :key="item.title"
                            v-can="item.permission"
                            class="nav-sidebar-item"
                            :class="{
                                'is-expanded':
                                    item.type === 'sub' && isOpenKey(item.title),
                            }"
                        >
                            <!-- Nivel 1: enlace directo -->
                            <Link
                                v-if="item.type === 'link'"
                                :href="item.path"
                                class="nav-sidebar-link"
                                :class="{ active: isActive(item.path) }"
                                :aria-current="isActive(item.path) ? 'page' : undefined"
                                @click="closeOnMobile"
                            >
                                <span v-if="section.icon" class="nav-dot" aria-hidden="true"></span>
                                <i
                                    v-else-if="item.icon"
                                    :class="item.icon"
                                    class="nav-icon-i"
                                    aria-hidden="true"
                                ></i>
                                <span class="nav-text">{{ item.title }}</span>
                            </Link>

                            <!-- Nivel 1: submenu (nivel 2 adentro) -->
                            <template v-else>
                                <a
                                    href="#"
                                    class="nav-sidebar-link"
                                    :class="{ active: isOpenKey(item.title) }"
                                    @click.prevent="toggleKey(item.title)"
                                >
                                    <span v-if="section.icon" class="nav-dot" aria-hidden="true"></span>
                                    <i
                                        v-else-if="item.icon"
                                        :class="item.icon"
                                        class="nav-icon-i"
                                        aria-hidden="true"
                                    ></i>
                                    <span class="nav-text">{{ item.title }}</span>
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
                                    v-show="isOpenKey(item.title)"
                                    class="nav-sidebar-submenu"
                                >
                                    <li
                                        v-for="child in item.children"
                                        :key="child.title"
                                        v-can="child.permission"
                                        :class="{
                                            'nav-sidebar-item':
                                                child.type === 'sub',
                                            'is-expanded':
                                                child.type === 'sub' &&
                                                isOpenKey(child.title),
                                        }"
                                    >
                                        <!-- Nivel 2: enlace directo -->
                                        <Link
                                            v-if="child.type === 'link'"
                                            :href="child.path"
                                            class="nav-sidebar-submenu-link"
                                            :class="{ active: isActive(child.path) }"
                                            @click="closeOnMobile"
                                        >
                                            {{ child.title }}
                                        </Link>

                                        <!-- Nivel 2: submenu (nivel 3, el maximo soportado) -->
                                        <template v-else>
                                            <a
                                                href="#"
                                                class="nav-sidebar-link"
                                                :class="{ active: isOpenKey(child.title) }"
                                                @click.prevent="toggleKey(child.title)"
                                            >
                                                <span class="nav-text">{{
                                                    child.title
                                                }}</span>
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
                                                v-show="isOpenKey(child.title)"
                                                class="nav-sidebar-submenu"
                                            >
                                                <li
                                                    v-for="grandchild in child.children"
                                                    :key="grandchild.title"
                                                    v-can="grandchild.permission"
                                                >
                                                    <Link
                                                        :href="grandchild.path"
                                                        class="nav-sidebar-submenu-link"
                                                        :class="{
                                                            active: isActive(
                                                                grandchild.path,
                                                            ),
                                                        }"
                                                        @click="closeOnMobile"
                                                    >
                                                        {{ grandchild.title }}
                                                    </Link>
                                                </li>
                                            </ul>
                                        </template>
                                    </li>
                                </ul>
                            </template>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>
    </div>
</template>
