# Catálogo de clases estilo Bootstrap (construidas con Tailwind)

Todas viven en [`resources/css/app.css`](../../../../resources/css/app.css) como clases
normales (`.btn`, `.card`, ...) definidas con `@apply` sobre utilidades Tailwind + las
variables de color de [`color-palette.md`](color-palette.md). Se usan igual que en Bootstrap:
combinando una clase base con un modificador (`btn` + `btn-primary`).

Colores disponibles en todos los grupos "por color": **primary, secondary, success, info,
warning, danger, dark** (más `light` y `pink` donde aplica).

## Botones (`.btn`)

```html
<button class="btn btn-primary">Guardar</button>
<button class="btn btn-outline-danger">Eliminar</button>
<button class="btn btn-soft-info btn-sm">Ver detalle</button>
<button class="btn btn-icon btn-soft-secondary"><svg .../></button>
```

- Base: `.btn` (+ tamaño opcional `.btn-sm` / `.btn-lg`, o `.btn-icon` para botón cuadrado
  solo-ícono de 32px).
- Sólido: `.btn-primary` `.btn-secondary` `.btn-success` `.btn-info` `.btn-warning`
  `.btn-danger` `.btn-dark` — fondo de color, texto blanco.
- Tenue: `.btn-soft-primary` ... `.btn-soft-dark` — fondo pastel, texto del color.
- Outline: `.btn-outline-primary` ... `.btn-outline-dark` — borde + texto del color, fondo
  transparente que se rellena en hover.
- `.btn-light` — caso especial ya existente (fondo `--page-bg`), útil sobre superficies
  oscuras/sidebar.

## Fondos (`.bg-*`)

Igual que `bg-primary` / `bg-soft-primary` / `bg-outline-primary` de Bootstrap 5.

```html
<div class="bg-soft-success p-3 rounded">Aprobado</div>
<span class="bg-outline-primary rounded-pill px-3 py-1">Nuevo</span>
```

- Sólido: `.bg-primary` ... `.bg-dark`, más `.bg-light` y `.bg-white`.
- Tenue: `.bg-soft-primary` ... `.bg-soft-dark`.
- Outline: `.bg-outline-primary` ... `.bg-outline-dark`.

## Badges (`.badge`)

```html
<span class="badge badge-primary">Activo</span>
<span class="badge badge-soft-warning badge-pill">Pendiente</span>
```

- Base: `.badge` (+ `.badge-pill` para 100% redondeado).
- Sólido: `.badge-primary` ... `.badge-dark`, `.badge-pink`.
- Tenue: `.badge-soft-primary` ... `.badge-soft-dark`, `.badge-soft-pink`.

## Texto (`.text-*`)

`.text-primary` `.text-secondary` `.text-success` `.text-info` `.text-warning`
`.text-danger` `.text-dark` `.text-light` `.text-pink` `.text-white`, más los ya
existentes `.text-muted` y `.text-heading` (usan tokens neutros, no de color).

## Cards

```html
<div class="card">
  <img src="..." class="card-img-top" />
  <div class="card-header">
    <span class="card-title">Título</span>
    <a href="#" class="card-link">Ver todo</a>
  </div>
  <div class="card-body">...</div>
</div>
```

`.card` `.card-header` `.card-body` `.card-title` `.card-subtitle` `.card-header-action`
`.card-link` `.card-img-top` (imagen de portada, esquinas superiores redondeadas) y
`.card-img-overlay` (overlay con degradado oscuro para texto sobre imagen, tipo cover de
proyecto en una galería).

## Avatares

`.avatar` + tamaño: `.avatar-xs` (32px) `.avatar-sm` (40px) `.avatar-md` (56px)
`.avatar-lg` (80px) `.avatar-xl` (96px). Iniciales dentro: `.avatar-title` (usa
`primary-soft` por defecto) o variante de color `.avatar-title-secondary`
`.avatar-title-success` `.avatar-title-info` `.avatar-title-warning`
`.avatar-title-danger` `.avatar-title-dark`.

```html
<span class="avatar avatar-sm">
  <span class="avatar-title avatar-title-success">JP</span>
</span>
```

## Imágenes

- `.img-fluid` — equivalente exacto a Bootstrap: `max-width: 100%; height: auto;`. Úsalo en
  cualquier imagen de contenido (galería de proyectos, foto de perfil, thumbnail) para que
  nunca desborde su contenedor.
- `.img-thumbnail` — imagen con borde, padding pequeño y esquinas redondeadas (estilo marco).
- `.article-thumb` — miniatura cuadrada de 40px ya usada en tablas (`table-dashboard`).

## Otros grupos ya existentes (sin cambios en esta iteración)

- **Layout**: `.app-shell` `.sidebar` `.topbar` `.main-wrapper` `.page-content`
  `.page-title-box` `.breadcrumb`.
