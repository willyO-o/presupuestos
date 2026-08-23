---
name: xtrapubli-ui
description: Sistema de diseño del panel administrativo de XtraPubli (costos y presupuestos para exhibidores, material POP, letreros y rotulado). Úsalo SIEMPRE que se cree o modifique cualquier vista, componente Vue, layout, formulario, tabla, dashboard, card, badge, avatar o estilo dentro de este proyecto Laravel + Inertia + Vue 3 — incluso si el usuario no menciona explícitamente "diseño" o "estilos". Cubre paleta de colores de marca, la librería completa de clases estilo Bootstrap (card, btn, badge, avatar, img-fluid, placeholder, etc.) recreada en Tailwind puro, convenciones de Tailwind + Bootstrap grid, componentes base reutilizables y patrones de UI para módulos de cotizaciones, presupuestos, materiales, órdenes de producción y roles (Spatie Permission).
---

# XtraPubli · Sistema de Diseño del Panel Admin

Panel administrativo interno para gestión de costos y presupuestos de
XtraPubli (fabricante de exhibidores, material POP, letreros luminosos y
rotulado para retail). **Referencia visual: tipo dashboard admin moderno
(estilo Velzon)** — cards con sombra suave y esquinas redondeadas
moderadas, colores "soft" (fondo pastel + texto del mismo tono) para
badges y estados, avatares con iniciales cuando no hay foto, sidebar
oscuro con acentos del color primario, widgets de KPI con ícono + valor +
variación. Tono formal y corporativo — es una herramienta de gestión
financiera interna, no un producto de consumo, así que evita excesos de
color o decoración.

Stack: Laravel + Inertia.js + Vue 3 (Composition API + `<script setup>`) +
Spatie Laravel-Permission + Tailwind CSS v4 + grid de Bootstrap 5 (solo
layout). Laravel Boost ya provee contexto del backend/Eloquent; este skill
cubre exclusivamente la capa de UI.

## Regla de oro: usa las clases semánticas, no utilidades sueltas

**Nunca** construyas un botón, card, badge, avatar o input acumulando
utilidades de Tailwind sueltas en la plantilla. Cada patrón visual ya
existe como clase semántica en `resources/css/app.css`, con nombres
familiares de Bootstrap pero implementados 100% en Tailwind (sin cargar el
CSS de Bootstrap). Tailwind "crudo" se reserva solo para spacing/posición
puntual de una vista específica (`flex`, `gap-4`, `mt-6`, `w-full md:w-auto`).

```
<!-- ❌ Evitar -->
<span class="inline-flex items-center gap-1 rounded-full bg-green-100
  px-2.5 py-0.5 text-xs font-medium text-green-700">Aprobado</span>

<!-- ✅ Preferido -->
<span class="badge-soft-success">Aprobado</span>
```

## Nunca hardcodear colores

Todo color de marca es una CSS variable en el bloque `@theme` de
`resources/css/app.css`. Usa las utilidades que Tailwind genera de ahí
(`bg-primary-500`, `text-accent-600`, `border-neutral-300`) o las clases
semánticas. Cambiar la paleta completa = editar un solo archivo.

| Token | Uso | Hex base |
|---|---|---|
| `primary-500` | Acciones primarias, links, nav activo, avatar-title | `#006db1` |
| `accent-500` | Highlights, gráficas, estado "info" | `#0e96d1` |
| `ink-950` | Sidebar oscuro, texto alto contraste | `#0b0e12` |
| `neutral-*` | Fondos, bordes, texto secundario | escala gris fría |
| `success/warning/danger-*` | Estados de negocio | ver `app.css` |

## Catálogo de clases disponibles (`resources/css/app.css`)

Todas responsivas por herencia de Tailwind: cualquier clase de esta lista
acepta prefijos `sm: md: lg: xl:` en la vista (ej.
`class="avatar-sm md:avatar-md"`, `class="w-full md:w-auto btn-primary"`).

