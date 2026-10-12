<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
</script>

<template>
    <Head title="Alertas de costo y margen" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

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
