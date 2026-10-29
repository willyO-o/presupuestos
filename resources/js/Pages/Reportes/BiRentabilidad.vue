<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import AvisoMesIncompleto from '@/Components/Reporte/AvisoMesIncompleto.vue';
import BiBloque from '@/Components/Reporte/BiBloque.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import ConfiabilidadPronostico from '@/Components/Reporte/ConfiabilidadPronostico.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { money, moneyCorto, numero, porcentaje } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Presupuestos y rentabilidad, en tres bloques que se leen de arriba abajo:
 *
 * 1. Qué se cotizó y cuánto se aprobó: montos emitidos y aprobados por mes,
 *    con la estimación del emitido.
 * 2. Aprobaciones y relación entre precio y costo: cantidades por mes y el
 *    precio promedio frente al costo promedio.
 * 3. Rentabilidad y resultado: el porcentaje sobre el costo y el monto en
 *    bolivianos, que son indicadores distintos y van por separado.
 *
 * "Resultado calculado" es la `utilidad_real` del motor de margen: precio
 * sin IVA − costo ajustado − IT − IUE. No es dinero cobrado.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const futuros = computed(() => props.datos.meses_proyeccion);
const vacioFuturo = computed(() => Array(futuros.value.length).fill(null));
const labelsConFuturo = computed(() => [...props.datos.labels, ...futuros.value]);
const inicioFuturo = computed(() => futuros.value[0] ?? null);

const resumen = computed(() => props.datos.resumen);
const rentabilidadDatos = computed(() => props.datos.rentabilidad);

function entero(value) {
    return numero(value, 0);
}

/** Monto para una nota: "Bs" y la cifra no se separan al partir el renglón. */
function montoJunto(value) {
    return money(value).replace(' ', '\u00a0');
}

function pct(value) {
    return porcentaje(value);
}

/** El último mes del periodo no terminó: se marca en cada gráfico mensual. */
const marcaMes = computed(() => (props.datos.periodo.mes_incompleto && props.datos.labels.length
    ? { etiqueta: props.datos.labels.at(-1), texto: 'Mes incompleto' }
    : null));

const nombreMesIncompleto = computed(() => {
    const palabra = props.datos.periodo.mes_incompleto?.mes?.split(' ')[0] ?? '';

    return palabra.charAt(0).toUpperCase() + palabra.slice(1);
});

/* ----- Indicadores de arriba ------------------------------------------------ */

const tonoRentabilidad = computed(() => {
    const valor = rentabilidadDatos.value.rentabilidad_promedio;
    const { verde, amarillo } = rentabilidadDatos.value.umbrales;

    if (valor === null) {
        return 'info';
    }

    return valor > verde ? 'success' : valor > amarillo ? 'warning' : 'danger';
});

const rentabilidadEstimada = computed(() => rentabilidadDatos.value.proyeccion_porcentaje.at(-1)?.valor ?? null);

/** "Por cada Bs 100 de costo…": el porcentaje del indicador, en bolivianos. */
const porCadaCien = computed(() => {
    const r = rentabilidadDatos.value.rentabilidad_promedio;

    return r === null ? null : money(r);
});

/* ----- 1. Qué se cotizó y cuánto se aprobó -------------------------------------- */

const montos = computed(() => {
    const p = props.datos.pronostico;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Monto emitido', datos: [...p.monto_emitido, ...vacioFuturo.value], area: true },
            { nombre: 'Monto aprobado', datos: [...p.monto_aprobado, ...vacioFuturo.value] },
            ...seriesProyectadas(p.monto_emitido, p.proyeccion_emitido, p.metodo, 'Estimación del emitido'),
        ],
    };
});

/* ----- 2. Aprobaciones y relación entre precio y costo ---------------------------- */

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

/** "768 ÷ (1.521 − 31) = 51,5 %": de dónde sale la tasa de aprobación. */
const cuentaAprobacion = computed(() => {
    const r = resumen.value;

    if (r.tasa_conversion === null) {
        return null;
    }

    return r.pendientes
        ? `${entero(r.aprobados)} ÷ (${entero(r.emitidos)} − ${entero(r.pendientes)}) = ${porcentaje(r.tasa_conversion)}`
        : `${entero(r.aprobados)} ÷ ${entero(r.emitidos)} = ${porcentaje(r.tasa_conversion)}`;
});

