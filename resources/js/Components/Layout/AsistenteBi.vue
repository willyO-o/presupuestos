<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ResultadoIa from '@/Components/Reporte/ResultadoIa.vue';
import RobotIa from '@/Components/Layout/RobotIa.vue';
import TextoTecleado from '@/Components/TextoTecleado.vue';
import { textoIaAHtml } from '@/Utils/TextoIa.js';
import { etiquetaDia } from '@/Utils/TimeAgo.js';

/**
 * Asistente de BI: panel lateral para preguntar en lenguaje natural.
 *
 * Vive en el layout (se puede consultar desde cualquier pantalla) y solo se
 * dibuja para quien tiene `reportes.asistente`. No es un modal: el panel
 * convive con la página para poder mirar un reporte y preguntar a la vez.
 *
 * Cada respuesta trae el texto del modelo y, aparte, los resultados de las
 * herramientas que usó: los gráficos y tablas se dibujan con esos datos (de
 * la base), y "Cómo lo calculé" muestra la consulta. El backend (App\Ai\*)
 * pone el tope de gasto, el límite por hora y la conexión de solo lectura.
 *
 * Dos vistas en el mismo panel: `chat` (la conversación) e `historial` (las
 * últimas consultas guardadas, por día, con filtro por fecha). Elegir una
 * consulta del historial reabre su conversación y la resalta; desde ahí se
 * puede seguir preguntando en ella.
 *
 * Espera: una respuesta tarda 1-2 minutos (el modelo encadena varias
 * consultas). Para que no parezca colgada, el turno pendiente muestra la
 * etapa probable según el tiempo transcurrido (ETAPAS_ESPERA) y un reloj; el
 * navegador pide confirmación antes de recargar. Si igual se navega o se
 * recarga, la pregunta queda en sessionStorage y al volver a montar el panel
 * se retoma la espera consultando el historial hasta que la respuesta llegue.
 */
const page = usePage();

const puede = computed(() => {
    const auth = page.props.auth ?? {};

    return auth.is_super_admin === true || (auth.permissions ?? []).includes('reportes.asistente');
});

const SUGERENCIAS = [
    { texto: '¿Cuánto vendimos este trimestre frente al anterior?', icono: 'fa-chart-line' },
    { texto: '¿Qué familia de productos deja más margen este año?', icono: 'fa-chart-pie' },
    { texto: '¿Hay pedidos atrasados ahora?', icono: 'fa-truck-fast' },
    { texto: '¿Qué materiales subieron de precio en los últimos 3 meses?', icono: 'fa-boxes-stacked' },
    { texto: 'Proyecta las ventas de los próximos 3 meses', icono: 'fa-arrow-trend-up' },
    { texto: '¿Qué debería revisar esta semana?', icono: 'fa-list-check' },
];

const CLAVE_ABIERTO = 'asistente-bi-abierto';
const CLAVE_PENDIENTE = 'asistente-bi-pendiente';

/**
 * Lo que el asistente hace, en el orden en que lo hace, con el segundo desde
 * el que se muestra cada etapa. Es una estimación honesta por tiempo (el
 * servidor no informa avance): nada de "ya casi listo" ni porcentajes, que
 * a los 2 minutos dejarían de ser creíbles.
 */
const ETAPAS_ESPERA = [
    { desde: 0, texto: 'Entendiendo tu pregunta' },
    { desde: 5, texto: 'Buscando los datos en el sistema' },
    { desde: 15, texto: 'Consultando ventas, pedidos y costos' },
    { desde: 30, texto: 'Calculando los resultados' },
    { desde: 50, texto: 'Preparando gráficos y tablas' },
    { desde: 75, texto: 'Redactando la respuesta' },
    { desde: 100, texto: 'Está tardando más de lo normal, pero sigue trabajando', larga: true },
    { desde: 150, texto: 'Sigue en curso: las preguntas que comparan varios periodos tardan más', larga: true },
];

/** Pasado este tiempo una espera retomada se da por perdida (la respuesta, si llega, queda en el historial). */
const ESPERA_MAXIMA_MS = 5 * 60 * 1000;
const INTERVALO_SONDEO_MS = 4000;

