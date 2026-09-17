---
paths:
  - 'app/Http/Controllers/CotizadorPublicoController.php, config/cotizador.php'
  - 'app/Http/Controllers/**'
---

# Controllers Views Publico

## Documento del cotizador y topes de descarga
`GET /cotizador/{codigo}/documento` sirve la estimación como PDF, con la misma forma que el presupuesto formal pero rotulada ESTIMACIÓN.

- **Lo genera `App\Services\Pdf\GeneradorPdf::cotizacionPublica()`, como todos los documentos del sistema** (FPDF; ver `.ai/rules/css.md`). No hay vista Blade imprimible ni `window.print()`: eso existió hasta el 2026-09-09 y se quitó junto con `publico/cotizacion-documento.blade.php`, su bloque de `publico.css` y el `[data-documento-imprimible]` de `publico.js`. No reintroducirlo.
- **Al visitante se le BAJA el archivo** (`attachment`), a diferencia del panel, que lo abre como vista previa: el código es su único hilo para volver a contactarnos y un PDF abierto en una pestaña se pierde al cerrarla.
- **Dos frenos y hacen falta los dos**: el rate limiter `cotizador-descargar` (por IP) y el tope por FILA `CotizacionPublica::puedeDescargar()` contra `config('cotizador.descargas_maximas')`. El limiter se renueva solo, así que sin el tope por fila un código válido alcanza para pedir el documento indefinidamente.
- `descargado_en` guarda la PRIMERA emisión y es lo que hace desaparecer el botón "Descargar" de `/cotizador/{codigo}`; `descargas` cuenta todas y es lo que aplica el tope.
- `cotizador-guardar` lleva TRES límites simultáneos (hora/IP, día/IP y un techo global por hora). Los `by()` van prefijados: varios límites en un mismo limiter necesitan claves distintas o comparten contador. El global es el único que frena una avalancha repartida entre muchas IP.

## Notificaciones internas (administrador/vendedor): ya construido (2026-09-17)
Sistema de notificaciones in-app (tabla nativa `notifications`, canal `database` únicamente, sin `ShouldQueue` — se envían síncronas). 6 eventos: para `administrador` — `OrdenCompraEmitida` (CompraController::store), `PagoRegistrado` (PagoController::store), `CotizacionSolicitada` (ClientePortalController::solicitarStore); para el vendedor dueño de la cotización de origen — `CotizacionAprobada` (CotizacionController::aprobar y ClientePortalController::responder), `AvancePedidoActualizado` (PedidoController::actualizarEstado, con guard anti-auto-notificación), `OrdenEntregaEmitida` (NotaEntregaController::store). Clases en `App\Notifications\*`, `toArray()` devuelve `titulo/mensaje/url/icono/color` (color mapea a `.stat-icon-{color}` existente).

TRAMPA: para notificar a los admins usar `User::administradores()` (app/Models/User.php), NUNCA `User::role('administrador')->get()` — el scope `role()` de Spatie LANZA `RoleDoesNotExist` si el rol aún no existe en BD (rompía CompraControllerTest/PortalClienteTest), y un efecto secundario de notificación no debe tumbar la operación principal. `Cotizacion::vendedor()`/`Pedido::vendedor()` resuelven el User del empleado dueño (null si no hay vendedor asignado o sin cuenta vinculada, en cuyo caso simplemente no se notifica).

`NotificacionController` (index/abrir/marcarTodasLeidas) sin permiso propio, dentro del grupo `auth`: cada quien ve solo sus propias notificaciones (`notifiable_id` scopeado a mano). `HandleInertiaRequests` comparte `notificaciones={no_leidas,recientes}` en cada visita para `Components/Layout/Topbar.vue`. Tests: tests/Feature/NotificacionesTest.php (10).
