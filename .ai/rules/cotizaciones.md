---
paths:
  - app/Http/Controllers/CotizacionController.php
  - 'app/Http/Requests/Cotizacion/**'
  - app/Models/Cotizacion.php
  - app/Models/CotizacionDetalle.php
  - app/Services/Calculo/PrecioSugeridoService.php
  - 'resources/js/Pages/Cotizaciones/**'
  - database/seeders/CotizacionSeeder.php
  - config/cotizacion.php
  - app/Models/Pedido.php
---

# Cotizaciones

## Módulo de cotizaciones (presupuestos): ya construido (2026-08-28)

CRUD completo del encabezado `cotizacion` + líneas `cotizacion_detalle`, siguiendo los
patrones del proyecto. No reimplementar desde cero.

- **Rutas** (`routes/web.php`, todas bajo `auth` + `can:cotizaciones.*`): `index`, `create`,
  `store`, `show`, `edit`, `update`, `destroy`, `aprobar`, `rechazar` y `costear`
  (JSON, no Inertia). `cotizaciones/crear` y `cotizaciones/costear` se registran ANTES de
  `cotizaciones/{cotizacion}` para que no las tome como id.
- **Vista independiente, no modal** (info compleja, ver `.ai/rules/pages.md`):
  `Pages/Cotizaciones/Index.vue` (DataTable + `useServerTable`), `Create.vue` / `Edit.vue`
  que solo montan el partial compartido `Pages/Cotizaciones/Partials/CotizacionForm.vue`
  (form con líneas de detalle repetibles + panel de totales en vivo), y `Show.vue`
  (documento de presupuesto imprimible — `@media print` en `app.css` §22).
- **Cálculo de precio**: el botón "Calcular precio sugerido" de cada línea hace
  `POST /cotizaciones/costear` (axios) → `CotizacionController::costear` →
  `App\Services\Calculo\PrecioSugeridoService` (envuelve `CosteoProductoService`, suma el
  BOM y le agrega `config('cotizacion.margen_sugerido')`). El vendedor puede sobrescribir
  el `precio_unitario`. `costear` devuelve 422 si el producto necesita un driver
  (área para M2, ancho para METRO_LINEAL) que no recibió.
- **Los montos se calculan SIEMPRE en el servidor** (`normalizarDetalles` +
  `calcularMontos` en el controlador): `area_m2` = ancho×alto, `subtotal` línea =
  precio×cantidad, `total` = subtotal − descuento + impuesto (nunca negativo). El
  `codigo_verificacion` se genera en `store` (`COT-Ymd-XXXXX`). Los Form Requests
  rechazan `subtotal`/`total`/`codigo_verificacion`/`estado` que mande el cliente.
- **`descuento` e `impuesto` son montos, no porcentajes** (así lo define el esquema §8).
  El formulario tiene un atajo "IVA 13%" (`config('cotizacion.impuesto_porcentaje')`).
- **Estados**: `store` siempre crea `PENDIENTE`. `edit`/`update`/`aprobar`/`rechazar` solo
  operan sobre `PENDIENTE` (`Cotizacion::esEditable()`), si no redirigen con
  `->with('error', ...)`. `destroy` se bloquea si `CONVERTIDA`. `CONVERTIDA`/`VENCIDA`
  las setea el (futuro) módulo de Pedidos / un job de vencimiento, no este módulo.
- El detalle se **reemplaza entero** en cada `update` (`detalles()->delete()` +
  `createMany`) — simple y seguro mientras no exista un pedido que dependa de los ids.
- Datos de prueba: `CotizacionSeeder` (8 cotizaciones curadas con detalle real, precio vía
  `PrecioSugeridoService` cuando el producto tiene BOM; se salta si ya hay filas — no
  idempotente, como los demás seeders de volumen). Tests: `CotizacionControllerTest`.

## Flujo de producción: etapa CONTROL_CALIDAD y postventa automática
2026-09-08, para alinear el sistema con los 3 procesos documentados de la empresa:

1. Se agregó `CONTROL_CALIDAD` entre ACABADO y ENTREGADO en `pedido.estado`, `pedido_detalle.estado_item` y `pedido_seguimiento.etapa` (Proceso 2, paso 4). La migración usa `->change()` con `enum`, no SQL crudo: los tests corren en SQLite y ahí Laravel traduce el enum a un CHECK. Las constantes `Pedido::ESTADOS`/`FLUJO`, `PedidoDetalle::ESTADOS` y `PedidoSeguimiento::ETAPAS` son la fuente para el frontend (Pedidos/Show las recibe como props) — al agregar una etapa hay que tocar también los mapas de badges de Pedidos/Index, Pedidos/Show, Portal/Pedidos y el array `flujo` de Portal/Pedido.

2. `Pedido::recalcularEstado()` llama a `App\Services\Pedido\ProgramarPostventaService` cuando el pedido queda ENTREGADO: crea el `seguimiento_postventa` PENDIENTE a `config('postventa.dias_seguimiento')` días (Proceso 3, "Seguimiento 7 días"). Ese servicio es el ÚNICO lugar que crea seguimientos, y es idempotente (`pedido_id` único) — no crear filas a mano desde un controlador ni un seeder.
