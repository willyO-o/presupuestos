# XtraPubli — Sistema de Costos y Presupuestos

Aplicación Laravel + Inertia (Vue 3) para la gestión de cotizaciones, pedidos, materiales, compras y pagos de XtraPubli (gigantografía, cerrajería, carpintería, publicidad). Roles y permisos con Spatie Laravel-Permission.

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.3+ (con extensiones habituales de Laravel: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `bcmath`) |
| Composer | 2.x |
| Node.js | 20+ |
| npm | 10+ |
| Base de datos | MariaDB 10.6+ / MySQL 8+ |

## Instalación

```bash
git clone <repo>
cd presupuestos

composer install
cp .env.example .env
php artisan key:generate

npm install
```

### Configurar `.env`

Por defecto el proyecto trae `DB_CONNECTION=sqlite`. Para usar MariaDB/MySQL, edita `.env`:

```env
APP_NAME="XtraPubli"
APP_URL=http://localhost:8000
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presupuestos
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
```

Crea la base de datos vacía (`presupuestos` o el nombre elegido) en tu servidor MariaDB antes de migrar.

### Migrar y sembrar datos

```bash
php artisan migrate --seed
```

Esto crea las tablas y siembra: roles/permisos base, sucursales, áreas, categorías de material/producto, proveedores, clientes, empleados, materiales y productos de catálogo (ver [.ai/rules/seeders.md](.ai/rules/seeders.md)).

### Publicar el storage (archivos públicos)

Las fotos de perfil de usuario (`users.foto`) se guardan en el disco `public`, así que hace falta el enlace simbólico:

```bash
php artisan storage:link
```

### Compilar assets

```bash
npm run build     # producción
npm run dev       # desarrollo (watch)
```

### Levantar en desarrollo

```bash
composer run dev
```

Este script (`php artisan dev`) levanta servidor, cola y Vite en paralelo.

## Roles y permisos

Los permisos y la matriz rol↔permiso se definen en [config/acl.php](config/acl.php) (fuente de verdad única) y se siembran vía `RolesAndPermissionsSeeder`. Convención de permisos: `recurso.accion` (ej. `cotizaciones.crear`).

| Rol | Alcance resumido |
|---|---|
| **super-admin** | Bypass total (todos los permisos + `Gate::before`). Usuario de arranque. |
| **administrador** | Acceso completo a todos los módulos del negocio. |
| **vendedor** | Clientes, cotizaciones, pedidos (ver), catálogo de productos, órdenes de compra de cliente. |
| **disenador** | Pedidos (asignar área / actualizar estado), catálogo de productos, cotizaciones (ver). |
| **jefe-produccion** | Materiales (incl. costos), pedidos, notas de entrega, reportes de producción. |
| **operario-produccion** | Pedidos (actualizar estado), notas de entrega. |
| **contador** | Materiales (costos), proveedores, compras, pagos, reportes financieros/BI. |
| **secretaria** | Clientes, cotizaciones, pedidos (ver), órdenes de compra de cliente. |
| **cliente** | Rol de portal externo: solo ve sus cotizaciones y pedidos (sin acceso al panel interno). |

Para agregar o modificar permisos: edita `config/acl.php` y vuelve a correr:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Usuario de arranque

Fuera de `production`, el seeder crea automáticamente:

- **Email:** `superadmin@gmail.com`
- **Password:** `admin123`
- **Rol:** `super-admin`

## Testing

```bash
php artisan test --compact
```

## Formato de código (PHP)

```bash
vendor/bin/pint --format agent
```
