---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/FormulaController.php
  - app/Http/Controllers/CompraController.php
  - app/Http/Controllers/PedidoController.php
  - app/Http/Controllers/NotaEntregaController.php
  - app/Http/Controllers/PagoController.php
  - app/Http/Controllers/UsuarioController.php
  - app/Http/Controllers/ClientePortalController.php
  - app/Http/Controllers/CotizacionController.php
---

# Controllers

## Convenciones de controlador: helper inertia(), request()->user(), redirect()->route()
En este proyecto, dentro de controladores:

- **Vistas Inertia**: usar el helper global `inertia('Carpeta/Componente', [...])`, no la fachada `Inertia::render(...)`. Sin `use Inertia\Inertia;` — solo `use Inertia\Response;` para el type hint del método si aplica.
- **Usuario autenticado**: usar `$request->user()`, no `Auth::user()` ni `auth()->user()`.
- **Redirecciones**: usar el helper `redirect()->route(...)` / `redirect()->to(...)` / `redirect()->intended(...)`, no la fachada `Redirect::route(...)` / `Redirect::to(...)`.

Ya se aplicó esto a todos los controladores existentes (`ProfileController` y los de `Auth/*` generados por Breeze, que traían `Inertia::render()` y, en `ProfileController`, `Redirect::`) — cualquier controlador nuevo debe seguir el mismo patrón desde el inicio.

## CRUD de Fórmulas y Receta/BOM de Producto: ya construido (2026-08-25)
Ya existe UI completa para gestionar el motor de fórmulas dinámicas (ver `.ai/rules/calculo.md`), construida siguiendo los patrones existentes del proyecto — no reimplementar desde cero ni asumir que falta:

- **Catálogo de fórmulas** (`formulas.ver/crear/editar/eliminar` en `config/acl.php`, solo rol `administrador`): `FormulaController` (CRUD estándar, patrón `AreaController`) + `Http/Requests/Formula/{Store,Update}FormulaRequest` (la regla de `expresion` es un Closure que llama `FormulaCalculator::mensajeError()`, no reinventar la validación) + `Pages/Formulas/Index.vue` (DataTable + Modal, con un tester "Probar fórmula" que llama a `POST /formulas/probar` vía axios — endpoint JSON aparte, no un visit de Inertia, ver `FormulaController::probar()`).
- **Receta/BOM de un producto** (bajo el mismo permiso `productos.editar`, ver comentario en `config/acl.php`): `ProductoMaterialController` con rutas anidadas `/productos/{producto}/materiales/*` + `Http/Requests/ProductoMaterial/{Store,Update}ProductoMaterialRequest` (XOR formula_id/cantidad_por_unidad validado en `withValidator()`, no en las rules) + `Pages/Productos/Receta.vue` — vista independiente (no modal) por ser información compleja, alcanzable desde un botón nuevo en `Pages/Productos/Index.vue` (icono `fa-flask`). `ProductoMaterialController` verifica a mano que la línea pertenezca al producto de la URL (`assertPerteneceAProducto()`) porque el binding anidado de Laravel no scopea solo.

Tests: `FormulaControllerTest`, `ProductoMaterialControllerTest`. Antes de tocar cualquiera de estos archivos, léelos — ya resuelven el flujo completo crear fórmula → armar receta → (falta) usar `CosteoProductoService` desde el módulo de Cotización.

