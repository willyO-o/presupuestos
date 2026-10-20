<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import ChartCard from '@/Components/Reporte/ChartCard.vue';
import KpiCard from '@/Components/Reporte/KpiCard.vue';
import ClienteAvatar from '@/Components/Cliente/ClienteAvatar.vue';
import { money, moneyCompacto, numero, porcentaje } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Panorama del BI: la pantalla para decidir de un vistazo.
 *
 * Todo cuelga de los mismos filtros globales (fechas, tipo de producto,
 * cliente, sucursal), que viajan en la URL. El drill-down ES aplicar un
 * filtro: clic en una familia → sus categorías; clic en un mes → ese mes;
 * clic en un cliente → solo ese cliente. Así todo el resto de la pantalla
 * baja junto, y "Limpiar" o las migas deshacen el camino.
 *
 * Cada cuadro lleva dos títulos: el tema y el hallazgo (la conclusión en una
 * frase, armada en el servidor con los mismos números).
 */
const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/* ----- Filtros ----------------------------------------------------------- */

const filtros = reactive({
    desde: props.filters.desde ?? props.datos.periodo.desde,
    hasta: props.filters.hasta ?? props.datos.periodo.hasta,
    familia_id: props.filters.familia_id ? Number(props.filters.familia_id) : '',
    categoria_id: props.filters.categoria_id ? Number(props.filters.categoria_id) : '',
    cliente_id: props.filters.cliente_id ? Number(props.filters.cliente_id) : '',
    sucursal_id: props.filters.sucursal_id ? Number(props.filters.sucursal_id) : '',
});

const cargando = ref(false);
const opciones = computed(() => props.datos.opciones);

const categoriasDeFamilia = computed(() => (filtros.familia_id
    ? opciones.value.categorias.filter((c) => c.familia_producto_id === Number(filtros.familia_id))
    : opciones.value.categorias));

function alCambiarFamilia() {
    const categoria = opciones.value.categorias.find((c) => c.id === Number(filtros.categoria_id));
    if (categoria && categoria.familia_producto_id !== Number(filtros.familia_id)) {
        filtros.categoria_id = '';
    }
}

/** Manda los filtros (y los cambios que traiga el clic) y recalcula todo. */
function filtrar(cambios = {}) {
    Object.assign(filtros, cambios);

    const parametros = Object.fromEntries(
        Object.entries(filtros).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );

    router.get(route('reportes.bi.panorama'), parametros, {
        preserveState: true,
        preserveScroll: true,
        only: ['datos', 'filters'],
        onStart: () => { cargando.value = true; },
        onFinish: () => { cargando.value = false; },
    });
}

function limpiar() {
    filtrar({ familia_id: '', categoria_id: '', cliente_id: '', sucursal_id: '' });
}

const nombreDe = (lista, id, campo = 'nombre') => lista.find((x) => x.id === Number(id))?.[campo];

/** Chips de lo que está filtrando ahora (las fechas ya se ven en el filtro). */
const activos = computed(() => [
    filtros.familia_id && !filtros.categoria_id && { clave: 'familia_id', texto: nombreDe(opciones.value.familias, filtros.familia_id), extra: { categoria_id: '' } },
    filtros.categoria_id && { clave: 'categoria_id', texto: nombreDe(opciones.value.categorias, filtros.categoria_id) },
    filtros.cliente_id && { clave: 'cliente_id', texto: nombreDe(opciones.value.clientes, filtros.cliente_id, 'razon_social') },
    filtros.sucursal_id && { clave: 'sucursal_id', texto: nombreDe(opciones.value.sucursales, filtros.sucursal_id) },
].filter(Boolean));

/* ----- KPIs -------------------------------------------------------------- */

const FORMATOS = {
    bs: (v) => (v === null ? '—' : moneyCompacto(v)),
    numero: (v) => (v === null ? '—' : numero(v, 0)),
    porcentaje: (v) => (v === null ? '—' : porcentaje(v, 1)),
};

const ESTILO_KPI = {
    ventas: { icono: 'fa-solid fa-sack-dollar', tono: 'success', ayuda: 'bi-panorama.kpi-ventas' },
    cotizaciones: { icono: 'fa-solid fa-file-invoice', tono: 'primary', ayuda: 'bi-panorama.kpi-cotizaciones' },
    conversion: { icono: 'fa-solid fa-handshake', tono: 'info', ayuda: 'bi-panorama.kpi-conversion' },
    ticket: { icono: 'fa-solid fa-receipt', tono: 'cyan', ayuda: 'bi-panorama.kpi-ticket' },
    margen: { icono: 'fa-solid fa-percent', tono: 'warning', ayuda: 'bi-panorama.kpi-margen' },
};

