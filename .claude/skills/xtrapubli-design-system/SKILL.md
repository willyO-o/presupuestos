---
name: xtrapubli-design-system
description: "Apply whenever doing UI/frontend work in this app: choosing colors, building or styling buttons, cards, badges, avatars, backgrounds, alerts or images, creating new Vue/Blade components, or when the user mentions XtraPubli, the brand, the logo, or 'paleta de colores'. Provides company context, the brand color palette (as CSS variables), and the full catalog of Bootstrap-named utility/component classes already built on top of Tailwind in resources/css/app.css. Read before inventing new colors or new component classes — almost everything needed already exists here. Skip for backend/PHP-only changes with no visual output."
license: MIT
metadata:
  author: project
---

# XtraPubli — Design System

This app is built for **XtraPubli** (xtrapubli.com), a point-of-sale (POP) branding
and retail-marketing company based in El Alto, Bolivia. Tagline: **"Hacemos que tu
Marca Venda!"**. They design, manufacture and install retail displays, banners,
roll-ups, stands, promotional islands, commercial signage and vehicle wrapping/branding
for consumer brands and agencies. Positioning: an execution partner ("ejecutamos ideas
que generan impacto, no solo producimos"), professional/corporate B2B tone, portfolio-driven.

Three things this skill exists to protect:

1. **One source of truth for brand color** — [`references/color-palette.md`](references/color-palette.md).
2. **One catalog of reusable classes** — [`references/component-classes.md`](references/component-classes.md) —
   so nobody re-invents `.btn-primary` or hand-rolls a new shade of blue in a `<div style>`.
3. **One stylesheet** — `resources/css/app.css` — so styling never fragments across dozens of
   per-component `<style>` blocks (see Rule 3).

## Relationship to other skills

- For generic Tailwind syntax, responsive layout, dark-mode variants, spacing rules → use
  `tailwindcss-development`. This skill does not repeat that guidance.
- This skill is specifically about **this project's brand identity** and its
  **Bootstrap-named component layer** (`.card`, `.btn-primary`, `.avatar-sm`, `.bg-soft-info`, ...),
  which is a project convention, not a Tailwind or Laravel Boost default. It did not exist
  anywhere else before this skill — check here first.
- For form inputs (numeric/decimal fields, character limits) or role/permission-gated UI, use the
  `vue-custom-directives` skill (`v-decimal`, `v-entero`, `v-max-length`, `v-can`) instead of writing
  manual `@input` handlers or `v-if` permission checks.

## Rule 1 — Never hardcode a brand color

All brand/semantic colors live as CSS custom properties in
[`resources/css/app.css`](../../../resources/css/app.css), defined once in `:root` (light)
and overridden in `.dark`. Never write a raw hex like `#1c7fc4` or `bg-[#1c7fc4]` in a
component. Use the existing `--c-*` variable (via an existing class, see Rule 2) or, if you
must reference it directly in a `<style>`/inline style, `var(--c-primary)` etc.

If XtraPubli ever rebrands, only 4 variables change — `--brand-blue`, `--brand-blue-light`,
`--brand-teal`, `--brand-dark` at the top of `:root` in `app.css` — and every button, badge,
avatar, background and card in the app updates automatically. Details and the full token
table: [`references/color-palette.md`](references/color-palette.md).

## Rule 2 — Reuse the existing component classes before writing new CSS/utilities

`resources/css/app.css` already implements a Bootstrap-style class layer on top of Tailwind
(`@apply` inside `@layer`/custom classes), covering cards, buttons, badges, backgrounds,
avatars, images, tables, list groups, a 12-col grid (`row`/`col-lg-*`), and Bootstrap-ish
utilities (`d-flex`, `fw-semibold`, `text-muted`, `mb-3`, ...). Before adding a new class or
falling back to a long string of raw Tailwind utilities for a common pattern:

1. Check [`references/component-classes.md`](references/component-classes.md) — it is very
   likely the class already exists (e.g. `btn-outline-danger`, `avatar-lg`, `bg-soft-warning`,
   `img-fluid`, `card-img-top`).
2. If it truly doesn't exist yet, add it to `app.css` **following the existing pattern**:
   for a new semantic color, add `--c-{name}`, `--c-{name}-dark`, `--c-{name}-soft` next to
   the others in `:root`/`.dark`, then add `.btn-{name}`, `.btn-soft-{name}`,
   `.btn-outline-{name}`, `.bg-{name}`, `.bg-soft-{name}`, `.badge-{name}`,
   `.badge-soft-{name}`, `.text-{name}` mirroring the existing color's block. Never introduce
   a parallel/second styling convention (e.g. don't switch to Tailwind's `theme.extend.colors`
   for this — the CSS-variable approach is the established convention here, see
   `laravel-best-practices` "Consistency First").
3. Update `references/component-classes.md` when you add a class so the catalog stays accurate.

## Rule 3 — CSS lives in `app.css`, never in a component's `<style>` block

When a Vue page or component needs styling that isn't already covered by a Tailwind utility
or an existing class (Rule 2), write it as a plain global class in
[`resources/css/app.css`](../../../resources/css/app.css) — not in a `<style scoped>` (or
unscoped) block inside the `.vue` file.

- Add it under the most relevant numbered section, or start a new one at the end (`16.`, `17.`,
  ...) with a short comment banner matching the existing style, e.g. `.login-*` under
  `16. LOGIN (pantalla de acceso)`.
- Prefix the class with the page/feature it belongs to (`.login-input`, `.login-brand-blob`)
  so it reads as scoped-by-name even though the CSS itself is global — this also makes it
  reusable the moment a second page needs the same look (see `AuthSplitLayout.vue` reusing
  `.login-*` across every `Pages/Auth/**` view).
- Use the existing `--c-*` / `--text-*` / `--border-subtle` tokens inside it (Rule 1) instead
  of new hardcoded values.
- Update [`references/component-classes.md`](references/component-classes.md) with the new
  class group, same as Rule 2.

Why: a single `app.css` is one file to search, one file to theme (light/dark), and one place
that survives a component being renamed or moved — a `<style scoped>` block silently
duplicates rules per-component and drifts from the design system the moment someone copies
the component instead of reusing the class. The only style attribute allowed inline is a
one-off `var(--c-primary)` reference where a class genuinely doesn't make sense (e.g.
`style="accent-color: var(--c-primary)"` on a native checkbox).