- **Stat cards**: `.stat-card` `.stat-icon` + `.stat-icon-{color}`.
- **List group**: `.list-group` `.list-group-item` `.comment-list` `.comment-item`.
- **Tabla**: `.table-responsive` `.table-dashboard`.
- **Donut CSS puro**: `.donut-chart` (sin uso hoy; el `.bar-chart` que había se retiró al pasar a una librería de gráficos — hoy Apache ECharts).
- **Grid 12 columnas**: `.row` `.col-12` `.col-lg-2` ... `.col-lg-9`. No hay `.col-4` ni `.col-6`
  sueltas: dentro de `.row` una columna sin clase ocupa 1/12 (así quedaron los campos de "Ajustar
  medidas reales", de un centímetro). Para campos en fila dentro de un modal, una grilla propia.
- **Pedidos** (app.css §24): `.pedido-tracker` (+ `-step`, `-dot`), `.pedido-timeline` (+ `-item`),
  `.pedido-consumo-lista`; "Ajustar medidas reales": `.pedido-medidas-campos` (ancho, alto y cantidad en
  una fila), `.pedido-ajuste` (ítem ajustado: lo cotizado al lado de lo que se fabrica; con
  `-referencia` en tono neutro dentro del modal) y `.pedido-ajuste-accion` (botón con aspecto de enlace).
- **Detalle de cotización** (app.css §22, Pages/Cotizaciones/Show.vue): resumen `.cotizacion-resumen` (tres
  `.stat-card`: cliente, total, estado; + `-cuerpo`, `-cliente`, `-nota`, `-estado`); documento `.cotizacion-doc` con
  `.cotizacion-doc-titulo` (no se imprime), `-marca`, `-numero`, `-tipo`, `-rotulo`, `-meta-detalles` y el total en
  franja (`.cotizacion-doc-totals .cotizacion-total-grand`); análisis interno (`.margen-interno`) con
  `.cotizacion-analisis` (+ `-cabecera`, `-kpis`, `-paneles` (lado a lado desde 2xl), `-subtitulo`, `-resultado`,
  `-pie`, `-pie-fila` (el `<details>` abierto ocupa toda la fila), `-aclaracion`, `-productos`) y los desplegables
  `.cotizacion-desplegable` (+ `-simple`, `-cuerpo`, `-lista`). Reusa `.margen-panel`/`.margen-row`, `.bi-aviso` y
  `.bi-formulas-lista`. Al imprimir solo sale el documento.
- **Ritmo vertical de pantalla**: `.page-stack` — envuelve los bloques de primer
  nivel de una página (tarjetas y `.row`) y los separa con la MISMA medida que
  `.row` separa columnas. Úsalo en vez de repartir `mb-4` por las tarjetas:
  `.row` no lleva margen inferior, así que una fila de dos tarjetas quedaba
  pegada a la de abajo, y el `mb-4` (16 px) no coincidía con el hueco entre
  columnas (24 px). Si la página es un único `.row`, no hace falta.
- **Utilidades**: `.d-flex` `.d-grid` `.flex-column` `.align-items-center`
  `.justify-content-between` `.gap-1..4` `.w-100` `.h-100` `.rounded` `.rounded-circle`
  `.rounded-pill` `.text-truncate` `.text-uppercase` `.fw-normal..bold` `.fs-xs..xl`
  `.position-relative` `.position-absolute` `.mb-0..5` `.mt-0..5` `.mx-auto`.

## Gráficos (`Components/Chart/BaseChart.vue`)

Todo gráfico del panel sale de `BaseChart.vue` (Apache ECharts 6, cargado bajo
demanda desde `Components/Chart/EchartsCore.js`). Una página declara `titulo`,
`labels`, `series` y un `formato`; el resto —marcas, tooltip, leyenda, vista de
tabla y exportación a PNG— lo pone el componente. No llamar `echarts.init` en
otro sitio ni pasarle colores a mano.

**Colores de serie**: tokens `--chart-1` … `--chart-6`, definidos en `app.css`
para claro y oscuro. Se asignan en orden y **no se ciclan**: pasadas seis series
hay que agrupar la cola en "Otros" o partir el gráfico, nunca inventar un séptimo
color. La paleta se derivó de los tonos de marca y está validada (banda de
luminosidad, croma, separación bajo daltonismo, piso de visión normal y 3:1
contra `--card-bg`); cambiar un valor a ojo la rompe.

**No son la misma cosa que los `--c-*`**: un verde semántico significa "bien". En
un gráfico el color solo dice qué serie es. Los de estado (`--c-success` /
`--c-danger`, expuestos como `positivo` / `negativo` en `UseChartTheme.js`) solo
se usan cuando la serie de verdad significa bien/mal — por ejemplo el margen por
pedido, donde además el signo se ve por el lado de la barra.

Clases del marco (no del canvas):

- `.chart` — contenedor; apila barra de herramientas, gráfico y tabla.
- `.chart-toolbar` `.chart-legend` `.chart-legend-item` (+ `.is-off`)
  `.chart-legend-key` (+ `.is-dashed` para proyecciones, `.is-band` para el rango
  probable) — la leyenda va en HTML y no dibujada en el canvas: se puede
  seleccionar, la lee un lector de pantalla y cada entrada es un botón que apaga
  o prende su serie. Su texto usa tokens de texto; el color lo lleva el
  cuadrito, nunca la palabra.
- `.chart-tooltip-title` `.chart-tooltip-row` `.chart-tooltip-key` — contenido
  HTML del tooltip de ECharts (fondo y color de texto los pone el componente).
- `.chart-actions` `.chart-action` (+ `.is-active`) `.chart-action-text` —
  botones de vista de tabla y descarga PNG.
- `.chart-canvas` — fija el alto incluyendo la banda del eje X.
- `.chart-empty` — estado sin datos.
- `.chart-table-caption` `.chart-table-num` — la tabla gemela, que es cómo se
  leen los valores sin depender del color ni del hover.

Lo que **no** es un gráfico: una sola razón contra un tope (entregas a tiempo) es
un medidor, `.reporte-progress` + `.reporte-progress-bar`; un puñado de números
sueltos es una fila de KPIs, `.reporte-kpi`.

Prop `marca-eje` (`{ etiqueta, texto }`): línea vertical punteada y rotulada sobre una
categoría, para avisar que ese punto es parcial ("Mes incompleto", "Oct-2026 en curso");
el texto se suma al título del tooltip. Solo en gráficos verticales.

## Inteligencia de negocios (`.bi-*`, app.css §26)

Pestañas `Pages/Reportes/Bi*.vue` y componentes `Components/Reporte/*`.

- `.bi-tabs` (sobre `.nav-tabs`) `.bi-filtro` `.bi-filtro-rango` `.bi-filtro-alcance`
  `.bi-select` — encabezado común (`BiEncabezado.vue`): pestañas + filtro de fechas. Su slot
  `aviso` va en `.bi-filtro-aviso` (a la derecha desde `xl`).
- `.bi-kpi` `.bi-kpi-cuerpo` `.bi-kpi-nota` + `.bi-tendencia` (`-buena` / `-mala` /
  `-neutra`) — tarjeta `BiKpi.vue` sobre `.stat-card`. La flecha dice la dirección;
  el color, si es bueno o malo según `subirEsBueno`. `sufijo` dice contra qué es la
  variación (por defecto "por mes"; el resumen semanal usa "vs. 4 semanas").
- `.bi-aviso` — nota informativa (qué datos quedan fuera de un cálculo).
- `.bi-metodo` `.bi-metodo-resumen` `.bi-metodo-titulo` `.bi-metodo-chips` `.bi-metodo-chip`
  (+ `-fuerte` / `-moderado` / `-debil` / `-neutro`) `.bi-metodo-lista` — ficha plegable
  "Cómo se calcula" bajo cada gráfico de proyección (`MetodoPronostico.vue`, un `<details>`).
- `.bi-informe-secciones` `.bi-informe-seccion` `.bi-informe-acciones` — modal "Generar
  informe" de `BiEncabezado.vue` (casillas de sección y botones PDF/CSV; un `<a>` sin
  secciones lleva `.btn.disabled`).