const abierto = ref(false);
const cargado = ref(false);
const enviando = ref(false);
const consultas = ref([]);
const conversacionId = ref(null);
const estado = ref(null);
const error = ref('');
const pregunta = ref('');
const lista = ref(null);
const campo = ref(null);
const hoy = ref(null);

/** 'chat' | 'historial' */
const vista = ref('chat');
const destacada = ref(null);

/** Respuesta recién llegada: su robot celebra una vez (no las del historial). */
const recienLlegada = ref(null);
let temporizadorCelebrar = null;

function celebrar(consultaId) {
    window.clearTimeout(temporizadorCelebrar);
    recienLlegada.value = consultaId;
    temporizadorCelebrar = window.setTimeout(() => {
        recienLlegada.value = null;
    }, 1500);
}
const tituloHistorial = ref(null);
const botonHistorial = ref(null);

const historial = ref({
    cargando: false,
    error: '',
    consultas: [],
    limite: 20,
    abriendo: null,
});
const filtroFecha = ref('');

const inicioEspera = ref(null);
const ahora = ref(Date.now());
let reloj = null;
let montado = false;

const disponible = computed(() => estado.value?.disponible !== false);

function leerPreferencia() {
    try {
        return window.localStorage.getItem(CLAVE_ABIERTO) === '1';
    } catch {
        return false;
    }
}

function guardarPreferencia(valor) {
    try {
        window.localStorage.setItem(CLAVE_ABIERTO, valor ? '1' : '0');
    } catch {
        // Sin almacenamiento (ventana privada): el panel arranca cerrado.
    }
}

/* --- Espera de la respuesta --- */

function leerPendiente() {
    try {
        return JSON.parse(window.sessionStorage.getItem(CLAVE_PENDIENTE) ?? 'null');
    } catch {
        return null;
    }
}

function guardarPendiente(pendiente) {
    try {
        window.sessionStorage.setItem(CLAVE_PENDIENTE, JSON.stringify(pendiente));
    } catch {
        // Sin almacenamiento: la espera no se puede retomar tras recargar, nada más.
    }
}

function borrarPendiente() {
    try {
        window.sessionStorage.removeItem(CLAVE_PENDIENTE);
    } catch {
        // Ídem.
    }
}

function avisarAlSalir(evento) {
    evento.preventDefault();
    evento.returnValue = '';
}

/** El reloj cuenta desde `inicio` (no desde 0): una espera retomada sigue donde iba. */
function iniciarReloj(inicio) {
    window.clearInterval(reloj);
    inicioEspera.value = inicio;
    ahora.value = Date.now();
    reloj = window.setInterval(() => {
        ahora.value = Date.now();
    }, 1000);
    window.addEventListener('beforeunload', avisarAlSalir);
}

function detenerReloj() {
    window.clearInterval(reloj);
    reloj = null;
    inicioEspera.value = null;
    window.removeEventListener('beforeunload', avisarAlSalir);
}

const segundosEspera = computed(() => (
    inicioEspera.value === null ? 0 : Math.max(0, Math.floor((ahora.value - inicioEspera.value) / 1000))
));

const etapaEspera = computed(() => ETAPAS_ESPERA.findLast((etapa) => segundosEspera.value >= etapa.desde));

const relojEspera = computed(() => {
    const segundos = segundosEspera.value;

    return `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')}`;
});

/**
 * Al cambiar de etapa el turno puede crecer (los textos largos ocupan dos
 * líneas y traen la nota de no recargar). Corre al terminar de teclear la
 * etapa, cuando el texto nuevo ya ocupa su lugar, y baja solo si el usuario ya
 * estaba abajo: no le mueve la vista si está releyendo algo anterior.
 */
function seguirAbajo() {
    const cuerpo = lista.value;

    if (cuerpo && cuerpo.scrollHeight - cuerpo.scrollTop - cuerpo.clientHeight < 160) {
        bajar();
    }
}

