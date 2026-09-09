# Manual: motor de margen automático y cómo cotizar

Sistema de costos y presupuestos **XtraPubli** — Laravel + Vue 3 + Inertia + MariaDB.

Este manual explica **cómo configurar los parámetros** que gobiernan los precios y **cómo
armar una cotización** de principio a fin. Reemplaza al archivo Excel
`11_Sistema_Margen_Automatico_Xtrapubli_DUPLICABLE.xlsx`: las 5 hojas (Muebles Exhibidores,
Islas Cabeceras, Sistema Letreros Luminosos, Trabajos Especiales y Bastidores) ahora son
líneas de cotización que usan el mismo motor de cálculo.

---

## 1. Qué calcula el sistema

Cada ítem de una cotización se calcula con esta cadena, exactamente igual que en el Excel:

```
1. Costo base       = suma de los insumos del ítem (materiales + mano de obra + impresión…)
2. Costo ajustado   = costo base × FACTOR DE COMPLEJIDAD    ← del tipo de proyecto
3. Precio           = costo ajustado × (1 + MARGEN MÍNIMO)  ← del tipo de proyecto

4. IT               = precio × 3 %
   Utilidad a/IUE   = precio − costo ajustado − IT
   IUE              = utilidad a/IUE × 25 %
   Utilidad real    = utilidad a/IUE − IUE

5. Semáforo (se compara contra el COSTO AJUSTADO, no contra el precio):
      utilidad real > 30 % del costo ajustado  →  🟢 VERDE     → ACEPTAR
      utilidad real > 15 % del costo ajustado  →  🟡 AMARILLO  → REVISAR PRECIO
      por debajo                               →  🔴 ROJO      → NO ACEPTAR

6. IVA              = precio × 13 %
   Precio final     = precio + IVA
   Total al cliente = precio final + INSTALACIÓN   ← monto manual, NO paga impuestos
```

> **Todo esto lo calcula el servidor.** Lo que se ve mientras se escribe en el formulario es
> una previsualización; al guardar, el sistema recalcula y su resultado es el que queda.
> Nadie puede forzar un precio o una rentabilidad desde el navegador.

### Ejemplo de referencia (hoja "Muebles Exhibidores")

| Tipo | Descripción | Cantidad | Costo unit. | Subtotal |
|---|---|---:|---:|---:|
| Material | Fierro | 0,55 | 45,00 | 24,75 |
| Material | Plancha | 0,15 | 145,00 | 21,75 |
| Material | Pintura | 0,33 | 70,00 | 23,10 |
| Material | Otros | 1,00 | 10,00 | 10,00 |
| Impresión | Adhesivo | 0,07 | 80,00 | 5,60 |
| Mano de obra | Horas | 6,00 | 15,00 | 90,00 |
| | | | **Costo base** | **175,20** |

Con tipo de proyecto **Medio** (factor 1,3 · margen 50 %):

| Concepto | Valor |
|---|---:|
| Costo ajustado | 227,76 |
| Precio (antes de IVA) | 341,64 |
| IT (3 %) | 10,25 |
| IUE (25 %) | 25,91 |
| **Utilidad real** | **77,72** |
| Semáforo | 🟢 VERDE — ACEPTAR |
| IVA (13 %) | 44,41 |
| **Total al cliente** | **386,05** |

Este caso está congelado como test automático (`tests/Unit/MotorMargenServiceTest.php`): si
alguien toca el motor y los números cambian, la suite falla.

---

## 2. Configuración de parámetros

Hay **dos niveles**: lo que se configura desde pantalla (día a día) y lo que se configura en
archivos (cambia muy rara vez).

### 2.1 Tipos de proyecto — desde el sistema, sin programador

**Menú:** Catálogo de Productos → **Tipos de Proyecto**
**Permisos:** `tipos-proyecto.ver`, `.crear`, `.editar`, `.eliminar`