- `.bi-alertas-vacio` — estado vacío de las alertas (las filas están en §46).
- `.bi-columna` — columna de tarjetas apiladas con el mismo espacio que `.page-stack`.
- Simulador: en §47 (ver "Simulador en tres pasos").

## IA del BI (`.ia-*`, `.resumen-*`, `.anomalia-*`, app.css §33)

El texto de la IA siempre va junto a la evidencia de la que sale.

- Mascota del asistente (`Components/Layout/RobotIa.vue`): robot en SVG por partes, es la seña de
  "esto es la IA". Props `animo` (`reposo` | `pensando` | `contento` | `apagado` | `quieto`), `variante`
  (`completo` | `cabeza`, para 20-40 px) y `pulso` (asiente cuando cambia). CSS: `.robot-ia` +
  `.is-{animo}`, `.is-asintiendo`; tamaños `.ia-robot-cabecera`, `-bienvenida`, `-espera`, `-autor`.
  Las cabecitas de la conversación van `quieto` (no animar decenas a la vez).
- Asistente (`Components/Layout/AsistenteBi.vue`): `.ia-lanzador` (botón flotante, + `-marca`,
  `-texto`, `-titulo`, `-sub`; en móvil queda solo el disco), `.ia-panel` (+ `-cabecera`, `-identidad`,
  `-titulo`, `-sub`, `-acciones`, `-cuerpo`, `-pie`), `.ia-estado` (+ `-ok`, `-off`), `.ia-vacio`
  `.ia-sugerencias-titulo` `.ia-sugerencias` `.ia-sugerencia` (+ `-icono`, `-texto`, `-flecha`),
  `.ia-turno` `.ia-pregunta` `.ia-autor` `.ia-respuesta` (+ `-texto`, `-error`, `-meta`, `-pendiente`),
  `.ia-formulario` (caja con `:focus-within`) `.ia-campo` `.ia-enviar` `.ia-pie-notas` `.ia-atajo`
  `.ia-error` `.ia-uso` (+ `-alerta`). Es un panel lateral, no un modal: convive con la página.
- Historial del asistente (misma pieza, vista `historial`): `.ia-historial` (+ `-barra`, `-encabezado`,
  `-titulo`, `-sub`, `-filtro`, `-cuerpo`, `-dia` (cabecera de día sticky), `-conteo`, `-lista`,
  `-item` (+ `.is-error`, `.is-abriendo`), `-hora`, `-texto`, `-pregunta` (2 líneas), `-meta`, `-flecha`,
  `-nota`, `-vacio`, `-error`, `-esqueleto`), `.ia-volver`, `.ia-fecha` + `.ia-fecha-input` (fecha nativa
  en caja con `:focus-within`), `.ia-filtro-quitar`, `.ia-esqueleto` (+ `-grupo`, `-dia`, `-fila`),
  `.ia-girando`. En la conversación: `.ia-separador-dia`, `.ia-pregunta-hora` y `.ia-turno.is-destacado`
  (halo que se apaga solo al reabrir una consulta). Espera de la respuesta: `.ia-pensamiento` (burbuja de
  pensamiento con estela hacia el robot, + `-puntos`), `.ia-espera` (+ `-linea` (+ `.is-larga`), `-nota`,
  `-reloj`). Texto letra por letra: `Components/TextoTecleado.vue` (`.texto-tecleado`, `-cursor`; con la prop `ola`,
  `.is-ola` + `.texto-tecleado-letra` y una onda de opacidad que recorre las letras); es
  solo visual, va `aria-hidden` con el texto completo al lado en `.sr-only`.
