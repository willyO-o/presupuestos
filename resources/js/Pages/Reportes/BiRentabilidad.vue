<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { money, moneyCorto, numero, porcentaje } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const futuros = computed(() => props.datos.meses_proyeccion);

const vacioFuturo = computed(() => Array(futuros.value.length).fill(null));
const labelsConFuturo = computed(() => [...props.datos.labels, ...futuros.value]);

/* ----- Pronóstico de presupuestos ---------------------------------------- */

const pronostico = computed(() => {
    const p = props.datos.pronostico;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Monto emitido', datos: [...p.monto_emitido, ...vacioFuturo.value], area: true },
            { nombre: 'Monto aprobado', datos: [...p.monto_aprobado, ...vacioFuturo.value] },
            ...seriesProyectadas(p.monto_emitido, p.proyeccion_emitido, p.metodo, 'Proyección emitido'),
        ],
    };
});

/** Cantidad emitida contra aprobada, mes a mes. */
const cantidades = computed(() => {
    const p = props.datos.pronostico;

    return {
        labels: props.datos.labels,
        series: [
            { nombre: 'Emitidos', tipo: 'bar', datos: p.emitidos },
            { nombre: 'Aprobados', tipo: 'bar', datos: p.aprobados },
        ],
    };
});

/* ----- Tendencia ticket vs costo ----------------------------------------- */

const tendencia = computed(() => ({
    labels: props.datos.labels,
    series: [
        { nombre: 'Ticket promedio', datos: props.datos.tendencia.ticket },
        { nombre: 'Costo promedio', datos: props.datos.tendencia.costo },
    ],
}));

/**
 * Lectura en una frase de las dos pendientes: si el costo crece más rápido
 * que el ticket, el margen se achica aunque se venda más.
 */
const lecturaTendencia = computed(() => {
    const { variacion_ticket: ticket, variacion_costo: costo } = props.datos.tendencia;

    if (ticket === null || costo === null) {
        return 'Se necesitan al menos 3 meses con presupuestos (y con hoja de costos) para comparar tendencias.';
    }

    return costo > ticket
        ? 'El costo promedio crece más rápido que el ticket: el margen se está achicando. Revisar precios o márgenes por tipo de proyecto.'
        : 'El ticket promedio crece al menos al ritmo del costo: el margen se sostiene.';
});

/* ----- Rentabilidad ------------------------------------------------------- */

const rentabilidad = computed(() => {
    const r = props.datos.rentabilidad;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Rentabilidad', datos: [...r.porcentaje, ...vacioFuturo.value] },
            ...seriesProyectadas(r.porcentaje, r.proyeccion_porcentaje, r.metodo),
        ],
        umbrales: [
            { valor: r.umbrales.verde, nombre: `Verde ${r.umbrales.verde} %` },
            { valor: r.umbrales.amarillo, nombre: `Amarillo ${r.umbrales.amarillo} %` },
        ],
    };
});

const utilidad = computed(() => {
    const r = props.datos.rentabilidad;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Utilidad real', tipo: 'bar', datos: [...r.utilidad, ...vacioFuturo.value] },
            ...seriesProyectadas(r.utilidad, r.proyeccion_utilidad, r.metodo_utilidad),
        ],
    };
});

const inicioFuturo = computed(() => futuros.value[0] ?? null);

const rentabilidadProyectada = computed(() => props.datos.rentabilidad.proyeccion_porcentaje.at(-1)?.valor ?? null);

const tonoRentabilidad = computed(() => {
    const valor = props.datos.rentabilidad.rentabilidad_promedio;
    const { verde, amarillo } = props.datos.rentabilidad.umbrales;

    if (valor === null) {
        return 'info';
    }

    return valor > verde ? 'success' : valor > amarillo ? 'warning' : 'danger';
});

function pct(value) {
    return porcentaje(value);
}
</script>

