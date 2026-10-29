<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import { numero } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Alertas, en dos pestañas que NO se suman:
 *
 * - "Costo y rentabilidad": las reglas de AlertasNegocioService. Cada alerta
 *   es una fila con lo que hay que hacer (como título), el presupuesto o
 *   pedido, las cifras que la disparan, qué revisar y el botón para ir.
 *   Buscador, prioridad, tipo y páginas de cinco.
 * - "Anomalías estadísticas": lo que encuentra la revisión diaria
 *   (DeteccionAnomaliasService), con su estado. Lleva el ancla #anomalias:
 *   el Resumen semanal y el asistente enlazan acá, y la abren directo.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/** Alertas por página. */
const POR_PAGINA = 5;

const umbrales = computed(() => props.datos.umbrales);

function pct(valor) {
    return `${numero(valor, 0)} %`;
}

/** Las cinco reglas, para "Ver criterios del sistema". */
const REGLAS = [
    { tipo: 'margen_bajo', icono: 'fa-solid fa-percent', que: (u) => `Presupuestos pendientes o aprobados sin convertir que dejan menos del ${pct(u.margen_verde)} del costo (semáforo amarillo o rojo).` },
    { tipo: 'presupuesto_desactualizado', icono: 'fa-solid fa-file-circle-exclamation', que: (u) => `Pendientes cuyos materiales subieron ${pct(u.aumento_costo)} o más desde que se cotizaron.` },
    { tipo: 'sobrecosto_pedido', icono: 'fa-solid fa-industry', que: (u) => `Pedidos que usaron ${pct(u.aumento_costo)} o más de material que lo presupuestado.` },
    { tipo: 'costo_material', icono: 'fa-solid fa-arrow-trend-up', que: (u) => `Un material subió ${pct(u.aumento_costo)} o más en un solo cambio de precio.` },
    { tipo: 'tendencia_costos', icono: 'fa-solid fa-chart-line', que: (u) => `El costo de los materiales sube ${pct(u.tendencia_mensual)} o más por mes.` },
];

const ICONO_POR_TIPO = Object.fromEntries(REGLAS.map((r) => [r.tipo, r.icono]));

/** Nombre de cada tipo: el que manda el servidor en cada alerta. */
const NOMBRES = {
    margen_bajo: 'Rentabilidad sobre costo baja',
    presupuesto_desactualizado: 'Presupuesto con precios viejos',
    sobrecosto_pedido: 'Costo de material superior al previsto',
    costo_material: 'Aumento de precio de material',
    tendencia_costos: 'Tendencia de costos',
};

/* ----- Pestañas ------------------------------------------------------------------ */

/** Abre en anomalías si se llegó por #anomalias o filtrando por su estado. */
function pestanaInicial() {
    if (typeof window === 'undefined') {
        return 'alertas';
    }

    return window.location.hash === '#anomalias' || new URLSearchParams(window.location.search).has('anomalias')
        ? 'anomalias'
        : 'alertas';
}

const pestana = ref(pestanaInicial());
const panel = ref(null);