- Resultado de herramienta (`Components/Reporte/ResultadoIa.vue`): `.ia-resultado` (+ `-titulo`,
  `-tabla`, `-pie`), `.ia-enlace`, `.ia-calculo` (+ `-parametros`, `-herramienta`) para "Cómo lo calculé".
- Resumen semanal (`Pages/Reportes/BiResumen.vue`), de arriba abajo: `.resumen-filtro` (barra con
  `.resumen-filtro-campo` (+ `-corto`), `-icono`, `-control`, `-estado` (badge "Resumen histórico" +
  cuándo y cómo se redactó), `-aviso`), `.resumen-titular-caja` (+ `.resumen-titular-icono`, `-cuerpo`,
  `-eyebrow`, `.resumen-titular`, `-nota`), `.resumen-tarjetas` (cuatro `BiKpi` con
  `sufijo="vs. 4 semanas"`), `.resumen-grilla` (dos columnas desde `lg`) con "Qué requiere atención" /
  "Resultados favorables": `.resumen-punto` (+ `-icono`, `-bueno`, `-alto`, `-medio`, `-cuerpo`,
  `-titulo`, `-nota`, `-vacio`) y los íconos de título `i.resumen-icono-atencion` / `-favorable`;
  `.resumen-comparar` (interruptor "Comparar con" sobre `.financiero-vista`), `.resumen-kpis` con
  `tr.is-evidencia` (se resalta al pasar por la frase que la cita) y `.resumen-kpis-referencia`
  (columna oculta en el teléfono), `.resumen-var-buena/-mala/-neutra`, `.resumen-nota`;
  `.resumen-acciones` (`.resumen-accion-item`, `-numero`, `-cuerpo`, `-enlace`, `.resumen-accion`,
  `.resumen-motivo`); `.resumen-contexto` (`-titulo`, `-pie`), `.resumen-familias` `.resumen-familia`
  (+ `-nombre`, `-barra`, `-cifra`; nombre | barra | cifra, la barra baja en el teléfono),
  `.resumen-vendedor` (+ `-icono`), `.resumen-anomalias`; `.resumen-pie`; `.resumen-vacio*`.
- Anomalías (`Pages/Reportes/BiAlertas.vue`): `.anomalia-filtro` (+ `-opcion`, `-conteo`), `.anomalias`,
  `.anomalia` (+ `.anomalia-alta` / `-media`, `.is-cerrada`) `.anomalia-icono` `-cuerpo` `-meta` `-tipo`
  `-dato` `-titulo` `-detalle` `-revisar` `-ia` `-acciones`.
- Las tablas compactas `.table-dashboard.resumen-kpis` / `.ia-resultado-tabla` anulan el
  `min-width: 640px` de `.table-dashboard` (entran en un teléfono o en el panel).

## Tabla: paginación y estados (`DataTable.vue` / `TablePagination.vue`)

Componentes en `resources/js/Components/Table/` (uso y API documentados en el propio
`DataTable.vue`). Clases que los soportan, en `app.css` sección "17. TABLA: ESTADOS Y
PAGINACION":

```html
<div class="table-responsive" :class="{ 'table-loading': loading }">
  <table class="table-dashboard">...</table>
</div>

<nav class="pagination-wrap">
  <p class="pagination-info">Mostrando 91–100 de 987 resultados</p>
  <ul class="pagination">
    <li class="page-item active"><button class="page-link">10</button></li>
    <li class="page-item disabled"><span class="page-link page-ellipsis">…</span></li>
  </ul>
</nav>

<td class="table-empty" colspan="...">No se encontraron resultados.</td>
```

- `.table-loading` — atenúa la tabla (`opacity-50 pointer-events-none`) durante una
  petición Inertia en curso.
- `.table-empty` — celda de estado vacío, texto centrado en `--text-muted`.
- `.table-sm` — modificador opcional, se combina con `.table-dashboard` (no la reemplaza):
  reduce padding y tipografía de encabezados/celdas para listados densos. Se pasa vía la prop
  `table-class` de `DataTable.vue` (acepta cualquier formato de `:class`), ej:
  `<DataTable table-class="table-sm" ...>`.
- `.table-loading-overlay` / `.table-loading-dots` / `.table-loading-dot` — loader por defecto
  de `DataTable.vue`: 3 puntos centrados sobre la tabla (colores `--c-primary`/`--c-secondary`)
  mientras `loading` es `true`. Se reemplaza por completo con el slot `#loader` si hace falta
  uno personalizado (spinner, texto, etc.).
- `.pagination-wrap` / `.pagination-info` / `.pagination` / `.page-item` / `.page-link` —
  paginador con saltos de rango (`« ‹ 1 … 8 9 10 11 12 … 100 › »`). `.page-item.active` usa
  `--c-primary`; `.page-item.disabled` y `.page-ellipsis` quedan atenuados y sin clic.

