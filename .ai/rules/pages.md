---
paths:
  - 'resources/js/Pages/**/*.vue'
---

# Pages

## Pages usan MainDashboardLayout como layout persistente (Inertia v2)
Toda página bajo `resources/js/Pages/**` que se renderiza para un usuario autenticado (dashboard y cualquier área de administración/backoffice) debe declarar su layout con el patrón persistente de Inertia v2, no envolviendo el template en un componente:

```js
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
defineOptions({ layout: MainDashboardLayout });
```

Ver `resources/js/Pages/Dashboard.vue` como referencia. No uses `<AuthenticatedLayout>...</AuthenticatedLayout>` (Breeze legacy) para páginas nuevas — `AuthenticatedLayout.vue` queda obsoleto, cualquier página que aún lo use (p. ej. `Profile/Edit.vue`) debe migrarse a `MainDashboardLayout` con `defineOptions`.

Excepción: páginas públicas/no autenticadas (`Pages/Auth/**`, `Welcome.vue`) siguen usando `GuestLayout` o sin layout — no les aplica esta regla.

Clases y colores dentro de esas páginas: seguir la skill `xtrapubli-design-system` (clases tipo Bootstrap en `resources/css/app.css`), no Tailwind crudo ni componentes de Breeze.

## Notificaciones y confirmaciones: SweetAlert2 (Utils/AlertUtil.js), no banners/modales a medida
Los toasts de éxito/error tras crear/editar/eliminar son automáticos: hay un listener global (`resources/js/Composables/UseFlashNotifications.js`, conectado una vez en `app.js` vía `router.on('success', ...)`) que lee la prop compartida `flash` (`HandleInertiaRequests::share()`) y llama `showToast()` de `Utils/AlertUtil.js`. Un controlador nuevo solo necesita `redirect(...)->with('success', 'Mensaje...')` (o `'error'`) — nunca hace falta un banner `v-if="flashSuccess"` en la página ni un `onSuccess` en el form solo para mostrar el mensaje.

Para confirmar una acción destructiva (borrar un registro), usar `await confirmation('mensaje', 'título')` de `Utils/AlertUtil.js` en vez de un `<Modal>` de confirmación hecho a mano — ver el patrón en `Pages/Sucursales/Index.vue` (`confirmDelete()`).

`sweetalert2` (paquete npm) ya está instalado; sus popups están temados a la marca en `app.css` sección "20. SWEETALERT2" — no pasar colores sueltos a `Swal.fire()`.

## CRUD nuevo: DataTable + modal vs. vista independiente según complejidad
Para un modulo nuevo, sigue la forma de Pages/Sucursales/Index.vue (ver skill xtrapubli-design-system, Rule 5):
- Listado: siempre DataTable.vue + useServerTable (nunca <table> a mano).
- Alta/edicion: si los campos son simples, un unico Modal.vue + useForm compartido para crear/editar. Si es informacion compleja (varias tablas relacionadas, lineas de detalle, formulario muy largo), usa vistas independientes Create.vue/Edit.vue reutilizando el mismo formulario (partial compartido), no un modal forzado.
- Notificaciones/confirmaciones: siempre Utils/AlertUtil.js. Los toasts de exito/error salen solos via el listener global (Composables/UseFlashNotifications.js) con solo el ->with('success'/'error') del backend -- no hace falta onSuccess. Para borrar/acciones destructivas, `await confirmation(...)`, no un modal a medida.