Es el reemplazo de la cadena de `SI` anidados que tenía el Excel (Tipo 1 a 4). Cada nivel
tiene:

| Campo | Qué hace |
|---|---|
| **Nombre** | Cómo se llama el nivel (Básico, Medio, Complejo, Crítico… los que quieras). |
| **¿Cuándo se usa?** | Descripción para que el vendedor no dude al elegir. |
| **Factor de complejidad** | Multiplica el costo de los insumos. `1` = sin recargo. |
| **Margen mínimo (%)** | Se carga en porcentaje (50) y el sistema lo guarda como fracción (0,5). |
| **Orden** | Posición en la lista del formulario de cotización. |
| **Estado** | `INACTIVO` lo saca del selector sin borrar el historial. |

Valores que trae cargados de fábrica (los del Excel):

| Nivel | Factor | Margen mínimo |
|---|---:|---:|
| Básico | 1,00 | 45 % |
| Medio | 1,30 | 50 % |
| Complejo | 1,50 | 60 % |
| Crítico | 1,80 | 70 % |

**Cómo crear o editar un nivel**

1. Entrá a **Tipos de Proyecto** → botón **Nuevo nivel** (o el lápiz para editar uno).
2. Cargá nombre, factor y margen.
3. Mirá el recuadro **"Simulación sobre un costo de Bs 100"**: muestra en vivo qué precio y
   qué utilidad real daría ese nivel, con su semáforo. Sirve para no configurar por intuición.
4. Guardá.

**Reglas importantes**

- Podés tener **los niveles que quieras**, no está limitado a 4.
- Cambiar el factor o el margen **NO recalcula cotizaciones ya emitidas**: cada línea guarda
  el factor y el margen con los que se cotizó, así el documento histórico sigue explicando
  su propio precio.
- Un nivel que ya se usó en alguna cotización **no se puede borrar** (el botón queda
  deshabilitado). Ponelo en `INACTIVO`.
- La columna **Usos** muestra en cuántas líneas se aplicó.

### 2.2 Impuestos y umbrales del semáforo — archivo `config/margen.php`

Estos valores son del régimen tributario boliviano y de la política comercial de la empresa,
así que no se editan desde pantalla. Viven en **un solo archivo**:

```php
// config/margen.php
return [
    'impuestos' => [
        'it'  => 0.03,   // Impuesto a las Transacciones
        'iue' => 0.25,   // Impuesto sobre las Utilidades de las Empresas
        'iva' => 0.13,   // Impuesto al Valor Agregado
    ],
    'semaforo' => [
        'umbral_verde'    => 0.30,   // utilidad real > 30 % del costo ajustado
        'umbral_amarillo' => 0.15,   // utilidad real > 15 % del costo ajustado
    ],
];
```

También se pueden sobreescribir por variable de entorno en `.env`, sin tocar código:

```dotenv
MARGEN_IT=0.03
MARGEN_IUE=0.25
MARGEN_IVA=0.13
MARGEN_UMBRAL_VERDE=0.30
MARGEN_UMBRAL_AMARILLO=0.15
```

Después de cambiar `.env` en un servidor con caché de configuración:
`php artisan config:clear`.

### 2.3 Otros parámetros

**`config/cotizacion.php`**

| Clave | Uso |
|---|---|
| `margen_sugerido` (0,45) | Margen que se aplica cuando una línea se cotiza **sin** tipo de proyecto. |
| `dias_vencimiento` (15) | Días por defecto entre la fecha y el "válida hasta". |

**`config/postventa.php`**

| Clave | Uso |
|---|---|
| `dias_seguimiento` (7) | Días entre la entrega y el contacto de postventa. |
| `satisfaccion_maxima` (5) | Tope de la escala de satisfacción. |

### 2.4 Vocabulario y catálogos que no se editan a mano

