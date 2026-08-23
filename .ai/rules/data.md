---
paths:
  - 'resources/js/Data/**'
---

# Data

## Menús de navegación viven en Data/Sidebar/Nav.js, no hardcodeados en el componente
Todo el árbol de navegación del sidebar está en [`resources/js/Data/Sidebar/Nav.js`](../../resources/js/Data/Sidebar/Nav.js) (JS plano, sin TypeScript) como `NAV_MENU`. `Sidebar.vue` solo lo renderiza — para agregar/quitar un ítem de menú, edita ese archivo, no el componente.

Forma de los datos (máximo 3 niveles: item → children → children.children):
- Separador de sección: `{ menutitle, permission }`.
- Ítem: `{ title, icon, type: 'link'|'sub', path?, children?, permission }`.
- `icon` es una clase de Font Awesome o MDI (ya instalados, ver `.ai/rules/js.md`), nunca un SVG importado.
- `path` es la URL final (`/vales`), no un nombre de ruta Ziggy — `Sidebar.vue` la pasa directo a `<Link :href>`.

Trampa importante: `permission` es **obligatorio** en cada ítem, incluso en los que "cualquiera debería ver" (ej. Dashboard). La directiva `v-can` falla cerrado: un `permission` vacío/omitido oculta el elemento para cualquier usuario que no sea super-admin, no lo muestra por defecto.

`App\Http\Middleware\HandleInertiaRequests::share()` ya comparte `auth.roles` / `auth.permissions` / `auth.is_super_admin` (via `Spatie\Permission\Traits\HasRoles` en `App\Models\User`), que es lo que `v-can` lee. Pero si el usuario logueado no tiene roles/permisos asignados en la base de datos (o no existe el rol `super-admin`), el menú se ve vacío igual — no es un bug del frontend, falta seed/asignación de roles y permisos.

## Checklist al agregar una vista nueva del dashboard
Cuando se agrega una pantalla nueva (no solo el componente Vue), tocan 4 archivos, en este orden:

1. **Ruta + controlador**: agregar la ruta en `routes/web.php` (o su propio archivo de rutas) apuntando a un controlador que devuelva `inertia('Carpeta/Componente', [...])` — ver `.ai/rules/controllers.md`.
2. **Página Vue**: crear `resources/js/Pages/Carpeta/Componente.vue` con `defineOptions({ layout: MainDashboardLayout })` — ver `.ai/rules/pages.md`.
3. **Permiso**: agregar el permiso (`recurso.accion`) al módulo correspondiente en [`config/acl.php`](../../config/acl.php), y a los roles que deban tenerlo — ver `.ai/rules/config.md`. Después correr `php artisan db:seed --class=RolesAndPermissionsSeeder`.
4. **Entrada de menú**: agregar el ítem a `NAV_MENU` en `Data/Sidebar/Nav.js` con el mismo string de `permission` que en `acl.php`, el `path` real de la ruta, y un `icon` de Font Awesome/MDI.

Si se salta el paso 3 o 4, o los nombres de permiso no coinciden entre `acl.php` y `Nav.js`, la vista puede existir y funcionar por URL directa pero nunca aparece en el sidebar para nadie que no sea `super-admin`.
