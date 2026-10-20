/**
 * Textos de ayuda de los cuadros de información del panel —gráficos,
 * tablas, indicadores y listas—: lo que muestra el popup del ícono "i"
 * (`Components/AyudaInfo.vue`) al lado del título.
 *
 * Están escritos para quien usa el sistema todos los días, no para un
 * analista: en castellano de acá, con palabras de todos los días ("harto",
 * "nomás", "caseros", "chamba") y sin términos de estadística. Para los
 * montos se dice "dinero" o "ingresos", nunca "plata" (pedido del usuario).
 * Si un cuadro cambia lo que calcula, se corrige su texto AQUÍ: una ayuda
 * que dice otra cosa que el cuadro es peor que no tener ayuda.
 *
 * Forma de cada entrada:
 * - `titulo`: el nombre del cuadro, como en la tarjeta.
 * - `que`:    qué muestra, en una o dos frases.
 * - `leer`:   cómo leerlo, de dos a cuatro pistas cortas.
 * - `ojo`:    (opcional) una advertencia o un consejo práctico.
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
        que: 'Cuánto dinero sumaron las cotizaciones aprobadas en cada mes de este año, al lado de lo que se vendió el año pasado en el mismo mes.',
        leer: [
            'Si la línea de este año va por encima de la del año pasado, nos está yendo mejor que el año anterior.',
            'Los meses que todavía no llegaron quedan vacíos, no en cero.',
            'Pasa el mouse por un punto para ver el monto exacto del mes.',
        ],
        ojo: 'Es lo aprobado, no lo cobrado: el dinero que ya entró a caja la ves en Pagos.',
    },
    'dashboard.pedidos-por-etapa': {
        titulo: 'Pedidos por etapa',
        que: 'Cuántos pedidos hay ahorita en producción y en qué etapa está cada uno: diseño, elaboración, acabado o control de calidad.',
        leer: [
            'La barra más larga es la etapa donde más pedidos se están juntando.',
            'El porcentaje dice qué parte de todo lo que está en producción está en esa etapa.',
            'Abajo tienes cuántos pedidos ya se entregaron en total.',
        ],
        ojo: 'Si una etapa se llena harto, ahí está la trancadera: conviene darle una mano a esa área antes de que se atrasen las entregas.',
    },
    'dashboard.productos-mas-vendidos': {
        titulo: 'Productos más vendidos',
        que: 'Los productos que más ingresos generaron en los últimos 12 meses, en cotizaciones aprobadas.',
        leer: [
            'El número de la izquierda es el puesto en el ranking: el 1 es el que más vende.',
            '"uds." son las unidades vendidas y el porcentaje es cuánto pesa ese producto en todo lo vendido.',
            'La barra compara cada producto contra el primero del ranking.',
        ],
        ojo: 'El orden va por ingresos, no por cantidad: un producto puede salir harto en unidades y estar más abajo si es baratito.',
    },

    /* ------------------------------------------------------------------
     * Reporte financiero
     * ---------------------------------------------------------------- */
    'financiero.ventas-por-mes': {
        titulo: 'Ventas por mes',
        que: 'Cuánto dinero sumaron las cotizaciones aprobadas (y las que ya pasaron a pedido) en cada mes del periodo que elegiste arriba.',
        leer: [
            'Los picos son los meses de más venta; los bajones, los más flojos.',
            'Si cambias las fechas del filtro y le das a "Aplicar", el gráfico se ajusta.',
        ],
        ojo: 'Es lo vendido, no lo cobrado. Lo que todavía nos deben está en "Cuentas por cobrar".',
    },
    'financiero.ventas-por-sucursal': {
        titulo: 'Ventas por sucursal',
        que: 'Cuánto vendió cada sucursal en el periodo elegido.',
        leer: [
            'La barra más larga es la sucursal que más vende.',
            'Pasa el mouse por una barra para ver el monto exacto.',
        ],
        ojo: 'Si solo ves tu sucursal es porque tu cuenta está limitada a ella: fíjate en la etiqueta de arriba a la derecha.',
    },
    'financiero.margen-por-pedido': {
        titulo: 'Margen real por pedido',
        que: 'Para cada pedido terminado: lo que se cobró menos lo que de verdad costó el material que se usó. Es lo que realmente nos quedó en el bolsillo.',
        leer: [
            'Verde, hacia la derecha: ese pedido nos dejó ganancia.',
            'Rojo, hacia la izquierda: ese pedido costó más de lo que se cobró, salimos perdiendo.',
            'Van ordenados del peor al mejor, así lo primero que ves es lo que hay que revisar.',
        ],
        ojo: 'Si producción no cargó el material que usó en un pedido, su margen sale inflado. Pídeles que lo registren para que la cuenta salga bien.',
    },
    'financiero.total-vendido': {
        titulo: 'Total vendido en el periodo',
        que: 'La suma de todas las cotizaciones aprobadas (y las que ya pasaron a pedido) con fecha dentro del periodo que elegiste arriba.',
        leer: [
            'Es el total de cada cotización tal como se le pasó al cliente.',
            'Cambia apenas mueves las fechas del filtro y le das a "Aplicar".',
        ],
        ojo: 'Vendido no es cobrado: un cliente puede haber aprobado y todavía no haber pagado nada.',
    },
    'financiero.por-cobrar': {
        titulo: 'Cuentas por cobrar',
        que: 'Todo el dinero que los clientes todavía nos deben: lo que falta pagar de los pedidos que no se cancelaron.',
        leer: [
            'Es la suma de los saldos: total del pedido menos lo que ya pagaron.',
            'No depende de las fechas del filtro: es lo que se debe a hoy, sea del pedido que sea.',
        ],
        ojo: 'El detalle pedido por pedido, con cuántos días lleva cada deuda, sale en el PDF del reporte.',
    },
    'financiero.rentabilidad-tabla': {
        titulo: 'Rentabilidad real por pedido',
        que: 'Los mismos pedidos del gráfico de margen, pero con los números completos: cuánto se cobró, cuánto costó el material y cuánto quedó.',
        leer: [
            'Ingreso: lo que vale el pedido. Costo real: el material que producción registró como usado.',
            'Margen: la diferencia. En verde si ganamos, en rojo si perdimos; entre paréntesis, qué parte del ingreso es ganancia.',
            'Entran los pedidos en acabado o ya entregados, sin importar la fecha del filtro.',
        ],
        ojo: 'Un costo real de Bs 0 casi siempre quiere decir que no se cargó el consumo, no que el material salió gratis.',
    },

    /* ------------------------------------------------------------------
     * Reporte de producción
     * ---------------------------------------------------------------- */
    'produccion.duracion-por-etapa': {
        titulo: 'Duración promedio por etapa',
        que: 'Cuántos días, en promedio, se queda un trabajo en cada etapa del taller antes de pasar a la siguiente.',
        leer: [
            'La barra más larga es la etapa más lenta: ahí es donde se tranca la cosa.',
            'Las etapas que no aparecen todavía no tienen registros para medir.',
        ],
        ojo: 'Solo se cuentan las etapas que el taller abrió y cerró en el sistema. Si no se registra, no hay cómo medir.',
    },
    'produccion.carga-por-area': {
        titulo: 'Carga de trabajo por área',
        que: 'Cuántos trabajos tiene cada área ahorita, todavía sin terminar.',
        leer: [
            'El área con la barra más larga es la que está con más chamba en este momento.',
            'Es una foto de ahora: cambia apenas se cierra o se asigna una etapa.',
        ],
        ojo: 'Sirve para repartir mejor la chamba: si un área está a full y otra tranquila, se puede mover gente o reordenar prioridades.',
    },
    'produccion.cumplimiento': {
        titulo: 'Cumplimiento de entregas',
        que: 'De los pedidos que ya se entregaron, cuántos llegaron en la fecha que le prometimos al cliente o antes.',
        leer: [
            'El porcentaje grande es la parte que llegó a tiempo; abajo ves cuántos son de cuántos.',
            'Solo cuentan los pedidos que tenían fecha estimada y fecha real de entrega.',
            'Es de toda la historia, no de un mes en particular.',
        ],
        ojo: 'Cada entrega tarde es un caserito que puede no volver: el PDF del reporte trae la lista de los atrasados.',
    },
    'produccion.pedidos-activos': {
        titulo: 'Pedidos activos por etapa',
        que: 'Cuántos pedidos hay ahorita en cada etapa, antes de la entrega.',
        leer: [
            'Cada número es la cantidad de pedidos que están en esa etapa en este momento.',
            'Si un pedido tiene varios trabajos, cuenta en la etapa del que va más atrás.',
        ],
        ojo: 'Si se amontonan en una etapa, ahí se está trancando la producción.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Demanda y ventas
     * ---------------------------------------------------------------- */
    'bi.proyeccion-demanda': {
        titulo: 'Proyección de demanda',
        que: 'Cuántos pedidos tuvimos cada mes y, con esa historia, cuántos podrían llegar en los próximos meses.',
        leer: [
            'La línea con relleno son los pedidos reales que ya pasaron.',
            'La línea gris es el promedio de los últimos 3 meses: suaviza los meses raros para ver mejor hacia dónde vamos.',
            'La línea punteada, en la zona sombreada, es la proyección. La franja a su alrededor es el rango probable: 8 de cada 10 veces el número real cae ahí adentro.',
        ],
        ojo: 'Es un cálculo con la tendencia, no una bola de cristal. Si pasa algo grande (una campaña, un cliente nuevo grande) tómalo con pinzas. Abajo, en "Cómo se calcula", ves qué tan confiable es.',
    },
    'bi.productos-mas-vendidos': {
        titulo: 'Productos más vendidos',
        que: 'Los 10 productos que más ingresos generaron en cotizaciones aprobadas dentro del periodo.',
        leer: [
            'La barra más larga es el producto estrella del periodo.',
            'Pasa el mouse por una barra para ver el monto exacto.',
        ],
        ojo: 'Si un producto que antes vendía harto ya no aparece, vale la pena preguntarse por qué.',
    },
    'bi.categorias-mas-vendidas': {
        titulo: 'Categorías más vendidas',
        que: 'Lo mismo que los productos, pero juntando los productos por categoría.',
        leer: [
            'Te dice en qué rubro están los ingresos, más allá de un producto puntual.',
            'La barra más larga es la categoría que más vende.',
        ],
    },
    'bi.clientes-top': {
        titulo: 'Clientes que más compran',
        que: 'Los 10 clientes que más dinero nos dejaron en el periodo, sumando sus cotizaciones aprobadas, y todos los demás juntos en "Otros".',
        leer: [
            'Arriba a la derecha: cuánto suman los 10 primeros. Si es harto (más de la mitad), dependemos de pocos caseros y perder uno se siente fuerte.',
            'Cada cliente muestra cuánto compró, cuántas veces, qué parte del total es y si compró más o menos que en un periodo igual de largo justo antes. "Nuevo" quiere decir que antes no había comprado.',
            'En amarillo, los que hace más de 60 días que no compran: son los primeros a los que conviene llamar.',
            'Si el cliente tiene logo cargado se ve el logo; si no, sus iniciales. El logo se sube desde Clientes.',
        ],
        ojo: 'Respeta las fechas del filtro y las sucursales que ves. Una variación muy alta puede ser solo porque en el periodo anterior todavía no había datos.',
    },
    'bi.clientes-pareto': {
        titulo: 'Concentración de ventas por cliente',
        que: 'Todos los clientes que compraron en el periodo, del que más al que menos, con una línea que va sumando qué parte de las ventas llevamos.',
        leer: [
            'Cada barra es lo que compró un cliente (su nombre completo sale al pasar el mouse).',
            'La línea es el % acumulado: en el cliente 5, por ejemplo, dice cuánto suman los 5 primeros juntos.',
            'La raya punteada marca el 80 %. Arriba a la derecha ves cuántos clientes hacen falta para llegar ahí.',
            'Si hay muchos clientes, a partir del puesto 30 van juntos en una sola barra.',
        ],
        ojo: 'Si pocos clientes hacen el 80 %, dependemos harto de ellos: hay que cuidarlos y, a la vez, buscar más caseros para no quedar colgados de unos pocos.',
    },
    'bi.evolucion-costos': {
        titulo: 'Evolución del costo de materiales',
        que: 'Cómo fue cambiando el precio de los materiales cada vez que se aprobó una compra.',
        leer: [
            'Si una línea sube, ese material nos está saliendo más caro.',
            'Entre una compra y la siguiente el precio se mantiene, por eso la línea no se corta.',
            'Se muestran 4 materiales nomás para que se entienda; el resto está en "Costos y materiales".',
        ],
        ojo: 'Es de toda la empresa, no de una sucursal. Si el material sube y no actualizamos precios, el margen se nos achica.',
    },
    'bi.estacionalidad': {
        titulo: 'Estacionalidad',
        que: 'Cuántos pedidos tenemos en cada mes del año, con una línea por gestión (2024, 2025, 2026…) y el promedio de todas. Usa toda la historia, no las fechas del filtro de arriba.',
        leer: [
            'La línea con relleno es el promedio: los meses donde sube son la temporada fuerte; donde baja, los meses tranquilos.',
            'Cada gestión tiene su línea: si un mes es alto en todos los años, es temporada de verdad; si es alto en uno solo, fue algo puntual.',
            'Toca un año en la leyenda para ocultarlo o mostrarlo, y así comparar solo las gestiones que quieras.',
            'Los meses que todavía no llegaron, o de antes del primer pedido registrado, quedan vacíos, no en cero.',
        ],
        ojo: 'El promedio aparece cuando hay al menos dos gestiones, y no cuenta el mes en curso porque todavía no termina. Sirve para prepararse: comprar material y tener gente lista antes de la temporada alta.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Alertas
     * ---------------------------------------------------------------- */
    'bi-alertas.anomalias': {
        titulo: 'Anomalías detectadas',
        que: 'Cosas raras que el sistema encuentra solito revisando los datos todos los días a las 6:30: caídas o subidas bruscas de cotizaciones, ventas con margen muy bajo, vendedores que dejaron de cotizar y clientes grandes que dejaron de comprar.',
        leer: [
            'Alta: hay que mirarla hoy (también llega a la campanita). Media: revisarla esta semana.',
            'Cada una dice qué pasó, en qué sucursal o con quién, y qué conviene revisar.',
            'Cuando ya la viste, márcala como "Revisada"; si no era nada, "Descartar". Los botones de arriba filtran por estado.',
        ],
        ojo: 'Que el sistema la marque no quiere decir que esté mal: a veces hay una explicación (feriado, un cliente que avisó). Para eso está "Descartar".',
    },
    'bi-alertas.kpi-activas': {
        titulo: 'Alertas activas',
        que: 'Cuántas alertas de costo y margen hay en el periodo elegido: materiales que subieron harto, presupuestos con poco margen y pedidos que gastaron más material de lo previsto.',
        leer: [
            'Es la suma de las de prioridad alta y media.',
            'El detalle de cada una está en la lista de abajo.',
        ],
    },
    'bi-alertas.kpi-alta': {
        titulo: 'Prioridad alta',
        que: 'Las alertas que piden acción ya: por ejemplo, un presupuesto que se está por cerrar en rojo o un material que subió muy fuerte.',
        leer: [
            'Si este número no es cero, empieza por acá.',
            'En la lista de abajo aparecen con la etiqueta roja "Alta".',
        ],
    },
    'bi-alertas.kpi-media': {
        titulo: 'Prioridad media',
        que: 'Alertas para revisar con calma durante la semana: no son urgentes, pero si se dejan pasar se vuelven un problema.',
        leer: [
            'En la lista de abajo aparecen con la etiqueta amarilla "Media".',
        ],
    },
    'bi-alertas.costo-margen': {
        titulo: 'Alertas de costo y margen',
        que: 'La lista de todo lo que puede estar comiéndose la ganancia: materiales que subieron más del límite, presupuestos vivos con margen bajo, presupuestos pendientes armados con precios viejos, pedidos que usaron más material de lo presupuestado y materiales que vienen subiendo mes tras mes.',
        leer: [
            'Arriba están los límites con los que se calcula cada alerta.',
            'Puedes filtrar por tipo con la lista de la derecha.',
            'El botón "Ver" te lleva directo al presupuesto, pedido o material.',
        ],
        ojo: 'Se calcula al abrir la pantalla, así que siempre está al día. Los precios de material son de toda la empresa.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Costos y materiales
     * ---------------------------------------------------------------- */
    'bi-costos.indice': {
        titulo: 'Proyección de costos futuros',
        que: 'Un solo número que resume cuánto cuestan los materiales comparado con el inicio del periodo. Arranca en 100.',
        leer: [
            '110 quiere decir que los materiales están 10 % más caros que al principio; 95, que están 5 % más baratos.',
            'La parte punteada es hacia dónde iría si sigue la misma tendencia, con su rango probable.',
        ],
        ojo: 'Si el índice sube y seguimos cotizando con los precios de antes, cada trabajo nos deja menos.',
    },
    'bi-costos.material': {
        titulo: 'Proyección por material',
        que: 'El precio de un solo material, mes a mes, y hacia dónde va. El material lo eliges en la lista de arriba.',
        leer: [
            'La línea llena es el precio que tuvo cada mes.',
            'La punteada es la proyección y la franja, el rango probable.',
        ],
        ojo: 'Útil antes de comprar: si el precio viene subiendo, puede convenir adelantar la compra.',
    },
    'bi-costos.consumo': {
        titulo: 'Consumo de materiales',
        que: 'Cuánto dinero en material se usó en los pedidos cada mes, según lo que registró producción.',
        leer: [
            'La línea con relleno es el consumo real de cada mes.',
            'La gris es el promedio de los últimos 3 meses.',
            'La punteada es cuánto se usaría los próximos meses si sigue igual.',
        ],
        ojo: 'Si producción no carga lo que usa, el mes sale bajo aunque se haya trabajado harto.',
    },
    'bi-costos.mas-consumidos': {
        titulo: 'Materiales más consumidos',
        que: 'Los 10 materiales en los que más dinero se gastó en el periodo.',
        leer: [
            'La barra más larga es el material que más nos cuesta.',
            'Si aparece "Presupuestado", compara lo que se calculó en la cotización con lo que de verdad se usó.',
        ],
        ojo: 'Si lo real es harto más que lo presupuestado, se está gastando más material de lo previsto: puede ser desperdicio o que la cotización se quedó corta.',
    },
    'bi-costos.variacion': {
        titulo: 'Impacto de las variaciones de precio',
        que: 'Cuánto subió o bajó el precio de cada material dentro del periodo, en porcentaje.',
        leer: [
            'Rojo, hacia la derecha: el material subió. Eso nos come el margen.',
            'Verde, hacia la izquierda: el material bajó. Bien para nosotros.',
        ],
        ojo: 'Los que más subieron son los primeros candidatos para revisar precios o buscar otro proveedor.',
    },
    'bi-costos.kpi-indice': {
        titulo: 'Índice de costo (fin del periodo)',
        que: 'Cuánto más caros (o más baratos) están los materiales al final del periodo comparado con el primer precio que tuvieron en ese periodo.',
        leer: [
            '+6 % quiere decir que, en promedio, los materiales cuestan 6 % más que al principio.',
            'La flecha dice hacia dónde va mes a mes: en rojo si sube (malo para nosotros), en verde si baja.',
        ],
    },
    'bi-costos.kpi-consumido': {
        titulo: 'Material consumido',
        que: 'Cuánto dinero en material se usó en los pedidos del periodo, según lo que registró producción.',
        leer: [
            'Abajo dice cuántos materiales distintos se usaron.',
            'La flecha es la tendencia mes a mes: subir acá significa gastar más en material.',
        ],
        ojo: 'Si producción no carga el consumo, este número sale más bajo de lo que de verdad se gastó.',
    },
    'bi-costos.kpi-impacto': {
        titulo: 'Impacto de las variaciones',
        que: 'Cuánto dinero de más (o de menos) nos costó que los materiales cambiaran de precio, según lo que se usó en el periodo.',
        leer: [
            'Se calcula así: (precio final − precio inicial) × lo que se consumió de cada material.',
            'En rojo, nos costó más; en verde, nos ahorramos.',
            'Abajo dice cuántos materiales subieron y cuántos bajaron.',
        ],
    },
    'bi-costos.kpi-historial': {
        titulo: 'Materiales con historial',
        que: 'Cuántos materiales tienen al menos dos precios registrados, que es lo mínimo para ver si suben o bajan.',
        leer: [
            'Cada compra aprobada deja registrado el precio del material.',
            'Un material con una sola compra todavía no tiene tendencia.',
        ],
    },
    'bi-costos.lectura': {
        titulo: 'Lectura del material',
        que: 'El resumen en números del material que elegiste en el gráfico de al lado.',
        leer: [
            'Precio actual: lo que cuesta hoy por unidad de medida.',
            'Tendencia: cuánto sube o baja por mes, en promedio.',
            'Precio estimado: cuánto costaría al final de la proyección si sigue igual.',
        ],
        ojo: 'Si el precio estimado está harto más arriba que el actual, conviene revisar las cotizaciones que usan este material.',
    },
    'bi-costos.detalle-impacto': {
        titulo: 'Detalle del impacto en costos',
        que: 'Material por material: con qué precio empezó el periodo, con cuál terminó, cuánto se usó y cuánto dinero significó esa diferencia.',
        leer: [
            'Variación: cuánto cambió el precio, en porcentaje.',
            'Impacto: (precio final − precio inicial) × lo consumido. En rojo nos costó más; en verde, menos.',
        ],
        ojo: 'Un material que subió pero no se usó en el periodo tiene impacto cero hoy, pero se va a sentir en el próximo pedido que lo use.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Presupuestos y rentabilidad
     * ---------------------------------------------------------------- */
    'bi-rentabilidad.pronostico': {
        titulo: 'Pronóstico de presupuestos',
        que: 'El dinero de los presupuestos que se hicieron cada mes y el de los que el cliente aprobó, con la proyección de lo que se va a cotizar.',
        leer: [
            'La línea con relleno es todo lo cotizado; la otra, lo que se aprobó.',
            'La distancia entre las dos es lo que se cotizó y no se cerró.',
            'La punteada es la proyección de lo cotizado, con su rango probable.',
        ],
        ojo: 'Si la distancia crece, se está cotizando harto pero cerrando poco: toca hacer seguimiento a los caseros.',
    },
    'bi-rentabilidad.cantidades': {
        titulo: 'Presupuestos por mes',
        que: 'Cuántos presupuestos se hicieron y cuántos se aprobaron cada mes.',
        leer: [
            'Cada mes tiene dos barras: la de emitidos y la de aprobados.',
            'Mientras más se parezcan, mejor estamos cerrando ventas.',
        ],
        ojo: 'Si la barra de aprobados queda chiquita al lado de la de emitidos, conviene llamar a esos clientes antes de que se enfríen.',
    },
    'bi-rentabilidad.tendencia': {
        titulo: 'Tendencia: ticket vs. costo',
        que: 'Lo que en promedio vale cada presupuesto (el "ticket") contra lo que en promedio cuesta hacerlo.',
        leer: [
            'El espacio entre las dos líneas es, más o menos, lo que se gana en cada trabajo.',
            'Si las líneas se van juntando, cada trabajo nos deja menos.',
        ],
    },
    'bi-rentabilidad.rentabilidad': {
        titulo: 'Rentabilidad por mes',
        que: 'De cada 100 Bs que nos cuesta hacer un trabajo, cuántos Bs de ganancia real quedan después de impuestos, mes a mes.',
        leer: [
            'Las líneas de colores son el semáforo: arriba de la verde, bien; entre la amarilla y la verde, a medias; debajo de la amarilla, estamos ganando poco.',
            'La parte punteada es hacia dónde iría si sigue la tendencia.',
        ],
        ojo: 'Solo cuenta presupuestos aprobados que tienen hoja de costos. Los antiguos, sin hoja, se dejan afuera para no inflar el número.',
    },
    'bi-rentabilidad.utilidad': {
        titulo: 'Utilidad real por mes',
        que: 'La ganancia real, en bolivianos, que dejaron los presupuestos aprobados de cada mes.',
        leer: [
            'Cada barra es lo que quedó limpio ese mes, ya descontados costo e impuestos.',
            'La parte punteada es la proyección para los próximos meses.',
        ],
        ojo: 'Un mes puede tener rentabilidad alta y poca utilidad si se vendió poco: mira los dos gráficos juntos.',
    },
    'bi-rentabilidad.kpi-emitidos': {
        titulo: 'Presupuestos emitidos',
        que: 'Cuántos presupuestos se hicieron en el periodo y cuánto dinero suman entre todos.',
        leer: [
            'Cuenta todos: aprobados, rechazados, vencidos y pendientes.',
            'La flecha dice si cada mes se está cotizando más o menos.',
        ],
    },
    'bi-rentabilidad.kpi-aprobacion': {
        titulo: 'Tasa de aprobación',
        que: 'De los presupuestos que ya tienen respuesta (aprobados, rechazados o vencidos), qué parte aprobó el cliente.',
        leer: [
            'Los pendientes no cuentan: todavía se pueden aprobar.',
            'Abajo ves cuántos se aprobaron y cuántos siguen esperando.',
        ],
        ojo: 'Si este número baja, puede ser el precio, el tiempo de respuesta o que falta hacer seguimiento a los clientes.',
    },
    'bi-rentabilidad.kpi-rentabilidad': {
        titulo: 'Rentabilidad sobre costo',
        que: 'De cada 100 Bs que nos cuesta hacer los trabajos aprobados del periodo, cuántos Bs de ganancia real nos quedan, ya descontados los impuestos.',
        leer: [
            'El color sigue el semáforo: verde está bien, amarillo a medias, rojo es poco.',
            'Abajo está la ganancia total en bolivianos; la flecha, si va mejorando mes a mes.',
        ],
        ojo: 'Solo cuentan los presupuestos con hoja de costos.',
    },
    'bi-rentabilidad.kpi-proyectada': {
        titulo: 'Rentabilidad proyectada',
        que: 'Hasta dónde llegaría la rentabilidad al final de la proyección si sigue la tendencia de los últimos meses.',
        leer: [
            'Abajo dice para qué mes es la estimación.',
            'Si dice "Faltan meses con datos", todavía no hay historia suficiente para calcularla.',
        ],
        ojo: 'Es una estimación, no un compromiso: sirve para ver si vamos para arriba o para abajo.',
    },
    'bi-rentabilidad.semaforo': {
        titulo: 'Semáforo de los presupuestos del periodo',
        que: 'Cómo salieron todos los presupuestos del periodo según el motor de margen, el mismo que se usa al cotizar.',
        leer: [
            'Aceptar (verde): el precio deja buena ganancia.',
            'Revisar precio (amarillo): deja algo, pero menos de lo recomendado.',
            'No aceptar (rojo): con ese precio se gana muy poco o se pierde.',
            'Sin hoja de costos: presupuestos antiguos o manuales que no se pueden medir.',
        ],
        ojo: 'Muchos en amarillo o rojo quiere decir que se está cotizando muy barato o que los costos subieron.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Simulador de decisiones
     * ---------------------------------------------------------------- */
    'bi-simulador.sensibilidad': {
        titulo: 'Sensibilidad al costo de materiales',
        que: '¿Qué pasa con la ganancia si el material sube o baja? Compara dos caminos: aguantar el precio o pasarle el aumento al cliente.',
        leer: [
            'Abajo está cuánto cambia el costo (de -30 % a +30 %); al costado, la rentabilidad que quedaría.',
            '"Manteniendo el precio" se va cayendo a medida que el material sube.',
            '"Trasladando el aumento" muestra qué pasa si subimos el precio en la misma proporción.',
            'Las líneas de colores son el semáforo de rentabilidad.',
        ],
        ojo: 'Es una simulación con los datos que pusiste arriba: no cambia nada en el sistema, juega tranquilo.',
    },
    'bi-simulador.composicion': {
        titulo: 'A dónde va el precio',
        que: 'Cómo se reparte el precio de un presupuesto: cuánto se va en costo, cuánto en impuestos (IT e IUE) y cuánto queda de ganancia.',
        leer: [
            '"Base" es el presupuesto promedio del periodo, tal como está hoy.',
            '"Escenario" es cómo quedaría con los cambios que moviste en el simulador.',
            'Compara las barras de "Utilidad real": esa es la ganancia que de verdad queda.',
        ],
    },
    'bi-simulador.base': {
        titulo: 'Escenario base',
        que: 'El punto de partida del simulador: un presupuesto "promedio" de los que se aprobaron en el periodo, con su costo, su precio y cuántos se aprueban por mes.',
        leer: [
            'Puedes cambiar cualquier número para simular un trabajo en particular.',
            'Si eliges un tipo de proyecto, el precio se calcula con el margen mínimo de ese tipo.',
            'El costo es el costo ajustado, el mismo que guarda la cotización.',
        ],
        ojo: 'Aquí juegas tranquilo: nada de lo que cambies se guarda ni toca las cotizaciones reales.',
    },
    'bi-simulador.palancas': {
        titulo: '¿Qué pasa si…?',
        que: 'Las "perillas" para probar decisiones: que suba o baje el material, subir o bajar el precio, dar un descuento o vender más o menos trabajos.',
        leer: [
            'Mueve las barras y el resultado de la derecha cambia al instante.',
            '"Trasladar al precio" sube el precio en la misma proporción que el material.',
            '"Restablecer" vuelve todo a cero.',
        ],
    },
    'bi-simulador.resultado': {
        titulo: 'Resultado del escenario',
        que: 'Cómo quedaría el presupuesto con los cambios que hiciste, calculado con el mismo motor de margen que las cotizaciones.',
        leer: [
            'El color de arriba es el semáforo: si se puede aceptar ese precio o no.',
            'El mensaje dice cuánto más puede subir el costo sin salir de verde, o cuánto hay que subir el precio para volver a verde.',
            'La tabla compara la base con tu escenario, línea por línea.',
        ],
        ojo: 'El "precio mínimo para verde" es una buena referencia para no cotizar por debajo cuando el material sube.',
    },

    /* ------------------------------------------------------------------
     * Inteligencia de negocios — Resumen semanal
     * ---------------------------------------------------------------- */
    'bi-resumen.semana': {
        titulo: 'Resumen de la semana',
        que: 'Un resumen automático de cómo nos fue la semana pasada, que se arma solito cada lunes a las 7 de la mañana.',
        leer: [
            'La frase grande es lo más importante de la semana en una línea.',
            'Si ves varias sucursales, hay uno para toda la empresa y uno por cada sucursal: cámbialo con los botones de la derecha.',
            'La etiqueta dice si lo redactó la IA o la plantilla automática; los números son los mismos en los dos casos.',
        ],
        ojo: 'Si la IA lo redacta, igual no inventa cifras: cada número se revisa contra los datos antes de publicarse.',
    },
    'bi-resumen.destacados': {
        titulo: 'Lo más destacado',
        que: 'Lo que salió bien la semana pasada.',
        leer: [
            'Pasa el mouse por un punto y se resaltan, en la tabla de la derecha, los indicadores de los que sale.',
        ],
    },
    'bi-resumen.riesgos': {
        titulo: 'Riesgos',
        que: 'Lo que salió mal o puede convertirse en problema si no se atiende.',
        leer: [
            'Alta: atenderlo esta semana sí o sí. Media: tenerlo en el ojo.',
            'Pasa el mouse por un punto para ver en la tabla los indicadores que lo explican.',
        ],
    },
    'bi-resumen.recomendaciones': {
        titulo: 'Recomendaciones para esta semana',
        que: 'Qué conviene hacer esta semana a partir de lo que pasó en la anterior.',
        leer: [
            'En negrita la acción; abajo, el porqué.',
        ],
        ojo: 'Son sugerencias para conversar con el equipo, no órdenes: tú conoces el contexto que los números no ven.',
    },
    'bi-resumen.indicadores': {
        titulo: 'Indicadores de la semana',
        que: 'Los números de los que sale todo el resumen, de la semana pasada: presupuestos, ventas, ticket promedio, rentabilidad, cobranza, entregas y costo de material.',
        leer: [
            '"vs. 4 sem." compara con el promedio de las 4 semanas anteriores: es la comparación que más vale, porque una sola semana puede salir rara por un pedido grande.',
            '"vs. ant." compara con la semana anterior y "vs. año", con la misma semana del año pasado.',
            'Verde quiere decir que se movió para el lado bueno de ese indicador; rojo, para el lado malo.',
        ],
    },
    'bi-resumen.contexto': {
        titulo: 'Contexto',
        que: 'Datos de apoyo para entender la semana: qué familias de productos se cotizaron más, qué vendedor cotizó más y cuántas anomalías siguen sin revisar.',
        leer: [
            'La barra de cada familia es su parte en el total cotizado.',
            'El número de anomalías te lleva directo a la pantalla de Alertas.',
        ],
    },
    'bi-resumen.historial': {
        titulo: 'Semanas anteriores',
        que: 'Los resúmenes de las semanas pasadas, para comparar o volver a leerlos.',
        leer: [
            'Toca una semana para abrir su resumen.',
            'Se abre con la misma sucursal que estás mirando.',
        ],
    },
};
