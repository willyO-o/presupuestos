<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import AvisoMesIncompleto from '@/Components/Reporte/AvisoMesIncompleto.vue';
import BiBloque from '@/Components/Reporte/BiBloque.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiInterruptor from '@/Components/Reporte/BiInterruptor.vue';
import ConfiabilidadPronostico from '@/Components/Reporte/ConfiabilidadPronostico.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import ClienteAvatar from '@/Components/Cliente/ClienteAvatar.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Demanda y ventas, en tres bloques que se leen de arriba abajo:
 *
 * 1. Pedidos futuros: la proyección de pedidos, con su confiabilidad a la
 *    vista (no escondida en "Cómo se calcula").
 * 2. Qué se vende y quién compra: productos, categorías y clientes con mayor
 *    monto aprobado, y cuánto se concentran las ventas.
 * 3. Comportamiento histórico: pedidos de cada mes, año por año.
 *
 * La evolución de precios de materiales vive en "Costos y materiales" (con
 * proyección e impacto); acá queda un enlace con su resumen.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/** Cuántos se ven en los rankings antes de "Ver los N". */
const PRIMEROS = 5;

const verMediaMovil = ref(false);
const verTodosProductos = ref(false);
const verTodasCategorias = ref(false);
const verTodosClientes = ref(false);
const verCurvaClientes = ref(false);
const verPromedioGestiones = ref(false);

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 })}`;
}

function pedidos(value) {
    return Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 1 });
}

function pct(value) {
    return porcentaje(value, 0);
}

/* ----- 1. Pedidos futuros --------------------------------------------------- */

const metodo = computed(() => props.datos.demanda.metodo);
const mesesProyectados = computed(() => props.datos.demanda.proyeccion.length);

/**
 * Pedidos registrados, estimación y su rango; el promedio de 3 meses va al
 * final para que prenderlo no les cambie el color a las demás series (se
 * asignan en orden).
 */
const demanda = computed(() => {
    const historico = props.datos.demanda.serie.map((s) => s.pedidos);
    const futuro = props.datos.demanda.proyeccion;
    const sinFuturo = Array(futuro.length).fill(null);

    return {
        labels: [...props.datos.demanda.serie.map((s) => s.mes), ...futuro.map((p) => p.mes)],
        inicio: futuro[0]?.mes ?? null,
        series: [
            { nombre: 'Pedidos registrados', datos: [...historico, ...sinFuturo], area: true },
            ...seriesProyectadas(
                historico,
                futuro.map((p) => ({ valor: p.pedidos_estimados, inferior: p.inferior, superior: p.superior })),
                metodo.value,
                'Estimación',
            ),
            ...(verMediaMovil.value
                ? [{ nombre: 'Promedio últimos 3 meses', datos: [...props.datos.demanda.media_movil.map((m) => m.valor), ...sinFuturo] }]
                : []),
        ],
    };
});

/** El mes en curso quedó fuera de la recta: se marca para que no se lea como una caída. */
const marcaDemanda = computed(() => (metodo.value?.mes_final_excluido
    ? { etiqueta: metodo.value.mes_final_excluido, texto: 'Mes incompleto' }
    : null));

/* ----- 2. Qué se vende y quién compra ---------------------------------------- */

/** Ranking en barras horizontales: los primeros 5 o los 10 (ya vienen ordenados). */
function ranking(lista, todos) {
    const visibles = todos ? lista : lista.slice(0, PRIMEROS);

    return {
        labels: visibles.map((p) => p.nombre),
        series: [{ nombre: 'Monto aprobado', datos: visibles.map((p) => p.monto) }],
        alto: Math.max(visibles.length * 40 + 40, 160),
    };
}

const productos = computed(() => ranking(props.datos.productos_mas_vendidos, verTodosProductos.value));
const categorias = computed(() => ranking(props.datos.categorias_mas_vendidas, verTodasCategorias.value));

const clientesTop = computed(() => props.datos.clientes_top);

const clientesVisibles = computed(() => (verTodosClientes.value
    ? clientesTop.value.clientes
    : clientesTop.value.clientes.slice(0, PRIMEROS)));

const periodoAnterior = computed(() => {
    const { desde, hasta } = clientesTop.value.periodo_anterior;
    const corto = (ymd) => ymd.split('-').reverse().join('/');

    return `${corto(desde)} al ${corto(hasta)}`;
});

/** "Hoy", "Ayer" o "Hace N días": el número exacto pesa más que "hace meses". */
function haceTanto(dias) {
    if (dias === 0) return 'Hoy';
    if (dias === 1) return 'Ayer';
    return `Hace ${dias} días`;
}

function sinComprarHaceMucho(cliente) {
    return cliente.dias_sin_comprar !== null && cliente.dias_sin_comprar > clientesTop.value.dias_sin_comprar;
}

/** Clientes del ranking (los 10, se vean o no) que hace mucho no compran. */
const seguimiento = computed(() => {
    const lista = clientesTop.value.clientes.filter(sinComprarHaceMucho);

    if (!lista.length) {
        return null;
    }

    if (lista.length === 1) {
        return `${lista[0].nombre} no registra compras desde hace ${lista[0].dias_sin_comprar} días.`;
    }

    const nombres = lista.slice(0, 3).map((c) => `${c.nombre} (${c.dias_sin_comprar} días)`);
    const resto = lista.length > 3 ? ` y ${lista.length - 3} más` : '';
    const juntos = resto ? nombres.join(', ') + resto : `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}`;

    return `${juntos} no registran compras hace más de ${clientesTop.value.dias_sin_comprar} días.`;
});

/** Los primeros contra el resto, en una barra partida (no es un gráfico: es un medidor). */
const concentracion = computed(() => {
    const c = clientesTop.value;

    if (!c.total_clientes) {
        return null;
    }

    const principales = c.clientes.length;
    const otros = c.otros.clientes;
    const corte = c.pareto.clientes_corte;

    return {
        principales,
        otros,
        partePrincipales: otros ? c.concentracion : 100,
        parteOtros: otros ? c.otros.participacion : 0,
        corte,
        porcentajeCorte: c.pareto.corte,
        // Pocos clientes hacen el grueso: 3 de cada 10 o menos.
        dependenciaAlta: corte !== null && c.total_clientes >= 5 && corte / c.total_clientes <= 0.3,
    };
});

/**
 * Curva cliente por cliente (Pareto): barras de mayor a menor y el %
 * acumulado en el eje derecho, con la línea del corte (80 %). Mejor que un
 * pastel: con 40 clientes las porciones no se distinguen.
 */
const paretoClientes = computed(() => {
    const p = clientesTop.value.pareto;

    return {
        labels: p.labels,
        series: p.labels.length
            ? [
                { nombre: 'Monto aprobado', tipo: 'bar', datos: p.montos },
                { nombre: '% acumulado', datos: p.acumulado, eje: 'derecho' },
            ]
            : [],
        umbrales: [{ valor: p.corte, nombre: `${p.corte} % de las ventas`, eje: 'derecho' }],
    };
});

/* ----- 3. Comportamiento histórico ------------------------------------------ */

/**
 * Una línea por gestión sobre el mismo eje Ene–Dic, para ver si un mes es
 * fuerte siempre o solo lo fue un año. El promedio es opcional y va al
 * final (no les cambia el color a las gestiones). Los `null` (meses sin
 * historia o que todavía no llegaron) cortan la línea en vez de bajarla a cero.
 */
const estacionalidad = computed(() => {
    const e = props.datos.estacionalidad;
    const promedio = verPromedioGestiones.value && e.gestiones.length > 1
        ? [{ nombre: 'Promedio', datos: e.promedio, area: true }]
        : [];

    return {
        labels: e.meses,
        series: [...e.gestiones.map((g) => ({ nombre: String(g.anio), datos: g.pedidos })), ...promedio],
    };
});

/** El mes en curso, en el eje Ene–Dic ("Oct" de "Oct-2026"). */
const marcaHistorico = computed(() => {
    const enCurso = props.datos.estacionalidad.mes_en_curso;

    return enCurso ? { etiqueta: enCurso.split('-')[0], texto: `${enCurso} en curso` } : null;
});

/** Con menos de dos años completos no se puede afirmar que un mes sea temporada. */
const historiaCorta = computed(() => props.datos.estacionalidad.gestiones
    .filter((g) => g.pedidos.every((v) => v !== null)).length < 2);

/** Resumen de la evolución de precios para el enlace a Costos y materiales. */
const resumenCostos = computed(() => {
    const materiales = props.datos.evolucion_costos;

    if (!materiales.length) {
        return 'Variación del precio de los materiales, que impacta en el costo y la rentabilidad.';
    }

    const total = materiales.length;
    const suben = materiales.filter((m) => m.puntos.at(-1).precio > m.puntos[0].precio).length;

    let cuantosSuben = `${suben} ${suben === 1 ? 'subió' : 'subieron'}`;

    if (suben === 0) {
        cuantosSuben = total === 1 ? 'no subió' : 'ninguno subió';
    } else if (suben === total) {
        cuantosSuben = total === 1 ? 'subió' : 'todos subieron';
    }

    return `${total} ${total === 1 ? 'material cambió' : 'materiales cambiaron'} de precio en el periodo y ${cuantosSuben}.`
        + ' Su evolución, proyección e impacto en los costos.';
});

const enlaceCostos = computed(() => route('reportes.bi.costos', props.filters.desde || props.filters.hasta
    ? { desde: props.filters.desde, hasta: props.filters.hasta }
    : {}));
</script>

<template>
    <Head title="Demanda y ventas" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters">
            <template v-if="datos.periodo.mes_incompleto" #aviso>
                <AvisoMesIncompleto :mes="datos.periodo.mes_incompleto">Su valor no entra en la estimación.</AvisoMesIncompleto>
            </template>
        </BiEncabezado>

        <!-- ==================== 1. PEDIDOS FUTUROS ==================== -->
        <BiBloque :numero="1" titulo="Pedidos futuros: una referencia para planificar"
            subtitulo="La tendencia de los pedidos por mes y un rango estimado para los próximos meses.">
            <template #accion>
                <BiInterruptor v-model="verMediaMovil" texto="Mostrar promedio de los últimos 3 meses" />
            </template>

            <div class="card">
                <div class="card-header bi-cabecera-con-aviso">
                    <div>
                        <span class="card-title">
                            ¿Cuántos pedidos podrían recibirse en los próximos {{ mesesProyectados || 3 }} meses?
                            <AyudaInfo clave="bi.proyeccion-demanda" />
                        </span>
                        <p class="card-subtitle">
                            Pedidos registrados por mes y estimación
                            <span v-if="!mesesProyectados">· se necesitan al menos 3 meses en el periodo para estimar</span>
                        </p>
                    </div>
                    <ConfiabilidadPronostico :metodo="metodo" />
                </div>
                <div class="card-body">
                    <BaseChart titulo="Pedidos registrados y estimación" :labels="demanda.labels" :series="demanda.series"
                        :formato="pedidos" :alto="300" :inicio-proyeccion="demanda.inicio" :marca-eje="marcaDemanda"
                        enteros vacio="No hay pedidos registrados en el periodo." />
                    <p v-if="mesesProyectados" class="bi-nota">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>El rango muestra la incertidumbre de la estimación: no garantiza el resultado.</span>
                    </p>
                    <MetodoPronostico :metodo="metodo" />
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 2. QUÉ SE VENDE Y QUIÉN COMPRA ==================== -->
        <BiBloque :numero="2" titulo="Qué se vende y quién compra"
            subtitulo="Productos, categorías y clientes que impulsan las ventas del periodo.">
            <div class="bi-demanda-par">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Productos con mayor monto aprobado <AyudaInfo clave="bi.productos-mas-vendidos" /></span>
                            <p class="card-subtitle">
                                {{ verTodosProductos ? `Los ${datos.productos_mas_vendidos.length}` : `Los ${Math.min(datos.productos_mas_vendidos.length, 5)}` }}
                                primeros del periodo
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Barras horizontales: los nombres de producto son largos
                             y en vertical se leerían inclinados o cortados. -->
                        <BaseChart titulo="Productos con mayor monto aprobado" tipo="bar" horizontal :labels="productos.labels"
                            :series="productos.series" :formato="money" :alto="productos.alto"
                            vacio="Sin cotizaciones aprobadas en el periodo." />
                        <button v-if="datos.productos_mas_vendidos.length > 5" type="button" class="bi-ver-mas"
                            :aria-expanded="verTodosProductos" @click="verTodosProductos = !verTodosProductos">
                            <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                            {{ verTodosProductos ? 'Ver solo los 5 primeros' : `Ver los ${datos.productos_mas_vendidos.length} productos` }}
                            <i :class="verTodosProductos ? 'fa-solid fa-chevron-up' : 'fa-solid fa-arrow-right'" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Categorías con mayor monto aprobado <AyudaInfo clave="bi.categorias-mas-vendidas" /></span>
                            <p class="card-subtitle">
                                {{ verTodasCategorias ? `Las ${datos.categorias_mas_vendidas.length}` : `Las ${Math.min(datos.categorias_mas_vendidas.length, 5)}` }}
                                primeras del periodo
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Categorías con mayor monto aprobado" tipo="bar" horizontal :labels="categorias.labels"
                            :series="categorias.series" :formato="money" :alto="categorias.alto"
                            vacio="Sin cotizaciones aprobadas en el periodo." />
                        <button v-if="datos.categorias_mas_vendidas.length > 5" type="button" class="bi-ver-mas"
                            :aria-expanded="verTodasCategorias" @click="verTodasCategorias = !verTodasCategorias">
                            <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                            {{ verTodasCategorias ? 'Ver solo las 5 primeras' : `Ver las ${datos.categorias_mas_vendidas.length} categorías` }}
                            <i :class="verTodasCategorias ? 'fa-solid fa-chevron-up' : 'fa-solid fa-arrow-right'" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <p class="bi-nota bi-nota-franja">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>
                    Los rankings comparan el monto aprobado (cotizaciones aprobadas o convertidas en pedido), no cantidades
                    vendidas ni dinero cobrado.
                </span>
            </p>

            <div class="bi-demanda-clientes">
                <section class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Clientes con mayor monto aprobado <AyudaInfo clave="bi.clientes-top" /></span>
                            <p class="card-subtitle">
                                {{ clientesTop.total_clientes }}
                                {{ clientesTop.total_clientes === 1 ? 'cliente compró' : 'clientes compraron' }} en el periodo
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Tabla y no un gráfico: los logos van como <img> en HTML
                             (nítidos, accesibles); en el canvas de ECharts serían
                             frágiles y no saldrían en la vista de tabla. -->
                        <div v-if="clientesTop.clientes.length" class="table-responsive">
                            <table class="panorama-ranking bi-clientes-tabla">
                                <thead>
                                    <tr>
                                        <th class="panorama-ranking-puesto">#</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Monto aprobado</th>
                                        <th class="text-end bi-col-opcional">Compras</th>
                                        <th>Última compra</th>
                                        <th class="text-end bi-col-opcional" :title="`Frente al periodo anterior (${periodoAnterior})`">vs. periodo anterior</th>
                                        <th class="text-end bi-col-opcional">Parte del total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(c, i) in clientesVisibles" :key="c.id">
                                        <td class="panorama-ranking-puesto">{{ i + 1 }}</td>
                                        <td class="panorama-ranking-nombre">
                                            <span class="cliente-celda">
                                                <ClienteAvatar :nombre="c.nombre" :logo-url="c.logo_url" tamano="sm" />
                                                <span :title="c.nombre">{{ c.nombre }}</span>
                                            </span>
                                        </td>
                                        <td class="text-end financiero-num fw-semibold">{{ money(c.monto) }}</td>
                                        <td class="text-end financiero-num bi-col-opcional">{{ c.compras }}</td>
                                        <td class="bi-cliente-ultima" :class="{ 'is-alerta': sinComprarHaceMucho(c) }">
                                            <i v-if="sinComprarHaceMucho(c)" class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                            {{ c.dias_sin_comprar === null ? '—' : haceTanto(c.dias_sin_comprar) }}
                                        </td>
                                        <td class="text-end bi-col-opcional">
                                            <span class="bi-cliente-variacion"
                                                :class="c.variacion === null ? 'is-nuevo' : c.variacion >= 0 ? 'is-sube' : 'is-baja'">
                                                <template v-if="c.variacion === null">Nuevo</template>
                                                <template v-else>
                                                    <i :class="c.variacion >= 0 ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-arrow-trend-down'"
                                                        aria-hidden="true"></i>
                                                    {{ variacion(c.variacion) }}
                                                </template>
                                            </span>
                                        </td>
                                        <td class="text-end financiero-num bi-col-opcional">{{ porcentaje(c.participacion) }}</td>
                                    </tr>
                                    <tr v-if="verTodosClientes && clientesTop.otros.clientes" class="bi-clientes-otros">
                                        <td class="panorama-ranking-puesto"></td>
                                        <td>
                                            Otros {{ clientesTop.otros.clientes }}
                                            {{ clientesTop.otros.clientes === 1 ? 'cliente' : 'clientes' }}
                                        </td>
                                        <td class="text-end financiero-num">{{ money(clientesTop.otros.monto) }}</td>
                                        <td class="bi-col-opcional"></td>
                                        <td></td>
                                        <td class="bi-col-opcional"></td>
                                        <td class="text-end financiero-num bi-col-opcional">{{ porcentaje(clientesTop.otros.participacion) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="chart-empty">Sin cotizaciones aprobadas en el periodo.</p>

                        <div v-if="clientesTop.clientes.length > 5 || seguimiento" class="bi-clientes-pie">
                            <button v-if="clientesTop.clientes.length > 5" type="button" class="btn btn-sm btn-soft-primary"
                                :aria-expanded="verTodosClientes" @click="verTodosClientes = !verTodosClientes">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                {{ verTodosClientes ? 'Ver solo los 5 primeros' : `Ver los ${clientesTop.clientes.length} principales` }}
                            </button>
                            <p v-if="seguimiento" class="bi-clientes-seguimiento">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                <span><strong>Revisar seguimiento:</strong> {{ seguimiento }}</span>
                            </p>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header">
                        <span class="card-title">¿Qué tan concentradas están las ventas? <AyudaInfo clave="bi.clientes-pareto" /></span>
                    </div>
                    <div v-if="concentracion" class="card-body bi-concentracion">
                        <div class="bi-concentracion-barra" role="img"
                            :aria-label="`${concentracion.principales} clientes principales: ${porcentaje(concentracion.partePrincipales)}; otros ${concentracion.otros}: ${porcentaje(concentracion.parteOtros)}`">
                            <span class="bi-concentracion-principales" :style="{ width: `${concentracion.partePrincipales}%` }">
                                <template v-if="concentracion.partePrincipales >= 15">{{ porcentaje(concentracion.partePrincipales) }}</template>
                            </span>
                            <span v-if="concentracion.otros" class="bi-concentracion-otros" :style="{ width: `${concentracion.parteOtros}%` }">
                                <template v-if="concentracion.parteOtros >= 15">{{ porcentaje(concentracion.parteOtros) }}</template>
                            </span>
                        </div>
                        <ul class="bi-concentracion-leyenda">
                            <li>
                                <span class="bi-concentracion-punto bi-concentracion-principales" aria-hidden="true"></span>
                                {{ concentracion.otros ? `${concentracion.principales} clientes principales` : `Los ${concentracion.principales} clientes` }}:
                                <strong>{{ porcentaje(concentracion.partePrincipales) }}</strong>
                            </li>
                            <li v-if="concentracion.otros">
                                <span class="bi-concentracion-punto bi-concentracion-otros" aria-hidden="true"></span>
                                Otros {{ concentracion.otros }} {{ concentracion.otros === 1 ? 'cliente' : 'clientes' }}:
                                <strong>{{ porcentaje(concentracion.parteOtros) }}</strong>
                            </li>
                        </ul>
                        <p v-if="concentracion.corte" class="bi-concentracion-dato">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>
                                <strong>{{ concentracion.corte }} de {{ clientesTop.total_clientes }} clientes</strong> concentran el
                                <strong>{{ concentracion.porcentajeCorte }} %</strong> del monto aprobado.
                            </span>
                        </p>
                        <p v-if="concentracion.dependenciaAlta" class="bi-sugerencia">
                            <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                            <span>Pocos clientes hacen la mayor parte de las ventas: conviene cuidar la relación con ellos y ampliar la base de compradores.</span>
                        </p>
                        <button v-if="paretoClientes.labels.length" type="button" class="bi-ver-mas"
                            :aria-expanded="verCurvaClientes" @click="verCurvaClientes = !verCurvaClientes">
                            <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                            {{ verCurvaClientes ? 'Ocultar la curva por cliente' : 'Ver la curva cliente por cliente' }}
                            <i :class="verCurvaClientes ? 'fa-solid fa-chevron-up' : 'fa-solid fa-arrow-down'" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div v-else class="card-body">
                        <p class="chart-empty">Sin cotizaciones aprobadas en el periodo.</p>
                    </div>
                </section>
            </div>

            <div v-if="verCurvaClientes" class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">Monto aprobado cliente por cliente <AyudaInfo clave="bi.clientes-curva" /></span>
                        <p class="card-subtitle">
                            De mayor a menor, con el % acumulado
                            <span v-if="clientesTop.pareto.agrupados"> · desde el puesto {{ clientesTop.pareto.labels.length }} van juntos</span>
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart titulo="Monto aprobado cliente por cliente" :labels="paretoClientes.labels"
                        :series="paretoClientes.series" :formato="money" :formato-derecho="pct"
                        :umbrales="paretoClientes.umbrales" :alto="340" etiquetas-inclinadas
                        vacio="Sin cotizaciones aprobadas en el periodo." />
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 3. COMPORTAMIENTO HISTÓRICO ==================== -->
        <BiBloque :numero="3" titulo="Comportamiento histórico"
            subtitulo="Los pedidos de cada mes, año por año, para identificar temporadas.">
            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title bi-titulo-etiqueta">
                            Pedidos por mes y año <AyudaInfo clave="bi.estacionalidad" />
                            <span class="badge badge-soft-secondary">Historial completo · No usa el filtro de fechas</span>
                        </span>
                        <p class="card-subtitle">
                            Una línea por año
                            <span v-if="datos.estacionalidad.desde"> · datos desde {{ datos.estacionalidad.desde }}</span>
                            <span v-if="datos.estacionalidad.gestiones_ocultas"> · se grafican los 5 años más recientes</span>
                        </p>
                    </div>
                    <BiInterruptor v-if="datos.estacionalidad.gestiones.length > 1" v-model="verPromedioGestiones"
                        texto="Mostrar promedio de los años" />
                </div>
                <div class="card-body">
                    <BaseChart titulo="Pedidos por mes y año" :labels="estacionalidad.labels"
                        :series="estacionalidad.series" :formato="pedidos" :alto="280" :marca-eje="marcaHistorico" enteros
                        vacio="Todavía no hay pedidos registrados." />
                    <p class="bi-nota">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>
                            Los meses sin datos no se muestran como cero.
                            <template v-if="historiaCorta">
                                Todavía hay menos de dos años completos: la historia no alcanza para confirmar una temporada.
                            </template>
                        </span>
                    </p>
                </div>
            </div>

            <Link :href="enlaceCostos" class="card bi-enlace">
                <span class="bi-enlace-icono" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                <span class="bi-enlace-texto">
                    <strong>Evolución de precios de materiales</strong>
                    <span>{{ resumenCostos }}</span>
                </span>
                <span class="bi-enlace-accion">Ver en Costos y materiales <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </Link>
        </BiBloque>
    </div>
</template>