/**
 * Cambia el turno pendiente por la respuesta. Si mientras tanto se abrió otra
 * conversación (Nueva o el Historial), el turno ya no está y no se toca nada:
 * la respuesta queda igual en el historial.
 */
function reemplazarTurno(turno, nuevo = null) {
    const indice = consultas.value.indexOf(turno);

    if (indice === -1) {
        return false;
    }

    if (nuevo) {
        consultas.value.splice(indice, 1, nuevo);
    } else {
        consultas.value.splice(indice, 1);
    }

    return true;
}

/**
 * Retoma una pregunta que quedó esperando cuando se cambió de página o se
 * recargó: el servidor la sigue procesando, así que se consulta el historial
 * hasta que aparezca. Se reconoce por el texto y por haber llegado después
 * de enviarla (con 2 minutos de margen por si el reloj del equipo no coincide
 * con el del servidor).
 */
async function retomarPendiente() {
    const pendiente = leerPendiente();

    if (!pendiente || enviando.value) {
        return;
    }

    // La espera es de quien preguntó: si en esta pestaña se cerró sesión y
    // entró otra persona, se descarta (el servidor igual solo le muestra sus
    // propias consultas, pero vería una espera que no es suya).
    if (pendiente.usuario !== (page.props.auth?.user?.id ?? null)) {
        borrarPendiente();

        return;
    }

    if (Date.now() - pendiente.inicio > ESPERA_MAXIMA_MS) {
        borrarPendiente();

        return;
    }

    enviando.value = true;
    const turno = { id: `pendiente-${pendiente.inicio}`, pregunta: pendiente.pregunta, pendiente: true, resultados: [], dia: hoy.value };
    consultas.value.push(turno);
    iniciarReloj(pendiente.inicio);
    bajar();

    while (montado && Date.now() - pendiente.inicio < ESPERA_MAXIMA_MS) {
        await new Promise((resolver) => window.setTimeout(resolver, INTERVALO_SONDEO_MS));

        if (!montado) {
            return;
        }

        try {
            const { data } = await window.axios.get(route('reportes.bi.asistente.consultas'));
            const llegada = data.consultas.find((c) => c.pregunta === pendiente.pregunta
                && new Date(c.fecha).getTime() >= pendiente.inicio - 120_000);

            if (llegada) {
                detenerReloj();
                borrarPendiente();
                enviando.value = false;

                if (reemplazarTurno(turno)) {
                    await abrirConsulta(llegada);
                    celebrar(llegada.id);
                }

                return;
            }
        } catch {
            // Un corte de red momentáneo: se reintenta en el próximo sondeo.
        }
    }

    if (!montado) {
        return;
    }

    detenerReloj();
    borrarPendiente();
    enviando.value = false;
    reemplazarTurno(turno);
    error.value = 'La respuesta está tardando demasiado. Cuando llegue la verás en el Historial.';
}

async function cargarHistorial() {
    try {
        const { data } = await window.axios.get(route('reportes.bi.asistente.historial'));
        estado.value = data.estado;
        hoy.value = data.hoy;
        consultas.value = data.consultas;
        conversacionId.value = data.consultas.at(-1)?.conversacion_id ?? null;
    } catch {
        error.value = 'No se pudo cargar el historial del asistente.';
    } finally {
        cargado.value = true;
        bajar();
    }

    retomarPendiente();
}

async function alternar(valor = !abierto.value) {
    abierto.value = valor;
    guardarPreferencia(valor);

    if (valor) {
        if (!cargado.value) {
            await cargarHistorial();
        }
        await nextTick();
        if (vista.value === 'chat') {
            campo.value?.focus();
        }
    }
}

function bajar() {
    nextTick(() => {
        if (lista.value) {
            lista.value.scrollTop = lista.value.scrollHeight;
        }
    });
}

