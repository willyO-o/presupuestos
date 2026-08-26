---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/FormulaController.php
---

# Controllers

## Convenciones de controlador: helper inertia(), request()->user(), redirect()->route()
En este proyecto, dentro de controladores:

- **Vistas Inertia**: usar el helper global `inertia('Carpeta/Componente', [...])`, no la fachada `Inertia::render(...)`. Sin `use Inertia\Inertia;` — solo `use Inertia\Response;` para el type hint del método si aplica.
- **Usuario autenticado**: usar `$request->user()`, no `Auth::user()` ni `auth()->user()`.
- **Redirecciones**: usar el helper `redirect()->route(...)` / `redirect()->to(...)` / `redirect()->intended(...)`, no la fachada `Redirect::route(...)` / `Redirect::to(...)`.

Ya se aplicó esto a todos los controladores existentes (`ProfileController` y los de `Auth/*` generados por Breeze, que traían `Inertia::render()` y, en `ProfileController`, `Redirect::`) — cualquier controlador nuevo debe seguir el mismo patrón desde el inicio.

## CRUD de Fórmulas y Receta/BOM de Producto: ya construido (2026-08-25)
Ya existe UI completa para gestionar el motor de fórmulas dinámicas (ver `.ai/rules/calculo.md`), construida siguiendo los patrones existentes del proyecto — no reimplementar desde cero ni asumir que falta:

- **Catálogo de fórmulas** (`formulas.ver/crear/editar/eliminar` en `config/acl.php`, solo rol `administrador`): `FormulaController` (CRUD estándar, patrón `AreaController`) + `Http/Requests/Formula/{Store,Update}FormulaRequest` (la regla de `expresion` es un Closure que llama `FormulaCalculator::mensajeError()`, no reinventar la validación) + `Pages/Formulas/Index.vue` (DataTable + Modal, con un tester "Probar fórmula" que llama a `POST /formulas/probar` vía axios — endpoint JSON aparte, no un visit de Inertia, ver `FormulaController::probar()`).
- **Receta/BOM de un producto** (bajo el mismo permiso `productos.editar`, ver comentario en `config/acl.php`): `ProductoMaterialController` con rutas anidadas `/productos/{producto}/materiales/*` + `Http/Requests/ProductoMaterial/{Store,Update}ProductoMaterialRequest` (XOR formula_id/cantidad_por_unidad validado en `withValidator()`, no en las rules) + `Pages/Productos/Receta.vue` — vista independiente (no modal) por ser información compleja, alcanzable desde un botón nuevo en `Pages/Productos/Index.vue` (icono `fa-flask`). `ProductoMaterialController` verifica a mano que la línea pertenezca al producto de la URL (`assertPerteneceAProducto()`) porque el binding anidado de Laravel no scopea solo.

Tests: `FormulaControllerTest`, `ProductoMaterialControllerTest`. Antes de tocar cualquiera de estos archivos, léelos — ya resuelven el flujo completo crear fórmula → armar receta → (falta) usar `CosteoProductoService` desde el módulo de Cotización.
