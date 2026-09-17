# Bitácora de cambios

Registro cronológico (más reciente arriba) de los cambios que se van haciendo
al sistema con Claude Code. No reemplaza al historial de git — es un
resumen legible en español, pensado para que el equipo (y el propio Claude
en sesiones futuras) entienda RÁPIDO qué se hizo y por qué, sin tener que
leer diffs.

Regla del proyecto: cada vez que se termina un cambio de cierto tamaño
(una funcionalidad, un refactor, una corrección no trivial), se agrega una
entrada nueva acá ANTES de darlo por terminado. Ver
`.ai/rules/bitacora.md`.

---

## 2026-09-16 — Botón "Nuevo X" junto a cada select con dependencia dinámica

**Qué cambió:** además de "Nuevo cliente" (ver la entrada de alta rápida de
cliente más abajo), ahora Empleado, Producto, Material, Proveedor y
Categoría de producto también tienen un botón "+" junto a su
`SearchableSelect` en los formularios que los usan como dependencia, con el
mismo patrón: modal → guarda por JSON → queda elegido, sin perder lo que ya
se llenó en el formulario grande.

- Cotizaciones: "Nuevo empleado" (vendedor) y "Nuevo producto" (por línea).
- Compras: "Nuevo proveedor", "Nuevo empleado" (responsable) y "Nuevo
  material" (por línea).
- Notas de entrega: "Nuevo empleado" (quién entrega).
- Receta de producto: "Nuevo material".
- Productos: "Nueva categoría".

**Por qué:** mismo motivo que el cliente — quien arma una cotización,
compra o receta descubre a mitad de camino que el catálogo relacionado no
tiene lo que necesita, y antes tenía que abandonar el formulario.

**Cómo:**
- `Components/QuickCreateModal.vue` (nuevo): generaliza el modal, el
  guardado por `axios` y el manejo de errores 422 que antes vivían
  duplicados en `ClienteQuickCreateModal.vue` — ese componente ahora es un
  usuario más de este wrapper. Cada catálogo solo aporta sus campos por el
  slot por defecto.
- `*FormFields.vue` extraídos para Empleado, Producto (sin imagen: el modal
  guarda JSON, no multipart), Material, Proveedor y CategoriaProducto —
  mismo patrón que `ClienteFormFields.vue`: el CRUD completo (`Empleados/
  Index.vue`, etc.) y el modal de alta rápida renderizan el MISMO
  formulario.
- `*Controller::storeRapido` nuevo en los cinco controladores + rutas
  `POST .../rapido` (mismo permiso `.crear` que el alta normal).
- El botón de cada catálogo lleva `v-can` con su propio permiso — en la
  práctica, "Nuevo empleado"/"Nuevo producto"/"Nuevo proveedor" solo los ve
  el rol `administrador` (vendedor/secretaria/contador no tienen esos
  permisos `.crear`, solo `clientes.crear`), pero "Nuevo material" sí lo ven
  contador y jefe-producción, que son quienes realmente llenan Compras y el
  consumo de Pedidos.
- **Trampa encontrada y corregida**: `Cotizaciones/{Create,Edit}.vue` y
  `Compras/{Create,Edit}.vue` son páginas delgadas que reenvían props a mano
  al partial (`CotizacionForm.vue`/`CompraForm.vue`); los props nuevos
  (`areas`, `cargosEmpleado`, `categoriasProducto`/`categoriasMaterial`,
  `sucursales` en Compras) hay que declararlos y reenviarlos ahí TAMBIÉN —
  quedaban `undefined` en el partial aunque el controlador ya los mandara.
  Se detectó recién al probar en navegador real (Playwright): el select de
  Área salía vacío en el modal de "Nuevo empleado".
- Alcance deliberado: NO se agregó en los dos modales internos de
  `Pedidos/Show.vue` (asignar área, registrar consumo) para no apilar un
  modal sobre otro modal ya abierto; tampoco en el selector de Cotización
  de `Pedidos/Create.vue` (crear un pedido no es el lugar para crear una
  cotización) ni en el portal del cliente (un cliente no da de alta
  productos del catálogo).

---

## 2026-09-16 — Categoría de producto también con búsqueda

**Qué cambió:** el select "Categoría" del filtro de Productos y del
formulario crear/editar ahora es un `SearchableSelect` (antes se habían
dejado como `<select>` nativo, junto con las demás categorías/enums).

**Por qué:** `categoria_producto` tiene 72 filas en este catálogo (a
diferencia de `categoria_material`, que tiene 5) — un `<select>` con 72
opciones sin buscador es incómodo de usar. El usuario lo marcó
explícitamente después de la pasada anterior de selects con búsqueda.

