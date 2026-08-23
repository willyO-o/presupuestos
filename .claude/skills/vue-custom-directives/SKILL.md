---
name: vue-custom-directives
description: "Apply whenever building a Vue form input in this app (text, number, decimal/money, quantity fields) or showing/hiding UI based on the logged-in user's role or permission. This project already has 4 custom global directives in resources/js/Directives (v-can, v-decimal, v-entero, v-max-length), registered once in resources/js/app.js. Reuse them instead of writing manual @input handlers, regex sanitizers, or v-if permission checks by hand. Skip for backend validation (Form Requests) — these directives are UI-only, not a substitute for server-side rules."
license: MIT
metadata:
  author: project
---

# Directivas Vue personalizadas

Cuatro directivas globales ya registradas en [`resources/js/app.js`](../../../resources/js/app.js)
(`.directive('can', can)`, etc.) y definidas en [`resources/js/Directives/`](../../../resources/js/Directives/).
No hay que importarlas en cada componente: cualquier `.vue` puede usarlas directo en el template.

## `v-can` — visibilidad por rol/permiso

Archivo: [`Directives/Can.js`](../../../resources/js/Directives/Can.js). Oculta (`element.hidden`) un
elemento según los permisos que Inertia comparte en `page.props.auth` (vienen de
`spatie/laravel-permission` en el backend — para la parte de roles/permisos en PHP, políticas,
migraciones de roles, etc. usa la skill `laravel-permission-development`, esta es solo la mitad
frontend).

```html
<button v-can="'vales.crear'" class="btn btn-primary">Nuevo vale</button>

<!-- Requiere cualquiera de la lista (OR) -->
<a v-can="['vales.editar', 'vales.aprobar']" href="#">Gestionar</a>

<!-- Requiere todos los permisos listados (AND) -->
<a v-can.all="['vales.editar', 'vales.aprobar']" href="#">Aprobar edicion</a>
```

- El rol `super-admin` (via `auth.roles` o `auth.is_super_admin`) siempre pasa, sin declarar permisos.
- Un valor vacío/inválido **nunca** concede acceso (falla cerrado).
- Es solo UI: la ruta/acción real en el backend debe seguir protegida con middleware o Policy —
  esta directiva no reemplaza esa protección, solo evita mostrar botones que el usuario no puede usar.

## `v-decimal` — campos numéricos con decimales (montos, litros, etc.)

Archivo: [`Directives/Decimal.js`](../../../resources/js/Directives/Decimal.js). Registrada como
`decimal`. Sanea el input en tiempo real: solo dígitos y un punto decimal, bloquea `-`, `+`, `e`
(sin negativos ni notación científica), convierte comas a punto, limita la cantidad de decimales, y
también sanea lo que se pega (paste).

```html
<!-- 2 decimales por defecto -->
<input v-decimal type="text" v-model="form.monto" />

<!-- Cantidad de decimales configurable y reactiva -->
<input v-decimal="3" type="text" v-model="form.litros" />
```

Usa `type="text"` (no `type="number"`) para que el sanitizado por regex tenga control total del
valor tecleado.

## `v-entero` — campos de enteros positivos con mínimo

Archivo: [`Directives/Entero.js`](../../../resources/js/Directives/Entero.js). Registrada como
`entero`. Solo dígitos (sin signo, sin punto), agrega `inputmode="numeric"` para teclado numérico en
móvil, y al perder foco (`blur`) sube el valor al mínimo si quedó por debajo.

```html
<!-- minimo 0 (por defecto) -->
<input v-entero type="text" v-model="form.cantidad" />

<!-- minimo 1, por ejemplo para que no se pueda pedir "0 unidades" -->
<input v-entero="1" type="text" v-model="form.unidades" />
```

## `v-max-length` — límite de caracteres con corte en vivo

Archivo: [`Directives/MaxLength.js`](../../../resources/js/Directives/MaxLength.js). Registrada como
`max-length`. Corta el texto (tecleado y pegado) al límite indicado, mantiene la posición del cursor,
y además setea el atributo nativo `maxlength` (para el contador/validación del propio navegador).

```html
<!-- 255 por defecto -->
<textarea v-max-length="500" v-model="form.observaciones"></textarea>
```

## Reglas generales

- Estas 4 directivas son **restricciones de entrada** (sanitizado en el DOM), no validación. Para el
  mensaje de error / regla real, sigue usando Form Requests en Laravel (`laravel-best-practices` →
  `rules/validation.md`) y, si hace falta feedback inmediato en el form de Inertia, `useForm().errors`.
- Si necesitas una restricción de input nueva y recurrente (por ejemplo, solo letras, un formato de
  teléfono, etc.), agrega una directiva nueva en `resources/js/Directives/`, regístrala en `app.js`
  igual que las demás, y documéntala aquí — no repitas `@input`/regex sueltos en cada formulario.
