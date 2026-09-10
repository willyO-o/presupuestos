---
paths:
  - 'resources/js/Layouts/**/*.vue'
  - 'resources/js/Components/Layout/**'
---

# Layouts

## Piezas del layout van en Components/Layout/, no inline en Layouts/*.vue
`MainDashboardLayout.vue` es solo el esqueleto (`app-shell` + slots). El sidebar, el topbar y el footer viven como componentes propios en `resources/js/Components/Layout/`:

- `Sidebar.vue` — recibe `isOpen` (prop) y emite `close`; el acordeon de submenus es estado interno. Root envuelto en `<div class="contents">` porque tiene overlay + `<aside>` como hermanos y el proyecto exige un unico elemento raiz por componente.
- `Topbar.vue` — emite `toggle-sidebar`; el modo oscuro (localStorage + `prefers-color-scheme`) vive dentro de este componente, no en el layout.
- `Footer.vue` — pie de pagina con derechos reservados (`.app-footer` en `app.css`).

Si agregas una pieza nueva de layout (otro sidebar, un footer alterno, una barra de breadcrumbs propia, etc.), sigue el mismo patron: componente propio en `Components/Layout/`, props/emits explicitos para lo que el layout padre necesita coordinar, estado puramente visual (acordeones, tooltips) queda local al componente. No vuelvas a inflar `MainDashboardLayout.vue` con markup grande inline.

## Loader de cambio de página: por qué espera y por qué ignora las recargas parciales
`Components/Layout/PageLoader.vue` es el velo con 3 puntos saltando + "Cargando..." de los cambios de página. Se monta HERMANO de la app en `app.js` (`render: () => [h(App, props), h(PageLoader)]`), no dentro de un layout: así cubre también Auth y el portal del cliente, y no se remonta en cada navegación — que es justo cuando tiene que estar vivo.

Dos condiciones que NO se pueden quitar sin empeorar la UX:

1. **Espera 300 ms antes de aparecer.** Una navegación local tarda decenas de ms; sin la espera cada clic da un parpadeo de velo, peor que no tener loader. Mismo criterio que la barra de progreso de Inertia, que se conserva a propósito: la barra avisa "algo pasa" desde el instante 0 y el velo sale solo si se alarga.
2. **Ignora las recargas parciales** (`event.detail.visit.only?.length`). Son las de `useServerTable` (buscar/filtrar en un listado), que YA tienen su propio loader dentro de la tabla (`.table-loading-*`, app.css §17). Taparlas con un velo escondería justo lo que se está mirando.

**ACOPLAMIENTO**: el punto 2 depende de que cada `useServerTable` pase `only`. Hoy lo hacen los 20 índices; un Index nuevo que lo omita se comería el velo en cada búsqueda. Si algún día se quiere blindar, la señal alternativa es `preserveState`.

CSS en app.css §30 (`.page-loader-*`). NO confundir con §17: aquellos puntos PULSAN dentro de una tabla, estos SALTAN sobre toda la pantalla. Respeta `prefers-reduced-motion` (se queda el velo y el texto, sin salto).

API verificada contra `@inertiajs/core` 2.x instalado: `start`/`finish` traen `detail.visit`, `Visit.only` es `Array<string>` y `router.on()` devuelve la función para desuscribirse (se usa en `onUnmounted`).
