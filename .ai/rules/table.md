---
paths:
  - 'resources/js/Components/Table/**'
---

# Table

## Tablas con paginación server-side usan DataTable.vue + useServerTable
Para cualquier listado con tabla (index de un recurso), usa `resources/js/Components/Table/DataTable.vue` (+ `TablePagination.vue` interno) en vez de escribir `<table>` a mano. Acepta `headers` como string HTML o como objeto `{ label, key, class, cellClass }`; columnas se personalizan con slots dinámicos `#cell-<key>`, la columna de botones con `#actions`, y para tablas muy complejas (rowspans, grids internos) hay un escape hatch `#tbody` que reemplaza el cuerpo entero. El paginador acepta el objeto de Laravel `paginate()` tal cual (snake_case) y muestra saltos de rango, no todas las páginas.

Para conectar filtros + paginación con una ruta Inertia, usa el composable `resources/js/Composables/UseServerTable.js` (`useServerTable({ url, filters, mode: 'auto'|'manual', only })`) en vez de escribir `router.get()` a mano en cada página — `mode: 'manual'` requiere llamar `table.search()` (botón "Buscar"); `mode: 'auto'` dispara la búsqueda solo al cambiar `table.filters` (debounced), para no sobrecargar el servidor en listados pesados. Ver el JSDoc de ambos archivos para el ejemplo completo de una página `Index.vue`.

Estilos de estas tablas (`.table-loading`, `.table-empty`, `.pagination*`, `.page-*`) viven en `app.css` sección "17. TABLA: ESTADOS Y PAGINACION" — no en `<style>` de los componentes (ver skill `xtrapubli-design-system`, Rule 3).