## Rule 4 — Verifying a change

Run `npm run build` (or confirm `npm run dev` / `composer run dev` is running) after editing
`app.css` — per the project's frontend-bundling rule, CSS changes are invisible until the
assets are rebuilt.

## Rule 5 — Building a new CRUD module (listado + alta/edición)

Follow this shape for any new resource (`Pages/{Recurso}/Index.vue` + backend), instead of
hand-rolling a `<table>` or a custom notification/confirmation flow. `Pages/Sucursales/Index.vue`
is the reference implementation — copy its structure for the next module.

**Listado**: always `resources/js/Components/Table/DataTable.vue` +
`resources/js/Composables/UseServerTable.js`, never a raw `<table>`. `DataTable` takes
`headers` (string HTML or `{ label, key, class, cellClass }` objects), renders rows from
`items`, and exposes `#cell-<key>` slots per column, an `#actions` slot for the buttons
column, a `#tbody` escape hatch for very custom layouts (rowspans, grids), a `table-class`
prop for modifiers like `table-sm`, and shows a default 3-dot loader (overridable with
`#loader`) while `loading` is true — see the component's own JSDoc for the full API and
[`references/component-classes.md`](references/component-classes.md) for the CSS classes
involved. `useServerTable({ url, filters, mode, only })` wires search/filter state, pagination
and the `router.get()` visit for you (`mode: 'manual'` = button-triggered, `'auto'` = debounced
on change) — see `.ai/rules/table.md` for the full contract, including `reset()`/`defaults`.

**Alta/edición — modal vs. vista independiente**: decide by field complexity, don't default
to always-a-modal:

- **Campos simples** (un puñado de inputs planos, sin sub-tablas ni relaciones que gestionar a
  la vez): reusa `resources/js/Components/Modal.vue` con un único formulario (`useForm`) que
  sirve tanto para crear como editar, alternando el título y el método (`post`/`put`) según si
  hay un registro en edición — exactamente como `Pages/Sucursales/Index.vue` (`showFormModal`,
  `editingSucursal`, `openCreate()`/`openEdit()`/`submitForm()`).
- **Información compleja** (múltiples tablas relacionadas, líneas de detalle repetibles, pasos,
  o cualquier formulario demasiado largo para un modal): usa una vista independiente en vez de
  forzarlo en un modal — `Pages/{Recurso}/Create.vue` y `Pages/{Recurso}/Edit.vue` navegando por
  rutas propias (`{recurso}.create` / `{recurso}.edit`), pero **reutilizando el mismo
  formulario**: extrae los campos compartidos a un componente parcial (ej.
  `Pages/{Recurso}/Partials/{Recurso}Form.vue`) que ambas vistas montan, en vez de duplicar el
  markup entre Create y Edit.

**Notificaciones y confirmaciones**: siempre `resources/js/Utils/AlertUtil.js`
(`showToast`, `showError`, `confirmation`), nunca un banner o `<Modal>` de confirmación a
medida.

- Los toasts de éxito/error tras crear/editar/eliminar **no se llaman a mano** — hay un listener
  global (`resources/js/Composables/UseFlashNotifications.js`, conectado una sola vez en
  `app.js`) que escucha el evento `success` de Inertia y muestra `showToast()` con lo que el
  controlador mande vía `redirect(...)->with('success', '...')` / `->with('error', ...)` (prop
  compartida `flash`, ver `HandleInertiaRequests::share()`). Un módulo nuevo no necesita
  `onSuccess` en el form para esto — alcanza con el `->with(...)` del backend.
- Para confirmar una acción destructiva (borrar, cancelar, etc.), usa `await confirmation(...)`
  antes de disparar la petición — no un `<Modal>` con botones "Sí/No" a medida.
- Los popups ya están temados a la marca (`app.css` sección "20. SWEETALERT2") — no pases
  colores a mano al llamar `Swal`/`AlertUtil`.

## Known state / pending items

- `resources/js/Layouts/MainDashboardLayout.vue` still has placeholder demo content inherited
  from the admin-template starting point (brand text `FUELPRO`, and demo menu items like
  Crypto/NFT/Job that are not real app features). This is pre-existing, not something to
  "fix" silently — flag it to the user if asked to touch that file, rather than assuming
  which text is correct.
- The real logo files live in `public/img/logo/`: `logo.webp` (full horizontal wordmark, used in
  the sidebar brand via `.sidebar-brand-logo`) and `logo-mini.png` (circular mark, used as the
  favicon in `resources/views/app.blade.php`). `logo.webp` has a transparent background and
  includes the word "Xtra" in black — never place it directly on a dark surface (like
  `--sidebar-bg`) without a light backing (see `.sidebar-brand-logo`'s white chip), or "Xtra"
  becomes unreadable. Reference these paths (`/img/logo/logo.webp`, `/img/logo/logo-mini.png`)
  instead of re-asking the user for the asset.