## Módulo de Compras: ya construido (2026-08-31)
CRUD de encabezado `compra` + líneas `compra_detalle`, patrón CotizacionController (vista independiente Create/Edit + partial `Pages/Compras/Partials/CompraForm.vue`, Show imprimible). Rutas `/compras*` bajo `can:compras.{ver,crear,editar,eliminar,aprobar}` (se agregaron `compras.editar`/`compras.eliminar` a config/acl.php y a administrador/contador). Montos SIEMPRE en el servidor (`normalizarDetalles`/`calcularTotal`); Form Requests rechazan total/estado. Estados: store crea PENDIENTE; edit/update solo PENDIENTE; destroy bloquea PAGADA. `aprobar` (PENDIENTE→PAGADA) delega en `App\Services\Compra\AprobarCompraService` — sube `material.stock_actual`, actualiza `precio_unitario`/`precio_presentacion` (escalado proporcional) y escribe `historial_precio_material` (insumo del BI); NO reimplementar esa lógica, la usa también CompraSeeder. `anular` PENDIENTE→ANULADA. `Material::conStockBajo()` scope + append `stock_bajo` + filtro en Materiales/Index. Tests: CompraControllerTest (12).

## Módulo de Pedidos / órdenes de trabajo: ya construido (2026-08-31)
Modelos Pedido/PedidoDetalle/PedidoSeguimiento/PedidoDetalleMaterial (+ factories). Conversión cotización→pedido en `App\Services\Pedido\ConvertirCotizacionService` (usada por PedidoController::store y PedidoSeeder): exige `Cotizacion::esConvertible()` (APROBADA + sin pedido), copia detalle a pedido_detalle, deja cotización CONVERTIDA. Botón "Convertir en pedido" en Cotizaciones/Show (cuando APROBADA). Rutas `/pedidos*` + subrutas `/pedidos/{pedido}/detalle/{detalle}/{asignar-area,estado,consumo}` con `assertPerteneceAlPedido` (404 si el detalle no es del pedido). Scoping por sucursal: `Pedido::visiblePara(user)` scope + `PedidoController::puedeVer()` — un usuario sin `pedidos.ver_todas_sucursales` solo ve pedidos de la sucursal de su ficha de empleado (`users` → `empleado`). `Pedido::recalcularEstado()` = estado global = etapa MENOS avanzada de los ítems; todo ENTREGADO ⇒ setea `fecha_entrega_real`. `pedido_seguimiento.etapa` usa ENTREGA (no ENTREGADO). Show trae comparativa costo presupuestado (CosteoProductoService sobre el BOM de la cotización de origen) vs. `pedido_detalle_material.costo_real`. ACL: `pedidos.crear` añadido a vendedor/secretaria. Tests: PedidoControllerTest (15). CSS §24.

## Documentos comerciales (OC de cliente + Notas de entrega): ya construido (2026-08-31)
**OrdenCompraCliente** (1:1 con `pedido`, único): `OrdenCompraClienteController` (index con `pedidosSinOc`, store deriva `cliente_id` de `pedido.cotizacion.cliente_id`, update, validar/anular solo PENDIENTE) + Requests. Página `OrdenesCompraCliente/Index.vue` (DataTable + Modal crear/editar con subida de PDF por POST+`_method`). Rutas `/ordenes-compra-cliente*` bajo `can:ordenes-compra-cliente.{ver,crear,validar}` (sin permisos nuevos). Panel en Pedidos/Show. **NotaEntrega + NotaEntregaDetalle**: `NotaEntregaController` (index, create `?pedido=`, store, show imprimible). `numero_nota` = NE-Ymd-XXXXX autogenerado. `store` (transacción): crea nota+detalles, sube foto por línea (`detalles.{i}.foto`), marca esos `pedido_detalle` como ENTREGADO y llama `Pedido::recalcularEstado()`. Páginas `NotasEntrega/{Index,Create,Show}`, botón "Emitir nota de entrega" en Pedidos/Show. Fotos/PDF en disco `public` (`notas-entrega/`, `notas-entrega/fotos/`, `ordenes-compra/`). Accesores `archivo_url`/`foto_publica_url`. Seeders: OrdenCompraClienteSeeder, NotaEntregaSeeder. Tests: OrdenCompraClienteControllerTest (7), NotaEntregaControllerTest (8).

