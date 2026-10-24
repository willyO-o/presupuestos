/**
 * Textos de ayuda de los cuadros de información del panel —gráficos,
 * tablas, indicadores y listas—: lo que muestra el popup del ícono "i"
 * (`Components/AyudaInfo.vue`) al lado del título.
 *
 * Registro (pedido del usuario, 2026-10-24): un punto medio entre lo técnico
 * y lo coloquial. Frases claras para quien usa el sistema todos los días,
 * sin términos de estadística innecesarios, pero sin regionalismos ni jerga
 * ("harto", "nomás", "chamba", "caseros", "ahorita"): el texto tiene que
 * leerse bien en una reunión o en un informe. Para los montos se dice
 * "dinero" o "ingresos", nunca "plata". AyudasTest vigila esas palabras.
 * Si un cuadro cambia lo que calcula, se corrige su texto AQUÍ: una ayuda
 * que dice otra cosa que el cuadro es peor que no tener ayuda.
 *
 * Forma de cada entrada:
 * - `titulo`: el nombre del cuadro, como en la tarjeta.
 * - `que`:    qué muestra, en una o dos frases.
 * - `leer`:   cómo leerlo, de dos a cuatro pistas cortas.
 * - `ojo`:    (opcional) una advertencia o una recomendación práctica.
 *
 * La clave es `pantalla.cuadro`. Un test (AyudasTest) revisa que cada
 * gráfico, cada tarjeta con título y cada indicador de Reportes y del BI
 * tengan su ayuda, y que toda clave usada exista acá.
 */
