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

Two things this skill exists to protect:

1. **One source of truth for brand color** — [`references/color-palette.md`](references/color-palette.md).
2. **One catalog of reusable classes** — [`references/component-classes.md`](references/component-classes.md) —
   so nobody re-invents `.btn-primary` or hand-rolls a new shade of blue in a `<div style>`.

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

## Rule 3 — Verifying a change

Run `npm run build` (or confirm `npm run dev` / `composer run dev` is running) after editing
`app.css` — per the project's frontend-bundling rule, CSS changes are invisible until the
assets are rebuilt.

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