const kpis = computed(() => props.datos.kpis.map((k) => ({
    ...k,
    ...ESTILO_KPI[k.clave],
    texto: FORMATOS[k.formato](k.valor),
    textoAnterior: k.anterior === null ? null : FORMATOS[k.formato](k.anterior),
})));

/* ----- Ventas por mes ---------------------------------------------------- */

const ventas = computed(() => {
    const v = props.datos.ventas_mensuales;

    return {
        labels: v.labels,
        series: v.mejor === null ? [] : [
            { nombre: 'Este periodo', tipo: 'bar', datos: v.actual, destacar: v.mejor, etiquetas: 'destacada' },
            { nombre: 'Periodo anterior', datos: v.anterior, neutra: true, punteada: true },
        ],
        umbrales: v.promedio ? [{ valor: v.promedio, nombre: `Promedio ${moneyCompacto(v.promedio)}` }] : [],
    };
});

/** Clic en un mes: el Panorama entero pasa a ese mes. */
function verMes({ indice }) {
    const clave = props.datos.ventas_mensuales.claves[indice];
    if (!clave) return;
    const [anio, mes] = clave.split('-').map(Number);
    const ultimo = new Date(anio, mes, 0).getDate();
    filtrar({ desde: `${clave}-01`, hasta: `${clave}-${String(ultimo).padStart(2, '0')}` });
}

/* ----- Ingresos y margen por mes ---------------------------------------- */

const ingresosMargen = computed(() => {
    const m = props.datos.ingresos_margen;

    return {
        labels: m.labels,
        series: m.ingresos.some((v) => v > 0) ? [
            { nombre: 'Ingresos', tipo: 'bar', datos: m.ingresos, neutra: true },
            { nombre: 'Margen bruto', datos: m.margen, eje: 'derecho', etiquetas: m.labels.length <= 6 },
        ] : [],
        umbrales: m.margen_periodo === null ? [] : [{ valor: m.margen_periodo, nombre: `Margen del periodo ${porcentaje(m.margen_periodo, 1)}`, eje: 'derecho' }],
    };
});

function pct(v) {
    return porcentaje(v, 0);
}

/* ----- Por tipo de producto (drill-down) -------------------------------- */

const tipo = computed(() => props.datos.por_tipo);

const tipoGrafico = computed(() => ({
    labels: tipo.value.items.map((i) => i.nombre),
    series: tipo.value.items.length ? [
        // Sin etiqueta: la barra más alta cae justo donde corre la línea del
        // margen. La cifra ya la dice el hallazgo (y el tooltip y la tabla).
        { nombre: 'Ingresos', tipo: 'bar', datos: tipo.value.items.map((i) => i.ingreso), destacar: 0 },
        { nombre: 'Margen bruto', datos: tipo.value.items.map((i) => i.margen), eje: 'derecho' },
    ] : [],
}));

const NIVEL_SIGUIENTE = { familia: 'sus categorías', categoria: 'sus productos' };

function bajarNivel({ indice }) {
    const item = tipo.value.items[indice];
    if (!tipo.value.siguiente || !item?.id) return;
    filtrar(tipo.value.siguiente === 'familia_id'
        ? { familia_id: item.id, categoria_id: '' }
        : { categoria_id: item.id });
}

/* ----- Embudo ------------------------------------------------------------ */

const embudo = computed(() => {
    const etapas = props.datos.embudo.etapas;
    const base = etapas[0]?.cantidad || 0;
    let peor = null;
    etapas.forEach((e, i) => {
        if (i > 0 && e.paso !== null && (peor === null || e.paso < etapas[peor].paso)) peor = i;
    });

    return {
        etapas: etapas.map((e) => ({ ...e, ancho: base ? Math.max((e.cantidad / base) * 100, 6) : 6 })),
        peor,
        vacio: base === 0,
    };
});

/* ----- Rankings ---------------------------------------------------------- */