export const AYUDAS = {
    /* ------------------------------------------------------------------
     * Dashboard
     * ---------------------------------------------------------------- */
    'dashboard.ventas-por-mes': {
        titulo: 'Ventas por mes',
        que: 'El monto total de las cotizaciones aprobadas en cada mes de este año, comparado con lo vendido en el mismo mes del año anterior.',
        leer: [
            'Si la línea de este año está por encima de la del año anterior, las ventas mejoraron.',
            'Los meses que aún no llegaron aparecen vacíos, no en cero.',
            'Pase el cursor sobre un punto para ver el monto exacto del mes.',
        ],
        ojo: 'Muestra lo aprobado, no lo cobrado: el dinero que ya ingresó a caja se consulta en Pagos.',
    },
    'dashboard.pedidos-por-etapa': {
        titulo: 'Pedidos por etapa',
        que: 'Cuántos pedidos hay en producción en este momento y en qué etapa se encuentra cada uno: diseño, elaboración, acabado o control de calidad.',
        leer: [
            'La barra más larga es la etapa donde se acumulan más pedidos.',
            'El porcentaje indica qué parte de la producción en curso está en esa etapa.',
            'Debajo se muestra cuántos pedidos se entregaron en total.',
        ],
        ojo: 'Si una etapa acumula demasiados pedidos, es un cuello de botella: conviene reforzar esa área antes de que se retrasen las entregas.',
    },
    'dashboard.productos-mas-vendidos': {
        titulo: 'Productos más vendidos',
        que: 'Los productos que más ingresos generaron en los últimos 12 meses, en cotizaciones aprobadas.',
        leer: [
            'El número de la izquierda es la posición en el ranking: el 1 es el que más vende.',
            '"uds." son las unidades vendidas y el porcentaje es la participación de ese producto en el total vendido.',
            'La barra compara cada producto con el primero del ranking.',
        ],
        ojo: 'El orden es por ingresos, no por cantidad: un producto puede venderse en muchas unidades y aparecer más abajo si su precio es bajo.',
    },

    /* ------------------------------------------------------------------
     * Reporte financiero
     * ---------------------------------------------------------------- */
    'financiero.ventas-por-mes': {
        titulo: 'Ventas por mes',
        que: 'El monto total de las cotizaciones aprobadas (incluidas las que ya pasaron a pedido) en cada mes del periodo seleccionado.',
        leer: [
            'Los picos son los meses de mayor venta; los descensos, los de menor actividad.',
            'Al cambiar las fechas del filtro y presionar "Aplicar", el gráfico se actualiza.',
        ],
        ojo: 'Muestra lo vendido, no lo cobrado. Los saldos pendientes de los clientes están en "Cuentas por cobrar".',
    },
    'financiero.ventas-por-sucursal': {
        titulo: 'Ventas por sucursal',
        que: 'El monto vendido por cada sucursal en el periodo seleccionado.',
        leer: [
            'La barra más larga corresponde a la sucursal con más ventas.',
            'Pase el cursor sobre una barra para ver el monto exacto.',
        ],
        ojo: 'Si solo aparece su sucursal, es porque su cuenta tiene acceso limitado a ella: lo indica la etiqueta de la esquina superior derecha.',
    },
    'financiero.margen-por-pedido': {
        titulo: 'Margen real por pedido',
        que: 'Para cada pedido terminado: lo cobrado menos el costo real del material utilizado. Es la ganancia efectiva de cada pedido.',
        leer: [
            'Verde, hacia la derecha: el pedido generó ganancia.',
            'Rojo, hacia la izquierda: el pedido costó más de lo que se cobró y generó pérdida.',
            'Están ordenados del peor al mejor, para que lo primero que se vea sea lo que requiere revisión.',
        ],
        ojo: 'Si producción no registró el material utilizado en un pedido, su margen aparece más alto de lo real. Es importante que se registre para que el cálculo sea correcto.',
    },
    'financiero.total-vendido': {
        titulo: 'Total vendido en el periodo',
        que: 'La suma de todas las cotizaciones aprobadas (incluidas las que ya pasaron a pedido) con fecha dentro del periodo seleccionado.',
        leer: [
            'Es el total de cada cotización tal como se presentó al cliente.',
            'Se actualiza al cambiar las fechas del filtro y presionar "Aplicar".',
        ],
        ojo: 'Vendido no significa cobrado: un cliente puede haber aprobado y aún no haber realizado ningún pago.',
    },
    'financiero.por-cobrar': {
        titulo: 'Cuentas por cobrar',
        que: 'El dinero que los clientes aún adeudan: el saldo pendiente de los pedidos que no fueron cancelados.',
        leer: [
            'Es la suma de los saldos: total del pedido menos lo ya pagado.',
            'No depende de las fechas del filtro: es la deuda pendiente a la fecha, de cualquier pedido.',
        ],
        ojo: 'El detalle por pedido, con los días de antigüedad de cada deuda, se encuentra en el PDF del reporte.',
    },
    'financiero.rentabilidad-tabla': {
        titulo: 'Rentabilidad real por pedido',
        que: 'Los mismos pedidos del gráfico de margen, con las cifras completas: lo cobrado, el costo del material y el resultado.',
        leer: [
            'Ingreso: el valor del pedido. Costo real: el material que producción registró como utilizado.',
            'Margen: la diferencia. En verde si hubo ganancia, en rojo si hubo pérdida; entre paréntesis, qué parte del ingreso es ganancia.',
            'Se incluyen los pedidos en acabado o ya entregados, sin importar la fecha del filtro.',
        ],
        ojo: 'Un costo real de Bs 0 casi siempre significa que no se registró el consumo, no que el material no tuvo costo.',
    },

    /* ------------------------------------------------------------------
     * Reporte de producción
     * ---------------------------------------------------------------- */
    'produccion.duracion-por-etapa': {
        titulo: 'Duración promedio por etapa',
        que: 'Cuántos días, en promedio, permanece un trabajo en cada etapa del taller antes de pasar a la siguiente.',
        leer: [
            'La barra más larga es la etapa más lenta: ahí se concentran las demoras.',
            'Las etapas que no aparecen todavía no tienen registros suficientes para medirse.',
        ],
        ojo: 'Solo se cuentan las etapas que el taller abrió y cerró en el sistema. Sin registro, no es posible medir.',
    },
    'produccion.carga-por-area': {
        titulo: 'Carga de trabajo por área',
        que: 'Cuántos trabajos pendientes tiene cada área en este momento.',
        leer: [
            'El área con la barra más larga es la que tiene más trabajo asignado.',
            'Refleja la situación actual: cambia cuando se cierra o se asigna una etapa.',
        ],
        ojo: 'Sirve para distribuir mejor el trabajo: si un área está saturada y otra tiene capacidad libre, se puede reasignar personal o reordenar prioridades.',
    },
    'produccion.cumplimiento': {
        titulo: 'Cumplimiento de entregas',
        que: 'De los pedidos ya entregados, cuántos llegaron en la fecha comprometida con el cliente o antes.',
        leer: [
            'El porcentaje principal es la proporción entregada a tiempo; debajo se indica cuántos son del total.',
            'Solo cuentan los pedidos que tenían fecha estimada y fecha real de entrega.',
            'Considera todo el historial, no un mes en particular.',
        ],
        ojo: 'Cada entrega tardía puede costar un cliente: el PDF del reporte incluye la lista de pedidos atrasados.',
    },
    'produccion.pedidos-activos': {
        titulo: 'Pedidos activos por etapa',
        que: 'Cuántos pedidos hay en cada etapa en este momento, antes de la entrega.',
        leer: [
            'Cada número es la cantidad de pedidos que se encuentran en esa etapa.',
            'Si un pedido tiene varios trabajos, se cuenta en la etapa del trabajo más atrasado.',
        ],
        ojo: 'Si los pedidos se acumulan en una etapa, ahí se está demorando la producción.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Panorama
     * ---------------------------------------------------------------- */
    'bi-panorama.kpi-ventas': {
        titulo: 'Ventas del periodo',
        que: 'El total vendido en el periodo: las cotizaciones aprobadas, sin IVA ni instalación y con el descuento otorgado al cliente.',
        leer: [
            'La etiqueta de color indica cuánto subió o bajó frente al periodo anterior; al lado, el valor de ese periodo.',
            'Verde indica una mejora; rojo, un retroceso.',
        ],
        ojo: 'El periodo anterior es el mismo rango de fechas desplazado hacia atrás: si se consultan 12 meses, se comparan con los 12 meses previos.',
    },
    'bi-panorama.kpi-cotizaciones': {
        titulo: 'Cotizaciones emitidas',
        que: 'Cuántos presupuestos se emitieron en el periodo, sin importar su resultado.',
        leer: [
            'Debajo se indica cuántos de ellos ya fueron aprobados.',
            'Un aumento de cotizaciones es una buena señal: indica más demanda.',
        ],
    },
    'bi-panorama.kpi-conversion': {
        titulo: 'Tasa de conversión',
        que: 'De los presupuestos que ya tienen respuesta (aprobados, rechazados o vencidos), qué porcentaje aprobó el cliente.',
        leer: [
            'Los pendientes no se cuentan: todavía pueden aprobarse.',
            'La variación se expresa en "pp" (puntos porcentuales): de 50 % a 52 % son +2 pp.',
        ],
        ojo: 'Una baja puede deberse al precio, al tiempo de respuesta o a la falta de seguimiento a los clientes.',
    },
    'bi-panorama.kpi-ticket': {
        titulo: 'Venta promedio',
        que: 'El ingreso promedio por venta: las ventas del periodo divididas entre las cotizaciones aprobadas.',
        leer: [
            'Si aumenta, cada cliente está comprando más por pedido.',
            'Debajo se indica sobre cuántas ventas se calculó.',
        ],
    },
    'bi-panorama.kpi-margen': {
        titulo: 'Margen bruto',
        que: 'De cada 100 Bs vendidos, cuántos quedan después de cubrir el costo del trabajo (material, mano de obra y complejidad), antes de impuestos.',
        leer: [
            'Se basa en el costo que calculó el motor de margen al cotizar: es el margen presupuestado.',
            'La variación se expresa en puntos porcentuales (pp).',
        ],
        ojo: 'Solo cuentan las cotizaciones con hoja de costos. La ganancia después de impuestos (IT e IUE) está en la pestaña "Presupuestos y rentabilidad".',
    },
    'bi-panorama.ventas': {
        titulo: 'Ventas por mes',
        que: 'El monto vendido en cada mes del periodo, comparado con el mismo mes del periodo anterior.',
        leer: [
            'Las barras corresponden a este periodo; la línea gris punteada, al periodo anterior.',
            'La barra de color intenso es el mejor mes; la línea punteada horizontal, el promedio mensual.',
            'En la parte superior, una frase resume lo más relevante.',
        ],
        ojo: 'Al hacer clic en una barra, todo el Panorama se filtra para mostrar solo ese mes.',
    },
    'bi-panorama.embudo': {
        titulo: 'Embudo de ventas',
        que: 'El recorrido de las cotizaciones del periodo: cuántas se emitieron, cuántas aprobó el cliente, cuántas pasaron a pedido y cuántas se cobraron por completo.',
        leer: [
            'Cada barra es una etapa; mientras más angosta, menos cotizaciones llegaron hasta ahí.',
            'Entre etapas se indica qué porcentaje avanzó a la siguiente.',
            'En rojo, la etapa donde más cotizaciones se pierden: es la que más conviene mejorar.',
        ],
        ojo: 'Junto a cada etapa se muestra el monto total de esas cotizaciones.',
    },
    'bi-panorama.ingresos-margen': {
        titulo: 'Ingresos y margen por mes',
        que: 'Las ventas de cada mes (barras) junto con el margen bruto de ese mes (línea).',
        leer: [
            'La escala izquierda está en bolivianos; la derecha, en porcentaje de margen.',
            'La línea punteada es el margen de todo el periodo: en los meses por debajo se vendió a menor precio o los trabajos costaron más.',
        ],
        ojo: 'Vender mucho con poco margen puede generar menos ganancia que vender menos con buen margen: conviene analizar ambos indicadores juntos.',
    },
    'bi-panorama.tipo': {
        titulo: 'Ingresos y margen por tipo de producto',
        que: 'Cuánto vende y qué margen genera cada tipo de producto: muebles y exhibidores, letreros, impresión, etc.',
        leer: [
            'Las barras son las ventas; la línea, el margen bruto de cada tipo.',
            'Al hacer clic en una barra se baja un nivel: de tipo a categorías y de categoría a productos. La ruta de navegación superior permite volver.',
            '"Trabajos a medida" son las líneas cotizadas sin un producto del catálogo.',
        ],
        ojo: 'El tipo que más vende no siempre es el más rentable: el hallazgo de la parte superior lo resume.',
    },
    'bi-panorama.clientes': {
        titulo: 'Top 5 clientes por ingresos',
        que: 'Los 5 clientes que más compraron en el periodo, con su participación en las ventas y el margen que generaron.',
        leer: [
            'La barra compara a cada cliente con el primero.',
            'Al hacer clic en un cliente, el Panorama muestra solo sus datos.',
        ],
        ojo: 'Un cliente grande con margen bajo merece una revisión: es posible que se le esté cotizando por debajo de lo conveniente.',
    },
    'bi-panorama.productos': {
        titulo: 'Top 5 productos por ingresos',
        que: 'Los 5 productos del catálogo que más ingresos generaron en el periodo, con las unidades vendidas y su margen.',
        leer: [
            'La barra compara cada producto con el primero.',
            'La imagen es la registrada en Productos; si no existe, se muestra un ícono.',
        ],
    },
    'bi-panorama.vendedor': {
        titulo: 'Ventas por vendedor',
        que: 'El monto vendido por cada vendedor en el periodo.',
        leer: [
            'La barra de color intenso corresponde al vendedor con más ventas.',
            '"Sin vendedor" son las cotizaciones que el cliente solicitó directamente por el portal.',
        ],
        ojo: 'Sirve para reconocer el buen desempeño e identificar a quién conviene apoyar.',
    },
    'bi-panorama.ciudad': {
        titulo: 'Ventas por ciudad',
        que: 'El monto vendido según la ciudad del cliente.',
        leer: [
            'La barra de color intenso corresponde a la ciudad con más compras.',
            'La ciudad se toma de la ficha del cliente; si no está registrada, se agrupa en "Sin ciudad registrada".',
        ],
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Preguntas para decidir
     * ---------------------------------------------------------------- */
    'bi-preguntas.costo-producto': {
        titulo: 'Cuánto cuesta producir cada producto',
        que: 'El costo real de producir 1 m², 1 metro o 1 unidad de cada producto: el material que el taller registró como utilizado, más la mano de obra y los otros insumos de la hoja de costos.',
        leer: [
            '"Mat. real" es el material efectivamente utilizado; "Mat. presup.", el previsto en la hoja de costos. Si el real es mayor, el taller está consumiendo más de lo previsto.',
            'El total es el precio mínimo: cobrar menos implica trabajar a pérdida.',
        ],
        ojo: 'La mano de obra es la presupuestada, porque no se registran las horas reales trabajadas.',
    },
    'bi-preguntas.costos-suben': {
        titulo: 'Qué costos están aumentando',
        que: 'Los materiales que subieron de precio en el periodo y el costo adicional que generó esa subida, según la cantidad realmente utilizada.',
        leer: [
            '"Costo adicional" = aumento del precio × cantidad utilizada.',
            'También indica si está subiendo el costo de la hora de mano de obra usado en las hojas de costos.',
        ],
    },
    'bi-preguntas.gasto-presupuesto': {
        titulo: 'Gasto contra presupuesto',
        que: 'En los mismos trabajos, el material realmente utilizado frente al presupuestado en la cotización.',
        leer: [
            'Una diferencia positiva es material consumido en exceso (cortes, sobrantes, errores o una hoja de costos que se quedó corta).',
            '"Excedidos" cuenta los trabajos que superaron en más del 5 % lo presupuestado.',
        ],
        ojo: 'Solo se incluyen los trabajos entregados con consumo registrado; de lo contrario, el gasto aparecería menor al real.',
    },
    'bi-preguntas.materiales-pesan': {
        titulo: 'Los materiales que más pesan',
        que: 'Los pocos materiales que concentran la mayor parte del gasto en material.',
        leer: [
            '"Acumulado" va sumando de arriba hacia abajo: al pasar el 80 %, ya están los materiales más relevantes.',
            'Ahí conviene negociar precios o buscar otros proveedores: una rebaja pequeña en estos ahorra más que una grande en el resto.',
        ],
    },
    'bi-preguntas.productos-rentables': {
        titulo: 'Qué producto genera más ganancia',
        que: 'Los productos ordenados por la ganancia que generan en bolivianos, con su margen: de cada 100 Bs cobrados, cuántos quedan después de cubrir el costo del trabajo (antes de impuestos).',
        leer: [
            'Más ganancia total no equivale a mejor margen: un producto con margen alto que se vende poco aporta menos que uno con margen normal y mucha venta.',
            'Si un producto se vende mucho y genera poco margen, conviene revisar su precio.',
        ],
    },
    'bi-preguntas.clientes-rentables': {
        titulo: 'Qué clientes generan más ganancia',
        que: 'Los clientes ordenados por la ganancia que generan, no por el volumen de compra.',
        leer: [
            'Un cliente grande con muchos descuentos puede generar menos ganancia que uno mediano que paga el precio normal.',
            'Si el cliente que más compra tiene un margen bajo, conviene revisar sus condiciones comerciales.',
        ],
    },
    'bi-preguntas.descuentos': {
        titulo: 'Lo que cuestan los descuentos',
        que: 'El monto descontado en las ventas del periodo, qué vendedores otorgan más descuentos y cuánto margen se pierde por ello. También cuánto quedó por debajo del precio sugerido en las líneas con precio ingresado manualmente.',
        leer: [
            'Compara el margen de las ventas con descuento frente a las que no lo tienen: la diferencia es el costo de descontar.',
            'El precio sugerido es el que calcula el sistema con el margen del tipo de proyecto.',
        ],
    },
    'bi-preguntas.margen-bajo': {
        titulo: 'Ventas con margen bajo',
        que: 'Las ventas que se cerraron en amarillo o en rojo según el semáforo de margen, por vendedor.',
        leer: [
            'Rojo: después de impuestos queda menos del 15 % del costo. Amarillo: entre 15 % y 30 %.',
            'Muchas ventas en rojo de un mismo vendedor suelen indicar exceso de descuentos o precios ingresados manualmente.',
        ],
    },
    'bi-preguntas.materia-prima': {
        titulo: 'Si sube la materia prima',
        que: 'Cómo cambiarían el margen y la ganancia si el material sube 5 %, 10 % o 20 %, y cuánto habría que ajustar los precios para no perder margen.',
        leer: [
            '"Subir precios" es el ajuste justo para mantener el margen: como el material es solo una parte del costo, el precio sube en menor proporción que el material.',
            '"Probable" usa la tendencia actual del índice de costo de materiales.',
        ],
        ojo: 'Es una simulación: supone que se vende lo mismo que en el periodo.',
    },
    'bi-preguntas.cobranza': {
        titulo: 'Cuánto se ha cobrado',
        que: 'De lo vendido en pedidos del periodo, cuánto ya ingresó a caja y cuánto falta cobrar, según la antigüedad del pedido.',
        leer: [
            'Lo que tiene más de 90 días es lo más difícil de cobrar: debe atenderse primero.',
            'La ganancia que no se cobra no es dinero disponible.',
        ],
    },
    'bi-preguntas.decisiones': {
        titulo: 'Qué decisiones tomar',
        que: 'Las acciones que surgen de las demás preguntas, ordenadas por el monto que involucran en un periodo como este.',
        leer: [
            'Cada acción indica de qué pregunta proviene: con un clic se accede al detalle.',
            'Los montos no se suman entre sí, porque algunas acciones se superponen (por ejemplo, descuentos y margen bajo).',
        ],
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Demanda y ventas
     * ---------------------------------------------------------------- */
    'bi.proyeccion-demanda': {
        titulo: 'Proyección de demanda',
        que: 'Cuántos pedidos hubo cada mes y, a partir de ese historial, cuántos podrían llegar en los próximos meses.',
        leer: [
            'La línea con relleno son los pedidos reales de los meses ya cerrados.',
            'La línea gris es el promedio de los últimos 3 meses: suaviza los meses atípicos para ver mejor la tendencia.',
            'La línea punteada, en la zona sombreada, es la proyección. La franja que la rodea es el rango probable: 8 de cada 10 veces el valor real cae dentro de ella.',
        ],
        ojo: 'Es un cálculo basado en la tendencia, no una certeza. Ante un hecho fuera de lo común (una campaña, un cliente nuevo importante) conviene tomarlo con cautela. Más abajo, en "Cómo se calcula", se indica qué tan confiable es.',
    },
    'bi.productos-mas-vendidos': {
        titulo: 'Productos más vendidos',
        que: 'Los 10 productos que más ingresos generaron en cotizaciones aprobadas dentro del periodo.',
        leer: [
            'La barra más larga es el producto con más ventas del periodo.',
            'Pase el cursor sobre una barra para ver el monto exacto.',
        ],
        ojo: 'Si un producto que antes se vendía bien ya no aparece, conviene analizar el motivo.',
    },
    'bi.categorias-mas-vendidas': {
        titulo: 'Categorías más vendidas',
        que: 'Lo mismo que los productos, pero agrupados por categoría.',
        leer: [
            'Indica en qué rubros se concentran los ingresos, más allá de un producto en particular.',
            'La barra más larga es la categoría con más ventas.',
        ],
    },
    'bi.clientes-top': {
        titulo: 'Clientes que más compran',
        que: 'Los 10 clientes con mayor monto de compra en el periodo, sumando sus cotizaciones aprobadas, y el resto agrupado en "Otros".',
        leer: [
            'En la esquina superior derecha: cuánto suman los 10 primeros. Si superan la mitad de las ventas, existe dependencia de pocos clientes y perder uno tiene un impacto importante.',
            'Cada cliente muestra el monto comprado, la cantidad de compras, su participación en el total y si compró más o menos que en un periodo de igual duración inmediatamente anterior. "Nuevo" indica que antes no había comprado.',
            'En amarillo, los clientes sin compras desde hace más de 60 días: son los primeros a quienes conviene contactar.',
            'Si el cliente tiene logo registrado se muestra el logo; si no, sus iniciales. El logo se carga desde Clientes.',
        ],
        ojo: 'Respeta las fechas del filtro y las sucursales a las que tiene acceso. Una variación muy alta puede deberse a que en el periodo anterior todavía no había datos.',
    },
    'bi.clientes-pareto': {
        titulo: 'Concentración de ventas por cliente',
        que: 'Todos los clientes que compraron en el periodo, de mayor a menor, con una línea que muestra el porcentaje acumulado de las ventas.',
        leer: [
            'Cada barra es el monto comprado por un cliente (el nombre completo aparece al pasar el cursor).',
            'La línea es el porcentaje acumulado: en el cliente 5, por ejemplo, indica cuánto suman los 5 primeros.',
            'La línea punteada marca el 80 %. En la esquina superior derecha se indica cuántos clientes se necesitan para alcanzarlo.',
            'Si hay muchos clientes, a partir del puesto 30 se agrupan en una sola barra.',
        ],
        ojo: 'Si pocos clientes concentran el 80 %, la dependencia de ellos es alta: conviene cuidarlos y, al mismo tiempo, ampliar la cartera de clientes.',
    },
    'bi.evolucion-costos': {
        titulo: 'Evolución del costo de materiales',
        que: 'Cómo cambió el precio de los materiales cada vez que se aprobó una compra.',
        leer: [
            'Si una línea sube, ese material se está encareciendo.',
            'Entre una compra y la siguiente el precio se mantiene; por eso la línea es continua.',
            'Se muestran solo 4 materiales para facilitar la lectura; el resto está en "Costos y materiales".',
        ],
        ojo: 'Corresponde a toda la empresa, no a una sucursal. Si el material sube y no se actualizan los precios de venta, el margen se reduce.',
    },
    'bi.estacionalidad': {
        titulo: 'Estacionalidad',
        que: 'Cuántos pedidos hay en cada mes del año, con una línea por gestión (2024, 2025, 2026…) y el promedio de todas. Usa todo el historial, no las fechas del filtro superior.',
        leer: [
            'La línea con relleno es el promedio: los meses en que sube son la temporada alta; en los que baja, la temporada baja.',
            'Cada gestión tiene su propia línea: si un mes es alto en todos los años, es una temporada real; si lo es en uno solo, fue algo puntual.',
            'Haga clic en un año de la leyenda para ocultarlo o mostrarlo y comparar solo las gestiones que necesite.',
            'Los meses que aún no llegaron, o anteriores al primer pedido registrado, aparecen vacíos, no en cero.',
        ],
        ojo: 'El promedio aparece cuando hay al menos dos gestiones y no incluye el mes en curso, porque aún no termina. Sirve para planificar: abastecer material y prever personal antes de la temporada alta.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Alertas
     * ---------------------------------------------------------------- */
    'bi-alertas.anomalias': {
        titulo: 'Anomalías detectadas',
        que: 'Situaciones inusuales que el sistema detecta automáticamente al revisar los datos cada día a las 6:30: caídas o aumentos bruscos de cotizaciones, ventas con margen muy bajo, vendedores que dejaron de cotizar y clientes importantes que dejaron de comprar.',
        leer: [
            'Alta: requiere atención hoy (también llega como notificación). Media: revisarla durante la semana.',
            'Cada una indica qué ocurrió, en qué sucursal o con quién, y qué conviene revisar.',
            'Una vez revisada, márquela como "Revisada"; si no era relevante, use "Descartar". Los botones superiores filtran por estado.',
        ],
        ojo: 'Que el sistema la marque no significa que haya un error: a veces existe una explicación (un feriado, un aviso del cliente). Para esos casos está "Descartar".',
    },
    'bi-alertas.kpi-activas': {
        titulo: 'Alertas activas',
        que: 'Cuántas alertas de costo y margen hay en el periodo seleccionado: materiales con aumentos importantes, presupuestos con poco margen y pedidos que consumieron más material de lo previsto.',
        leer: [
            'Es la suma de las alertas de prioridad alta y media.',
            'El detalle de cada una está en la lista inferior.',
        ],
    },
    'bi-alertas.kpi-alta': {
        titulo: 'Prioridad alta',
        que: 'Las alertas que requieren acción inmediata: por ejemplo, un presupuesto a punto de cerrarse en rojo o un material con un aumento muy fuerte.',
        leer: [
            'Si este número no es cero, conviene empezar por aquí.',
            'En la lista inferior aparecen con la etiqueta roja "Alta".',
        ],
    },
    'bi-alertas.kpi-media': {
        titulo: 'Prioridad media',
        que: 'Alertas para revisar durante la semana: no son urgentes, pero si no se atienden pueden convertirse en un problema.',
        leer: [
            'En la lista inferior aparecen con la etiqueta amarilla "Media".',
        ],
    },
    'bi-alertas.costo-margen': {
        titulo: 'Alertas de costo y margen',
        que: 'Todo lo que puede estar reduciendo la ganancia: materiales que subieron por encima del límite, presupuestos vigentes con margen bajo, presupuestos pendientes armados con precios desactualizados, pedidos que usaron más material de lo presupuestado y materiales que suben mes tras mes.',
        leer: [
            'En la parte superior están los límites con los que se calcula cada alerta.',
            'Puede filtrar por tipo con la lista de la derecha.',
            'El botón "Ver" lleva directamente al presupuesto, pedido o material.',
        ],
        ojo: 'Se calcula al abrir la pantalla, por lo que siempre está actualizado. Los precios de material corresponden a toda la empresa.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Costos y materiales
     * ---------------------------------------------------------------- */
    'bi-costos.indice': {
        titulo: 'Proyección de costos futuros',
        que: 'Un indicador que resume el costo de los materiales en comparación con el inicio del periodo. Parte de 100.',
        leer: [
            '110 significa que los materiales están 10 % más caros que al inicio; 95, que están 5 % más baratos.',
            'La parte punteada indica hacia dónde iría si se mantiene la tendencia, con su rango probable.',
        ],
        ojo: 'Si el índice sube y se sigue cotizando con los precios anteriores, cada trabajo genera menos ganancia.',
    },
    'bi-costos.material': {
        titulo: 'Proyección por material',
        que: 'El precio de un material, mes a mes, y su tendencia. El material se elige en la lista superior.',
        leer: [
            'La línea continua es el precio de cada mes.',
            'La punteada es la proyección y la franja, el rango probable.',
        ],
        ojo: 'Es útil antes de comprar: si el precio viene subiendo, puede convenir adelantar la compra.',
    },
    'bi-costos.consumo': {
        titulo: 'Consumo de materiales',
        que: 'El monto en material utilizado en los pedidos cada mes, según lo registrado por producción.',
        leer: [
            'La línea con relleno es el consumo real de cada mes.',
            'La gris es el promedio de los últimos 3 meses.',
            'La punteada es el consumo estimado para los próximos meses si se mantiene la tendencia.',
        ],
        ojo: 'Si producción no registra lo que utiliza, el mes aparece bajo aunque haya tenido mucho trabajo.',
    },
    'bi-costos.mas-consumidos': {
        titulo: 'Materiales más consumidos',
        que: 'Los 10 materiales con mayor gasto en el periodo.',
        leer: [
            'La barra más larga es el material con mayor costo total.',
            'Si aparece "Presupuestado", se compara lo calculado en la cotización con lo realmente utilizado.',
        ],
        ojo: 'Si lo real es bastante mayor que lo presupuestado, se está consumiendo más material de lo previsto: puede ser desperdicio o una cotización que se quedó corta.',
    },
    'bi-costos.variacion': {
        titulo: 'Impacto de las variaciones de precio',
        que: 'Cuánto subió o bajó el precio de cada material dentro del periodo, en porcentaje.',
        leer: [
            'Rojo, hacia la derecha: el material subió, lo que reduce el margen.',
            'Verde, hacia la izquierda: el material bajó, lo que favorece el margen.',
        ],
        ojo: 'Los que más subieron son los primeros candidatos para revisar precios o evaluar otros proveedores.',
    },
    'bi-costos.kpi-indice': {
        titulo: 'Índice de costo (fin del periodo)',
        que: 'Cuánto más caros (o más baratos) están los materiales al final del periodo en comparación con su primer precio dentro de ese periodo.',
        leer: [
            '+6 % significa que, en promedio, los materiales cuestan 6 % más que al inicio.',
            'La flecha indica la tendencia mensual: en rojo si sube (desfavorable), en verde si baja.',
        ],
    },
    'bi-costos.kpi-consumido': {
        titulo: 'Material consumido',
        que: 'El monto en material utilizado en los pedidos del periodo, según lo registrado por producción.',
        leer: [
            'Debajo se indica cuántos materiales distintos se utilizaron.',
            'La flecha es la tendencia mensual: si sube, se está gastando más en material.',
        ],
        ojo: 'Si producción no registra el consumo, este número aparece por debajo del gasto real.',
    },
    'bi-costos.kpi-impacto': {
        titulo: 'Impacto de las variaciones',
        que: 'El costo adicional (o el ahorro) que generó el cambio de precio de los materiales, según lo utilizado en el periodo.',
        leer: [
            'Se calcula así: (precio final − precio inicial) × cantidad consumida de cada material.',
            'En rojo, costo adicional; en verde, ahorro.',
            'Debajo se indica cuántos materiales subieron y cuántos bajaron.',
        ],
    },
    'bi-costos.kpi-historial': {
        titulo: 'Materiales con historial',
        que: 'Cuántos materiales tienen al menos dos precios registrados, lo mínimo necesario para ver si suben o bajan.',
        leer: [
            'Cada compra aprobada registra el precio del material.',
            'Un material con una sola compra todavía no tiene tendencia.',
        ],
    },
    'bi-costos.lectura': {
        titulo: 'Lectura del material',
        que: 'El resumen numérico del material seleccionado en el gráfico contiguo.',
        leer: [
            'Precio actual: el costo vigente por unidad de medida.',
            'Tendencia: cuánto sube o baja por mes, en promedio.',
            'Precio estimado: lo que costaría al final de la proyección si se mantiene la tendencia.',
        ],
        ojo: 'Si el precio estimado es bastante mayor que el actual, conviene revisar las cotizaciones que usan este material.',
    },
    'bi-costos.detalle-impacto': {
        titulo: 'Detalle del impacto en costos',
        que: 'Por material: el precio al inicio y al final del periodo, la cantidad utilizada y el monto que representó esa diferencia.',
        leer: [
            'Variación: cuánto cambió el precio, en porcentaje.',
            'Impacto: (precio final − precio inicial) × cantidad consumida. En rojo, costo adicional; en verde, ahorro.',
        ],
        ojo: 'Un material que subió pero no se utilizó en el periodo tiene impacto cero por ahora, pero se reflejará en el próximo pedido que lo use.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Presupuestos y rentabilidad
     * ---------------------------------------------------------------- */
    'bi-rentabilidad.pronostico': {
        titulo: 'Pronóstico de presupuestos',
        que: 'El monto de los presupuestos emitidos cada mes y el de los aprobados por el cliente, con la proyección de lo que se cotizará.',
        leer: [
            'La línea con relleno es todo lo cotizado; la otra, lo aprobado.',
            'La distancia entre ambas es lo cotizado que no se concretó.',
            'La punteada es la proyección de lo cotizado, con su rango probable.',
        ],
        ojo: 'Si la distancia crece, se está cotizando mucho pero cerrando poco: conviene reforzar el seguimiento a los clientes.',
    },
    'bi-rentabilidad.cantidades': {
        titulo: 'Presupuestos por mes',
        que: 'Cuántos presupuestos se emitieron y cuántos se aprobaron cada mes.',
        leer: [
            'Cada mes tiene dos barras: emitidos y aprobados.',
            'Mientras más se parezcan, mayor es la efectividad en el cierre de ventas.',
        ],
        ojo: 'Si la barra de aprobados es mucho menor que la de emitidos, conviene contactar a esos clientes antes de que pierdan interés.',
    },
    'bi-rentabilidad.tendencia': {
        titulo: 'Tendencia: precio vs. costo',
        que: 'El precio promedio cobrado por presupuesto (sin IVA ni instalación) frente a su costo promedio. Ambas líneas se calculan sobre los mismos presupuestos: los que tienen hoja de costos.',
        leer: [
            'El espacio entre las dos líneas es, aproximadamente, la ganancia por trabajo.',
            'Si las líneas se acercan, cada trabajo genera menos ganancia.',
        ],
    },
    'bi-rentabilidad.rentabilidad': {
        titulo: 'Rentabilidad por mes',
        que: 'De cada 100 Bs de costo de un trabajo, cuántos Bs de ganancia real quedan después de impuestos, mes a mes.',
        leer: [
            'Las líneas de colores son el semáforo: sobre la verde, rentabilidad adecuada; entre la amarilla y la verde, intermedia; bajo la amarilla, baja.',
            'La parte punteada indica hacia dónde iría si se mantiene la tendencia.',
        ],
        ojo: 'Solo cuentan los presupuestos aprobados con hoja de costos. Los anteriores, sin hoja, se excluyen para no distorsionar el resultado.',
    },
    'bi-rentabilidad.utilidad': {
        titulo: 'Utilidad real por mes',
        que: 'La ganancia real, en bolivianos, que generaron los presupuestos aprobados de cada mes.',
        leer: [
            'Cada barra es la utilidad neta del mes, ya descontados el costo y los impuestos.',
            'La parte punteada es la proyección para los próximos meses.',
        ],
        ojo: 'Un mes puede tener rentabilidad alta y poca utilidad si se vendió poco: conviene analizar ambos gráficos juntos.',
    },
    'bi-rentabilidad.kpi-emitidos': {
        titulo: 'Presupuestos emitidos',
        que: 'Cuántos presupuestos se emitieron en el periodo y el monto total que suman.',
        leer: [
            'Incluye todos: aprobados, rechazados, vencidos y pendientes.',
            'La flecha indica si cada mes se cotiza más o menos.',
        ],
    },
    'bi-rentabilidad.kpi-aprobacion': {
        titulo: 'Tasa de aprobación',
        que: 'De los presupuestos que ya tienen respuesta (aprobados, rechazados o vencidos), qué porcentaje aprobó el cliente.',
        leer: [
            'Los pendientes no se cuentan: todavía pueden aprobarse.',
            'Debajo se indica cuántos se aprobaron y cuántos siguen pendientes.',
        ],
        ojo: 'Si este número baja, puede deberse al precio, al tiempo de respuesta o a la falta de seguimiento a los clientes.',
    },
    'bi-rentabilidad.kpi-rentabilidad': {
        titulo: 'Rentabilidad sobre costo',
        que: 'De cada 100 Bs de costo de los trabajos aprobados del periodo, cuántos Bs de ganancia real quedan, ya descontados los impuestos.',
        leer: [
            'El color sigue el semáforo: verde es adecuado, amarillo intermedio y rojo bajo.',
            'Debajo se indica la ganancia total en bolivianos; la flecha muestra si mejora mes a mes.',
        ],
        ojo: 'Solo cuentan los presupuestos con hoja de costos.',
    },
    'bi-rentabilidad.kpi-proyectada': {
        titulo: 'Rentabilidad proyectada',
        que: 'La rentabilidad estimada al final de la proyección si se mantiene la tendencia de los últimos meses.',
        leer: [
            'Debajo se indica a qué mes corresponde la estimación.',
            'Si indica "Faltan meses con datos", todavía no hay historial suficiente para calcularla.',
        ],
        ojo: 'Es una estimación, no un compromiso: sirve para saber si la tendencia es al alza o a la baja.',
    },
    'bi-rentabilidad.semaforo': {
        titulo: 'Semáforo de los presupuestos del periodo',
        que: 'El resultado de todos los presupuestos del periodo según el motor de margen, el mismo que se usa al cotizar.',
        leer: [
            'Aceptar (verde): el precio genera una buena ganancia.',
            'Revisar precio (amarillo): genera ganancia, pero menos de la recomendada.',
            'No aceptar (rojo): con ese precio la ganancia es muy baja o hay pérdida.',
            'Sin hoja de costos: presupuestos antiguos o manuales que no se pueden medir.',
        ],
        ojo: 'Muchos presupuestos en amarillo o rojo indican que se está cotizando por debajo de lo conveniente o que los costos subieron.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Simulador de decisiones
     * ---------------------------------------------------------------- */
    'bi-simulador.sensibilidad': {
        titulo: 'Sensibilidad al costo de materiales',
        que: '¿Qué ocurre con la ganancia si el material sube o baja? Compara dos alternativas: mantener el precio o trasladar el aumento al cliente.',
        leer: [
            'En el eje inferior está la variación del costo (de -30 % a +30 %); en el lateral, la rentabilidad resultante.',
            '"Manteniendo el precio" disminuye a medida que sube el material.',
            '"Trasladando el aumento" muestra el resultado si el precio sube en la misma proporción.',
            'Las líneas de colores son el semáforo de rentabilidad.',
        ],
        ojo: 'Es una simulación con los datos ingresados arriba: no modifica nada en el sistema.',
    },
    'bi-simulador.composicion': {
        titulo: 'A dónde va el precio',
        que: 'Cómo se distribuye el precio de un presupuesto: cuánto corresponde al costo, a los impuestos (IT e IUE) y a la ganancia.',
        leer: [
            '"Base" es el presupuesto promedio del periodo, en su situación actual.',
            '"Escenario" es cómo quedaría con los cambios aplicados en el simulador.',
            'Compare las barras de "Utilidad real": es la ganancia efectiva.',
        ],
    },
    'bi-simulador.base': {
        titulo: 'Escenario base',
        que: 'El punto de partida del simulador: un presupuesto promedio de los aprobados en el periodo, con su costo, su precio y la cantidad de aprobaciones por mes.',
        leer: [
            'Puede modificar cualquier valor para simular un trabajo en particular.',
            'Si elige un tipo de proyecto, el precio se calcula con el margen mínimo de ese tipo.',
            'El costo es el costo ajustado, el mismo que registra la cotización.',
        ],
        ojo: 'Los cambios realizados aquí no se guardan ni afectan las cotizaciones reales.',
    },
    'bi-simulador.palancas': {
        titulo: '¿Qué pasa si…?',
        que: 'Los controles para evaluar decisiones: variación del costo del material, ajuste del precio, descuentos y cambios en la cantidad de trabajos vendidos.',
        leer: [
            'Al mover los controles, el resultado de la derecha se actualiza de inmediato.',
            '"Trasladar al precio" sube el precio en la misma proporción que el material.',
            '"Restablecer" devuelve todos los valores a cero.',
        ],
    },
    'bi-simulador.resultado': {
        titulo: 'Resultado del escenario',
        que: 'Cómo quedaría el presupuesto con los cambios aplicados, calculado con el mismo motor de margen de las cotizaciones.',
        leer: [
            'El color superior es el semáforo: indica si ese precio es aceptable o no.',
            'El mensaje indica cuánto más puede subir el costo sin salir de verde, o cuánto debe subir el precio para volver a verde.',
            'La tabla compara la base con su escenario, línea por línea.',
        ],
        ojo: 'El "precio mínimo para verde" es una buena referencia para no cotizar por debajo cuando sube el material.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Resumen semanal
     * ---------------------------------------------------------------- */
    'bi-resumen.semana': {
        titulo: 'Resumen de la semana',
        que: 'Un resumen automático del desempeño de la semana anterior, que se genera cada lunes a las 7:00.',
        leer: [
            'La frase destacada resume lo más importante de la semana en una línea.',
            'Si tiene acceso a varias sucursales, hay un resumen general y uno por sucursal: puede cambiarlo con los botones de la derecha.',
            'La etiqueta indica si fue redactado por la IA o por la plantilla automática; las cifras son las mismas en ambos casos.',
        ],
        ojo: 'Aunque lo redacte la IA, no inventa cifras: cada número se verifica contra los datos antes de publicarse.',
    },
    'bi-resumen.destacados': {
        titulo: 'Lo más destacado',
        que: 'Los resultados positivos de la semana anterior.',
        leer: [
            'Al pasar el cursor sobre un punto, se resaltan en la tabla de la derecha los indicadores de los que proviene.',
        ],
    },
    'bi-resumen.riesgos': {
        titulo: 'Riesgos',
        que: 'Los resultados negativos o lo que puede convertirse en un problema si no se atiende.',
        leer: [
            'Alta: debe atenderse esta semana. Media: requiere seguimiento.',
            'Al pasar el cursor sobre un punto, se resaltan en la tabla los indicadores que lo explican.',
        ],
    },
    'bi-resumen.recomendaciones': {
        titulo: 'Recomendaciones para esta semana',
        que: 'Qué conviene hacer esta semana a partir de los resultados de la anterior.',
        leer: [
            'En negrita la acción; debajo, su justificación.',
        ],
        ojo: 'Son sugerencias para analizar con el equipo, no instrucciones: el contexto que no reflejan los números lo conoce usted.',
    },
    'bi-resumen.indicadores': {
        titulo: 'Indicadores de la semana',
        que: 'Las cifras de la semana anterior en las que se basa el resumen: presupuestos, ventas, monto promedio por presupuesto, rentabilidad, cobranza, entregas y costo de material.',
        leer: [
            '"vs. 4 sem." compara con el promedio de las 4 semanas anteriores: es la comparación más confiable, porque una sola semana puede verse alterada por un pedido grande.',
            '"vs. ant." compara con la semana anterior y "vs. año", con la misma semana del año pasado.',
            'Verde indica que el indicador se movió en la dirección favorable; rojo, en la desfavorable.',
        ],
    },
    'bi-resumen.contexto': {
        titulo: 'Contexto',
        que: 'Información de apoyo para interpretar la semana: qué familias de productos se cotizaron más, qué vendedor cotizó más y cuántas anomalías siguen sin revisar.',
        leer: [
            'La barra de cada familia es su participación en el total cotizado.',
            'El número de anomalías lleva directamente a la pantalla de Alertas.',
        ],
    },
    'bi-resumen.historial': {
        titulo: 'Semanas anteriores',
        que: 'Los resúmenes de semanas anteriores, para compararlos o consultarlos de nuevo.',
        leer: [
            'Haga clic en una semana para abrir su resumen.',
            'Se abre con la misma sucursal que está consultando.',
        ],
    },
};
