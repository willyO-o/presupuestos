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

## 2026-10-26 — Anexo metodológico ordenado en apartados con fórmulas aparte

**Qué cambió:**
- El anexo del "Informe del negocio" pasó de 6 párrafos seguidos a **6 apartados numerados**: 01 Método
  de cálculo, 02 Meses incompletos, 03 Rango probable, 04 Ajuste de la recta (R²), 05 Validación con
  meses reales, 06 Datos mínimos. Después, "Ficha de cada estimación" con la tabla de cada indicador.
- Cada fórmula sale del párrafo y va en un **recuadro celeste centrado** (y = a + b · x, el rango
  probable, R² = 1 - SSE / SST y la del MAPE, que antes no estaba escrita), con la explicación de los
  términos debajo. Un apartado no se parte entre páginas.
- **Bug**: un párrafo que provocaba un salto de página salía en **blanco** (invisible): heredaba el
  color de la etiqueta "USO INTERNO" del membrete. Afectaba a cualquier PDF con párrafos largos.

**Por qué:** pedido del usuario (propuesta de maqueta del anexo); el bug apareció al revisar el PDF.

**Cómo:**
- Los cálculos no cambian. `InformeProyeccionesPdf::apartadosDelAnexo()` (contenido) y
  `apartadoAnexo()` (maqueta); bloque nuevo `formula` en `Documento::recuadro()`. Fórmulas con signos
  de cp1252: "raíz(...)" y "y(x0)" porque la fuente del PDF no tiene √ ni ŷ. MAPE descrito según
  `Pronostico::mape()` (los meses con real 0 no entran).
- `Documento::AddPage()` restaura `colorActual` después del membrete (FPDF restaura su color, no nuestra copia).
- Tests: orden de los apartados y fórmulas en `InformeProyeccionesTest`, y que lo único en blanco sea la
  etiqueta (se probó que falla sin el arreglo). Suite completa en verde (822). Revisado el PDF generado.

---

## 2026-10-25 — Informe del BI entendible para quien no es analista

**Qué cambió:**
- El PDF del BI ahora se llama **"Informe del negocio"** y la portada se lee de un vistazo:
  - **"En pocas palabras"**: frases simples por tema (Ventas, Pedidos, Cotizaciones, Precios y
    costos, Materiales, Ganancia, Alertas, Estimaciones). Reemplaza las "Conclusiones" con jerga.
  - **"Lo primero que conviene hacer"**: las 3 acciones con más dinero en juego (de "¿Qué
    decisiones…?").
  - **"Qué esperar en los próximos meses"**: promedio por mes, cómo viene ("Sube 1,3 % al mes",
    "Estable"), lo que se espera, entre qué valores podría quedar y la confianza (Alta/Media/Baja).
    Reemplaza el "Resumen ejecutivo".
  - **"Cómo leer este informe"**: qué es cada línea del gráfico y qué significa la confianza.
- Indicadores con nombres claros: "Precio de los materiales" (antes Índice de costo), "Gasto en
  materiales", "Monto cotizado", "Ventas", "Ganancia por cada Bs 100 de costo" (antes Rentabilidad
  sobre costo), "Ganancia del mes" (antes Utilidad real), con descripciones sin fórmulas.
- Cada gráfico se explica con "Qué pasó / Cómo viene / Qué se espera / Confianza en la
  estimación". R², MAPE, Holt e intervalos solo aparecen en el anexo, rotulado como detalle técnico.
- Gráficos y tablas: "Lo que pasó", "Lo que se espera", "Rango probable", "Lo más probable / Como
  mínimo / Como máximo"; pedidos sin decimales ("67 pedidos"); el índice de precios se traduce a
  "15,9 % más caros que al inicio".

**Por qué:** pedido del usuario: el informe tiene que entenderlo alguien sin conocimiento técnico.

**Cómo:**
- Solo cambió la presentación: los cálculos (`Pronostico`, servicios del BI) no se tocaron.
  `RedaccionPronostico::lectura()/resumen()/conclusiones()` reescritos (solo los usa el informe;
  `ficha()`, la de la pantalla "Cómo se calcula", queda igual). `InformeProyeccionesPdf`: portada
  nueva (`accionesPrincipales()`, `comoLeer()`), tabla de resumen y anexo. El rótulo del área
  estimada de los gráficos dice "ESTIMADO" (`DibujaGraficos`). El nombre del archivo no cambia.
- Tests: `InformeProyeccionesTest` fija la redacción nueva y que la jerga estadística no aparezca
  antes del anexo (se probó que falla si vuelve). Suite completa en verde. Revisado el PDF con la base
  demo (informe completo y por pestaña).

---

## 2026-10-25 — Firmas editables, OC con imagen e imagen de referencia en la cotización

**Qué cambió:**
- **Firmas de documentos** (Administración → Firmas de documentos, `/firmas-documento`): quién firma
  por la empresa la cotización, la orden de trabajo (producción y control de calidad) y la nota de
  entrega. Por defecto firma **Guillermo Apaza Apaza, Propietario**; cuando haya responsable de
  producción se cambia ahí su nombre y cargo, sin tocar código.
- **Orden de trabajo**: antes firmaban "Jefe de producción" y "Control de calidad" sin nombre. Ahora
  firma la persona configurada; si es la misma en las dos líneas sale UNA firma con ambas funciones
  ("Propietario · Recibe la orden y aprueba antes de entregar").
- **Nota de entrega**: por la empresa firma la persona configurada; quien llevó el trabajo sigue en
  "Entregó" del bloque de datos. Al lado sigue la firma de quien recibe.
