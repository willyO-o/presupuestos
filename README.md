# XtraPubli — Sistema de Costos y Presupuestos

Aplicación Laravel + Inertia (Vue 3) para la gestión de cotizaciones, pedidos, materiales, compras y pagos de XtraPubli (gigantografía, cerrajería, carpintería, publicidad). Roles y permisos con Spatie Laravel-Permission.

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.3+ (con extensiones habituales de Laravel: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `bcmath`, y además `gd` y `zlib` para los PDFs) |
| Composer | 2.x |
| Node.js | 20+ (**solo para compilar el frontend**, no hace falta en el servidor) |
| npm | 10+ |
| Base de datos | MariaDB 10.6+ / MySQL 8+ |

### Generación de PDFs

Todos los documentos (cotización, orden de trabajo, nota de entrega, compra,
orden de compra del cliente y la estimación del cotizador web) se generan con
**`setasign/fpdf` 1.9**, que es PHP puro.

**No hace falta nada en el servidor más que PHP** con `gd` y `zlib`, ambas
habituales en cualquier hosting. Se eligió así a propósito: el sistema se
despliega en hosting compartido, donde no se puede ejecutar un navegador
headless ni instalar binarios. Un documento típico pesa unos 4 KB y se genera
en milisegundos.

El precio de esa portabilidad es que **no hay CSS**: FPDF dibuja por
coordenadas. Toda la maqueta —membrete, pie numerado, tablas que paginan
solas, recuadros, firmas— vive en `App\Services\Pdf\Documentos\Documento`, y
cada documento la usa como ladrillos. Ver `.ai/rules/css.md` antes de tocarla.

Dos trampas que ya están resueltas y conviene conocer:

- **Encoding**: las fuentes del núcleo de FPDF son cp1252, no UTF-8. Todo texto
  que se dibuje tiene que pasar por `Documento::t()` o la `ñ` sale como `Ã±`.
  (FPDF 1.9 sí acepta UTF-8, pero solo en las propiedades del documento.)
- **Imágenes**: se leen del disco por ruta, no por URL — ver
  `NotaEntregaDetalle::fotoRuta()`.

## Instalación

Resumen (cada paso se explica abajo):

```bash
git clone <repo> presupuestos
cd presupuestos

composer install
npm install
cp .env.example .env          # en Windows (PowerShell): Copy-Item .env.example .env
php artisan key:generate

# editar .env: base de datos (y, si se usa, la IA)

php artisan migrate --seed    # tablas + catálogos + historial de prueba
php artisan storage:link
npm run build

composer run dev              # o: php artisan serve
```