## SweetAlert2 (`Utils/AlertUtil.js`)

Los popups de SweetAlert2 (toasts de éxito/error, confirmaciones) van temados a la marca en
`app.css` sección "20. SWEETALERT2" — `.swal2-popup`, `.swal2-title`, `.swal2-confirm`,
`.swal2-cancel`, `.swal2-toast`, usando `--card-bg`/`--text-*`/`--c-primary`/`--c-secondary`. No
hay que pasar colores a mano al llamar `Swal.fire()`; el tema ya los aplica global (incluye modo
oscuro, porque `.dark` vive en `<html>` y el popup, aunque cuelga de `<body>`, hereda las
variables igual).

```js
import { showToast, showError, confirmation } from '@/Utils/AlertUtil';

showToast('Guardado correctamente.');           // toast arriba a la derecha, 3s
showToast('Algo falló.', 'error');
showError(form.errors);                          // popup centrado con la lista de errores
const ok = await confirmation('¿Eliminar este registro?', 'Confirmar');
```

Los toasts de éxito/error de las acciones CRUD (crear/editar/eliminar) **no se llaman a mano**
en cada página: hay un listener global en `Composables/UseFlashNotifications.js` (conectado una
sola vez en `app.js`) que escucha el evento `success` de Inertia y muestra `showToast()` solo
con lo que el controlador mande vía `redirect(...)->with('success', '...')` /
`->with('error', '...')` (prop compartida `flash`, ver `HandleInertiaRequests::share()`). Un
controlador nuevo no necesita `onSuccess` en el form del frontend para esto — alcanza con el
`->with(...)` del lado backend.

Para confirmar un borrado, usar `confirmation()` (ver `Pages/Sucursales/Index.vue`) en vez de un
`<Modal>` a medida — más corto y consistente en toda la app.

## Login (`.login-*`)

Clases especificas de la pantalla de acceso (`resources/js/Pages/Auth/Login.vue`), panel
partido con blob de marca:

```html
<div class="login-brand-blob bg-primary ..."></div>
<img src="/img/logo/logo-blanco.png" class="login-logo-3d w-96 max-w-full" />

<div class="login-input-group">
  <i class="fa-solid fa-user login-input-icon"></i>
  <input class="login-input" placeholder="Usuario" />
</div>
```

- `.login-brand-blob` — recorta el panel de marca en un óvalo (`border-radius: 50%`).
- `.login-logo-3d` — leve giro 3D (`perspective` + `rotateY`/`rotateX` de solo unos grados) más
  `drop-shadow`, para que el logo "mire" hacia el centro de la pantalla sin perder legibilidad.
- `.login-input-group` / `.login-input-icon` / `.login-input` / `.login-input-eye` — input tipo
  subrayado con ícono a la izquierda y botón opcional a la derecha (mostrar/ocultar contraseña),
  usando `--text-muted` / `--border-subtle` / `--c-primary` en vez de colores nuevos.

## Notificaciones (`.notif-*`)

Dropdown de la campanita en `Components/Layout/Topbar.vue` + listado completo en
`Pages/Notificaciones/Index.vue` (app.css sección "32. NOTIFICACIONES"):

```html
<Link :href="n.url" class="list-group-item-start notif-link">
  <span class="list-icon" :class="`stat-icon-${n.color}`"><i :class="n.icono"></i></span>
  <div class="min-w-0">
    <p class="list-group-item-title">{{ n.titulo }}</p>
    <p class="fs-xs text-muted">{{ n.mensaje }}</p>
  </div>
  <span v-if="!n.leida" class="notif-unread-dot"></span>
</Link>
```

- `.notif-link` — vuelve clickeable un `.list-group-item-start` completo (icono + texto) sin
  heredar el subrayado/color de enlace por defecto.
- `.notif-unread-dot` — punto `--c-primary` que marca una notificación sin leer.
- `.notif-empty` — mensaje centrado cuando no hay notificaciones.
- `.notif-footer` — pie del dropdown ("Marcar todas como leídas" / "Ver todas").
- `.notif-item-unread` — resalta la fila en el listado completo (`bg-soft-primary`) mientras
  no se marque como leída.

No reinventa colores: reusa `.stat-icon-{color}` (`primary/success/warning/danger/info`) para
el ícono según el campo `color` que manda cada `App\Notifications\*::toArray()`.

## Tema "Cordillera": layout, encabezado y panel (app.css §1-3 y §34)

- **Sidebar**: `.sidebar` (degradado azul marino + `public/img/banner/montanas-sidebar.svg` abajo).
  Secciones con `icon` en `Data/Sidebar/Nav.js` → acordeón: `.nav-section` / `.nav-section-toggle` /
  `.nav-section-list`, enlaces con `.nav-dot` en vez de icono. Sin `icon` → `.sidebar-menu-label` fijo.
  Página actual: `.nav-sidebar-link.active` (pastilla sólida `--c-primary`). El logo es `logo-blanco.png`.
- **Topbar**: `.topbar-search` (+ `.topbar-search-results` / `.topbar-search-result`, busca pantallas del
  menú con `userCan()` de `Directives/Can.js`, Ctrl+K), `.topbar-icon-btn` (redondo),
  `.topbar-icon-btn-desktop` (solo desde md), `.topbar-divider`, `.topbar-menu` / `.topbar-menu-item`
  (menú de usuario), `.quick-access*` (accesos rápidos). `Dropdown.vue` con `bare` cuando el contenido trae
  su propia superficie.
