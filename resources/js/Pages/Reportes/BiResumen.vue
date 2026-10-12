<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import { money, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Resumen semanal del BI: titular, destacados, riesgos y recomendaciones de
 * la última semana cerrada, con la tabla de indicadores de la que salen.
 *
 * Cada destacado y riesgo cita los indicadores en los que se apoya; al
 * pasar el cursor (o el foco) por la frase se resaltan esas filas. Es la
 * forma de leer el texto contra la evidencia: las cifras las calcula el
 * sistema, la IA (o la plantilla, si la IA no está disponible) solo redacta.
 */
const props = defineProps({
    datos: { type: Object, required: true },
});

const resumen = computed(() => props.datos.resumen);
const kpiActivos = ref([]);
const generando = ref(false);

const kpis = computed(() => resumen.value?.kpis ?? []);

/** Historial agrupado por semana: [{ semana, resumenes: [...] }]. */
const historial = computed(() => {
    const grupos = new Map();

    for (const r of props.datos.historial) {
        if (!grupos.has(r.semana)) {
            grupos.set(r.semana, []);
        }
        grupos.get(r.semana).push(r);
    }

    return [...grupos.entries()].map(([semana, resumenes]) => ({ semana, resumenes }));
});

/** Otras vistas (consolidado / sucursales) de la misma semana. */
const alcancesDeLaSemana = computed(() => historial.value
    .find((g) => g.semana === resumen.value?.semana?.etiqueta)?.resumenes ?? []);

function abrir(id) {
    router.get(route('reportes.bi.resumen'), { resumen: id }, { preserveScroll: true });
}

function generar() {
    generando.value = true;
    router.post(route('reportes.bi.resumen.generar'), {}, {
        preserveScroll: true,
        onFinish: () => { generando.value = false; },
    });
}

function formatoValor(valor, unidad) {
    if (valor === null || valor === undefined) {
        return '—';
    }

    return {
        bs: money(valor),
        porcentaje: porcentaje(valor),
        cantidad: numero(valor, unidad === 'cantidad' && !Number.isInteger(valor) ? 1 : 0),
    }[unidad] ?? String(valor);
}

/**
 * Clase de una variación según si moverse en esa dirección es bueno para el
 * indicador (el costo de material que sube es malo; las ventas, bueno).
 */
function claseVariacion(kpi, valor) {
    if (valor === null || valor === undefined || Math.abs(valor) < 0.5) {
        return 'resumen-var-neutra';
    }

    return (valor > 0) === (kpi.mejor_si === 'sube') ? 'resumen-var-buena' : 'resumen-var-mala';
}

function activar(ids) {
    kpiActivos.value = ids ?? [];
}

const origenTexto = computed(() => {
    if (!resumen.value) {
        return '';
    }

    return resumen.value.origen === 'IA'
        ? `Redactado por IA · ${resumen.value.modelo ?? resumen.value.proveedor ?? ''}`
        : 'Plantilla automática (sin IA)';
});

const generadoTexto = computed(() => {
    const fecha = resumen.value?.generado;

    return fecha ? new Date(fecha).toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' }) : '';
});
</script>

<template>
    <Head title="Resumen semanal" />

    <div class="page-stack">
        <BiEncabezado />

        <div v-if="!resumen" class="card">
            <div class="card-body resumen-vacio">
                <i class="fa-solid fa-newspaper"></i>
                <h2 class="resumen-vacio-titulo">Todavía no hay resúmenes semanales</h2>
                <p>
                    El sistema arma uno cada lunes a las 07:00 con la semana que terminó: un resumen de toda la empresa
                    y uno por sucursal. Si la IA no está configurada, se publica igual con una plantilla automática.
                </p>
                <button v-if="datos.puede_generar" type="button" class="btn btn-primary" :disabled="generando" @click="generar">
                    <i class="fa-solid fa-bolt"></i> {{ generando ? 'Generando…' : 'Generar el de la semana pasada' }}
                </button>
            </div>
        </div>

        <template v-else>
            <div class="card resumen-cabecera">
                <div class="card-body">
                    <div class="resumen-cabecera-fila">
                        <div class="resumen-cabecera-texto">
                            <p class="resumen-semana">
                                {{ resumen.semana?.etiqueta }} · <strong>{{ resumen.alcance }}</strong>
                            </p>
                            <h2 class="resumen-titular">{{ resumen.contenido.titular }}</h2>
                            <p class="resumen-origen">
                                <span class="badge" :class="resumen.origen === 'IA' ? 'badge-soft-primary' : 'badge-soft-secondary'">
                                    <i :class="resumen.origen === 'IA' ? 'fa-solid fa-wand-magic-sparkles' : 'fa-solid fa-file-lines'"></i>
                                    {{ origenTexto }}
                                </span>
                                <span class="fs-xs text-muted">Generado {{ generadoTexto }}</span>
                            </p>
                        </div>
                        <div class="resumen-cabecera-acciones">
                            <div v-if="alcancesDeLaSemana.length > 1" class="resumen-alcances" role="group" aria-label="Ver resumen de">
                                <button v-for="a in alcancesDeLaSemana" :key="a.id" type="button" class="resumen-alcance"
                                    :class="{ 'is-active': a.id === resumen.id }" :aria-pressed="a.id === resumen.id"
                                    @click="abrir(a.id)">
                                    {{ a.alcance }}
                                </button>
                            </div>
                            <button v-if="datos.puede_generar" type="button" class="btn btn-sm btn-outline-primary"
                                :disabled="generando" @click="generar">
                                <i class="fa-solid fa-rotate"></i> {{ generando ? 'Generando…' : 'Regenerar semana pasada' }}
                            </button>
                        </div>
                    </div>
                    <p v-if="datos.ia && !datos.ia.disponible" class="bi-aviso mt-3 mb-0">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>{{ datos.ia.motivo }} Mientras tanto, los resúmenes usan la plantilla automática.</span>
                    </p>
                </div>
            </div>

            <div class="resumen-cuerpo">
                <div class="resumen-lectura">
                    <section class="card">
                        <div class="card-header"><span class="card-title">Lo más destacado</span></div>
                        <ul class="card-body resumen-puntos">
                            <li v-for="(d, i) in resumen.contenido.destacados" :key="i" class="resumen-punto" tabindex="0"
                                @mouseenter="activar(d.kpis)" @mouseleave="activar([])" @focus="activar(d.kpis)" @blur="activar([])">
                                <span class="resumen-punto-icono resumen-punto-bueno"><i class="fa-solid fa-arrow-trend-up"></i></span>
                                <span>{{ d.texto }}</span>
                            </li>
                            <li v-if="!resumen.contenido.destacados.length" class="text-muted fs-sm">Sin destacados esta semana.</li>
                        </ul>
                    </section>

                    <section class="card">
                        <div class="card-header"><span class="card-title">Riesgos</span></div>
                        <ul class="card-body resumen-puntos">
                            <li v-for="(r, i) in resumen.contenido.riesgos" :key="i" class="resumen-punto" tabindex="0"
                                @mouseenter="activar(r.kpis)" @mouseleave="activar([])" @focus="activar(r.kpis)" @blur="activar([])">
                                <span class="resumen-punto-icono" :class="r.severidad === 'alta' ? 'resumen-punto-alto' : 'resumen-punto-medio'">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </span>
                                <span>
                                    <span class="badge me-1" :class="r.severidad === 'alta' ? 'badge-soft-danger' : 'badge-soft-warning'">
                                        {{ r.severidad === 'alta' ? 'Alta' : 'Media' }}
                                    </span>
                                    {{ r.texto }}
                                </span>
                            </li>
                            <li v-if="!resumen.contenido.riesgos.length" class="text-muted fs-sm">Ningún indicador en zona de riesgo.</li>
                        </ul>
                    </section>

                    <section class="card">
                        <div class="card-header"><span class="card-title">Recomendaciones para esta semana</span></div>
                        <ul class="card-body resumen-puntos">
                            <li v-for="(r, i) in resumen.contenido.recomendaciones" :key="i" class="resumen-punto">
                                <span class="resumen-punto-icono resumen-punto-accion"><i class="fa-solid fa-check"></i></span>
                                <span>
                                    <strong class="resumen-accion">{{ r.accion }}</strong>
                                    <span class="resumen-motivo">{{ r.motivo }}</span>
                                </span>
                            </li>
                        </ul>
                    </section>
                </div>

                <div class="resumen-evidencia">
                    <section class="card">
                        <div class="card-header">
                            <div>
                                <span class="card-title">Indicadores de la semana</span>
                                <p class="card-subtitle">Los números de los que sale el texto</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table-dashboard table-sm resumen-kpis">
                                    <thead>
                                        <tr>
                                            <th>Indicador</th>
                                            <th class="text-end">Semana</th>
                                            <th class="text-end" title="Frente al promedio de las 4 semanas anteriores">vs. 4 sem.</th>
                                            <th class="text-end" title="Frente a la semana anterior">vs. ant.</th>
                                            <th v-if="resumen.hay_anio_anterior" class="text-end" title="Frente a la misma semana del año anterior">vs. año</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="k in kpis" :key="k.id" :class="{ 'is-evidencia': kpiActivos.includes(k.id) }">
                                            <td>{{ k.nombre }}</td>
                                            <td class="text-end chart-table-num fw-semibold">{{ formatoValor(k.valor, k.unidad) }}</td>
                                            <td class="text-end chart-table-num" :class="claseVariacion(k, k.var_promedio_pct)">{{ variacion(k.var_promedio_pct) }}</td>
                                            <td class="text-end chart-table-num" :class="claseVariacion(k, k.var_semana_pct)">{{ variacion(k.var_semana_pct) }}</td>
                                            <td v-if="resumen.hay_anio_anterior" class="text-end chart-table-num" :class="claseVariacion(k, k.var_anio_pct)">{{ variacion(k.var_anio_pct) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="fs-xs text-muted mb-0 mt-2">
                                Verde = se movió en la dirección buena para ese indicador. "vs. 4 sem." compara con el promedio de las 4 semanas anteriores.
                            </p>
                        </div>
                    </section>

                    <section class="card">
                        <div class="card-header"><span class="card-title">Contexto</span></div>
                        <div class="card-body resumen-contexto">
                            <div v-if="resumen.contexto.familias_mas_cotizadas?.length">
                                <p class="resumen-contexto-titulo">Familias más cotizadas</p>
                                <div v-for="f in resumen.contexto.familias_mas_cotizadas" :key="f.familia" class="resumen-familia">
                                    <div class="resumen-familia-fila">
                                        <span>{{ f.familia }}</span>
                                        <span class="chart-table-num">{{ money(f.monto) }} · {{ porcentaje(f.participacion_pct) }}</span>
                                    </div>
                                    <div class="reporte-progress"><div class="reporte-progress-bar" :style="{ width: `${f.participacion_pct}%` }"></div></div>
                                </div>
                            </div>
                            <p v-if="resumen.contexto.vendedor_destacado" class="mb-0">
                                <span class="resumen-contexto-titulo">Vendedor con más monto cotizado:</span>
                                {{ resumen.contexto.vendedor_destacado.vendedor }}
                                ({{ money(resumen.contexto.vendedor_destacado.monto) }} en {{ resumen.contexto.vendedor_destacado.presupuestos }} presupuestos)
                            </p>
                            <p class="mb-0">
                                <span class="resumen-contexto-titulo">Anomalías sin revisar:</span>{{ ' ' }}
                                <a :href="`${route('reportes.bi.alertas')}#anomalias`">{{ resumen.contexto.anomalias_abiertas }}</a>
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <section v-if="historial.length > 1" class="card">
                <div class="card-header"><span class="card-title">Semanas anteriores</span></div>
                <ul class="card-body resumen-historial">
                    <li v-for="g in historial" :key="g.semana">
                        <button type="button" class="resumen-historial-item" :class="{ 'is-active': g.semana === resumen.semana?.etiqueta }"
                            @click="abrir((g.resumenes.find((r) => r.alcance === resumen.alcance_corto) ?? g.resumenes[0]).id)">
                            <span class="resumen-historial-semana">{{ g.semana }}</span>
                            <span class="resumen-historial-titular">{{ (g.resumenes.find((r) => r.alcance === 'Toda la empresa') ?? g.resumenes[0]).titular }}</span>
                        </button>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
