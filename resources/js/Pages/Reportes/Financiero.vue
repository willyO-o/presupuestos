<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import { userCan } from '@/Directives/Can.js';
import { mesAnio } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Reporte financiero. Recibe el MISMO informe que el PDF
 * (`InformeReportesService::financiero()`), así que cada cifra de esta
 * pantalla coincide con la del papel: el margen excluye los pedidos sin
 * consumo de material registrado, igual que allá.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    sucursales: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// `v-can` oculta un elemento con `hidden` y no aplica a un <template>; acá
// son dos enlaces, así que se decide una vez y con v-if.
const puedeExportar = userCan('reportes.exportar_financiero');

const filtro = reactive({
    desde: props.filters.desde ?? props.datos.periodo.desde,
    hasta: props.filters.hasta ?? props.datos.periodo.hasta,
    sucursal_id: props.filters.sucursal_id ? Number(props.filters.sucursal_id) : '',
});

/** Quien solo ve una sucursal no tiene nada que elegir: el selector se oculta. */
const eligeSucursal = computed(() => props.sucursales.length > 1);

/** Sin `sucursal_id` vacío en la URL: "todas" es la ausencia del filtro. */
function parametros(extra = {}) {
    return Object.fromEntries(
        Object.entries({ ...filtro, ...extra }).filter(([, valor]) => valor !== '' && valor !== null),
    );
}

const actualizando = ref(false);

function aplicar() {
    router.get(route('reportes.financiero'), parametros(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (actualizando.value = true),
        onFinish: () => (actualizando.value = false),
    });
}

/**
 * El PDF sale con los filtros que se ven, aunque todavía no se haya pulsado
 * "Actualizar" (mismo criterio que el informe del BI).
 */
function urlPdf(descargar = false) {
    return route('reportes.financiero.pdf', parametros(descargar ? { descargar: 1 } : {}));
}

/* -----------------------------------------------------------------
 * Formato
 * -------------------------------------------------------------- */

const formatoBs = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function money(valor) {
    const numero = Number(valor ?? 0);

    return `${numero < 0 ? '-' : ''}Bs ${formatoBs.format(Math.abs(numero))}`;
}

/** En el eje el importe va compacto: "Bs 400 mil" no se pisa con el de al lado. */
function moneyEje(valor) {
    const numero = Number(valor ?? 0);

    if (Math.abs(numero) >= 1_000_000) {
        return `Bs ${(numero / 1_000_000).toLocaleString('es-BO', { maximumFractionDigits: 1 })} M`;
    }

    return Math.abs(numero) >= 1_000
        ? `Bs ${Math.round(numero / 1_000).toLocaleString('es-BO')} mil`
        : `Bs ${Math.round(numero)}`;
}

function porcentaje(valor, decimales = 1) {
    return `${Number(valor ?? 0).toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })} %`;
}

function cantidad(valor) {
    return Number(valor ?? 0).toLocaleString('es-BO');
}

function plural(n, singular, varios) {
    return `${cantidad(n)} ${n === 1 ? singular : varios}`;
}

/* -----------------------------------------------------------------
 * Indicadores
 * -------------------------------------------------------------- */

const resumen = computed(() => props.datos.rentabilidad_resumen);
const cuentas = computed(() => props.datos.cuentas_por_cobrar);

