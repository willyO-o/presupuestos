<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import { money, moneyCorto, numero, porcentaje } from '@/Utils/FormatoReporte';
import { tiempoRelativo } from '@/Utils/TimeAgo';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    resumen: { type: Object, required: true },
});

const page = usePage();

/**
 * Variación contra el mismo corte del mes anterior (el servicio ya recorta el
 * mes pasado al mismo día). Sin base (mes anterior en cero) no hay porcentaje
 * honesto que mostrar: se dice así en vez de inventar un "+100 %".
 */
function comparacion(actual, anterior) {
    if (!anterior) {
        return null;
    }

    return ((actual - anterior) / anterior) * 100;
}

const etapasActivas = ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD'];

const etiquetaEtapa = {
    DISENO: 'Diseño',
    ELABORACION: 'Elaboración',
    ACABADO: 'Acabado',
    CONTROL_CALIDAD: 'Control de calidad',
    ENTREGADO: 'Entregado',
    CANCELADO: 'Cancelado',
};

const estadoBadge = {
    DISENO: 'badge-soft-secondary',
    ELABORACION: 'badge-soft-info',
    ACABADO: 'badge-soft-warning',
    CONTROL_CALIDAD: 'badge-soft-primary',
    ENTREGADO: 'badge-soft-success',
    CANCELADO: 'badge-soft-danger',
};

const pedidosEnCurso = computed(() =>
    etapasActivas.reduce((total, etapa) => total + (props.resumen.pedidos_por_etapa[etapa] ?? 0), 0),
);

const kpis = computed(() => [
    {
        label: 'Ventas del mes',
        value: moneyCorto(props.resumen.ventas_mes),
        icon: 'fa-solid fa-arrow-trend-up',
        tone: 'navy',
        delta: comparacion(props.resumen.ventas_mes, props.resumen.ventas_mes_anterior),
    },
    {
        label: 'Cotizaciones del mes',
        value: numero(props.resumen.cotizaciones_mes, 0),
        icon: 'fa-regular fa-file-lines',
        tone: 'cyan',
        delta: comparacion(props.resumen.cotizaciones_mes, props.resumen.cotizaciones_mes_anterior),
    },
    {
        label: 'Pedidos en curso',
        value: numero(pedidosEnCurso.value, 0),
        icon: 'fa-solid fa-cube',
        tone: 'violet',
        note: 'Entre diseño y control de calidad',
    },
    {
        label: 'Cobros del mes',
        value: moneyCorto(props.resumen.ingresos_mes),
        icon: 'fa-solid fa-wallet',
        tone: 'primary',
        delta: comparacion(props.resumen.ingresos_mes, props.resumen.ingresos_mes_anterior),
    },
    {
        label: 'Materiales con stock bajo',
        value: numero(props.resumen.materiales_bajo_stock, 0),
        icon: 'fa-solid fa-triangle-exclamation',
        tone: 'warning',
        // `global`: el stock no es de una sucursal (`material` no tiene
        // `sucursal_id`, el almacén es uno solo). Se marca para que no se lea
        // como el resto de la fila, que sí va acotada.
        global: true,
    },
]);

const anioActual = computed(() => props.resumen.ventas_por_mes[0]?.anio ?? new Date().getFullYear());

const ventas = computed(() => ({
    labels: props.resumen.ventas_por_mes.map((m) => m.mes),
    series: [
        { nombre: `Ventas ${anioActual.value}`, datos: props.resumen.ventas_por_mes.map((m) => m.total), area: true },
        { nombre: `Ventas ${anioActual.value - 1}`, datos: props.resumen.ventas_por_mes.map((m) => m.anterior) },
    ],
}));

const actividad = computed(() => (page.props.notificaciones?.recientes ?? []).slice(0, 5));

const etapas = computed(() =>
    etapasActivas.map((etapa) => {
        const cantidad = props.resumen.pedidos_por_etapa[etapa] ?? 0;

        return {
            etapa,
            nombre: etiquetaEtapa[etapa],
            cantidad,
            porcentaje: pedidosEnCurso.value ? (cantidad / pedidosEnCurso.value) * 100 : 0,
        };
    }),
);