- **Unidades de medida**: la lista canónica es `Material::UNIDADES_MEDIDA`
  (`M2`, `METRO_LINEAL`, `UNIDAD`, `LITRO`) y `Producto::UNIDADES_MEDIDA` es un subconjunto
  suyo. No agregues una unidad nueva en un `<select>` del frontend: se agrega en esas
  constantes y en el ENUM de la tabla, para que sigan hablando el mismo idioma.
- **Cargos de empleado**: salen de los roles del negocio definidos en `config/acl.php`
  (`Empleado::cargos()`). Para un cargo nuevo, agregá el rol allí — el selector del formulario
  se actualiza solo.

### 2.5 Catálogos que alimentan la hoja de costos

Para que el sistema pueda **traer los insumos solo** en vez de tipearlos:

1. **Materiales** (menú Materiales e Insumos → Materiales): nombre, unidad de medida y
   **precio unitario**. Opcionalmente `redondeo_compra`, el múltiplo al que se redondea hacia
   arriba lo consumido (una plancha entera, una barra de 6 m); dejalo vacío para material de
   rollo que se corta a medida.
2. **Fórmulas** (Catálogo de Productos → Fórmulas): expresiones con las variables `ancho`,
   `alto`, `profundo`, `area`, `perimetro`. Ej.: `(ancho + alto) * 2`.
3. **Receta / BOM del producto** (Productos → icono 🧪 de la fila): qué materiales lleva UNA
   unidad del producto, con una cantidad fija o una fórmula.

Sin receta el sistema igual funciona: se cargan los insumos a mano.

---

## 3. Cómo hacer una cotización

**Menú:** Ventas → **Cotizaciones** → botón **Nueva cotización**
**Permisos:** `cotizaciones.crear`, `cotizaciones.editar`, `cotizaciones.aprobar`

### Paso 1 — Datos de la cotización

Cliente, vendedor, sucursal, fecha y "válida hasta" (se propone sola a 15 días).

### Paso 2 — Agregar un ítem

Cada ítem es una hoja de costos independiente. Por ítem se define:

- **Producto**: del catálogo, o *Ítem personalizado* si no está catalogado.
- **Tipo de proyecto**: el nivel de complejidad. El selector muestra el factor y el margen de
  cada nivel para elegir con criterio.
- **Descripción**: lo que va a leer el cliente en el documento.
- **Ancho / Alto**: en metros. El área se calcula sola.
- **Cantidad**: cuántas unidades.
- **Instalación (Bs)**: monto fijo del trabajo completo. **No se multiplica por la cantidad y
  no paga IVA** (igual que en el Excel: Islas, Trabajos Especiales y Bastidores lo usaban).
  Dejalo en 0 si el ítem no lleva instalación.

### Paso 3 — Cargar la hoja de costos (los insumos)

Es el corazón del cálculo. Tres botones:

| Botón | Qué hace |
|---|---|
| **Traer insumos del producto** | Lee la receta/BOM del producto, la evalúa con las medidas cargadas y llena las filas de materiales con su cantidad y precio real. Respeta las filas que hayas escrito a mano. |
| **Insumo** | Agrega una fila vacía para cargar cualquier cosa a mano. |
| **Mano de obra** | Agrega una fila de horas ya preconfigurada. |

Cada fila tiene: **Tipo** (Material / Mano de obra / Impresión / Servicio de terceros / Otro),
**Descripción**, **Unidad**, **Cantidad**, **Costo unitario** y su subtotal.

> **Las cantidades son por UNA unidad del producto.** Si cotizás 4 exhibidores, cargá lo que
> lleva uno solo: el sistema multiplica por la cantidad. Así una receta siempre significa lo
> mismo.

Podés agregar y quitar filas libremente: no hay un número fijo de insumos.
Abajo se ve el **Costo base (1 unidad)**.

### Paso 4 — Revisar el precio y el semáforo

Debajo de la hoja de costos, en vivo:

