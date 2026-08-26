---
paths:
  - 'database/migrations/2026_08_23_04*.php'
  - 'database/migrations/**'
---

# Migrations

## Fuente de verdad del esquema: skill `xtrapubli-design-system`
El esquema completo del dominio (29 tablas: 6 nativas de Laravel/Spatie + 23 propias) vive en
[`.claude/skills/xtrapubli-design-system/references/schema.json`](../../.claude/skills/xtrapubli-design-system/references/schema.json)
(estructura exacta: columnas, tipos, FKs, índices) y su explicación narrativa en
[`database-design.md`](../../.claude/skills/xtrapubli-design-system/references/database-design.md)
del mismo directorio (módulos, flujo de negocio cotización → pedido → entrega → pago). Antes de
tocar una migración de dominio, lee ahí — no inventes columnas ni relaciones a ojo.

Este es el segundo esquema del proyecto: reemplazó por completo al primero (`cargo`, `persona`,
`empresa`, `personal`, `unidad_medida`, `tipo_producto`, `formula`, `variable_formula`,
`formula_material`, más las versiones anteriores de `sucursal`/`cliente`/`material`/`proveedor`/`producto`)
el 2026-08-23. Esas migraciones y sus tablas ya no existen — si ves referencias a ellas en
memoria/conversaciones viejas, están obsoletas.

## Convenciones de nomenclatura
- Tablas propias del dominio: **singular**, snake_case (`empleado`, `cotizacion_detalle`,
  `pedido_seguimiento`). Cualquier modelo Eloquent necesita `protected $table = 'empleado';`
  explícito (o el que corresponda) — si no, Eloquent busca el plural y falla.
