---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Escritura acotada por sucursal: ValidaAlcanceSucursal en los Form Requests
Acotar la lectura no alcanza: el desplegable puede listar una sola sucursal y el navegador mandar igual el id de otra a mano. `Rule::exists` NO sirve para esto — comprueba que la sucursal exista, no que sea del usuario.

`App\Http\Requests\Concerns\ValidaAlcanceSucursal` da dos reglas (closures, como el resto del proyecto):
- `sucursalAdministrada()` → sobre `sucursal_id`. Ya en Store/UpdateCotizacionRequest y Store/UpdateEmpleadoRequest. En el Update cubre el caso que se escapa fácil: MOVER un registro propio a una sucursal ajena.
- `registroVisible(Modelo::class, $mensaje)` → sobre una FK (`cotizacion_id`, `pedido_id`). Ya en StorePedidoRequest, StorePagoRequest, StoreNotaEntregaRequest y StoreOrdenCompraClienteRequest. Si el registro no existe no falla: eso es asunto de `Rule::exists`.

Ambas preguntan al MISMO helper que el listado (`puedeVerSucursal` / `esVisiblePara`). Si la validación y el scope pudieran discrepar aparecería el "lo creo pero después no lo puedo abrir" — hay un test que lo fija ("lo que se ve se puede editar").

**Acciones sobre un registro ya identificado** (no son Form Request): `PedidoController::assertPuedeOperarSobre()` cubre las 4 rutas de ítem (área/estado/medidas/consumo) + `cancelar`; `SeguimientoPostventaController::registrar`, `OrdenCompraClienteController::update/validar/anular` y `EmpleadoController::update/destroy` usan `esVisiblePara`. La fase 3 había acotado solo `show` y estas rutas cambiaban el estado de un pedido ajeno con su id en la URL.

**Orden de ejecución**: el Form Request se resuelve ANTES del cuerpo del controlador, así que un payload inválido da 302 por validación y no 403. No es un agujero (la escritura sigue detrás del guarda) pero hay que mandar payload completo para probar el guarda.

Tests: `AlcanceEscrituraTest` (11). Verificado con mutación: quitar las reglas de los Requests tumba 5; revertir `assertPuedeOperarSobre` tumba la de los ítems de pedido.
