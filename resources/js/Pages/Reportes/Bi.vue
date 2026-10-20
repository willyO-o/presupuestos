<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { mesAnio } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 })}`;
}

function pedidos(value) {
    return Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 1 });
}

/**
 * Demanda: histórico, media móvil y proyección sobre una sola línea de tiempo.
 *
 * La proyección va punteada sobre una zona sombreada: es lo único del gráfico
 * que todavía no ocurrió, y esa distinción no puede depender solo del color.
 * La banda es su intervalo de predicción. Ver Utils/SerieProyectada.js.
 */
const demanda = computed(() => {
    const historico = props.datos.demanda.serie.map((s) => s.pedidos);
    const futuro = props.datos.demanda.proyeccion;
    const sinFuturo = Array(futuro.length).fill(null);

    return {
        labels: [...props.datos.demanda.serie.map((s) => s.mes), ...futuro.map((p) => p.mes)],
        inicio: futuro[0]?.mes ?? null,
        series: [
            { nombre: 'Pedidos reales', datos: [...historico, ...sinFuturo], area: true },
            { nombre: 'Media móvil 3m', datos: [...props.datos.demanda.media_movil.map((m) => m.valor), ...sinFuturo] },
            ...seriesProyectadas(
                historico,
                futuro.map((p) => ({ valor: p.pedidos_estimados, inferior: p.inferior, superior: p.superior })),
                props.datos.demanda.metodo,
            ),
        ],
    };
});

/** Los diez primeros ya vienen ordenados por monto desde el servicio. */
const productos = computed(() => ({
    labels: props.datos.productos_mas_vendidos.map((p) => p.nombre),
    series: [{ nombre: 'Vendido', datos: props.datos.productos_mas_vendidos.map((p) => p.monto) }],
}));

const categorias = computed(() => ({
    labels: props.datos.categorias_mas_vendidas.map((c) => c.nombre),
    series: [{ nombre: 'Vendido', datos: props.datos.categorias_mas_vendidas.map((c) => c.monto) }],
}));

/**
 * Evolución de costos: cada material tiene sus propias fechas de compra, así
 * que el eje es la UNIÓN de todas y cada serie deja huecos donde no hubo
 * registro. Tomar las fechas del primer material —como se hacía antes— alinea
 * mal a los demás y dibuja precios en fechas que no les corresponden.
 *
 * Tope de cuatro materiales: más series de las que el ojo puede seguir en un
 * gráfico de líneas, y la paleta no se cicla.
 */
const costos = computed(() => {
    const materiales = props.datos.evolucion_costos.slice(0, 4);

    const fechas = [...new Set(materiales.flatMap((m) => m.puntos.map((p) => p.fecha)))].sort();

    return {
        labels: fechas.map((f) => mesAnio(f)),
        series: materiales.map((m) => {
            const porFecha = Object.fromEntries(m.puntos.map((p) => [p.fecha, p.precio]));

            return { nombre: m.material, datos: fechas.map((f) => porFecha[f] ?? null) };
        }),
    };
});

/**
 * Estacionalidad: el promedio y una línea por gestión sobre el mismo eje
 * Ene–Dic, para ver si un mes es fuerte siempre o solo lo fue un año. La
 * leyenda prende y apaga cada gestión (hace de selector sin perder la
 * comparación). Los `null` (meses sin historia o que todavía no llegaron)
 * cortan la línea en vez de bajarla a cero.
 */
const estacionalidad = computed(() => {
    const e = props.datos.estacionalidad;

    // Con una sola gestión el promedio es esa misma línea encimada: se omite.
    const promedio = e.gestiones.length > 1 ? [{ nombre: 'Promedio', datos: e.promedio, area: true }] : [];

    return {
        labels: e.meses,
        series: [...promedio, ...e.gestiones.map((g) => ({ nombre: String(g.anio), datos: g.pedidos }))],
    };
});

const materialesOcultos = computed(() => Math.max(props.datos.evolucion_costos.length - 4, 0));
</script>

<template>
    <Head title="Inteligencia de negocios" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Proyección de demanda <AyudaInfo clave="bi.proyeccion-demanda" /></span>
                    <p class="card-subtitle">
                        Pedidos por mes, media móvil de 3 meses y tendencia lineal a {{ datos.demanda.proyeccion.length || 3 }} meses
                        <span v-if="!datos.demanda.proyeccion.length">· se necesitan al menos 3 meses en el periodo para proyectar</span>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Proyección de demanda" :labels="demanda.labels" :series="demanda.series"
                    :formato="pedidos" :alto="300" :inicio-proyeccion="demanda.inicio"
                    vacio="No hay pedidos registrados en el periodo." />
                <MetodoPronostico :metodo="datos.demanda.metodo" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Productos más vendidos <AyudaInfo clave="bi.productos-mas-vendidos" /></span>
                            <p class="card-subtitle">Top 10 por monto aprobado en el periodo</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Barras horizontales: los nombres de producto son largos
                             y en vertical se leerían inclinados o cortados. -->
                        <BaseChart titulo="Productos más vendidos" tipo="bar" horizontal :labels="productos.labels"
                            :series="productos.series" :formato="money" :alto="300"
                            vacio="Sin cotizaciones aprobadas todavía." />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Categorías más vendidas <AyudaInfo clave="bi.categorias-mas-vendidas" /></span>
                            <p class="card-subtitle">Top 10 por monto aprobado en el periodo</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Categorías más vendidas" tipo="bar" horizontal :labels="categorias.labels"
                            :series="categorias.series" :formato="money" :alto="300"
                            vacio="Sin cotizaciones aprobadas todavía." />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Evolución del costo de materiales <AyudaInfo clave="bi.evolucion-costos" /></span>
                    <EtiquetaAlcance global texto="Inventario" />
                    <p class="card-subtitle">
                        Precio unitario según el historial que deja cada compra aprobada
                        <span v-if="materialesOcultos">· se grafican los 4 primeros de
                            {{ datos.evolucion_costos.length }}</span>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Evolución del costo de materiales" :labels="costos.labels" :series="costos.series"
                    :formato="money" :alto="300" unir-huecos
                    vacio="Se necesita más de un registro de precio por material (se genera al aprobar compras)." />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Estacionalidad <AyudaInfo clave="bi.estacionalidad" /></span>
                    <span class="badge badge-soft-secondary">Toda la historia · no usa el filtro de fechas</span>
                    <p class="card-subtitle">
                        <template v-if="datos.estacionalidad.gestiones.length === 1">
                            Pedidos de cada mes de {{ datos.estacionalidad.gestiones[0].anio }} · con una segunda gestión
                            aparecen la comparación entre años y el promedio
                        </template>
                        <template v-else>Pedidos de cada mes, una línea por gestión y el promedio</template>
                        <span v-if="datos.estacionalidad.desde"> · datos desde {{ datos.estacionalidad.desde }}</span>
                        <span v-if="datos.estacionalidad.mes_en_curso"> · {{ datos.estacionalidad.mes_en_curso }} todavía no termina</span>
                        <span v-if="datos.estacionalidad.gestiones_ocultas"> · se grafican las 5 gestiones más recientes</span>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Estacionalidad de pedidos" :labels="estacionalidad.labels"
                    :series="estacionalidad.series" :formato="pedidos" :alto="280"
                    vacio="Todavía no hay pedidos registrados." />
            </div>
        </div>
    </div>
</template>