const kpis = computed(() => [
    {
        clave: 'ventas',
        etiqueta: 'Ventas del período',
        valor: money(props.datos.total_vendido),
        nota: 'Cotizaciones aprobadas o convertidas.',
        icono: 'fa-solid fa-sack-dollar',
        tono: 'success',
        ayuda: 'financiero.total-vendido',
    },
    {
        clave: 'cobro',
        etiqueta: 'Pendiente de cobro',
        valor: money(cuentas.value.total),
        nota: cuentas.value.pedidos.length
            ? `${plural(cuentas.value.pedidos.length, 'pedido', 'pedidos')} con saldo.`
            : 'No hay saldos pendientes.',
        icono: 'fa-solid fa-file-invoice-dollar',
        tono: 'warning',
        ayuda: 'financiero.por-cobrar',
    },
    {
        clave: 'margen',
        etiqueta: 'Margen sobre materiales',
        valor: resumen.value.margen_pct === null ? '—' : porcentaje(resumen.value.margen_pct),
        nota: resumen.value.con_costo
            ? `${plural(resumen.value.con_costo, 'pedido', 'pedidos')} con consumo registrado.`
            : 'Aún no hay pedidos con consumo registrado.',
        icono: 'fa-solid fa-percent',
        tono: resumen.value.margen < 0 ? 'danger' : 'primary',
        ayuda: 'financiero.margen-materiales',
    },
    {
        clave: 'perdida',
        etiqueta: 'Pedidos con pérdida',
        valor: cantidad(resumen.value.con_perdida),
        nota: resumen.value.con_perdida ? 'Requiere revisión.' : 'Ningún pedido dio pérdida.',
        icono: 'fa-solid fa-triangle-exclamation',
        tono: resumen.value.con_perdida ? 'danger' : 'success',
        ayuda: 'financiero.pedidos-con-perdida',
    },
]);

/* -----------------------------------------------------------------
 * Ventas por mes y por sucursal
 * -------------------------------------------------------------- */

const vistaVentas = ref('grafico');

const ventas = computed(() => ({
    labels: props.datos.ventas_por_mes.map((m) => mesAnio(m.mes)),
    series: [{ nombre: 'Ventas', datos: props.datos.ventas_por_mes.map((m) => m.total), area: true }],
}));

const mesesConVentas = computed(() => props.datos.ventas_por_mes.filter((m) => m.total > 0));

/** El hallazgo del gráfico: el mismo "mejor mes" que redacta el PDF. */
const mejorMes = computed(() => {
    if (mesesConVentas.value.length < 2) {
        return null;
    }

    return mesesConVentas.value.reduce((mejor, mes) => (mes.total > mejor.total ? mes : mejor));
});

function nombreMes(mes) {
    return mes.parcial ? `${mes.nombre} (${mes.parcial})` : mes.nombre;
}

/** Ancho de cada barra contra la sucursal que más vendió, no contra el 100 %. */
const sucursalesVentas = computed(() => {
    const maximo = Math.max(...props.datos.ventas_por_sucursal.map((s) => s.total), 0);

    return props.datos.ventas_por_sucursal.map((s) => ({
        ...s,
        ancho: maximo > 0 ? Math.max((s.total / maximo) * 100, 2) : 0,
    }));
});

/* -----------------------------------------------------------------
 * Resultado por pedido
 * -------------------------------------------------------------- */

const PAGINA = 10;

const tabla = reactive({
    busqueda: '',
    orden: 'menor',
    filtro: null, // null | 'perdida' | 'sin_consumo'
    visibles: PAGINA,
});

const tablaRef = ref(null);

const ordenes = {
    menor: { etiqueta: 'Menor resultado primero', comparar: (a, b) => a.margen - b.margen },
    mayor: { etiqueta: 'Mayor resultado primero', comparar: (a, b) => b.margen - a.margen },
    ingreso: { etiqueta: 'Mayor ingreso primero', comparar: (a, b) => b.ingreso - a.ingreso },
};

const filasFiltradas = computed(() => {
    const termino = tabla.busqueda.trim().toLowerCase();

    const filas = props.datos.rentabilidad.filter((fila) => {
        if (tabla.filtro === 'perdida' && (fila.sin_consumo || fila.margen >= 0)) {
            return false;
        }

        if (tabla.filtro === 'sin_consumo' && !fila.sin_consumo) {
            return false;
        }

        return !termino
            || fila.pedido.toLowerCase().includes(termino)
            || (fila.cliente ?? '').toLowerCase().includes(termino);
    });

    // Los pedidos sin consumo van siempre al final: su "resultado" no está
    // medido, y mezclarlos con los medidos los pondría arriba como si fueran
    // los más rentables (mismo criterio que el PDF).
    return [...filas].sort((a, b) => (a.sin_consumo - b.sin_consumo) || ordenes[tabla.orden].comparar(a, b));
});

