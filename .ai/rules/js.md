---
paths:
  - 'resources/js/**/*.vue'
---

# Js

## Iconos: librerías de icon-fonts gratuitas vs. SVG a mano en tarjetas
Instaladas y ya importadas en `resources/css/app.css` (bundle vía Vite, sin CDN):

```css
@import '@fortawesome/fontawesome-free/css/all.min.css'; /* Font Awesome Free */
@import 'remixicon/fonts/remixicon.css';                  /* Remix Icon */
@import '@mdi/font/css/materialdesignicons.min.css';      /* Material Design Icons (MDI) */
```

Para iconografía de interfaz (botones, sidebar, menús, alerts, badges, inputs) usa clases de cualquiera de estas tres librerías gratuitas, la que ya tenga el icono que necesitas — no reinventar SVG para esto:
- Font Awesome: `<i class="fa-solid fa-truck-fast"></i>`
- Remix Icon: `<i class="ri-dashboard-line"></i>` (sufijo `-line` u `-fill`)
- Material Design Icons: `<span class="mdi mdi-fuel"></span>`

No mezcles las tres dentro del mismo componente sin necesidad; mantené consistencia dentro de una misma página/sección. Actualiza siempre a `npm install <pkg>@latest` en vez de fijar versiones viejas manualmente.

Excepción — no reemplazar por icon-fonts: los iconos dentro de tarjetas/widgets del dashboard (`.stat-icon`, `.list-icon`, gráficos como `.donut-chart`/`.bar-chart` y cualquier ilustración de card) se quedan como SVG inline hecho a mano (ver `resources/js/Pages/Dashboard.vue`), porque usan `stroke="currentColor"`/`fill="currentColor"` para heredar el color semántico (`--c-primary`, etc.) de forma nítida a cualquier tamaño, cosa que un icon-font no logra igual de bien en esos tamaños grandes de tarjeta.
