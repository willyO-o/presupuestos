<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import { useChartTheme } from '@/Composables/UseChartTheme.js';
import { userCan } from '@/Directives/Can.js';

defineOptions({ layout: MainDashboardLayout });

// `v-can` oculta un elemento con `hidden` y no aplica a un <template>; acá
// son dos enlaces, así que se decide una vez y con v-if.
const puedeExportar = userCan('reportes.exportar_financiero');

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { tema } = useChartTheme();

const rango = reactive({
    desde: props.filters.desde ?? props.datos.rango.desde,
    hasta: props.filters.hasta ?? props.datos.rango.hasta,
});

function aplicar() {
    router.get(route('reportes.financiero'), { ...rango }, { preserveState: true, preserveScroll: true });
}

/**
 * El PDF sale con las fechas que se ven en el filtro, aunque todavía no se
 * haya pulsado "Aplicar" (mismo criterio que el informe del BI).
 */
function urlPdf(descargar = false) {
    return route('reportes.financiero.pdf', { ...rango, ...(descargar ? { descargar: 1 } : {}) });
}

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** En los ejes el importe va sin centavos: si no, las etiquetas se pisan. */
function moneyCorto(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 })}`;
}

const ventas = computed(() => ({
    labels: props.datos.ventas_por_mes.map((m) => m.mes),
    series: [{ nombre: 'Ventas', datos: props.datos.ventas_por_mes.map((m) => m.total), area: true }],
}));

const sucursales = computed(() => ({
    labels: props.datos.ventas_por_sucursal.map((s) => s.sucursal),
    series: [{ nombre: 'Vendido', datos: props.datos.ventas_por_sucursal.map((s) => s.total) }],
}));

const rentabilidadOrdenada = computed(() => [...props.datos.rentabilidad].sort((a, b) => a.margen - b.margen));

/**
 * Margen real por pedido, ordenado de peor a mejor.
 *
 * Es el único gráfico del sistema que usa colores de ESTADO en vez de la paleta
 * de series: acá el color no dice qué pedido es, dice si ganó o perdió plata.
 * El signo además se ve por el lado de la barra respecto del cero, así que
 * quien no distinga verde de rojo lo lee igual.
 */
const margenes = computed(() => ({
    labels: rentabilidadOrdenada.value.map((r) => r.pedido),
    series: [{
        nombre: 'Margen real',
        datos: rentabilidadOrdenada.value.map((r) => r.margen),
        colores: rentabilidadOrdenada.value.map(
            (r) => (r.margen < 0 ? tema.value?.negativo : tema.value?.positivo),
        ),
    }],
}));
</script>

<template>
    <Head title="Reporte financiero" />

    <div class="d-flex justify-content-end mb-3">
        <EtiquetaAlcance />
    </div>

    <div class="page-stack">
        <!-- Un solo filtro arriba de todo lo que acota: los gráficos de abajo se
             redibujan todos contra el mismo periodo. -->
        <div class="card">
            <div class="card-body">
                <form class="row" @submit.prevent="aplicar">
                    <div class="col-lg-5">
                        <DateRangeFilter
                            v-model:fecha-desde="rango.desde"
                            v-model:fecha-hasta="rango.hasta"
                            label="Periodo"
                            default-range="Este año"
                        />
                    </div>
                    <div class="col-lg-7 flex flex-wrap items-end gap-2">
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                        <!-- Vista previa en pestaña nueva; el botón de al lado
                             baja el archivo (App\Services\Pdf\GeneradorPdf). -->
                        <template v-if="puedeExportar">
                            <a :href="urlPdf()" target="_blank" rel="noopener" class="btn btn-soft-primary">
                                <i class="fa-solid fa-file-pdf"></i>
                                Ver PDF
                            </a>
                            <a :href="urlPdf(true)" class="btn btn-soft-secondary btn-icon"
                                aria-label="Descargar el reporte financiero en PDF" title="Descargar PDF">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </template>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="stat-card card">
                    <div class="stat-icon stat-icon-success"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div>
                        <p class="text-muted fs-sm mb-0">Total vendido en el periodo</p>
                        <p class="fs-xl fw-bold mb-0">{{ money(datos.total_vendido) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="stat-card card">
                    <div class="stat-icon stat-icon-warning"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div>
                        <p class="text-muted fs-sm mb-0">Cuentas por cobrar</p>
                        <p class="fs-xl fw-bold mb-0">{{ money(datos.cuentas_por_cobrar.total) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Ventas por mes</span>
                    <p class="card-subtitle">Cotizaciones aprobadas y convertidas dentro del periodo</p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Ventas por mes" :labels="ventas.labels" :series="ventas.series" :formato="moneyCorto"
                    :alto="300" vacio="No hubo ventas en el periodo seleccionado." />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><span class="card-title">Ventas por sucursal</span></div>
                    <div class="card-body">
                        <BaseChart titulo="Ventas por sucursal" tipo="bar" horizontal :labels="sucursales.labels"
                            :series="sucursales.series" :formato="moneyCorto" :alto="260"
                            vacio="Sin ventas por sucursal en el periodo." />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Margen real por pedido</span>
                            <p class="card-subtitle">De peor a mejor · rojo = el pedido costó más de lo que dejó</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Margen real por pedido" tipo="bar" horizontal :labels="margenes.labels"
                            :series="margenes.series" :formato="moneyCorto" :alto="260"
                            vacio="Todavía no hay pedidos con costo real registrado." />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Rentabilidad real por pedido</span>
                    <p class="card-subtitle">Ingreso cobrado contra el costo de material realmente consumido</p>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table-dashboard table-sm">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th class="text-end">Ingreso</th>
                                <th class="text-end">Costo real</th>
                                <th class="text-end">Margen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rentabilidadOrdenada" :key="r.pedido">
                                <td>{{ r.pedido }}</td>
                                <td class="text-end">{{ money(r.ingreso) }}</td>
                                <td class="text-end">{{ money(r.costo_real) }}</td>
                                <td class="text-end fw-semibold" :class="r.margen < 0 ? 'text-danger' : 'text-success'">
                                    {{ money(r.margen) }}
                                    <span v-if="r.margen_pct !== null" class="fs-xs text-muted">
                                        ({{ r.margen_pct }}%)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
