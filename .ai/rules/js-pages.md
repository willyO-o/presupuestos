---
paths:
  - 'resources/js/Components/Chart/**, resources/js/Composables/UseChartTheme.js, resources/js/Pages/Reportes/**, resources/js/Pages/Dashboard.vue'
---

# Js Pages

## Paleta, colores y trampas de los gráficos de BaseChart
Todo gráfico del panel se dibuja con `Components/Chart/BaseChart.vue` (desde 2026-10-12 sobre Apache ECharts 6, ver la sección siguiente; antes Chart.js 4). Una página solo pasa `titulo`, `labels`, `series` y `formato`. Nunca instanciar un gráfico en otro lado ni pasarle colores a mano: el componente fija marcas, tooltip, leyenda, vista de tabla y export PNG. Reemplazó al SVG a mano `Chart/LineChart.vue` y a las barras CSS `.bar-chart` (ambos borrados, 2026-09-09).

**Colores**: tokens `--chart-1..6` en `app.css` (juegos distintos para claro y oscuro), leídos por `Composables/UseChartTheme.js` con `getComputedStyle` — ECharts dibuja en canvas y no entiende `var()`. Se asignan EN ORDEN y **no se ciclan**: pasadas 6 series hay que agrupar en "Otros" o partir el gráfico. La paleta se derivó de los tonos de marca y se validó con el script de la skill `dataviz` (banda de luminosidad, croma, separación protan/deutan, piso de visión normal y 3:1 contra `--card-bg`): los seis checks pasan en ambos modos. Tocar un hex a ojo la rompe — hay que revalidar.

**No confundir con los `--c-*` semánticos**: en un gráfico el color solo dice qué serie es. `positivo`/`negativo` (de `--c-success`/`--c-danger`) solo para series que significan bien/mal — el margen por pedido en Reportes/Financiero, donde además el signo se ve por el lado de la barra.

Trampas ya resueltas: `unir-huecos` existe porque un `null` normalmente es "no se sabe" (línea cortada) pero en la evolución de precios significa "siguió igual"; la evolución de costos arma el eje con la UNIÓN de fechas de todos los materiales, porque cada uno tiene sus propias compras. Un medidor (entregas a tiempo) y una fila de KPIs NO son gráficos: `.reporte-progress` y `.reporte-kpi`.

## Los gráficos salen de BaseChart (Apache ECharts 6) con la paleta --chart-* validada
Desde 2026-10-12 `Components/Chart/BaseChart.vue` dibuja con Apache ECharts 6 (reemplazó a Chart.js 4, desinstalado). Mismo contrato: una página solo pasa `titulo`, `labels`, `series`, `formato`; nunca `echarts.init` fuera de BaseChart ni colores a mano. ECharts se registra por piezas en `Components/Chart/EchartsCore.js` (línea, barra, grid, tooltip, markLine, markArea, aria + canvas) y se carga con `import()` en onMounted → chunk propio (~188 KB gzip); un tipo nuevo se registra ahí.
Extras: serie `tipo: 'banda'` (`inferior`/`superior`, va justo después de la serie que acompaña y toma su color sin gastar paleta) para el rango probable de una proyección; `eje: 'derecho'` + `formatoDerecho`; props `umbrales` (markLine, solo umbrales con significado: semáforo, base 100), `inicioProyeccion` (sombrea y rotula el futuro), `enteros` (minInterval 1 para conteos, si no ECharts pone pasos de 0,5 y los ejes se leen repetidos), `ajustarEscala` (solo índices/precios en línea, nunca barras). La leyenda HTML apaga/prende series; la tabla gemela siempre las muestra todas. ECharts no se redimensiona solo: BaseChart usa ResizeObserver. El tooltip es HTML → los nombres se escapan (vienen de la base).
Colores, `unir-huecos`, polaridad positivo/negativo y "medidor/KPI no es gráfico": igual que antes.