async function preguntar(texto = pregunta.value) {
    const limpia = texto.trim();

    if (!limpia || enviando.value || !disponible.value) {
        return;
    }

    error.value = '';
    enviando.value = true;
    destacada.value = null;
    pregunta.value = '';
    const inicio = Date.now();
    const pendiente = { id: `pendiente-${inicio}`, pregunta: limpia, pendiente: true, resultados: [], dia: hoy.value };
    consultas.value.push(pendiente);
    iniciarReloj(inicio);
    guardarPendiente({ pregunta: limpia, inicio, usuario: page.props.auth?.user?.id ?? null });
    bajar();

    try {
        const { data } = await window.axios.post(route('reportes.bi.asistente.preguntar'), {
            pregunta: limpia,
            conversacion_id: conversacionId.value,
        });

        if (reemplazarTurno(pendiente, data.consulta)) {
            if (data.consulta.estado === 'OK') {
                celebrar(data.consulta.id);
            }
            conversacionId.value = data.consulta.conversacion_id ?? conversacionId.value;
        }
        estado.value = data.estado;
    } catch (e) {
        // El servidor contestó con un error (límite por hora, validación): no
        // hay respuesta que esperar, aunque el panel ya se haya desmontado.
        if (e.response) {
            borrarPendiente();
        }
        reemplazarTurno(pendiente);
        pregunta.value = limpia;
        error.value = e.response?.data?.message
            ?? e.response?.data?.errors?.pregunta?.[0]
            ?? 'No se pudo enviar la pregunta. Revisa tu conexión e intenta de nuevo.';
    } finally {
        // Si el panel se desmontó (se cambió de página), la instancia nueva
        // retoma la espera con lo guardado en sessionStorage: no se borra.
        if (montado) {
            detenerReloj();
            borrarPendiente();
            enviando.value = false;
            bajar();
            nextTick(() => campo.value?.focus());
        }
    }
}

function nuevaConversacion() {
    consultas.value = [];
    conversacionId.value = null;
    destacada.value = null;
    error.value = '';
    vista.value = 'chat';
    nextTick(() => campo.value?.focus());
}

/* --- Historial --- */

async function cargarConsultas() {
    historial.value.cargando = true;
    historial.value.error = '';

    try {
        const { data } = await window.axios.get(route('reportes.bi.asistente.consultas'), {
            params: filtroFecha.value ? { fecha: filtroFecha.value } : {},
        });
        historial.value.consultas = data.consultas;
        historial.value.limite = data.limite;
        hoy.value = data.hoy;
    } catch (e) {
        historial.value.consultas = [];
        historial.value.error = e.response?.data?.errors?.fecha?.[0]
            ?? 'No se pudo cargar el historial. Revisa tu conexión e intenta de nuevo.';
    } finally {
        historial.value.cargando = false;
    }
}

function abrirHistorial() {
    vista.value = 'historial';
    cargarConsultas();
    nextTick(() => tituloHistorial.value?.focus());
}

function volverAlChat() {
    vista.value = 'chat';
    nextTick(() => campo.value?.focus());
}

function alternarHistorial() {
    if (vista.value === 'historial') {
        volverAlChat();
    } else {
        abrirHistorial();
    }
}

function filtrarPorFecha() {
    cargarConsultas();
}

function quitarFiltro() {
    filtroFecha.value = '';
    cargarConsultas();
}

async function abrirConsulta(item) {
    if (historial.value.abriendo) {
        return;
    }

    historial.value.abriendo = item.id;
    historial.value.error = '';

    try {
        const { data } = await window.axios.get(route('reportes.bi.asistente.historial'), {
            params: { consulta_id: item.id },
        });
        estado.value = data.estado;
        hoy.value = data.hoy;
        consultas.value = data.consultas;
        conversacionId.value = item.conversacion_id ?? null;
        error.value = '';
        destacada.value = item.id;
        vista.value = 'chat';

        await nextTick();
        const turno = document.getElementById(`ia-turno-${item.id}`);
        const reducir = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        turno?.scrollIntoView({ block: 'start', behavior: reducir ? 'auto' : 'smooth' });
        turno?.focus({ preventScroll: true });
    } catch (e) {
        historial.value.error = e.response?.status === 404
            ? 'Esa consulta ya no está en tu historial.'
            : 'No se pudo abrir la consulta. Intenta de nuevo.';
    } finally {
        historial.value.abriendo = null;
    }
}