- **Orden de compra del cliente**: acepta PDF o imagen (JPG, PNG, WEBP), antes solo PDF. Ícono de
  imagen o PDF en el listado; en el pedido el enlace dice "Ver archivo".
- **Bug**: la imagen de referencia de las líneas de la cotización se guardaba pero el PDF nunca la
  dibujaba. Ahora sale en una columna "Imagen" (solo si alguna línea tiene imagen), sin deformarse.
- **Bug**: signos que la fuente del PDF no tiene salían como "?" ("Pedidos ? Ajustar medidas reales"
  en la orden de trabajo; "−" y "→" de textos del BI). `Documento::t()` los cambia por su equivalente.
- Las miniaturas (evidencia de la nota de entrega, referencia de la cotización) ya no se estiran a un
  recuadro 3:2 fijo: respetan la proporción de la foto.

**Por qué:** pedido del usuario (todo lo firma el dueño mientras no haya jefe de producción; la OC
del cliente llega como foto; la imagen de referencia no salía al imprimir).

**Cómo:**
- Tabla `firma_documento` (clave única, nombre, cargo), modelo `FirmaDocumento` con las firmas fijas
  (`FIRMAS`) y `vigentes()` (lo editado + el propietario para el resto, una consulta).
  `GeneradorPdf::empresa()` las suma a los datos de la empresa; `Documento::firmasEmpresa()` las
  dibuja (une a la misma persona; una sola firma va centrada). Reemplaza a `firmaPropietario()`.
