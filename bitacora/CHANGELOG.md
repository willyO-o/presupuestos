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

## 2026-10-19 — Tema "Cordillera": nuevo look del panel (sidebar, topbar, encabezado y dashboard)

**Qué cambió:**
- **Tema visual** nuevo según la referencia: sidebar azul marino con degradado y silueta de montañas,
  secciones del menú como acordeón con icono (enlaces con punto, página actual en pastilla azul sólida),
  topbar blanco con buscador en píldora, banda de encabezado celeste con la cordillera y la fecha en
  TODAS las pantallas. Fuente **Plus Jakarta Sans**. Tarjetas con borde fino y radio 14px, iconos de KPI
  sólidos con degradado, tablas con encabezado en franja, badges en píldora. Modo oscuro re-tonado a
  azul marino.
- **Dashboard rehecho**: 5 KPIs con variación contra el mes anterior, ventas por mes del año contra el
  anterior, actividad reciente (notificaciones), pedidos recientes, pedidos por etapa, productos más
  vendidos y una banda con conversión y entregas a tiempo. Saludo "¡Hola, {nombre}!".
- **Buscador del topbar funcional** (antes era decorativo): salta a cualquier pantalla del menú a la que
  el usuario tiene permiso, con Ctrl+K y flechas. Nuevo menú de **accesos rápidos**.

**Por qué:**
- Pedido del usuario: llevar el tema principal al diseño de la captura de referencia, con aspecto
  profesional.
- Los KPIs comparan contra el **mismo corte** del mes anterior (del 1 al mismo día): comparar contra el
  mes completo daría siempre una caída falsa a principio de mes.

**Cómo:**
- Tokens en `:root`/`.dark` de `app.css` (nuevo `--brand-navy`, `--card-border`, `--hero-bg-*`,
  `--input-*`, `--table-head-bg`, `--c-cyan`, `--c-violet`); §1-3 reescritas y §34 nueva para el panel.
  Montañas como SVG propios en `public/img/banner/`.
- `ResumenDashboardService` suma `ventas_mes(_anterior)`, `cotizaciones_mes_anterior`,
  `ingresos_mes_anterior`, `pedidos_recientes`, `productos_mas_vendidos` y `ventas_por_mes` pasa a 12
  meses del año con el año anterior (`null` en meses futuros). Todo sigue acotado por sucursal.
- `MainDashboardLayout` acepta `pageSubtitle`; `Directives/Can.js` exporta `userCan()`;
  `Dropdown.vue` acepta `bare`. Tests nuevos en `ReporteControllerTest`.

---

## 2026-10-13 — Pronósticos del BI: intervalo de predicción, validación e informe PDF/CSV

**Qué cambió:**
- **Método (Fase A):** el "rango probable" ahora es un **intervalo de predicción al 80 %** (t de
  Student, se ensancha hacia el futuro) en vez de ±1 error típico. Cada pronóstico calcula **R²** y
  una **validación retrospectiva**: oculta los últimos 3 meses reales y compara el error (MAPE) de la
  recta contra **Holt** y la **media móvil**.
- **El mes en curso ya no entra en la recta** de los totales (pedidos, montos, consumo, utilidad):
  al día 13 de 31 se leía como una caída. Se sigue mostrando y se estima su cierre. Promedios,
  porcentajes y precios lo usan entero. Ninguna fecha del filtro pasa de hoy.
- Bajo cada gráfico de proyección, ficha plegable **"Cómo se calcula"**: método, meses usados,
  R², intervalo y error de validación de ESE gráfico.
- **Informe de proyecciones (Fase B):** botón "Generar informe" en el encabezado del BI → **PDF**
  (conclusiones, resumen ejecutivo, gráfico + lectura en texto automático + tabla por indicador,
  alertas y anexo metodológico) y **CSV** para Excel (series mes a mes, ficha del método, alertas).
- **Datos de prueba configurables:** `BI_DEMO_MESES_HISTORIAL` (por defecto 24) en el seeder.
- README: instalación paso a paso, datos de prueba de 12/24 meses, método estadístico, informe e
  instalación del asistente de IA.

**Por qué:** pedido del usuario. Su docente preguntó qué método estadístico usan los gráficos y
cuántos meses de historia toman. Además, los resultados solo existían como imágenes y tablas en
pantalla. Al revisarlo apareció el sesgo del mes en curso.

