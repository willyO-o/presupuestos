---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders de catálogo por módulo (2026-08-24): curado + factory para volumen
Se crearon seeders para todos los módulos CRUD ya construidos: AreaSeeder, CategoriaMaterialSeeder, CategoriaProductoSeeder (listas fijas de database-design.md, via firstOrCreate — no usan la factory para evitar el sufijo aleatorio que le agrega unicidad al nombre), ProveedorSeeder/ClienteSeeder (factory random, sin lista fija), EmpleadoSeeder (factory + ->recycle(Sucursal::all())->recycle(Area::all()) para no crear sucursales/áreas nuevas), MaterialSeeder/ProductoSeeder (catálogo curado con nombres reales de la hoja de costos vía firstOrCreate + Model::factory(10)->recycle(...)->create() extra para volumen de paginación/búsqueda).

Todos registrados en DatabaseSeeder::run() en orden de dependencias (Sucursal/Area antes de Empleado; CategoriaMaterial antes de Material; CategoriaProducto antes de Producto). También se cambió el `User::factory()->create(['email' => 'test@example.com'])` del final de DatabaseSeeder a `User::firstOrCreate(...)` porque bloqueaba correr `php artisan db:seed` una segunda vez (email único). Los seeders de catálogo fijo y curado son idempotentes (correr `db:seed` de nuevo no duplica); los de puro volumen (Proveedor, Cliente, y el lote random de Material/Producto) no lo son a propósito — cada corrida agrega más filas de prueba.

No se sembró `producto_material` (BOM): no existe modelo/factory para esa tabla todavía (no tiene CRUD construido). Ver también la nota sobre el motor de cálculo por tipo de producto en .ai/rules/migrations.md.

## FormulaSeeder y ProductoMaterialSeeder: datos de prueba del motor de fórmulas
Se agregaron a la cadena de DatabaseSeeder (después de MaterialSeeder/ProductoSeeder): `FormulaSeeder` (4 fórmulas reutilizables idempotentes vía firstOrCreate: "Área simple", "Perímetro", "Perímetro con profundidad", "Volumen" — variables ancho/alto/profundo/area/perimetro) y `ProductoMaterialSeeder` (recetas BOM sobre el catálogo curado de ProductoSeeder/MaterialSeeder, mezclando líneas estáticas y dinámicas; el caso completo es "Letras corpóreas 3D iluminadas": acrílico vía fórmula de área + vinil vía fórmula de perímetro con profundidad + silicona estática). Idempotente igual que sus dependencias.

No confundir con `producto_material` como concepto: la tabla ya existía desde el primer esquema (§7 database-design.md) pero no tenía modelo Eloquent ni seeder hasta que se construyó el motor de fórmulas — ver `.ai/rules/migrations.md`.
