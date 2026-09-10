---
paths:
  - 'app/Services/Reporte/**'
---

# Reporte

## Dashboard + Reportes + BI: ya construido (2026-08-31)
4 services en `App\Services\Reporte\`: `ResumenDashboardService` (KPIs del panel), `ReporteFinancieroService` (ventas por mes/sucursal, rentabilidad real = ingreso vs. `pedido_detalle_material.costo_real`, cuentas por cobrar), `ReporteProduccionService` (cumplimiento de entregas, duración por etapa desde `pedido_seguimiento`, carga por área), `InteligenciaNegociosService` (productos/categorías más vendidos, evolución de costos desde `historial_precio_material`, proyección de demanda = media móvil 3m + regresión lineal mínimos cuadrados, estacionalidad). **Toda agregación es en PHP con Collections** (`->get()->groupBy(fn=>$fecha->format('Y-m'))`), NO con `DATE_FORMAT`/`strftime` — así funciona igual en MariaDB (prod) y sqlite (tests). `ReporteController` (dashboard reemplaza el closure de `/dashboard`; financiero/produccion/bi bajo `can:reportes.*`). `/dashboard` NO lleva `can:dashboard.ver` a propósito (todo usuario autenticado lo ve, para no romper los tests de auth de Breeze). Frontend: `Components/Chart/LineChart.vue` (SVG inline, sin librería, color por `var(--c-*)`), `Pages/Reportes/{Financiero,Produccion,Bi}.vue`, Dashboard reescrito con datos reales. CSS §26. Tests: ReporteControllerTest (3), InteligenciaNegociosServiceTest (3).

## Reportes y dashboard acotados por sucursal (y qué queda global)
Desde la fase 5 (2026-09-10) los CUATRO servicios reciben `User $usuario` y pasan cada consulta por `visiblePara($usuario)`: `ResumenDashboardService::resumen()`, `ReporteFinancieroService::datos()` (el usuario va PRIMERO, antes de $desde/$hasta), `ReporteProduccionService::datos()`, `InteligenciaNegociosService::datos()`. Antes ni siquiera recibían el usuario: un contador de una sucursal veía la facturación consolidada.

Es donde más daño hace equivocarse: en un listado el acotado se nota (faltan filas), en un KPI o un gráfico no hay con qué comparar, así que un total filtrado se lee como el de la empresa entera y nadie lo cuestiona.

**Lo que NO se acota** (esas tablas no tienen `sucursal_id`; el inventario es de toda la empresa) y por eso va ROTULADO en pantalla:
- `materiales_bajo_stock` del dashboard → `.stat-global` ("Toda la empresa") bajo el KPI.
- `evolucion_costos` del BI (cuelga de `material` vía `historial_precio_material`) → `<EtiquetaAlcance global texto="Inventario" />`.

`resources/js/Components/EtiquetaAlcance.vue` rotula el alcance de cada pantalla leyendo `auth.sucursales`. Está en Dashboard y en los 3 reportes. No es un permiso: el filtrado real es del servidor.

Dos modelos más ganaron el trait para esto: `CotizacionDetalle` (`cotizacion`) y `PedidoSeguimiento` (`pedidoDetalle.pedido.cotizacion`, la cadena más larga del esquema).

**TRAMPA al escribir tests de reportes**: `AreaFactory` usa `fake()->unique()->randomElement()` sobre SIETE nombres y `SucursalFactory` `unique()->city()` — a la octava área revienta con "Maximum retries of 10000". Hay que `recycle()` sucursal y área en vez de dejar que las cadenas de factory creen filas sueltas (que además falsearían el conteo del propio reporte). Ver `montarMovimiento()` en AlcanceReportesTest.

Tests: `AlcanceReportesTest` (8). Verificado con mutación: quitar los `visiblePara` de los cuatro servicios tumba 5 de 8.