**Cómo:**
- `Pronostico::analizar()` devuelve la proyección, la variación y la ficha `metodo`. `proyectar()`
  queda como atajo. `Estadistica::tStudentCuantil()` invierte la t de Student (beta incompleta +
  bisección), sin tablas fijas. Holt elige α y β en una grilla de 0,1 a 0,9.
- `PeriodoReporte::pronostico($serie, piso, sumaDelMes)` es el puente con el calendario: recorta los
  meses incompletos de los bordes y agrega desde/hasta y `explicacion` (texto de
  `RedaccionPronostico::ficha()`, el mismo que imprime el anexo del PDF).
- Front: `Utils/SerieProyectada.js` (una sola función arma proyección y banda en las 3 pestañas; la
  línea sale del último mes completo y pasa por el cierre estimado), `Components/Reporte/MetodoPronostico.vue`,
  CSS `.bi-metodo-*` y `.bi-informe-*` (§26).
- Informe: `InformeProyeccionesService` NO calcula nada propio: llama a los mismos servicios que
  las pestañas (mismo alcance por sucursal). Texto automático = plantillas en `RedaccionPronostico`,
  no IA. El PDF va por `GeneradorPdf::informeProyecciones()`, y los gráficos los dibuja FPDF
  (trait `Documentos\DibujaGraficos`: polígono y punteado con operadores PDF a mano) en vez de
  capturar ECharts: es determinista, no depende de tener la pestaña abierta y los tests lo leen.
  Rutas `reportes.bi.informe.{pdf,csv}` bajo `can:reportes.bi`.
- Trampa: los textos del PDF son cp1252. `−` (U+2212), `√`, `ŷ` y `x̄` no existen ahí y salen como
  `?`: en el PDF se usa `-` y "raíz(...)".
- Tests: `PronosticoTest` (24: tabla t, intervalo, R², Holt, MAPE, validación, mes en curso, tope
  de fecha), `InformeProyeccionesTest` (9) y `HistorialComercialMesesTest` (1). Suite completa en
  verde (663). Verificado en Edge contra una base demo SQLite de 12 meses (claro, oscuro y móvil,
  sin errores de consola) y revisando el PDF renderizado página por página.

---

## 2026-10-12 — Asistente IA: burbuja de pensamiento y texto que se escribe solo

**Qué cambió:**
- Mientras se espera la respuesta aparece una burbuja de pensamiento junto al robot (con su
  estela de circulitos) con puntos grandes animados y "Pensando".
- El texto de la etapa ("Calculando los resultados", …) se escribe letra por letra con un
  cursor, y va en gris tenue para que se lea como estado y no como respuesta.
- Además, una onda de opacidad recorre el texto de la etapa (de tenue a sólido, letra por
  letra), al ritmo de los puntos de carga (prop `ola` de `TextoTecleado`).

**Por qué:** pedido del usuario — que la espera se note viva y que el texto de carga no se
confunda con la respuesta.

**Cómo:** componente nuevo `Components/TextoTecleado.vue` (24 ms por letra, reinicia al
cambiar el texto; con "reducir movimiento" lo muestra entero). El lector de pantalla recibe
la frase completa en un `.sr-only`, no letra por letra. Se quitó la transición de fundido
anterior (`ia-etapa`). Verificado en Edge (claro y oscuro) reteniendo la pregunta en el
navegador: tecleo, cursor, texto para lector y sin errores de consola.

---

## 2026-10-12 — Asistente IA: mascota robot animada en SVG

**Qué cambió:**
- El asistente tiene mascota: el robot de XtraPubli (visor con ojos sonrientes, auricular,
  pluma de la marca, burbuja de chat y pulgar arriba) reemplaza al ícono de varita en el
  botón flotante, la cabecera, la bienvenida, la espera y la firma de cada respuesta.
- Se mueve según lo que hace el asistente: en reposo parpadea y mece la pluma; mientras
  espera la respuesta la burbuja "escribe", mira alrededor y asiente con cada cambio de
  etapa del texto de carga; al llegar la respuesta celebra una vez; con la IA no
  disponible los ojos se apagan. Al pasar el mouse por el botón saluda con el pulgar.

**Por qué:** pedido del usuario — usar la ilustración del robot, liviana y animada, en
sintonía con los textos de carga.