- **Botones**: `.btn-{primary|secondary|success|danger|warning|info|light|dark|link}`,
  variantes `.btn-soft-{primary|success|danger|warning|info}` (fondo pastel,
  úsalas para acciones secundarias en tablas/cards),
  `.btn-outline-{primary|secondary|danger}`, tamaños `.btn-sm` / `.btn-lg`,
  `.btn-icon` (botón cuadrado solo-ícono), `.btn-group`.
- **Badges**: `.badge-{color}` (sólido) y `.badge-soft-{color}` (pastel —
  úsalas para estados de cotización/orden), `.badge-pill-dot` (punto de
  estado antes del texto).
- **Avatares**: `.avatar-{xs|sm|md|lg|xl}` + `.avatar-title` (iniciales
  cuando no hay foto — ej. cliente sin logo), `.avatar-img` (con foto),
  `.avatar-square` (variante esquinas redondeadas, para logos de
  proveedores/materiales), `.avatar-status-{online|offline}`,
  `.avatar-group` (pila superpuesta, ej. equipo asignado a un proyecto).
- **Imágenes**: `.img-fluid`, `.img-thumbnail`, `.img-circle`.
- **Cards**: `.card` + `.card-header` / `.card-title` / `.card-subtitle` /
  `.card-body` / `.card-footer` / `.card-img-top` / `.card-text`.
  `.stat-card` + `.stat-card-icon` / `.stat-card-label` / `.stat-card-value`
  / `.stat-card-delta-up` / `.stat-card-delta-down` para KPIs de dashboard.
- **Alertas**: `.alert-{primary|success|warning|danger|info}`.
- **Progreso**: `.progress` (+ `.progress-sm` / `.progress-lg`) con
  `.progress-bar` (+ `.progress-bar-{success|warning|danger}`) dentro.
- **Placeholders (skeleton loaders)**: `.placeholder` dentro de un
  contenedor `.placeholder-glow` para el efecto de pulso; tamaños
  `.placeholder-lg` / `.placeholder-sm` / `.placeholder-xs`.
- **Formularios**: `.form-label`, `.form-input` (+ `.form-input-error`),
  `.form-select`, `.form-hint`, `.form-error`, `.form-check` +
  `.form-check-input` / `.form-check-label`.
- **Tablas**: `.table-app` (+ `.table-app-striped`).
- **Listas**: `.list-group` + `.list-group-item` / `.list-group-item-action`.
- **Tabs**: `.nav-tabs` + `.nav-link` / `.nav-link-active`, o
  `.nav-pills` con la misma estructura.
- **Dropdown**: `.dropdown-menu` + `.dropdown-item` / `.dropdown-divider`.
- **Breadcrumb**: `.breadcrumb` + `.breadcrumb-item-active`.
- **Ribbon**: `.ribbon` (+ `.ribbon-danger` / `.ribbon-success`) — etiqueta
  de esquina sobre una card, requiere que el padre tenga `class="relative"`.
- **Layout de app**: `.app-sidebar`, `.app-sidebar-brand`, `.app-nav-link`
  (+ `.app-nav-link-active`), `.app-topbar`.

Antes de escribir un patrón que "se ve como" algo de esta lista, revisa
primero si ya existe la clase — no la reinventes con utilidades sueltas.

## Tailwind + Bootstrap: quién hace qué

No se mezclan al mismo nivel. División de responsabilidades:
- **Bootstrap** (`resources/scss/bootstrap-grid.scss`, SOLO el módulo
  grid): ÚNICAMENTE para estructurar layouts de página con
  `.container` / `.row` / `.col-md-6` etc.
- **Tailwind + `app.css`**: TODO lo visual — color, tipografía, componentes
  (botones, cards, badges, avatares, tablas, formularios, placeholders...).

Nunca importar `bootstrap.css`/`bootstrap.scss` completo — solo el grid (su
reboot chocaría con el preflight de Tailwind). Ver comentarios en
`bootstrap-grid.scss`.

```vue
<div class="container">
  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Cotización #0231</h3>
          <span class="badge-soft-info">Enviado</span>
        </div>
        <div class="card-body">
          <label class="form-label">Cliente</label>
          <input class="form-input" v-model="form.cliente" />
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-money-dollar-circle-line"></i></div>
        <div>
          <p class="stat-card-label">Total presupuestado</p>
          <p class="stat-card-value">$12,450</p>
        </div>
      </div>
    </div>
  </div>
</div>
```