**Cómo:** mismo componente `Components/SearchableSelect.vue` ya usado en
cliente/producto/material/proveedor/empleado/cotización — sin cambios en
el componente, solo en `Productos/Index.vue`. `categoria_material` se
dejó como estaba (5 filas no lo justifica).

---

## 2026-09-16 — Alta rápida de cliente desde la cotización

**Qué cambió:** en el formulario de cotización (crear/editar), el campo
Cliente ahora tiene un botón "Nuevo cliente" al lado. Abre un modal con los
mismos campos que el CRUD de Clientes, guarda el cliente sin salir de la
cotización a medio llenar, y lo deja seleccionado automáticamente.

**Por qué:** el vendedor arma la cotización con el cliente en el teléfono y
recién ahí descubre que no está registrado; antes tenía que abandonar el
formulario, ir a Clientes, crearlo, y volver a empezar la cotización.

**Cómo:**
- `ClienteController::storeRapido` (nuevo, ruta `POST /clientes/rapido`,
  `clientes.rapido`): misma validación y permiso que `store`, pero responde
  JSON en vez de redirigir — un POST de Inertia normal navegaría a
  `/clientes` y perdería la cotización a medio llenar.
- `Components/Cliente/ClienteFormFields.vue`: los campos del formulario de
  cliente, extraídos de `Clientes/Index.vue` para que el modal de alta
  rápida use EXACTAMENTE el mismo formulario (un solo lugar si mañana se
  agrega un campo).
- `Components/Cliente/ClienteQuickCreateModal.vue`: el modal en sí, con
  `axios` en vez de `useForm` de Inertia (por el motivo de arriba).
- `CotizacionForm.vue` quedó con un `<div>` envolvente (antes el `<form>`
  era la raíz): el modal trae su propio `<form>`, y un `<form>` anidado
  dentro de otro es HTML inválido.
- Tests nuevos en `ClienteControllerTest` (permiso, validación, json).

---

## 2026-09-16 — Selects con búsqueda y dropzone de archivos en toda la app

**Qué cambió:** dos componentes nuevos, reusables:
- `Components/SearchableSelect.vue` (envuelve `vue-multiselect`, dependencia
  nueva): reemplaza los `<select>` que listan catálogos grandes (cliente,
  producto, material, proveedor, empleado, cotización) por un combobox con
  búsqueda. Los `<select>` de listas chicas (estado, categoría, sucursal,
  etc.) se dejaron como estaban a propósito.
- `Components/FileDropzone.vue`: reemplaza `<input type="file">` por una
  zona de arrastrar-y-soltar con miniatura/chip del archivo, en Productos,
  Cotizaciones, Usuarios, Notas de entrega, Órdenes de compra cliente y
  Pagos. Se dejó sin tocar el avatar de Profile/Edit.vue (ya tenía un
  diseño propio, mejor que el genérico para ese caso).

**Por qué:** un `<select>` nativo con cientos de clientes/productos es
inutilizable a mano; el pedido explícito fue "convertir los selects con
muchos registros en un select de búsqueda" y "mejorar el diseño de los
inputs de archivo".

**Cómo:** re-tematizado en `app.css` (secciones 30 y 31) con los tokens de
marca del proyecto (`--c-primary`, `--card-bg`, etc.), mismo patrón que ya
existía para el date range picker y SweetAlert2. Verificado con Playwright
contra la app real (login, abrir selects, buscar, subir un archivo) — cero
errores de consola.

---

## 2026-09-16 — Imagen referencial en cotizaciones y productos; evidencia fotográfica convertida a JPG

**Qué cambió:**
- `producto.imagen` (nueva columna): los productos del catálogo pueden
  llevar una imagen de referencia, subida desde `Productos/Index.vue`.
- `cotizacion_detalle.imagen` (nueva columna): cada línea de una cotización
  puede llevar una imagen, subida a mano O copiada del producto elegido
  (botón "Usar imagen del producto" — copia el archivo, no solo la
  referencia, para que la cotización quede como foto histórica).
- La foto de evidencia de `nota_entrega_detalle` (ya existía) ahora pasa
  por el mismo conversor a JPG en vez de guardarse tal cual.

**Por qué:** pedido explícito de aligerar el peso de las imágenes en el
servidor (hosting compartido) y de poder mostrar una referencia visual del
producto cotizado.

**Cómo:** `App\Services\Imagen\ConvierteImagenAJpgService` (GD puro, sin
paquete nuevo) es el único lugar que convierte una imagen subida a JPG
(calidad 82, lado mayor tope 1600px, corrige orientación EXIF, aplana
transparencia). El detalle de cotización se reemplaza entero en cada
`update` (patrón ya existente del módulo) — se agregó limpieza de
imágenes huérfanas en disco para que cada edición no vaya acumulando
archivos sin referencia. Ver `.ai/rules/controllers-http-controllers.md`
para el detalle completo de la decisión.
