---
paths:
  - 'app/Models/Concerns/**'
---

# Concerns

## Alcance por sucursal: TieneAlcanceSucursal + AcotaPorSucursal
Qué sucursales ve una cuenta se decide en UN solo sitio: `User::sucursalesVisibles(?string $modulo)` (trait `App\Models\Concerns\TieneAlcanceSucursal`). No volver a escribir la condición inline en un controlador — así estaba antes, copiada 3 veces (Pedido, PedidoController, DocumentoPdfController) y solo para pedidos.

Orden de resolución: rol global (`config('acl.sucursales.roles_globales')` = super-admin, administrador) → `users.alcance_sucursal` TODAS → override `<modulo>.ver_todas_sucursales` → ASIGNADAS (pivote `sucursal_user`, lista literal, NO agrega la del empleado) → PROPIA (`empleado.sucursal_id`).

**TRAMPA — `null` ≠ `[]`**: `null` = ve todas (sin filtro); `[]` = no ve ninguna (cuenta sin ficha de empleado, o ASIGNADAS sin nada marcado). Un `empty($ids)` los confunde y le abre la empresa entera a quien no debe ver nada. Comprobar SIEMPRE `=== null` primero.

Para acotar un modelo: `use AcotaPorSucursal` + declarar `rutaSucursal()` (`null` = columna propia; si no la relación, admite `'pedido.cotizacion'`) y opcionalmente `moduloSucursal()`. Da el scope `visiblePara($user)`. `compra`/`material`/`proveedor`/`cliente`/`producto` NO tienen sucursal (inventario y compras son de toda la empresa): no usan el trait, se rotulan como dato global en pantalla.

El override solo AMPLÍA, nunca recorta. La escritura (Form Requests, guards de store) tiene que consultar el mismo helper con el MISMO módulo que el listado, o aparece "veo un registro que no puedo editar".

Tests: `tests/Feature/AlcanceSucursalTest.php` (21). Verificado con mutación: romper el fallo-cerrado o ignorar el módulo hace fallar pruebas concretas.