- **Costo ajustado** (costo base × factor).
- **Precio sugerido** (costo ajustado × (1 + margen)) — es el precio que se va a guardar.
- **Utilidad real de la línea** después de IT e IUE, con su porcentaje sobre el costo y una
  barra de color.
- El **semáforo** del ítem, arriba a la derecha de la tarjeta.

**Si querés negociar el precio:** botón **"Fijar precio a mano"**. El campo se habilita, ponés
el número que acordaste y el semáforo se recalcula con ese precio real — así ves al instante
si el descuento que estás por dar te deja en amarillo o en rojo. El botón **"Volver al precio
sugerido"** devuelve el control al motor.

> Un ítem **sin insumos cargados** (reventa, servicio de terceros) se cotiza siempre con
> precio manual: no hay costo del que calcular.

### Paso 5 — Totales del presupuesto

En el panel derecho:

- **Subtotal**: suma de los ítems.
- **Descuento (Bs)**: monto, no porcentaje. Baja la base imponible, así que **empuja el
  semáforo** — es la señal para saber hasta dónde podés ceder.
- **IVA 13 %**: casilla para aplicarlo o no. El monto lo calcula el sistema y se guarda en la
  columna `cotizacion.iva`.
- **Instalación**: suma de las instalaciones de cada ítem.
- **Total**: subtotal − descuento + IVA + instalación.

A la izquierda, la tarjeta **"Rentabilidad del presupuesto"** muestra costo, IT, IUE, utilidad
real y el semáforo global. **Esa tarjeta es de uso interno y no se imprime.**

### Paso 6 — Guardar, aprobar, convertir

1. **Crear cotización** → queda en estado `PENDIENTE` con un código de verificación
   (`COT-AAAAMMDD-XXXXX`).
2. En la vista de detalle: **Imprimir** (el cliente solo ve el documento comercial, nunca los
   costos ni la rentabilidad), **Editar**, **Aprobar** o **Rechazar**.
   El panel **"Análisis de rentabilidad (uso interno)"** de esa pantalla muestra el desglose
   por ítem y, desplegable, la hoja de costos completa de cada uno.
3. Una vez `APROBADA`: **Convertir en pedido** genera la orden de trabajo.

> Solo se puede editar una cotización **PENDIENTE**. Una vez aprobada o convertida queda como
> documento histórico.

---

## 4. Del pedido a la postventa

El sistema cubre los 3 procesos de la empresa:

**Proceso 1 — Cotización y ventas**
Consulta → cotización → envío → aprobación del cliente (o desde el **Portal del cliente**,
donde puede pedir presupuesto y aprobarlo) → conversión en pedido.

**Proceso 2 — Producción**
Etapas por ítem: `DISEÑO → ELABORACIÓN → ACABADO → CONTROL DE CALIDAD → ENTREGADO`.
Desde **Pedidos → ver pedido** se asigna área y responsable, se avanza la etapa y se registra
el consumo real de materiales (queda la comparativa costo presupuestado vs. real).
El estado global del pedido es siempre el de la etapa **menos avanzada** entre sus ítems.

El botón **"Ajustar medidas reales"** corrige lo que el taller está fabricando de verdad
(descripción, ancho, alto y cantidad) cuando difiere de lo cotizado. La cotización NO se toca
—queda como documento histórico de lo prometido— y **el precio acordado no cambia**: el
ajuste se anota en la bitácora del ítem con su motivo.

> Para ver pedidos, tu usuario tiene que estar **vinculado a una ficha de empleado**
> (Organización → Empleados): de ahí sale la sucursal que el sistema usa para filtrar. Sin esa
> ficha el listado sale vacío y la pantalla te lo avisa.

**Proceso 3 — Entrega y postventa**
Nota de entrega con foto y conformidad → registro de pagos (Cobranza) → **Seguimiento
postventa**.
Al quedar el pedido `ENTREGADO`, el sistema **programa solo** el contacto a los 7 días.
En **Ventas → Seguimiento Postventa** está la bandeja: vencidos, pendientes, reclamos abiertos
y satisfacción promedio. Al registrar el contacto se anota el medio, la satisfacción (1 a 5),
si el cliente reportó un problema y cualquier **oportunidad comercial** detectada.

