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
import { money, moneyCompacto, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

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

/* ----- Destacados (franja bajo los KPIs) ---------------------------------- */

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** "Ago-2026" → "agosto de 2026", para la frase de la franja. */
function mesLargo(etiqueta, clave) {
    const [anio, mes] = (clave ?? '').split('-').map(Number);

    return mes ? `${MESES[mes - 1]} de ${anio}` : etiqueta;
}

const destacados = computed(() => {
    const v = props.datos.ventas_mensuales;
    const lista = [];

    if (v.mejor !== null && v.labels.length > 1) {
        lista.push({
            icono: 'fa-solid fa-trophy',
            texto: `Mayor venta mensual: ${mesLargo(v.labels[v.mejor], v.claves[v.mejor])},`,
            cifra: money(v.actual[v.mejor]),
        });
    }

    const clientes = props.datos.top_clientes.items;

    if (clientes.length > 1) {
        const suma = clientes.reduce((total, c) => total + c.participacion, 0);
        lista.push({
            icono: 'fa-solid fa-users',
            texto: `Los ${clientes.length} principales clientes reúnen el`,
            cifra: porcentaje(suma, 1),
            despues: 'de las ventas.',
        });
    }

    // El último mes queda cortado si el periodo no llega a fin de mes: su
    // barra baja sin que haya bajado la venta.
    const [anio, mes, dia] = props.datos.periodo.hasta.split('-').map(Number);
    if (dia < new Date(anio, mes, 0).getDate()) {
        const nombre = MESES[mes - 1];
        lista.push({
            icono: 'fa-regular fa-calendar',
            texto: `${nombre.charAt(0).toUpperCase()}${nombre.slice(1)} incluye datos solo hasta el día ${dia}.`,
        });
    }

    return lista;
});

/* ----- 1. Evolución de las ventas ----------------------------------------- */

const vistaVentas = ref('grafico');

const ventas = computed(() => {
    const v = props.datos.ventas_mensuales;

    return {
        labels: v.labels,
        series: v.mejor === null ? [] : [
            { nombre: 'Este periodo', datos: v.actual, area: true },
            { nombre: 'Periodo anterior', datos: v.anterior, neutra: true, punteada: true },
        ],
    };
});

const filasVentas = computed(() => {
    const v = props.datos.ventas_mensuales;

    return v.labels.map((etiqueta, i) => ({
        etiqueta,
        actual: v.actual[i],
        anterior: v.anterior[i],
        cambio: v.anterior[i] ? ((v.actual[i] - v.anterior[i]) / v.anterior[i]) * 100 : null,
    }));
});

/** Clic en un mes: el Panorama entero pasa a ese mes. */
function verMes({ indice }) {
    const clave = props.datos.ventas_mensuales.claves[indice];
    if (!clave) return;
    const [anio, mes] = clave.split('-').map(Number);
    const ultimo = new Date(anio, mes, 0).getDate();
    filtrar({ desde: `${clave}-01`, hasta: `${clave}-${String(ultimo).padStart(2, '0')}` });
}

/* ----- Avance de las cotizaciones (embudo) -------------------------------- */

const NOMBRE_ETAPA = {
    emitidas: { nombre: 'Emitidas', de: '' },
    aprobadas: { nombre: 'Aprobadas', de: 'de las emitidas' },
    pedido: { nombre: 'Convertidas en pedido', de: 'de las aprobadas' },
    pagadas: { nombre: 'Cobradas por completo', de: 'de los pedidos' },
};

const embudo = computed(() => {
    const etapas = props.datos.embudo.etapas;
    const base = etapas[0]?.cantidad || 0;
    let peor = null;
    etapas.forEach((e, i) => {
        if (i > 0 && e.paso !== null && (peor === null || e.paso < etapas[peor].paso)) peor = i;
    });

    return {
        etapas: etapas.map((e) => ({
            ...e,
            ...(NOMBRE_ETAPA[e.clave] ?? { nombre: e.nombre, de: 'de la etapa anterior' }),
            ancho: base ? Math.max((e.cantidad / base) * 100, 4) : 4,
        })),
        peor,
        vacio: base === 0,
    };
});

/**
 * El "pasa el 50 %" de emitidas → aprobadas y la "Conversión" de la tarjeta no
 * coinciden y parecen un error: una cuenta todas las emitidas y la otra solo
 * las que ya tienen respuesta. Se aclara cuando los dos números existen.
 */
const aclaracionConversion = computed(() => {
    const aprobadas = props.datos.embudo.etapas.find((e) => e.clave === 'aprobadas');
    const conversion = props.datos.kpis.find((k) => k.clave === 'conversion');

    if (!aprobadas || aprobadas.paso === null || !conversion || conversion.valor === null) {
        return null;
    }

    return `El ${porcentaje(aprobadas.paso, 1)} usa todas las emitidas; el ${porcentaje(conversion.valor, 1)} de la tarjeta, solo las cotizaciones que ya tienen respuesta.`;
});

/* ----- 2. Ventas y margen presupuestado ------------------------------------ */

const vistaMargen = ref('grafico');

const margenMensual = computed(() => {
    const m = props.datos.ingresos_margen;

    return {
        labels: m.labels,
        series: m.margen.some((v) => v !== null) ? [{ nombre: 'Margen presupuestado', datos: m.margen, etiquetas: m.labels.length <= 6 }] : [],
        umbrales: m.margen_periodo === null ? [] : [{ valor: m.margen_periodo, nombre: `Promedio del periodo: ${porcentaje(m.margen_periodo, 1)}` }],
    };
});

function pct(v) {
    return porcentaje(v, 0);
}

function pctTooltip(v) {
    return v === null ? '—' : porcentaje(v, 1);
}

const tipo = computed(() => props.datos.por_tipo);
const filasTipo = computed(() => ranking(tipo.value.items));

const NIVEL_SIGUIENTE = { familia: 'sus categorías', categoria: 'sus productos' };

/** Clic en un tipo (o categoría): todo el Panorama baja a ese nivel. */
function bajarNivel(item) {
    if (!tipo.value.siguiente || !item?.id) return;
    filtrar(tipo.value.siguiente === 'familia_id'
        ? { familia_id: item.id, categoria_id: '' }
        : { categoria_id: item.id });
}

/** El mejor margen, para destacarlo en la columna (en verde). */
const mejorMargenTipo = computed(() => {
    const conMargen = tipo.value.items.filter((i) => i.margen !== null);

    return conMargen.length > 1 ? Math.max(...conMargen.map((i) => i.margen)) : null;
});

/* ----- 3. Rankings y 4. distribución -------------------------------------- */

function ranking(items) {
    const maximo = Math.max(...items.map((i) => i.ingreso), 1);

    return items.map((i) => ({ ...i, ancho: Math.max((i.ingreso / maximo) * 100, 2) }));
}

const topClientes = computed(() => ranking(props.datos.top_clientes.items));
const topProductos = computed(() => ranking(props.datos.top_productos.items));

function barrasHorizontales(items) {
    return {
        labels: items.map((i) => i.nombre),
        series: items.length ? [{ nombre: 'Ventas', tipo: 'bar', datos: items.map((i) => i.ingreso), destacar: 0, etiquetas: true }] : [],
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
                            Aplicar filtros
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
                        Comparación: {{ datos.periodo_anterior.desde.split('-').reverse().join('/') }} al
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

        <!-- Lo que conviene saber antes de mirar los gráficos. -->
        <ul v-if="destacados.length" class="panorama-destacados" :class="{ 'is-cargando': cargando }">
            <li v-for="d in destacados" :key="d.icono">
                <i :class="d.icono" aria-hidden="true"></i>
                <span>
                    {{ d.texto }}
                    <strong v-if="d.cifra">{{ d.cifra }}</strong>{{ d.despues ? ` ${d.despues}` : '' }}
                </span>
            </li>
        </ul>

        <!-- ==================== 1. EVOLUCIÓN ==================== -->
        <h2 class="panorama-seccion">1. Evolución de las ventas</h2>

        <div class="panorama-fila panorama-fila-8-4">
            <ChartCard tema="Ventas por mes" subtitulo="Este periodo frente al periodo anterior · Bolivianos"
                :hallazgo="datos.ventas_mensuales.hallazgo" hallazgo-abajo ayuda="bi-panorama.ventas" :cargando="cargando">
                <template #acciones>
                    <div class="financiero-vista panorama-vista" role="group" aria-label="Ver ventas por mes como">
                        <button type="button" :class="{ 'is-active': vistaVentas === 'grafico' }" :aria-pressed="vistaVentas === 'grafico'"
                            @click="vistaVentas = 'grafico'"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Gráfico</button>
                        <button type="button" :class="{ 'is-active': vistaVentas === 'tabla' }" :aria-pressed="vistaVentas === 'tabla'"
                            @click="vistaVentas = 'tabla'"><i class="fa-solid fa-table" aria-hidden="true"></i> Tabla</button>
                    </div>
                </template>

                <template v-if="vistaVentas === 'grafico'">
                    <BaseChart titulo="Ventas por mes" :labels="ventas.labels" :series="ventas.series" :formato="moneyCompacto"
                        :formato-tooltip="money" :alto="300" sin-tabla vacio="No hubo ventas en el periodo." @seleccionar="verMes" />
                    <p v-if="ventas.series.length" class="panorama-pista">
                        <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Haz clic en un mes para ver solo ese mes.
                    </p>
                </template>
                <div v-else class="table-responsive">
                    <table class="table-dashboard table-sm">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="text-end">Este periodo</th>
                                <th class="text-end">Periodo anterior</th>
                                <th class="text-end">Variación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in filasVentas" :key="f.etiqueta">
                                <td>{{ f.etiqueta }}</td>
                                <td class="text-end financiero-num">{{ money(f.actual) }}</td>
                                <td class="text-end financiero-num">{{ money(f.anterior) }}</td>
                                <td class="text-end financiero-num" :class="f.cambio === null ? 'text-muted' : (f.cambio >= 0 ? 'text-success' : 'text-danger')">
                                    {{ f.cambio === null ? '—' : variacion(f.cambio) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ChartCard>

            <ChartCard tema="Avance de las cotizaciones" subtitulo="Cantidad que llega a cada etapa"
                :hallazgo="datos.embudo.hallazgo" hallazgo-abajo ayuda="bi-panorama.embudo" :cargando="cargando">
                <p v-if="embudo.vacio" class="chart-empty">No hubo cotizaciones en el periodo.</p>
                <ol v-else class="panorama-avance">
                    <li v-for="(e, i) in embudo.etapas" :key="e.clave" class="panorama-avance-etapa">
                        <span class="panorama-avance-nombre">{{ e.nombre }}</span>
                        <span class="panorama-avance-pista">
                            <span class="panorama-avance-barra" :class="`panorama-avance-barra-${i}`" :style="{ width: `${e.ancho}%` }"></span>
                            <strong>{{ numero(e.cantidad, 0) }}</strong>
                        </span>
                        <span v-if="i > 0" class="panorama-avance-paso" :class="{ 'is-peor': i === embudo.peor }">
                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                            {{ e.paso === null ? '—' : porcentaje(e.paso, 1) }} {{ e.de }}
                        </span>
                    </li>
                </ol>
                <p v-if="aclaracionConversion" class="panorama-aclaracion">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ aclaracionConversion }}
                </p>
            </ChartCard>
        </div>

        <!-- ==================== 2. MARGEN ==================== -->
        <h2 class="panorama-seccion">2. Ventas y margen presupuestado</h2>

        <div class="panorama-fila">
            <ChartCard tema="Margen presupuestado por mes" subtitulo="Porcentaje sobre ventas"
                :hallazgo="datos.ingresos_margen.hallazgo" hallazgo-abajo ayuda="bi-panorama.ingresos-margen" :cargando="cargando">
                <template #acciones>
                    <div class="financiero-vista panorama-vista" role="group" aria-label="Ver margen por mes como">
                        <button type="button" :class="{ 'is-active': vistaMargen === 'grafico' }" :aria-pressed="vistaMargen === 'grafico'"
                            @click="vistaMargen = 'grafico'"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Gráfico</button>
                        <button type="button" :class="{ 'is-active': vistaMargen === 'tabla' }" :aria-pressed="vistaMargen === 'tabla'"
                            @click="vistaMargen = 'tabla'"><i class="fa-solid fa-table" aria-hidden="true"></i> Tabla</button>
                    </div>
                </template>

                <BaseChart v-if="vistaMargen === 'grafico'" titulo="Margen presupuestado por mes" :labels="margenMensual.labels"
                    :series="margenMensual.series" :umbrales="margenMensual.umbrales" :formato="pct" :formato-tooltip="pctTooltip"
                    :alto="280" sin-tabla vacio="Sin cotizaciones con hoja de costos en el periodo." />
                <div v-else class="table-responsive">
                    <table class="table-dashboard table-sm">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Margen presupuestado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(etiqueta, i) in datos.ingresos_margen.labels" :key="etiqueta">
                                <td>{{ etiqueta }}</td>
                                <td class="text-end financiero-num">{{ money(datos.ingresos_margen.ingresos[i]) }}</td>
                                <td class="text-end financiero-num">{{ pctTooltip(datos.ingresos_margen.margen[i]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ChartCard>

            <ChartCard tema="Ventas y margen por tipo de producto" :hallazgo="tipo.hallazgo" hallazgo-abajo
                ayuda="bi-panorama.tipo" :cargando="cargando">
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

                <p v-if="!filasTipo.length" class="chart-empty">No hubo ventas de este tipo en el periodo.</p>
                <table v-else class="panorama-ranking">
                    <thead>
                        <tr>
                            <th>{{ tipo.nivel === 'familia' ? 'Tipo de producto' : (tipo.nivel === 'categoria' ? 'Categoría' : 'Producto') }}</th>
                            <th>Ventas</th>
                            <th class="text-end">Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filasTipo" :key="item.id ?? item.nombre"
                            :class="{ 'is-clic': tipo.siguiente && item.id }"
                            :tabindex="tipo.siguiente && item.id ? 0 : undefined"
                            @click="bajarNivel(item)" @keydown.enter="bajarNivel(item)">
                            <td class="panorama-ranking-nombre">{{ item.nombre }}</td>
                            <td>
                                <span class="panorama-ranking-barra">
                                    <span><span :style="{ width: `${item.ancho}%` }"></span></span>
                                    <span class="financiero-num">{{ moneyCompacto(item.ingreso) }}</span>
                                </span>
                            </td>
                            <td class="text-end financiero-num" :class="{ 'text-success fw-semibold': item.margen !== null && item.margen === mejorMargenTipo }">
                                {{ item.margen === null ? '—' : porcentaje(item.margen, 1) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="tipo.siguiente && filasTipo.length" class="panorama-pista">
                    <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
                    Haz clic en una fila para ver {{ NIVEL_SIGUIENTE[tipo.nivel] }}.
                </p>
            </ChartCard>
        </div>

        <!-- ==================== 3. CLIENTES Y PRODUCTOS ==================== -->
        <h2 class="panorama-seccion">3. Clientes y productos destacados</h2>

        <div class="panorama-fila">
            <ChartCard tema="5 clientes con mayores ventas" :hallazgo="datos.top_clientes.hallazgo" hallazgo-abajo
                ayuda="bi-panorama.clientes" :cargando="cargando">
                <p v-if="!topClientes.length" class="chart-empty">No hubo ventas en el periodo.</p>
                <table v-else class="panorama-ranking">
                    <thead>
                        <tr>
                            <th class="panorama-ranking-puesto">#</th>
                            <th>Cliente</th>
                            <th>Ventas</th>
                            <th class="text-end">Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in topClientes" :key="c.id" class="is-clic" tabindex="0" :title="`Ver solo ${c.nombre}`"
                            @click="filtrar({ cliente_id: c.id })" @keydown.enter="filtrar({ cliente_id: c.id })">
                            <td class="panorama-ranking-puesto">{{ i + 1 }}</td>
                            <td class="panorama-ranking-nombre">{{ c.nombre }}</td>
                            <td>
                                <span class="panorama-ranking-barra">
                                    <span><span :style="{ width: `${c.ancho}%` }"></span></span>
                                    <span class="financiero-num">{{ money(c.ingreso) }}</span>
                                </span>
                            </td>
                            <td class="text-end financiero-num">{{ c.margen === null ? '—' : porcentaje(c.margen, 1) }}</td>
                        </tr>
                    </tbody>
                </table>
            </ChartCard>

            <ChartCard tema="5 productos con mayores ventas" :hallazgo="datos.top_productos.hallazgo" hallazgo-abajo
                ayuda="bi-panorama.productos" :cargando="cargando">
                <p v-if="!topProductos.length" class="chart-empty">No hubo ventas de productos del catálogo en el periodo.</p>
                <table v-else class="panorama-ranking">
                    <thead>
                        <tr>
                            <th class="panorama-ranking-puesto">#</th>
                            <th>Producto</th>
                            <th>Ventas</th>
                            <th class="text-end">Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in topProductos" :key="p.id">
                            <td class="panorama-ranking-puesto">{{ i + 1 }}</td>
                            <td class="panorama-ranking-nombre">{{ p.nombre }}</td>
                            <td>
                                <span class="panorama-ranking-barra">
                                    <span><span :style="{ width: `${p.ancho}%` }"></span></span>
                                    <span class="financiero-num">{{ money(p.ingreso) }}</span>
                                </span>
                            </td>
                            <td class="text-end financiero-num">{{ p.margen === null ? '—' : porcentaje(p.margen, 1) }}</td>
                        </tr>
                    </tbody>
                </table>
            </ChartCard>
        </div>

        <!-- ==================== 4. DISTRIBUCIÓN ==================== -->
        <h2 class="panorama-seccion">4. Distribución comercial</h2>

        <div class="panorama-fila">
            <ChartCard tema="Ventas por vendedor" :hallazgo="datos.por_vendedor.hallazgo" hallazgo-abajo
                ayuda="bi-panorama.vendedor" :cargando="cargando">
                <BaseChart titulo="Ventas por vendedor" tipo="bar" horizontal :labels="vendedores.labels"
                    :series="vendedores.series" :formato="moneyCompacto" :formato-tooltip="money"
                    :alto="Math.max(220, vendedores.labels.length * 34)" vacio="No hubo ventas en el periodo." />
            </ChartCard>

            <ChartCard tema="Ventas por ciudad" :hallazgo="datos.por_ciudad.hallazgo" hallazgo-abajo
                ayuda="bi-panorama.ciudad" :cargando="cargando">
                <BaseChart titulo="Ventas por ciudad" tipo="bar" horizontal :labels="ciudades.labels"
                    :series="ciudades.series" :formato="moneyCompacto" :formato-tooltip="money"
                    :alto="Math.max(220, ciudades.labels.length * 34)" vacio="No hubo ventas en el periodo." />
            </ChartCard>
        </div>

        <p class="panorama-nota-final">
            Los gráficos abrevian los montos para facilitar la lectura; las tablas y el botón "Tabla" muestran el monto completo.
        </p>
    </div>
</template>
