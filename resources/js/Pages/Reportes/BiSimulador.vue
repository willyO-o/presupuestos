<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiBloque from '@/Components/Reporte/BiBloque.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import { CLASES_SEMAFORO, evaluarMargen, textoRecomendacion as recomendacion } from '@/Utils/MotorMargen';
import { money, moneyCorto, numero, porcentaje, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/**
 * Simulador de decisiones en tres pasos: 1. revisar los datos de partida,
 * 2. cambiar los supuestos, 3. comparar el resultado.
 *
 * Todos los números salen de `evaluarMargen()` (Utils/MotorMargen.js), el
 * espejo del motor de margen que ya usa el formulario de cotización para su
 * panel en vivo, con las tasas y umbrales de `config/margen.php` que manda el
 * servidor. Acá no hay fórmulas de impuestos ni de semáforo propias: si el
 * motor cambia, el simulador cambia con él. Una simulación no guarda nada.
 *
 * Los pasos 1 y 2 editan un borrador; "Calcular escenario" lo aplica al
 * paso 3. El resultado queda debajo de los controles, fuera de la vista
 * mientras se mueven: recalcular en cada movimiento cambiaría números que
 * nadie está mirando. Si el borrador difiere de lo calculado, el paso 3 lo
 * avisa.
 */
const config = props.datos.config;
const umbralAceptar = config.semaforo.umbral_verde;
const umbralRevisar = config.semaforo.umbral_amarillo;

const TONO_SEMAFORO = { VERDE: 'success', AMARILLO: 'warning', ROJO: 'danger' };
const ICONO_SEMAFORO = { VERDE: 'fa-solid fa-check', AMARILLO: 'fa-solid fa-exclamation', ROJO: 'fa-solid fa-xmark' };

/* ----- Pasos 1 y 2: datos de partida y supuestos (borrador) --------------- */

const SIN_CAMBIOS = { variacionCosto: 0, trasladarCosto: false, variacionPrecio: 0, descuento: 0, variacionVolumen: 0 };

/** Supuestos del paso 2: barra y casilla de porcentaje, con el mismo rango. */
const SUPUESTOS = [
    { clave: 'variacionCosto', etiqueta: 'Variación del costo de materiales', min: -30, max: 60 },
    { clave: 'variacionPrecio', etiqueta: 'Ajuste del precio de venta', min: -30, max: 50 },
    { clave: 'descuento', etiqueta: 'Descuento al cliente', min: 0, max: 30 },
    { clave: 'variacionVolumen', etiqueta: 'Variación de presupuestos aprobados', min: -50, max: 100 },
];

function partida() {
    return {
        costo: String(props.datos.base.costo),
        precio: String(props.datos.base.precio),
        volumen: String(props.datos.base.volumen_mensual),
        comoPrecio: '',
        ...SIN_CAMBIOS,
    };
}

/** El borrador en números: los campos de texto llegan como cadena. */
function fijar(valores) {
    return {
        costo: Number(valores.costo || 0),
        precio: Number(valores.precio || 0),
        volumen: Number(valores.volumen || 0),
        variacionCosto: Number(valores.variacionCosto || 0),
        trasladarCosto: Boolean(valores.trasladarCosto),
        variacionPrecio: Number(valores.variacionPrecio || 0),
        descuento: Number(valores.descuento || 0),
        variacionVolumen: Number(valores.variacionVolumen || 0),
    };
}

const borrador = reactive(partida());
const aplicado = ref(fijar(borrador));

const hayCambiosSinCalcular = computed(() => {
    const actual = fijar(borrador);

    return Object.keys(actual).some((clave) => actual[clave] !== aplicado.value[clave]);
});

/** Sin supuestos el escenario es la base, aunque se hayan editado los datos de partida. */
const sinCambios = computed(() => ['variacionCosto', 'variacionPrecio', 'descuento', 'variacionVolumen']
    .every((clave) => aplicado.value[clave] === 0));

/** La casilla acepta lo que se escriba: se redondea y se lleva al rango de la barra. */
function acotar(supuesto) {
    const valor = Math.round(Number(borrador[supuesto.clave]));

    borrador[supuesto.clave] = Number.isFinite(valor) ? Math.min(supuesto.max, Math.max(supuesto.min, valor)) : 0;
}

const subtituloPartida = props.datos.base.desde_datos
    ? (props.datos.base.presupuestos === 1
        ? 'Datos del único presupuesto aprobado con hoja de costos del periodo.'
        : `Promedio de ${numero(props.datos.base.presupuestos, 0)} presupuestos aprobados con hoja de costos.`)
    : 'El periodo no tiene presupuestos aprobados con hoja de costos: se muestra un ejemplo.';

/**
 * "Cómo definir el precio": mantener el escrito o calcularlo con un margen
 * (el sugerido del sistema o el mínimo de un tipo de proyecto). El costo ya
 * es costo AJUSTADO (lo que guarda la cotización, con el factor de
 * complejidad), así que solo se aplica el margen.
 */
const OPCIONES_PRECIO = [
    { valor: '', texto: 'Mantener el precio ingresado', margen: null },
    ...(config.margen_sugerido > 0
        ? [{ valor: 'sugerido', texto: `Margen sugerido del sistema (${porcentaje(config.margen_sugerido * 100, 0)})`, margen: config.margen_sugerido }]
        : []),
    ...props.datos.tipos_proyecto.map((t) => ({
        valor: `tipo-${t.id}`,
        texto: `Margen mínimo de ${t.nombre} (${porcentaje(t.margen_minimo * 100, 0)})`,
        margen: t.margen_minimo,
    })),
];

const margenElegido = computed(() => OPCIONES_PRECIO.find((o) => o.valor === borrador.comoPrecio)?.margen ?? null);

// Con un margen elegido el precio sigue al costo; escribirlo a mano vuelve a "Mantener".
watch([margenElegido, () => borrador.costo], ([margen]) => {
    if (margen !== null) {
        borrador.precio = (Number(borrador.costo || 0) * (1 + margen)).toFixed(2);
    }
});

function precioEscritoAMano() {
    borrador.comoPrecio = '';
}

const reducirMovimiento = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function calcular() {
    aplicado.value = fijar(borrador);

    // Lleva al resultado solo si no está a la vista.
    const bloque = document.getElementById('simulador-resultado');
    const arriba = bloque?.getBoundingClientRect().top ?? 0;

    if (bloque && (arriba < 0 || arriba > window.innerHeight * 0.5)) {
        bloque.scrollIntoView({ behavior: reducirMovimiento ? 'auto' : 'smooth', block: 'start' });
    }
}

function restablecer() {
    Object.assign(borrador, partida());
    aplicado.value = fijar(borrador);
}

/* ----- Paso 3: resultado (sobre lo aplicado) ------------------------------ */

const costoBase = computed(() => aplicado.value.costo);
const precioBase = computed(() => aplicado.value.precio);
const volumenBase = computed(() => aplicado.value.volumen);

/** Precio con el ajuste y el descuento del escenario, antes de trasladar materiales. */
const precioAjustado = computed(() => precioBase.value
    * (1 + aplicado.value.variacionPrecio / 100)
    * (1 - aplicado.value.descuento / 100));

const variacionCosto = computed(() => aplicado.value.variacionCosto / 100);
const costoEscenario = computed(() => costoBase.value * (1 + variacionCosto.value));
const precioEscenario = computed(() => (aplicado.value.trasladarCosto
    ? precioAjustado.value * (1 + variacionCosto.value)
    : precioAjustado.value));

const resultadoBase = computed(() => evaluarMargen(config, costoBase.value, precioBase.value));
const resultado = computed(() => evaluarMargen(config, costoEscenario.value, precioEscenario.value));

const volumenEscenario = computed(() => volumenBase.value * (1 + aplicado.value.variacionVolumen / 100));
const resultadoMensualBase = computed(() => resultadoBase.value.utilidadReal * volumenBase.value);
const resultadoMensual = computed(() => resultado.value.utilidadReal * volumenEscenario.value);

/**
 * Precio mínimo para un umbral del semáforo con el costo del escenario.
 *
 * El resultado calculado es lineal en el precio, así que basta evaluar el
 * motor en dos precios para conocer la recta y despejar dónde cruza
 * `umbral × costo`. Se le pregunta al motor en vez de reescribir su fórmula.
 */
function precioMinimo(umbral) {
    const costo = costoEscenario.value;

    if (costo <= 0) {
        return null;
    }

    const u0 = evaluarMargen(config, costo, 0).utilidadReal;
    const pendiente = evaluarMargen(config, costo, 1).utilidadReal - u0;

    return pendiente > 0 ? (umbral * costo - u0) / pendiente : null;
}

const minimoAceptar = computed(() => precioMinimo(umbralAceptar));
const minimoRevisar = computed(() => precioMinimo(umbralRevisar));

/**
 * Cuánto más puede subir el costo, al precio del escenario, antes de que la
 * recomendación deje de ser "Aceptar". Mismo truco: (resultado − umbral ·
 * costo) es lineal en el costo.
 */
const toleranciaCosto = computed(() => {
    const precio = precioEscenario.value;
    const holgura = (c) => evaluarMargen(config, c, precio).utilidadReal - umbralAceptar * c;

    const h0 = holgura(0);
    const pendiente = holgura(1) - h0;

    if (pendiente >= 0 || costoEscenario.value <= 0) {
        return null;
    }

    return (-h0 / pendiente / costoEscenario.value - 1) * 100;
});

const aumentoNecesario = computed(() => (minimoAceptar.value && precioEscenario.value > 0
    ? (minimoAceptar.value / precioEscenario.value - 1) * 100
    : null));

/** Insignia junto al título del paso 3. */
const estadoEscenario = computed(() => {
    if (hayCambiosSinCalcular.value) {
        return { texto: 'Hay cambios sin calcular', clase: 'badge-soft-warning' };
    }

    return sinCambios.value
        ? { texto: 'Sin cambios aplicados', clase: 'badge-soft-secondary' }
        : { texto: 'Escenario calculado', clase: 'badge-soft-primary' };
});

/** Qué pasó con la rentabilidad, qué significa en bolivianos y qué margen queda. */
const veredicto = computed(() => {
    const estado = resultado.value.estado;

    if (costoEscenario.value <= 0 || precioEscenario.value <= 0) {
        return { estado: 'ROJO', titulo: 'Ingrese un costo y un precio mayores a cero para evaluar el escenario.', detalles: [] };
    }

    const antes = resultadoBase.value.rentabilidad * 100;
    const ahora = resultado.value.rentabilidad * 100;
    let titulo;

    if (Math.abs(ahora - antes) < 0.05) {
        titulo = sinCambios.value
            ? `El escenario mantiene la rentabilidad de la base: ${porcentaje(ahora)} sobre costo.`
            : `Con estos cambios la rentabilidad se mantiene en ${porcentaje(ahora)} sobre costo.`;
    } else {
        titulo = `El escenario ${ahora > antes ? 'mejora' : 'reduce'} la rentabilidad: pasa de ${porcentaje(antes)} a ${porcentaje(ahora)} sobre costo.`;
    }

    const detalles = [`Por cada Bs 100 de costo ajustado, el resultado calculado es ${money(ahora)}.`];

    if (estado === 'VERDE' && toleranciaCosto.value !== null) {
        detalles.push(`Con este precio, el costo puede subir hasta ${porcentaje(toleranciaCosto.value)} antes de que la recomendación deje de ser "Aceptar".`);
    } else if (estado !== 'VERDE' && aumentoNecesario.value !== null) {
        detalles.push(`Para que el sistema recomiende "Aceptar", el precio sin IVA tendría que subir más de ${porcentaje(aumentoNecesario.value)} (por encima de ${money(minimoAceptar.value)}).`);
    }

    return { estado, titulo, detalles };
});

/** "+Bs 120,00 frente a la base." / "Igual que la base." */
function frenteABase(diferencia, formato, minimo = 0.005) {
    if (Math.abs(diferencia) < minimo) {
        return 'Igual que la base.';
    }

    return `${diferencia > 0 ? '+' : '−'}${formato(Math.abs(diferencia))} frente a la base.`;
}

const notaResultado = computed(() => frenteABase(resultado.value.utilidadReal - resultadoBase.value.utilidadReal, money));
const notaRentabilidad = computed(() => frenteABase(
    (resultado.value.rentabilidad - resultadoBase.value.rentabilidad) * 100,
    (v) => `${numero(v, 1)} puntos`,
    0.05,
));
const notaMensual = computed(() => `Con ${numero(volumenEscenario.value, 1)} presupuestos aprobados al mes.`);

/**
 * Filas de la comparación base / escenario. `subirEsBueno: null` = sin color:
 * que el IUE baje no es una buena noticia (baja porque bajó el resultado).
 */
const comparativa = computed(() => {
    const b = resultadoBase.value;
    const e = resultado.value;

    return [
        { concepto: 'Costo ajustado', base: b.costoAjustado, escenario: e.costoAjustado, formato: money, subirEsBueno: false },
        { concepto: 'Precio sin IVA', base: b.precio, escenario: e.precio, formato: money, subirEsBueno: true },
        { concepto: 'IT calculado', base: b.it, escenario: e.it, formato: money, subirEsBueno: null },
        { concepto: 'IUE calculado', base: b.iue, escenario: e.iue, formato: money, subirEsBueno: null },
        { concepto: 'Resultado por presupuesto', base: b.utilidadReal, escenario: e.utilidadReal, formato: money, subirEsBueno: true, destacada: true },
        { concepto: 'Rentabilidad sobre costo', base: b.rentabilidad * 100, escenario: e.rentabilidad * 100, formato: porcentaje, subirEsBueno: true, puntos: true },
        { concepto: 'Precio al cliente con IVA', base: b.precioFinal, escenario: e.precioFinal, formato: money, subirEsBueno: null },
        { concepto: 'Resultado mensual estimado', base: resultadoMensualBase.value, escenario: resultadoMensual.value, formato: money, subirEsBueno: true, destacada: true },
    ];
});

function sinDiferencia(fila) {
    return Math.abs(fila.escenario - fila.base) < (fila.puntos ? 0.05 : 0.005);
}

function claseDiferencia(fila) {
    if (sinDiferencia(fila)) {
        return 'text-muted';
    }

    if (fila.subirEsBueno === null) {
        return '';
    }

    return (fila.escenario > fila.base) === fila.subirEsBueno ? 'text-success' : 'text-danger';
}

function diferencia(fila) {
    if (sinDiferencia(fila)) {
        return fila.puntos ? '0 puntos' : money(0);
    }

    const dif = fila.escenario - fila.base;
    const signo = dif > 0 ? '+' : '−';

    return fila.puntos ? `${signo}${numero(Math.abs(dif), 1)} puntos` : `${signo}${fila.formato(Math.abs(dif))}`;
}

/** Tasas y umbrales del sistema para "Ver fórmulas". */
const tasa = (fraccion) => porcentaje(fraccion * 100);

/* ----- Gráficos ----------------------------------------------------------- */

const PASOS_COSTO = Array.from({ length: 19 }, (_, i) => -30 + i * 5);

/**
 * Sensibilidad: rentabilidad según cuánto varíe el costo, con el ajuste y el
 * descuento del escenario, manteniendo el precio o trasladando la variación.
 * La segunda línea es plana a propósito: trasladar la variación mantiene la
 * rentabilidad — es la comparación que hay que ver.
 */
const sensibilidad = computed(() => {
    const rentabilidadCon = (paso, trasladar) => {
        const costo = costoBase.value * (1 + paso / 100);
        const precio = trasladar ? precioAjustado.value * (1 + paso / 100) : precioAjustado.value;

        return costo > 0 ? +(evaluarMargen(config, costo, precio).rentabilidad * 100).toFixed(2) : null;
    };

    return {
        labels: PASOS_COSTO.map((p) => variacion(p, 0)),
        series: [
            { nombre: 'Mantener precio', datos: PASOS_COSTO.map((p) => rentabilidadCon(p, false)) },
            { nombre: 'Trasladar variación al precio', punteada: true, datos: PASOS_COSTO.map((p) => rentabilidadCon(p, true)) },
        ],
        umbrales: [
            { valor: umbralAceptar * 100, nombre: `Aceptar ${porcentaje(umbralAceptar * 100, 0)}` },
            { valor: umbralRevisar * 100, nombre: `Revisar precio ${porcentaje(umbralRevisar * 100, 0)}` },
        ],
    };
});

/** Marca en el eje la variación de costo del escenario (si cae en un paso del gráfico). */
const marcaEscenario = computed(() => {
    const paso = aplicado.value.variacionCosto;

    return paso !== 0 && PASOS_COSTO.includes(paso) ? { etiqueta: variacion(paso, 0), texto: 'Escenario' } : null;
});

/** A dónde va cada boliviano del precio, base contra escenario. */
const composicion = computed(() => {
    const partes = (r) => [r.costoAjustado, r.it + r.iue, r.utilidadReal].map((v) => +v.toFixed(2));

    return {
        labels: ['Costo ajustado', 'IT + IUE', 'Resultado calculado'],
        series: [
            { nombre: 'Base', datos: partes(resultadoBase.value) },
            { nombre: 'Escenario', datos: partes(resultado.value) },
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
        <BiEncabezado :periodo="datos.periodo" :filters="filters">
            <template #aviso>
                <p class="bi-aviso mb-0">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Esta simulación no modifica las cotizaciones ni garantiza resultados futuros.</span>
                </p>
            </template>
        </BiEncabezado>

        <form class="bi-demanda-par" @submit.prevent="calcular">
            <!-- 1. Datos de partida -->
            <BiBloque :numero="1" titulo="Revise los datos de partida" :subtitulo="subtituloPartida" tarjeta>
                <template #ayuda><AyudaInfo clave="bi-simulador.base" /></template>

                <div class="bi-simulador-campos">
                    <div>
                        <label class="form-label" for="sim-costo">Costo ajustado por presupuesto (Bs)</label>
                        <input id="sim-costo" v-model="borrador.costo" v-decimal="2" type="text" inputmode="decimal"
                            class="form-control">
                    </div>
                    <div>
                        <label class="form-label" for="sim-precio">Precio de venta sin IVA (Bs)</label>
                        <input id="sim-precio" v-model="borrador.precio" v-decimal="2" type="text" inputmode="decimal"
                            class="form-control" aria-describedby="sim-precio-nota" @input="precioEscritoAMano">
                        <p v-if="margenElegido !== null" id="sim-precio-nota" class="bi-campo-nota">
                            Calculado con el margen elegido abajo. Si lo cambia a mano, se mantiene el precio ingresado.
                        </p>
                    </div>
                    <div>
                        <label class="form-label" for="sim-volumen">Presupuestos aprobados por mes</label>
                        <input id="sim-volumen" v-model="borrador.volumen" v-decimal="1" type="text" inputmode="decimal"
                            class="form-control">
                    </div>
                    <div>
                        <label class="form-label" for="sim-como-precio">Cómo definir el precio</label>
                        <select id="sim-como-precio" v-model="borrador.comoPrecio" class="form-control">
                            <option v-for="o in OPCIONES_PRECIO" :key="o.valor" :value="o.valor">{{ o.texto }}</option>
                        </select>
                    </div>
                </div>

                <p class="bi-aviso mb-0">
                    <i class="fa-solid fa-circle-info"></i>
                    <span v-if="datos.base.desde_datos">
                        Los datos iniciales provienen del periodo seleccionado. Puede cambiarlos para probar otro caso.
                    </span>
                    <span v-else>Son valores de ejemplo: cámbielos por los de un trabajo real para probar un caso.</span>
                </p>
            </BiBloque>

            <!-- 2. Supuestos -->
            <BiBloque :numero="2" titulo="Cambie los supuestos"
                subtitulo="Ajuste los porcentajes para simular diferentes escenarios." tarjeta>
                <template #ayuda><AyudaInfo clave="bi-simulador.palancas" /></template>

                <div class="bi-supuestos">
                    <div v-for="s in SUPUESTOS" :key="s.clave" class="bi-supuesto">
                        <label class="bi-supuesto-etiqueta" :for="`sim-${s.clave}`">{{ s.etiqueta }}</label>
                        <input :id="`sim-${s.clave}`" v-model.number="borrador[s.clave]" type="range" :min="s.min"
                            :max="s.max" step="1" class="bi-range" :aria-valuetext="variacion(borrador[s.clave], 0)">
                        <div class="bi-supuesto-valor">
                            <input v-model.number="borrador[s.clave]" type="number" :min="s.min" :max="s.max" step="1"
                                class="form-control" :aria-label="`${s.etiqueta}, en porcentaje`" @change="acotar(s)">
                            <span aria-hidden="true">%</span>
                        </div>
                        <label v-if="s.clave === 'variacionCosto'" class="bi-supuesto-casilla">
                            <input v-model="borrador.trasladarCosto" type="checkbox" class="bi-check">
                            <span>
                                Ajustar también el precio para trasladar la variación de materiales
                                <small>Si se activa, el precio sube o baja en el mismo porcentaje que el material.</small>
                            </span>
                        </label>
                    </div>
                </div>

                <p class="bi-aviso mb-0">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>0 % significa mantener el valor de partida. El descuento se aplica sobre el precio ya ajustado.</span>
                </p>

                <div class="bi-simulador-botones">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-calculator"></i> Calcular escenario
                    </button>
                    <button type="button" class="btn btn-outline-primary" @click="restablecer">
                        <i class="fa-solid fa-rotate-left"></i> Restablecer
                    </button>
                    <span class="bi-simulador-pendiente" role="status">
                        {{ hayCambiosSinCalcular ? 'Hay cambios sin calcular.' : '' }}
                    </span>
                </div>
            </BiBloque>
        </form>

        <!-- 3. Resultado -->
        <BiBloque id="simulador-resultado" class="bi-simulador-resultado" :numero="3" titulo="Compare el resultado"
            subtitulo="Resultados calculados con el mismo motor de margen que las cotizaciones." tarjeta>
            <template #ayuda><AyudaInfo clave="bi-simulador.resultado" /></template>
            <template #etiqueta>
                <span class="badge" :class="estadoEscenario.clase">{{ estadoEscenario.texto }}</span>
            </template>
            <template #accion>
                <span :class="CLASES_SEMAFORO[resultado.estado]">
                    <span class="semaforo-punto"></span>{{ recomendacion(resultado.estado) }} según el sistema
                </span>
            </template>

            <div class="bi-veredicto" :class="`bi-veredicto-${veredicto.estado.toLowerCase()}`" aria-live="polite">
                <span class="bi-veredicto-icono" aria-hidden="true"><i :class="ICONO_SEMAFORO[veredicto.estado]"></i></span>
                <div>
                    <p class="bi-veredicto-titulo">{{ veredicto.titulo }}</p>
                    <p v-for="detalle in veredicto.detalles" :key="detalle" class="bi-veredicto-detalle">{{ detalle }}</p>
                </div>
            </div>

            <div class="bi-simulador-kpis">
                <BiKpi etiqueta="Resultado por presupuesto" ayuda="bi-simulador.kpi-resultado" icono="fa-solid fa-chart-column"
                    :valor="money(resultado.utilidadReal)" :nota="notaResultado" />
                <BiKpi etiqueta="Rentabilidad sobre costo" ayuda="bi-simulador.kpi-rentabilidad" icono="fa-solid fa-percent"
                    :tono="TONO_SEMAFORO[resultado.estado]" :valor="porcentaje(resultado.rentabilidad * 100)"
                    :nota="notaRentabilidad" />
                <BiKpi etiqueta="Resultado mensual estimado" ayuda="bi-simulador.kpi-mensual" icono="fa-regular fa-calendar"
                    :valor="money(resultadoMensual)" :nota="notaMensual" />
            </div>

            <div class="table-responsive">
                <table class="table-dashboard table-sm bi-simulador-tabla">
                    <caption class="sr-only">Comparación entre la base y el escenario</caption>
                    <thead>
                        <tr>
                            <th scope="col">Concepto</th>
                            <th scope="col" class="text-end bi-col-opcional">Base</th>
                            <th scope="col" class="text-end">Escenario</th>
                            <th scope="col" class="text-end">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fila in comparativa" :key="fila.concepto" :class="{ 'is-destacada': fila.destacada }">
                            <td>{{ fila.concepto }}</td>
                            <td class="text-end bi-col-opcional">{{ fila.formato(fila.base) }}</td>
                            <td class="text-end">{{ fila.formato(fila.escenario) }}</td>
                            <td class="text-end" :class="claseDiferencia(fila)">{{ diferencia(fila) }}</td>
                        </tr>
                        <tr>
                            <td>Recomendación del sistema</td>
                            <td class="text-end bi-col-opcional">
                                <span :class="CLASES_SEMAFORO[resultadoBase.estado]">
                                    <span class="semaforo-punto"></span>{{ recomendacion(resultadoBase.estado) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <span :class="CLASES_SEMAFORO[resultado.estado]">
                                    <span class="semaforo-punto"></span>{{ recomendacion(resultado.estado) }}
                                </span>
                            </td>
                            <td class="text-end text-muted">
                                {{ resultadoBase.estado === resultado.estado ? 'Sin cambio' : 'Cambia' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bi-simulador-pie">
                <details class="bi-formulas">
                    <summary class="bi-ver-mas">
                        <i class="fa-solid fa-chevron-down"></i> Ver fórmulas, impuestos y supuestos del sistema
                    </summary>
                    <dl class="bi-formulas-lista">
                        <div>
                            <dt>Costo ajustado</dt>
                            <dd>Costo de partida (ya incluye el factor de complejidad) × (1 + variación del costo de materiales).</dd>
                        </div>
                        <div>
                            <dt>Precio sin IVA</dt>
                            <dd>
                                Precio de partida × (1 + ajuste del precio) × (1 − descuento). Si se traslada la variación
                                de materiales, también × (1 + esa variación).
                            </dd>
                        </div>
                        <div>
                            <dt>IT calculado</dt>
                            <dd>{{ tasa(config.impuestos.it) }} del precio sin IVA.</dd>
                        </div>
                        <div>
                            <dt>IUE calculado</dt>
                            <dd>{{ tasa(config.impuestos.iue) }} de (precio sin IVA − costo ajustado − IT).</dd>
                        </div>
                        <div>
                            <dt>Resultado calculado</dt>
                            <dd>Precio sin IVA − costo ajustado − IT − IUE.</dd>
                        </div>
                        <div>
                            <dt>Rentabilidad sobre costo</dt>
                            <dd>Resultado calculado ÷ costo ajustado.</dd>
                        </div>
                        <div>
                            <dt>Precio al cliente con IVA</dt>
                            <dd>Precio sin IVA + {{ tasa(config.impuestos.iva) }} de IVA (la instalación, si la hay, va aparte).</dd>
                        </div>
                        <div>
                            <dt>Recomendación del sistema</dt>
                            <dd>
                                "Aceptar" si la rentabilidad supera el {{ tasa(umbralAceptar) }}; "Revisar precio" si supera
                                el {{ tasa(umbralRevisar) }}; "No aceptar" en otro caso.
                            </dd>
                        </div>
                        <div>
                            <dt>Resultado mensual estimado</dt>
                            <dd>Resultado por presupuesto × presupuestos aprobados por mes (con su variación).</dd>
                        </div>
                    </dl>
                    <p class="bi-nota">
                        <i class="fa-solid fa-gear"></i>
                        <span>Las tasas y los umbrales son los de la configuración del sistema, los mismos que usan las cotizaciones.</span>
                    </p>
                </details>
                <p class="bi-aviso mb-0">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>El resultado mensual depende del volumen supuesto; no es un pronóstico de ventas confirmadas.</span>
                </p>
            </div>
        </BiBloque>

        <div class="bi-demanda-par">
            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">¿Qué pasa con la rentabilidad si cambia el costo de materiales? <AyudaInfo clave="bi-simulador.sensibilidad" /></span>
                        <p class="card-subtitle">Mantener el precio o trasladar la variación, con el ajuste y el descuento del escenario</p>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart titulo="Rentabilidad sobre costo según la variación del costo de materiales"
                        :labels="sensibilidad.labels" :series="sensibilidad.series" :formato="pct"
                        :umbrales="sensibilidad.umbrales" :marca-eje="marcaEscenario" :alto="280"
                        vacio="Ingrese un costo mayor a cero para ver la sensibilidad." />
                    <p class="bi-nota">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            Sin ajustar el precio, un mayor costo reduce el resultado calculado. Trasladar la variación
                            mantiene la rentabilidad, pero cambia el precio al cliente.
                        </span>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">¿Cómo se distribuye el precio sin IVA? <AyudaInfo clave="bi-simulador.composicion" /></span>
                        <p class="card-subtitle">Costo, impuestos y resultado calculado por presupuesto: base contra escenario</p>
                    </div>
                </div>
                <div class="card-body">
                    <BaseChart titulo="Composición del precio sin IVA" tipo="bar" horizontal :labels="composicion.labels"
                        :series="composicion.series" :formato="moneyCorto" :alto="280" />
                    <p v-if="minimoAceptar !== null && minimoRevisar !== null" class="bi-nota">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            Con el costo del escenario, el precio sin IVA tiene que superar {{ money(minimoAceptar) }} para
                            que el sistema recomiende "Aceptar", y {{ money(minimoRevisar) }} para "Revisar precio". Son
                            valores calculados con las reglas del sistema y no garantizan resultados.
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <p class="bi-nota mt-0">
            <i class="fa-solid fa-circle-info"></i>
            <span>
                Los colores usan los umbrales del sistema. Que el sistema recomiende "Aceptar" no reemplaza la revisión de
                costos, impuestos y condiciones comerciales.
            </span>
        </p>
    </div>
</template>