- Tablas nativas de Laravel y de `spatie/laravel-permission` conservan su nombre/plural original
  (`users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
  `role_has_permissions`) — **nunca se tocan sus migraciones originales**. Si una tabla nativa
  necesita columnas nuevas (ej. `users.foto`/`users.estado`, ver
  `2026_08_23_040001_add_foto_and_estado_to_users_table.php`), se agrega con una migración nueva
  (`Schema::table(...)`), nunca editando `0001_01_01_000000_create_users_table.php` ni la
  migración de permisos.
- `estado` (y cualquier otro campo que antes fue booleano) es `ENUM` en **mayúsculas**
  (`'ACTIVO'`/`'INACTIVO'`, o los valores que correspondan), nunca `boolean` ni minúsculas — así
  los `GROUP BY`/`WHERE` de reportes/BI no se rompen por inconsistencias de texto.
- `$table->id()` para toda PK; FKs con `$table->foreignId('x_id')->constrained('tabla_singular')`
  (nombre de tabla siempre explícito, la convención de Eloquent asume plural).
- Ninguna tabla del esquema actual usa `softDeletes()` (no está en `schema.json`) — no agregarlo
  por costumbre del esquema anterior.

## Política de `onDelete()` en FKs (no está en schema.json, se decidió al migrar)
- Catálogos/maestros referenciados por muchas filas (`sucursal`, `area`, `categoria_material`,
  `categoria_producto`, `proveedor`, `cliente`, `empleado`, `material`, `producto` cuando los
  referencia un encabezado transaccional) → `restrictOnDelete()`: no se puede borrar un maestro
  con historial dependiente.
- Detalle/líneas que pertenecen por completo a un encabezado (`compra_detalle`,
  `cotizacion_detalle`, `pedido_detalle`, `pedido_seguimiento`, `pedido_detalle_material`,
  `nota_entrega_detalle`, `producto_material.producto_id`, y los documentos que cuelgan 1:1 o
  1:N de un `pedido`: `orden_compra_cliente`, `nota_entrega`, `pago`) → `cascadeOnDelete()`:
  borrar el encabezado limpia sus líneas.
- FK opcional a `users` (`empleado.user_id`, `cliente.user_id`) → `nullOnDelete()`: si se borra
  la cuenta de usuario, el empleado/cliente sigue existiendo, solo pierde el acceso al sistema.
- `cotizacion_detalle.producto_id` (nullable, item personalizado sin catálogo) → `nullOnDelete()`.

## Inconsistencia corregida al migrar
`schema.json` describe `orden_compra_cliente.pedido_id` como relación `one_to_one_optional` en el
array `relationships`, pero la columna en `tables` no traía la marca `unique`. Se agregó
`->unique()` a `pedido_id` en `2026_08_23_040601_create_orden_compra_cliente_table.php` para que
la restricción real coincida con lo documentado (a lo sumo una OC por pedido).

## Verificar una migración nueva
`php artisan migrate:fresh --force` corre las 29 migraciones desde cero (útil para detectar un FK
mal ordenado); reseedear después con `php artisan db:seed --class=RolesAndPermissionsSeeder`. El
orden de archivos importa: una tabla con `foreignId(...)->constrained(...)` necesita que la
migración de la tabla referenciada tenga timestamp **anterior**.

## Motor de cálculo por tipo de producto (letras 3D, muebles): implementado (2026-08-25)
Ver la nota de arriba, "Fuente de verdad del esquema": el esquema anterior del proyecto SÍ tenía `formula`/`variable_formula`/`formula_material` y se eliminó por completo el 2026-08-23 al simplificar hacia el esquema actual. El 2026-08-24 se decidió diferir reintroducirlo hasta construir Cotización; el 2026-08-25, a pedido explícito del usuario, se implementó — **esta nota reemplaza esa decisión de diferir, ya no aplica**.

Diseño final (más liviano que el `formula`/`variable_formula`/`formula_material` original — sin tabla de variables, son un contrato fijo en código): tabla `formula` nueva (`id`, `nombre`, `expresion`, `descripcion`, `estado`) + `producto_material.formula_id` (nullable FK, `restrictOnDelete`) y `producto_material.cantidad_por_unidad` ahora nullable — exactamente una de las dos debe estar presente en cada línea de BOM (no se puede expresar como XOR en el esquema, se confía en cómo lo arma el seeder/futuro Form Request). `expresion` se evalúa con `nxp/math-executor` (paquete Composer nuevo, con aprobación del usuario) vía `App\Services\Calculo\FormulaCalculator`, con variables `ancho`/`alto`/`profundo`/`area`/`perimetro` (`App\Services\Calculo\MedidasCotizacion`). `App\Services\Calculo\CosteoProductoService` recorre `producto->productoMateriales`, resuelve cada línea (fórmula, o factor fijo × driver de `producto.unidad_medida`: área para M2, `ancho` para METRO_LINEAL, 1 para UNIDAD) y suma el costo — ver los modelos `App\Models\Formula` y `App\Models\ProductoMaterial` (este último no existía como modelo hasta ahora) y `.ai/rules/models.md`.

Importante: `cantidad` (unidades pedidas) NO es una variable de fórmula — se aplica como multiplicador uniforme fuera de la fórmula/factor, para que una receta siempre describa "cuánto material lleva UNA unidad" sin poder duplicar el multiplicador. Datos de prueba: `database/seeders/FormulaSeeder.php` (4 fórmulas reutilizables: Área simple, Perímetro, Perímetro con profundidad, Volumen) y `database/seeders/ProductoMaterialSeeder.php` (recetas reales sobre el catálogo curado, incluyendo "Letras corpóreas 3D iluminadas" combinando fórmula de área + fórmula de perímetro con profundidad + una línea estática — el caso que motivó todo esto). Tests: `tests/Unit/FormulaCalculatorTest.php`, `tests/Feature/CosteoProductoServiceTest.php`.

Sigue sin existir un CRUD/UI para `formula`/`producto_material` (no se pidió) — cuando se construya, la validación de una `expresion` nueva debe usar `FormulaCalculator::mensajeError()`/`validar()`, no reimplementarla.
