<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import { porcentaje } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/** Etiqueta e icono de cada regla de `AlertasNegocioService`. */
const TIPOS = {
    costo_material: { texto: 'Aumento de costo', icono: 'fa-solid fa-arrow-trend-up' },
    margen_bajo: { texto: 'Margen bajo', icono: 'fa-solid fa-percent' },
    presupuesto_desactualizado: { texto: 'Presupuesto desactualizado', icono: 'fa-solid fa-file-circle-exclamation' },
    sobrecosto_pedido: { texto: 'Sobrecosto en pedido', icono: 'fa-solid fa-industry' },
    tendencia_costos: { texto: 'Tendencia de costos', icono: 'fa-solid fa-chart-line' },
};

const filtro = ref('todas');

const tiposPresentes = computed(() => Object.keys(props.datos.resumen.por_tipo));

const visibles = computed(() => (filtro.value === 'todas'
    ? props.datos.alertas
    : props.datos.alertas.filter((a) => a.tipo === filtro.value)));

/* ----- Anomalías detectadas (DeteccionAnomaliasService) ------------------- */

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
    { valor: 'NUEVA', texto: 'Nuevas' },
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
    <Head title="Alertas de costo y margen" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <section id="anomalias" class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Anomalías detectadas</span>
                    <p class="card-subtitle">
                        Detección estadística diaria: caídas de cotizaciones, ventas con margen muy bajo, vendedores sin
                        actividad y clientes grandes que dejaron de comprar.
                        <span v-if="ultimaDeteccion">Última revisión: {{ ultimaDeteccion }}.</span>
                    </p>
                </div>
                <button v-if="anomalias.puede_gestionar" type="button" class="btn btn-sm btn-outline-primary" :disabled="detectando"
                    @click="detectar">
                    <i class="fa-solid fa-magnifying-glass-chart"></i> {{ detectando ? 'Revisando…' : 'Revisar ahora' }}
                </button>
            </div>
            <div class="card-body">
                <div class="anomalia-filtro" role="group" aria-label="Filtrar anomalías por estado">
                    <button v-for="e in ESTADOS_ANOMALIA" :key="e.valor" type="button" class="anomalia-filtro-opcion"
                        :class="{ 'is-active': anomalias.estado === e.valor }" :aria-pressed="anomalias.estado === e.valor"
                        @click="filtrarAnomalias(e.valor)">
                        {{ e.texto }} <span class="anomalia-filtro-conteo">{{ conteo(e.valor) }}</span>
                    </button>
                </div>

                <ul v-if="anomalias.items.length" class="anomalias">
                    <li v-for="a in anomalias.items" :key="a.id" class="anomalia" :class="[`anomalia-${a.severidad.toLowerCase()}`, { 'is-cerrada': a.estado !== 'NUEVA' }]">
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
                            ? 'No hay anomalías nuevas. La revisión corre sola cada día a las 06:30.'
                            : 'No hay anomalías en este estado.' }}
                    </p>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Alertas activas" icono="fa-solid fa-bell" tono="primary"
                    :valor="String(datos.resumen.total)" nota="En el periodo seleccionado" />
            </div>
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Prioridad alta" icono="fa-solid fa-circle-exclamation" tono="danger"
                    :valor="String(datos.resumen.alta)" nota="Requieren acción ya" />
            </div>
            <div class="col-lg-4 col-md-6">
                <BiKpi etiqueta="Prioridad media" icono="fa-solid fa-triangle-exclamation" tono="warning"
                    :valor="String(datos.resumen.media)" nota="Revisar esta semana" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Alertas de costo y margen</span>
                    <p class="card-subtitle">
                        Aumentos de costo ≥ {{ porcentaje(datos.umbrales.aumento_costo) }} · margen por debajo de
                        {{ porcentaje(datos.umbrales.margen_verde) }} del costo (semáforo) · tendencia ≥
                        {{ porcentaje(datos.umbrales.tendencia_mensual) }} por mes
                    </p>
                </div>
                <select v-if="tiposPresentes.length > 1" v-model="filtro" class="form-control bi-select"
                    aria-label="Filtrar alertas por tipo">
                    <option value="todas">Todas ({{ datos.resumen.total }})</option>
                    <option v-for="t in tiposPresentes" :key="t" :value="t">
                        {{ TIPOS[t]?.texto ?? t }} ({{ datos.resumen.por_tipo[t] }})
                    </option>
                </select>
            </div>
            <div class="card-body">
                <ul v-if="visibles.length" class="bi-alertas">
                    <li v-for="(a, i) in visibles" :key="`${a.tipo}-${i}`" class="bi-alerta"
                        :class="`bi-alerta-${a.severidad}`">
                        <span class="bi-alerta-icono"><i :class="TIPOS[a.tipo]?.icono ?? 'fa-solid fa-bell'"></i></span>
                        <div class="bi-alerta-cuerpo">
                            <div class="bi-alerta-encabezado">
                                <span class="badge" :class="a.severidad === 'alta' ? 'badge-soft-danger' : 'badge-soft-warning'">
                                    {{ a.severidad === 'alta' ? 'Alta' : 'Media' }}
                                </span>
                                <span class="bi-alerta-tipo">{{ TIPOS[a.tipo]?.texto ?? a.tipo }}</span>
                            </div>
                            <p class="bi-alerta-titulo">{{ a.titulo }}</p>
                            <p class="bi-alerta-detalle">{{ a.detalle }}</p>
                        </div>
                        <Link v-if="a.url" :href="a.url" class="btn btn-sm btn-outline-primary bi-alerta-accion">
                            Ver <i class="fa-solid fa-arrow-right"></i>
                        </Link>
                    </li>
                </ul>
                <div v-else class="bi-alertas-vacio">
                    <i class="fa-solid fa-circle-check"></i>
                    <p class="mb-0">Sin alertas en el periodo: ningún costo subió por encima del umbral y los presupuestos
                        vivos están en verde.</p>
                </div>
            </div>
        </div>

        <p class="fs-xs text-muted mb-0">
            Las alertas se calculan al abrir esta pantalla con los umbrales de <code>config/bi.php</code> y del semáforo de
            margen (<code>config/margen.php</code>). Los precios de material son de toda la empresa; presupuestos y pedidos
            respetan las sucursales que ves.
        </p>
    </div>
</template>