- Permisos `firmas-documento.ver` / `.editar` en `config/acl.php` (rol administrador) y entrada en
  `Nav.js`. **En producción**: `php artisan migrate` y
  `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- `CotizacionDetalle::imagenRuta()` (por ruta, como `NotaEntregaDetalle::fotoRuta()`); celdas de
  imagen con `alto_imagen` (26 mm en la cotización, 15 en la evidencia).
- Tests: `FirmaDocumentoControllerTest` (6), OC con PDF/JPG/PNG/WEBP y rechazo de .docx, imagen de
  referencia con su proporción (se probó que falla sin la columna), firma solo en cotización, orden de
  trabajo y nota de entrega, signos sin "?". Suite completa en verde y `npm run build` sin errores.
  Verificado en el navegador: guardar firmas, volver al propietario y subir una OC con foto.

---

## 2026-10-24 — Textos de ayuda e informe en registro neutro; informe por pestaña; tabla de rentabilidad

**Qué cambió:**
- **Textos de ayuda (íconos "i")** de Dashboard, Reportes y todo el BI reescritos en un punto medio:
  claros pero sin regionalismos ni jerga ("harto", "nomás", "chamba", "caseros", "ahorita", "se
  tranca"), ni la primera persona coloquial ("nos costó", "gastamos").
- **Preguntas para decidir**: respuestas, acciones, columnas y títulos con el mismo criterio ("Costo
  adicional", "Excedidos", "Proviene de", "Monto involucrado", "¿Cuánto cuesta realmente producir…?").
- **Bug**: "Qué hacer" de la pregunta 5 recomendaba revisar un producto que no estaba en la tabla
  (buscaba entre los 10 primeros y la tabla mostraba 5). Ahora el candidato se elige por ventas (lo
  que dice la frase) y todo producto o cliente nombrado en la respuesta o en "Qué hacer" aparece en
  la tabla. Igual en la pregunta 6 (clientes).
- **Bug**: "Generar informe" daba el mismo PDF en todas las pestañas del BI (siempre con las cinco
  secciones). Ahora cada pestaña marca solo la suya; Panorama y Simulador, sin sección propia,
  ofrecen el informe general. El título del cuadro dice qué informe es.
- **Informe general (PDF)**: recuadro "Cómo leer este informe"; método y rango probable explicados
  sin jerga; la confiabilidad dice Alta/Media/Baja y el error de la prueba con meses reales; la ficha
  de cada indicador ("Meses analizados", "Confiabilidad", "Prueba con datos reales") deja los
  términos técnicos solo entre paréntesis. El anexo metodológico conserva el detalle técnico.
- Corrección menor: la lectura decía "Confiabilidad: Alta: …" (dos veces dos puntos).

**Por qué:** pedidos del usuario: textos "muy coloquiales", un producto de "Qué hacer" que no
aparecía en la tabla, el mismo informe en todas las pestañas y un informe general difícil de leer.

**Cómo:**
- `resources/js/Data/Ayudas.js` (registro documentado en su cabecera), `PreguntasNegocioService`
  (`filasConMencionados()`), `BiEncabezado.vue` (`SECCION_DE_PESTANA`), `BiPreguntas.vue`,
  `RedaccionPronostico` (ficha, confiabilidad, validación), `InformeProyeccionesPdf::comoLeer()`, CSV.
- Las claves internas no cambian (`ajuste`, `validacion`, `ticket`…); las columnas técnicas del CSV
  quedan como estaban (es para reprocesar en Excel).
- Tests: `AyudasTest` vigila las palabras coloquiales (sin contar comentarios); `PreguntasNegocioTest`
  fija que un nombrado fuera del top 5 entra a la tabla; `InformeProyeccionesTest` fija la sección
  por pestaña, la guía de lectura y la confiabilidad sin dos puntos dobles. Suite completa en verde y
  `npm run build` sin errores. Revisado el PDF generado.

---

## 2026-10-24 — NIT en documentos y sitio; firma del propietario en la cotización

**Qué cambió:**
- Se cargaron los datos del NIT: propietario y razón social **Guillermo Apaza Apaza**,
  **NIT 4755806014**, actividad Publicidad y domicilio de la casa matriz
  (C/ René Vargas Nro. 113, Z. Villa Ballivián, El Alto) con su referencia.
- **Todos los PDF** muestran en el membrete la dirección actualizada y el NIT, en la misma línea del
  teléfono y el correo (el membrete no crece).
- **El nombre del propietario va solo donde él firma**: hoy, la **cotización**, con una firma
  centrada al pie ("Guillermo Apaza Apaza · Propietario · XtraPubli"). Pedido explícito del usuario:
  no en el membrete, ni en notas de entrega, reportes ni el sitio público.
- Sitio público: el pie muestra la dirección con la referencia y el NIT; el JSON-LD suma `taxID`.
- Seeder: la casa matriz de prueba usa la dirección real (las demás sucursales siguen ficticias).
- Correcciones que salieron al revisar: (1) el bloque de totales se medía corto (≈34 mm contra
  ≈45 mm reales) y en cotizaciones largas el "Total" podía quedar solo en otra hoja; (2) el descuento
  de la cotización salía "? 100,00" porque el signo "−" no existe en cp1252: ahora "- Bs 100,00".

**Por qué:** pedido del usuario, a partir del certificado de NIT (Régimen General, emite factura).

**Cómo:**
- Claves nuevas en `config/sitio.php` → `empresa`: `propietario`, `razon_social`, `nit`, `actividad`,
  `referencia`; `direccion` pasó a la forma corta del NIT. Todo sale de ahí.
- `Documento::firmaPropietario()`: firma centrada; para sumarla a otro documento que el propietario
  deba firmar, basta llamarla en su `cuerpo()`. `Documento::totales()` mide el bloque con las mismas
  alturas con que lo dibuja.
- Tests: `GeneradorPdfTest` exige el NIT en los 8 documentos, el nombre una sola vez y solo en la
  firma de la cotización, el descuento con guion y que subtotal y total queden en la misma hoja (se
  probó que fallaba con la medida vieja); `SitioPublicoTest` verifica JSON-LD y pie sin el nombre.
  Suite completa: 803 en verde.
- Las bases ya sembradas no cambian la dirección de la sucursal (`firstOrCreate` por nombre): se
  edita desde Sucursales. Teléfono, correo y web no figuran en el NIT y quedan como estaban.

---

## 2026-10-20 — BI: "Preguntas para decidir" (pestaña, PDF y CSV)

**Qué cambió:**
- Pestaña nueva **Preguntas** en Inteligencia de negocios (`/reportes/bi/preguntas`) y sección nueva
  **"Preguntas para decidir"**, la primera del informe PDF/CSV (se elige en "Generar informe"). Once
  preguntas, cada una con su respuesta en una frase (con la cifra), una tabla corta, qué hacer y con
  qué datos se respondió:
  - **Costos:** 1) cuánto cuesta realmente producir cada producto (material real + mano de obra y
    otros presupuestados, por m²/metro/unidad); 2) qué costos suben (materiales, lo que nos costó la
    subida, índice y tarifa de mano de obra); 3) si gastamos más de lo presupuestado (en los mismos
    trabajos); 4) en qué materiales se va el dinero (Pareto 80 %).
  - **Ganancias:** 5) productos por ganancia en Bs y por margen; 6) clientes por ganancia (no por
    compras); 7) lo que cuestan los descuentos y los precios a mano; 8) ventas en amarillo/rojo.
  - **Escenarios:** 9) si la materia prima sube 5/10/20 % (y el probable por la tendencia): margen,
    ganancia perdida y cuánto subir precios; 10) cuánto de lo vendido ya se cobró, por antigüedad.
  - **Decisiones:** 11) las acciones de las demás, ordenadas por el dinero en juego, con enlace a la
    pregunta de la que salen.
- En escritorio, índice fijo con las once preguntas; en el celular, un atajo a las decisiones.

**Por qué:** pedido del usuario (las 6 preguntas del docente más 5 propuestas). Se descartaron por falta
de datos: ganancia neta/punto de equilibrio (no hay gastos fijos), mano de obra real (no hay horas
trabajadas), motivos de rechazo (no se guardan) y comparación de proveedores (ningún material se compra
a más de uno).

**Cómo:**
- `PreguntasNegocioService`: agrega en SQL; reutiliza `CostosMaterialesService` (impacto, índice,
  consumo). El ingreso neto por línea pasó a `ConsultaSql::INGRESO_NETO_LINEA`, compartido con el
  Panorama. Parámetros en `config/bi.php` → `preguntas` (tolerancia 5 %, escenarios, mínimo de ventas,
  Pareto, tramos de cobranza, ahorro negociable).
- Decisiones de criterio: "real" y "presupuestado" se comparan solo en ítems **entregados** con
  consumo y hoja de costos (un pedido a medias parecería ahorro); el escenario de materia prima no entra
  a "Decisiones" (es hipótesis); las frases son plantillas, ninguna cifra sale de la IA.
- PDF (`InformeProyeccionesPdf::preguntas()`), CSV (bloque 4), pantalla `Reportes/BiPreguntas.vue` con
  `ChartCard`, 11 ayudas "i", CSS §38. `textoDelInforme()` se movió a `tests/Pest.php` (lo usan dos
  tests). `AyudasTest` acepta `:ayuda` en `ChartCard`.
- Tests: `PreguntasNegocioTest` (16, con dos ventas armadas para sacar las cuentas a mano). Verificado
  con la base demo: pestaña (escritorio, celular, oscuro), anclas, PDF (8 páginas) y CSV.

---

## 2026-10-20 — Adiós "ticket": precio promedio y venta promedio (y la tendencia corregida)

**Qué cambió:**
- "Ticket" se reemplaza según lo que mide:
  - **"Precio promedio"** (por presupuesto): gráfico "Tendencia: precio vs. costo" de Presupuestos y
    rentabilidad (título, leyenda, subtítulo, lectura y ayuda "i") y conclusiones del informe de
    proyecciones (PDF/CSV).
  - **"Monto promedio por presupuesto"** en el Resumen semanal (va con "Monto presupuestado", que también
    es con IVA), con sus recomendaciones ("mayor monto", "El monto promedio por presupuesto bajó.").
  - **"Venta promedio"** para lo que deja cada venta aprobada: indicador del Panorama y el asistente IA
    ("Venta promedio (sin IVA)").
- **Corrección del gráfico de tendencia**: el precio era el total CON IVA e instalación de TODOS los
  presupuestos y el costo solo el de los que tienen hoja de costos, sin IVA. Ahora las dos líneas salen
  de los mismos presupuestos (con hoja de costos) y el precio es subtotal − descuento, el que mira el
  motor de margen. En la base demo la brecha precio/costo bajó de 74 % a 51 %: la vieja exageraba lo
  que se gana.
- La frase de lectura decía "el costo crece más rápido que el precio" aun cuando los dos bajaban
  (−0,3 % contra −0,5 %). Ahora: "el precio va por detrás del costo" / "acompaña al costo o le gana".

**Por qué:** pedido del usuario ("ticket" no se entendía) y, al revisarlo, la tendencia comparaba dos
bases distintas.

**Cómo:**
- `PresupuestosRentabilidadService::tendencia()`; textos en `BiRentabilidad.vue`, `Ayudas.js`,
  `PanoramaService`, `RedaccionPronostico`, `ResumenSemanalService`, `ConsultaVentasService`. Las claves
  internas (`ticket`, `ticket_promedio`, `variacion_ticket`) no cambian.
- Migración de datos `rename_ticket_labels_in_bi_resumen_table`: los resúmenes ya generados guardan sus
  textos; reemplaza solo las frases exactas de la plantilla (reversible). **Se corrió en
  `presupuesto_demo` y `presupuesto`**; no quedó "ticket" en ningún resumen.
- `TerminologiaPantallasTest` ahora también vigila "ticket" (en JS solo como palabra suelta, no las
  claves; en PHP también `app/Services` y `app/Ai`). Se probó contra los textos viejos. Tests nuevos:
  tendencia (InteligenciaNegociosAvanzadoTest), conclusión con pendientes negativas
  (InformeProyeccionesTest) y la migración (ResumenSemanalTest). Verificado en la app y en el PDF.

---

## 2026-10-20 — "Receta" pasa a llamarse "Materiales del producto"

**Qué cambió:**
- En pantalla ya no se dice "receta" ni "BOM": la lista de materiales que lleva un producto es
  **"Materiales del producto"**. Cambian el botón del listado de Productos (ícono de cajas en vez del
  matraz), el título y las migas ("Materiales — {producto}"), la tarjeta, el estado vacío, los
  modales ("Agregar material" / "Editar material"), la confirmación al quitar, los avisos, la
  ayuda de "Publicar en la web" del producto, el tooltip y el texto de la hoja de costos vacía de la
  cotización (y su ícono), y la etiqueta del permiso: "Editar productos (incluye sus materiales)".
- En el pedido, "Presupuestado (BOM de la cotización)" pasa a **"Presupuestado (según los materiales
  del producto)"**, que es lo que realmente calcula `PedidoController::comparativaCostos()`
  (materiales del producto a las medidas del pedido), no la hoja de costos de la cotización.

**Por qué:** pedido del usuario. Se descartó "materiales utilizados" porque eso ya es otra cosa:
el consumo real que se registra en Pedidos (`materialesUsados`).

**Cómo:**
- Solo texto visible. Los nombres internos quedan igual: rutas `productos.materiales.*`, componente
  `Productos/Receta`, modelo `ProductoMaterial`, comentarios y seeders. El permiso no cambia de
  nombre (solo su etiqueta), así que no hace falta reseedear.
- `TerminologiaPantallasTest` (3): falla si "receta" o "BOM" vuelve a aparecer en un .vue/.js (sin
  contar comentarios) o en una cadena de `app/Http` o `config/acl.php`. Se probó contra los archivos
  viejos: marcaba exactamente los 5 archivos y las 6 cadenas cambiadas. `ProductoMaterialControllerTest`
  ahora verifica título, migas y los tres avisos.
- Verificado en la app con la base demo: botón → pantalla, estado vacío, agregar → editar → quitar
  (los datos quedaron como estaban), formulario de producto, "Traer insumos del producto" en una
  cotización (cargó 2 insumos), etiqueta del pedido y del permiso en Roles. Sin errores de consola.
- Pendiente: `.ai/rules/controllers.md` todavía dice que el botón usa el ícono `fa-flask`.

---

## 2026-10-20 — BI: pestaña "Panorama" (KPIs comparados, embudo, margen por tipo, drill-down)

**Qué cambió:**
- Pestaña nueva **Panorama** (`/reportes/bi/panorama`), primera del BI y destino del menú
  "Inteligencia de Negocios". Filtros globales que mueven toda la pantalla: periodo, tipo de
  producto (familia), categoría, cliente y sucursal, con chips para quitar cada uno.
- **5 KPIs** contra el periodo anterior (▲ verde / ▼ rojo según convenga): ventas, cotizaciones
  emitidas, tasa de conversión, ticket promedio y margen bruto. Conversión y margen varían en pp.
- Cuadros, cada uno con su **hallazgo** (la conclusión en una frase, armada en el servidor) y su "i":
  ventas por mes (este periodo vs. anterior + promedio, mejor mes destacado; clic en un mes = filtra
  ese mes) · embudo emitidas → aprobadas → con pedido → cobradas (marca el paso que más pierde) ·
  ingresos + margen por mes (combinado) · ingresos y margen por tipo con **drill-down** familia →
  categoría → producto (clic en la barra) · top 5 clientes (clic = filtra el cliente) · top 5
  productos · ventas por vendedor · ventas por ciudad.

**Por qué:** pedido del docente: gráficos más llamativos y que digan algo. Decisiones tomadas por
defecto: línea de **promedio** (no hay metas mensuales en la base), **margen bruto presupuestado**
= (ingreso neto − `costo_ajustado` de la línea) / ingreso neto, antes de impuestos, solo con líneas
que tienen hoja de costos (las anteriores al motor de márgenes tienen costo 0 y se excluyen), y la
paleta actual (gris para el contexto, color solo para el dato clave).

**Cómo:**
- `PanoramaService` **agrega en SQL** (SUM/COUNT/GROUP BY), no en PHP: con 24 meses de líneas traer
  todo a Collections ya no escala. El mes sale de `ConsultaSql::mes()` (strftime / to_char /
  DATE_FORMAT según el motor, columna validada con regex). Esto es una **excepción explícita** a la
  regla de `.ai/rules/reporte.md` ("toda agregación en PHP"); los services viejos siguen igual.
- **Trampa**: en SQLite los decimales enteros se guardan como enteros y `100 / 1000` da 0. Toda
  división en SQL lleva `* 1.0` (el prorrateo del descuento por línea la tenía).
- Periodo anterior = mismo rango corrido tantos meses como dura (12 → el año anterior; mes con mes).
  Si el periodo cierra a fin de mes, el anterior también (30/11 → 31/10).
- `PanoramaRequest` (valida filtros y arma el periodo), `ReporteController::biPanorama`, ruta bajo
  `can:reportes.bi`. Recarga parcial de Inertia (`only: datos, filters`) con la tarjeta atenuada
  mientras carga.
- Componentes nuevos `Reporte/KpiCard.vue` y `Reporte/ChartCard.vue`. `BaseChart` gana opciones
  opcionales (no cambian los gráficos existentes): serie `neutra`, `destacar`, `etiquetas`, evento
  `seleccionar` (cursor de mano solo si la página lo escucha), `formatoTooltip`, animación 650 ms
  (apagada con reduced-motion). `moneyCompacto` en `FormatoReporte.js`. Menú: ítem con `match`
  para que quede activo en todas las pestañas del BI. CSS §37. 13 ayudas nuevas en `Ayudas.js`.
- Tests: `PanoramaTest` (15) y `AyudasTest` ampliado a KpiCard/ChartCard. Suite completa verde.
  Verificado con la base demo en escritorio, tablet, celular y oscuro (drill-down y clic en mes).
- **Falta en la base**: metas mensuales de ventas (por eso la línea es el promedio).

---

## 2026-10-20 — Clientes que más compran (con logo) y Pareto de clientes en el BI

**Qué cambió:**
- **Logo opcional del cliente**: se sube desde Clientes (crear/editar) y se ve en el listado al lado
  del nombre. Sin logo, las iniciales con un color fijo por cliente.
- BI → Demanda y ventas, cuadro nuevo **"Clientes que más compran"**: los 10 primeros por monto
  aprobado en el periodo, con logo o iniciales, monto, % del total, cantidad de compras, variación
  contra un periodo anterior de la misma duración ("Nuevo" si antes no compró) y hace cuánto fue la
  última compra (en amarillo si pasan 60 días). Arriba, cuánto suman los 10; abajo, "Otros".
- Gráfico nuevo **"Concentración de ventas por cliente"** (Pareto): todos los clientes de mayor a
  menor con el % acumulado y la línea del 80 %, más "N de M clientes hacen el 80 % de las ventas".
  Desde el puesto 31 la cola va en una sola barra.
- Los dos con su ícono "i".

**Por qué:** pedido del usuario. Se descartó meter clientes en la proyección de demanda (mezcla
"cuántos pedidos" con "quiénes") y el pastel (con 40 clientes o más las porciones no se distinguen).
En la base demo los 10 primeros concentran el 78 % y uno de ellos lleva 147 días sin comprar.

**Cómo:**
- Migración `add_logo_to_cliente_table` (`cliente.logo` nullable, también en `schema.json`). El logo
  pasa por `ConvierteImagenAJpgService` como toda imagen subida (JPG, sin SVG); se borra el anterior
  al reemplazarlo y al borrar el cliente. Accesor `logo_url`. El alta rápida (JSON) no lleva logo.
- `InteligenciaNegociosService::clientesQueMasCompran()` → `datos.clientes_top` (con `pareto`).
  Monto = total de cotizaciones APROBADA/CONVERTIDA, acotado por sucursal y por el filtro de fechas;
  la última compra sale de toda la historia. El corte del 80 % se cuenta sobre la lista completa.
- Front: `Components/Cliente/ClienteAvatar.vue` (logo sobre plaquita blanca —el JPG pierde la
  transparencia— o iniciales); ranking en HTML (los logos en el canvas de ECharts serían frágiles);
  `BaseChart` gana `etiquetas-inclinadas` para muchos nombres en el eje X. CSS §36.
- Tests: `ClientesTopTest` (9) y 6 de logo en `ClienteControllerTest`. Verificado con la base demo
  (escritorio, oscuro y celular) con un logo temporal que se revirtió.
- **Ojo**: se migró `presupuesto` y `presupuesto_demo` (columna nueva, sin tocar datos).

---

## 2026-10-20 — Filtro de fechas: "Últimos 3 meses" y salto por mes y año

**Qué cambió:**
- Nuevo atajo **"Últimos 3 meses"** (entre "Este mes" y "Últimos 6 meses") en el filtro de fechas,
  así que aparece en todas las pantallas que lo usan: Reporte financiero y las pestañas del BI.
- En **Rango personalizado**, una franja arriba de los calendarios: **Desde [mes][año] → Hasta
  [mes][año]**. Elegir mes y año fija el rango por meses completos (del 1 del mes de inicio al
  último día del mes final; si es el mes en curso, hasta hoy) y lleva el calendario a ese mes. Los
  días se siguen afinando con clic. En el celular los dos campos van uno debajo del otro.

**Por qué:** pedido del usuario: llegar a un año anterior obligaba a retroceder de a un mes con las
flechas (el calendario además abre en el mes final del rango).

**Cómo:**
- Todo en `Components/DateRangeFilter.vue`, sin tocar el paquete `daterange-picker-vue3`: la franja
  va en su ranura `header`, se muestra solo con los calendarios abiertos (`showCalendars`) y mueve
  la vista con sus propios `changeLeftMonth`/`changeRightMonth`, por la referencia al componente.
  El rango nunca queda al revés: si "Desde" pasa a "Hasta" (o al revés), el rango es ese mes
  completo. Respeta `minDate`/`maxDate`; sin topes, ofrece los últimos 10 años.
- Selects nativos (en el celular abren la rueda del sistema), con `aria-label` propio cada uno.
  CSS en app.css §29 (`.date-range-meses*`), claro y oscuro con los tokens.
- Test `FiltroFechasTest` (sobre el código fuente). Verificado en el navegador: atajo, salto a
  Mar-2024 y Jun-2025, rango invertido, mes en curso, "Aplicar" con `?desde=&hasta=`, BI y modo
  oscuro, escritorio y celular, sin errores de consola.

---

## 2026-10-20 — Estacionalidad por gestión, con promedio y sobre toda la historia

**Qué cambió:**
- El gráfico de Estacionalidad (BI → Demanda y ventas) pasa de barras con la suma de los meses a
  **una línea por gestión** sobre el eje Ene–Dic más una línea **Promedio** (con relleno). La
  leyenda prende y apaga cada año, así que funciona como selector sin perder la comparación.
- Usa **toda la historia** y no el filtro de fechas (rotulado en la tarjeta). Los meses sin datos
  posibles —antes del primer pedido o que todavía no llegaron— quedan vacíos, no en cero. El
  subtítulo avisa qué mes está en curso.

**Por qué:** pedido del usuario (un selector por gestión con opción "Todo"); se propuso esta
variante y la aceptó. Dos problemas de antes: con los 12 meses por defecto cada mes aparecía una vez
y no se comparaba nada, y una "suma de todos los años" infla los meses que tienen un año más de
historia.

**Cómo:**
- `InteligenciaNegociosService::estacionalidad()` sale de `demanda` a una clave propia
  (`datos.estacionalidad`): `meses`, `gestiones` (año, 12 valores con `null`, total), `promedio`,
  `desde`, `mes_en_curso` y `gestiones_ocultas`. Sigue acotada por sucursal.
- El promedio es por gestión disponible (no suma) y no cuenta el mes en curso. Se grafican el
  promedio y las 5 gestiones más recientes (la paleta tiene 6 colores y no se cicla); con una sola
  gestión el promedio se omite (sería la misma línea) y el subtítulo lo explica.
- `PeriodoReporte::nombreMes()` da el nombre corto del mes. Ayuda "i" actualizada.
- Tests: `EstacionalidadTest` (5) y los dos tests que leían la forma vieja. Verificado contra la
  base demo de 24 meses con un servidor temporal: julio y agosto salen como temporada alta en ambas
  gestiones.

---

## 2026-10-20 — Ayuda "i" también en indicadores, tablas y listas de Reportes y BI

**Qué cambió:**
- Además de los gráficos, ahora llevan el ícono **"i"** los 31 cuadros de información que no son
  gráficos en Reportes (financiero y producción) y en las seis pestañas del BI: las tarjetas de
  indicador, las tablas (rentabilidad real, detalle del impacto), las listas (anomalías, alertas
  de costo y margen), la lectura del material, el semáforo, los tres paneles del simulador y los
  cuadros del resumen semanal.
- En los indicadores del BI el ícono va en la esquina superior derecha de la tarjeta; en las
  tarjetas de Reportes, al lado de la etiqueta.

**Por qué:** pedido del usuario: que también se entiendan los cuadros que no son gráficos.

**Cómo:**
- El componente se generalizó y renombró: `Components/AyudaInfo.vue` (antes
  `Chart/AyudaGrafico.vue`) y los textos en `Data/Ayudas.js` (antes `AyudaGraficos.js`); clases CSS
  `.ayuda-*`. `BiKpi` ganó la prop `ayuda` (clave del texto).
- Los textos siguen el mismo criterio: castellano boliviano de todos los días, "dinero" o
  "ingresos" y nunca "plata", y cada uno revisado contra lo que el cuadro calcula de verdad (por
  ejemplo, la tasa de aprobación no cuenta los pendientes; las cuentas por cobrar no dependen del
  filtro de fechas).
- Única tarjeta sin ícono, a propósito: "Proyección de rentabilidad", que agrupa dos gráficos que ya
  traen el suyo.
- `AyudasTest` (reemplaza a `GraficosConAyudaTest`): además de los gráficos, exige ícono en cada
  tarjeta con título y cada indicador de `Pages/Reportes`. Verificado en el navegador (escritorio y
  celular, sin errores de consola).

---

## 2026-10-20 — Encabezados alineados, meses en mes-año y botones de PDF en rojo

**Qué cambió:**
- **Tablas:** cada encabezado se alinea como sus valores (montos a la derecha, estados y fechas al
  centro) en todas las tablas del panel, incluida la vista "Tabla" de los gráficos.
- **Meses:** las etiquetas de periodo mensual de gráficos, tablas, PDFs y respuestas del asistente
  salen como **mes-año: `Ene-2026`** (antes `2026-01` o `ene. 2026`). Las fechas de un día puntual
  (la de un pedido, una entrega) no cambian. Quedan solo con el mes, a propósito, la estacionalidad
  (junta todos los años) y el gráfico de ventas del Dashboard (cada serie ya es un año).
- **PDF e impresión:** los 18 botones de ver, descargar o imprimir PDF del panel, el "Ver PDF" del
  informe del BI y la descarga del cotizador público van en un **rojo propio** (`--c-pdf`), más
  oscuro que el rojo de "peligro" para no confundirse con "Cancelar pedido".

**Por qué:** pedidos del usuario: leer mejor tablas y periodos, y encontrar los PDF de un vistazo.

**Cómo:**
- La causa del desalineado era una sola regla: `.table-dashboard thead th` forzaba `text-start` con
  más especificidad que la utilidad `text-end` del `<th>`. Ahora el valor por defecto va en
  `:where()` (especificidad cero). Las tablas ya declaraban la misma alineación en encabezado y
  celda; se verificó midiendo la alineación calculada en 14 pantallas.
- `PeriodoReporte::etiquetaMes()` es el único formato de mes del servidor (nombres fijos, sin el
  punto ni el "sept." de Carbon) y `mesAnio()` en `Utils/FormatoReporte.js` es su espejo. El
  asistente ordena por la clave `Y-m` y rotula después, para que abril no quede antes que enero.
- Clases `.btn-pdf` (suave), `.btn-pdf-solid` y `.text-pdf` en app.css §8; color `marca.pdf` en
  `tailwind.publico.config.js`.
- Tests: `EtiquetaMesTest` y `BotonesPdfTest` (todo `<a>`/`<button>` que abre un PDF o imprime
  lleva la clase roja). Verificado en el navegador (claro y oscuro).

---

## 2026-10-20 — Ayuda "i" en todos los gráficos del panel

**Qué cambió:**
- Cada gráfico del Dashboard, de los reportes Financiero y de Producción y de las cuatro pestañas
  del BI con gráficos (25 en total) tiene un ícono **"i"** al lado del título. Al tocarlo se abre
  un popup con qué muestra el gráfico, "¿Cómo se lee?" (2 a 4 pistas) y un "Ojo" con la advertencia
  o el consejo práctico de ese gráfico.
- Los textos están en castellano boliviano de todos los días ("harto", "nomás", "caseros",
  "chamba", "trancadera"), sin términos de estadística. Para los montos se dice **"dinero" o
  "ingresos", nunca "plata"** (pedido del usuario).

**Por qué:** pedido del usuario: que cualquiera entienda a qué se refiere cada gráfico.

**Cómo:**
- `Components/Chart/AyudaGrafico.vue` (prop `clave`) y todos los textos en `Data/AyudaGraficos.js`:
  un solo lugar para corregirlos. El popup va con Teleport al `<body>` en `position: fixed` (las
  tarjetas recortan lo que sobresale), se acomoda abajo o arriba según el espacio, con flechita al
  ícono. Se abre con clic (en el celular no hay hover), se cierra con Escape, la X o tocando afuera;
  abrir uno cierra el otro. Foco al popup al abrir y de vuelta al ícono al cerrar con teclado.
- CSS §35 en `app.css` (`.ayuda-grafico-*`, `.chart-heading`), claro y oscuro con los tokens.
  Los dos gráficos que comparten la tarjeta "Proyección de rentabilidad" ganaron título propio.
- Quedan fuera los gráficos del asistente de IA: los arma la IA en cada respuesta y ya traen su
  "Cómo lo calculé".
- Test `GraficosConAyudaTest`: cada pantalla con `<BaseChart>` tiene sus íconos, toda clave existe,
  ninguna se repite ni sobra, cada texto trae qué muestra y cómo leerlo, y ninguno dice "plata".
  Verificado con Playwright en escritorio (claro y oscuro) y en celular, sin errores de consola.

---

## 2026-10-20 — PDF del reporte financiero y del reporte de producción

**Qué cambió:**
- Botones **"Ver PDF"** (vista previa en pestaña nueva) y **descargar** en Reportes → Financiero (usa
  las fechas del filtro) y Reportes → Producción.
- **Financiero:** cuatro indicadores (total vendido, margen real, pedidos con pérdida, por cobrar),
  "Puntos clave" en texto, ventas por mes (gráfico + tabla), ventas por sucursal con participación,
  margen por pedido (barras rojo/verde, los 10 peores) con el detalle por cliente, y el **detalle de
  cuentas por cobrar** con antigüedad (en pantalla solo se ve el total).
- **Producción:** indicadores, puntos clave, entregas a tiempo/tarde con la lista de **pedidos
  atrasados**, tiempo promedio por etapa, ítems abiertos por área y pedidos en curso por etapa.
- **Permisos nuevos:** `reportes.exportar_financiero` y `reportes.exportar_produccion` (administrador
  y gerente: los dos; contador: financiero; jefe de producción: producción).

**Por qué:** pedido del usuario. Exportar es un permiso aparte de ver: el papel sale de la empresa
con márgenes, saldos y nombres de clientes.

**Cómo:**
- `InformeReportesService` arma los dos informes llamando a los MISMOS servicios de pantalla (mismo
  alcance por sucursal) y agrega meses en cero, participaciones y puntos clave con plantillas (no IA).
  `ReporteFinancieroService` suma `cliente` a rentabilidad y `cliente`/`fecha_pedido`/`dias` a cuentas
  por cobrar; `ReporteProduccionService` suma `atrasos`. Las pantallas no cambian.
- Los pedidos terminados **sin consumo de material registrado** (costo 0, "margen" 100 %) se listan
  pero quedan fuera del margen y se avisa, igual que el BI con las cotizaciones sin hoja de costos.
- PDF por `GeneradorPdf::reporteFinanciero()/reporteProduccion()` (FPDF, carta, membrete común).
  `Documento` gana `tituloSeccion`, `parrafo` (movidos desde el informe del BI), `subtitulo`,
  `indicadores`, `recortar` y color por celda en `tabla()`; `DibujaGraficos` gana `graficoBarras()`
  (barras horizontales con valor, admite negativos). `AlcanceReporte::rotulo()` reemplaza la copia
  privada de `InformeProyeccionesService`.
- Rutas `reportes.{financiero,produccion}.pdf` con `can:` de ver Y de exportar; `ReportePdfRequest`
  valida las fechas (la pantalla las pasa a `Carbon::parse` sin validar).
- **Hay que correr `php artisan db:seed --class=RolesAndPermissionsSeeder`** para crear los permisos
  en una base existente (el super-admin no lo necesita).
- Tests: `ReportesPdfTest` (14) y los dos reportes sumados a "todos los documentos comparten formato".
  Revisados los PDF renderizados contra la base local y los botones en el panel.

---

## 2026-10-20 — Seguimiento público de pedidos (`/seguimiento`)

**Qué cambió:**
- Sección nueva del sitio público, **"Mi pedido"** en el menú y en el pie: el cliente escribe su
  número de pedido y los **últimos 4 dígitos de su teléfono o NIT** y ve el estado del pedido sin
  iniciar sesión.
- La página del pedido muestra la etapa actual con una barra de etapas, las fechas (pedido, entrega
  estimada con días restantes o aviso si la fecha ya pasó, entrega real), cada trabajo con medidas,
  cantidad y su propia etapa, las notas de entrega y los pagos (total, pagado, saldo).
- **Sidebar derecho con la línea de tiempo**: Pedido confirmado → Diseño → Elaboración → Acabado →
  Control de calidad → Entregado, con pasos completos (fecha), el actual (pulso, "desde", "2 de 3
  trabajos ya pasaron esta etapa") y pendientes. Un pedido cancelado muestra dónde se detuvo. En el
  teléfono la línea de tiempo va primero.
- `/seguimiento?pedido=PED-...` llega con el número ya escrito: es el enlace que el vendedor le
  puede pasar al cliente.

**Por qué:** pedido del usuario. El número solo no alcanza para abrir el pedido (decisión del
usuario): viaja en capturas y WhatsApp reenviados, y el detalle lleva montos y el nombre del cliente.

**Cómo:**
- `SeguimientoPedidoController` (index/buscar/show/cerrar) + `BuscarPedidoRequest` +
  `Services/Pedido/SeguimientoPublicoService` (verifica con `hash_equals` sobre los dígitos, y arma
  la vista: no recalcula nada, traduce `pedido.estado` y las fechas de `pedido_seguimiento`). Vistas
  Blade `publico/seguimiento{,-pedido}.blade.php`, sin JavaScript.
- Al verificar, el id del pedido queda en la **sesión**; `/seguimiento/{numero}` sin esa sesión
  vuelve al formulario. Mismo mensaje para número inexistente y dígitos equivocados. Rate limiter
  `seguimiento-buscar` con límite **por número de pedido** (20/h) además del de IP (10/min):
  4 dígitos son 10.000 combinaciones. Config en `config/seguimiento.php`.
- No sale nada interno: ni observaciones del taller, áreas, empleados, teléfono ni NIT. La página
  del pedido es `noindex`, `Cache-Control: no-store` y `Disallow: /seguimiento/` en robots;
  `/seguimiento` entra al sitemap. Botón "Cerrar consulta en este dispositivo".
- Iconos nuevos en `x-publico.icono` (pincel, brillo, camion, buscar, candado, billete, cancelado);
  CSS `.seguimiento-pulso`/`.seguimiento-barra-actual` en `publico.css`. El menú del header no
  parte palabras a 1024 px (ahora tiene 7 enlaces).
- Tests: `SeguimientoPedidoTest` (21). Se corrigió un test del cotizador que leía el sitemap con
  `iterator_to_array` conservando claves (solo veía la última URL). Suite completa en verde (687).
  Verificado con Playwright en escritorio y móvil (en curso, entregado, error), sin errores de consola.

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