<template>
    <Head title="Presupuestos y rentabilidad" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Presupuestos emitidos" ayuda="bi-rentabilidad.kpi-emitidos" icono="fa-solid fa-file-invoice" tono="primary"
                    :valor="String(datos.resumen.emitidos)" :variacion="datos.pronostico.variacion_mensual"
                    :nota="`${money(datos.resumen.monto_emitido)} en total`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Tasa de aprobación" ayuda="bi-rentabilidad.kpi-aprobacion" icono="fa-solid fa-handshake" tono="success"
                    :valor="porcentaje(datos.resumen.tasa_conversion)"
                    :nota="`${datos.resumen.aprobados} aprobados · ${datos.resumen.pendientes} pendientes (no cuentan)`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Rentabilidad sobre costo" ayuda="bi-rentabilidad.kpi-rentabilidad" icono="fa-solid fa-percent" :tono="tonoRentabilidad"
                    :valor="porcentaje(datos.rentabilidad.rentabilidad_promedio)"
                    :variacion="datos.rentabilidad.variacion_mensual"
                    :nota="`Utilidad real ${money(datos.rentabilidad.utilidad_total)}`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Rentabilidad proyectada" ayuda="bi-rentabilidad.kpi-proyectada" icono="fa-solid fa-binoculars" tono="info"
                    :valor="porcentaje(rentabilidadProyectada)"
                    :nota="rentabilidadProyectada === null ? 'Faltan meses con datos' : `Estimada para ${futuros.at(-1)}`" />
            </div>
        </div>

        <!-- Pronóstico de presupuestos -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Pronóstico de presupuestos <AyudaInfo clave="bi-rentabilidad.pronostico" /></span>
                    <p class="card-subtitle">
                        Monto emitido y aprobado por mes, con la tendencia del monto emitido a {{ futuros.length }} meses
                        <span v-if="!datos.pronostico.proyeccion_emitido.length">· se necesitan al menos 3 meses en el periodo para proyectar</span>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Pronóstico de presupuestos" :labels="pronostico.labels" :series="pronostico.series"
                    :formato="moneyCorto" :alto="300" :inicio-proyeccion="inicioFuturo"
                    vacio="No hay presupuestos emitidos en el periodo." />
                <MetodoPronostico :metodo="datos.pronostico.metodo" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Presupuestos por mes <AyudaInfo clave="bi-rentabilidad.cantidades" /></span>
                            <p class="card-subtitle">Cantidad emitida y aprobada</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Presupuestos por mes" tipo="bar" :labels="cantidades.labels"
                            :series="cantidades.series" :formato="(v) => numero(v, 0)" :alto="260" enteros
                            vacio="No hay presupuestos emitidos en el periodo." />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Tendencia: ticket vs. costo <AyudaInfo clave="bi-rentabilidad.tendencia" /></span>
                            <p class="card-subtitle">Monto promedio por presupuesto contra su costo ajustado promedio</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Tendencia de ticket y costo promedio" :labels="tendencia.labels"
                            :series="tendencia.series" :formato="moneyCorto" :alto="220"
                            vacio="No hay presupuestos emitidos en el periodo." />
                        <p class="bi-aviso mt-3 mb-0">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>{{ lecturaTendencia }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Proyección de rentabilidad -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Proyección de rentabilidad</span>
                    <p class="card-subtitle">
                        Utilidad real ÷ costo ajustado de los presupuestos aprobados, contra los umbrales del semáforo
                    </p>
                </div>
            </div>
            <div class="card-body">
                <p v-if="datos.rentabilidad.excluidos_sin_costo" class="bi-aviso mb-3">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>
                        {{ datos.rentabilidad.excluidos_sin_costo }} presupuesto(s) aprobado(s) del periodo no tienen hoja de
                        costos (son anteriores al motor de margen) y no entran en este cálculo: su utilidad aparecería
                        como el 100 % del precio. Se analizan {{ datos.rentabilidad.analizados }}.
                    </span>
                </p>
                <div class="row">
                    <div class="col-lg-7">
                        <p class="chart-heading">Rentabilidad por mes <AyudaInfo clave="bi-rentabilidad.rentabilidad" /></p>
                        <BaseChart titulo="Rentabilidad por mes" :labels="rentabilidad.labels" :series="rentabilidad.series"
                            :formato="pct" :alto="280" :umbrales="rentabilidad.umbrales" :inicio-proyeccion="inicioFuturo"
                            unir-huecos vacio="Ningún presupuesto aprobado del periodo tiene hoja de costos." />
                        <MetodoPronostico :metodo="datos.rentabilidad.metodo" />
                    </div>
                    <div class="col-lg-5">
                        <p class="chart-heading">Utilidad real por mes <AyudaInfo clave="bi-rentabilidad.utilidad" /></p>
                        <BaseChart titulo="Utilidad real por mes" :labels="utilidad.labels" :series="utilidad.series"
                            :formato="moneyCorto" :alto="280" :inicio-proyeccion="inicioFuturo"
                            vacio="Ningún presupuesto aprobado del periodo tiene hoja de costos." />
                        <MetodoPronostico :metodo="datos.rentabilidad.metodo_utilidad" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Semáforo de los presupuestos del periodo <AyudaInfo clave="bi-rentabilidad.semaforo" /></span>
                    <p class="card-subtitle">Cómo salieron todos los presupuestos emitidos según el motor de margen</p>
                </div>
            </div>
            <div class="card-body">
                <!-- Cuatro números sueltos: fila de KPIs, no un gráfico. -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="reporte-kpi">
                            <span class="reporte-kpi-value">{{ datos.rentabilidad.semaforo.VERDE }}</span>
                            <span class="semaforo semaforo-verde"><span class="semaforo-punto"></span>Aceptar</span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="reporte-kpi">
                            <span class="reporte-kpi-value">{{ datos.rentabilidad.semaforo.AMARILLO }}</span>
                            <span class="semaforo semaforo-amarillo"><span class="semaforo-punto"></span>Revisar precio</span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="reporte-kpi">
                            <span class="reporte-kpi-value">{{ datos.rentabilidad.semaforo.ROJO }}</span>
                            <span class="semaforo semaforo-rojo"><span class="semaforo-punto"></span>No aceptar</span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="reporte-kpi">
                            <span class="reporte-kpi-value">{{ datos.rentabilidad.semaforo.SIN_COSTO }}</span>
                            <span class="reporte-kpi-label">Sin hoja de costos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