/** Consultas del historial agrupadas por día, en el orden que llegan. */
const gruposHistorial = computed(() => {
    const grupos = [];

    for (const consulta of historial.value.consultas) {
        const ultimo = grupos.at(-1);

        if (ultimo?.dia === consulta.dia) {
            ultimo.consultas.push(consulta);
        } else {
            grupos.push({ dia: consulta.dia, consultas: [consulta] });
        }
    }

    return grupos;
});

const subtituloHistorial = computed(() => {
    if (filtroFecha.value) {
        return etiquetaDia(filtroFecha.value, hoy.value);
    }

    return `Tus ${historial.value.limite} consultas más recientes`;
});

/** El historial llegó al tope: hay más guardadas de las que se ven. */
const historialRecortado = computed(() => historial.value.consultas.length >= historial.value.limite);

function metaConsulta(item) {
    if (item.estado === 'ERROR') {
        return [{ icono: 'fa-circle-exclamation', texto: 'Sin respuesta' }];
    }

    const partes = [];

    if (item.graficos) {
        partes.push({ icono: 'fa-chart-column', texto: `${item.graficos} ${item.graficos === 1 ? 'gráfico' : 'gráficos'}` });
    }
    if (item.tablas) {
        partes.push({ icono: 'fa-table', texto: `${item.tablas} ${item.tablas === 1 ? 'tabla' : 'tablas'}` });
    }

    return partes.length ? partes : [{ icono: 'fa-align-left', texto: 'Respuesta en texto' }];
}

/** Separador de día en la conversación: solo cuando cambia de un turno al siguiente. */
function cambiaDeDia(indice) {
    const actual = consultas.value[indice]?.dia;

    return Boolean(actual) && (indice === 0 || consultas.value[indice - 1]?.dia !== actual);
}

/* --- Teclado y formulario --- */

function alTeclear(evento) {
    if (evento.key === 'Enter' && !evento.shiftKey) {
        evento.preventDefault();
        preguntar();
    }
}

function alEscape(evento) {
    if (evento.key !== 'Escape' || !abierto.value) {
        return;
    }

    // Escape retrocede un nivel: del historial al chat, del chat a cerrar.
    if (vista.value === 'historial') {
        volverAlChat();
        botonHistorial.value?.focus();
    } else {
        alternar(false);
    }
}

const usoTexto = computed(() => {
    if (!estado.value) {
        return '';
    }

    const gasto = Number(estado.value.gasto_mes ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const tope = Number(estado.value.presupuesto ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 });

    return `Uso del mes: USD ${gasto} de ${tope}`;
});

/** Estado de la IA para la cabecera; null mientras no se cargó el historial. */
const estadoCabecera = computed(() => {
    if (!estado.value) {
        return null;
    }

    return disponible.value
        ? { clase: 'ia-estado-ok', texto: 'Disponible' }
        : { clase: 'ia-estado-off', texto: 'No disponible' };
});

onMounted(() => {
    montado = true;
    document.addEventListener('keydown', alEscape);

    if (puede.value && leerPreferencia() && window.innerWidth >= 1024) {
        alternar(true);
    }
});

onBeforeUnmount(() => {
    window.clearTimeout(temporizadorCelebrar);
    montado = false;
    document.removeEventListener('keydown', alEscape);
    window.clearInterval(reloj);
    window.removeEventListener('beforeunload', avisarAlSalir);
});

// Textarea que crece con el texto, hasta 6 líneas.
watch(pregunta, () => nextTick(() => {
    if (campo.value) {
        campo.value.style.height = 'auto';
        campo.value.style.height = `${Math.min(campo.value.scrollHeight, 150)}px`;
    }
}));
</script>

