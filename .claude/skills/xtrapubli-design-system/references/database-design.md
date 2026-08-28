# Diseño de Base de Datos — Sistema Web de Costos y Presupuestos XtraPubli

**Stack:** Laravel + Vue 3 (Inertia) + MySQL + Spatie Laravel-Permission
**Versión:** actualizada conforme al archivo `xtrapubli_bd_schema.json`

Este diseño se basa en:
- El documento del proyecto de grado (proceso: cotización → confirmación → orden → definición de área → diseño → elaboración → acabado → entrega).
- La hoja de costos de materiales (Gigantografía, Cerrajería, Carpintería, Otros materiales, Pinturas).
- Las Órdenes de Compra y Notas de Entrega reales de la empresa (bastidores, banners, vinyl adhesivo, gigantografías, con medidas, cantidades y precios por unidad).
- Los servicios publicados en xtrapubli.com (exhibidores, material POP, letreros luminosos, fachadas, rotulado vehicular, toldos, implementación en punto de venta).
- El organigrama de la empresa (Gerente → Ventas, Diseño, Imprenta/Producción → Contador, Secretaria, Atención al cliente, Diseñador, Gigantografía, Producción, Obreros).

---

## 1. Convenciones de nomenclatura

| Regla | Detalle |
|---|---|
| Tablas propias del dominio | **singular** y **minúsculas**, snake_case (`empleado`, `cotizacion_detalle`, `pedido_seguimiento`) |
| Tablas de Laravel / Spatie Laravel-Permission | se conservan con su nombre y **plural** original (`users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) para no romper la funcionalidad del framework/paquete |
| Campos de estado que antes eran boolean | ahora son `ENUM` en **mayúsculas**: `'ACTIVO'` / `'INACTIVO'` (o el valor que corresponda al contexto) |
| Resto de campos tipo enum | también en **mayúsculas** (ej. `'PENDIENTE'`, `'APROBADA'`, `'DISENO'`) |
| Fotos de perfil | tabla `users` incluye el campo `foto` |

> Motivo de estas reglas: al estandarizar los valores en mayúsculas se evitan inconsistencias de texto (`"activo"` vs `"Activo"` vs `"ACTIVO"`) que rompen los `GROUP BY`/`WHERE` de los reportes del módulo de Inteligencia de Negocios. Usar `ENUM` en vez de `boolean` para estados también permite agregar más valores en el futuro (ej. `'SUSPENDIDO'`) sin cambiar el tipo de columna.

---

## 2. Módulos del sistema

| Módulo | Tablas | Propósito |
|---|---|---|
| Seguridad y accesos | `users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Autenticación, roles y permisos |
| Organización | `sucursal`, `area`, `empleado` | Estructura interna de la empresa |
| Clientes | `cliente` | Empresas/clientes, con o sin portal |
| Materiales e insumos | `categoria_material`, `material`, `historial_precio_material`, `proveedor`, `compra`, `compra_detalle` | Catálogo de insumos, precios, stock, compras |
| Catálogo de productos | `categoria_producto`, `producto`, `producto_material`, `formula` | Productos publicitarios y su receta de costo (BOM), con fórmulas dinámicas para consumos que dependen de varias medidas a la vez |
| Cotizaciones | `cotizacion`, `cotizacion_detalle` | Presupuestos entregados al cliente |
| Pedidos / órdenes de trabajo | `pedido`, `pedido_detalle`, `pedido_seguimiento`, `pedido_detalle_material` | Ejecución del pedido por áreas |
| Documentos comerciales | `orden_compra_cliente`, `nota_entrega`, `nota_entrega_detalle` | Respaldo formal y evidencia de entrega |
| Pagos | `pago` | Registro de cobros |

**30 tablas en total** (6 de Laravel/Spatie + 24 propias del dominio — `formula` se agregó el 2026-08-25, ver §7).

---

## 3. Seguridad: Roles y permisos (Spatie Laravel-Permission)

Se usan las tablas estándar del paquete, guard `web` para todos (staff **y** clientes con portal, diferenciados por rol).

### `users`

| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, único | |
| email_verified_at | timestamp, nullable | |
| password | string | |
| **foto** | string, nullable | ruta/URL de la foto de perfil |
| **estado** | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | controla si el usuario puede iniciar sesión |
| remember_token | string, nullable | |
| created_at / updated_at | timestamp | |

### 3.1 Roles (según cargos del organigrama)

`ADMINISTRADOR`, `VENDEDOR`, `DISENADOR`, `JEFE_PRODUCCION`, `OPERARIO_PRODUCCION`, `CONTADOR`, `SECRETARIA`, `CLIENTE`.

### 3.2 Permisos sugeridos (agrupados por módulo)

```
cotizacion.ver          cotizacion.crear        cotizacion.editar
cotizacion.aprobar      cotizacion.eliminar

pedido.ver                pedido.crear             pedido.asignar_area
pedido.actualizar_estado  pedido.ver_todas_sucursales

material.ver             material.gestionar      material.ver_costos
compra.crear               compra.aprobar

producto.gestionar

nota_entrega.crear        nota_entrega.ver

pago.registrar             pago.ver

reporte.financiero        reporte.produccion       reporte.bi

usuario.gestionar          rol.gestionar             cliente.gestionar
```

### 3.3 Matriz rol ↔ permisos (resumen)

| Permiso | Admin | Vendedor | Diseñador | Jefe Prod. | Operario | Contador | Secretaria | Cliente |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| cotizacion.crear/editar | ✔ | ✔ | | | | | ✔ | |
| cotizacion.aprobar | ✔ | | | | | | | (aprueba la propia) |
| pedido.asignar_area | ✔ | | ✔ | ✔ | | | | |
| pedido.actualizar_estado | ✔ | | ✔ | ✔ | ✔ (su etapa) | | | |
| material.gestionar | ✔ | | | ✔ | | ✔ | | |
| compra.crear/aprobar | ✔ | | | | | ✔ | | |
| nota_entrega.crear | ✔ | | | ✔ | ✔ | | | |
| pago.registrar | ✔ | | | | | ✔ | | |
| reporte.bi | ✔ | | | | | ✔ | | |
| usuario/rol.gestionar | ✔ | | | | | | | |
| (portal) ver sus cotizaciones/pedidos | | | | | | | | ✔ |

> El rol `CLIENTE` no comparte permisos con el staff: en Inertia se separan layouts/rutas por `role:CLIENTE` vs el resto, y las policies filtran datos por `cliente_id` propio.

---

## 4. Organización

### `sucursal`
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string |
| direccion | string |
| ciudad | string |
| telefono | string, nullable |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` |
| created_at / updated_at | timestamp |

### `area` (departamentos de producción/administración)
Valores tentativos: `VENTAS`, `DISENO`, `GIGANTOGRAFIA`, `CERRAJERIA`, `CARPINTERIA`, `ACABADO`, `ADMINISTRACION`.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string |
| descripcion | string, nullable |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` |
| created_at / updated_at | timestamp |

### `empleado`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → `users.id`, nullable | no todo empleado necesita login |
| sucursal_id | bigint FK → `sucursal.id` | |
| area_id | bigint FK → `area.id` | |
| nombre_completo | string | |
| ci | string, único | |
| cargo | string | texto libre (ej. "Diseñador Gráfico") |
| telefono | string, nullable | |
| fecha_ingreso | date | |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | |
| created_at / updated_at | timestamp | |

---

## 5. Clientes

### `cliente`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → `users.id`, nullable | solo si tiene acceso al portal |
| tipo | `ENUM('NATURAL','JURIDICO')` | |
| razon_social | string | |
| nit | string, único | |
| contacto_nombre | string, nullable | |
| telefono | string, nullable | |
| email | string, nullable | |
| direccion | string, nullable | dirección de "embarcar a" |
| ciudad | string, nullable | |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | |
| created_at / updated_at | timestamp | |

---

## 6. Materiales e insumos (según hoja de costos)

### `categoria_material`
`GIGANTOGRAFIA`, `CERRAJERIA`, `CARPINTERIA`, `OTROS_MATERIALES`, `PINTURAS`.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` |
| created_at / updated_at | timestamp |

### `material`
Modela filas como *"Lona FrontLight 3,20x50m"*, *"Tubo 20x20x0,9mm"*, *"MDF 9mm"*, etc.

| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| categoria_material_id | bigint FK → `categoria_material.id` | |
| nombre | string | |
| presentacion | string | "Rollo 3,20x50m", "Plancha 2x1m", "Litro", "Barra 6m" |
| unidad_medida | `ENUM('M2','METRO','UNIDAD','LITRO')` | según cómo se cobra: gigantografía por m², cerrajería por metro, otros por unidad/plancha |
| precio_presentacion | decimal(10,2) | costo total del rollo/plancha/litro comprado |
| precio_unitario | decimal(10,2) | costo por m²/metro/unidad (el dato que realmente se usa al cotizar) |
| stock_actual | decimal(10,2), default 0 | control de inventario |
| stock_minimo | decimal(10,2), default 0 | alerta de reposición |
| redondeo_compra | decimal(10,4), nullable | *(2026-08-28)* múltiplo (en `unidad_medida`) al que `CosteoProductoService` redondea **hacia arriba** la cantidad consumida — el material se compra en unidades enteras (plancha, barra de 6 m, galón) y el sobrante rara vez se reutiliza. `null` = cantidad exacta (se corta del rollo). Ej.: `1` unidades enteras, `6` barra de 6 m, `2.98` plancha de acrílico |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | |
| created_at / updated_at | timestamp | |

### `historial_precio_material`
Guarda el precio vigente en cada fecha (clave para el análisis BI de evolución de costos y para no alterar cotizaciones/pedidos ya cerrados).
| Campo | Tipo |
|---|---|
| id | bigint PK |
| material_id | bigint FK → `material.id` |
| precio_presentacion | decimal(10,2) |
| precio_unitario | decimal(10,2) |
| vigente_desde | date |
| created_at | timestamp |

### `proveedor`
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string |
| nit | string, nullable |
| contacto | string, nullable |
| telefono | string, nullable |
| direccion | string, nullable |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` |
| created_at / updated_at | timestamp |

### `compra`
| Campo | Tipo |
|---|---|
| id | bigint PK |
| proveedor_id | bigint FK → `proveedor.id` |
| empleado_id | bigint FK → `empleado.id` |
| numero_factura | string, nullable |
| fecha | date |
| total | decimal(10,2) |
| estado | `ENUM('PENDIENTE','PAGADA','ANULADA')`, default `PENDIENTE` |
| created_at / updated_at | timestamp |

### `compra_detalle`
| Campo | Tipo |
|---|---|
| id | bigint PK |
| compra_id | bigint FK → `compra.id` |
| material_id | bigint FK → `material.id` |
| cantidad | decimal(10,2) |
| precio_unitario | decimal(10,2) |
| subtotal | decimal(10,2) |
| created_at / updated_at | timestamp |

---

## 7. Catálogo de productos publicitarios (con receta de costo)

Este es el núcleo de la solución al problema del proyecto ("error en el cálculo real de cada producto" / cálculo manual). En vez de que el vendedor calcule a mano, el producto trae su **fórmula de consumo de materiales (BOM)**.

### `categoria_producto`
`BASTIDORES`, `BANNERS`, `GIGANTOGRAFIAS`, `VINYL_ROTULADO`, `EXHIBIDORES`, `MATERIAL_POP`, `TOLDOS`, `LETREROS_LUMINOSOS`, `ROTULADO_VEHICULAR`.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` |
| created_at / updated_at | timestamp |

### `producto`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| categoria_producto_id | bigint FK → `categoria_producto.id` | |
| nombre | string | ej. "Bastidor lona PVC 1440dpi" |
| descripcion | text, nullable | |
| unidad_medida | `ENUM('M2','UNIDAD','METRO_LINEAL')` | |
| precio_base | decimal(10,2), nullable | referencial/mano de obra fija |
| requiere_medidas | `ENUM('SI','NO')`, default `SI` | si pide ancho/alto al cotizar (la mayoría sí, según OC/Notas de Entrega) |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | |
| created_at / updated_at | timestamp | |

### `producto_material` (BOM — lista de materiales)
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| producto_id | bigint FK → `producto.id` | |
| material_id | bigint FK → `material.id` | |
| formula_id | bigint FK → `formula.id`, nullable | si está presente, la cantidad se calcula dinámicamente (ver abajo) en vez de con el factor fijo |
| cantidad_por_unidad | decimal(10,4), nullable | factor fijo: cuánto material consume 1 m²/unidad del producto. Exactamente uno de `formula_id`/`cantidad_por_unidad` debe estar presente por línea |
| created_at / updated_at | timestamp | |

### `formula` (motor de cálculo dinámico, 2026-08-25)
Una línea de BOM "estática" (`cantidad_por_unidad` × un solo driver: área para M2, un lado para METRO_LINEAL, 1 para UNIDAD) alcanza para productos que se cotizan por un único driver (gigantografías/banners por m², productos simples por unidad), pero no para **letras corpóreas 3D** (área de cara + perímetro de canto + profundidad combinados) ni **muebles/exhibidores a medida** (varias dimensiones a la vez). Para esos casos, una línea de `producto_material` puede apuntar a una `formula` en vez de traer un factor fijo.

| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| nombre | string | ej. "Perímetro con profundidad" |
| expresion | string | ej. `(ancho + alto) * 2 * profundo` |
| descripcion | text, nullable | |
| estado | `ENUM('ACTIVO','INACTIVO')`, default `ACTIVO` | |
| created_at / updated_at | timestamp | |

`expresion` se evalúa con el paquete `nxp/math-executor` vía `App\Services\Calculo\FormulaCalculator`, con las variables `ancho`/`alto`/`profundo`/`area` (=ancho×alto) /`perimetro` (=(ancho+alto)×2) — ver `App\Services\Calculo\MedidasCotizacion`. `App\Services\Calculo\CosteoProductoService` recorre el BOM completo de un producto (mezclando líneas estáticas y dinámicas) y devuelve el costo total más el desglose por línea; es el punto de entrada que debe usar el futuro módulo de Cotización, no una reimplementación a mano. `cantidad` (unidades pedidas) no es variable de fórmula — se aplica como multiplicador uniforme fuera de la fórmula/factor. Tras multiplicar por `cantidad`, la cantidad total consumida de cada línea se **redondea hacia arriba** al múltiplo `material.redondeo_compra` (si está definido) — el material se compra en unidades enteras (plancha, barra de 6 m, galón). Detalle completo, historia de la decisión y datos de prueba (`FormulaSeeder`/`ProductoMaterialSeeder`) en `.ai/rules/migrations.md` ("Motor de cálculo por tipo de producto") y `.ai/rules/calculo.md`.

Con esto, el sistema calcula automáticamente el costo de materiales de un producto en vez de que el vendedor lo haga a mano, más margen/mano de obra que se define al momento de cotizar.

---

## 8. Cotizaciones (Presupuestos)

### `cotizacion`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| codigo_verificacion | string, único | código de autenticidad mencionado en "Alcances" del proyecto |
| cliente_id | bigint FK → `cliente.id` | |
| empleado_id | bigint FK → `empleado.id` | vendedor que la elabora |
| sucursal_id | bigint FK → `sucursal.id` | |
| fecha | date | |
| fecha_vencimiento | date, nullable | |
| estado | `ENUM('PENDIENTE','APROBADA','RECHAZADA','CONVERTIDA','VENCIDA')`, default `PENDIENTE` | corresponde al rombo "Propuesta Sí/No" del flujo |
| subtotal | decimal(10,2) | |
| descuento | decimal(10,2), default 0 | |
| impuesto | decimal(10,2), default 0 | |
| total | decimal(10,2) | |
| observaciones | text, nullable | |
| created_at / updated_at | timestamp | |

### `cotizacion_detalle`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| cotizacion_id | bigint FK → `cotizacion.id` | |
| producto_id | bigint FK → `producto.id`, nullable | nullable si es un ítem personalizado no catalogado |
| descripcion | string | |
| ancho, alto | decimal(10,2), nullable | tal como aparece en las OC reales (ej. 3,35 × 2,00 m) |
| area_m2 | decimal(10,2), nullable | calculado = ancho × alto |
| cantidad | decimal(10,2) | |
| precio_unitario | decimal(10,2) | tomado del cálculo del BOM + margen, editable por el vendedor |
| subtotal | decimal(10,2) | |
| created_at / updated_at | timestamp | |

---

## 9. Pedidos / Órdenes de trabajo (ejecución)

Cuando el cliente confirma ("Confirmación" → "Realiza la orden" del flujo), la cotización aprobada genera un **pedido**.

### `pedido`
| Campo | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| cotizacion_id | bigint FK → `cotizacion.id`, único | 1 cotización → 1 pedido |
| numero_pedido | string, único | |
| fecha_pedido | date | |
| fecha_entrega_estimada | date, nullable | |
| fecha_entrega_real | date, nullable | |
| estado | `ENUM('DISENO','ELABORACION','ACABADO','ENTREGADO','CANCELADO')`, default `DISENO` | refleja el diagrama de flujo del Anexo |
| total | decimal(10,2) | |
| created_at / updated_at | timestamp | |

### `pedido_detalle`
Copia de `cotizacion_detalle` al momento de crear el pedido (permite que el pedido evolucione sin alterar la cotización histórica).
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_id | bigint FK → `pedido.id` |
| cotizacion_detalle_id | bigint FK → `cotizacion_detalle.id` |
| descripcion | string |
| ancho, alto | decimal(10,2), nullable |
| cantidad | decimal(10,2) |
| estado_item | `ENUM('DISENO','ELABORACION','ACABADO','ENTREGADO')`, default `DISENO` |
| created_at / updated_at | timestamp |

### `pedido_seguimiento` (bitácora por etapa y por área)
Modela exactamente el rombo *"Define el área"* → Diseño → Elaboración → Acabado → Entrega del anexo del proyecto.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_detalle_id | bigint FK → `pedido_detalle.id` |
| area_id | bigint FK → `area.id` |
| empleado_id | bigint FK → `empleado.id` (responsable) |
| etapa | `ENUM('DISENO','ELABORACION','ACABADO','ENTREGA')` |
| fecha_inicio / fecha_fin | datetime, nullable |
| observaciones | text, nullable |
| created_at / updated_at | timestamp |

### `pedido_detalle_material` (consumo real)
Compara lo presupuestado (BOM) contra lo realmente usado — insumo clave para el análisis de BI y reducción de errores.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_detalle_id | bigint FK → `pedido_detalle.id` |
| material_id | bigint FK → `material.id` |
| cantidad_usada | decimal(10,2) |
| costo_real | decimal(10,2) |
| created_at / updated_at | timestamp |

---

## 10. Documentos comerciales (según PDFs de la empresa)

### `orden_compra_cliente`
Representa el documento tipo *"Orden de Compra 11021545"* que el cliente (ej. Compañía de Alimentos Ltda./Delizia) envía a XtraPubli como respaldo formal del pedido.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_id | bigint FK → `pedido.id` |
| cliente_id | bigint FK → `cliente.id` |
| numero_oc | string |
| fecha | date |
| monto_total | decimal(10,2) |
| condicion_pago | string, nullable | ej. "60 DIAS" |
| archivo_pdf | string, nullable |
| estado | `ENUM('PENDIENTE','VALIDADA','ANULADA')`, default `PENDIENTE` |
| created_at / updated_at | timestamp |

### `nota_entrega`
Representa el documento *"Nota de Entrega"*, firmado por quien recibe.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_id | bigint FK → `pedido.id` |
| empleado_id | bigint FK → `empleado.id` |
| numero_nota | string, único |
| fecha_entrega | date |
| recibido_por | string, nullable |
| cargo_receptor | string, nullable |
| observaciones | text, nullable |
| archivo_pdf | string, nullable |
| created_at / updated_at | timestamp |

### `nota_entrega_detalle`
Con foto de evidencia, cantidad, medida y ubicación (ej. "Bastidor ingreso tienda lado derecho 3,10x1,00m"), como en tus PDFs de Dismac y Black Weekend.
| Campo | Tipo |
|---|---|
| id | bigint PK |
| nota_entrega_id | bigint FK → `nota_entrega.id` |
| pedido_detalle_id | bigint FK → `pedido_detalle.id` |
| descripcion | string |
| cantidad_entregada | decimal(10,2) |
| ubicacion | string, nullable |
| foto_url | string, nullable |
| created_at / updated_at | timestamp |

---

## 11. Pagos

### `pago`
| Campo | Tipo |
|---|---|
| id | bigint PK |
| pedido_id | bigint FK → `pedido.id` |
| monto | decimal(10,2) |
| fecha_pago | date |
| metodo_pago | `ENUM('EFECTIVO','TRANSFERENCIA','QR','TARJETA','CHEQUE')` |
| estado | `ENUM('PENDIENTE','PAGADO','PARCIAL')`, default `PENDIENTE` |
| comprobante_url | string, nullable |
| created_at / updated_at | timestamp |

---

## 12. Explicación del flujo (cómo se conecta todo)

El anexo del proyecto define este flujo: **Inicio → Cliente solicita cotización → Propuesta (Sí/No) → Confirmación → Realiza la orden → Define el área (Sí/No) → Diseño → Elaboración → Acabado → Entrega → Fin.** Así se traduce a la base de datos:

1. **Cliente solicita cotización** → se crea una fila en `cotizacion` (`estado = 'PENDIENTE'`), con sus líneas en `cotizacion_detalle`. Cada línea toma un `producto` (o texto libre), y el sistema calcula el precio sugerido recorriendo `producto_material` (BOM) contra el `precio_unitario` vigente en `material` — esto resuelve el problema de "cálculo manual con errores" del planteamiento del problema.

2. **Propuesta Sí/No** → el `estado` de `cotizacion` cambia a `'APROBADA'` o `'RECHAZADA'`. Si el cliente tiene portal (rol `CLIENTE`), puede aprobar/rechazar desde ahí; si no, lo hace el vendedor.

3. **Confirmación / Realiza la orden** → al aprobarse (`estado = 'CONVERTIDA'`), se genera un `pedido` ligado a `cotizacion_id`, copiando las líneas a `pedido_detalle`. Opcionalmente se adjunta el respaldo formal del cliente en `orden_compra_cliente` (como las OC 11021545 / 11021575 que subiste).

4. **Define el área** → por cada `pedido_detalle` se crea un registro en `pedido_seguimiento` indicando a qué `area` pasa (`DISENO`, `GIGANTOGRAFIA`, `CERRAJERIA`, `CARPINTERIA`...) y qué `empleado` es responsable.

5. **Diseño → Elaboración → Acabado** → cada etapa es una nueva fila (o actualización) en `pedido_seguimiento` (`etapa = 'DISENO' | 'ELABORACION' | 'ACABADO'`), y el `estado_item` de `pedido_detalle` (y el `estado` global del `pedido`) avanza en consecuencia. Aquí también se registra el consumo real de materiales en `pedido_detalle_material`, lo que permite comparar costo estimado (cotización) vs. costo real.

6. **Entrega** → se genera una `nota_entrega` con sus `nota_entrega_detalle` (cantidad, medida, ubicación, foto — tal como en tus PDFs de Dismac y Black Weekend), `pedido.estado` pasa a `'ENTREGADO'`, y se puede registrar el `pago` correspondiente.

7. **Fin / Analítica** → con `historial_precio_material`, `pedido_detalle_material`, `cotizacion` y `pedido` acumulados, tu módulo de inteligencia de negocios puede construir reportes de: productos más vendidos por categoría, evolución de costos de materiales, cumplimiento de tiempos de entrega por sucursal/área, rentabilidad real por pedido (costo real vs. cotizado), y proyecciones de demanda — exactamente lo pedido en los "Alcances" del proyecto (informes de rendimiento y proyecciones estratégicas). Al tener todos los `estado` como `ENUM` en mayúsculas, estas consultas de agregación (`GROUP BY`, `WHERE estado = 'ENTREGADO'`) son directas y consistentes.

**Seguridad (roles):** cada usuario de `users` tiene un rol de Spatie según su cargo (`empleado.cargo`/`area_id` sugiere el rol a asignar), un `estado` (`ACTIVO`/`INACTIVO`) que controla su acceso, y opcionalmente una `foto` de perfil. Las Policies de Laravel filtran: un `VENDEDOR` solo ve sus propias cotizaciones y las de su sucursal; un `OPERARIO_PRODUCCION` solo ve las etapas de `pedido_seguimiento` asignadas a su área; un `CLIENTE` solo ve sus propias `cotizacion`/`pedido`/`nota_entrega` vía el portal.
