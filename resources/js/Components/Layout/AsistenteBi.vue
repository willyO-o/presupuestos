<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ResultadoIa from '@/Components/Reporte/ResultadoIa.vue';
import { textoIaAHtml } from '@/Utils/TextoIa.js';

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

async function cargarHistorial() {
    try {
        const { data } = await window.axios.get(route('reportes.bi.asistente.historial'));
        estado.value = data.estado;
        consultas.value = data.consultas;
        conversacionId.value = data.consultas.at(-1)?.conversacion_id ?? null;
    } catch {
        error.value = 'No se pudo cargar el historial del asistente.';
    } finally {
        cargado.value = true;
        bajar();
    }
}

async function alternar(valor = !abierto.value) {
    abierto.value = valor;
    guardarPreferencia(valor);

    if (valor) {
        if (!cargado.value) {
            await cargarHistorial();
        }
        await nextTick();
        campo.value?.focus();
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
    pregunta.value = '';
    const pendiente = { id: `pendiente-${Date.now()}`, pregunta: limpia, pendiente: true, resultados: [] };
    consultas.value.push(pendiente);
    bajar();

    try {
        const { data } = await window.axios.post(route('reportes.bi.asistente.preguntar'), {
            pregunta: limpia,
            conversacion_id: conversacionId.value,
        });

        consultas.value.splice(consultas.value.indexOf(pendiente), 1, data.consulta);
        conversacionId.value = data.consulta.conversacion_id ?? conversacionId.value;
        estado.value = data.estado;
    } catch (e) {
        consultas.value.splice(consultas.value.indexOf(pendiente), 1);
        pregunta.value = limpia;
        error.value = e.response?.data?.message
            ?? e.response?.data?.errors?.pregunta?.[0]
            ?? 'No se pudo enviar la pregunta. Revisa tu conexión e intenta de nuevo.';
    } finally {
        enviando.value = false;
        bajar();
        nextTick(() => campo.value?.focus());
    }
}

function nuevaConversacion() {
    consultas.value = [];
    conversacionId.value = null;
    error.value = '';
    campo.value?.focus();
}

function alTeclear(evento) {
    if (evento.key === 'Enter' && !evento.shiftKey) {
        evento.preventDefault();
        preguntar();
    }
}

function alEscape(evento) {
    if (evento.key === 'Escape' && abierto.value) {
        alternar(false);
    }
}

/** Estado de la IA para la cabecera; null mientras no se cargó el historial. */
const estadoCabecera = computed(() => {
    if (!estado.value) {
        return null;
    }

    return disponible.value
        ? { clase: 'ia-estado-ok', texto: 'Disponible' }
        : { clase: 'ia-estado-off', texto: 'No disponible' };
});

const usoTexto = computed(() => {
    if (!estado.value) {
        return '';
    }

    const gasto = Number(estado.value.gasto_mes ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const tope = Number(estado.value.presupuesto ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 0 });

    return `Uso del mes: USD ${gasto} de ${tope}`;
});

onMounted(() => {
    document.addEventListener('keydown', alEscape);

    if (puede.value && leerPreferencia() && window.innerWidth >= 1024) {
        alternar(true);
    }
});

onBeforeUnmount(() => document.removeEventListener('keydown', alEscape));

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
            <span class="ia-marca ia-lanzador-marca" aria-hidden="true">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            <span class="ia-lanzador-texto" aria-hidden="true">
                <span class="ia-lanzador-titulo">Asistente IA</span>
                <span class="ia-lanzador-sub">Pregunta sobre tus datos</span>
            </span>
        </button>

        <aside v-show="abierto" id="asistente-bi" class="ia-panel" aria-label="Asistente de inteligencia de negocios">
            <header class="ia-panel-cabecera">
                <div class="ia-panel-identidad">
                    <span class="ia-marca" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    <div class="min-w-0">
                        <h2 class="ia-panel-titulo">
                            Asistente IA
                            <span v-if="estadoCabecera" class="ia-estado" :class="estadoCabecera.clase">{{ estadoCabecera.texto }}</span>
                        </h2>
                        <p class="ia-panel-sub">Responde con datos del sistema, de las sucursales que ves</p>
                    </div>
                </div>
                <div class="ia-panel-acciones">
                    <button type="button" class="chart-action" :disabled="!consultas.length" title="Nueva conversación"
                        @click="nuevaConversacion">
                        <i class="fa-solid fa-plus"></i>
                        <span class="chart-action-text">Nueva</span>
                    </button>
                    <button type="button" class="chart-action" aria-label="Cerrar el asistente" @click="alternar(false)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </header>

            <div ref="lista" class="ia-panel-cuerpo" aria-live="polite">
                <div v-if="!cargado" class="ia-cargando" role="status">
                    <span class="table-loading-dot"></span><span class="table-loading-dot"></span><span class="table-loading-dot"></span>
                </div>

                <div v-else-if="!consultas.length" class="ia-vacio">
                    <span class="ia-marca ia-marca-lg" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
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
                    <article v-for="c in consultas" :key="c.id" class="ia-turno">
                        <p class="ia-pregunta">{{ c.pregunta }}</p>

                        <div v-if="c.pendiente" class="ia-respuesta ia-respuesta-pendiente" role="status">
                            <span class="ia-marca ia-marca-xs" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                            <span class="table-loading-dot"></span><span class="table-loading-dot"></span><span class="table-loading-dot"></span>
                            <span>Consultando los datos…</span>
                        </div>

                        <div v-else class="ia-respuesta" :class="{ 'ia-respuesta-error': c.estado === 'ERROR' }">
                            <p class="ia-autor">
                                <span class="ia-marca ia-marca-xs" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                                Asistente
                            </p>
                            <div class="ia-respuesta-texto" v-html="textoIaAHtml(c.respuesta)"></div>
                            <ResultadoIa v-for="(r, i) in c.resultados" :key="i" :resultado="r" />
                            <p v-if="c.modelo" class="ia-respuesta-meta">{{ c.proveedor }} · {{ c.modelo }}</p>
                        </div>
                    </article>
                </template>
            </div>

            <footer class="ia-panel-pie">
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
