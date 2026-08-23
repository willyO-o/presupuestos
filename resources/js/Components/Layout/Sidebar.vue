<script setup>
import { computed, reactive } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { NAV_MENU } from '@/Data/Sidebar/Nav';

/**
 * Sidebar del dashboard. El menu (items, permisos, iconos) vive en
 * resources/js/Data/Sidebar/Nav.js — este componente solo sabe renderizarlo,
 * hasta 3 niveles de profundidad (item -> children -> children.children).
 *
 * El estado de apertura movil llega del layout (el boton esta en el Topbar);
 * que submenus estan expandidos es un detalle interno de este componente.
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
   secciones, para poder renderizar un <ul> por cada bloque con su titulo. */
const sections = computed(() => {
    const result = [];
    let current = null;

    for (const entry of NAV_MENU) {
        if (entry.menutitle) {
            current = { label: entry.menutitle, permission: entry.permission, items: [] };
            result.push(current);
        } else if (current) {
            current.items.push(entry);
        }
    }

    return result;
});

/* Acordeon de submenus: guarda el titulo de cada item tipo 'sub' que esta
   expandido (a cualquier nivel). Se usa el titulo como key porque debe ser
   unico dentro del menu. */
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

/* Al cargar, expande automaticamente los submenus que contienen la ruta
   actual, para que el usuario vea donde esta parado. */
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

        <aside class="sidebar" :class="{ 'is-open': isOpen }">
            <div class="sidebar-brand">
                <Link :href="route('dashboard')" class="sidebar-brand-logo">
                    <img src="/img/logo/logo-blanco.png" alt="XtraPubli" />
                </Link>
            </div>

            <div class="sidebar-body">
                <template v-for="section in sections" :key="section.label">
                    <p v-can="section.permission" class="sidebar-menu-label">
                        {{ section.label }}
                    </p>

                    <ul class="nav-sidebar">
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
                                @click="closeOnMobile"
                            >
                                <i
                                    v-if="item.icon"
                                    :class="item.icon"
                                    class="nav-icon-i"
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
                                    <i
                                        v-if="item.icon"
                                        :class="item.icon"
                                        class="nav-icon-i"
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
                </template>
            </div>
        </aside>
    </div>
</template>