function ranking(items) {
    const maximo = Math.max(...items.map((i) => i.ingreso), 1);

    return items.map((i) => ({ ...i, ancho: (i.ingreso / maximo) * 100 }));
}

const topClientes = computed(() => ranking(props.datos.top_clientes.items));
const topProductos = computed(() => ranking(props.datos.top_productos.items));

function barrasHorizontales(items) {
    return {
        labels: items.map((i) => i.nombre),
        series: items.length ? [{ nombre: 'Ingresos', tipo: 'bar', datos: items.map((i) => i.ingreso), destacar: 0, etiquetas: true }] : [],
    };
}

const vendedores = computed(() => barrasHorizontales(props.datos.por_vendedor.items));
const ciudades = computed(() => barrasHorizontales(props.datos.por_ciudad.items));
</script>

<template>
    <Head title="Panorama · Inteligencia de negocios" />

    <div class="page-stack">
        <BiEncabezado :filters="filters" />

        <!-- ==================== FILTROS GLOBALES ==================== -->
        <section class="card panorama-filtros" aria-label="Filtros del panorama">
            <form class="card-body" @submit.prevent="filtrar()">
                <div class="panorama-filtros-campos">
                    <div class="panorama-filtro panorama-filtro-fechas">
                        <DateRangeFilter v-model:fecha-desde="filtros.desde" v-model:fecha-hasta="filtros.hasta"
                            label="Periodo" />
                    </div>
                    <div class="panorama-filtro">
                        <label class="form-label" for="pf-familia">Tipo de producto</label>
                        <select id="pf-familia" v-model="filtros.familia_id" class="form-control" @change="alCambiarFamilia">
                            <option value="">Todos</option>
                            <option v-for="f in opciones.familias" :key="f.id" :value="f.id">{{ f.nombre }}</option>
                        </select>
                    </div>
                    <div class="panorama-filtro">
                        <label class="form-label" for="pf-categoria">Categoría</label>
                        <select id="pf-categoria" v-model="filtros.categoria_id" class="form-control">
                            <option value="">Todas</option>
                            <option v-for="c in categoriasDeFamilia" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                    </div>
                    <div class="panorama-filtro panorama-filtro-cliente">
                        <label class="form-label" for="pf-cliente">Cliente</label>
                        <SearchableSelect id="pf-cliente" v-model="filtros.cliente_id" :options="opciones.clientes"
                            option-label="razon_social" placeholder="Todos los clientes" />
                    </div>
                    <div v-if="opciones.sucursales.length > 1" class="panorama-filtro">
                        <label class="form-label" for="pf-sucursal">Sucursal</label>
                        <select id="pf-sucursal" v-model="filtros.sucursal_id" class="form-control">
                            <option value="">Todas</option>
                            <option v-for="s in opciones.sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>
                    </div>
                    <div class="panorama-filtros-acciones">
                        <button type="submit" class="btn btn-primary" :disabled="cargando">
                            <i :class="cargando ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-filter'" aria-hidden="true"></i>
                            Aplicar
                        </button>
                        <button v-if="activos.length" type="button" class="btn btn-soft-secondary" :disabled="cargando" @click="limpiar">
                            Limpiar
                        </button>
                    </div>
                </div>
                <div class="panorama-filtros-pie">
                    <ul v-if="activos.length" class="panorama-chips" aria-label="Filtros aplicados">
                        <li v-for="a in activos" :key="a.clave">
                            <span class="panorama-chip">
                                {{ a.texto }}
                                <button type="button" :aria-label="`Quitar filtro ${a.texto}`"
                                    @click="filtrar({ [a.clave]: '', ...(a.extra ?? {}) })">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="panorama-filtros-nota">
                        Comparado con {{ datos.periodo_anterior.desde.split('-').reverse().join('/') }} al
                        {{ datos.periodo_anterior.hasta.split('-').reverse().join('/') }}
                    </p>
                    <EtiquetaAlcance />
                </div>
            </form>
        </section>

        <!-- ==================== KPIs ==================== -->
        <div class="panorama-kpis" :class="{ 'is-cargando': cargando }">
            <KpiCard v-for="k in kpis" :key="k.clave" :etiqueta="k.etiqueta" :valor="k.texto" :anterior="k.textoAnterior"
                :variacion="k.variacion" :unidad="k.unidad_variacion" :icono="k.icono" :tono="k.tono" :nota="k.nota"
                :ayuda="k.ayuda" />
        </div>

        <!-- ==================== TENDENCIA + EMBUDO ==================== -->
        <div class="panorama-fila panorama-fila-8-4">
            <ChartCard tema="Ventas por mes" :hallazgo="datos.ventas_mensuales.hallazgo" ayuda="bi-panorama.ventas"
                :cargando="cargando">
                <BaseChart titulo="Ventas por mes" :labels="ventas.labels" :series="ventas.series"
                    :umbrales="ventas.umbrales" :formato="moneyCompacto" :formato-tooltip="money" :alto="300"
                    vacio="No hubo ventas en el periodo." @seleccionar="verMes" />
                <p v-if="ventas.series.length" class="panorama-pista">
                    <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Haz clic en un mes para ver solo ese mes.
                </p>
            </ChartCard>

            <ChartCard tema="Embudo de ventas" :hallazgo="datos.embudo.hallazgo" ayuda="bi-panorama.embudo"
                :cargando="cargando">
                <p v-if="embudo.vacio" class="chart-empty">No hubo cotizaciones en el periodo.</p>
                <ol v-else class="embudo">
                    <li v-for="(e, i) in embudo.etapas" :key="e.clave" class="embudo-etapa">
                        <p v-if="i > 0" class="embudo-paso" :class="{ 'is-peor': i === embudo.peor }">
                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                            pasa el {{ e.paso === null ? '—' : porcentaje(e.paso, 1) }}
                        </p>
                        <div class="embudo-fila">
                            <div class="embudo-barra" :class="`embudo-barra-${i}`" :style="{ width: `${e.ancho}%` }">
                                <span>{{ numero(e.cantidad, 0) }}</span>
                            </div>
                        </div>
                        <p class="embudo-texto">
                            <strong>{{ e.nombre }}</strong>
                            <span>{{ money(e.monto) }}</span>
                        </p>
                    </li>
                </ol>
            </ChartCard>
        </div>

        <!-- ==================== MARGEN + TIPO DE PRODUCTO ==================== -->
        <div class="panorama-fila">
            <ChartCard tema="Ingresos y margen por mes" :hallazgo="datos.ingresos_margen.hallazgo"
                ayuda="bi-panorama.ingresos-margen" :cargando="cargando">
                <BaseChart titulo="Ingresos y margen por mes" :labels="ingresosMargen.labels" :series="ingresosMargen.series"
                    :umbrales="ingresosMargen.umbrales" :formato="moneyCompacto" :formato-tooltip="money"
                    :formato-derecho="pct" :alto="300" vacio="No hubo ventas en el periodo." />
            </ChartCard>

            <ChartCard tema="Ingresos y margen por tipo de producto" :hallazgo="tipo.hallazgo" ayuda="bi-panorama.tipo"
                :cargando="cargando">
                <nav v-if="tipo.migas.length > 1" class="panorama-migas" aria-label="Nivel del tipo de producto">
                    <template v-for="(m, i) in tipo.migas" :key="i">
                        <button v-if="i < tipo.migas.length - 1" type="button" class="panorama-miga"
                            @click="filtrar({ familia_id: m.filtro.familia_id ?? '', categoria_id: m.filtro.categoria_id ?? '' })">
                            {{ m.texto }}
                        </button>
                        <span v-else class="panorama-miga is-actual" aria-current="location">{{ m.texto }}</span>
                        <i v-if="i < tipo.migas.length - 1" class="fa-solid fa-chevron-right panorama-miga-sep" aria-hidden="true"></i>
                    </template>
                </nav>
                <BaseChart titulo="Ingresos y margen por tipo de producto" :labels="tipoGrafico.labels"
                    :series="tipoGrafico.series" :formato="moneyCompacto" :formato-tooltip="money" :formato-derecho="pct"
                    :alto="300" :etiquetas-inclinadas="tipoGrafico.labels.length > 5"
                    vacio="No hubo ventas de este tipo en el periodo." @seleccionar="bajarNivel" />
                <p v-if="tipo.siguiente && tipo.items.length" class="panorama-pista">
                    <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
                    Haz clic en una barra para ver {{ NIVEL_SIGUIENTE[tipo.nivel] }}.
                </p>
            </ChartCard>
        </div>

        <!-- ==================== TOP 5 ==================== -->
        <div class="panorama-fila">
            <ChartCard tema="Top 5 clientes por ingresos" :hallazgo="datos.top_clientes.hallazgo"
                ayuda="bi-panorama.clientes" :cargando="cargando">
                <ol v-if="topClientes.length" class="panorama-top">
                    <li v-for="(c, i) in topClientes" :key="c.id">
                        <button type="button" class="panorama-top-item" :title="`Ver solo ${c.nombre}`"
                            @click="filtrar({ cliente_id: c.id })">
                            <span class="panorama-top-puesto">{{ i + 1 }}</span>
                            <ClienteAvatar :nombre="c.nombre" :logo-url="c.logo_url" />
                            <span class="panorama-top-cuerpo">
                                <span class="panorama-top-fila">
                                    <span class="panorama-top-nombre">{{ c.nombre }}</span>
                                    <span class="panorama-top-monto">{{ money(c.ingreso) }}</span>
                                </span>
                                <span class="panorama-top-barra" aria-hidden="true"><span :style="{ width: `${c.ancho}%` }"></span></span>
                                <span class="panorama-top-meta">
                                    <span>{{ porcentaje(c.participacion, 1) }} de las ventas</span>
                                    <span>{{ c.ventas }} {{ c.ventas === 1 ? 'venta' : 'ventas' }}</span>
                                    <span v-if="c.margen !== null" class="panorama-margen">margen {{ porcentaje(c.margen, 1) }}</span>
                                </span>
                            </span>
                        </button>
                    </li>
                </ol>
                <p v-else class="chart-empty">No hubo ventas en el periodo.</p>
            </ChartCard>

            <ChartCard tema="Top 5 productos por ingresos" :hallazgo="datos.top_productos.hallazgo"
                ayuda="bi-panorama.productos" :cargando="cargando">
                <ol v-if="topProductos.length" class="panorama-top">
                    <li v-for="(p, i) in topProductos" :key="p.id">
                        <div class="panorama-top-item">
                            <span class="panorama-top-puesto">{{ i + 1 }}</span>
                            <span class="panorama-top-imagen" aria-hidden="true">
                                <img v-if="p.imagen_url" :src="p.imagen_url" alt="" loading="lazy" />
                                <i v-else class="fa-solid fa-cube"></i>
                            </span>
                            <span class="panorama-top-cuerpo">
                                <span class="panorama-top-fila">
                                    <span class="panorama-top-nombre">{{ p.nombre }}</span>
                                    <span class="panorama-top-monto">{{ money(p.ingreso) }}</span>
                                </span>
                                <span class="panorama-top-barra" aria-hidden="true"><span :style="{ width: `${p.ancho}%` }"></span></span>
                                <span class="panorama-top-meta">
                                    <span>{{ porcentaje(p.participacion, 1) }} de las ventas</span>
                                    <span>{{ numero(p.cantidad, 0) }} unid.</span>
                                    <span v-if="p.margen !== null" class="panorama-margen">margen {{ porcentaje(p.margen, 1) }}</span>
                                </span>
                            </span>
                        </div>
                    </li>
                </ol>
                <p v-else class="chart-empty">No hubo ventas de productos del catálogo en el periodo.</p>
            </ChartCard>
        </div>

        <!-- ==================== VENDEDOR + CIUDAD ==================== -->
        <div class="panorama-fila">
            <ChartCard tema="Ventas por vendedor" :hallazgo="datos.por_vendedor.hallazgo" ayuda="bi-panorama.vendedor"
                :cargando="cargando">
                <BaseChart titulo="Ventas por vendedor" tipo="bar" horizontal :labels="vendedores.labels"
                    :series="vendedores.series" :formato="moneyCompacto" :formato-tooltip="money"
                    :alto="Math.max(220, vendedores.labels.length * 34)" vacio="No hubo ventas en el periodo." />
            </ChartCard>

            <ChartCard tema="Ventas por ciudad" :hallazgo="datos.por_ciudad.hallazgo" ayuda="bi-panorama.ciudad"
                :cargando="cargando">
                <BaseChart titulo="Ventas por ciudad" tipo="bar" horizontal :labels="ciudades.labels"
                    :series="ciudades.series" :formato="moneyCompacto" :formato-tooltip="money"
                    :alto="Math.max(220, ciudades.labels.length * 34)" vacio="No hubo ventas en el periodo." />
            </ChartCard>
        </div>
    </div>
</template>