<template>
    <div v-if="puede" class="contents">
        <button v-show="!abierto" type="button" class="ia-lanzador" aria-controls="asistente-bi" :aria-expanded="abierto"
            aria-label="Abrir el asistente de IA" @click="alternar(true)">
            <span class="ia-lanzador-marca" aria-hidden="true">
                <RobotIa animo="reposo" />
            </span>
            <span class="ia-lanzador-texto" aria-hidden="true">
                <span class="ia-lanzador-titulo">Asistente IA</span>
                <span class="ia-lanzador-sub">Pregunta sobre tus datos</span>
            </span>
        </button>

        <aside v-show="abierto" id="asistente-bi" class="ia-panel" aria-label="Asistente de inteligencia de negocios">
            <header class="ia-panel-cabecera">
                <div class="ia-panel-identidad">
                    <RobotIa class="ia-robot-cabecera" variante="cabeza" :animo="estado && !disponible ? 'apagado' : 'quieto'" />
                    <div class="min-w-0">
                        <h2 class="ia-panel-titulo">
                            Asistente IA
                            <span v-if="estadoCabecera" class="ia-estado" :class="estadoCabecera.clase">{{ estadoCabecera.texto }}</span>
                        </h2>
                        <p class="ia-panel-sub">Responde con datos de las sucursales que ves</p>
                    </div>
                </div>
                <div class="ia-panel-acciones">
                    <button ref="botonHistorial" type="button" class="chart-action" :class="{ 'is-active': vista === 'historial' }"
                        :aria-pressed="vista === 'historial'" title="Consultas anteriores" @click="alternarHistorial">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span class="chart-action-text">Historial</span>
                    </button>
                    <button type="button" class="chart-action" :disabled="enviando || (!consultas.length && vista === 'chat')" title="Nueva conversación"
                        aria-label="Nueva conversación" @click="nuevaConversacion">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <button type="button" class="chart-action" aria-label="Cerrar el asistente" @click="alternar(false)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </header>

            <!-- Vista: historial de consultas -->
            <section v-if="vista === 'historial'" class="ia-historial" aria-labelledby="ia-historial-titulo">
                <div class="ia-historial-barra">
                    <div class="ia-historial-encabezado">
                        <button type="button" class="ia-volver" aria-label="Volver a la conversación" @click="volverAlChat">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                        <div class="min-w-0">
                            <h3 id="ia-historial-titulo" ref="tituloHistorial" class="ia-historial-titulo" tabindex="-1">
                                Historial de consultas
                            </h3>
                            <p class="ia-historial-sub">{{ subtituloHistorial }}</p>
                        </div>
                    </div>

                    <div class="ia-historial-filtro">
                        <label for="ia-historial-fecha" class="ia-fecha">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <span class="sr-only">Ver las consultas de una fecha</span>
                            <input id="ia-historial-fecha" v-model="filtroFecha" type="date" class="ia-fecha-input" :max="hoy ?? undefined"
                                @change="filtrarPorFecha" />
                        </label>
                        <button v-if="filtroFecha" type="button" class="ia-filtro-quitar" @click="quitarFiltro">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            Ver las últimas
                        </button>
                    </div>
                </div>

                <div class="ia-historial-cuerpo" :aria-busy="historial.cargando">
                    <p v-if="historial.error" class="ia-error ia-historial-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ historial.error }}</span>
                        <button type="button" class="ia-enlace" @click="cargarConsultas">Reintentar</button>
                    </p>

                    <div v-if="historial.cargando" class="ia-historial-esqueleto" role="status">
                        <span class="sr-only">Cargando el historial…</span>
                        <div v-for="n in 2" :key="n" class="ia-esqueleto-grupo" aria-hidden="true">
                            <span class="ia-esqueleto ia-esqueleto-dia"></span>
                            <span v-for="f in 3" :key="f" class="ia-esqueleto ia-esqueleto-fila"></span>
                        </div>
                    </div>

                    <div v-else-if="!historial.consultas.length && !historial.error" class="ia-historial-vacio">
                        <span class="ia-historial-vacio-icono" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <template v-if="filtroFecha">
                            <p class="ia-vacio-titulo">Sin consultas ese día</p>
                            <p class="ia-vacio-texto">No hiciste preguntas al asistente el {{ etiquetaDia(filtroFecha, hoy).toLowerCase() }}.</p>
                            <button type="button" class="btn btn-soft-primary btn-sm" @click="quitarFiltro">Ver las últimas consultas</button>
                        </template>
                        <template v-else>
                            <p class="ia-vacio-titulo">Todavía no hay consultas</p>
                            <p class="ia-vacio-texto">Cada pregunta que hagas queda guardada aquí, con sus gráficos, para volver a verla cuando la necesites.</p>
                            <button type="button" class="btn btn-soft-primary btn-sm" @click="volverAlChat">Hacer una pregunta</button>
                        </template>
                    </div>

                    <template v-else>
                        <section v-for="grupo in gruposHistorial" :key="grupo.dia" class="ia-historial-grupo">
                            <h4 class="ia-historial-dia">
                                {{ etiquetaDia(grupo.dia, hoy) }}
                                <span class="ia-historial-conteo">{{ grupo.consultas.length }}</span>
                            </h4>
                            <ol class="ia-historial-lista">
                                <li v-for="item in grupo.consultas" :key="item.id">
                                    <button type="button" class="ia-historial-item" :class="{ 'is-error': item.estado === 'ERROR', 'is-abriendo': historial.abriendo === item.id }"
                                        :disabled="historial.abriendo !== null" @click="abrirConsulta(item)">
                                        <span class="ia-historial-hora">{{ item.hora }}</span>
                                        <span class="ia-historial-texto">
                                            <span class="ia-historial-pregunta">{{ item.pregunta }}</span>
                                            <span class="ia-historial-meta">
                                                <span v-for="m in metaConsulta(item)" :key="m.texto">
                                                    <i class="fa-solid" :class="m.icono" aria-hidden="true"></i>
                                                    {{ m.texto }}
                                                </span>
                                            </span>
                                        </span>
                                        <span class="ia-historial-flecha" aria-hidden="true">
                                            <span v-if="historial.abriendo === item.id" class="ia-girando"></span>
                                            <i v-else class="fa-solid fa-chevron-right"></i>
                                        </span>
                                    </button>
                                </li>
                            </ol>
                        </section>

                        <p v-if="historialRecortado" class="ia-historial-nota">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            {{ filtroFecha
                                ? `Se muestran las ${historial.limite} más recientes de ese día.`
                                : 'Se guardan todas tus consultas. Para ver otras más antiguas, elige una fecha.' }}
                        </p>
                    </template>
                </div>
            </section>

            <!-- Vista: conversación -->
            <div v-show="vista === 'chat'" ref="lista" class="ia-panel-cuerpo" aria-live="polite">
                <div v-if="!cargado" class="ia-cargando" role="status">
                    <span class="table-loading-dot"></span><span class="table-loading-dot"></span><span class="table-loading-dot"></span>
                </div>

                <div v-else-if="!consultas.length" class="ia-vacio">
                    <RobotIa class="ia-robot-bienvenida" :animo="disponible ? 'reposo' : 'apagado'" />
                    <p class="ia-vacio-titulo">Pregunta por ventas, márgenes, pedidos, costos o alertas.</p>
                    <p class="ia-vacio-texto">
                        Las cifras salen de consultas a la base de datos: cada respuesta muestra cómo se calculó.
                    </p>
                    <p class="ia-sugerencias-titulo">Prueba con</p>
                    <div class="ia-sugerencias">
                        <button v-for="s in SUGERENCIAS" :key="s.texto" type="button" class="ia-sugerencia"
                            :disabled="!disponible || enviando" @click="preguntar(s.texto)">
                            <span class="ia-sugerencia-icono" aria-hidden="true"><i class="fa-solid" :class="s.icono"></i></span>
                            <span class="ia-sugerencia-texto">{{ s.texto }}</span>
                            <i class="fa-solid fa-arrow-right ia-sugerencia-flecha" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <template v-else>
                    <template v-for="(c, indice) in consultas" :key="c.id">
                        <p v-if="cambiaDeDia(indice)" class="ia-separador-dia">
                            <span>{{ etiquetaDia(c.dia, hoy) }}</span>
                        </p>

                        <article :id="`ia-turno-${c.id}`" class="ia-turno" :class="{ 'is-destacado': destacada === c.id }" tabindex="-1">
                            <p class="ia-pregunta">{{ c.pregunta }}</p>
                            <p v-if="c.hora" class="ia-pregunta-hora">{{ c.hora }}</p>

                            <div v-if="c.pendiente" class="ia-respuesta ia-respuesta-pendiente">
                                <!-- La burbuja del robot "escribe" y asiente con cada cambio de etapa. -->
                                <RobotIa class="ia-robot-espera" animo="pensando" :pulso="etapaEspera.texto" />
                                <div class="ia-espera">
                                    <!-- Burbuja de pensamiento: la estela de circulitos apunta al robot. -->
                                    <div class="ia-pensamiento" aria-hidden="true">
                                        <span class="ia-pensamiento-puntos">
                                            <span class="table-loading-dot"></span><span class="table-loading-dot"></span><span class="table-loading-dot"></span>
                                        </span>
                                        Pensando
                                    </div>
                                    <!-- Solo la etapa se anuncia al lector de pantalla, completa; el
                                         tecleo letra por letra y el reloj son solo visuales. -->
                                    <p class="ia-espera-linea" :class="{ 'is-larga': etapaEspera.larga }" role="status">
                                        <span class="sr-only">{{ etapaEspera.texto }}</span>
                                        <TextoTecleado :texto="etapaEspera.texto" ola aria-hidden="true" @terminado="seguirAbajo" />
                                    </p>
                                    <p class="ia-espera-nota">
                                        {{ etapaEspera.larga ? 'No recargues la página: la respuesta llegará aquí.' : 'Suele tardar 1 a 2 minutos.' }}
                                    </p>
                                </div>
                                <span class="ia-espera-reloj" aria-hidden="true">{{ relojEspera }}</span>
                            </div>

                            <div v-else class="ia-respuesta" :class="{ 'ia-respuesta-error': c.estado === 'ERROR' }">
                                <p class="ia-autor">
                                    <RobotIa class="ia-robot-autor" variante="cabeza" :animo="recienLlegada === c.id ? 'contento' : 'quieto'" />
                                    Asistente
                                </p>
                                <div class="ia-respuesta-texto" v-html="textoIaAHtml(c.respuesta)"></div>
                                <ResultadoIa v-for="(r, i) in c.resultados" :key="i" :resultado="r" />
                                <p v-if="c.modelo" class="ia-respuesta-meta">{{ c.proveedor }} · {{ c.modelo }}</p>
                            </div>
                        </article>
                    </template>
                </template>
            </div>

            <footer v-show="vista === 'chat'" class="ia-panel-pie">
                <p v-if="error" class="ia-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ error }}
                </p>
                <p v-else-if="estado && !disponible" class="ia-error" role="alert">
                    <i class="fa-solid fa-plug-circle-xmark"></i> {{ estado.motivo }}
                </p>

                <form class="ia-formulario" @submit.prevent="preguntar()">
                    <label for="asistente-bi-pregunta" class="sr-only">Tu pregunta</label>
                    <textarea id="asistente-bi-pregunta" ref="campo" v-model="pregunta" class="ia-campo" rows="1"
                        maxlength="500" placeholder="Ej.: ¿Cuánto vendimos en letras 3D este año?"
                        :disabled="enviando || !disponible" @keydown="alTeclear"></textarea>
                    <button type="submit" class="ia-enviar" :disabled="enviando || !pregunta.trim() || !disponible"
                        aria-label="Enviar pregunta">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
                <div class="ia-pie-notas">
                    <span class="ia-atajo"><kbd>Enter</kbd> envía · <kbd>Shift</kbd> + <kbd>Enter</kbd> nueva línea</span>
                    <span class="ia-uso" :class="{ 'ia-uso-alerta': estado?.cerca_del_tope }">{{ usoTexto }}</span>
                </div>
            </footer>
        </aside>
    </div>
</template>