## Módulo de Pagos: ya construido (2026-08-31)
Modelo `Pago` (tabla `pago`) + factory. `PagoController` (index con resumen total cobrado/por cobrar + filtros estado/método; store). No hay create/edit/destroy: los pagos se registran desde `Pedidos/Show` (modal, panel "Cobranza") o desde `Pagos/Index`. `pago.estado` = estado del saldo del pedido tras ese pago (PARCIAL/PAGADO), lo calcula el controlador — el Form Request lo rechaza. Helpers en `Pedido`: `totalPagado()`, `saldo()`, `estadoPago()` (PENDIENTE/PARCIAL/PAGADO). `store` redirige con `back()` (se llama desde varias pantallas). Comprobante (img/pdf) a disco `public` (`comprobantes-pago/`), accesor `comprobante_publico_url`. Rutas `/pagos` (index) y `/pagos` POST (store) bajo `can:pagos.{ver,registrar}`. Seeder: PagoSeeder (anticipo 50% + saldo en entregados, parcial en acabado). Tests: PagoControllerTest (7).

## Módulos Usuarios y Roles: ya construidos (2026-08-31)
**Usuarios**: `UsuarioController` CRUD sobre `users` (name/email/password/foto/estado + UN rol Spatie vía `syncRoles`). `users.estado` ENUM ACTIVO/INACTIVO (ya en `#[Fillable]`, `User::ESTADOS`, `User::estaActivo()`); `LoginRequest::authenticate()` bloquea el login de una cuenta INACTIVO (logout + ValidationException). `destroy` protege la propia cuenta y el último super-admin. Página `Usuarios/Index.vue` (Modal, foto por POST+`_method`). Rutas `/usuarios*` bajo `can:usuarios.{ver,crear,editar,eliminar}`. **Roles**: `RolController` (index con counts, create/store, edit/update matriz, destroy) — pinta `config('acl.modules')` como grupos de checkboxes en `Roles/Edit.vue`. El rol `super-admin` NO se edita/borra desde la UI (constante `PROTEGIDO`, 403 en update, redirect en edit). `destroy` bloquea roles con usuarios. Tras `syncPermissions` se llama `app(PermissionRegistrar::class)->forgetCachedPermissions()`. **TRAMPA**: `RolesAndPermissionsSeeder` borra de la BD cualquier Role/Permission que no esté en `config/acl.php` — un rol creado desde la UI se pierde al reseedear. Para un rol permanente, agregarlo también a `config/acl.php`. Tests: UsuarioControllerTest (8), RolControllerTest (9). `Cliente` ganó `user_id` en fillable + relaciones `user()`/`cotizaciones()`; `User` ganó `cliente()`.

## Portal del cliente + verificación pública: ya construido (2026-08-31)
**Verificación pública**: `GET /verificar/{codigo}` (fuera de `auth`, `where('codigo','[A-Za-z0-9\-]+')`) → `VerificacionController` → `Pages/Verificar/Show.vue` (sin layout de dashboard, usa `.verificar-shell`). Enlazar el código para que el cliente verifique autenticidad. **Portal**: grupo `Route::middleware(['auth','role:cliente'])->prefix('portal')->name('portal.')` — el alias `role`/`permission`/`role_or_permission` de Spatie se registró en `bootstrap/app.php`. `ClientePortalController` scopea TODO por `$request->user()->cliente->id` **inline** (`abort_unless(... === $this->clienteId($request))`), NO con Policy — consistente con el resto del proyecto que no usa Policies (ver PedidoController::puedeVer). Sin ficha de cliente → 403. `responder` aprueba/rechaza solo cotización PENDIENTE propia con total > 0. `solicitarStore` crea `cotizacion` PENDIENTE con `empleado_id = null` (por eso la migración `make_empleado_id_nullable_on_cotizacion_table` — dropForeign + change + re-add nullOnDelete), `sucursal_id` = primera activa, montos en 0 (ventas les pone precio). `Layouts/ClientePortalLayout.vue` (topbar propio, no el sidebar de staff). Post-login: `AuthenticatedSessionController::store` + `ReporteController::dashboard` redirigen el rol `cliente` a `portal.cotizaciones`. `ClienteSeeder` crea 2 usuarios portal (`cliente0@gmail.com`/`cliente1@gmail.com`, `cliente123`) fuera de producción. CSS §27. Tests: PortalClienteTest (9), VerificacionPublicaTest (2).