- **Banda de encabezado** (todas las pantallas, la dibuja `MainDashboardLayout`): `.page-hero`,
  `.page-hero-inner`, `.page-title`, `.page-subtitle` (prop `pageSubtitle`; si viene, reemplaza al
  breadcrumb), `.page-hero-date`. Montañas: `public/img/banner/montanas.svg`.
- **Cards**: `.card` ahora con borde fino (`--card-border`) + `--card-radius` (14px). `.card-title > i`
  pinta el icono del título en primario.
- **Iconos de KPI**: `.stat-icon` es un cuadro SÓLIDO con degradado y glifo blanco. Tonos:
  `primary success warning danger info pink cyan violet navy`. Un `.list-icon.stat-icon-*` usa el mismo
  sólido (notificaciones, actividad).
- **Panel principal** (§34): `.kpi-grid` / `.kpi-card` / `.kpi-label` / `.kpi-value` / `.kpi-delta`
  (`-up`/`-down`) / `.kpi-note`; `.dashboard-grid` / `-main` / `-aside` / `-split`; medidores
  `.dashboard-meter*`; `.activity-*`; `.ranking-*`; banda `.dashboard-cta*`.
- **Tablas**: encabezado en franja (`--table-head-bg`, sin mayúsculas) y números tabulares.
- **Badges**: píldora; los `badge-soft-*` llevan filete del mismo color.

## Reporte financiero (app.css §39, Pages/Reportes/Financiero.vue)

- Filtros en fila: `.financiero-filtros` / `-filtro-fechas` / `-filtro-sucursal` / `-filtros-acciones`.
- KPIs: `.financiero-kpis` (grilla de 4) con `.kpi-card` + `.stat-icon-*` del panel.
- `.financiero-alcance` (franja info), `.financiero-hallazgo` (frase destacada bajo un gráfico),
  `.financiero-vista` (interruptor Gráfico/Tabla, botón activo `.is-active`).
- Barras de participación (medidor, no gráfico): `.financiero-sucursales` / `-sucursal` / `-sucursal-nombre`
  / `-sucursal-pista` / `-sucursal-barra` / `-sucursal-fuera` / `-sucursal-monto`.
- Aviso con acción: `.financiero-aviso` / `-aviso-icono` / `-aviso-texto` / `-aviso-titulo` / `-aviso-accion`.
- Tabla con filtros: `.financiero-tabla-controles`, `.financiero-busqueda`, `.financiero-orden`,
  `.financiero-chip` + `-chip-danger` / `-chip-warning` (`.is-active`), `.financiero-pedido`,
  `.financiero-cliente`, `.financiero-num`, `.financiero-tabla-pie`, `.financiero-ver-mas`.
- `BaseChart` acepta `sin-tabla` para ocultar su botón "Tabla" cuando la página trae su propia tabla.

## Reporte de producción (app.css §40, Pages/Reportes/Produccion.vue)

- Reusa de §39: `.financiero-kpis`, `.financiero-alcance`, `.financiero-vista`, `.financiero-card-header`, `.financiero-num`.
- Propias: `.produccion-acciones` (barra de fecha + sucursal + PDF), `.produccion-fecha`, `.produccion-sucursal`,
  `.produccion-fila`, `.produccion-hallazgo` / `-hallazgo-titulo` (bombilla bajo un gráfico),
  `.produccion-pregunta` / `-pregunta-titulo`, pasos del taller `.produccion-flujo` / `.produccion-paso`
  (flecha entre pasos desde lg) / `-paso-icono` / `-paso-nombre` / `-paso-valor` / `-paso-unidad`.

## Panorama del BI por secciones (app.css §41, Pages/Reportes/BiPanorama.vue)

- `.panorama-destacados` (franja de 3 datos bajo los KPIs), `.panorama-seccion` (título numerado),
  `.panorama-vista` (variante chica de `.financiero-vista`), `.panorama-nota-final`.
- `ChartCard` acepta `subtitulo` (→ `.chart-card-subtitulo`) y `hallazgo-abajo` (→ `.chart-card-pie`).
- Avance de cotizaciones: `.panorama-avance` / `-etapa` / `-nombre` / `-pista` / `-barra` (`-barra-0..3`) / `-paso` (`.is-peor`),
  `.panorama-aclaracion`.
- Tablas-ranking: `.panorama-ranking` (filas `.is-clic`), `-ranking-puesto`, `-ranking-nombre`, `-ranking-barra`.

## Preguntas para decidir, una a la vez (app.css §42, Pages/Reportes/BiPreguntas.vue)

- Índice: `.preguntas-vista`, `.preguntas-menu` / `-menu-titulo` / `-menu-grupo` / `-menu-rotulo` / `-menu-item` (`.is-activa`)
  / `-menu-numero`, `.preguntas-buscar`.
- Pregunta: `.preguntas-detalle`, `.preguntas-miga`, `.preguntas-cabecera`, `.preguntas-titulo`, `.preguntas-etiqueta`,
  `.preguntas-respuesta` (+ `-titulo`), cuenta `.preguntas-cuenta` / `-cuenta-dato` (`.is-total`) / `-cuenta-signo`,
  `.preguntas-desvio`, `.preguntas-aviso`, `.preguntas-tabla-caja` / `-tabla-cabecera`, `.preguntas-accion` (+ `-titulo`),
  `.preguntas-calculo` / `-calculo-boton` / `-calculo-texto`, `.preguntas-pie`.