const tendencia = computed(() => ({
    labels: props.datos.labels,
    series: [
        // La clave del servidor sigue siendo `ticket`; en pantalla es el precio.
        { nombre: 'Precio promedio', datos: props.datos.tendencia.ticket },
        { nombre: 'Costo promedio', datos: props.datos.tendencia.costo },
    ],
}));

/**
 * Lectura del precio frente al costo, en palabras. La calcula el servidor
 * (RedaccionPronostico::precioFrenteACosto), con la misma regla que el informe.
 */
const lecturaTendencia = computed(() => props.datos.tendencia.lectura
    ?? 'Se necesitan al menos 3 meses con presupuestos (y con hoja de costos) para comparar tendencias.');

/* ----- 3. Rentabilidad y resultado -------------------------------------------------- */

const rentabilidad = computed(() => {
    const r = rentabilidadDatos.value;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Rentabilidad', datos: [...r.porcentaje, ...vacioFuturo.value] },
            ...seriesProyectadas(r.porcentaje, r.proyeccion_porcentaje, r.metodo, 'Estimación'),
        ],
        umbrales: [
            { valor: r.umbrales.verde, nombre: `Verde ${r.umbrales.verde} %` },
            { valor: r.umbrales.amarillo, nombre: `Amarillo ${r.umbrales.amarillo} %` },
        ],
    };
});

const resultado = computed(() => {
    const r = rentabilidadDatos.value;

    return {
        labels: labelsConFuturo.value,
        series: [
            { nombre: 'Resultado calculado', tipo: 'bar', datos: [...r.utilidad, ...vacioFuturo.value] },
            ...seriesProyectadas(r.utilidad, r.proyeccion_utilidad, r.metodo_utilidad, 'Estimación'),
        ],
    };
});

/** Cómo salieron los presupuestos emitidos en el semáforo del motor de margen. */
const semaforo = computed(() => {
    const s = rentabilidadDatos.value.semaforo;
    const { verde, amarillo } = rentabilidadDatos.value.umbrales;

    return {
        total: s.VERDE + s.AMARILLO + s.ROJO + s.SIN_COSTO,
        zonas: [
            { clase: 'semaforo-verde', cantidad: s.VERDE, texto: `Aceptar · más de ${numero(verde, 0)} %` },
            { clase: 'semaforo-amarillo', cantidad: s.AMARILLO, texto: `Revisar precio · ${numero(amarillo, 0)} a ${numero(verde, 0)} %` },
            { clase: 'semaforo-rojo', cantidad: s.ROJO, texto: `No aceptar · hasta ${numero(amarillo, 0)} %` },
        ],
        sinCosto: s.SIN_COSTO,
    };
});

/* ----- Qué revisar ------------------------------------------------------------------ */

/** Los arma el servidor (PresupuestosRentabilidadService::queRevisar): el informe dice lo mismo. */
const queRevisar = computed(() => props.datos.que_revisar ?? []);
</script>