/* La barra de cada producto se mide contra el más vendido (si se midiera
   contra el total, con muchos productos todas quedarían como un hilo); el
   número de al lado sí es la participación sobre el total vendido. */
const productos = computed(() => {
    const lista = props.resumen.productos_mas_vendidos;
    const maximo = Math.max(...lista.map((p) => p.monto), 0);

    return lista.map((p) => ({ ...p, ancho: maximo ? (p.monto / maximo) * 100 : 0 }));
});

const entregasTotal = computed(() => props.resumen.entregas.a_tiempo + props.resumen.entregas.tarde);
const entregasATiempo = computed(() =>
    entregasTotal.value ? (props.resumen.entregas.a_tiempo / entregasTotal.value) * 100 : null,
);

function fecha(value) {
    return value ? new Date(`${value}T00:00:00`).toLocaleDateString('es-BO') : '—';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="page-stack">
        <!-- Qué sucursales incluyen estos números: un KPI acotado y uno de toda la
             empresa se ven idénticos, y aquí no hay filas que falten para notarlo. -->
        <div class="dashboard-scope">
            <EtiquetaAlcance />
        </div>

        <div class="kpi-grid">
            <article v-for="kpi in kpis" :key="kpi.label" class="card kpi-card">
                <span class="stat-icon" :class="`stat-icon-${kpi.tone}`">
                    <i :class="kpi.icon" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <p class="kpi-label">{{ kpi.label }}</p>
                    <p class="kpi-value">{{ kpi.value }}</p>

                    <p v-if="kpi.global" class="stat-global">Toda la empresa</p>
                    <p v-else-if="kpi.note" class="kpi-note">{{ kpi.note }}</p>
                    <p
                        v-else-if="kpi.delta !== null"
                        class="kpi-delta"
                        :title="'Comparado con los mismos días del mes anterior'"
                    >
                        <span :class="kpi.delta >= 0 ? 'kpi-delta-up' : 'kpi-delta-down'">
                            <i :class="kpi.delta >= 0 ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'" aria-hidden="true"></i>
                            {{ porcentaje(Math.abs(kpi.delta)) }}
                        </span>
                        vs. mes anterior
                    </p>
                    <p v-else class="kpi-note">Sin movimiento el mes anterior</p>
                </div>
            </article>
        </div>

        <div class="dashboard-grid">
            <!-- Columna principal -->
            <div class="dashboard-main">
                <section class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Ventas por mes <AyudaInfo clave="dashboard.ventas-por-mes" /></h2>
                            <p class="card-subtitle mt-1">Total de cotizaciones aprobadas, {{ anioActual }} contra {{ anioActual - 1 }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart
                            titulo="Ventas por mes"
                            tipo="line"
                            :labels="ventas.labels"
                            :series="ventas.series"
                            :formato="moneyCorto"
                            :alto="280"
                            vacio="Todavía no hay cotizaciones aprobadas."
                        />
                    </div>
                </section>

                <div class="dashboard-split">
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Pedidos recientes</h2>
                            <Link v-can="'pedidos.ver'" :href="route('pedidos.index')" class="card-link">Ver todos</Link>
                        </div>
                        <div class="card-body pt-3">
                            <div v-if="resumen.pedidos_recientes.length" class="table-responsive">
                                <table class="table-dashboard dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>N.º pedido</th>
                                            <th>Cliente</th>
                                            <th class="hidden sm:table-cell">Fecha</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="pedido in resumen.pedidos_recientes" :key="pedido.id">
                                            <td>
                                                <Link :href="route('pedidos.show', pedido.id)" class="dashboard-table-link">
                                                    {{ pedido.numero_pedido }}
                                                </Link>
                                            </td>
                                            <td class="dashboard-table-cliente" :title="pedido.cliente ?? ''">{{ pedido.cliente ?? '—' }}</td>
                                            <td class="hidden sm:table-cell text-muted">{{ fecha(pedido.fecha) }}</td>
                                            <td class="text-end fw-semibold">{{ money(pedido.total) }}</td>
                                            <td class="text-center">
                                                <span class="badge" :class="estadoBadge[pedido.estado]">{{ etiquetaEtapa[pedido.estado] ?? pedido.estado }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="dashboard-empty">Todavía no hay pedidos registrados.</p>
                        </div>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Pedidos por etapa <AyudaInfo clave="dashboard.pedidos-por-etapa" /></h2>
                        </div>
                        <div class="card-body">
                            <p class="dashboard-total">
                                <span class="dashboard-total-value">{{ numero(pedidosEnCurso, 0) }}</span>
                                <span class="dashboard-total-label">pedidos en producción</span>
                            </p>
                            <ul class="dashboard-meters">
                                <li v-for="e in etapas" :key="e.etapa" class="dashboard-meter">
                                    <div class="dashboard-meter-head">
                                        <span>{{ e.nombre }}</span>
                                        <span class="dashboard-meter-value">{{ e.cantidad }} <small>· {{ porcentaje(e.porcentaje, 0) }}</small></span>
                                    </div>
                                    <div class="dashboard-meter-track">
                                        <div class="dashboard-meter-bar" :style="{ width: `${e.porcentaje}%` }"></div>
                                    </div>
                                </li>
                            </ul>
                            <p class="dashboard-footnote">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                {{ numero(resumen.pedidos_por_etapa.ENTREGADO ?? 0, 0) }} pedidos entregados en total
                            </p>
                        </div>
                    </section>
                </div>

                <section class="dashboard-cta">
                    <span class="dashboard-cta-icon"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="dashboard-cta-title">Impulsa tu negocio con <span>XtraPubli</span></h2>
                        <p class="dashboard-cta-text">
                            Convertiste el <strong>{{ porcentaje(resumen.tasa_conversion) }}</strong> de las cotizaciones decididas<template v-if="entregasATiempo !== null">
                                y entregaste a tiempo el <strong>{{ porcentaje(entregasATiempo, 0) }}</strong> de los pedidos</template>.
                        </p>
                    </div>
                    <Link v-can="'reportes.bi'" :href="route('reportes.bi')" class="btn btn-primary dashboard-cta-btn">
                        Ver reportes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </Link>
                </section>
            </div>

            <!-- Columna lateral -->
            <div class="dashboard-aside">
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fa-regular fa-clock" aria-hidden="true"></i> Actividad reciente</h2>
                        <Link :href="route('notificaciones.index')" class="card-link">Ver todas</Link>
                    </div>
                    <div class="card-body py-2">
                        <ul v-if="actividad.length" class="activity-list">
                            <li v-for="n in actividad" :key="n.id">
                                <Link :href="n.url" class="activity-item">
                                    <span class="activity-icon list-icon" :class="`stat-icon-${n.color}`">
                                        <i :class="n.icono" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="activity-title">{{ n.titulo }}</span>
                                        <span class="activity-text">{{ n.mensaje }}</span>
                                    </span>
                                    <span class="activity-time">{{ tiempoRelativo(n.creada_hace) }}</span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="dashboard-empty">Aquí aparecerán las aprobaciones, pagos y avances de pedidos.</p>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Productos más vendidos <AyudaInfo clave="dashboard.productos-mas-vendidos" /></h2>
                            <p class="card-subtitle mt-1">Últimos 12 meses · % del total vendido</p>
                        </div>
                        <Link v-can="'reportes.bi'" :href="route('reportes.bi')" class="card-link">Ver todos</Link>
                    </div>
                    <div class="card-body">
                        <ol v-if="productos.length" class="ranking-list">
                            <li v-for="(p, index) in productos" :key="p.nombre" class="ranking-item">
                                <span class="ranking-position">{{ index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="ranking-head">
                                        <span class="ranking-name" :title="p.nombre">{{ p.nombre }}</span>
                                        <span class="ranking-qty">{{ numero(p.cantidad, 0) }} uds.</span>
                                        <span class="ranking-share">{{ porcentaje(p.participacion, 0) }}</span>
                                    </div>
                                    <div class="dashboard-meter-track">
                                        <div class="dashboard-meter-bar" :style="{ width: `${p.ancho}%` }"></div>
                                    </div>
                                </div>
                            </li>
                        </ol>
                        <p v-else class="dashboard-empty">Sin ventas de productos en los últimos 12 meses.</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
