<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import EtiquetaAlcance from '@/Components/EtiquetaAlcance.vue';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
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
 * La proyección arranca repitiendo el último dato real para que se vea de dónde
 * sale, y va punteada: es lo único del gráfico que todavía no ocurrió, y esa
 * distinción no puede depender solo del color.
 */
const demanda = computed(() => {
    const historico = props.datos.demanda.serie;
    const futuro = props.datos.demanda.proyeccion;

    const relleno = Array(Math.max(historico.length - 1, 0)).fill(null);
    const ultimoReal = historico.at(-1)?.pedidos ?? null;

    return {
        labels: [...historico.map((s) => s.mes), ...futuro.map((p) => p.mes)],
        series: [
            { nombre: 'Pedidos reales', datos: historico.map((s) => s.pedidos), area: true },
            { nombre: 'Media móvil 3m', datos: props.datos.demanda.media_movil.map((m) => m.valor) },
            {
                nombre: 'Proyección',
                punteada: true,
                datos: [...relleno, ultimoReal, ...futuro.map((p) => p.pedidos_estimados)],
            },
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
        labels: fechas.map((f) => f.slice(0, 7)),
        series: materiales.map((m) => {
            const porFecha = Object.fromEntries(m.puntos.map((p) => [p.fecha, p.precio]));

            return { nombre: m.material, datos: fechas.map((f) => porFecha[f] ?? null) };
        }),
    };
});

const estacionalidad = computed(() => ({
    labels: props.datos.demanda.estacionalidad.map((m) => m.mes),
    series: [{ nombre: 'Pedidos', datos: props.datos.demanda.estacionalidad.map((m) => m.pedidos) }],
}));

const materialesOcultos = computed(() => Math.max(props.datos.evolucion_costos.length - 4, 0));
</script>

<template>
    <Head title="Inteligencia de negocios" />

    <div class="page-stack">
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Proyección de demanda</span>
                    <p class="card-subtitle">
                        Pedidos por mes, media móvil de 3 meses y tendencia lineal a 3 meses
                    </p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Proyección de demanda" :labels="demanda.labels" :series="demanda.series"
                    :formato="pedidos" :alto="300" vacio="Todavía no hay pedidos registrados para proyectar." />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Productos más vendidos</span>
                            <p class="card-subtitle">Top 10 por monto cotizado y aprobado</p>
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
                            <span class="card-title">Categorías más vendidas</span>
                            <p class="card-subtitle">Top 10 por monto cotizado y aprobado</p>
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
                    <span class="card-title">Evolución del costo de materiales</span>
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
                    <span class="card-title">Estacionalidad</span>
                    <p class="card-subtitle">Pedidos acumulados por mes calendario, todos los años juntos</p>
                </div>
            </div>
            <div class="card-body">
                <BaseChart titulo="Estacionalidad de pedidos" tipo="bar" :labels="estacionalidad.labels"
                    :series="estacionalidad.series" :formato="pedidos" :alto="240"
                    vacio="Todavía no hay pedidos registrados." />
            </div>
        </div>
    </div>
</template>
