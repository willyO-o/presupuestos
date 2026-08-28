---
paths:
  - 'app/Services/Calculo/**'
---

# Calculo

## Motor de fórmulas dinámicas (nxp/math-executor): no reimplementar la evaluación a mano
`App\Services\Calculo\FormulaCalculator` envuelve `NXP\MathExecutor` (paquete `nxp/math-executor`, agregado 2026-08-25 con aprobación del usuario) para evaluar `formula.expresion`. Variables disponibles: `ancho`/`alto`/`profundo`/`area`/`perimetro`, armadas por `MedidasCotizacion::variables()` (`area`=ancho×alto, `perimetro`=(ancho+alto)×2 — solo se incluyen si ancho/alto están presentes; una fórmula que referencia una variable no disponible lanza `App\Exceptions\FormulaInvalidaException`, no calcula con 0 en silencio). `cantidad` (unidades pedidas) NO es variable de fórmula — la aplica `CosteoProductoService` como multiplicador uniforme afuera.

`App\Services\Calculo\CosteoProductoService::calcular(Producto, MedidasCotizacion, cantidad)` es el punto de entrada para costear un producto completo (recorre su BOM, mezcla líneas estáticas y dinámicas, devuelve `ResultadoCosteo` con el total y el desglose por línea). El futuro módulo de Cotización debe usar este servicio, no reimplementar la suma del BOM a mano. Ver `.ai/rules/migrations.md` para el diseño completo y los seeders de datos de prueba.

## Redondeo a unidad de compra (`material.redondeo_compra`, 2026-08-28)
`CosteoProductoService::calcular()` multiplica `cantidad_por_unidad`/fórmula × `cantidad` (unidades pedidas) → `cantidadBruta`, y **luego redondea hacia arriba** al múltiplo `material.redondeo_compra` (privado `redondearACompra()`, `ceil(x/m - 1e-9) * m`) — el material se compra en unidades enteras (plancha, barra de 6 m, galón, unidad) y el sobrante de un corte no se reutiliza. `null`/0 = sin redondeo (lona/vinil de rollo, líquidos que sí se aprovechan). El redondeo es sobre el TOTAL del pedido, no por unidad (10 letreros × 0,3 plancha = 3 planchas, no 10). `LineaCosteo` expone `cantidadBruta` + `cantidadConsumida` + `fueRedondeada()` para mostrar "0,6 → 2,98 m²" en el desglose. `PrecioSugeridoService` reenvía eso al frontend (`lineas[].cantidad_bruta`/`redondeada`/`unidad`). El multiplo se configura por material desde `Pages/Materiales/Index.vue`. Tests: `CosteoProductoServiceTest` ("redondea…"). Columna: migración `2026_08_28_150839_add_redondeo_compra_to_material_table`, ver `schema.json`/`database-design.md` §6.
