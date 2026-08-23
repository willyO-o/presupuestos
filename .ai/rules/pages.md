---
paths:
  - 'resources/js/Pages/**/*.vue'
---

# Pages

## Pages usan MainDashboardLayout como layout persistente (Inertia v2)
Toda página bajo `resources/js/Pages/**` que se renderiza para un usuario autenticado (dashboard y cualquier área de administración/backoffice) debe declarar su layout con el patrón persistente de Inertia v2, no envolviendo el template en un componente:

```js
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
defineOptions({ layout: MainDashboardLayout });
```

Ver `resources/js/Pages/Dashboard.vue` como referencia. No uses `<AuthenticatedLayout>...</AuthenticatedLayout>` (Breeze legacy) para páginas nuevas — `AuthenticatedLayout.vue` queda obsoleto, cualquier página que aún lo use (p. ej. `Profile/Edit.vue`) debe migrarse a `MainDashboardLayout` con `defineOptions`.

Excepción: páginas públicas/no autenticadas (`Pages/Auth/**`, `Welcome.vue`) siguen usando `GuestLayout` o sin layout — no les aplica esta regla.

Clases y colores dentro de esas páginas: seguir la skill `xtrapubli-design-system` (clases tipo Bootstrap en `resources/css/app.css`), no Tailwind crudo ni componentes de Breeze.