const filasVisibles = computed(() => filasFiltradas.value.slice(0, tabla.visibles));

function alternarFiltro(clave) {
    tabla.filtro = tabla.filtro === clave ? null : clave;
    tabla.visibles = PAGINA;
}

/** "Ver pedidos" del aviso: filtra la tabla y la lleva a la vista. */
async function verSinConsumo() {
    tabla.filtro = 'sin_consumo';
    tabla.busqueda = '';
    tabla.visibles = PAGINA;
    await nextTick();
    tablaRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function verMas() {
    tabla.visibles += 25;
}

function estadoDe(fila) {
    if (fila.sin_consumo) {
        return { texto: 'Sin materiales', clase: 'badge-soft-warning' };
    }

    return fila.margen < 0
        ? { texto: 'Con pérdida', clase: 'badge-soft-danger' }
        : { texto: 'Positivo', clase: 'badge-soft-success' };
}

/** El rótulo de alcance solo aparece cuando los datos NO son de toda la empresa. */
const alcanceVisible = computed(() => props.datos.alcance !== 'Toda la empresa');
</script>

<template>
    <Head title="Reporte financiero" />

    <div class="page-stack">
        <!-- Filtros: todo lo de abajo se recalcula contra este período y sucursal. -->
        <div class="card">
            <form class="card-body financiero-filtros" @submit.prevent="aplicar">
                <div class="financiero-filtro-fechas">
                    <DateRangeFilter
                        v-model:fecha-desde="filtro.desde"
                        v-model:fecha-hasta="filtro.hasta"
                        label="Período de ventas"
                        default-range="Este año"
                    />
                </div>

                <div v-if="eligeSucursal" class="financiero-filtro-sucursal">
                    <label for="filtro-sucursal" class="form-label">Sucursal</label>
                    <select id="filtro-sucursal" v-model="filtro.sucursal_id" class="form-control">
                        <option value="">Todas las sucursales</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                </div>

                <div class="financiero-filtros-acciones">
                    <button type="submit" class="btn btn-primary" :disabled="actualizando">
                        <i class="fa-solid fa-rotate" :class="{ 'fa-spin': actualizando }" aria-hidden="true"></i>
                        Actualizar
                    </button>
                    <!-- Vista previa en pestaña nueva; el de al lado baja el archivo. -->
                    <template v-if="puedeExportar">
                        <a :href="urlPdf()" target="_blank" rel="noopener" class="btn btn-pdf">
                            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                            Ver PDF
                        </a>
                        <a :href="urlPdf(true)" class="btn btn-pdf">
                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                            Descargar PDF
                        </a>
                    </template>
                </div>
            </form>
        </div>

        <div class="financiero-kpis">
            <article v-for="kpi in kpis" :key="kpi.clave" class="card kpi-card">
                <span class="stat-icon" :class="`stat-icon-${kpi.tono}`">
                    <i :class="kpi.icono" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <p class="kpi-label etiqueta-con-ayuda"><span>{{ kpi.etiqueta }}</span> <AyudaInfo :clave="kpi.ayuda" /></p>
                    <p class="kpi-value" :class="{ 'text-danger': kpi.clave === 'perdida' && resumen.con_perdida }">
                        {{ kpi.valor }}
                    </p>
                    <p class="kpi-note">{{ kpi.nota }}</p>
                </div>
            </article>
        </div>

        <!-- Las dos familias de cifras tienen alcances distintos y no se
             comparan entre sí: se dice una vez, arriba de todo. -->
        <p class="financiero-alcance">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>
                <strong>Alcance:</strong> las ventas usan el período seleccionado. El margen incluye pedidos de
                todas las fechas.
                <template v-if="alcanceVisible"> <strong>{{ datos.alcance }}</strong>.</template>
            </span>
        </p>

        <div class="financiero-fila">
            <section class="card">
                <div class="card-header financiero-card-header">
                    <div>
                        <h2 class="card-title">Ventas por mes <AyudaInfo clave="financiero.ventas-por-mes" /></h2>
                        <p class="card-subtitle">Cotizaciones aprobadas y convertidas dentro del período seleccionado.</p>
                    </div>
                    <div class="financiero-vista" role="group" aria-label="Ver ventas por mes como">
                        <button type="button" :class="{ 'is-active': vistaVentas === 'grafico' }"
                            :aria-pressed="vistaVentas === 'grafico'" @click="vistaVentas = 'grafico'">Gráfico</button>
                        <button type="button" :class="{ 'is-active': vistaVentas === 'tabla' }"
                            :aria-pressed="vistaVentas === 'tabla'" @click="vistaVentas = 'tabla'">Tabla</button>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart v-if="vistaVentas === 'grafico'" titulo="Ventas por mes" :labels="ventas.labels"
                        :series="ventas.series" :formato="moneyEje" :formato-tooltip="money" :alto="280" sin-tabla
                        vacio="No hubo ventas en el período seleccionado." />

                    <div v-else class="table-responsive">
                        <table class="table-dashboard table-sm">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th class="text-end">Ventas</th>
                                    <th class="text-end">Parte del total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in datos.ventas_por_mes" :key="m.mes">
                                    <td>{{ nombreMes(m) }}</td>
                                    <td class="text-end financiero-num">{{ money(m.total) }}</td>
                                    <td class="text-end financiero-num">
                                        {{ datos.total_vendido > 0 ? porcentaje((m.total / datos.total_vendido) * 100) : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end financiero-num">{{ money(datos.total_vendido) }}</th>
                                    <th class="text-end financiero-num">100 %</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p v-if="mejorMes" class="financiero-hallazgo">
                        <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                        <span>{{ mejorMes.nombre }} fue el mes con mayores ventas: <strong>{{ money(mejorMes.total) }}</strong>.</span>
                    </p>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Ventas por sucursal <AyudaInfo clave="financiero.ventas-por-sucursal" /></h2>
                        <p class="card-subtitle">Participación en el total de ventas del período.</p>
                    </div>
                </div>
                <div class="card-body">
                    <p v-if="!sucursalesVentas.length" class="chart-empty">Sin ventas por sucursal en el período.</p>

                    <ul v-else class="financiero-sucursales">
                        <li v-for="s in sucursalesVentas" :key="s.sucursal" class="financiero-sucursal">
                            <span class="financiero-sucursal-nombre" :title="s.sucursal">{{ s.sucursal }}</span>
                            <span class="financiero-sucursal-pista">
                                <span class="financiero-sucursal-barra" :style="{ width: `${s.ancho}%` }">
                                    <span v-if="s.ancho >= 35">{{ porcentaje(s.participacion) }}</span>
                                </span>
                                <span v-if="s.ancho < 35" class="financiero-sucursal-fuera">{{ porcentaje(s.participacion) }}</span>
                            </span>
                            <span class="financiero-sucursal-monto">{{ money(s.total) }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <div v-if="resumen.sin_consumo" class="financiero-aviso">
            <span class="financiero-aviso-icono"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <div class="financiero-aviso-texto">
                <p class="financiero-aviso-titulo">
                    {{ plural(resumen.sin_consumo, 'pedido sin materiales registrados', 'pedidos sin materiales registrados') }}
                </p>
                <p>Completa el consumo de materiales para calcular su margen.</p>
            </div>
            <button type="button" class="btn btn-outline-warning financiero-aviso-accion" @click="verSinConsumo">
                Ver pedidos <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <section ref="tablaRef" class="card financiero-resultados">
            <div class="card-header financiero-card-header">
                <div>
                    <h2 class="card-title">Resultado por pedido <AyudaInfo clave="financiero.rentabilidad-tabla" /></h2>
                    <p class="card-subtitle">Ingreso menos materiales registrados. Este resultado no es la ganancia final de la empresa.</p>
                </div>

                <div class="financiero-tabla-controles">
                    <label class="financiero-busqueda">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <span class="sr-only">Buscar pedido o cliente</span>
                        <input v-model="tabla.busqueda" type="search" class="form-control" placeholder="Buscar pedido o cliente..."
                            @input="tabla.visibles = PAGINA" />
                    </label>
                    <label class="sr-only" for="orden-resultados">Ordenar</label>
                    <select id="orden-resultados" v-model="tabla.orden" class="form-control financiero-orden">
                        <option v-for="(o, clave) in ordenes" :key="clave" :value="clave">{{ o.etiqueta }}</option>
                    </select>
                    <button type="button" class="financiero-chip financiero-chip-danger"
                        :class="{ 'is-active': tabla.filtro === 'perdida' }" :aria-pressed="tabla.filtro === 'perdida'"
                        @click="alternarFiltro('perdida')">
                        Con pérdida ({{ cantidad(resumen.con_perdida) }})
                    </button>
                    <button type="button" class="financiero-chip financiero-chip-warning"
                        :class="{ 'is-active': tabla.filtro === 'sin_consumo' }" :aria-pressed="tabla.filtro === 'sin_consumo'"
                        @click="alternarFiltro('sin_consumo')">
                        Sin materiales ({{ cantidad(resumen.sin_consumo) }})
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table-dashboard financiero-tabla">
                        <thead>
                            <tr>
                                <th>Pedido / Cliente</th>
                                <th class="text-end">Ingreso</th>
                                <th class="text-end">Materiales</th>
                                <th class="text-end">Resultado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="fila in filasVisibles" :key="fila.pedido">
                                <td>
                                    <span class="financiero-pedido">{{ fila.pedido }}</span>
                                    <span class="financiero-cliente">{{ fila.cliente ?? 'Sin cliente' }}</span>
                                </td>
                                <td class="text-end financiero-num">{{ money(fila.ingreso) }}</td>
                                <td class="text-end financiero-num">
                                    {{ fila.sin_consumo ? '—' : money(fila.costo_real) }}
                                </td>
                                <td class="text-end financiero-num fw-semibold"
                                    :class="fila.sin_consumo ? 'text-muted' : (fila.margen < 0 ? 'text-danger' : 'text-success')">
                                    <template v-if="fila.sin_consumo">Sin medir</template>
                                    <template v-else>
                                        {{ money(fila.margen) }}
                                        <span v-if="fila.margen_pct !== null">({{ porcentaje(fila.margen_pct) }})</span>
                                    </template>
                                </td>
                                <td>
                                    <span class="badge financiero-estado" :class="estadoDe(fila).clase">{{ estadoDe(fila).texto }}</span>
                                </td>
                            </tr>
                            <tr v-if="!filasVisibles.length">
                                <td colspan="5" class="text-center text-muted py-4">
                                    {{ datos.rentabilidad.length ? 'Ningún pedido coincide con la búsqueda.' : 'Todavía no hay pedidos en acabado o entregados.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filasFiltradas.length" class="financiero-tabla-pie">
                    <span>Mostrando {{ cantidad(filasVisibles.length) }} de {{ plural(filasFiltradas.length, 'pedido', 'pedidos') }}.</span>
                    <button v-if="filasVisibles.length < filasFiltradas.length" type="button" class="financiero-ver-mas" @click="verMas">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        Ver más pedidos
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
