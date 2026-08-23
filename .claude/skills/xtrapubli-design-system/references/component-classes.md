# Catálogo de clases estilo Bootstrap (construidas con Tailwind)

Todas viven en [`resources/css/app.css`](../../../../resources/css/app.css) como clases
normales (`.btn`, `.card`, ...) definidas con `@apply` sobre utilidades Tailwind + las
variables de color de [`color-palette.md`](color-palette.md). Se usan igual que en Bootstrap:
combinando una clase base con un modificador (`btn` + `btn-primary`).

Colores disponibles en todos los grupos "por color": **primary, secondary, success, info,
warning, danger, dark** (más `light` y `pink` donde aplica).

## Botones (`.btn`)

```html
<button class="btn btn-primary">Guardar</button>
<button class="btn btn-outline-danger">Eliminar</button>
<button class="btn btn-soft-info btn-sm">Ver detalle</button>
<button class="btn btn-icon btn-soft-secondary"><svg .../></button>
```

- Base: `.btn` (+ tamaño opcional `.btn-sm` / `.btn-lg`, o `.btn-icon` para botón cuadrado
  solo-ícono de 32px).
- Sólido: `.btn-primary` `.btn-secondary` `.btn-success` `.btn-info` `.btn-warning`
  `.btn-danger` `.btn-dark` — fondo de color, texto blanco.
- Tenue: `.btn-soft-primary` ... `.btn-soft-dark` — fondo pastel, texto del color.
- Outline: `.btn-outline-primary` ... `.btn-outline-dark` — borde + texto del color, fondo
  transparente que se rellena en hover.
- `.btn-light` — caso especial ya existente (fondo `--page-bg`), útil sobre superficies
  oscuras/sidebar.

## Fondos (`.bg-*`)

Igual que `bg-primary` / `bg-soft-primary` / `bg-outline-primary` de Bootstrap 5.

```html
<div class="bg-soft-success p-3 rounded">Aprobado</div>
<span class="bg-outline-primary rounded-pill px-3 py-1">Nuevo</span>
```

- Sólido: `.bg-primary` ... `.bg-dark`, más `.bg-light` y `.bg-white`.
- Tenue: `.bg-soft-primary` ... `.bg-soft-dark`.
- Outline: `.bg-outline-primary` ... `.bg-outline-dark`.

## Badges (`.badge`)

```html
<span class="badge badge-primary">Activo</span>
<span class="badge badge-soft-warning badge-pill">Pendiente</span>
```

- Base: `.badge` (+ `.badge-pill` para 100% redondeado).
- Sólido: `.badge-primary` ... `.badge-dark`, `.badge-pink`.
- Tenue: `.badge-soft-primary` ... `.badge-soft-dark`, `.badge-soft-pink`.

## Texto (`.text-*`)

`.text-primary` `.text-secondary` `.text-success` `.text-info` `.text-warning`
`.text-danger` `.text-dark` `.text-light` `.text-pink` `.text-white`, más los ya
existentes `.text-muted` y `.text-heading` (usan tokens neutros, no de color).

## Cards

```html
<div class="card">
  <img src="..." class="card-img-top" />
  <div class="card-header">
    <span class="card-title">Título</span>
    <a href="#" class="card-link">Ver todo</a>
  </div>
  <div class="card-body">...</div>
</div>
```

`.card` `.card-header` `.card-body` `.card-title` `.card-subtitle` `.card-header-action`
`.card-link` `.card-img-top` (imagen de portada, esquinas superiores redondeadas) y
`.card-img-overlay` (overlay con degradado oscuro para texto sobre imagen, tipo cover de
proyecto en una galería).

## Avatares

`.avatar` + tamaño: `.avatar-xs` (32px) `.avatar-sm` (40px) `.avatar-md` (56px)
`.avatar-lg` (80px) `.avatar-xl` (96px). Iniciales dentro: `.avatar-title` (usa
`primary-soft` por defecto) o variante de color `.avatar-title-secondary`
`.avatar-title-success` `.avatar-title-info` `.avatar-title-warning`
`.avatar-title-danger` `.avatar-title-dark`.

```html
<span class="avatar avatar-sm">
  <span class="avatar-title avatar-title-success">JP</span>
</span>
```

## Imágenes

- `.img-fluid` — equivalente exacto a Bootstrap: `max-width: 100%; height: auto;`. Úsalo en
  cualquier imagen de contenido (galería de proyectos, foto de perfil, thumbnail) para que
  nunca desborde su contenedor.
- `.img-thumbnail` — imagen con borde, padding pequeño y esquinas redondeadas (estilo marco).
- `.article-thumb` — miniatura cuadrada de 40px ya usada en tablas (`table-dashboard`).

## Otros grupos ya existentes (sin cambios en esta iteración)

- **Layout**: `.app-shell` `.sidebar` `.topbar` `.main-wrapper` `.page-content`
  `.page-title-box` `.breadcrumb`.
- **Stat cards**: `.stat-card` `.stat-icon` + `.stat-icon-{color}`.
- **List group**: `.list-group` `.list-group-item` `.comment-list` `.comment-item`.
- **Tabla**: `.table-responsive` `.table-dashboard`.
- **Donut/bar chart CSS puro**: `.donut-chart` `.bar-chart`.
- **Grid 12 columnas**: `.row` `.col-12` `.col-lg-3` ... `.col-lg-9`.
- **Utilidades**: `.d-flex` `.d-grid` `.flex-column` `.align-items-center`
  `.justify-content-between` `.gap-1..4` `.w-100` `.h-100` `.rounded` `.rounded-circle`
  `.rounded-pill` `.text-truncate` `.text-uppercase` `.fw-normal..bold` `.fs-xs..xl`
  `.position-relative` `.position-absolute` `.mb-0..5` `.mt-0..5` `.mx-auto`.

## Login (`.login-*`)

Clases especificas de la pantalla de acceso (`resources/js/Pages/Auth/Login.vue`), panel
partido con blob de marca:

```html
<div class="login-brand-blob bg-primary ..."></div>
<img src="/img/logo/logo-blanco.png" class="login-logo-3d w-96 max-w-full" />

<div class="login-input-group">
  <i class="fa-solid fa-user login-input-icon"></i>
  <input class="login-input" placeholder="Usuario" />
</div>
```

- `.login-brand-blob` — recorta el panel de marca en un óvalo (`border-radius: 50%`).
- `.login-logo-3d` — leve giro 3D (`perspective` + `rotateY`/`rotateX` de solo unos grados) más
  `drop-shadow`, para que el logo "mire" hacia el centro de la pantalla sin perder legibilidad.
- `.login-input-group` / `.login-input-icon` / `.login-input` / `.login-input-eye` — input tipo
  subrayado con ícono a la izquierda y botón opcional a la derecha (mostrar/ocultar contraseña),
  usando `--text-muted` / `--border-subtle` / `--c-primary` en vez de colores nuevos.

## Al agregar una clase nueva

Sigue el patrón de color de tres variables (`--c-{nombre}` / `-dark` / `-soft`) y añade la
clase al grupo correspondiente (`btn-`, `bg-`, `badge-`, `text-`, `avatar-title-`) en vez de
crear una convención nueva. Actualiza esta tabla cuando lo hagas.
