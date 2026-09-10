---
paths:
  - resources/js/Components/DateRangeFilter.vue
---

# Components

## Filtro de rango de fechas: DateRangeFilter.vue
Único componente para "fecha desde / hasta" (el equivalente de BaseChart para calendarios). Envuelve `daterange-picker-vue3`. Expone `v-model:fecha-desde` / `v-model:fecha-hasta` en 'YYYY-MM-DD' (formato que esperan whereDate/whereBetween). Props: label, placeholder, opens, defaultRange, clearable, disabled, appendToBody, minDate, maxDate.

- El paquete (~80 KB, sólo build UMD) se carga con `import()` dinámico en onMounted → chunk propio `daterange-picker-vue3.umd.min-*.js`, no engorda app.js ni la página. Mientras carga se muestra un esqueleto no interactivo. NO volver a hacer `import ... from 'daterange-picker-vue3'` estático.
- TODO el CSS (incl. modo oscuro) vive en `resources/css/app.css` sección "29. FILTRO DE RANGO DE FECHAS", re-tematizando el CSS del paquete (importado arriba, junto a las fuentes de iconos) con tokens `--c-primary*`/`--card-bg`/`--text-*`. El componente NO lleva `<style>` (ver skill xtrapubli-design-system, Rule 3).
- El disparador usa `control-container-class="form-control date-range-filter-input"` → hereda el `.form-control` ya temado.
- Ejemplo en uso: `resources/js/Pages/Reportes/Financiero.vue`.
