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

## acl.php reescrito para el negocio XtraPubli (ya no es la app de combustible/flota)
config/acl.php se reescribió (2026-08-23) para alinearse a database-design.md: se eliminaron TODOS los módulos/permisos de la app de flota/combustible (vales, cargas-combustible, operacion-diaria, mantenimiento, conductores, vehiculos, grifos, tipos-*, grupos-vehiculo, repuestos, personas, parametros-empresa) — no tenían pantallas reales, eran solo scaffolding de la plantilla base. El seeder (RolesAndPermissionsSeeder) ahora también BORRA de la base de datos cualquier Role/Permission que ya no esté en acl.php (antes solo agregaba, nunca limpiaba), así que acl.php es fuente de verdad real, no solo de arranque.

Roles nuevos (organigrama de database-design.md 3.1): administrador, vendedor, disenador, jefe-produccion, operario-produccion, contador, secretaria, cliente — más `super-admin` que se mantiene igual (bypass total, sin depender de la lista de permisos). Se eliminó el rol `operador` y su usuario de prueba `operador@gmail.com` (era de la app de flota). El usuario `superadmin@gmail.com` se mantiene con rol `super-admin` — es el usuario real que se usa para operar el sistema mientras se construyen los módulos.

El rol `cliente` NO tiene `cotizaciones.aprobar` a propósito, aunque database-design.md menciona que el cliente "aprueba la propia": eso debe resolverse con una policy scopeada por `cliente_id` desde el futuro portal, no dándole el permiso plano (que le dejaría aprobar cualquier cotización).

De momento solo existen pantallas reales para 3 módulos (sucursales, categorias-material, categorias-producto, ver [[table]]) — el resto de módulos en acl.php y en Nav.js (empleados, clientes, materiales, proveedores, compras, productos, cotizaciones, pedidos, ordenes-compra-cliente, notas-entrega, pagos, reportes, usuarios, roles) son scaffolding de permisos/menú a propósito, para ir construyendo el resto sin retocar ACL/Nav.js en cada módulo nuevo — sus rutas todavía no existen (404 si se navega ahí).