- Respuesta: `.preguntas-respuesta-icono` (foco en círculo) y `.preguntas-respuesta-texto` (primera oración en
  `<strong>`); acciones numeradas `.preguntas-accion-icono` / `-accion-numero`; plegable `.preguntas-calculo-info` /
  `-calculo-flecha`.
- Pregunta 2 (Aumento de materiales, `Components/Reporte/PreguntaAumentoMateriales.vue`): `.aumento-cuerpo`,
  `.aumento-kpis` (tres `BiKpi`, columnas auto-fit), `.aumento-paneles` / `.aumento-panel`, `.aumento-tabla`, `.aumento-nota`.
- Pregunta 11 (Acciones, `Components/Reporte/PreguntaAcciones.vue`): `.acciones-cuerpo`, `.preguntas-bajada`
  (respuesta como bajada del título), `.acciones-caja` con
  `.acciones-tabla` (`td.acciones-titulo`, `td.acciones-monto`; el monto lleva `.badge badge-soft-*` según su tipo),
  `.acciones-ver` (enlace "Ver pregunta N"), `.acciones-nota`; cuadros `.acciones-lecturas` / `.acciones-lectura`
  (`-cabecera`, `-icono`, `-pie`), `.acciones-escenario`, `.acciones-cobros` (`-monto`, `-antiguo`).

## Demanda y ventas por bloques (app.css §43, Pages/Reportes/Bi.vue)

- Bloque numerado `Components/Reporte/BiBloque.vue` (props `numero`, `titulo`, `subtitulo`, `tarjeta`; slots
  `accion`, `ayuda`, `etiqueta` y por defecto): `.bi-bloque` (+ `-cabecera`, `-numero`, `-texto`, `-titulo-linea`,
  `-titulo`, `-subtitulo`, `-accion`, `-cuerpo`). Con `tarjeta` el bloque es la tarjeta (`.card.bi-bloque-tarjeta`,
  §47).
  Reutilizable en otras pestañas largas.
- Interruptor `Components/Reporte/BiInterruptor.vue` (`v-model`, `texto`; es un `<button role="switch">`):
  `.bi-interruptor` (`.is-activo`) con `-pista`, `-perilla`, `-texto`. Para capas opcionales de un gráfico.
- Avisos en recuadro naranja: `.bi-mes-incompleto` y `.bi-clientes-seguimiento`. El del mes lo arma
  `Components/Reporte/AvisoMesIncompleto.vue` (prop `mes` = `periodo.mes_incompleto`; el slot dice qué significa en
  esa pestaña) y va en el slot `aviso` de `BiEncabezado`.
- `.bi-nota` (nota al pie con ícono; `.bi-nota-franja` suelta entre tarjetas), `.bi-ver-mas` (enlace de texto que
  despliega "Ver los 10…").
- Confiabilidad de una proyección `Components/Reporte/ConfiabilidadPronostico.vue` (prop `metodo`): recuadro
  `.bi-confiabilidad` (+ `-warning` / `-info` / `-success` según el ajuste) que va en una cabecera
  `.card-header.bi-cabecera-con-aviso`. Dice el ajuste, cuánto se equivocó en la prueba y si otro método acertó más.
- `.bi-sugerencia` (qué hacer, verde con ícono; `.bi-sugerencia-franja` suelta a todo el ancho), `.bi-col-opcional`
  (columna de tabla oculta en el teléfono).
- Bloque 2: `.bi-demanda-par` (dos tarjetas), `.bi-demanda-clientes` (tabla 2/3 + concentración 1/3 desde `xl`);
  tabla `.panorama-ranking.bi-clientes-tabla` con `.bi-col-opcional`,
  `.bi-cliente-ultima` (`.is-alerta`), `.bi-cliente-variacion` (§36), `.bi-clientes-otros`, `.bi-clientes-pie`;
  medidor `.bi-concentracion` (+ `-barra`, `-principales`, `-otros`, `-leyenda`, `-punto`, `-dato`) con su `.bi-sugerencia`.
- Bloque 3: `.card-title.bi-titulo-etiqueta` (título con badge que baja entero), enlace a otra pestaña
  `.card.bi-enlace` (+ `-icono`, `-texto`, `-accion`).

## Costos y materiales por bloques (app.css §44, Pages/Reportes/BiCostos.vue)

Mismo esquema que Demanda y ventas (§43): `BiBloque`, `BiInterruptor`, `ConfiabilidadPronostico`, `.bi-nota`,
`.bi-sugerencia`, `.bi-col-opcional`. Arriba, la franja `.bi-aviso` dice el alcance (precios de toda la empresa, consumo
acotado) en lugar de las etiquetas "Precios" de cada tarjeta.

- Grillas de cada par de tarjetas: `.bi-costos-material` y `.bi-costos-consumo` (7/5 desde `lg`), `.bi-costos-impacto` (5/7
  desde `xl`).
- "Qué significa para este material": `.bi-costos-lectura` con `.margen-panel` / `.margen-row`.
- "Qué revisar": lista numerada `.resumen-acciones` (§33).
- Tabla de impacto `.table-dashboard.bi-costos-tabla` (sin el mínimo de 640 px) y su pie `.bi-costos-tabla-pie`.

