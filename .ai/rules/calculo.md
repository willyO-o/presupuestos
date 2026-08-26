---
paths:
  - 'app/Services/Calculo/**'
---

# Calculo

## Motor de fórmulas dinámicas (nxp/math-executor): no reimplementar la evaluación a mano
`App\Services\Calculo\FormulaCalculator` envuelve `NXP\MathExecutor` (paquete `nxp/math-executor`, agregado 2026-08-25 con aprobación del usuario) para evaluar `formula.expresion`. Variables disponibles: `ancho`/`alto`/`profundo`/`area`/`perimetro`, armadas por `MedidasCotizacion::variables()` (`area`=ancho×alto, `perimetro`=(ancho+alto)×2 — solo se incluyen si ancho/alto están presentes; una fórmula que referencia una variable no disponible lanza `App\Exceptions\FormulaInvalidaException`, no calcula con 0 en silencio). `cantidad` (unidades pedidas) NO es variable de fórmula — la aplica `CosteoProductoService` como multiplicador uniforme afuera.

`App\Services\Calculo\CosteoProductoService::calcular(Producto, MedidasCotizacion, cantidad)` es el punto de entrada para costear un producto completo (recorre su BOM, mezcla líneas estáticas y dinámicas, devuelve `ResultadoCosteo` con el total y el desglose por línea). El futuro módulo de Cotización debe usar este servicio, no reimplementar la suma del BOM a mano. Ver `.ai/rules/migrations.md` para el diseño completo y los seeders de datos de prueba.
