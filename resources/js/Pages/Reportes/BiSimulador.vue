<script setup>
import { computed, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import { CLASES_SEMAFORO, evaluarMargen } from '@/Utils/MotorMargen';
import { money, moneyCorto, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/**
 * Simulador de decisiones: "¿qué pasa con la utilidad si…?".
 *
 * Todos los números salen de `evaluarMargen()` (Utils/MotorMargen.js), el
 * espejo del motor de margen que ya usa el formulario de cotización para su
 * panel en vivo, con las tasas y umbrales de `config/margen.php` que manda el
 * servidor. Acá no hay fórmulas de impuestos ni de semáforo propias: si el
 * motor cambia, el simulador cambia con él. Una simulación no guarda nada.
 *
 * El escenario base es el presupuesto promedio del periodo (con hoja de
 * costos); se puede pisar a mano para simular un trabajo puntual.
 */
const config = props.datos.config;

const base = reactive({
    costo: String(props.datos.base.costo),
    precio: String(props.datos.base.precio),
    volumen: String(props.datos.base.volumen_mensual),
});

const palancas = reactive({
    variacionCosto: 0,
    trasladarCosto: false,
    variacionPrecio: 0,
    descuento: 0,
    variacionVolumen: 0,
});

const tipoProyecto = reactive({ id: '' });

function aplicarTipo() {
    const tipo = props.datos.tipos_proyecto.find((t) => t.id === Number(tipoProyecto.id));

    if (!tipo) {
        return;
    }

    // El costo base del simulador ya es costo AJUSTADO (lo que guarda la
    // cotización), así que solo se aplica el margen mínimo del tipo.
    base.precio = (Number(base.costo || 0) * (1 + tipo.margen_minimo)).toFixed(2);
}

function restablecer() {
    base.costo = String(props.datos.base.costo);
    base.precio = String(props.datos.base.precio);
    base.volumen = String(props.datos.base.volumen_mensual);
    Object.assign(palancas, { variacionCosto: 0, trasladarCosto: false, variacionPrecio: 0, descuento: 0, variacionVolumen: 0 });
    tipoProyecto.id = '';
}

const costoBase = computed(() => Number(base.costo || 0));
const precioBase = computed(() => Number(base.precio || 0));
const volumenBase = computed(() => Number(base.volumen || 0));

/** Precio del escenario para un costo que varió `vc` (fracción). */
function precioPara(vc) {
    const precio = palancas.trasladarCosto ? precioBase.value * (1 + vc) : precioBase.value;

    return precio * (1 + palancas.variacionPrecio / 100) * (1 - palancas.descuento / 100);
}

const resultadoBase = computed(() => evaluarMargen(config, costoBase.value, precioBase.value));

const costoEscenario = computed(() => costoBase.value * (1 + palancas.variacionCosto / 100));
const precioEscenario = computed(() => precioPara(palancas.variacionCosto / 100));
const resultado = computed(() => evaluarMargen(config, costoEscenario.value, precioEscenario.value));

const volumenEscenario = computed(() => volumenBase.value * (1 + palancas.variacionVolumen / 100));

/**
 * Precio mínimo para un umbral del semáforo con el costo del escenario.
 *
 * La utilidad real es lineal en el precio, así que basta evaluar el motor en
 * dos precios para conocer la recta y despejar dónde cruza `umbral × costo`.
 * Se le pregunta al motor en vez de reescribir su fórmula.
 */
function precioMinimo(umbral) {
    const costo = costoEscenario.value;

    if (costo <= 0) {
        return 0;
    }

    const u0 = evaluarMargen(config, costo, 0).utilidadReal;
    const pendiente = evaluarMargen(config, costo, 1).utilidadReal - u0;

    return pendiente > 0 ? (umbral * costo - u0) / pendiente : null;
}

/**
 * Cuánto más puede subir el costo, al precio del escenario, antes de salir de
 * verde. Mismo truco: (utilidad − umbral·costo) es lineal en el costo.
 */
const toleranciaCosto = computed(() => {
    const precio = precioEscenario.value;
    const umbral = config.semaforo.umbral_verde;
    const holgura = (c) => evaluarMargen(config, c, precio).utilidadReal - umbral * c;

    const h0 = holgura(0);
    const pendiente = holgura(1) - h0;

    if (pendiente >= 0 || costoEscenario.value <= 0) {
        return null;
    }

    const costoMaximo = -h0 / pendiente;

    return (costoMaximo / costoEscenario.value - 1) * 100;
});

const minimoVerde = computed(() => precioMinimo(config.semaforo.umbral_verde));
const minimoAmarillo = computed(() => precioMinimo(config.semaforo.umbral_amarillo));

const aumentoNecesario = computed(() => (minimoVerde.value && precioEscenario.value > 0
    ? (minimoVerde.value / precioEscenario.value - 1) * 100
    : null));

const utilidadMensualBase = computed(() => resultadoBase.value.utilidadReal * volumenBase.value);
const utilidadMensual = computed(() => resultado.value.utilidadReal * volumenEscenario.value);

/** Filas de la comparativa base / escenario. */
const comparativa = computed(() => {
    const b = resultadoBase.value;
    const e = resultado.value;

    return [
        { concepto: 'Costo ajustado', base: b.costoAjustado, escenario: e.costoAjustado, formato: money, subirEsBueno: false },
        { concepto: 'Precio sin IVA', base: b.precio, escenario: e.precio, formato: money, subirEsBueno: true },
        { concepto: 'IT', base: b.it, escenario: e.it, formato: money, subirEsBueno: false },
        { concepto: 'IUE', base: b.iue, escenario: e.iue, formato: money, subirEsBueno: false },
        { concepto: 'Utilidad real', base: b.utilidadReal, escenario: e.utilidadReal, formato: money, subirEsBueno: true, destacada: true },
        { concepto: 'Rentabilidad (utilidad ÷ costo)', base: b.rentabilidad * 100, escenario: e.rentabilidad * 100, formato: porcentaje, subirEsBueno: true, puntos: true },
        { concepto: 'Precio al cliente con IVA', base: b.precioFinal, escenario: e.precioFinal, formato: money, subirEsBueno: true },
        { concepto: 'Utilidad mensual estimada', base: utilidadMensualBase.value, escenario: utilidadMensual.value, formato: money, subirEsBueno: true, destacada: true },
    ];
});

function claseDiferencia(fila) {
    const dif = fila.escenario - fila.base;

    if (Math.abs(dif) < 0.005) {
        return 'text-muted';
    }

    return (dif > 0) === fila.subirEsBueno ? 'text-success' : 'text-danger';
}

function diferencia(fila) {
    const dif = fila.escenario - fila.base;

    if (fila.puntos) {
        return `${dif >= 0 ? '+' : '−'}${numero(Math.abs(dif), 1)} pts`;
    }

    return `${dif >= 0 ? '+' : '−'}${fila.formato(Math.abs(dif))}`;
}

/* ----- Gráficos ----------------------------------------------------------- */

const PASOS_COSTO = Array.from({ length: 19 }, (_, i) => -30 + i * 5);

/**
 * Sensibilidad: rentabilidad según cuánto varíe el costo, con el precio que
 * fija el escenario y trasladando el aumento al precio. La segunda línea es
 * plana a propósito: trasladar el aumento mantiene la rentabilidad — es la
 * comparación que hay que ver.
 */
const sensibilidad = computed(() => {
    const precioFijo = precioBase.value * (1 + palancas.variacionPrecio / 100) * (1 - palancas.descuento / 100);

    return {
        labels: PASOS_COSTO.map((p) => variacion(p, 0)),
        series: [
            {
                nombre: 'Manteniendo el precio',
                datos: PASOS_COSTO.map((p) => {
                    const costo = costoBase.value * (1 + p / 100);

                    return costo > 0 ? +(evaluarMargen(config, costo, precioFijo).rentabilidad * 100).toFixed(2) : null;
                }),
            },
            {
                nombre: 'Trasladando el aumento al precio',
                punteada: true,
                datos: PASOS_COSTO.map((p) => {
                    const costo = costoBase.value * (1 + p / 100);

                    return costo > 0 ? +(evaluarMargen(config, costo, precioFijo * (1 + p / 100)).rentabilidad * 100).toFixed(2) : null;
                }),
            },
        ],
        umbrales: [
            { valor: config.semaforo.umbral_verde * 100, nombre: `Verde ${numero(config.semaforo.umbral_verde * 100, 0)} %` },
            { valor: config.semaforo.umbral_amarillo * 100, nombre: `Amarillo ${numero(config.semaforo.umbral_amarillo * 100, 0)} %` },
        ],
    };
});

/** A dónde va cada boliviano del precio, base contra escenario. */
const composicion = computed(() => {
    const b = resultadoBase.value;
    const e = resultado.value;

    return {
        labels: ['Costo ajustado', 'Impuestos (IT + IUE)', 'Utilidad real'],
        series: [
            { nombre: 'Base', datos: [b.costoAjustado, b.it + b.iue, b.utilidadReal].map((v) => +v.toFixed(2)) },
            { nombre: 'Escenario', datos: [e.costoAjustado, e.it + e.iue, e.utilidadReal].map((v) => +v.toFixed(2)) },
        ],
    };
});

function pct(value) {
    return porcentaje(value);
}
</script>

<template>
    <Head title="Simulador de decisiones" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="row">
            <!-- Controles -->
            <div class="col-lg-4 bi-columna">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Escenario base</span>
                            <p class="card-subtitle">
                                <template v-if="datos.base.desde_datos">
                                    Presupuesto promedio de {{ datos.base.presupuestos }} aprobado(s) con hoja de costos en el periodo
                                </template>
                                <template v-else>
                                    Sin presupuestos con hoja de costos en el periodo: base de ejemplo, edítala
                                </template>
                            </p>
                        </div>
                    </div>
                    <div class="card-body bi-simulador-controles">
                        <div>
                            <label class="form-label" for="sim-costo">Costo ajustado (Bs)</label>
                            <input id="sim-costo" v-model="base.costo" v-decimal="2" type="text" inputmode="decimal"
                                class="form-control">
                        </div>
                        <div>
                            <label class="form-label" for="sim-precio">Precio sin IVA (Bs)</label>
                            <input id="sim-precio" v-model="base.precio" v-decimal="2" type="text" inputmode="decimal"
                                class="form-control">
                        </div>
                        <div v-if="datos.tipos_proyecto.length">
                            <label class="form-label" for="sim-tipo">Aplicar margen de un tipo de proyecto</label>
                            <select id="sim-tipo" v-model="tipoProyecto.id" class="form-control" @change="aplicarTipo">
                                <option value="">— Mantener el precio escrito —</option>
                                <option v-for="t in datos.tipos_proyecto" :key="t.id" :value="t.id">
                                    {{ t.nombre }} ({{ numero(t.margen_minimo * 100, 0) }} %)
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="sim-volumen">Presupuestos aprobados por mes</label>
                            <input id="sim-volumen" v-model="base.volumen" v-decimal="1" type="text" inputmode="decimal"
                                class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">¿Qué pasa si…?</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="restablecer">
                            <i class="fa-solid fa-rotate-left"></i> Restablecer
                        </button>
                    </div>
                    <div class="card-body bi-simulador-controles">
                        <div class="bi-control">
                            <div class="bi-control-encabezado">
                                <label for="sim-vc">El costo de materiales varía</label>
                                <output for="sim-vc">{{ variacion(palancas.variacionCosto, 0) }}</output>
                            </div>
                            <input id="sim-vc" v-model.number="palancas.variacionCosto" type="range" min="-30" max="60"
                                step="1" class="bi-range">
                            <label class="bi-control-check">
                                <input v-model="palancas.trasladarCosto" type="checkbox" class="bi-check">
                                Trasladar la variación al precio
                            </label>
                        </div>
                        <div class="bi-control">
                            <div class="bi-control-encabezado">
                                <label for="sim-vp">Ajuste de precio</label>
                                <output for="sim-vp">{{ variacion(palancas.variacionPrecio, 0) }}</output>
                            </div>
                            <input id="sim-vp" v-model.number="palancas.variacionPrecio" type="range" min="-30" max="50"
                                step="1" class="bi-range">
                        </div>
                        <div class="bi-control">
                            <div class="bi-control-encabezado">
                                <label for="sim-d">Descuento al cliente</label>
                                <output for="sim-d">{{ porcentaje(palancas.descuento, 0) }}</output>
                            </div>
                            <input id="sim-d" v-model.number="palancas.descuento" type="range" min="0" max="30"
                                step="1" class="bi-range">
                        </div>
                        <div class="bi-control">
                            <div class="bi-control-encabezado">
                                <label for="sim-vv">Volumen de presupuestos aprobados</label>
                                <output for="sim-vv">{{ variacion(palancas.variacionVolumen, 0) }}</output>
                            </div>
                            <input id="sim-vv" v-model.number="palancas.variacionVolumen" type="range" min="-50" max="100"
                                step="5" class="bi-range">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resultado -->
            <div class="col-lg-8 bi-columna">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Resultado del escenario</span>
                            <p class="card-subtitle">Calculado con el mismo motor de margen que las cotizaciones</p>
                        </div>
                        <span :class="CLASES_SEMAFORO[resultado.estado]">
                            <span class="semaforo-punto"></span>{{ resultado.recomendacion }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="bi-veredicto" :class="`bi-veredicto-${resultado.estado.toLowerCase()}`">
                            <p class="bi-veredicto-titulo">
                                <template v-if="resultado.estado === 'VERDE'">
                                    El escenario es rentable.
                                    <span v-if="toleranciaCosto !== null">
                                        El costo todavía puede subir {{ porcentaje(toleranciaCosto) }} antes de salir de verde.
                                    </span>
                                </template>
                                <template v-else-if="aumentoNecesario !== null">
                                    Para volver a verde hay que subir el precio {{ porcentaje(aumentoNecesario) }}
                                    (a {{ money(minimoVerde) }} sin IVA).
                                </template>
                                <template v-else>Carga un costo y un precio para evaluar el escenario.</template>
                            </p>
                            <p class="bi-veredicto-detalle">
                                Precio mínimo con este costo: {{ money(minimoVerde ?? 0) }} para verde ·
                                {{ money(minimoAmarillo ?? 0) }} para amarillo.
                            </p>
                        </div>

                        <div class="table-responsive">
                            <table class="table-dashboard table-sm">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-end">Base</th>
                                        <th class="text-end">Escenario</th>
                                        <th class="text-end">Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="fila in comparativa" :key="fila.concepto" :class="{ 'fw-semibold': fila.destacada }">
                                        <td>{{ fila.concepto }}</td>
                                        <td class="text-end chart-table-num">{{ fila.formato(fila.base) }}</td>
                                        <td class="text-end chart-table-num">{{ fila.formato(fila.escenario) }}</td>
                                        <td class="text-end chart-table-num" :class="claseDiferencia(fila)">{{ diferencia(fila) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Semáforo</td>
                                        <td class="text-end">
                                            <span :class="CLASES_SEMAFORO[resultadoBase.estado]"><span class="semaforo-punto"></span>{{ resultadoBase.estado }}</span>
                                        </td>
                                        <td class="text-end">
                                            <span :class="CLASES_SEMAFORO[resultado.estado]"><span class="semaforo-punto"></span>{{ resultado.estado }}</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Sensibilidad al costo de materiales</span>
                            <p class="card-subtitle">Rentabilidad si el costo varía, con los ajustes de precio y descuento del escenario</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Sensibilidad de la rentabilidad al costo" :labels="sensibilidad.labels"
                            :series="sensibilidad.series" :formato="pct" :umbrales="sensibilidad.umbrales" :alto="260"
                            vacio="Carga un costo mayor a cero para ver la sensibilidad." />
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">A dónde va el precio</span>
                            <p class="card-subtitle">Costo, impuestos y utilidad por presupuesto: base contra escenario</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Composición del precio" tipo="bar" :labels="composicion.labels"
                            :series="composicion.series" :formato="moneyCorto" :alto="240" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