**Cómo:**
- La ilustración original es un PNG de 1,5 MB: se redibujó a mano en SVG por partes
  (`Components/Layout/RobotIa.vue`, ~8 KB de fuente, sin archivos que descargar). Se
  descartó vectorizarla automáticamente (cientos de KB, sin partes animables), un WebP
  (solo se podría mover entero) y Lottie/Rive (dependencia nueva de 60-250 KB).
- Animaciones solo de transform/opacity en CSS (`app.css`, "Asistente: mascota"); las
  cabecitas de la conversación van quietas. Con "reducir movimiento" no se anima nada.
- Ids de degradados únicos por instancia (`useId`): un id repetido apuntaría a la copia
  del botón, que está oculta con display:none, y el navegador no lo pintaría.
- Verificado en Edge contra la app (claro y oscuro): estados, asentir por etapa, sin errores.

---

## 2026-10-12 — Asistente IA: espera con etapas, reloj y recuperación

**Qué cambió:**
- Mientras se espera la respuesta, el loader muestra qué está haciendo el asistente
  ("Entendiendo tu pregunta" → "Buscando los datos" → … → "Redactando la respuesta"),
  un reloj que avanza (`0:42`) y la nota "Suele tardar 1 a 2 minutos". Pasados los 100 s
  avisa que tarda más de lo normal y pide no recargar.
- Si se intenta recargar o cerrar la pestaña durante la espera, el navegador pide confirmar.
- Si igual se cambia de página o se recarga, al volver a abrir el panel la espera se
  retoma (con el tiempo real transcurrido) y la respuesta aparece sola cuando llega.
- "Nueva conversación" queda deshabilitada mientras se espera.

**Por qué:** pedido del usuario — las respuestas reales tardan ~2 minutos (116 y 146 s
medidos en `ia_uso`) y sin señales de vida el usuario recargaba creyendo que se colgó.

**Cómo:**
- Etapas por tiempo transcurrido (`ETAPAS_ESPERA`), no un avance real: el servidor no lo
  informa. A propósito sin "ya casi listo" ni porcentajes, que a los 2 minutos dejarían
  de ser creíbles. Solo la etapa se anuncia al lector de pantalla, no el reloj.
- La pregunta en curso se guarda en `sessionStorage`; al montar el panel se sondea
  `reportes/bi/asistente/consultas` cada 4 s (máx. 5 min) hasta encontrarla por texto e
  instante. Por eso el resumen del historial ahora incluye `fecha` (ISO, UTC).
- Verificado en Edge reteniendo la petición en el navegador (sin llamar a Gemini):
  etapas, reloj, aviso, bloqueo de "Nueva" y limpieza tras un corte de red.

---

## 2026-10-12 — Asistente IA: historial de consultas por fecha y rediseño del panel

