---
paths:
  - 'resources/js/Layouts/**/*.vue'
---

# Layouts

## Piezas del layout van en Components/Layout/, no inline en Layouts/*.vue
`MainDashboardLayout.vue` es solo el esqueleto (`app-shell` + slots). El sidebar, el topbar y el footer viven como componentes propios en `resources/js/Components/Layout/`:

- `Sidebar.vue` — recibe `isOpen` (prop) y emite `close`; el acordeon de submenus es estado interno. Root envuelto en `<div class="contents">` porque tiene overlay + `<aside>` como hermanos y el proyecto exige un unico elemento raiz por componente.
- `Topbar.vue` — emite `toggle-sidebar`; el modo oscuro (localStorage + `prefers-color-scheme`) vive dentro de este componente, no en el layout.
- `Footer.vue` — pie de pagina con derechos reservados (`.app-footer` en `app.css`).

Si agregas una pieza nueva de layout (otro sidebar, un footer alterno, una barra de breadcrumbs propia, etc.), sigue el mismo patron: componente propio en `Components/Layout/`, props/emits explicitos para lo que el layout padre necesita coordinar, estado puramente visual (acordeones, tooltips) queda local al componente. No vuelvas a inflar `MainDashboardLayout.vue` con markup grande inline.