## Presupuestos y rentabilidad por bloques (app.css §45, Pages/Reportes/BiRentabilidad.vue)

Mismo esquema que §43 y §44 (`BiBloque`, `ConfiabilidadPronostico`, `AvisoMesIncompleto`, `.bi-nota`, `.bi-demanda-par`).

- `.bi-definicion` — recuadro "¿Qué significa X % sobre costo?": un indicador traducido a bolivianos (ícono, `strong`
  como título y el texto).
- `.bi-rentabilidad-cuerpo` — cuerpo de tarjeta en columna (gráfico, notas, confiabilidad y método).
- `.bi-semaforo` (+ `-titulo`, `-zonas`) — cómo salieron los presupuestos en el semáforo, con las píldoras
  `.semaforo .semaforo-verde/-amarillo/-rojo`.
- `.bi-pasos` / `.bi-paso` — "Qué revisar" en una fila de pasos (columna en el teléfono); el número y el texto reusan
  `.resumen-accion-numero`, `.resumen-accion-cuerpo`, `.resumen-accion`, `.resumen-motivo` (§33).

## Alertas: dos pestañas y una fila por alerta (app.css §46, Pages/Reportes/BiAlertas.vue)

Pestañas "Costo y rentabilidad" y "Anomalías estadísticas" sobre `.nav-tabs` (botones `role="tab"`); no se suman.
La tarjeta lleva `id="anomalias"`: con ese ancla (o `?anomalias=`) abre directo en la segunda pestaña.

- `.bi-alertas-panel`, `.bi-alertas-pestanas`, `.nav-tabs.bi-alertas-tabs`, `.bi-alertas-diferencia` (aviso de que no se suman).
- Cabecera y filtros: `.bi-alertas-cabecera`, `.bi-alertas-titulo`, `.bi-alertas-bajada`, `.bi-alertas-filtros`, `.bi-alertas-buscar`.
- Fila `.bi-alertas-filas` > `.bi-alerta-fila` (`.is-alta` / `.is-media`), grilla con áreas nombradas (estado | cuerpo |
  datos | revisar | botón; en md dos renglones, en el teléfono apilada — el mapa de áreas va en los tres tamaños):
  `-estado`, `-icono`, `-etiquetas`, `-tipo`, `-cuerpo`, `-accion`, `-ref`, `-cliente`, `-datos`, `-metricas` (dl),
  `-nota`, `-barras` / `-barra` / `-barra-fila` / `-pista` (`.is-registrado` / `.is-previsto`), `-revisar`, `-accion-boton`.
- `.bi-alertas-paginacion`, `.bi-alertas-paginas` (botones, `.is-activa`), `.bi-alertas-puntos`.
- `.bi-alertas-colores` (`<details>` "¿Qué significan los colores?", + `-flecha`), `.bi-alertas-otras`, `.bi-alertas-enlace`
  (botón con aspecto de enlace), `.bi-alertas-criterios` con las fichas `.bi-reglas` / `.bi-regla` (+ `-success` /
  `-warning`, `-cabecera`, `-icono`, `-cantidad`, `-nombre`, `-que`, `-estado`, `-ver`).
- `.bi-anomalias-resumen` (+ `-texto`, `-accion`) — resumen de la otra pestaña bajo la lista.

## Simulador en tres pasos (app.css §47, Pages/Reportes/BiSimulador.vue)

Cada paso es un `BiBloque` con `tarjeta`: 1. datos de partida y 2. supuestos lado a lado (`.bi-demanda-par`, que acá es
un `<form>`), 3. resultado a lo ancho. Los avisos en recuadro reusan `.bi-aviso`; las notas, `.bi-nota`; "Ver fórmulas",
`.bi-ver-mas`; la recomendación, las píldoras `.semaforo`.

- `.bi-bloque.bi-bloque-tarjeta`, `.bi-simulador-resultado` (con `scroll-margin-top` para "Calcular escenario").
- Paso 1: `.bi-simulador-campos`, `.bi-campo-nota` (aclaración bajo un campo).
- Paso 2: `.bi-supuestos` > `.bi-supuesto` (grilla etiqueta | barra | casilla de %): `-etiqueta`, `.bi-range`,
  `-valor` (casilla numérica sin flechas con el `%` adentro), `-casilla` (checkbox con `small`), `.bi-check`;
  botones `.bi-simulador-botones` y `.bi-simulador-pendiente` ("Hay cambios sin calcular").
- Paso 3: veredicto `.bi-veredicto` (+ `-verde` / `-amarillo` / `-rojo`, `-icono`, `-titulo`, `-detalle`),
  indicadores `.bi-simulador-kpis` (BiKpi sin sombra), tabla `.table-dashboard.bi-simulador-tabla` (sin el mínimo de
  640 px en el teléfono, con la columna Base en `.bi-col-opcional`; filas `.is-destacada`), pie `.bi-simulador-pie`
  con `.bi-formulas` (`<details>`) y `.bi-formulas-lista` (dl).

## Al agregar una clase nueva

Sigue el patrón de color de tres variables (`--c-{nombre}` / `-dark` / `-soft`) y añade la
clase al grupo correspondiente (`btn-`, `bg-`, `badge-`, `text-`, `avatar-title-`) en vez de
crear una convención nueva. Actualiza esta tabla cuando lo hagas.
