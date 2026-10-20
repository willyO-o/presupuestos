<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { useChartTheme } from '@/Composables/UseChartTheme.js';
import { money, moneyCorto, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { tema } = useChartTheme();

/**
 * Une una serie histórica con su proyección sobre un mismo eje de tiempo.
 * La proyección y su banda las arma Utils/SerieProyectada.js; el histórico
 * queda vacío en los meses futuros.
 */
function conProyeccion(labels, historico, proyeccion, metodo, nombre, { area = false } = {}) {
    const futuros = props.datos.meses_proyeccion.slice(0, proyeccion.length);
    const vacio = Array(futuros.length).fill(null);

    return {
        labels: [...labels, ...futuros],
        inicio: futuros[0] ?? null,
        series: [
            { nombre, datos: [...historico, ...vacio], area },
            ...seriesProyectadas(historico, proyeccion, metodo),
        ],
    };
}

/* ----- Índice de costo de materiales ----------------------------------- */

const indice = computed(() => conProyeccion(
    props.datos.indice.labels,
    props.datos.indice.serie,
    props.datos.indice.proyeccion,
    props.datos.indice.metodo,
    'Índice de costo',
));

function puntos(value) {
    return numero(value, 1);
}

/** Unidad de medida del material (enum de `material.unidad_medida`) legible. */
const UNIDADES = { M2: 'm²', METRO_LINEAL: 'm lineal', UNIDAD: 'u.', LITRO: 'l' };

function unidad(valor) {
    return UNIDADES[valor] ?? valor ?? '';
}

/* ----- Proyección por material ------------------------------------------ */

const materialElegido = ref(props.datos.materiales[0]?.id ?? null);

const material = computed(() => props.datos.materiales.find((m) => m.id === materialElegido.value) ?? null);

const materialGrafico = computed(() => (material.value
    ? conProyeccion(props.datos.indice.labels, material.value.serie, material.value.proyeccion, material.value.metodo, 'Precio unitario')
    : { labels: [], series: [], inicio: null }));

const precioProyectado = computed(() => material.value?.proyeccion.at(-1)?.valor ?? null);

/* ----- Consumo ---------------------------------------------------------- */

const consumoMensual = computed(() => {
    const base = conProyeccion(
        props.datos.consumo.labels,
        props.datos.consumo.serie,
        props.datos.consumo.proyeccion,
        props.datos.consumo.metodo,
        'Costo consumido',
        { area: true },
    );
    const vacio = Array(base.labels.length - props.datos.consumo.labels.length).fill(null);

    base.series.splice(1, 0, { nombre: 'Media móvil 3m', datos: [...props.datos.consumo.media_movil, ...vacio] });

    return base;
});

/**
 * Real contra presupuestado, por material. Solo se agrega la serie
 * presupuestada cuando existe: sin hojas de costo en el periodo sería una
 * columna de ceros que se lee como "presupuestamos cero".
 */
const consumoPorMaterial = computed(() => {
    const filas = props.datos.consumo.por_material;

    return {
        labels: filas.map((f) => f.material),
        series: [
            { nombre: 'Consumo real', datos: filas.map((f) => f.costo_real) },
            ...(props.datos.consumo.tiene_presupuestado
                ? [{ nombre: 'Presupuestado', datos: filas.map((f) => f.costo_presupuestado) }]
                : []),
        ],
    };
});

/* ----- Impacto de variaciones ------------------------------------------- */

/**
 * Variación de precio por material. Usa colores de ESTADO (no de serie): un
 * insumo que sube es malo para el margen, uno que baja es bueno. El signo
 * además se ve por el lado de la barra respecto del cero.
 */
const impactoGrafico = computed(() => {
    const filas = props.datos.impacto.materiales;

    return {
        labels: filas.map((f) => f.material),
        series: [{
            nombre: 'Variación de precio',
            datos: filas.map((f) => f.variacion_pct),
            colores: filas.map((f) => (f.variacion_pct > 0 ? tema.value?.negativo : tema.value?.positivo)),
        }],
    };
});
</script>

<template>
    <Head title="Costos y materiales" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Índice de costo (fin del periodo)" ayuda="bi-costos.kpi-indice" icono="fa-solid fa-gauge-high" tono="warning"
                    :valor="datos.indice.variacion_periodo === null ? '—' : variacion(datos.indice.variacion_periodo)"
                    :variacion="datos.indice.variacion_mensual" :subir-es-bueno="false"
                    nota="Frente al primer precio del periodo" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Material consumido" ayuda="bi-costos.kpi-consumido" icono="fa-solid fa-boxes-stacked" tono="primary"
                    :valor="money(datos.consumo.total)" :variacion="datos.consumo.variacion_mensual"
                    :subir-es-bueno="false" :nota="`${datos.consumo.materiales_distintos} materiales distintos`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Impacto de las variaciones" ayuda="bi-costos.kpi-impacto" icono="fa-solid fa-scale-unbalanced"
                    :tono="datos.impacto.total > 0 ? 'danger' : 'success'" :valor="money(datos.impacto.total)"
                    :nota="`${datos.impacto.suben} suben · ${datos.impacto.bajan} bajan`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Materiales con historial" ayuda="bi-costos.kpi-historial" icono="fa-solid fa-clock-rotate-left" tono="info"
                    :valor="String(datos.indice.materiales)" nota="Con 2 o más precios registrados" />
            </div>
        </div>

        <!-- Proyección de costos futuros -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Proyección de costos futuros <AyudaInfo clave="bi-costos.indice" /></span>
                    <EtiquetaAlcance global texto="Precios" />
                    <p class="card-subtitle">
                        Índice de costo de materiales (100 = primer precio del periodo) y su tendencia lineal
                        <span v-if="!datos.indice.proyeccion.length">· se necesitan al menos 3 meses con precios para proyectar</span>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Índice de costo de materiales" :labels="indice.labels" :series="indice.series"
                    :formato="puntos" :alto="300" :inicio-proyeccion="indice.inicio" unir-huecos ajustar-escala
                    :umbrales="[{ valor: 100, nombre: 'Base 100' }]"
                    vacio="Se necesita más de un registro de precio por material (se genera al aprobar compras)." />
                <MetodoPronostico :metodo="datos.indice.metodo" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Proyección por material <AyudaInfo clave="bi-costos.material" /></span>
                            <p class="card-subtitle">Precio unitario vigente cada mes y hacia dónde va</p>
                        </div>
                        <select v-if="datos.materiales.length" v-model="materialElegido" class="form-control bi-select"
                            aria-label="Material a proyectar">
                            <option v-for="m in datos.materiales" :key="m.id" :value="m.id">{{ m.material }}</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Proyección de precio por material" :labels="materialGrafico.labels"
                            :series="materialGrafico.series" :formato="moneyCorto" :alto="260" unir-huecos ajustar-escala
                            :inicio-proyeccion="materialGrafico.inicio"
                            vacio="Ningún material tiene historial de precios suficiente." />
                        <MetodoPronostico :metodo="material?.metodo" />
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><span class="card-title">Lectura del material <AyudaInfo clave="bi-costos.lectura" /></span></div>
                    <div class="card-body">
                        <div v-if="material" class="margen-panel">
                            <div class="margen-row">
                                <span>Precio actual</span>
                                <strong>{{ money(material.precio_actual) }}</strong>
                            </div>
                            <div class="margen-row">
                                <span>Tendencia</span>
                                <strong :class="material.variacion_mensual > 0 ? 'text-danger' : 'text-success'">
                                    {{ material.variacion_mensual === null ? '—' : `${variacion(material.variacion_mensual)} / mes` }}
                                </strong>
                            </div>
                            <div class="margen-row margen-row-destacada">
                                <span>Precio estimado en {{ datos.meses_proyeccion.at(-1) }}</span>
                                <strong>{{ precioProyectado === null ? '—' : money(precioProyectado) }}</strong>
                            </div>
                            <p class="fs-xs text-muted mb-0 mt-2">
                                Precio por {{ unidad(material.unidad) || 'unidad' }}. La estimación prolonga la recta de los precios
                                mensuales; el sombreado del gráfico es el intervalo de predicción.
                            </p>
                        </div>
                        <p v-else class="chart-empty">Sin materiales para analizar.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consumo de materiales -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Consumo de materiales <AyudaInfo clave="bi-costos.consumo" /></span>
                    <p class="card-subtitle">
                        Costo del material que producción registró en los pedidos, por mes, con su proyección
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Consumo mensual de materiales" :labels="consumoMensual.labels"
                    :series="consumoMensual.series" :formato="moneyCorto" :alto="280"
                    :inicio-proyeccion="consumoMensual.inicio"
                    vacio="Producción no registró consumo de materiales en el periodo." />
                <MetodoPronostico :metodo="datos.consumo.metodo" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Materiales más consumidos <AyudaInfo clave="bi-costos.mas-consumidos" /></span>
                            <p class="card-subtitle">
                                Top 10 por costo
                                <span v-if="datos.consumo.tiene_presupuestado">· real contra lo presupuestado en las hojas de costo</span>
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Materiales más consumidos" tipo="bar" horizontal
                            :labels="consumoPorMaterial.labels" :series="consumoPorMaterial.series" :formato="moneyCorto"
                            :alto="320" vacio="Sin consumo registrado en el periodo." />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Impacto de las variaciones de precio <AyudaInfo clave="bi-costos.variacion" /></span>
                            <EtiquetaAlcance global texto="Precios" />
                            <p class="card-subtitle">Cambio de precio en el periodo · rojo = subió, verde = bajó</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Variación de precio por material" tipo="bar" horizontal
                            :labels="impactoGrafico.labels" :series="impactoGrafico.series"
                            :formato="(v) => variacion(v)" :alto="320"
                            vacio="Ningún material cambió de precio en el periodo." />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="datos.impacto.materiales.length" class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Detalle del impacto en costos <AyudaInfo clave="bi-costos.detalle-impacto" /></span>
                    <p class="card-subtitle">
                        Impacto = (precio final − precio inicial) × cantidad consumida en el periodo
                    </p>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table-dashboard table-sm">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Precio inicial</th>
                                <th class="text-end">Precio final</th>
                                <th class="text-end">Variación</th>
                                <th class="text-end">Consumido</th>
                                <th class="text-end">Impacto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in datos.impacto.materiales" :key="f.material">
                                <td>{{ f.material }}</td>
                                <td class="text-end chart-table-num">{{ money(f.precio_inicio) }}</td>
                                <td class="text-end chart-table-num">{{ money(f.precio_fin) }}</td>
                                <td class="text-end chart-table-num fw-semibold"
                                    :class="f.variacion_pct > 0 ? 'text-danger' : 'text-success'">
                                    {{ variacion(f.variacion_pct) }}
                                </td>
                                <td class="text-end chart-table-num">{{ numero(f.cantidad_consumida, 2) }} {{ unidad(f.unidad) }}</td>
                                <td class="text-end chart-table-num fw-semibold"
                                    :class="f.impacto > 0 ? 'text-danger' : f.impacto < 0 ? 'text-success' : ''">
                                    {{ money(f.impacto) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="fs-xs text-muted mt-2 mb-0">
                    Total: {{ money(datos.impacto.total) }} · {{ porcentaje(datos.indice.variacion_periodo) }} de variación
                    promedio del índice. Un material sin consumo en el periodo tiene impacto 0 hoy, pero pesará en el
                    próximo pedido que lo use.
                </p>
            </div>
        </div>
    </div>
</template>
