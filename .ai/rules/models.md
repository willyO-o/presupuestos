---
paths:
  - app/Models/Sucursal.php
  - app/Models/Formula.php
---

# Models

## Sucursal (modelo/CRUD) desactualizado tras el nuevo schema.json — falta `ciudad`
La migración `sucursal` se reescribió (2026-08-23) para seguir `schema.json`/`database-design.md` y ahora exige `ciudad` (string, NOT NULL) además de nombre/direccion/telefono/estado. `app/Models/Sucursal.php`, `SucursalFactory`, `Store/UpdateSucursalRequest`, `SucursalController`, `Pages/Sucursales/Index.vue` y `SucursalControllerTest` (construidos antes de este cambio) NO conocen `ciudad` todavía — 9 tests de `SucursalControllerTest` fallan con `NOT NULL constraint failed: sucursal.ciudad` hasta que se actualicen esos archivos.

**Cómo aplicar:** agregar `ciudad` a `$fillable` del modelo, a la factory, a las reglas de ambos Form Requests, al formulario/columna de `Index.vue`, y a los payloads de los tests — mismo patrón que `direccion`. No es un bug nuevo, es trabajo pendiente conocido del cambio de esquema.

## Sucursal (CRUD) desactualizado tras el nuevo schema.json — falta `ciudad` en Requests/Controller/Vue/tests
La migración `sucursal` se reescribió (2026-08-23) para seguir `schema.json`/`database-design.md` y ahora exige `ciudad` (string, NOT NULL) además de nombre/direccion/telefono/estado.

`app/Models/Sucursal.php` (`$fillable`) y `database/factories/SucursalFactory.php` YA incluyen `ciudad`. Lo que sigue pendiente: `StoreSucursalRequest`/`UpdateSucursalRequest` (falta la regla de validación de `ciudad`), `SucursalController` no la necesita tocar (usa `$request->validated()`, alcanza con agregarla al Request), `Pages/Sucursales/Index.vue` (falta columna/campo del formulario) y `tests/Feature/SucursalControllerTest.php` (los payloads de create/update no mandan `ciudad`). Mientras tanto, 1 test falla: `a user with permission can create a sucursal` (500 — `NOT NULL constraint failed: sucursal.ciudad`), porque el Form Request no deja pasar ese campo a `Sucursal::create()`.

**Cómo aplicar:** agregar `'ciudad' => ['required','string','max:255']` a ambos Form Requests, un input más en el modal de `Index.vue` (mismo patrón que `direccion`), y `'ciudad' => '...'` en los payloads de los tests de creación/edición.

## Sucursal ciudad — ya no está pendiente, quedó resuelto
Las notas previas sobre "falta ciudad" en Sucursal (modelo/CRUD desactualizado tras el nuevo schema.json) ya no aplican: UpdateSucursalRequest ahora valida `ciudad` igual que StoreSucursalRequest, y SucursalControllerTest manda `ciudad` en los payloads de create/update. Los 152 tests del proyecto pasan. No repetir ese trabajo.

## Formula y ProductoMaterial: modelos nuevos del motor de fórmulas dinámicas
`App\Models\Formula` (tabla `formula`) y `App\Models\ProductoMaterial` (tabla `producto_material`, no existía como modelo Eloquent hasta 2026-08-25 pese a que la tabla sí existía) son parte del motor de cálculo dinámico — ver la nota completa en `.ai/rules/migrations.md` ("Motor de cálculo por tipo de producto: implementado") y `App\Services\Calculo\*`.

`ProductoMaterial::esDinamica()` indica si la línea usa `formula_id` (cantidad calculada en runtime) en vez del factor fijo `cantidad_por_unidad` (nullable ahora, exactamente uno de los dos debe estar presente). No evalúes `formula->expresion` a mano en un controller/modelo — siempre a través de `App\Services\Calculo\FormulaCalculator`.
