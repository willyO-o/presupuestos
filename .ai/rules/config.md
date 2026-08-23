---
paths:
  - config/acl.php
---

# Config

## Roles/permisos base: config/acl.php es la fuente de verdad, el seeder solo la lee
[`config/acl.php`](../../config/acl.php) define TODOS los permisos (agrupados por módulo, notación `recurso.accion`, ej. `usuarios.crear`) y los roles base (`super-admin` = todos los permisos, `operador` = subset de prueba). Es la fuente de verdad — para agregar un permiso o rol nuevo, edítalo ahí, no directamente en la base de datos ni con `Role::create()` suelto en otro lado.

Esto se siembra con un **seeder** (`database/seeders/RolesAndPermissionsSeeder.php`, registrado en `DatabaseSeeder`), no con una migración — es DML puro (crear/sincronizar `Role`/`Permission` de Spatie via `findOrCreate` + `syncPermissions`, idempotente), y `laravel-best-practices`/`laravel-permission-development` piden mantener eso fuera de las migraciones. Corre `php artisan db:seed --class=RolesAndPermissionsSeeder` para (re)aplicarlo después de editar `acl.php`.

Trampa ya encontrada una vez: entre crear los `Permission` y llamar `syncPermissions()` en los roles hace falta un `app(PermissionRegistrar::class)->forgetCachedPermissions()` intermedio — si falta, `syncPermissions()` resuelve contra el cache vacío de *antes* de crear los permisos y tira `PermissionDoesNotExist` aunque el permiso ya exista en la base de datos.

Ese mismo seeder crea 2 usuarios de prueba (`superadmin@gmail.com` / `operador@gmail.com`, clave `admin123`) pero SOLO fuera de `production` (`app()->environment('production')` los salta) — son datos de arranque temporales, se van a reemplazar cuando existan los roles/usuarios reales.

`super-admin` tiene bypass total en 3 capas que deben mantenerse en sync (incluso aunque también tenga todos los permisos listados en `acl.php`):
- Backend: `Gate::before` en `App\Providers\AppServiceProvider::boot()`.
- Frontend: directiva `v-can` (`resources/js/Directives/Can.js`), via `auth.is_super_admin` / `auth.roles`.
- Props compartidas: `App\Http\Middleware\HandleInertiaRequests::share()` manda `auth.roles`, `auth.permissions`, `auth.is_super_admin`.

Los nombres de permiso en `acl.php` deben coincidir exactamente con los `permission` que usa `resources/js/Data/Sidebar/Nav.js` (ver `.ai/rules/data.md`) — si renombras uno, renombra el otro.
