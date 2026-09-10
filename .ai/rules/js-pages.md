---
paths:
  - 'resources/js/Components/Chart/**, resources/js/Composables/UseChartTheme.js, resources/js/Pages/Reportes/**, resources/js/Pages/Dashboard.vue'
---

# Js Pages

## Los gráficos salen de BaseChart (Chart.js 4) con la paleta --chart-* validada
Todo gráfico del panel se dibuja con `Components/Chart/BaseChart.vue` (Chart.js 4, importado con `import()` dentro de `onMounted` para que quede en su propio chunk — `app.js` no engorda). Una página solo pasa `titulo`, `labels`, `series` y `formato`. Nunca instanciar `Chart` en otro lado ni pasarle colores a mano: el componente fija marcas, tooltip, leyenda, vista de tabla y export PNG. Reemplazó al SVG a mano `Chart/LineChart.vue` y a las barras CSS `.bar-chart` (ambos borrados, 2026-09-09).

**Colores**: tokens `--chart-1..6` en `app.css` (juegos distintos para claro y oscuro), leídos por `Composables/UseChartTheme.js` con `getComputedStyle` — Chart.js dibuja en canvas y no entiende `var()`. Se asignan EN ORDEN y **no se ciclan**: pasadas 6 series hay que agrupar en "Otros" o partir el gráfico. La paleta se derivó de los tonos de marca y se validó con el script de la skill `dataviz` (banda de luminosidad, croma, separación protan/deutan, piso de visión normal y 3:1 contra `--card-bg`): los seis checks pasan en ambos modos. Tocar un hex a ojo la rompe — hay que revalidar.

**No confundir con los `--c-*` semánticos**: en un gráfico el color solo dice qué serie es. `positivo`/`negativo` (de `--c-success`/`--c-danger`) solo para series que significan bien/mal — el margen por pedido en Reportes/Financiero, donde además el signo se ve por el lado de la barra.

Trampas ya resueltas: `unir-huecos` existe porque un `null` normalmente es "no se sabe" (línea cortada) pero en la evolución de precios significa "siguió igual"; la evolución de costos arma el eje con la UNIÓN de fechas de todos los materiales, porque cada uno tiene sus propias compras. Un medidor (entregas a tiempo) y una fila de KPIs NO son gráficos: `.reporte-progress` y `.reporte-kpi`.