Entrar con `superadmin@gmail.com` / `admin123` (ver [Usuarios de prueba](#usuarios-de-prueba)).

### 1. Configurar `.env`

`.env.example` trae `DB_CONNECTION=sqlite`. Para MariaDB/MySQL (lo que se usa en producción):

```env
APP_NAME="XtraPubli"
APP_URL=http://localhost:8000
APP_LOCALE=es

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presupuestos
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
```

Crea la base de datos vacía (`presupuestos` o el nombre elegido) en tu servidor MariaDB antes de migrar:

```sql
CREATE DATABASE presupuestos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Migrar y sembrar datos

```bash
php artisan migrate --seed
```

Crea las tablas y siembra roles/permisos, sucursales, áreas, categorías, proveedores, clientes, empleados, materiales, productos, fórmulas, recetas y un **historial comercial simulado** (compras, cotizaciones, pedidos, pagos y entregas) para que los reportes y el BI tengan datos. Ver [Datos de prueba](#datos-de-prueba-historial-comercial).

Para empezar de cero (borra TODO y vuelve a sembrar):

```bash
php artisan migrate:fresh --seed
```

### 3. Publicar el storage (archivos públicos)

Las fotos de perfil, las imágenes de productos, los comprobantes y las evidencias se guardan en el disco `public`:

```bash
php artisan storage:link
```

### 4. Compilar assets

```bash
npm run build     # producción
npm run dev       # desarrollo (watch)
```

### 5. Levantar en desarrollo

```bash
composer run dev
```

Levanta servidor, cola y Vite en paralelo. Para que corran las tareas programadas (anomalías diarias y resumen semanal del BI) en desarrollo, en otra terminal:

```bash
php artisan schedule:work
```

En producción, un cron cada minuto: `* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1`.

## Datos de prueba (historial comercial)

`HistorialComercialSeeder` simula mes a mes, con los mismos servicios que usa el sistema, un historial coherente: crecimiento ~1,2 % mensual, estacionalidad boliviana (carnaval, fiestas patrias, fin de año), inflación de materiales con un salto cambiario, una sucursal que abre a mitad del historial y escenarios plantados para que las alertas y anomalías tengan qué mostrar. Es determinista (siempre genera lo mismo).

**Cuántos meses simula** se configura en `.env` (por defecto 24; mínimo 3, máximo 60):

```env
BI_DEMO_MESES_HISTORIAL=12
```

Para sembrar **12 meses** de datos de prueba:

```bash
# 1) en .env:  BI_DEMO_MESES_HISTORIAL=12
php artisan config:clear
php artisan migrate:fresh --seed
```

| Meses | Tiempo aprox. de siembra | Para qué sirve |
|---|---|---|
| 12 | ~35 s | Demo rápida: tendencias, proyección y validación (11 meses completos + el mes en curso). |
| 24 | ~1 min | Muestra la estacionalidad de dos años y deja más historia antes del salto cambiario. |

Notas:

- El seeder **no se repite**: si ya hay cotizaciones en la base, se omite (avisa en consola). Por eso para cambiar la cantidad de meses hay que usar `migrate:fresh --seed`.
- Los eventos se acomodan al largo elegido: el salto cambiario ocurre 10 meses antes del final y la sucursal de Santa Cruz abre a un tercio del historial.
- La validación de los pronósticos necesita al menos 9 meses completos (6 de entrenamiento + 3 a validar); con menos, la ficha "Cómo se calcula" lo dice.
- Si siembras menos de 12 meses, filtra el BI a los meses que tienen datos: el periodo por defecto (12 meses) incluiría meses vacíos al principio, que cuentan como cero en la tendencia.

### Usuarios de prueba

Fuera de `production` se crean:

| Usuario | Clave | Rol |
|---|---|---|
| `superadmin@gmail.com` | `admin123` | super-admin (todo) |
| `gerente@gmail.com` | `admin123` | gerente (toda la empresa, BI y asistente IA) |
| `vendedor@gmail.com` | `admin123` | vendedor |
| `cliente0@gmail.com`, `cliente1@gmail.com` | `cliente123` | cliente (portal) |

## Inteligencia de negocios: método de los pronósticos

Todas las proyecciones del BI (demanda, costos, consumo, presupuestos, rentabilidad) usan la misma matemática, en `App\Services\Reporte\Pronostico`:

| Pregunta | Respuesta |
|---|---|
| Método de la proyección | **Regresión lineal simple por mínimos cuadrados**: y = a + b·x, un punto por mes (x = 0, 1, 2…). |
| Línea suavizada | Media móvil simple de 3 meses. |
| Rango probable (banda) | **Intervalo de predicción al 80 %**: ŷ ± t(n−2) · s · √(1 + 1/n + (x₀ − x̄)²/Sxx). Se ensancha cuanto más lejos se proyecta. |
| Historia que usa | El periodo del filtro; sin filtro, **los últimos 12 meses** (`BI_MESES_POR_DEFECTO`). |
| Mes en curso | En los **totales** (pedidos, montos, consumo, utilidad) **no entra en la recta** porque está incompleto; se muestra igual y se estima su cierre. Los promedios, porcentajes y precios sí lo usan. |
| Horizonte | 3 meses (`BI_MESES_PROYECCION`). |
| Mínimo | 3 meses con datos para proyectar; si no, la pantalla lo dice en vez de inventar una tendencia. |
| Calidad del ajuste | R² (fuerte ≥ 0,7 · moderado ≥ 0,4 · débil por debajo). |
| Validación | Se ocultan los últimos 3 meses reales, se pronostican con los anteriores y se compara el MAPE de la recta contra **Holt** (suavizado exponencial doble) y la **media móvil**. Si otro modelo se equivoca menos, se advierte. |

Cada gráfico tiene debajo una ficha plegable **"Cómo se calcula"** con estos datos para ESE gráfico (meses usados, R², intervalo y error de validación).

Configuración (`config/bi.php`, en `.env`): `BI_MESES_POR_DEFECTO`, `BI_MESES_PROYECCION`, `BI_CONFIANZA` (0.80 = 80 %).

Otras técnicas del BI: anomalías con la prueba de cola de Poisson contra la mediana de 8 semanas y el percentil 10 de márgenes (`DeteccionAnomaliasService`); el resumen semanal compara contra el promedio de 4 semanas.

## Informe de proyecciones (PDF y CSV)

En **Reportes → Inteligencia de negocios** está el botón **Generar informe** (junto al filtro de fechas). Se eligen las secciones (Demanda y ventas, Costos y materiales, Presupuestos y rentabilidad, Alertas) y el formato:

- **PDF** (vista previa en una pestaña nueva): conclusiones, resumen ejecutivo, una sección por área con **gráfico, lectura en texto automático y tabla de la proyección**, y un **anexo metodológico** con la ficha de cada pronóstico. Es un documento interno (lleva costos y márgenes).
- **CSV** para Excel en español (`;`, coma decimal): las series mes a mes (real, cierre estimado, proyección con su rango), la ficha del método de cada indicador y las alertas.

Rutas: `GET /reportes/bi/informe/pdf` y `GET /reportes/bi/informe/csv` con `desde`, `hasta` y `secciones[]` (`?descargar=1` baja el PDF en vez de abrirlo). Permiso: `reportes.bi`, el mismo de las pantallas; respeta el alcance por sucursal del usuario.

El texto automático son plantillas sobre los números calculados (`RedaccionPronostico`), no IA: cada cifra del párrafo está en la tabla y el gráfico de al lado.

## Asistente de IA (opcional)

El BI tiene tres funciones con IA: el **asistente** de preguntas en lenguaje natural (panel lateral), el **resumen semanal** redactado y la **detección de anomalías**. **El sistema funciona completo sin IA**: sin claves, el resumen y las anomalías salen con su plantilla y el asistente avisa que no está disponible. Los números siempre los calcula el sistema; la IA solo redacta y elige consultas cerradas de solo lectura (ver `.ai/rules/ia.md`).

### 1. Claves del proveedor

En `.env` (al menos una; Gemini responde primero y, si falla, Claude):

```env
GEMINI_API_KEY=tu-clave-de-google-ai-studio
ANTHROPIC_API_KEY=tu-clave-de-anthropic
IA_HABILITADA=true
IA_PRESUPUESTO_MENSUAL_USD=20
```

- Gemini: crear la clave en Google AI Studio **con facturación activa** (el nivel gratuito usa el contenido para entrenar sus modelos).
- Claude: crear la clave en la consola de Anthropic.
- `IA_PRESUPUESTO_MENSUAL_USD` es un tope duro: al alcanzarlo, la IA se apaga hasta el mes siguiente. El consumo de cada llamada se registra en la tabla `ia_uso`.
- `IA_HABILITADA=false` apaga todo sin borrar las claves.
- Los modelos de cada función se pueden cambiar en `config/ia.php` (variables `IA_ASISTENTE_GEMINI`, `IA_RESUMEN_GEMINI`, etc.).

### 2. Usuario de solo lectura en MariaDB (recomendado)

Las consultas del asistente corren en una conexión de solo lectura. Para reforzarla con un usuario de MariaDB que solo tiene `SELECT` sobre las tablas de negocio (y ninguna columna con datos personales):

```bash
php artisan ia:usuario-lectura                 # muestra el SQL
php artisan ia:usuario-lectura --aplicar       # lo ejecuta (requiere un usuario con privilegio GRANT)
```

y agregar en `.env` las líneas que imprime:

```env
IA_DB_USERNAME=ia_lectura
IA_DB_PASSWORD=la-clave-generada
```

Sin estas líneas, la conexión de lectura usa el usuario principal y sigue protegida por `SET SESSION TRANSACTION READ ONLY` y por un filtro que solo deja pasar `SELECT`.

### 3. Permisos

El asistente y las acciones de IA requieren el permiso `reportes.asistente`. Lo tienen `super-admin`, `administrador` y `gerente`; se puede delegar a otros roles desde **Roles**.

### 4. Tareas programadas

```bash
php artisan bi:detectar-anomalias      # a diario 06:30 (hora de La Paz)
php artisan bi:resumen-semanal         # los lunes 07:00
```

Corren solas con el scheduler (`php artisan schedule:work` en desarrollo o el cron en producción, ver arriba). También se pueden lanzar a mano desde las pestañas **Alertas** y **Resumen semanal**.

### 5. Verificar

```bash
php artisan config:clear
```

Entrar con `gerente@gmail.com`, pulsar **Asistente IA** (abajo a la derecha) y preguntar, por ejemplo, "¿cuánto vendimos el último trimestre por sucursal?". Las respuestas tardan entre 1 y 2 minutos. Si el hosting corta antes, subir `max_execution_time` de PHP a 180 s o más.

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
| **gerente** | Ve toda la empresa: reportes, BI, informe de proyecciones y asistente IA. |
| **secretaria** | Clientes, cotizaciones, pedidos (ver), órdenes de compra de cliente. |
| **cliente** | Rol de portal externo: solo ve sus cotizaciones y pedidos (sin acceso al panel interno). |

Para agregar o modificar permisos: edita `config/acl.php` y vuelve a correr:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Usuario de arranque

Fuera de `production`, el seeder crea `superadmin@gmail.com` / `admin123` (rol `super-admin`) y los demás [usuarios de prueba](#usuarios-de-prueba).

## Testing

```bash
php artisan test --compact
```

## Formato de código (PHP)

```bash
vendor/bin/pint --format agent
```