## Componentes Vue base

Antes de escribir HTML plano para un patrón común, revisa
`resources/js/Components/Base/`:
- `BaseButton.vue` — props `variant` (primary/secondary/danger/ghost),
  `size` (sm/md/lg).
- `BaseCard.vue` — slot default + slot `actions` en el header.
- `StatusBadge.vue` — recibe `status` (borrador/enviado/aprobado/
  rechazado/en_produccion/pagado), resuelve label + `.badge-soft-*`
  automáticamente.

Y en `resources/js/Components/Examples/QuoteCardExample.vue` un ejemplo
completo combinando avatar con iniciales + badge de estado, como
referencia de cómo se ven estas clases combinadas en un componente real.

Si necesitas un componente que no existe (`BaseTable`, `BaseSelect`,
`BaseModal`, `BasePlaceholder`), créalo en `Base/` siguiendo el mismo
patrón, usando siempre las clases semánticas de `app.css` por dentro.

## Iconos

Para un look consistente con el estilo de dashboard admin de referencia,
usa un set de iconos completo y libre — **Remix Icon** (`ri-*`, licencia
Apache 2.0, gratuito) es una buena opción y es el más común en este tipo de
temas. Instálalo vía `remixicon` (npm) y usa `<i class="ri-nombre-icon">`.
No es obligatorio, pero mantén un solo set de iconos en todo el panel.

## Layout general del panel

- `.app-sidebar` (fondo `ink-950`) + `.app-nav-link` / `.app-nav-link-active`
  para la navegación lateral. Genera el menú dinámicamente según los
  permisos de Spatie del usuario (`$page.props.auth.permissions` vía
  Inertia shared props) — no muestres enlaces a módulos sin permiso.
- `.app-topbar` para la barra superior (breadcrumb, usuario, notificaciones).
- Dashboard con KPIs de costos/presupuestos: usar `.stat-card` con
  `.stat-card-icon` a la izquierda, `.stat-card-label` / `.stat-card-value`
  / `.stat-card-delta-up|down` a la derecha (patrón "icon + número" típico
  de dashboards admin).
- Estados de carga: usa `.placeholder-glow` + `.placeholder` mientras
  cargan datos vía Inertia (en vez de un spinner genérico) para listados y
  cards, siguiendo el patrón skeleton-loader del tema de referencia.

## Módulos de negocio (para nombrar componentes/rutas de forma consistente)

- **Catálogo de materiales/insumos** — costos base (MDF, acrílico, vinil,
  luces LED, estructura metálica, etc.), con historial de precios.
- **Cotizaciones / Presupuestos** — flujo de estados: borrador → enviado →
  aprobado/rechazado. Cada ítem referencia materiales del catálogo + mano
  de obra + margen. Usa `StatusBadge.vue` / `.badge-soft-*` para el estado.
- **Órdenes de producción** — se generan al aprobar una cotización; estado
  `en_produccion` → `completado`.
- **Reportes de costos** — comparativo presupuestado vs. real, márgenes por
  proyecto/cliente. Usa `.progress-bar` para mostrar % de avance/consumo.
- **Roles y permisos (Spatie)** — roles típicos: `admin`, `ventas`,
  `produccion`, `finanzas`. La UI debe ocultar/deshabilitar acciones según
  `can()` / permisos compartidos vía Inertia, no solo por rol hardcodeado.

## Archivos de referencia en este proyecto

- `resources/css/app.css` — fuente de verdad de tokens y clases semánticas.
- `resources/scss/bootstrap-grid.scss` — grid de Bootstrap, solo layout.
- `resources/js/Components/Base/` — componentes Vue reutilizables.
- `resources/js/Components/Examples/QuoteCardExample.vue` — ejemplo de uso combinado.
- `.ai/guidelines/xtrapubli-ui.md` — versión corta "siempre activa" de las
  reglas no-negociables (Boost la carga en cada sesión).
