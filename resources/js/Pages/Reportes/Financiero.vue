<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import LineChart from '@/Components/Chart/LineChart.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const rango = reactive({
    desde: props.filters.desde ?? props.datos.rango.desde,
    hasta: props.filters.hasta ?? props.datos.rango.hasta,
});

function aplicar() {
    router.get(route('reportes.financiero'), { ...rango }, { preserveState: true, preserveScroll: true });
}

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const ventasSerie = computed(() => [{
    nombre: 'Ventas',
    valores: props.datos.ventas_por_mes.map((m) => m.total),
}]);
const ventasLabels = computed(() => props.datos.ventas_por_mes.map((m) => m.mes));

const sucursalMax = computed(() => Math.max(1, ...props.datos.ventas_por_sucursal.map((s) => s.total)));

const rentabilidadOrdenada = computed(() => [...props.datos.rentabilidad].sort((a, b) => a.margen - b.margen));
</script>

<template>
    <Head title="Reporte financiero" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="aplicar">
                <div class="col-lg-3">
                    <label class="form-label">Desde</label>
                    <input v-model="rango.desde" type="date" class="form-control" />
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Hasta</label>
                    <input v-model="rango.hasta" type="date" class="form-control" />
                </div>
                <div class="col-lg-3 flex items-end">
                    <button type="submit" class="btn btn-primary">Aplicar</button>
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

    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Ventas por mes</span></div>
        <div class="card-body text-primary">
            <LineChart :series="ventasSerie" :labels="ventasLabels" :format-y="money" />
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><span class="card-title">Ventas por sucursal</span></div>
                <div class="card-body">
                    <div v-for="s in datos.ventas_por_sucursal" :key="s.sucursal" class="mb-3">
                        <div class="d-flex justify-content-between fs-sm">
                            <span>{{ s.sucursal }}</span><span class="fw-semibold">{{ money(s.total) }}</span>
                        </div>
                        <div class="reporte-progress">
                            <div class="reporte-progress-bar" :style="{ width: (s.total / sucursalMax) * 100 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><span class="card-title">Rentabilidad real por pedido</span></div>
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
                                    <td class="text-end fw-semibold"
                                        :class="r.margen < 0 ? 'text-danger' : 'text-success'">
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
    </div>
</template>