async function abrirAnomalias() {
    pestana.value = 'anomalias';
    await nextTick();
    panel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

onMounted(() => {
    if (pestana.value === 'anomalias' && window.location.hash === '#anomalias') {
        panel.value?.scrollIntoView({ block: 'start' });
    }
});

/* ----- Costo y rentabilidad ---------------------------------------------------- */

const busqueda = ref('');
const prioridad = ref('todas');
const tipo = ref('todos');
const pagina = ref(1);
const verCriterios = ref(false);

const tiposPresentes = computed(() => Object.keys(props.datos.resumen.por_tipo));

function sinAcentos(texto) {
    return String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const filtradas = computed(() => {
    const termino = sinAcentos(busqueda.value.trim());

    return props.datos.alertas
        .filter((a) => prioridad.value === 'todas' || a.severidad === prioridad.value)
        .filter((a) => tipo.value === 'todos' || a.tipo === tipo.value)
        .filter((a) => !termino || [a.referencia, a.cliente, a.accion, a.titulo, a.tipo_etiqueta]
            .some((campo) => sinAcentos(campo).includes(termino)));
});

const totalPaginas = computed(() => Math.max(Math.ceil(filtradas.value.length / POR_PAGINA), 1));

const visibles = computed(() => filtradas.value.slice((pagina.value - 1) * POR_PAGINA, pagina.value * POR_PAGINA));

// Al cambiar un filtro se vuelve a la primera página.
watch([busqueda, prioridad, tipo], () => { pagina.value = 1; });

/** Botones de página: 1 2 3 4 5 … 9 (con la actual siempre a la vista). */
const paginas = computed(() => {
    const total = totalPaginas.value;
    const actual = pagina.value;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    if (actual <= 4) {
        return [1, 2, 3, 4, 5, '…', total];
    }

    if (actual >= total - 3) {
        return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
    }

    return [1, '…', actual - 1, actual, actual + 1, '…', total];
});

const desde = computed(() => (filtradas.value.length ? (pagina.value - 1) * POR_PAGINA + 1 : 0));
const hasta = computed(() => Math.min(pagina.value * POR_PAGINA, filtradas.value.length));

/** Ancho de cada barra de la comparación, contra la mayor de las dos. */
function anchoBarra(comparacion, valor) {
    const mayor = Math.max(...comparacion.map((c) => c.valor), 1);

    return `${Math.max((valor / mayor) * 100, 2)}%`;
}

const criterios = computed(() => REGLAS.map((r) => ({
    ...r,
    nombre: NOMBRES[r.tipo],
    descripcion: r.que(umbrales.value),
    cantidad: props.datos.resumen.por_tipo[r.tipo] ?? 0,
})));

/* ----- Anomalías estadísticas ---------------------------------------------------- */

const anomalias = computed(() => props.datos.anomalias);

/** Icono por regla estadística. */
const ICONOS_ANOMALIA = {
    caida_cotizaciones: 'fa-solid fa-arrow-down-wide-short',
    alza_cotizaciones: 'fa-solid fa-arrow-up-wide-short',
    margen_bajo: 'fa-solid fa-percent',
    vendedor_inactivo: 'fa-solid fa-user-clock',
    cliente_en_riesgo: 'fa-solid fa-user-minus',
};

const ESTADOS_ANOMALIA = [
    { valor: 'NUEVA', texto: 'Sin revisar' },
    { valor: 'REVISADA', texto: 'Revisadas' },
    { valor: 'DESCARTADA', texto: 'Descartadas' },
    { valor: 'TODAS', texto: 'Todas' },
];

const detectando = ref(false);
const cambiando = ref(null);

function conteo(estado) {
    const c = anomalias.value.conteo ?? {};

    return estado === 'TODAS'
        ? Object.values(c).reduce((total, n) => total + Number(n), 0)
        : Number(c[estado] ?? 0);
}

function filtrarAnomalias(estado) {
    router.get(route('reportes.bi.alertas'), { ...props.filters, anomalias: estado }, {
        preserveState: true,
        preserveScroll: true,
        only: ['datos'],
    });
}

function detectar() {
    detectando.value = true;
    router.post(route('reportes.bi.anomalias.detectar'), { ...props.filters }, {
        preserveScroll: true,
        onFinish: () => { detectando.value = false; },
    });
}

function cambiarEstado(anomalia, estado) {
    cambiando.value = anomalia.id;
    router.patch(route('reportes.bi.anomalias.actualizar', anomalia.id), { estado }, {
        preserveScroll: true,
        onFinish: () => { cambiando.value = null; },
    });
}

function fecha(valor) {
    return valor ? new Date(`${valor}T12:00:00`).toLocaleDateString('es-BO', { day: 'numeric', month: 'short' }) : '';
}

const ultimaDeteccion = computed(() => {
    const valor = anomalias.value.ultima_deteccion;

    return valor ? new Date(valor).toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' }) : null;
});
</script>

<template>
    <Head title="Alertas" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Alertas de costo y rentabilidad" ayuda="bi-alertas.kpi-activas" icono="fa-solid fa-bell" tono="primary"
                    :valor="String(datos.resumen.total)" nota="En el periodo seleccionado" />
            </div>
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Prioridad alta" ayuda="bi-alertas.kpi-alta" icono="fa-solid fa-circle-exclamation"
                    :tono="datos.resumen.alta ? 'danger' : 'success'" :valor="String(datos.resumen.alta)" nota="Revisar primero" />
            </div>
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Prioridad media" ayuda="bi-alertas.kpi-media" icono="fa-solid fa-triangle-exclamation"
                    tono="warning" :valor="String(datos.resumen.media)" nota="Revisar precio y condiciones" />
            </div>
        </div>

        <section id="anomalias" ref="panel" class="card bi-alertas-panel">
            <div class="bi-alertas-pestanas">
                <div class="nav-tabs bi-alertas-tabs" role="tablist" aria-label="Tipo de alerta">
                    <button id="pestana-alertas" type="button" role="tab" class="nav-link" :class="{ active: pestana === 'alertas' }"
                        :aria-selected="pestana === 'alertas'" aria-controls="panel-alertas" @click="pestana = 'alertas'">
                        Costo y rentabilidad ({{ datos.resumen.total }})
                    </button>
                    <button id="pestana-anomalias" type="button" role="tab" class="nav-link" :class="{ active: pestana === 'anomalias' }"
                        :aria-selected="pestana === 'anomalias'" aria-controls="panel-anomalias" @click="pestana = 'anomalias'">
                        Anomalías estadísticas ({{ conteo('NUEVA') }})
                    </button>
                </div>
                <p class="bi-aviso bi-alertas-diferencia">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        Las alertas de costo y rentabilidad usan reglas del sistema. Las anomalías estadísticas son un análisis
                        distinto; sus cantidades no se suman.
                    </span>
                </p>
            </div>

            <!-- ==================== COSTO Y RENTABILIDAD ==================== -->
            <div v-show="pestana === 'alertas'" id="panel-alertas" role="tabpanel" aria-labelledby="pestana-alertas" class="card-body">
                <div class="bi-alertas-cabecera">
                    <div>
                        <h2 class="bi-alertas-titulo">Alertas de costo y rentabilidad <AyudaInfo clave="bi-alertas.costo-margen" /></h2>
                        <p class="bi-alertas-bajada">Primero las de prioridad alta.</p>
                    </div>
                    <div class="bi-alertas-filtros">
                        <label class="bi-alertas-buscar">
                            <span class="sr-only">Buscar presupuesto, pedido o cliente</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input v-model="busqueda" type="search" class="form-control" placeholder="Buscar presupuesto, pedido o cliente…" />
                        </label>
                        <div>
                            <label for="alertas-prioridad" class="form-label">Prioridad</label>
                            <select id="alertas-prioridad" v-model="prioridad" class="form-control">
                                <option value="todas">Todas</option>
                                <option value="alta">Alta ({{ datos.resumen.alta }})</option>
                                <option value="media">Media ({{ datos.resumen.media }})</option>
                            </select>
                        </div>
                        <div>
                            <label for="alertas-tipo" class="form-label">Tipo</label>
                            <select id="alertas-tipo" v-model="tipo" class="form-control">
                                <option value="todos">Todos</option>
                                <option v-for="t in tiposPresentes" :key="t" :value="t">
                                    {{ NOMBRES[t] ?? t }} ({{ datos.resumen.por_tipo[t] }})
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <ul v-if="visibles.length" class="bi-alertas-filas">
                    <li v-for="(a, i) in visibles" :key="`${a.tipo}-${a.referencia ?? i}`" class="bi-alerta-fila" :class="`is-${a.severidad}`">
                        <div class="bi-alerta-fila-estado">
                            <span class="bi-alerta-fila-icono" aria-hidden="true">
                                <i :class="a.severidad === 'alta' ? 'fa-solid fa-exclamation' : 'fa-solid fa-triangle-exclamation'"></i>
                            </span>
                            <div class="bi-alerta-fila-etiquetas">
                                <span class="badge" :class="a.severidad === 'alta' ? 'badge-soft-danger' : 'badge-soft-warning'">
                                    {{ a.severidad === 'alta' ? 'Alta' : 'Media' }}
                                </span>
                                <span class="bi-alerta-fila-tipo">
                                    <i :class="ICONO_POR_TIPO[a.tipo] ?? 'fa-solid fa-bell'" aria-hidden="true"></i>
                                    {{ a.tipo_etiqueta ?? NOMBRES[a.tipo] ?? a.tipo }}
                                </span>
                            </div>
                        </div>

                        <div class="bi-alerta-fila-cuerpo">
                            <p class="bi-alerta-fila-accion">{{ a.accion ?? a.titulo }}</p>
                            <p v-if="a.referencia" class="bi-alerta-fila-ref">{{ a.referencia }}</p>
                            <p v-if="a.cliente" class="bi-alerta-fila-cliente">{{ a.cliente }}</p>
                        </div>

                        <div class="bi-alerta-fila-datos">
                            <div v-if="a.comparacion" class="bi-alerta-fila-barras">
                                <div v-for="(c, j) in a.comparacion" :key="c.etiqueta" class="bi-alerta-fila-barra">
                                    <div class="bi-alerta-fila-barra-fila">
                                        <span>{{ c.etiqueta }}</span>
                                        <strong>{{ c.texto }}</strong>
                                    </div>
                                    <span class="bi-alerta-fila-pista" aria-hidden="true">
                                        <span :class="j === 0 ? 'is-registrado' : 'is-previsto'" :style="{ width: anchoBarra(a.comparacion, c.valor) }"></span>
                                    </span>
                                </div>
                            </div>
                            <dl v-else-if="a.metricas?.length" class="bi-alerta-fila-metricas">
                                <div v-for="m in a.metricas" :key="m.etiqueta">
                                    <dt>{{ m.etiqueta }}</dt>
                                    <dd>{{ m.valor }}</dd>
                                </div>
                            </dl>
                            <p v-else class="bi-alerta-fila-nota">{{ a.detalle }}</p>
                            <p v-if="a.nota" class="bi-alerta-fila-nota">{{ a.nota }}</p>
                        </div>

                        <p v-if="a.que_revisar" class="bi-alerta-fila-revisar">
                            <strong>Qué revisar:</strong> {{ a.que_revisar }}
                        </p>

                        <div class="bi-alerta-fila-accion-boton">
                            <Link v-if="a.url" :href="a.url" class="btn btn-sm btn-primary">
                                {{ a.enlace ?? 'Ver' }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </Link>
                        </div>
                    </li>
                </ul>
                <div v-else class="bi-alertas-vacio">
                    <i class="fa-solid fa-circle-check"></i>
                    <p class="mb-0">
                        {{ datos.resumen.total
                            ? 'Ninguna alerta coincide con la búsqueda o los filtros.'
                            : 'Sin alertas en el periodo: ningún costo subió por encima del umbral y los presupuestos vivos están en verde.' }}
                    </p>
                </div>

                <div v-if="filtradas.length" class="bi-alertas-paginacion">
                    <p class="mb-0">Mostrando {{ desde }}–{{ hasta }} de {{ filtradas.length }} {{ filtradas.length === 1 ? 'alerta' : 'alertas' }}</p>
                    <nav v-if="totalPaginas > 1" class="bi-alertas-paginas" aria-label="Páginas de alertas">
                        <button type="button" :disabled="pagina === 1" aria-label="Página anterior" @click="pagina--">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <template v-for="(p, k) in paginas" :key="`${p}-${k}`">
                            <span v-if="p === '…'" class="bi-alertas-puntos">…</span>
                            <button v-else type="button" :class="{ 'is-activa': p === pagina }" :aria-current="p === pagina ? 'page' : null"
                                @click="pagina = p">{{ p }}</button>
                        </template>
                        <button type="button" :disabled="pagina === totalPaginas" aria-label="Página siguiente" @click="pagina++">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </nav>
                    <button v-if="pagina < totalPaginas" type="button" class="bi-ver-mas mt-0" @click="pagina++">
                        Ver siguientes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>

                <details class="bi-alertas-colores">
                    <summary>
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>
                            <strong>¿Qué significan los colores?</strong>
                            <span class="d-block">
                                Rojo: rentabilidad sobre costo de hasta {{ pct(umbrales.margen_amarillo) }}. Amarillo: entre
                                {{ pct(umbrales.margen_amarillo) }} y {{ pct(umbrales.margen_verde) }}. Una rentabilidad baja no
                                significa necesariamente pérdida.
                            </span>
                        </span>
                        <i class="fa-solid fa-chevron-down bi-alertas-colores-flecha" aria-hidden="true"></i>
                    </summary>
                    <p>
                        En pedidos y precios de material, la prioridad es alta cuando el desvío llega al doble del umbral
                        ({{ pct(umbrales.aumento_costo * 2) }} o más). Las alertas se calculan al abrir esta pantalla, con los
                        presupuestos y pedidos del periodo; los precios de material son de toda la empresa.
                    </p>
                </details>

                <p class="bi-alertas-otras">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                    <span>
                        Otras reglas que se revisan: aumento de precio de material de {{ pct(umbrales.aumento_costo) }} o más y tendencia de
                        {{ pct(umbrales.tendencia_mensual) }} o más por mes.
                        <button type="button" class="bi-alertas-enlace" :aria-expanded="verCriterios" @click="verCriterios = !verCriterios">
                            {{ verCriterios ? 'Ocultar criterios del sistema' : 'Ver criterios del sistema' }}
                        </button>
                    </span>
                </p>

                <ul v-if="verCriterios" class="bi-reglas bi-alertas-criterios">
                    <li v-for="r in criterios" :key="r.tipo" class="card bi-regla" :class="r.cantidad ? 'bi-regla-warning' : 'bi-regla-success'">
                        <div class="bi-regla-cabecera">
                            <span class="bi-regla-icono" aria-hidden="true"><i :class="r.icono"></i></span>
                            <span class="bi-regla-cantidad">{{ r.cantidad }}</span>
                        </div>
                        <p class="bi-regla-nombre">{{ r.nombre }}</p>
                        <p class="bi-regla-que">{{ r.descripcion }}</p>
                        <button v-if="r.cantidad" type="button" class="bi-ver-mas bi-regla-ver" @click="tipo = r.tipo">
                            Filtrar por este tipo <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        </button>
                        <p v-else class="bi-regla-estado"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Sin alertas</p>
                    </li>
                </ul>
            </div>

            <!-- ==================== ANOMALÍAS ESTADÍSTICAS ==================== -->
            <div v-show="pestana === 'anomalias'" id="panel-anomalias" role="tabpanel" aria-labelledby="pestana-anomalias" class="card-body">
                <div class="bi-alertas-cabecera">
                    <div>
                        <h2 class="bi-alertas-titulo">
                            Anomalías estadísticas <AyudaInfo clave="bi-alertas.anomalias" />
                            <span class="badge badge-soft-secondary">Revisión diaria · No usa el filtro de fechas</span>
                        </h2>
                        <p class="bi-alertas-bajada">
                            Cambios fuera de lo habitual en cotizaciones, vendedores y clientes.
                            <span v-if="ultimaDeteccion">Última revisión: {{ ultimaDeteccion }}.</span>
                        </p>
                    </div>
                    <button v-if="anomalias.puede_gestionar" type="button" class="btn btn-sm btn-outline-primary" :disabled="detectando"
                        @click="detectar">
                        <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                        {{ detectando ? 'Analizando…' : 'Ejecutar análisis estadístico' }}
                    </button>
                </div>

                <div class="anomalia-filtro" role="group" aria-label="Filtrar anomalías por estado">
                    <button v-for="e in ESTADOS_ANOMALIA" :key="e.valor" type="button" class="anomalia-filtro-opcion"
                        :class="{ 'is-active': anomalias.estado === e.valor }" :aria-pressed="anomalias.estado === e.valor"
                        @click="filtrarAnomalias(e.valor)">
                        {{ e.texto }} <span class="anomalia-filtro-conteo">{{ conteo(e.valor) }}</span>
                    </button>
                </div>

                <ul v-if="anomalias.items.length" class="anomalias">
                    <li v-for="a in anomalias.items" :key="a.id" class="anomalia"
                        :class="[`anomalia-${a.severidad.toLowerCase()}`, { 'is-cerrada': a.estado !== 'NUEVA' }]">
                        <span class="anomalia-icono"><i :class="ICONOS_ANOMALIA[a.tipo] ?? 'fa-solid fa-wave-square'"></i></span>
                        <div class="anomalia-cuerpo">
                            <div class="anomalia-meta">
                                <span class="badge" :class="a.severidad === 'ALTA' ? 'badge-soft-danger' : 'badge-soft-warning'">
                                    {{ a.severidad === 'ALTA' ? 'Alta' : 'Media' }}
                                </span>
                                <span class="anomalia-tipo">{{ a.tipo_etiqueta }}</span>
                                <span class="anomalia-dato">{{ a.alcance }} · {{ fecha(a.detectada_en) }}</span>
                                <span v-if="a.estado !== 'NUEVA'" class="badge badge-soft-secondary">
                                    {{ a.estado === 'REVISADA' ? 'Revisada' : 'Descartada' }}<template v-if="a.revisor"> por {{ a.revisor }}</template>
                                </span>
                            </div>
                            <p class="anomalia-titulo">{{ a.titulo }}</p>
                            <p class="anomalia-detalle">{{ a.detalle }}</p>
                            <p v-if="a.que_revisar" class="anomalia-revisar">
                                <i class="fa-solid fa-hand-point-right"></i> {{ a.que_revisar }}
                            </p>
                            <p v-if="a.redaccion === 'IA'" class="anomalia-ia"><i class="fa-solid fa-wand-magic-sparkles"></i> Texto redactado por IA a partir de los datos</p>
                        </div>
                        <div class="anomalia-acciones">
                            <Link v-if="a.url" :href="a.url" class="btn btn-sm btn-soft-primary">Ver</Link>
                            <template v-if="anomalias.puede_gestionar">
                                <template v-if="a.estado === 'NUEVA'">
                                    <button type="button" class="btn btn-sm btn-soft-success" :disabled="cambiando === a.id"
                                        @click="cambiarEstado(a, 'REVISADA')">
                                        <i class="fa-solid fa-check"></i> Revisada
                                    </button>
                                    <button type="button" class="btn btn-sm btn-soft-secondary" :disabled="cambiando === a.id"
                                        @click="cambiarEstado(a, 'DESCARTADA')">
                                        Descartar
                                    </button>
                                </template>
                                <button v-else type="button" class="btn btn-sm btn-soft-secondary" :disabled="cambiando === a.id"
                                    @click="cambiarEstado(a, 'NUEVA')">
                                    Reabrir
                                </button>
                            </template>
                        </div>
                    </li>
                </ul>
                <div v-else class="bi-alertas-vacio">
                    <i class="fa-solid fa-circle-check"></i>
                    <p class="mb-0">
                        {{ anomalias.estado === 'NUEVA'
                            ? 'No hay anomalías sin revisar. La revisión corre sola cada día a las 06:30.'
                            : 'No hay anomalías en este estado.' }}
                        Esto no significa que no existan alertas de costo y rentabilidad.
                    </p>
                </div>
            </div>
        </section>

        <!-- Resumen de la otra pestaña, para no perderla de vista. -->
        <section v-if="pestana === 'alertas'" class="card bi-anomalias-resumen" aria-labelledby="anomalias-resumen-titulo">
            <div class="card-body">
                <div class="bi-anomalias-resumen-texto">
                    <h2 id="anomalias-resumen-titulo" class="bi-alertas-titulo">
                        Anomalías estadísticas <span class="badge badge-soft-primary">{{ conteo('NUEVA') }}</span>
                    </h2>
                    <p class="mb-0">
                        <template v-if="conteo('TODAS') === 0">
                            No hay anomalías sin revisar, revisadas ni descartadas en el análisis mostrado. Esto no significa que no
                            existan alertas de costo y rentabilidad.
                        </template>
                        <template v-else>
                            {{ conteo('NUEVA') }} sin revisar de {{ conteo('TODAS') }} detectadas por la revisión diaria.
                            <button type="button" class="bi-alertas-enlace" @click="abrirAnomalias">Ver anomalías</button>
                        </template>
                    </p>
                </div>
                <div v-if="anomalias.puede_gestionar" class="bi-anomalias-resumen-accion">
                    <button type="button" class="btn btn-sm btn-outline-primary" :disabled="detectando" @click="detectar">
                        <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                        {{ detectando ? 'Analizando…' : 'Ejecutar análisis estadístico' }}
                    </button>
                    <p class="mb-0">Busca cambios inusuales en cotizaciones, actividad comercial y compras de clientes.</p>
                </div>
            </div>
        </section>
    </div>
</template>