---

## 5. Puesta en marcha y datos de prueba

```bash
composer install
npm install
php artisan migrate                                   # crea/actualiza las tablas
php artisan db:seed --class=RolesAndPermissionsSeeder # roles y permisos (incluye los nuevos)
php artisan db:seed --class=TipoProyectoSeeder        # los 4 niveles del Excel
npm run build                                         # o: composer run dev
```

Para una base de demostración completa desde cero (⚠️ **borra todos los datos**):

```bash
php artisan migrate:fresh --seed
```

Verificar que el motor sigue coincidiendo con el Excel:

```bash
php artisan test --filter=MotorMargen
php artisan test                 # suite completa
```

---

## 6. Dónde está cada cosa (referencia técnica)

| Qué | Dónde |
|---|---|
| Motor de cálculo (función pura) | `app/Services/Calculo/MotorMargenService.php` |
| Resultado del motor | `app/Services/Calculo/ResultadoMargen.php` |
| Impuestos y umbrales | `config/margen.php` |
| Niveles de complejidad (CRUD) | `app/Models/TipoProyecto.php`, `app/Http/Controllers/TipoProyectoController.php`, `resources/js/Pages/TiposProyecto/Index.vue` |
| Insumos de una línea | tabla `cotizacion_detalle_item`, `app/Models/CotizacionDetalleItem.php` |
| Aplicación del motor al presupuesto | `app/Http/Controllers/CotizacionController.php` (`normalizarDetalles`, `calcularMontos`) |
| BOM → insumos + precio sugerido | `app/Services/Calculo/PrecioSugeridoService.php` |
| Formulario de cotización | `resources/js/Pages/Cotizaciones/Partials/CotizacionForm.vue` |
| Previsualización en el navegador | `resources/js/Utils/MotorMargen.js` |
| Postventa | `config/postventa.php`, `app/Services/Pedido/ProgramarPostventaService.php`, `resources/js/Pages/SeguimientosPostventa/Index.vue` |
| Test de referencia contra el Excel | `tests/Unit/MotorMargenServiceTest.php` |

**Regla para quien mantenga el código:** las tasas (3 %, 25 %, 13 %) y los umbrales (30 %,
15 %) viven **únicamente** en `config/margen.php`; el factor y el margen mínimo **únicamente**
en la tabla `tipo_proyecto`. No los repitas en controladores ni en componentes.

---

## 7. Qué NO se guarda (y por qué)

Varias cosas que parecerían columnas son derivados: guardarlas solo abriría la puerta a que
se desincronicen. Si buscás alguna de estas columnas y no la encontrás, es a propósito.

| Dato | De dónde sale |
|---|---|
| Recomendación comercial de una cotización | Del semáforo (`estado_margen`), vía `MotorMargenService::RECOMENDACIONES`. |
| Estado de cobranza | Del pedido (`Pedido::estadoPago()`), no de cada fila de `pago`. |
| Cliente de una orden de compra | De la cadena `pedido → cotizacion → cliente`. |
| Cargos de empleado | De los roles de `config/acl.php`. |

Y tres que **sí** se guardan aunque parezcan duplicados, porque tienen que poder diferir:

| Dato | Por qué se guarda |
|---|---|
| `cotizacion_detalle.factor_complejidad` / `margen_aplicado` | Foto histórica: el presupuesto sigue explicando su precio aunque cambie el CRUD. |
| `pedido_detalle` (descripción, medidas, cantidad) | Medidas **reales** de producción, que pueden diferir de lo cotizado. |
| `orden_compra_cliente.monto_total` | Es el importe que declara el documento del cliente; si no coincide con el pedido, el sistema lo marca. |