## Cotización: hoja de costos por línea, precio del motor, IVA server-side
Refactor 2026-09-08: cada `cotizacion_detalle` es una hoja del Excel de margen. Sus insumos viven en `cotizacion_detalle_item` (tipo/descripcion/unidad/cantidad/costo_unitario/subtotal, columnas A-E), y describen UNA unidad — `cantidad` los multiplica.

Flujo en `normalizarDetalles()`: costo_base unitario = Σ items → `MotorMargenService::calcularCon(tipo)` → precio_unitario. El precio que manda el navegador SE IGNORA salvo que la línea traiga `precio_manual = 'SI'` (o no tenga items: sin hoja de costos el precio solo puede ser manual, eso mantiene vivas las líneas de reventa y el portal del cliente). `factor_complejidad`/`margen_aplicado` se copian a la línea como foto histórica: editar el CRUD de tipos NO recalcula presupuestos ya emitidos.

`calcularMontos()` agrega y vuelve a pasar por el motor: `impuesto` ES el IVA calculado (bandera `aplicar_iva`, default true) — ya NO es un monto libre del cliente; `descuento` baja la base imponible y por eso empuja el semáforo; `total` = subtotal − descuento + IVA + instalación. `estado_margen`/`recomendacion`/`it`/`iue`/`utilidad_real` son cache, se sobreescriben en cada guardado.

Endpoints JSON: `costear` (BOM → `insumos` listos para precargar la hoja + `motor`) y `simular` (motor sobre un costo escrito a mano). Registrar ambos ANTES de `cotizaciones/{cotizacion}`.

## pedido_detalle guarda medidas REALES de producción (no es una copia muerta)
`pedido_detalle` copia descripción/ancho/alto/cantidad de `cotizacion_detalle` además de guardar la FK. Eso NO es redundancia: desde 2026-09-09 esos campos son editables desde `PedidoController::actualizarMedidas` (ruta `PUT /pedidos/{pedido}/detalle/{detalle}/medidas`, permiso `pedidos.actualizar_estado`, botón "Ajustar medidas reales" en Pedidos/Show) — el taller mide la pieza terminada y la cotización queda intacta como documento histórico.

Reglas: el precio NO se recalcula (lo acordado con el cliente no cambia porque la pieza salga distinta), por eso `pedido.total` sigue congelado desde la cotización. El ajuste se anota en la última etapa de `pedido_seguimiento` del ítem con su motivo. Bloqueado si el pedido está ENTREGADO o CANCELADO (`esCancelable()`).

Antes de "simplificar" quitando esas columnas: leerlas de la cotización rompería el caso de uso. Tests en `PedidoControllerTest` ("production measurements can diverge…").

Aparte: `Pedido::visiblePara()` falla cerrado (`whereRaw('1=0')`) si el usuario no tiene ficha de empleado — un usuario sin vincular ve CERO pedidos. `PedidoController::index` manda `sinFichaEmpleado` para explicarlo en pantalla, y `EmpleadoSeeder` vincula las cuentas sembradas. No cambiar el fallo cerrado por uno abierto.

## El alcance por sucursal se configura en Usuarios (no en Empleados)
`users.alcance_sucursal` + el pivote `sucursal_user` se editan desde el modal de `Usuarios/Index.vue` (bloque `.alcance-bloque`, CSS §25), no desde Empleados: es un permiso de la CUENTA, no un dato de la persona. La ficha de empleado solo aporta la sucursal "propia".

