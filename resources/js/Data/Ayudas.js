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
            'Al cambiar las fechas del filtro y presionar "Actualizar", el gráfico se actualiza.',
        ],
        ojo: 'Muestra lo vendido, no lo cobrado. Los saldos pendientes de los clientes están en "Cuentas por cobrar".',
    },
    'financiero.ventas-por-sucursal': {
        titulo: 'Ventas por sucursal',
        que: 'El monto vendido por cada sucursal en el periodo seleccionado.',
        leer: [
            'La barra más larga corresponde a la sucursal con más ventas; el porcentaje es su parte del total.',
            'A la derecha, el monto vendido por cada sucursal.',
        ],
        ojo: 'Si solo aparece su sucursal, es porque su cuenta tiene acceso limitado a ella: lo indica la etiqueta de la esquina superior derecha.',
    },
    'financiero.margen-materiales': {
        titulo: 'Margen sobre materiales',
        que: 'Qué parte de lo cobrado en los pedidos terminados queda después de descontar el material que producción registró como utilizado.',
        leer: [
            'Por ejemplo, 75 % quiere decir que de cada Bs 100 cobrados quedan Bs 75 después de pagar el material.',
            'Cuenta los pedidos en acabado o entregados, de cualquier fecha: no depende del período del filtro.',
        ],
        ojo: 'Solo descuenta materiales, no sueldos ni otros gastos: no es la ganancia final de la empresa. Los pedidos sin consumo registrado quedan fuera del cálculo.',
    },
    'financiero.pedidos-con-perdida': {
        titulo: 'Pedidos con pérdida',
        que: 'Pedidos terminados cuyo material registrado costó más de lo que se cobró.',
        leer: [
            'Use el filtro "Con pérdida" de la tabla de resultados para ver cuáles son.',
            'Están ordenados del menor al mayor resultado, para que lo primero que se vea sea lo que requiere revisión.',
        ],
        ojo: 'Conviene revisar el precio y el material registrado de cada uno: una pérdida también puede ser un consumo cargado de más.',
    },
    'financiero.total-vendido': {
        titulo: 'Ventas del período',
        que: 'La suma de todas las cotizaciones aprobadas (incluidas las que ya pasaron a pedido) con fecha dentro del periodo seleccionado.',
        leer: [
            'Es el total de cada cotización tal como se presentó al cliente.',
            'Se actualiza al cambiar las fechas del filtro y presionar "Actualizar".',
        ],
        ojo: 'Vendido no significa cobrado: un cliente puede haber aprobado y aún no haber realizado ningún pago.',
    },
    'financiero.por-cobrar': {
        titulo: 'Pendiente de cobro',
        que: 'El dinero que los clientes aún adeudan: el saldo pendiente de los pedidos que no fueron cancelados.',
        leer: [
            'Es la suma de los saldos: total del pedido menos lo ya pagado.',
            'No depende de las fechas del filtro: es la deuda pendiente a la fecha, de cualquier pedido.',
        ],
        ojo: 'El detalle por pedido, con los días de antigüedad de cada deuda, se encuentra en el PDF del reporte.',
    },
    'financiero.rentabilidad-tabla': {
        titulo: 'Resultado por pedido',
        que: 'Cada pedido terminado con lo cobrado, el costo del material registrado y la diferencia entre ambos.',
        leer: [
            'Ingreso: el valor del pedido. Materiales: lo que producción registró como utilizado.',
            'Resultado: la diferencia. En verde si quedó a favor, en rojo si hubo pérdida; entre paréntesis, qué parte del ingreso representa.',
            'Se incluyen los pedidos en acabado o ya entregados, sin importar la fecha del filtro.',
        ],
        ojo: 'Los pedidos "Sin materiales" no tienen consumo registrado: su resultado no se puede medir y van al final de la lista.',
    },

    /* ------------------------------------------------------------------
     * Reporte de producción
     * ---------------------------------------------------------------- */
    'produccion.entregados-tarde': {
        titulo: 'Pedidos entregados tarde',
        que: 'Pedidos entregados después de la fecha comprometida con el cliente.',
        leer: [
            'Debajo se indica el atraso medio: cuántos días pasaron, en promedio, desde la fecha comprometida.',
            'Considera todo el historial de entregas.',
        ],
        ojo: 'El PDF del reporte lista cada pedido atrasado con sus fechas.',
    },
    'produccion.pedidos-en-curso': {
        titulo: 'Pedidos en curso',
        que: 'Pedidos que todavía no se entregaron: están en alguna etapa del taller.',
        leer: ['Más abajo se ve en qué etapa está cada uno.'],
        ojo: 'Un pedido puede tener varios trabajos: por eso esta cifra puede ser distinta a la de trabajos pendientes.',
    },
    'produccion.trabajos-pendientes': {
        titulo: 'Trabajos pendientes',
        que: 'Etapas de trabajo asignadas a un área que todavía no se cerraron.',
        leer: ['Debajo se indica entre cuántas áreas se reparten.'],
        ojo: 'Refleja la situación actual: cambia cuando un área cierra o recibe una etapa.',
    },
    'produccion.duracion-por-etapa': {
        titulo: 'Tiempo promedio por etapa',
        que: 'Cuántos días, en promedio, permanece un trabajo en cada etapa del taller antes de pasar a la siguiente.',
        leer: [
            'La barra más larga es la etapa más lenta: ahí se concentran las demoras.',
            'Las etapas que no aparecen todavía no tienen registros suficientes para medirse.',
        ],
        ojo: 'Solo se cuentan las etapas que el taller abrió y cerró en el sistema. Sin registro, no es posible medir.',
    },
    'produccion.carga-por-area': {
        titulo: 'Trabajos pendientes por área',
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
        titulo: 'Pedidos en curso por etapa',
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
        titulo: 'Conversión de cotizaciones',
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
        titulo: 'Margen presupuestado',
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
            'La línea azul corresponde a este periodo; la gris punteada, al periodo anterior.',
            'Con el botón "Tabla" se ven los montos exactos de cada mes y su variación.',
            'Debajo del gráfico, una frase resume lo más relevante.',
        ],
        ojo: 'Al hacer clic en un mes, todo el Panorama se filtra para mostrar solo ese mes.',
    },
    'bi-panorama.embudo': {
        titulo: 'Avance de las cotizaciones',
        que: 'El recorrido de las cotizaciones del periodo: cuántas se emitieron, cuántas aprobó el cliente, cuántas pasaron a pedido y cuántas se cobraron por completo.',
        leer: [
            'Cada barra es una etapa; mientras más corta, menos cotizaciones llegaron hasta ahí.',
            'Debajo de cada etapa se indica qué porcentaje de la anterior llegó a ella.',
            'En rojo, el paso donde más cotizaciones se pierden: es el que más conviene mejorar.',
        ],
        ojo: 'Este porcentaje de aprobación usa todas las emitidas; el de la tarjeta "Conversión de cotizaciones" solo las que ya tienen respuesta. Por eso no coinciden.',
    },
    'bi-panorama.ingresos-margen': {
        titulo: 'Margen presupuestado por mes',
        que: 'El margen presupuestado de cada mes: qué parte de lo vendido queda después del costo calculado al cotizar.',
        leer: [
            'La línea punteada es el margen de todo el periodo: en los meses por debajo se vendió a menor precio o los trabajos costaron más.',
            'Con el botón "Tabla" se ven las ventas y el margen de cada mes.',
        ],
        ojo: 'Vender mucho con poco margen puede generar menos ganancia que vender menos con buen margen: conviene analizar ambos indicadores juntos.',
    },
    'bi-panorama.tipo': {
        titulo: 'Ventas y margen por tipo de producto',
        que: 'Cuánto vende y qué margen presupuestado deja cada tipo de producto: muebles y exhibidores, letreros, impresión, etc.',
        leer: [
            'La barra compara las ventas de cada tipo con las del primero; a la derecha, su margen.',
            'Al hacer clic en una fila se baja un nivel: de tipo a categorías y de categoría a productos. La ruta de navegación superior permite volver.',
            '"Trabajos a medida" son las líneas cotizadas sin un producto del catálogo.',
        ],
        ojo: 'El tipo que más vende no siempre es el más rentable: la frase de abajo lo resume.',
    },
    'bi-panorama.clientes': {
        titulo: '5 clientes con mayores ventas',
        que: 'Los 5 clientes que más compraron en el periodo, con su participación en las ventas y el margen que generaron.',
        leer: [
            'La barra compara a cada cliente con el primero.',
            'Al hacer clic en un cliente, el Panorama muestra solo sus datos.',
        ],
        ojo: 'Un cliente grande con margen bajo merece una revisión: es posible que se le esté cotizando por debajo de lo conveniente.',
    },
    'bi-panorama.productos': {
        titulo: '5 productos con mayores ventas',
        que: 'Los 5 productos del catálogo que más ingresos generaron en el periodo, con las unidades vendidas y su margen.',
        leer: [
            'La barra compara cada producto con el primero.',
            'El margen es el presupuestado al cotizar cada producto.',
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
        titulo: 'Costo calculado por producto',
        que: 'El costo calculado de producir 1 m², 1 metro o 1 unidad de cada producto: el material que el taller registró como utilizado, más la mano de obra y los otros insumos de la hoja de costos.',
        leer: [
            '"Material registrado" es el material efectivamente utilizado; "Material previsto", el de la hoja de costos. Si el registrado es mayor, el taller está consumiendo más de lo previsto.',
            'El total sirve de referencia al cotizar; antes de fijar el precio conviene revisar la mano de obra, los otros costos y el margen.',
        ],
        ojo: 'La mano de obra es la presupuestada, porque no se registran las horas reales trabajadas.',
    },
    'bi-preguntas.costos-suben': {
        titulo: 'Qué materiales están subiendo de precio',
        que: 'Los materiales que subieron de precio en el periodo y el costo adicional que generó esa subida, según la cantidad realmente utilizada.',
        leer: [
            '"Costo adicional calculado" = aumento del precio × cantidad utilizada.',
            'El gráfico ordena los materiales por costo adicional; "Detalle de los precios" compara el precio anterior con el actual.',
            'La respuesta también indica si está subiendo la hora de mano de obra usada en las hojas de costos.',
        ],
        ojo: 'El costo adicional no equivale a una pérdida neta ni a un ahorro garantizado: depende de cuánto se siga comprando.',
    },
    'bi-preguntas.gasto-presupuesto': {
        titulo: 'Material registrado frente al previsto',
        que: 'En los mismos trabajos, el material registrado como utilizado frente al previsto en la cotización.',
        leer: [
            'Una diferencia positiva es material consumido en exceso (cortes, sobrantes, errores o una hoja de costos que se quedó corta).',
            'En "Trabajos / excedidos", el segundo número cuenta los trabajos que superaron en más del 5 % lo previsto.',
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
        titulo: 'Qué productos aportan más al resultado bruto',
        que: 'Los productos ordenados por el resultado bruto que generan en bolivianos (ingreso sin IVA menos el costo de la hoja, antes de impuestos), con su margen: de cada 100 Bs cobrados, cuántos quedan después de cubrir ese costo.',
        leer: [
            'Más resultado total no equivale a mejor margen: un producto con margen alto que se vende poco aporta menos que uno con margen normal y mucha venta.',
            'Si un producto se vende mucho y genera poco margen, conviene revisar su precio.',
        ],
    },
    'bi-preguntas.clientes-rentables': {
        titulo: 'Qué clientes aportan más al resultado bruto',
        que: 'Los clientes ordenados por el resultado bruto que generan, no por el volumen de compra.',
        leer: [
            'Un cliente grande con muchos descuentos puede aportar menos que uno mediano que paga el precio normal.',
            'Si el cliente que más compra tiene un margen bajo, conviene revisar sus condiciones comerciales.',
        ],
    },
    'bi-preguntas.descuentos': {
        titulo: 'Cómo influyen los descuentos y los precios manuales',
        que: 'El monto descontado en las ventas del periodo, qué vendedores otorgan más descuentos y cuánto margen se pierde por ello. También cuánto quedó por debajo del precio sugerido en las líneas con precio ingresado manualmente.',
        leer: [
            'Compara el margen de las ventas con descuento frente a las que no lo tienen: la diferencia es el costo de descontar.',
            'El precio sugerido es el que calcula el sistema con el margen del tipo de proyecto.',
        ],
    },
    'bi-preguntas.margen-bajo': {
        titulo: 'Ventas clasificadas con margen bajo',
        que: 'Las ventas que quedaron en amarillo o en rojo según el semáforo de margen, por vendedor.',
        leer: [
            'Rojo: después de impuestos queda menos del 15 % del costo. Amarillo: entre 15 % y 30 %. Rojo no significa necesariamente pérdida.',
            'Muchas ventas en rojo de un mismo vendedor suelen indicar exceso de descuentos o precios ingresados manualmente.',
        ],
    },
    'bi-preguntas.materia-prima': {
        titulo: 'Si aumenta el precio de los materiales',
        que: 'Cómo cambiarían el margen y el resultado si los materiales suben 5 %, 10 % o 20 %, y cuánto habría que ajustar los precios para mantener el margen.',
        leer: [
            '"Ajuste de precios estimado" es lo justo para mantener el margen: como el material es solo una parte del costo, el precio sube en menor proporción que el material.',
            'La fila "A 3 meses" usa la tendencia actual del precio de los materiales.',
        ],
        ojo: 'Es una simulación: supone que se vende lo mismo que en el periodo.',
    },
    'bi-preguntas.cobranza': {
        titulo: 'Cuánto se cobró y cuánto falta recibir',
        que: 'De lo vendido en pedidos del periodo, cuánto ya ingresó a caja y cuánto falta cobrar, según la antigüedad del pedido.',
        leer: [
            'Lo que tiene más de 90 días es lo más difícil de cobrar: debe atenderse primero.',
            'La ganancia que no se cobra no es dinero disponible.',
        ],
    },
    'bi-preguntas.decisiones': {
        titulo: 'Qué acciones revisar primero',
        que: 'Las acciones que surgen de las demás preguntas, ordenadas por el monto que involucran en un periodo como este.',
        leer: [
            '"Qué representa el monto" indica qué es cada cifra: naranja, un costo que ya ocurrió o un saldo pendiente; rojo, ventas o descuentos en riesgo; verde, una estimación; azul, una diferencia frente a una referencia.',
            '"Ver pregunta" lleva a los datos de los que sale cada acción.',
            'Los montos no se suman entre sí, porque algunas acciones se superponen (por ejemplo, descuentos y margen bajo).',
        ],
        ojo: 'Los escenarios son simulaciones y la antigüedad de los cobros se cuenta desde la fecha del pedido, no desde el vencimiento.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Demanda y ventas
     * ---------------------------------------------------------------- */
    'bi.proyeccion-demanda': {
        titulo: '¿Cuántos pedidos podrían recibirse?',
        que: 'Cuántos pedidos hubo cada mes y, a partir de ese historial, cuántos podrían llegar en los próximos meses.',
        leer: [
            'La línea con relleno son los pedidos registrados de cada mes.',
            'La línea punteada, en la zona sombreada, es la estimación. La franja que la rodea es el rango probable: 8 de cada 10 veces el valor real cae dentro de ella.',
            '"Mes incompleto" marca el mes en curso: su valor es parcial y no entra en el cálculo, por eso la estimación pasa por encima de él.',
            'El recuadro de la derecha dice qué tan confiable es la estimación: el ajuste de la tendencia y cuánto se equivocó al probarla con meses ya ocurridos.',
            'Con "Mostrar promedio de los últimos 3 meses" aparece una línea que suaviza los meses atípicos.',
        ],
        ojo: 'Es un cálculo basado en la tendencia, no una certeza. Ante un hecho fuera de lo común (una campaña, un cliente nuevo importante) conviene tomarlo con cautela. En "Cómo se calcula" está el detalle del método.',
    },
    'bi.productos-mas-vendidos': {
        titulo: 'Productos con mayor monto aprobado',
        que: 'Los productos que más ingresos generaron en cotizaciones aprobadas dentro del periodo.',
        leer: [
            'La barra más larga es el producto con mayor monto aprobado del periodo.',
            'Se ven los 5 primeros; "Ver los 10 productos" muestra el ranking completo.',
            'Pase el cursor sobre una barra para ver el monto exacto, o use "Tabla" para ver todos los importes.',
        ],
        ojo: 'Si un producto que antes se vendía bien ya no aparece, conviene analizar el motivo.',
    },
    'bi.categorias-mas-vendidas': {
        titulo: 'Categorías con mayor monto aprobado',
        que: 'Lo mismo que los productos, pero agrupados por categoría.',
        leer: [
            'Indica en qué rubros se concentran los ingresos, más allá de un producto en particular.',
            'Se ven las 5 primeras; "Ver las categorías" muestra todas las del ranking.',
        ],
    },
    'bi.clientes-top': {
        titulo: 'Clientes con mayor monto aprobado',
        que: 'Los clientes con mayor monto de compra en el periodo, sumando sus cotizaciones aprobadas.',
        leer: [
            'Cada fila muestra el monto aprobado, la cantidad de compras, cuándo fue su última compra y su parte del total.',
            '"vs. periodo anterior" compara con un periodo de igual duración inmediatamente anterior. "Nuevo" indica que antes no había comprado.',
            'En amarillo, los clientes sin compras desde hace más de 60 días; el recuadro "Revisar seguimiento" los nombra aunque estén fuera de los 5 primeros.',
            'Se ven los 5 primeros; "Ver los 10 principales" muestra el resto del ranking y a los demás clientes juntos en "Otros".',
        ],
        ojo: 'Respeta las fechas del filtro y las sucursales a las que tiene acceso. Una variación muy alta puede deberse a que en el periodo anterior todavía no había datos.',
    },
    'bi.clientes-pareto': {
        titulo: '¿Qué tan concentradas están las ventas?',
        que: 'Qué parte del monto aprobado la hacen los clientes principales y qué parte el resto.',
        leer: [
            'La barra se divide en dos: los clientes principales del ranking y todos los demás.',
            'Debajo se indica cuántos clientes hacen falta para llegar al 80 % del monto aprobado.',
            '"Ver la curva cliente por cliente" abre el gráfico con todos los clientes, de mayor a menor.',
        ],
        ojo: 'Si pocos clientes concentran el 80 %, la dependencia de ellos es alta: perder uno tiene un impacto importante. Conviene cuidarlos y, al mismo tiempo, ampliar la cartera de clientes.',
    },
    'bi.clientes-curva': {
        titulo: 'Monto aprobado cliente por cliente',
        que: 'Todos los clientes que compraron en el periodo, de mayor a menor, con una línea que muestra el porcentaje acumulado de las ventas.',
        leer: [
            'Cada barra es el monto aprobado de un cliente (el nombre completo aparece al pasar el cursor).',
            'La línea es el porcentaje acumulado: en el cliente 5, por ejemplo, indica cuánto suman los 5 primeros.',
            'La línea punteada marca el 80 %.',
            'Si hay muchos clientes, a partir del puesto 30 se agrupan en una sola barra.',
        ],
    },
    'bi.estacionalidad': {
        titulo: 'Pedidos por mes y año',
        que: 'Cuántos pedidos hay en cada mes del año, con una línea por año (2024, 2025, 2026…). Usa todo el historial, no las fechas del filtro superior.',
        leer: [
            'Cada año tiene su propia línea: si un mes es alto en todos los años, es una temporada real; si lo es en uno solo, fue algo puntual.',
            'Con "Mostrar promedio de los años" aparece el promedio: los meses en que sube son la temporada alta; en los que baja, la temporada baja.',
            'La marca "en curso" señala el mes actual, que todavía no termina: su valor es parcial.',
            'Haga clic en un año de la leyenda para ocultarlo o mostrarlo. Los meses que aún no llegaron, o anteriores al primer pedido registrado, aparecen vacíos, no en cero.',
        ],
        ojo: 'El promedio necesita al menos dos años y no incluye el mes en curso. Con menos de dos años completos, la historia no alcanza para confirmar una temporada. Sirve para planificar: abastecer material y prever personal antes de la temporada alta.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Alertas
     * ---------------------------------------------------------------- */
    'bi-alertas.anomalias': {
        titulo: 'Anomalías estadísticas',
        que: 'Situaciones inusuales que el sistema detecta automáticamente al revisar los datos cada día a las 6:30: caídas o aumentos bruscos de cotizaciones, ventas con margen muy bajo, vendedores que dejaron de cotizar y clientes importantes que dejaron de comprar.',
        leer: [
            'Es un análisis distinto de las alertas de costo y rentabilidad: sus cantidades no se suman.',
            'Alta: requiere atención hoy (también llega como notificación). Media: revisarla durante la semana.',
            'Una vez revisada, márquela como "Revisada"; si no era relevante, use "Descartar". Los botones de arriba filtran por estado.',
            'No usa el filtro de fechas: muestra lo que encontró la revisión diaria. "Ejecutar análisis estadístico" la corre en el momento.',
        ],
        ojo: 'Que el sistema la marque no significa que haya un error: a veces existe una explicación (un feriado, un aviso del cliente). Para esos casos está "Descartar".',
    },
    'bi-alertas.kpi-activas': {
        titulo: 'Alertas de costo y rentabilidad',
        que: 'Cuántas alertas de costo y rentabilidad hay en el periodo seleccionado: presupuestos con rentabilidad baja, presupuestos con precios viejos, pedidos con sobrecosto y aumentos de material.',
        leer: [
            'Es la suma de las alertas de prioridad alta y media.',
            'No incluye las anomalías estadísticas, que se cuentan aparte en su pestaña.',
        ],
    },
    'bi-alertas.kpi-alta': {
        titulo: 'Prioridad alta',
        que: 'Las alertas que conviene revisar primero: por ejemplo, un presupuesto en rojo a punto de aceptarse o un pedido que consumió el doble del umbral de material.',
        leer: [
            'Si este número no es cero, conviene empezar por aquí.',
            'En la lista aparecen primero, con la etiqueta roja "Alta"; el filtro "Prioridad" deja solo esas.',
        ],
    },
    'bi-alertas.kpi-media': {
        titulo: 'Prioridad media',
        que: 'Alertas para revisar el precio y las condiciones durante la semana: no son urgentes, pero si no se atienden pueden convertirse en un problema.',
        leer: [
            'En la lista aparecen con la etiqueta amarilla "Media".',
        ],
    },
    'bi-alertas.costo-margen': {
        titulo: 'Alertas de costo y rentabilidad',
        que: 'Todo lo que puede estar reduciendo el resultado: presupuestos vigentes con rentabilidad baja, presupuestos pendientes armados con precios viejos, pedidos que usaron más material de lo presupuestado, materiales que subieron de golpe y costos que suben mes tras mes.',
        leer: [
            'Cada fila dice qué hacer, a qué presupuesto o pedido corresponde, las cifras que la disparan y qué revisar.',
            'Están ordenadas por prioridad: primero las altas. El buscador, "Prioridad" y "Tipo" acotan la lista.',
            'El botón de la derecha lleva directamente al presupuesto o pedido.',
            '"Ver criterios del sistema" muestra las cinco reglas, con cuántas alertas dio cada una.',
        ],
        ojo: 'Se calcula al abrir la pantalla, por lo que siempre está actualizado. Una rentabilidad baja no significa necesariamente pérdida.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Costos y materiales
     * ---------------------------------------------------------------- */
    'bi-costos.indice': {
        titulo: 'Evolución y estimación del índice de precios',
        que: 'Un indicador que resume el precio de los materiales en comparación con el inicio del periodo. Parte de 100.',
        leer: [
            '110 significa que los materiales están 10 % más caros que al inicio; 95, que están 5 % más baratos. No es un precio en bolivianos.',
            'La línea punteada, en la zona sombreada, indica hacia dónde iría si se mantiene la tendencia; la franja es el rango probable.',
            'El recuadro de la derecha dice qué tan confiable es la estimación: el ajuste de la tendencia y cuánto se equivocó al probarla con meses ya ocurridos.',
        ],
        ojo: 'Si el índice sube y se sigue cotizando con los precios anteriores, cada trabajo genera menos ganancia.',
    },
    'bi-costos.material': {
        titulo: 'Precio del material seleccionado',
        que: 'El precio de un material, mes a mes, y su estimación. El material se elige en la lista de la derecha (los de mayor consumo primero).',
        leer: [
            'La línea continua es el precio vigente al cierre de cada mes: entre una compra y la siguiente se mantiene.',
            'La punteada es la estimación y la franja, el rango probable.',
        ],
        ojo: 'Es útil antes de comprar: si el precio viene subiendo, puede convenir adelantar la compra.',
    },
    'bi-costos.lectura': {
        titulo: 'Qué significa para este material',
        que: 'El resumen numérico del material elegido en el gráfico de al lado.',
        leer: [
            'Precio actual: el costo vigente por unidad de medida.',
            'Tendencia calculada: cuánto sube o baja por mes, en promedio.',
            'Estimación y rango probable: lo que costaría al final de la estimación si se mantiene la tendencia.',
        ],
        ojo: 'Es una referencia, no una cotización del proveedor. Si la estimación es bastante mayor que el precio actual, conviene revisar las cotizaciones que usan este material.',
    },
    'bi-costos.consumo': {
        titulo: 'Costo del consumo registrado por mes',
        que: 'El monto en bolivianos del material utilizado en los pedidos cada mes, según lo registrado por producción.',
        leer: [
            'La línea con relleno es el costo consumido de cada mes.',
            'La punteada es el costo estimado para los próximos meses si se mantiene la tendencia; el recuadro de arriba dice qué tan confiable es.',
            '"Mes incompleto" marca el mes en curso: su valor es parcial y no entra en el cálculo.',
            'Con "Mostrar promedio de 3 meses" aparece una línea que suaviza los meses atípicos.',
        ],
        ojo: 'Si producción no registra lo que utiliza, el mes aparece bajo aunque haya tenido mucho trabajo.',
    },
    'bi-costos.mas-consumidos': {
        titulo: 'Materiales con mayor costo registrado',
        que: 'Los materiales con mayor costo consumido en el periodo, comparados con lo previsto en las hojas de costos de los presupuestos aprobados.',
        leer: [
            'Cada material tiene dos barras: lo registrado por producción y lo presupuestado.',
            'Se ven los 5 primeros; "Ver los materiales" muestra los 10.',
        ],
        ojo: 'Lo registrado sale de los pedidos del periodo y lo presupuestado, de los presupuestos aprobados en el periodo: pueden no ser los mismos trabajos. Antes de sacar conclusiones, conviene verificarlo.',
    },
    'bi-costos.que-revisar': {
        titulo: 'Qué revisar',
        que: 'Los pasos para entender las diferencias entre el material registrado y el previsto, en orden.',
        leer: [
            'Primero, que el registro de consumos esté completo: si falta, todo lo demás se lee mal.',
            'Después, comparar con las hojas de costos: se indica el material con la mayor diferencia.',
            'Por último, buscar la causa antes de hablar de desperdicio.',
        ],
    },
    'bi-costos.variacion': {
        titulo: 'Materiales con mayor costo adicional calculado',
        que: 'Cuánto costo adicional, en bolivianos, generó el aumento de precio de cada material según lo que se consumió.',
        leer: [
            'La barra más larga es el material cuyo aumento pesó más en los costos.',
            'Se calcula así: (precio final − precio inicial) × cantidad consumida.',
            'Un material que subió mucho pero se usó poco puede tener un costo adicional bajo.',
        ],
        ojo: 'Los primeros son los candidatos para revisar precios o evaluar otros proveedores.',
    },
    'bi-costos.detalle-impacto': {
        titulo: 'Detalle del impacto en costos',
        que: 'Por material: el precio al inicio y al final del periodo, el aumento, la cantidad utilizada y el costo adicional.',
        leer: [
            'Aumento: cuánto cambió el precio, en porcentaje.',
            'Costo adicional: (precio final − precio inicial) × cantidad consumida. En rojo, costo adicional; en verde, ahorro.',
            'Se ven los 5 de mayor impacto; "Ver los materiales" muestra todos los que cambiaron de precio.',
        ],
        ojo: 'Es una estimación, no una pérdida neta, y ya forma parte del costo consumido. Un material que subió pero no se utilizó en el periodo tiene costo adicional cero por ahora, pero se reflejará en el próximo pedido que lo use.',
    },
    'bi-costos.kpi-indice': {
        titulo: 'Aumento del índice de precios',
        que: 'Cuánto más caros (o más baratos) están los materiales al final del periodo en comparación con su precio al inicio.',
        leer: [
            '+6 % significa que, en promedio, los materiales cuestan 6 % más que al inicio.',
            'La flecha indica la tendencia mensual: en rojo si sube (desfavorable), en verde si baja.',
        ],
    },
    'bi-costos.kpi-consumido': {
        titulo: 'Costo del material consumido',
        que: 'El monto en material utilizado en los pedidos del periodo, según lo registrado por producción.',
        leer: [
            'Debajo se indica cuántos materiales distintos tienen consumo registrado.',
        ],
        ojo: 'Si producción no registra el consumo, este número aparece por debajo del gasto real.',
    },
    'bi-costos.kpi-impacto': {
        titulo: 'Costo adicional por variación de precios',
        que: 'El costo adicional (o el ahorro) que generó el cambio de precio de los materiales, según lo utilizado en el periodo.',
        leer: [
            'Se calcula así: (precio final − precio inicial) × cantidad consumida de cada material.',
            'En rojo, costo adicional; en verde, ahorro.',
        ],
        ojo: 'Ya forma parte del costo del material consumido: no se suma a él.',
    },
    'bi-costos.kpi-historial': {
        titulo: 'Materiales con historial de precios',
        que: 'Cuántos materiales tienen al menos dos precios registrados, lo mínimo necesario para ver si suben o bajan.',
        leer: [
            'Cada compra aprobada registra el precio del material.',
            'Debajo se indica cuántos subieron, cuántos bajaron y cuántos siguen igual en el periodo.',
        ],
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Presupuestos y rentabilidad
     * ---------------------------------------------------------------- */
    'bi-rentabilidad.pronostico': {
        titulo: 'Montos cotizados y aprobados por mes',
        que: 'El monto de los presupuestos emitidos cada mes y el de los aprobados por el cliente, con la estimación de lo que se cotizará.',
        leer: [
            'La línea con relleno es todo lo cotizado; la otra, lo aprobado.',
            'La distancia entre ambas es lo cotizado que no se concretó.',
            'La punteada es la estimación de lo cotizado, con su rango probable; el recuadro de arriba dice qué tan confiable es.',
            '"Mes incompleto" marca el mes en curso: su valor es parcial.',
        ],
        ojo: 'La estimación es del monto emitido, no de ventas aseguradas. Si la distancia entre las líneas crece, se está cotizando mucho pero cerrando poco: conviene reforzar el seguimiento a los clientes.',
    },
    'bi-rentabilidad.cantidades': {
        titulo: 'Cantidad de presupuestos por mes',
        que: 'Cuántos presupuestos se emitieron y cuántos se aprobaron cada mes.',
        leer: [
            'Cada mes tiene dos barras: emitidos y aprobados.',
            'Mientras más se parezcan, mayor es la efectividad en el cierre de ventas.',
            'Debajo está la cuenta de la tasa de aprobación: aprobados ÷ (emitidos − pendientes).',
        ],
        ojo: 'Si la barra de aprobados es mucho menor que la de emitidos, conviene contactar a esos clientes antes de que pierdan interés.',
    },
    'bi-rentabilidad.tendencia': {
        titulo: 'Precio y costo promedio por presupuesto',
        que: 'El precio promedio cobrado por presupuesto (sin IVA ni instalación) frente a su costo ajustado promedio. Ambas líneas se calculan sobre los mismos presupuestos: los que tienen hoja de costos.',
        leer: [
            'El espacio entre las dos líneas es, aproximadamente, lo que queda por trabajo antes de impuestos.',
            'Si las líneas se acercan, cada trabajo deja menos. Debajo se indica cuánto sube o baja cada una por mes.',
        ],
        ojo: 'El gráfico no dice por qué: un cambio en el tipo de trabajos que se cotizan también mueve los promedios.',
    },
    'bi-rentabilidad.rentabilidad': {
        titulo: 'Rentabilidad sobre costo (%)',
        que: 'De cada 100 Bs de costo de un trabajo aprobado, cuántos Bs de resultado calculado quedan después de impuestos, mes a mes.',
        leer: [
            'Las líneas de referencia son el semáforo: sobre la verde, rentabilidad adecuada; entre la amarilla y la verde, intermedia; bajo la amarilla, baja.',
            'La parte punteada indica hacia dónde iría si se mantiene la tendencia.',
            'El mes en curso es parcial: su rentabilidad puede cambiar al completarse los registros.',
        ],
        ojo: 'Es un porcentaje sobre el costo, no sobre las ventas. Solo cuentan los presupuestos aprobados con hoja de costos.',
    },
    'bi-rentabilidad.semaforo': {
        titulo: 'Cómo salieron los presupuestos emitidos',
        que: 'El resultado de todos los presupuestos emitidos del periodo según el semáforo del motor de margen, el mismo que se usa al cotizar.',
        leer: [
            'Aceptar (verde): el resultado calculado supera el 30 % del costo.',
            'Revisar precio (amarillo): queda entre el 15 % y el 30 %.',
            'No aceptar (rojo): no llega al 15 %, o hay pérdida.',
            'Sin hoja de costos: presupuestos antiguos o manuales que no se pueden medir.',
        ],
        ojo: 'Muchos presupuestos en amarillo o rojo indican que se está cotizando por debajo de lo conveniente o que los costos subieron.',
    },
    'bi-rentabilidad.utilidad': {
        titulo: 'Resultado calculado por mes',
        que: 'El resultado calculado, en bolivianos, que dejaron los presupuestos aprobados de cada mes.',
        leer: [
            'Cada barra es el resultado del mes: precio sin IVA menos costo ajustado, IT e IUE.',
            'La parte punteada es la estimación para los próximos meses; el recuadro de abajo dice qué tan confiable es.',
        ],
        ojo: 'No es dinero cobrado. Un mes puede tener rentabilidad alta y poco resultado si se vendió poco: conviene leer ambos gráficos juntos.',
    },
    'bi-rentabilidad.que-revisar': {
        titulo: 'Qué revisar',
        que: 'Los pasos sugeridos a partir de lo que muestra la pestaña.',
        leer: [
            'Resultado calculado: precio sin IVA − costo ajustado − IT (3 %) − IUE (25 %), según el motor de margen.',
            'Costo ajustado: el costo de la hoja de costos multiplicado por el factor de complejidad del tipo de proyecto.',
            'Las estimaciones prolongan la tendencia: sirven para planificar, no son metas.',
        ],
    },
    'bi-rentabilidad.kpi-emitidos': {
        titulo: 'Presupuestos emitidos',
        que: 'Cuántos presupuestos se emitieron en el periodo y el monto total que suman.',
        leer: [
            'Incluye todos: aprobados, rechazados, vencidos y pendientes.',
        ],
    },
    'bi-rentabilidad.kpi-aprobacion': {
        titulo: 'Aprobación entre presupuestos decididos',
        que: 'De los presupuestos que ya tienen respuesta (aprobados, rechazados o vencidos), qué porcentaje aprobó el cliente.',
        leer: [
            'Los pendientes no se cuentan: todavía pueden aprobarse.',
            'Debajo se indica cuántos se aprobaron y cuántos pendientes quedaron fuera de la cuenta.',
        ],
        ojo: 'Si este número baja, puede deberse al precio, al tiempo de respuesta o a la falta de seguimiento a los clientes.',
    },
    'bi-rentabilidad.kpi-rentabilidad': {
        titulo: 'Rentabilidad calculada sobre costo',
        que: 'De cada 100 Bs de costo de los trabajos aprobados del periodo, cuántos Bs de resultado calculado quedan, ya descontados los impuestos.',
        leer: [
            'El color sigue el semáforo: verde es adecuado, amarillo intermedio y rojo bajo.',
            'Debajo se indica el resultado calculado total en bolivianos.',
        ],
        ojo: 'Solo cuentan los presupuestos con hoja de costos. Es un porcentaje sobre el costo: sobre las ventas es menor (el recuadro de abajo lo indica).',
    },
    'bi-rentabilidad.kpi-proyectada': {
        titulo: 'Rentabilidad estimada',
        que: 'La rentabilidad estimada al final de la proyección si se mantiene la tendencia de los últimos meses.',
        leer: [
            'El título indica a qué mes corresponde la estimación.',
            'Si indica "Faltan meses con datos", todavía no hay historial suficiente para calcularla.',
        ],
        ojo: 'Es una proyección del modelo, no un compromiso: sirve para saber si la tendencia es al alza o a la baja.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Simulador de decisiones
     * ---------------------------------------------------------------- */
    'bi-simulador.base': {
        titulo: '1. Datos de partida',
        que: 'El punto de partida del simulador: el promedio de los presupuestos aprobados con hoja de costos del periodo (costo, precio sin IVA y cuántos se aprueban por mes).',
        leer: [
            'Puede cambiar cualquier valor para probar un trabajo en particular.',
            'El costo es el costo ajustado, el mismo que registra la cotización (ya incluye el factor de complejidad).',
            '"Cómo definir el precio" permite calcularlo con el margen sugerido del sistema o con el margen mínimo de un tipo de proyecto.',
        ],
        ojo: 'Nada de lo que se cambie aquí se guarda ni modifica las cotizaciones.',
    },
    'bi-simulador.palancas': {
        titulo: '2. Supuestos',
        que: 'Los cambios que quiere probar: variación del costo de materiales, ajuste del precio, descuento al cliente y variación de la cantidad de presupuestos aprobados.',
        leer: [
            'Mueva la barra o escriba el porcentaje en la casilla de la derecha; 0 % deja el valor de partida.',
            'La casilla "Ajustar también el precio" sube o baja el precio en el mismo porcentaje que el material.',
            '"Calcular escenario" muestra el resultado en el paso 3; "Restablecer" vuelve a los datos del periodo y a 0 %.',
        ],
    },
    'bi-simulador.resultado': {
        titulo: '3. Resultado',
        que: 'Cómo quedaría el presupuesto con los supuestos aplicados, calculado con el mismo motor de margen de las cotizaciones.',
        leer: [
            'La insignia de la derecha es la recomendación del sistema: "Aceptar", "Revisar precio" o "No aceptar".',
            'El recuadro de color explica qué pasa con la rentabilidad y cuánto margen queda antes de dejar de recomendar "Aceptar".',
            'La tabla compara la base con el escenario línea por línea; la última columna es la diferencia.',
        ],
        ojo: 'Si aparece "Hay cambios sin calcular", el resultado todavía corresponde al cálculo anterior.',
    },
    'bi-simulador.kpi-resultado': {
        titulo: 'Resultado por presupuesto',
        que: 'Lo que queda de un presupuesto del escenario después de pagar el costo ajustado, el IT y el IUE (resultado calculado).',
        leer: [
            'Es un monto en bolivianos por cada presupuesto, sin IVA.',
            'Debajo se indica cuánto cambia frente a la base.',
        ],
    },
    'bi-simulador.kpi-rentabilidad': {
        titulo: 'Rentabilidad sobre costo',
        que: 'El resultado calculado dividido entre el costo ajustado: cuántos bolivianos quedan por cada 100 de costo.',
        leer: [
            'El color sigue la recomendación del sistema: verde "Aceptar", amarillo "Revisar precio", rojo "No aceptar".',
            'La diferencia con la base se expresa en puntos porcentuales.',
        ],
    },
    'bi-simulador.kpi-mensual': {
        titulo: 'Resultado mensual estimado',
        que: 'El resultado por presupuesto multiplicado por la cantidad de presupuestos aprobados por mes del escenario.',
        leer: [
            'Debajo se indica con cuántos presupuestos al mes se calculó.',
            'Cambia con el supuesto "Variación de presupuestos aprobados".',
        ],
        ojo: 'Depende del volumen supuesto: no es un pronóstico de ventas confirmadas.',
    },
    'bi-simulador.sensibilidad': {
        titulo: 'Rentabilidad si cambia el costo de materiales',
        que: 'Qué pasa con la rentabilidad si el material sube o baja, comparando dos decisiones: mantener el precio o trasladar la variación al cliente.',
        leer: [
            'En el eje inferior está la variación del costo (de −30 % a +60 %); en el lateral, la rentabilidad sobre costo.',
            '"Mantener precio" baja a medida que sube el material.',
            '"Trasladar variación al precio" queda plana: el precio sube en la misma proporción y la rentabilidad se mantiene.',
            'Las líneas punteadas marcan desde dónde el sistema recomienda "Aceptar" y "Revisar precio".',
        ],
        ojo: 'Usa el ajuste y el descuento del escenario calculado; si el escenario cambia el costo, el eje lo marca.',
    },
    'bi-simulador.composicion': {
        titulo: 'Cómo se distribuye el precio sin IVA',
        que: 'En qué se va el precio de un presupuesto: el costo ajustado, los impuestos (IT e IUE) y el resultado calculado.',
        leer: [
            '"Base" es el presupuesto con los datos de partida, sin supuestos.',
            '"Escenario" es cómo quedaría con los supuestos aplicados.',
            'Compare las barras de "Resultado calculado": es lo que queda después de costo e impuestos.',
        ],
        ojo: 'La nota de abajo indica el precio mínimo para que el sistema recomiende "Aceptar" o "Revisar precio" con ese costo.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Resumen semanal
     * ---------------------------------------------------------------- */
    'bi-resumen.semana': {
        titulo: 'Lectura de la semana',
        que: 'Un resumen automático de la última semana cerrada (lunes a domingo), que se genera cada lunes a las 7:00.',
        leer: [
            'La frase grande resume lo más importante de la semana en una línea.',
            'Con "Semana analizada" se abren semanas anteriores; "Resumen histórico" avisa que no es la última semana cerrada.',
            'Si tiene acceso a varias sucursales, hay un resumen de toda la empresa y uno por sucursal: se cambia con "Sucursal".',
            'Arriba a la derecha se indica si lo redactó la IA o la plantilla automática; las cifras son las mismas en ambos casos.',
        ],
        ojo: 'Aunque lo redacte la IA, no inventa cifras: cada número se verifica contra los datos antes de publicarse.',
    },
    'bi-resumen.tarjetas': {
        titulo: 'Indicadores clave de la semana',
        que: 'Las cuatro cifras que más pesan en la semana: lo vendido (pedidos nuevos), lo cobrado (pagos recibidos), lo presupuestado (cotizaciones emitidas) y la parte de los pedidos entregados que llegó a tiempo.',
        leer: [
            'El porcentaje compara la semana con el promedio de las 4 semanas anteriores; debajo está ese promedio.',
            'Verde: se movió en la dirección buena; rojo, en la mala. El ícono toma color cuando el cambio llega al 10 %.',
            'En "Entregas a tiempo", debajo se indica cuántos pedidos se entregaron en total.',
        ],
    },
    'bi-resumen.destacados': {
        titulo: 'Resultados favorables',
        que: 'Los indicadores que se movieron en la dirección buena durante la semana.',
        leer: [
            'En negrita, el indicador y hacia dónde se movió; debajo, la frase del resumen.',
            'Al pasar el cursor sobre un punto, se resaltan en la tabla de indicadores las cifras de las que proviene.',
        ],
    },
    'bi-resumen.riesgos': {
        titulo: 'Qué requiere atención',
        que: 'Los resultados negativos o lo que puede convertirse en un problema si no se atiende.',
        leer: [
            'Ícono rojo: prioridad alta, conviene atenderlo esta semana. Ícono naranja: prioridad media, requiere seguimiento.',
            'La flecha indica si el indicador subió o bajó.',
            'Al pasar el cursor sobre un punto, se resaltan en la tabla los indicadores que lo explican.',
        ],
    },
    'bi-resumen.recomendaciones': {
        titulo: 'Acciones sugeridas para el seguimiento',
        que: 'Qué conviene hacer esta semana a partir de los resultados de la anterior.',
        leer: [
            'En negrita la acción; debajo, su justificación.',
            'El botón de la derecha abre la pantalla donde se puede hacer (solo si tiene acceso a ella).',
        ],
        ojo: 'Son sugerencias para analizar con el equipo, no instrucciones: el contexto que no reflejan los números lo conoce usted.',
    },
    'bi-resumen.indicadores': {
        titulo: 'Indicadores de la semana',
        que: 'Las cifras de la semana en las que se basa el resumen: presupuestos, ventas, monto promedio por presupuesto, rentabilidad, cobranza, entregas y costo de material.',
        leer: [
            '"Comparar con" elige la referencia. "4 semanas" (el promedio de las 4 anteriores) es la más confiable, porque una sola semana puede verse alterada por un pedido grande.',
            'La columna del medio muestra el valor de la referencia; la última, el cambio en %.',
            'Verde indica que el indicador se movió en la dirección favorable; rojo, en la desfavorable.',
        ],
        ojo: 'La rentabilidad se expresa sobre el costo y su cambio es relativo (no en puntos). Un menor costo de material suele venir de producir menos: no es ahorro por sí solo.',
    },
    'bi-resumen.contexto': {
        titulo: 'Contexto de las cotizaciones',
        que: 'Información de apoyo para interpretar la semana: qué familias de productos se cotizaron más, qué vendedor presupuestó más y cuántas anomalías siguen sin revisar.',
        leer: [
            'La barra de cada familia es su parte en la suma de las líneas de producto cotizadas, que no coincide exactamente con el monto presupuestado total.',
            'El número de anomalías lleva directamente a la pantalla de Alertas.',
        ],
    },
};
