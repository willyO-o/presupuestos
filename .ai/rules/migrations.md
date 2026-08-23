---
paths:
  - 'database/migrations/2026_08_23_02*.php'
  - 'database/migrations/**'
---

# Migrations

## Usar nombres singulares en migraciones de negocio
Para las migraciones de negocio creadas en esta tanda (`sucursal`, `cargo`, `persona`, `empresa`, `personal` y `cliente`), el nombre de la migración y de la tabla debe mantenerse en singular, aunque Laravel genere inicialmente nombres plurales. Esta regla no aplica a las migraciones predeterminadas de Laravel/Breeze ni a migraciones de paquetes como Spatie Permission; esas deben conservar sus nombres y tablas originales para no romper convenciones o funcionalidades del framework.

## Esquema de costos/presupuestos: tablas en singular, soft deletes solo en las núcleo
Las tablas del dominio (sucursal, cargo, persona, empresa, personal, cliente, unidad_medida, tipo_producto, proveedor, material, formula, variable_formula, formula_material, producto) usan nombre **singular**, no el plural que Eloquent espera por convención. Cualquier modelo nuevo sobre estas tablas necesita `protected $table = 'persona';` explícito (o el nombre que corresponda) — si no, Eloquent va a buscar `personas` y falla.

Patrón de columnas ya establecido (seguirlo en tablas nuevas del mismo esquema):
- `estado` es `enum('ACTIVO','INACTIVO')` en **mayúsculas** (no `activo`/`inactivo` como venía en el SQL original).
- `timestamps()` va en la mayoría de las tablas, pero se omite en catálogos chicos tipo enum (`cargo`, `unidad_medida`) y en tablas de detalle/BOM (`variable_formula`, `formula_material`).
- `softDeletes()` solo en las tablas "núcleo" del negocio, para no perder el historial cuando algo se referencia desde presupuestos/cotizaciones: `persona`, `empresa`, `personal`, `cliente`, `proveedor`, `material`, `producto`. Los catálogos puros (`sucursal`, `cargo`, `unidad_medida`, `tipo_producto`, `formula`, `variable_formula`, `formula_material`) no la llevan.

Trampa real ya encontrada: `Illuminate\Database\Schema\Blueprint` **no tiene** un método `check()` nativo (a pesar de estar en Laravel 13) — el CHECK constraint de `cliente` (tipo_cliente natural/jurídico XOR persona_id/empresa_id) se agrega con `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)')` después de `Schema::create`. Además, SQLite (el driver que usan los tests, `phpunit.xml` → `DB_CONNECTION=sqlite`, `:memory:`) no soporta `ADD CONSTRAINT` vía `ALTER TABLE`, así que ese `DB::statement` va envuelto en `if (DB::connection()->getDriverName() !== 'sqlite')` — si no, toda la suite de tests falla al migrar.