- `alcance_sucursal` es `required` en Store/UpdateUserRequest (sin default): un formulario nuevo tiene que decidirlo explícitamente. `sucursales` es `required_if:alcance_sucursal,ASIGNADAS` — un array vacío cuenta como ausente, que es justo lo que se quiere (guardar ASIGNADAS sin nada marcado dejaría una cuenta que no ve NADA en silencio).
- `UsuarioController::sincronizarSucursales()` VACÍA el pivote con cualquier alcance que no sea ASIGNADAS. Deliberado: una fila huérfana no hace nada hoy pero devolvería esas sucursales de golpe si alguien vuelve a poner ASIGNADAS. Después llama `olvidarAlcanceSucursal()` (el alcance se memoriza por instancia).
- El selector NO se deshabilita para roles globales (super-admin/administrador): un input deshabilitado no se envía y haría fallar `required`. Se muestra un aviso y el valor se guarda igual, para que tenga efecto si mañana se le cambia el rol.
- `HandleInertiaRequests` comparte `auth.sucursales = {ve_todas, visibles}`. `visibles` es `null` cuando ve todas — así no se consulta la tabla en el caso más común. Es solo para ROTULAR pantallas ("Viendo: El Alto"); el filtrado real es del servidor.
- `.alcance-aviso` NO puede ser flex: la frase lleva `<strong>` intercalados y cada trozo se volvía un ítem flex ("El / rol / administrador" apilado en columnas). Es bloque con el ícono inline.

Tests: `UsuarioControllerTest` (16, incluye el helper `datosUsuario()` para el payload).

## Módulos acotados por sucursal: qué lleva visiblePara y qué no
Desde la fase 3 (2026-09-10) SIETE modelos usan `AcotaPorSucursal`: Cotizacion y Empleado (columna propia), Pedido (`cotizacion`), y NotaEntrega / Pago / OrdenCompraCliente / SeguimientoPostventa (`pedido.cotizacion`). Cada listado lleva `->visiblePara($request->user())`.

- **Los agregados también van acotados, no solo la tabla.** `PagoController::index` (total_cobrado / por_cobrar) y `SeguimientoPostventaController::resumen()` pasan por `visiblePara`. Un total se lee de un vistazo sin comprobar de dónde sale: acotar la lista y no el resumen es peor que no acotar nada.
- **Rutas de detalle** (`show`/`edit`/`update`/`destroy`/`aprobar`/PDF) usan `$modelo->esVisiblePara($user)`, que vuelve a la base con el MISMO scope. NO comparar `sucursal_id` a mano: eso era lo que había en `PedidoController::puedeVer()` y `DocumentoPdfController` duplicado, y es como aparece el "lo veo en la lista pero me da 403".
- **NO se acotan** (no tienen `sucursal_id`): `compra`, `material`, `proveedor`, `cliente`, `producto`. Inventario y compras son de toda la empresa; la cartera de clientes es compartida. Decisión del usuario, ver .ai/rules/concerns.md.
- `PedidoController::create` acota las cotizaciones convertibles con las reglas de COTIZACIÓN, no con `pedidos.ver_todas_sucursales`: ese override es de lectura de pedidos ajenos, y convertir la cotización de otra sucursal es escribir sobre su cartera.
- Los desplegables de sucursal (filtro del índice y selector del formulario) se filtran con `CotizacionController::sucursalesVisibles()`. Un filtro que siempre devuelve vacío parece un bug.
- **Fallar cerrado tiene coste de UX**: `MainDashboardLayout` muestra un banner (`.alcance-aviso-warning`) cuando `auth.sucursales` dice que la cuenta no alcanza ninguna. Va en el layout y no en cada página: son 6 pantallas con la misma causa, y un listado vacío sin explicación se lee como "no hay datos".
- Los tests de cada módulo usan cuentas con alcance TODAS a propósito (comprueban el módulo). El acotado se prueba aparte en `AlcanceModulosTest` (14) — verificado con mutación: quitar los `visiblePara` tumba 9, quitar los `assertVisible` del PDF tumba la de la puerta trasera.