**Qué cambió:**
- **Historial de consultas** dentro del panel del asistente (botón "Historial" en la
  cabecera): las últimas 20 preguntas del usuario agrupadas por día (Hoy, Ayer, "Lunes,
  6 de octubre"), con hora, cuántos gráficos/tablas trajo cada respuesta o "Sin
  respuesta". Selector de fecha para ver las consultas de un día puntual.
- Al elegir una consulta se reabre su conversación, se baja hasta esa pregunta y se
  resalta un momento; se puede seguir preguntando en ella. La conversación muestra
  separadores de día y la hora de cada pregunta.
- Rediseño del lanzador y del panel: marca propia del asistente (degradé azul → teal),
  botón de dos líneas "Asistente IA", sugerencias con ícono, campo de escritura unificado.
- Corrección: el loader "Consultando los datos…" salía en vertical.

**Por qué:** pedido del usuario — poder volver a ver lo que se preguntó al asistente,
por fecha, y que el botón del asistente se identifique mejor.

**Cómo:**
- Las consultas ya se guardaban en `ia_consulta` (sin vencimiento); faltaba leerlas.
  Endpoint nuevo `GET reportes/bi/asistente/consultas?fecha=` (`HistorialAsistenteRequest`)
  y `historial?consulta_id=` para reabrir una conversación (404 si no es del usuario).
  Una conversación de más de 20 consultas se abre en la ventana que rodea a la elegida.
- El día y la hora salen del servidor en hora de la empresa (`ia.zona_horaria`):
  `created_at` está en UTC y una consulta de las 22:00 en La Paz cae al día siguiente
  en UTC; agrupar en el navegador desalinearía la lista con el filtro.
- Todo en el mismo panel (no un modal ni otra página): el asistente convive con el
  reporte que se está mirando. Escape retrocede un nivel (historial → chat → cerrar).
- Tests: 6 nuevos en `AsistenteBiTest` (límite de 20, solo las propias, filtro por día en
  hora de La Paz, reabrir conversación, ventana de conversación larga, permiso).
  `IaConsultaFactory` nueva. Verificado en Edge contra la app real (claro, oscuro, móvil).

---

## 2026-10-12 — IA en el BI: anomalías, resumen semanal, asistente y datos de 24 meses

**Qué cambió:**
- **Detección de anomalías** (diaria, 06:30): caídas o alzas de cotizaciones por
  sucursal, ventas con margen muy bajo, vendedores sin actividad y clientes grandes
  que dejaron de comprar. Se ven en la pestaña Alertas con estado nueva / revisada /
  descartada; las de prioridad alta llegan a la campanita.
- **Resumen semanal** (lunes 07:00): pestaña nueva "Resumen semanal" con titular,
  destacados, riesgos y recomendaciones, consolidado y por sucursal, junto a la
  tabla de indicadores de la que sale (al pasar por una frase se resaltan sus
  indicadores). Historial de semanas y botón para regenerar.
- **Asistente de preguntas** en lenguaje natural: panel lateral disponible en todo
  el panel para quien tenga el permiso `reportes.asistente`. Cada respuesta trae
  gráficos y tablas dibujados con los datos de la base y un "Cómo lo calculé".
- **Familias de producto**: tabla `familia_producto` y campo en el CRUD de
  categorías (6 familias, clasificación automática por palabras clave).
- **Rol Gerente** (ve toda la empresa) y permiso delegable `reportes.asistente`.
- **Datos de prueba de 24 meses** coherentes (`HistorialComercialSeeder`).

**Por qué:** pedido del usuario — fases 1 y 2 de la propuesta de IA para el BI,
con Gemini como proveedor principal, tope de USD 20 al mes, solo consultas de
lectura y sin enviar datos sensibles.

**Cómo:**
- SDK oficial `laravel/ai` 1.2 (requirió subir `laravel/boost` 2.5 → 2.10 por
  `laravel/mcp`; Laravel no cambió). Toda llamada pasa por `App\Ai\ServicioIa`:
  interruptor, proveedores con clave, tope mensual con el costo de cada llamada en
  `ia_uso`, failover Gemini → Claude.
- **La IA no calcula cifras**: redacta hechos calculados en PHP o elige entre 8
  herramientas cerradas (`App\Ai\Tools`). Un verificador descarta todo texto con un
  número que no esté en los hechos y se publica la plantilla automática. Sin claves
  o sin presupuesto, resumen y anomalías salen igual con plantilla.
- **Solo lectura, en tres capas**: conexión `mariadb_lectura` con
  `SET SESSION TRANSACTION READ ONLY`, guardia que solo deja pasar SELECT, y
  usuario MariaDB con permiso por tabla y columna (`php artisan ia:usuario-lectura`).
  Un minimizador quita contraseñas, tokens, correos, teléfonos, NIT, CI y
  direcciones antes de que algo salga hacia el proveedor.
- Estadística: cola de Poisson para conteos semanales (el z robusto con MAD era
  demasiado sordo con números chicos), percentil 10 para márgenes, ritmo propio
  de cada vendedor y cliente; el resumen compara contra el promedio de 4 semanas
  porque una semana sola salta cientos de por ciento con un pedido corporativo.
- Seeder: simula mes a mes con los servicios reales y `Carbon::setTestNow()`;
  reemplaza a Compra/Cotizacion/Pedido/PagoSeeder. El `CompraSeeder` anterior
  compraba por rollo sobre materiales medidos en m² (lona a Bs 2.900/m²), lo que
  inflaba todos los costos. Sucursales, plantel, clientes y proveedores ficticios
  y curados; sin productos ni materiales inventados por la factory. Escenarios
  plantados para que cada alerta tenga qué mostrar.
- Trampas encontradas: nombres de modelo con puntos rompen `config("ia.precios.x")`;
  `IA_DB_USERNAME=` vacío dejaba la conexión sin usuario; el guardia de solo lectura
  se registraba por nombre de conexión y no sobrevivía a una reconexión; en sqlite
  `updateOrCreate` sobre columnas `date` duplicaba.
- Tests: `IaServicioTest`, `AnomaliasBiTest`, `ResumenSemanalTest`,
  `AsistenteBiTest`, `BiIaPantallasTest`, `HistorialComercialSeederTest`,
  `Unit/IaSeguridadTest`. Suite completa en verde; verificado en navegador contra
  una base demo (claro, oscuro y móvil, sin errores de consola).

## 2026-10-12 — Inteligencia de negocios ampliada + gráficos con Apache ECharts

**Qué cambió:**
- Todos los gráficos del sistema (Dashboard, Reporte financiero, Producción e
  Inteligencia de negocios) se dibujan ahora con **Apache ECharts 6** en vez de
  Chart.js 4 (desinstalado). Las páginas existentes no cambiaron su código de
  gráficos: se reemplazó por dentro `Components/Chart/BaseChart.vue`.
- Inteligencia de negocios pasó de 1 pantalla a **5 pestañas**, todas con
  **filtro de fechas** (por defecto, últimos 12 meses) que se conserva al
  cambiar de pestaña:
  1. *Demanda y ventas* (la pantalla original, ahora filtrable y con rango
     probable en la proyección).
  2. *Costos y materiales*: proyección de costos futuros (índice de costo de
     materiales base 100 + proyección por material), consumo de materiales
     (real de producción vs. presupuestado en las hojas de costo), impacto en
     Bs de las variaciones de precio y tendencia.
  3. *Presupuestos y rentabilidad*: pronóstico de presupuestos (monto y
     cantidad), tendencia ticket vs. costo promedio, proyección de rentabilidad
     contra los umbrales del semáforo y distribución del semáforo.
  4. *Alertas*: aumento de costo de materiales, presupuestos vivos con margen
     bajo, presupuestos pendientes con costos desactualizados (re-evaluados con
     el motor de margen), sobrecosto de material en pedidos y tendencia
     sostenida de costos.
  5. *Simulador de decisiones*: "¿qué pasa si…?" con costo de materiales,
     ajuste de precio, descuento, volumen y traslado al precio; muestra
     semáforo, precio mínimo para verde, tolerancia de costo y sensibilidad.

**Por qué:** pedido del usuario — cubrir con BI las proyecciones de costos,
consumo de materiales, rentabilidad, pronóstico de presupuestos, tendencias,
impacto de variaciones, alertas, simulador y filtros por fecha, usando ECharts.

**Cómo:**
- `Components/Chart/EchartsCore.js` registra solo las piezas usadas (línea,
  barra, grid, tooltip, markLine, markArea, aria, canvas) y se carga con
  `import()`: chunk propio (~188 KB gzip) que solo bajan las pantallas con
  gráficos. `BaseChart` mantiene su API y suma: serie `banda` (rango probable),
  eje derecho, `umbrales`, `inicioProyeccion`, `enteros`, `ajustarEscala`,
  leyenda que apaga/prende series, ResizeObserver y tooltip con HTML escapado.
- Backend: `PeriodoReporte` (rango + meses) y `Pronostico` (regresión lineal,
  media móvil, proyección ± error típico) compartidos; servicios nuevos
  `CostosMaterialesService`, `PresupuestosRentabilidadService`,
  `AlertasNegocioService` y `SimuladorDecisionesService`; `config/bi.php` con
  umbrales (aumento de costo 10 %, tendencia 2 %/mes, 3 meses de proyección).
  Rutas `reportes.bi.{costos,rentabilidad,alertas,simulador}` bajo el permiso
  `reportes.bi` existente (sin permisos nuevos ni reseed).
- La rentabilidad excluye las cotizaciones sin hoja de costos (anteriores al
  motor de margen guardan costo 0) y lo avisa en pantalla. Precios de material
  se rotulan "toda la empresa"; consumo, presupuestos y alertas respetan la
  sucursal del usuario.
- El simulador no tiene endpoint: usa `Utils/MotorMargen.js` (espejo del motor
  que ya usaba el formulario de cotización) con la config del servidor.
- Trampa encontrada: en sqlite las columnas `date` se guardan con hora, así que
  filtrar con `'AAAA-MM-DD'` excluía el último día → se filtra con los Carbon
  del periodo. Y 120/100 − 1 = 0,1999… en flotante: las alertas comparan
  redondeando.
- CSS en app.css §26 (`.chart-legend-item.is-off`, `.chart-tooltip-*`, `.bi-*`);
  catálogo de clases y reglas `.ai/rules/{js-pages,reporte}.md` actualizados.
- Tests: `InteligenciaNegociosAvanzadoTest` (19), `PronosticoTest` (8) y un caso
  nuevo de filtro en `InteligenciaNegociosServiceTest`. Suite completa en verde;
  verificado en navegador (claro, oscuro y móvil, sin errores de consola).

## 2026-09-17 — Notificaciones internas: administrador y vendedor

**Qué cambió:** se agregó un sistema de notificaciones in-app (campanita del
topbar + página "Ver todas") con 6 eventos de negocio:

- Para el rol `administrador`: se emite una orden de compra (`compras.store`),
  se registra un pago (`pagos.store`), un cliente solicita una cotización
  desde el portal (`portal.solicitar.store`).
- Para el vendedor dueño de la cotización de origen: se aprueba su cotización
  (`cotizaciones.aprobar` interno y `portal.responder` del cliente), se
  modifica el avance de un ítem de su pedido (`pedidos.detalle.estado`), se
  emite una nota de entrega de su pedido (`notas-entrega.store`).

**Por qué:** pedido explícito del usuario — que administrador y vendedor se
enteren de estos hitos sin tener que revisar cada listado a mano.

**Cómo:**
- Tabla nativa `notifications` (`make:notifications-table`) + canal
  `database` únicamente (sin mail: `MAIL_MAILER=log` en este entorno, y una
  notificación in-app no debe depender de un worker de cola — ninguna clase
  implementa `ShouldQueue`, se envían síncronas).
- 6 clases en `App\Notifications\*` (`OrdenCompraEmitida`, `PagoRegistrado`,
  `CotizacionSolicitada`, `AvancePedidoActualizado`, `CotizacionAprobada`,
  `OrdenEntregaEmitida`), cada una con `toArray()` devolviendo
  `titulo/mensaje/url/icono/color` — `color` mapea directo a las clases
  `.stat-icon-{color}` ya existentes (sin inventar paleta nueva).
- `User::administradores()` (nuevo, `app/Models/User.php`): usa
  `whereHas('roles', ...)` en vez del scope `role()` de Spatie — ese scope
  **lanza** `RoleDoesNotExist` si el rol no existe todavía en la fila
  `roles` (rompía `CompraControllerTest`/`PortalClienteTest`, que no seedean
  `administrador`), y un efecto secundario de notificación nunca debe tumbar
  la operación principal.
- `Cotizacion::vendedor()` / `Pedido::vendedor()` (nuevo): resuelven el
  `User` del empleado dueño de la cotización de origen; `null` si la
  solicitó el cliente por el portal sin asignar (`empleado_id` nulo) o el
  empleado no tiene cuenta vinculada — en ese caso simplemente no se notifica
  a nadie del lado vendedor.
- `PedidoController::actualizarEstado` evita auto-notificar: si quien avanza
  el ítem es el mismo usuario que el vendedor dueño, no se envía nada.
- `NotificacionController` (nuevo): `index` (listado paginado,
  `Pages/Notificaciones/Index.vue`), `abrir` (marca como leída y redirige a
  `data.url` en un solo clic — así el dropdown del topbar no necesita un
  segundo viaje al servidor) y `marcarTodasLeidas`. Todo dentro del grupo de
  rutas `auth` general, sin permiso propio: cada quien ve solo lo suyo
  (`notifiable_id` scopeado a mano, mismo patrón sin Policies del resto del
  proyecto).
- `HandleInertiaRequests` comparte `notificaciones = {no_leidas, recientes}`
  en cada visita (closures, mismo patrón que `flash`) para que
  `Components/Layout/Topbar.vue` deje de mostrar la campanita mockeada que
  traía el template.
- CSS nuevo bajo app.css §32 (`.notif-*`) — ver
  `.claude/skills/xtrapubli-design-system/references/component-classes.md`.
- Tests: `tests/Feature/NotificacionesTest.php` (10, cubre los 6 disparadores
  + index/abrir/marcar-todas del `NotificacionController`).

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
