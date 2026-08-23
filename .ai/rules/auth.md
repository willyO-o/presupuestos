---
paths:
  - 'resources/js/Pages/Auth/**'
---

# Auth

## Auth pages con diseño "split" usan AuthSplitLayout, no GuestLayout
`resources/js/Layouts/AuthSplitLayout.vue` es el layout de marca (panel azul con blob + logo 3D a la izquierda, avatar circular + heading + formulario a la derecha) usado por `Login.vue` y `ResetPassword.vue`. Recibe una prop `title` (heading, ej. "BIENVENIDO") y expone slot `#status` (mensajes flash) + slot por defecto (el `<form>`). Los inputs dentro del form usan las clases globales `.login-input-group` / `.login-input-icon` / `.login-input` / `.login-input-eye` (definidas en `app.css`, sección "16. LOGIN"), no `TextInput`/`InputLabel`/`PrimaryButton` de Breeze.

Si migras otra página de `Pages/Auth/**` (Register, ForgotPassword, ConfirmPassword, VerifyEmail) a este mismo diseño, reutiliza `AuthSplitLayout` en vez de duplicar el blob/logo/avatar. Las páginas de Auth que NO se migren se quedan en `GuestLayout` (Breeze) — es válido que convivan ambos estilos hasta que se decida migrar el resto.

## Auth pages con diseño "split" usan AuthSplitLayout, no GuestLayout
Toda `resources/js/Pages/Auth/**` (Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail) usa `resources/js/Layouts/AuthSplitLayout.vue`: panel azul con blob + logo 3D a la izquierda, avatar circular + heading + formulario a la derecha. Recibe prop `title` (heading, ej. "BIENVENIDO", "CREAR CUENTA") y expone slot `#status` (mensajes flash/success) + slot por defecto (contenido: párrafo descriptivo opcional + `<form>`).

Los inputs usan las clases globales `.login-input-group` / `.login-input-icon` / `.login-input` / `.login-input-eye` (definidas en `app.css`, sección "16. LOGIN"), con ícono Font Awesome a la izquierda (`fa-user`, `fa-envelope`, `fa-lock`) y, en campos de password, un botón mostrar/ocultar (`fa-eye`/`fa-eye-slash`) vía un `ref` local — no `TextInput`/`InputLabel`/`PrimaryButton` de Breeze. El botón de envío es `btn btn-primary rounded-full w-100 uppercase`. Links secundarios (volver a login, cerrar sesión) usan `text-xs text-muted italic hover:text-primary`, centrados.

`GuestLayout.vue` quedó sin uso tras esta migración — no se borró por si se necesita para alguna vista futura fuera de `Pages/Auth/**`, pero cualquier página nueva de Auth debe usar `AuthSplitLayout`, no `GuestLayout`.
