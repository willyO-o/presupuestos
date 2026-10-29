<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import { userCan } from '@/Directives/Can.js';
import { money, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Resumen semanal del BI: titular, cuatro indicadores clave, qué requiere
 * atención, resultados favorables, acciones sugeridas y la tabla de
 * indicadores de la que sale todo, de la última semana cerrada (o de una
 * anterior, elegida en el selector).
 *
 * El texto (titular, riesgos, destacados, acciones) es el que redactó la IA
 * —o la plantilla, si la IA no estaba disponible— y se muestra tal cual. Lo
 * que agrega la pantalla sale de los indicadores que cada frase cita: el
 * título corto de cada punto, las aclaraciones y los enlaces. Al pasar el
 * cursor (o el foco) por un punto se resaltan sus filas en la tabla.
 */
const props = defineProps({
    datos: { type: Object, required: true },
});

/**
 * Mismo umbral que ResumenSemanalService::VARIACION_RELEVANTE: desde ahí un
 * indicador cuenta como destacado o riesgo, y su tarjeta toma color.
 */
const VARIACION_RELEVANTE = 10;

/** Las cuatro tarjetas de arriba, en orden. */
const TARJETAS = [
    { clave: 'monto_vendido', etiqueta: 'Ventas de la semana', icono: 'fa-solid fa-cart-shopping' },
    { clave: 'cobranza', etiqueta: 'Cobros recibidos', icono: 'fa-solid fa-coins' },
    { clave: 'monto_emitido', etiqueta: 'Monto presupuestado', icono: 'fa-solid fa-file-invoice-dollar' },
    { clave: 'entregas_a_tiempo_pct', etiqueta: 'Entregas a tiempo', icono: 'fa-solid fa-truck-fast' },
];

/** Título corto de un punto según el indicador que cita y hacia dónde se movió: [subió, bajó]. */
const TITULOS = {
    presupuestos_emitidos: ['Más presupuestos emitidos', 'Menos presupuestos emitidos'],
    monto_emitido: ['Mayor monto presupuestado', 'Menor monto presupuestado'],
    ventas_cerradas: ['Más ventas cerradas', 'Menos ventas cerradas'],
    monto_vendido: ['Mayor monto vendido', 'Menor monto vendido'],
    ticket_promedio: ['Mayor monto promedio por presupuesto', 'Menor monto promedio por presupuesto'],
    rentabilidad_pct: ['Mayor rentabilidad presupuestada', 'Menor rentabilidad presupuestada'],
    cobranza: ['Mayores cobros recibidos', 'Menores cobros recibidos'],
    entregas: ['Más pedidos entregados', 'Menos pedidos entregados'],
    entregas_a_tiempo_pct: ['Más entregas a tiempo', 'Menos entregas a tiempo'],
    costo_material: ['Mayor costo de material consumido', 'Menor costo de material consumido'],
};

/** Indicadores que se malinterpretan fácil: la aclaración va debajo del punto que los cita. */
const ACLARACIONES = {
    rentabilidad_pct: 'Se expresa sobre el costo, no sobre las ventas. La variación es relativa, no en puntos porcentuales.',
    costo_material: 'Depende de cuánto se produjo: compárelo con los pedidos entregados antes de leerlo como ahorro o sobrecosto.',
};

/** Contra qué se compara en la tabla de indicadores. */
const COMPARACIONES = [
    { clave: 'promedio', texto: '4 semanas', columna: 'Promedio 4 semanas', referencia: 'promedio_4_semanas', variacion: 'var_promedio_pct' },
    { clave: 'semana', texto: 'Semana anterior', columna: 'Semana anterior', referencia: 'semana_anterior', variacion: 'var_semana_pct' },
    { clave: 'anio', texto: 'Año anterior', columna: 'Año anterior', referencia: 'anio_anterior', variacion: 'var_anio_pct' },
];

/**
 * Pantalla donde se hace cada acción sugerida. La IA redacta libremente, así
 * que se elige por palabras de la acción (gana la primera que coincide); si
 * ninguna coincide, o el usuario no tiene permiso, la acción va sin botón.
 */
const DESTINOS = [
    { patron: /anomal/i, texto: 'Ver anomalías', ruta: 'reportes.bi.alertas', ancla: '#anomalias', permiso: 'reportes.bi' },
    { patron: /cobr|saldo/i, texto: 'Revisar cuentas por cobrar', ruta: 'reportes.financiero', permiso: 'reportes.financiero' },
    { patron: /entreg|atrasad|producci|etapa/i, texto: 'Revisar producción', ruta: 'reportes.produccion', permiso: 'reportes.produccion' },
    { patron: /material|consumo/i, texto: 'Ver costos y materiales', ruta: 'reportes.bi.costos', permiso: 'reportes.bi' },
    { patron: /rentabilidad|descuento|margen/i, texto: 'Ver rentabilidad', ruta: 'reportes.bi.rentabilidad', permiso: 'reportes.bi' },
    { patron: /aprobad/i, texto: 'Ver presupuestos aprobados', ruta: 'cotizaciones.index', parametros: { estado: 'APROBADA' }, permiso: 'cotizaciones.ver' },
    { patron: /cotizac|presupuest|prospecc|oportunidad/i, texto: 'Revisar presupuestos', ruta: 'cotizaciones.index', permiso: 'cotizaciones.ver' },
];

const resumen = computed(() => props.datos.resumen);
const kpiActivos = ref([]);
const generando = ref(false);
const comparar = ref('promedio');

const kpis = computed(() => resumen.value?.kpis ?? []);
const kpiPorId = computed(() => Object.fromEntries(kpis.value.map((k) => [k.id, k])));
const kpiPorClave = computed(() => Object.fromEntries(kpis.value.map((k) => [k.clave, k])));

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

/** El resumen abierto no es el de la última semana cerrada. */
const esHistorico = computed(() => Boolean(
    resumen.value?.semana?.inicio && props.datos.ultima_semana && resumen.value.semana.inicio !== props.datos.ultima_semana,
));

function abrir(id) {
    router.get(route('reportes.bi.resumen'), { resumen: id }, { preserveScroll: true });
}

/** Otra semana, con el mismo alcance (empresa o sucursal) si lo tiene. */
function cambiarSemana(etiqueta) {
    const grupo = historial.value.find((g) => g.semana === etiqueta);

    if (grupo) {
        abrir((grupo.resumenes.find((r) => r.alcance === resumen.value.alcance_corto) ?? grupo.resumenes[0]).id);
    }
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

/** 'sube' | 'baja' | null, contra el promedio de 4 semanas (o la semana anterior si no hay promedio). */
function direccion(kpi) {
    const valor = kpi?.var_promedio_pct ?? kpi?.var_semana_pct ?? null;

    if (valor === null || Math.abs(valor) < 0.5) {
        return null;
    }

    return valor > 0 ? 'sube' : 'baja';
}

function kpisDe(punto) {
    return (punto.kpis ?? []).map((id) => kpiPorId.value[id]).filter(Boolean);
}

function tituloDe(punto) {
    const kpi = kpisDe(punto)[0];

    if (!kpi) {
        return null;
    }

    const sentido = direccion(kpi);

    return TITULOS[kpi.clave] && sentido ? TITULOS[kpi.clave][sentido === 'sube' ? 0 : 1] : kpi.nombre;
}

function aclaracionDe(punto) {
    const kpi = kpisDe(punto).find((k) => ACLARACIONES[k.clave]);

    return kpi ? ACLARACIONES[kpi.clave] : null;
}

function iconoRiesgo(riesgo) {
    return {
        sube: 'fa-solid fa-arrow-up',
        baja: 'fa-solid fa-arrow-down',
    }[direccion(kpisDe(riesgo)[0])] ?? 'fa-solid fa-triangle-exclamation';
}

function activar(ids) {
    kpiActivos.value = ids ?? [];
}

const tarjetas = computed(() => TARJETAS
    .filter((t) => kpiPorClave.value[t.clave])
    .map((t) => {
        const kpi = kpiPorClave.value[t.clave];
        const cambio = kpi.var_promedio_pct;
        const relevante = cambio !== null && cambio !== undefined && Math.abs(cambio) >= VARIACION_RELEVANTE;
        const bueno = (cambio > 0) === (kpi.mejor_si === 'sube');

        return {
            ...t,
            valor: formatoValor(kpi.valor, kpi.unidad),
            variacion: cambio ?? null,
            subirEsBueno: kpi.mejor_si === 'sube',
            tono: !relevante ? 'primary' : (bueno ? 'success' : 'danger'),
            nota: notaTarjeta(kpi),
        };
    }));

function notaTarjeta(kpi) {
    if (kpi.clave === 'entregas_a_tiempo_pct') {
        const entregados = kpiPorClave.value.entregas?.valor ?? 0;

        return entregados === 0
            ? 'Sin pedidos entregados en la semana'
            : `${numero(entregados, 0)} ${entregados === 1 ? 'pedido entregado' : 'pedidos entregados'}`;
    }

    // "vs. 4 semanas" ya está en la línea de arriba: acá alcanza con la cifra.
    return kpi.promedio_4_semanas === null || kpi.promedio_4_semanas === undefined
        ? ''
        : `Promedio: ${formatoValor(kpi.promedio_4_semanas, kpi.unidad)}`;
}

/** Sin historia del año anterior, esa comparación no se ofrece. */
const comparaciones = computed(() => COMPARACIONES.filter((c) => c.clave !== 'anio' || resumen.value?.hay_anio_anterior));
const comparacion = computed(() => comparaciones.value.find((c) => c.clave === comparar.value) ?? comparaciones.value[0]);

function destinoDe(recomendacion) {
    const destino = DESTINOS.find((d) => d.patron.test(recomendacion.accion));

    if (!destino || !route().has(destino.ruta) || !userCan(destino.permiso)) {
        return null;
    }

    return { texto: destino.texto, url: route(destino.ruta, destino.parametros ?? {}) + (destino.ancla ?? '') };
}

const recomendaciones = computed(() => (resumen.value?.contenido?.recomendaciones ?? [])
    .map((r) => ({ ...r, destino: destinoDe(r) })));

const familias = computed(() => resumen.value?.contexto?.familias_mas_cotizadas ?? []);
const vendedor = computed(() => resumen.value?.contexto?.vendedor_destacado ?? null);
const anomalias = computed(() => resumen.value?.contexto?.anomalias_abiertas ?? 0);

const generadoTexto = computed(() => {
    const fecha = resumen.value?.generado;

    return fecha
        ? new Date(fecha).toLocaleString('es-BO', {
            day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        })
        : '';
});

const origenTexto = computed(() => (resumen.value?.origen === 'IA'
    ? 'Redacción asistida por IA'
    : 'Plantilla automática (sin IA)'));
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
            <!-- Qué semana y de quién; cuándo y cómo se redactó. -->
            <section class="card" aria-label="Semana y sucursal del resumen">
                <div class="card-body resumen-filtro">
                    <div class="resumen-filtro-campo">
                        <span class="resumen-filtro-icono" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                        <div class="resumen-filtro-control">
                            <label for="resumen-semana" class="form-label">Semana analizada</label>
                            <select id="resumen-semana" class="form-control" :value="resumen.semana?.etiqueta"
                                @change="cambiarSemana($event.target.value)">
                                <option v-for="g in historial" :key="g.semana" :value="g.semana">{{ g.semana }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="resumen-filtro-campo resumen-filtro-campo-corto">
                        <span class="resumen-filtro-icono" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                        <div class="resumen-filtro-control">
                            <label for="resumen-alcance" class="form-label">Sucursal</label>
                            <select id="resumen-alcance" class="form-control" :value="resumen.id"
                                :disabled="alcancesDeLaSemana.length < 2" @change="abrir(Number($event.target.value))">
                                <option v-for="a in alcancesDeLaSemana" :key="a.id" :value="a.id">{{ a.alcance }}</option>
                                <option v-if="!alcancesDeLaSemana.length" :value="resumen.id">{{ resumen.alcance_corto }}</option>
                            </select>
                        </div>
                    </div>

                    <button v-if="datos.puede_generar" type="button" class="btn btn-primary resumen-filtro-boton"
                        :disabled="generando" title="Vuelve a calcular el resumen de la última semana cerrada (lunes a domingo)"
                        @click="generar">
                        <i class="fa-solid fa-rotate" :class="{ 'fa-spin': generando }"></i>
                        {{ generando ? 'Actualizando…' : 'Actualizar resumen' }}
                    </button>

                    <div class="resumen-filtro-estado">
                        <span v-if="esHistorico" class="badge badge-soft-info"
                            title="Hay una semana cerrada más reciente que la que se está viendo">
                            <i class="fa-solid fa-clock-rotate-left"></i> Resumen histórico
                        </span>
                        <span>Generado el {{ generadoTexto }}</span>
                        <span :title="resumen.modelo ?? resumen.proveedor ?? null">{{ origenTexto }}</span>
                    </div>

                    <p v-if="datos.ia && !datos.ia.disponible" class="bi-aviso resumen-filtro-aviso">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>{{ datos.ia.motivo }} Mientras tanto, los resúmenes usan la plantilla automática.</span>
                    </p>
                </div>
            </section>

            <!-- Titular (texto redactado, tal cual). -->
            <section class="resumen-titular-caja" aria-labelledby="resumen-titular">
                <span class="resumen-titular-icono" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                <div class="resumen-titular-cuerpo">
                    <p class="resumen-titular-eyebrow">
                        Lectura de la semana · {{ resumen.alcance }} <AyudaInfo clave="bi-resumen.semana" />
                    </p>
                    <h2 id="resumen-titular" class="resumen-titular">{{ resumen.contenido.titular }}</h2>
                </div>
                <p class="resumen-titular-nota">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        Comparación principal: promedio de las 4 semanas anteriores.
                        <template v-if="esHistorico"> No es la última semana cerrada.</template>
                    </span>
                </p>
            </section>

            <div class="resumen-tarjetas">
                <BiKpi v-for="t in tarjetas" :key="t.clave" :etiqueta="t.etiqueta" :valor="t.valor" :icono="t.icono"
                    :tono="t.tono" :variacion="t.variacion" sufijo="vs. 4 semanas" :subir-es-bueno="t.subirEsBueno"
                    :nota="t.nota" ayuda="bi-resumen.tarjetas" />
            </div>

            <div class="resumen-grilla">
                <section class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fa-solid fa-triangle-exclamation resumen-icono-atencion" aria-hidden="true"></i>
                            Qué requiere atención <AyudaInfo clave="bi-resumen.riesgos" />
                        </span>
                    </div>
                    <ul class="card-body resumen-puntos">
                        <li v-for="(r, i) in resumen.contenido.riesgos" :key="i" class="resumen-punto" tabindex="0"
                            @mouseenter="activar(r.kpis)" @mouseleave="activar([])" @focus="activar(r.kpis)" @blur="activar([])">
                            <span class="resumen-punto-icono" :class="r.severidad === 'alta' ? 'resumen-punto-alto' : 'resumen-punto-medio'"
                                :title="r.severidad === 'alta' ? 'Prioridad alta' : 'Prioridad media'">
                                <i :class="iconoRiesgo(r)" aria-hidden="true"></i>
                            </span>
                            <span class="resumen-punto-cuerpo">
                                <strong v-if="tituloDe(r)" class="resumen-punto-titulo">{{ tituloDe(r) }}</strong>
                                <span class="sr-only">Prioridad {{ r.severidad === 'alta' ? 'alta' : 'media' }}.</span>
                                <span class="resumen-punto-texto">{{ r.texto }}</span>
                                <span v-if="aclaracionDe(r)" class="resumen-punto-nota">{{ aclaracionDe(r) }}</span>
                            </span>
                        </li>
                        <li v-if="!resumen.contenido.riesgos.length" class="resumen-punto-vacio">Ningún indicador en zona de riesgo.</li>
                    </ul>
                </section>

                <section class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fa-solid fa-arrow-trend-up resumen-icono-favorable" aria-hidden="true"></i>
                            Resultados favorables <AyudaInfo clave="bi-resumen.destacados" />
                        </span>
                    </div>
                    <ul class="card-body resumen-puntos">
                        <li v-for="(d, i) in resumen.contenido.destacados" :key="i" class="resumen-punto" tabindex="0"
                            @mouseenter="activar(d.kpis)" @mouseleave="activar([])" @focus="activar(d.kpis)" @blur="activar([])">
                            <span class="resumen-punto-icono resumen-punto-bueno" aria-hidden="true">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                            </span>
                            <span class="resumen-punto-cuerpo">
                                <strong v-if="tituloDe(d)" class="resumen-punto-titulo">{{ tituloDe(d) }}</strong>
                                <span class="resumen-punto-texto">{{ d.texto }}</span>
                                <span v-if="aclaracionDe(d)" class="resumen-punto-nota">{{ aclaracionDe(d) }}</span>
                            </span>
                        </li>
                        <li v-if="!resumen.contenido.destacados.length" class="resumen-punto-vacio">Sin destacados esta semana.</li>
                    </ul>
                </section>
            </div>

            <section class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                            Indicadores de la semana <AyudaInfo clave="bi-resumen.indicadores" />
                        </span>
                        <p class="card-subtitle mb-0">Los números de los que sale el texto</p>
                    </div>
                    <div class="resumen-comparar">
                        <span id="resumen-comparar-etiqueta" class="fs-xs text-muted">Comparar con:</span>
                        <div class="financiero-vista panorama-vista" role="group" aria-labelledby="resumen-comparar-etiqueta">
                            <button v-for="c in comparaciones" :key="c.clave" type="button"
                                :class="{ 'is-active': comparacion.clave === c.clave }" :aria-pressed="comparacion.clave === c.clave"
                                @click="comparar = c.clave">
                                {{ c.texto }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table-dashboard table-sm resumen-kpis">
                            <thead>
                                <tr>
                                    <th>Indicador</th>
                                    <th class="text-end">Esta semana</th>
                                    <th class="text-end resumen-kpis-referencia">{{ comparacion.columna }}</th>
                                    <th class="text-end">Cambio vs. {{ comparacion.texto.toLowerCase() }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in kpis" :key="k.id" :class="{ 'is-evidencia': kpiActivos.includes(k.id) }">
                                    <td>{{ k.nombre }}</td>
                                    <td class="text-end chart-table-num fw-semibold">{{ formatoValor(k.valor, k.unidad) }}</td>
                                    <td class="text-end chart-table-num resumen-kpis-referencia">{{ formatoValor(k[comparacion.referencia], k.unidad) }}</td>
                                    <td class="text-end chart-table-num" :class="claseVariacion(k, k[comparacion.variacion])">
                                        {{ variacion(k[comparacion.variacion]) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="resumen-nota">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>
                            Los cambios son variaciones relativas (%). Verde: el indicador se movió en la dirección
                            favorable; rojo, en la desfavorable. Un menor costo de material no implica ahorro por sí solo.
                        </span>
                    </p>
                </div>
            </section>

            <div class="resumen-grilla">
                <section class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                            Acciones sugeridas para el seguimiento <AyudaInfo clave="bi-resumen.recomendaciones" />
                        </span>
                    </div>
                    <ol class="card-body resumen-acciones">
                        <li v-for="(r, i) in recomendaciones" :key="i" class="resumen-accion-item">
                            <span class="resumen-accion-numero" aria-hidden="true">{{ i + 1 }}</span>
                            <span class="resumen-accion-cuerpo">
                                <strong class="resumen-accion">{{ r.accion }}</strong>
                                <span class="resumen-motivo">{{ r.motivo }}</span>
                            </span>
                            <Link v-if="r.destino" :href="r.destino.url" class="btn btn-sm btn-outline-primary resumen-accion-enlace">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> {{ r.destino.texto }}
                            </Link>
                        </li>
                    </ol>
                </section>

                <section class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                            Contexto de las cotizaciones <AyudaInfo clave="bi-resumen.contexto" />
                        </span>
                    </div>
                    <div class="card-body resumen-contexto">
                        <template v-if="familias.length">
                            <p class="resumen-contexto-titulo">Familias más cotizadas</p>
                            <ul class="resumen-familias">
                                <li v-for="f in familias" :key="f.familia" class="resumen-familia">
                                    <span class="resumen-familia-nombre">{{ f.familia }}</span>
                                    <span class="reporte-progress resumen-familia-barra" aria-hidden="true">
                                        <span class="reporte-progress-bar d-block" :style="{ width: `${f.participacion_pct}%` }"></span>
                                    </span>
                                    <span class="resumen-familia-cifra chart-table-num">{{ money(f.monto) }} · {{ porcentaje(f.participacion_pct) }}</span>
                                </li>
                            </ul>
                            <p class="resumen-nota mt-0">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                <span>
                                    Porcentaje sobre la suma de las líneas de producto cotizadas; no coincide exactamente con
                                    el monto presupuestado total.
                                </span>
                            </p>
                        </template>
                        <p v-else class="resumen-punto-vacio">No se cotizó en la semana.</p>

                        <div class="resumen-contexto-pie">
                            <p v-if="vendedor" class="resumen-vendedor">
                                <span class="resumen-vendedor-icono" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                <span>
                                    <span class="resumen-contexto-titulo">Mayor monto presupuestado por vendedor</span>
                                    <span class="d-block">
                                        {{ vendedor.vendedor }} · {{ money(vendedor.monto) }} en {{ vendedor.presupuestos }}
                                        {{ vendedor.presupuestos === 1 ? 'presupuesto' : 'presupuestos' }}
                                    </span>
                                </span>
                            </p>
                            <Link :href="`${route('reportes.bi.alertas')}#anomalias`" class="badge resumen-anomalias"
                                :class="anomalias > 0 ? 'badge-soft-warning' : 'badge-soft-info'">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                Anomalías sin revisar: {{ anomalias }}
                            </Link>
                        </div>
                    </div>
                </section>
            </div>

            <p class="resumen-pie">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Interpretación automática basada en los registros del sistema. Las causas deben confirmarse antes de tomar decisiones.
            </p>
        </template>
    </div>
</template>
