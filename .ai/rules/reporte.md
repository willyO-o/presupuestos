---
paths:
  - 'app/Services/Reporte/**'
---

# Reporte

## Dashboard + Reportes + BI: ya construido (2026-08-31)
4 services en `App\Services\Reporte\`: `ResumenDashboardService` (KPIs del panel), `ReporteFinancieroService` (ventas por mes/sucursal, rentabilidad real = ingreso vs. `pedido_detalle_material.costo_real`, cuentas por cobrar), `ReporteProduccionService` (cumplimiento de entregas, duración por etapa desde `pedido_seguimiento`, carga por área), `InteligenciaNegociosService` (productos/categorías más vendidos, evolución de costos desde `historial_precio_material`, proyección de demanda = media móvil 3m + regresión lineal mínimos cuadrados, estacionalidad). **Toda agregación es en PHP con Collections** (`->get()->groupBy(fn=>$fecha->format('Y-m'))`), NO con `DATE_FORMAT`/`strftime` — así funciona igual en MariaDB (prod) y sqlite (tests). `ReporteController` (dashboard reemplaza el closure de `/dashboard`; financiero/produccion/bi bajo `can:reportes.*`). `/dashboard` NO lleva `can:dashboard.ver` a propósito (todo usuario autenticado lo ve, para no romper los tests de auth de Breeze). Frontend: `Components/Chart/LineChart.vue` (SVG inline, sin librería, color por `var(--c-*)`), `Pages/Reportes/{Financiero,Produccion,Bi}.vue`, Dashboard reescrito con datos reales. CSS §26. Tests: ReporteControllerTest (3), InteligenciaNegociosServiceTest (3).