<template>
    <Head title="Presupuestos y rentabilidad" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters">
            <template v-if="datos.periodo.mes_incompleto" #aviso>
                <AvisoMesIncompleto :mes="datos.periodo.mes_incompleto">Los montos cotizados no son dinero cobrado.</AvisoMesIncompleto>
            </template>
        </BiEncabezado>

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Presupuestos emitidos" ayuda="bi-rentabilidad.kpi-emitidos" icono="fa-solid fa-file-invoice"
                    tono="primary" :valor="entero(resumen.emitidos)" :nota="`Monto emitido: ${montoJunto(resumen.monto_emitido)}`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Aprobación entre presupuestos decididos" ayuda="bi-rentabilidad.kpi-aprobacion"
                    icono="fa-solid fa-handshake" tono="success" :valor="porcentaje(resumen.tasa_conversion)"
                    :nota="`${entero(resumen.aprobados)} aprobados · ${entero(resumen.pendientes)} pendientes excluidos`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Rentabilidad calculada sobre costo" ayuda="bi-rentabilidad.kpi-rentabilidad"
                    icono="fa-solid fa-percent" :tono="tonoRentabilidad" :valor="porcentaje(rentabilidadDatos.rentabilidad_promedio)"
                    :nota="`Resultado calculado: ${montoJunto(rentabilidadDatos.utilidad_total)}`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi :etiqueta="futuros.length ? `Rentabilidad estimada para ${futuros.at(-1)}` : 'Rentabilidad estimada'"
                    ayuda="bi-rentabilidad.kpi-proyectada" icono="fa-solid fa-binoculars" tono="info"
                    :valor="porcentaje(rentabilidadEstimada)"
                    :nota="rentabilidadEstimada === null ? 'Faltan meses con datos' : 'Proyección del modelo; no garantizada'" />
            </div>
        </div>

        <div v-if="porCadaCien" class="bi-definicion">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <p>
                <strong>¿Qué significa {{ porcentaje(rentabilidadDatos.rentabilidad_promedio) }} sobre costo?</strong>
                Por cada Bs 100 de costo ajustado, el resultado calculado (ya descontados IT e IUE) es {{ porCadaCien }}.
                No equivale al margen sobre las ventas<template v-if="rentabilidadDatos.margen_sobre_precio !== null">:
                    sobre el precio sin IVA, el resultado es el {{ porcentaje(rentabilidadDatos.margen_sobre_precio) }}</template>.
            </p>
        </div>

        <!-- ==================== 1. QUÉ SE COTIZÓ Y CUÁNTO SE APROBÓ ==================== -->
        <BiBloque :numero="1" titulo="Qué se cotizó y cuánto se aprobó"
            subtitulo="El monto de los presupuestos emitidos y aprobados cada mes, y hacia dónde va lo emitido.">
            <div class="card">
                <div class="card-header bi-cabecera-con-aviso">
                    <div>
                        <span class="card-title">Montos cotizados y aprobados por mes <AyudaInfo clave="bi-rentabilidad.pronostico" /></span>
                        <p class="card-subtitle">
                            Historial y estimación del monto emitido · Bolivianos
                            <span v-if="!datos.pronostico.proyeccion_emitido.length">· se necesitan al menos 3 meses en el periodo para estimar</span>
                        </p>
                    </div>
                    <ConfiabilidadPronostico :metodo="datos.pronostico.metodo" />
                </div>
                <div class="card-body">
                    <BaseChart titulo="Montos cotizados y aprobados por mes" :labels="montos.labels" :series="montos.series"
                        :formato="moneyCorto" :formato-tooltip="money" :alto="300" :inicio-proyeccion="inicioFuturo"
                        :marca-eje="marcaMes" vacio="No hay presupuestos emitidos en el periodo." />
                    <p class="bi-nota">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>La estimación corresponde al monto emitido, no a ventas futuras aseguradas.</span>
                    </p>
                    <MetodoPronostico :metodo="datos.pronostico.metodo" />
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 2. APROBACIONES Y PRECIO FRENTE A COSTO ==================== -->
        <BiBloque :numero="2" titulo="Aprobaciones y relación entre precio y costo"
            subtitulo="Cuántos presupuestos se emitieron y aprobaron, y si el precio acompaña al costo.">
            <div class="bi-demanda-par">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Cantidad de presupuestos por mes <AyudaInfo clave="bi-rentabilidad.cantidades" /></span>
                            <p class="card-subtitle">Cantidad emitida y aprobada</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Cantidad de presupuestos por mes" tipo="bar" :labels="cantidades.labels"
                            :series="cantidades.series" :formato="entero" :alto="260" enteros :marca-eje="marcaMes"
                            vacio="No hay presupuestos emitidos en el periodo." />
                        <p v-if="cuentaAprobacion" class="bi-nota">
                            <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                            <span>
                                La tasa de aprobación
                                <template v-if="resumen.pendientes">excluye los {{ entero(resumen.pendientes) }} pendientes</template>
                                <template v-else>cuenta todos los presupuestos</template>:
                                <strong>{{ cuentaAprobacion }}</strong>.
                            </span>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Precio y costo promedio por presupuesto <AyudaInfo clave="bi-rentabilidad.tendencia" /></span>
                            <p class="card-subtitle">Precio sin IVA frente a costo ajustado, de los mismos presupuestos</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Precio y costo promedio por presupuesto" :labels="tendencia.labels"
                            :series="tendencia.series" :formato="moneyCorto" :formato-tooltip="money" :alto="260"
                            vacio="No hay presupuestos emitidos en el periodo." />
                        <p class="bi-aviso mt-3 mb-0">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>{{ lecturaTendencia }} El gráfico no confirma por sí solo la causa: conviene revisar la composición de los trabajos.</span>
                        </p>
                    </div>
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 3. RENTABILIDAD Y RESULTADO ==================== -->
        <BiBloque :numero="3" titulo="Rentabilidad y resultado: indicadores distintos"
            subtitulo="La rentabilidad es un porcentaje sobre el costo; el resultado, un monto en bolivianos.">
            <p v-if="rentabilidadDatos.excluidos_sin_costo" class="bi-aviso">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>
                    {{ rentabilidadDatos.excluidos_sin_costo }}
                    {{ rentabilidadDatos.excluidos_sin_costo === 1 ? 'presupuesto aprobado no tiene' : 'presupuestos aprobados no tienen' }}
                    hoja de costos (son anteriores al motor de margen) y no entran en estos cálculos: su resultado aparecería
                    como el 100 % del precio. Se analizan {{ entero(rentabilidadDatos.analizados) }}.
                </span>
            </p>

            <div class="bi-demanda-par">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Rentabilidad sobre costo (%) <AyudaInfo clave="bi-rentabilidad.rentabilidad" /></span>
                            <p class="card-subtitle">Resultado calculado ÷ costo ajustado × 100, de los presupuestos aprobados</p>
                        </div>
                    </div>
                    <div class="card-body bi-rentabilidad-cuerpo">
                        <BaseChart titulo="Rentabilidad sobre costo" :labels="rentabilidad.labels" :series="rentabilidad.series"
                            :formato="pct" :alto="260" :umbrales="rentabilidad.umbrales" :inicio-proyeccion="inicioFuturo"
                            :marca-eje="marcaMes" unir-huecos vacio="Ningún presupuesto aprobado del periodo tiene hoja de costos." />

                        <div class="bi-semaforo">
                            <p class="bi-semaforo-titulo">
                                Cómo salieron los {{ entero(semaforo.total) }} presupuestos emitidos
                                <AyudaInfo clave="bi-rentabilidad.semaforo" />
                            </p>
                            <ul class="bi-semaforo-zonas">
                                <li v-for="z in semaforo.zonas" :key="z.clase">
                                    <span class="semaforo" :class="z.clase"><span class="semaforo-punto"></span>{{ entero(z.cantidad) }}</span>
                                    <span>{{ z.texto }}</span>
                                </li>
                                <li v-if="semaforo.sinCosto">
                                    <span class="fw-semibold">{{ entero(semaforo.sinCosto) }}</span>
                                    <span>sin hoja de costos</span>
                                </li>
                            </ul>
                        </div>

                        <p v-if="marcaMes" class="bi-nota mt-0">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>{{ nombreMesIncompleto }} es parcial: su rentabilidad puede cambiar al completarse los registros.</span>
                        </p>
                        <ConfiabilidadPronostico :metodo="rentabilidadDatos.metodo" />
                        <MetodoPronostico :metodo="rentabilidadDatos.metodo" />
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Resultado calculado por mes (Bs) <AyudaInfo clave="bi-rentabilidad.utilidad" /></span>
                            <p class="card-subtitle">Importe calculado por el sistema, ya descontados IT e IUE; no equivale a cobros recibidos</p>
                        </div>
                    </div>
                    <div class="card-body bi-rentabilidad-cuerpo">
                        <BaseChart titulo="Resultado calculado por mes" :labels="resultado.labels" :series="resultado.series"
                            :formato="moneyCorto" :formato-tooltip="money" :alto="260" :inicio-proyeccion="inicioFuturo"
                            :marca-eje="marcaMes" vacio="Ningún presupuesto aprobado del periodo tiene hoja de costos." />
                        <ConfiabilidadPronostico :metodo="rentabilidadDatos.metodo_utilidad" />
                        <MetodoPronostico :metodo="rentabilidadDatos.metodo_utilidad" />
                    </div>
                </div>
            </div>

            <section class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        Qué revisar <AyudaInfo clave="bi-rentabilidad.que-revisar" />
                    </span>
                </div>
                <ol class="card-body bi-pasos">
                    <li v-for="(paso, i) in queRevisar" :key="paso.titulo" class="bi-paso">
                        <span class="resumen-accion-numero" aria-hidden="true">{{ i + 1 }}</span>
                        <span class="resumen-accion-cuerpo">
                            <strong class="resumen-accion">{{ paso.titulo }}</strong>
                            <span class="resumen-motivo">{{ paso.detalle }}</span>
                        </span>
                    </li>
                </ol>
            </section>
        </BiBloque>
    </div>
</template>
