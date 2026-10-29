<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import PreguntaAcciones from '@/Components/Reporte/PreguntaAcciones.vue';
import PreguntaAumentoMateriales from '@/Components/Reporte/PreguntaAumentoMateriales.vue';
import { formateador, money, numero, porcentaje } from '@/Utils/FormatoReporte.js';

/**
 * Preguntas para decidir: el BI contestado en castellano, UNA pregunta a la
 * vez. A la izquierda el índice (con buscador); a la derecha la pregunta
 * elegida: respuesta breve, su tabla (o gráfico), el aviso, qué hacer y con
 * qué datos se respondió.
 *
 * Dos preguntas tienen cuerpo propio: la 2 (`PreguntaAumentoMateriales`:
 * indicadores, gráfico y detalle de precios) y la 11 (`PreguntaAcciones`: el
 * resumen de las demás, cada acción con qué representa su monto y un enlace a
 * la pregunta de la que sale, más los cuadros de escenarios y cobros).
 *
 * Todo lo arma `PreguntasNegocioService` (las mismas cifras salen en el PDF y
 * el CSV del informe); acá solo se maqueta. La pregunta abierta viaja en el
 * hash de la URL (#pregunta-costo_producto), así un enlace lleva directo a ella.
 */
defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/**
 * Lo que la pantalla agrega a cada pregunta: su ayuda "i", la etiqueta de qué
 * datos usa, el título de la tabla y qué columna se grafica.
 */
const PRESENTACION = {
    costo_producto: { ayuda: 'bi-preguntas.costo-producto', etiqueta: 'Costo con datos registrados y presupuestados', tituloTabla: 'Comparación por producto', grafico: 'Total' },
    // Con cuerpo propio (PreguntaAumentoMateriales): sin tabla ni gráfico genéricos.
    costos_suben: { ayuda: 'bi-preguntas.costos-suben', etiqueta: null, tituloTabla: null, grafico: null },
    gasto_presupuesto: { ayuda: 'bi-preguntas.gasto-presupuesto', etiqueta: 'Material registrado frente al previsto', tituloTabla: 'Diferencia por producto', grafico: 'Diferencia' },
    materiales_pesan: { ayuda: 'bi-preguntas.materiales-pesan', etiqueta: 'Consumo registrado de material', tituloTabla: 'Gasto por material', grafico: 'Gasto registrado (Bs)' },
    productos_rentables: { ayuda: 'bi-preguntas.productos-rentables', etiqueta: 'Resultado antes de impuestos', tituloTabla: 'Productos con mayor resultado', grafico: 'Resultado bruto (Bs)' },
    clientes_rentables: { ayuda: 'bi-preguntas.clientes-rentables', etiqueta: 'Resultado antes de impuestos', tituloTabla: 'Clientes con mayor resultado', grafico: 'Resultado bruto (Bs)' },
    descuentos: { ayuda: 'bi-preguntas.descuentos', etiqueta: 'Descuentos y precios registrados', tituloTabla: 'Descuentos por vendedor', grafico: 'Descontado (Bs)' },
    margen_bajo: { ayuda: 'bi-preguntas.margen-bajo', etiqueta: 'Semáforo de rentabilidad', tituloTabla: 'Margen bajo por vendedor', grafico: 'Vendido con margen bajo (Bs)' },
    materia_prima: { ayuda: 'bi-preguntas.materia-prima', etiqueta: 'Simulación sobre las ventas del período', tituloTabla: 'Escenarios', grafico: 'Reducción estimada (Bs)' },
    cobranza: { ayuda: 'bi-preguntas.cobranza', etiqueta: 'Pagos registrados', tituloTabla: 'Saldo por antigüedad', grafico: 'Saldo por cobrar (Bs)' },
    decisiones: { ayuda: 'bi-preguntas.decisiones', etiqueta: null, tituloTabla: 'Acciones ordenadas por monto', grafico: null },
};

const numeradas = computed(() => props.datos.preguntas.map((p, i) => ({ ...p, numero: i + 1, ...PRESENTACION[p.clave] })));

/* ----- Índice y pregunta abierta ----------------------------------------- */

const busqueda = ref('');

function normalizar(texto) {
    return (texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const grupos = computed(() => {
    const termino = normalizar(busqueda.value.trim());

    return Object.entries(props.datos.grupos)
        .map(([clave, titulo]) => ({
            clave,
            titulo,
            preguntas: numeradas.value.filter((p) => p.grupo === clave
                && (!termino || normalizar(`${p.corto} ${p.pregunta}`).includes(termino))),
        }))
        .filter((g) => g.preguntas.length > 0);
});

const PREFIJO = '#pregunta-';

function claveDelHash() {
    const clave = window.location.hash.startsWith(PREFIJO) ? window.location.hash.slice(PREFIJO.length) : null;

    return numeradas.value.some((p) => p.clave === clave) ? clave : numeradas.value[0]?.clave;
}

const abierta = ref(claveDelHash());

const pregunta = computed(() => numeradas.value.find((p) => p.clave === abierta.value) ?? numeradas.value[0]);
const siguiente = computed(() => numeradas.value[pregunta.value.numero] ?? null);
const anterior = computed(() => numeradas.value[pregunta.value.numero - 2] ?? null);

const contenido = ref(null);

function abrir(clave) {
    abierta.value = clave;
    // replaceState: cambiar de pregunta no llena el historial del navegador.
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}${PREFIJO}${clave}`);

    if (window.matchMedia('(max-width: 1023px)').matches) {
        contenido.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function alCambiarHash() {
    abierta.value = claveDelHash();
}

onMounted(() => window.addEventListener('hashchange', alCambiarHash));
onBeforeUnmount(() => window.removeEventListener('hashchange', alCambiarHash));

/* ----- Tabla, gráfico y cifras de la pregunta abierta --------------------- */

const vista = ref('tabla');
const verCalculo = ref(false);

watch(abierta, () => {
    vista.value = 'tabla';
    verCalculo.value = false;
});

/** Una columna "… (Bs)" ya dice la moneda: la cifra va sin el "Bs". */
function celda(valor, columna) {
    if (valor === null || valor === undefined) {
        return '—';
    }

    if (columna.formato === 'bs' && columna.titulo.endsWith('(Bs)')) {
        return numero(valor, 0);
    }

    return columna.formato === 'texto' ? valor : formateador(columna.formato)(valor);
}

/** El gráfico usa la columna de PRESENTACION; sin ella, la tabla sola. */
const grafico = computed(() => {
    const p = pregunta.value;
    const indice = p.grafico ? p.tabla.columnas.findIndex((c) => c.titulo === p.grafico) : -1;

    if (indice < 0 || !p.tabla.filas.length) {
        return null;
    }

    const columna = p.tabla.columnas[indice];

    return {
        labels: p.tabla.filas.map((f) => String(f[0])),
        series: [{ nombre: columna.titulo, tipo: 'bar', datos: p.tabla.filas.map((f) => f[indice]), destacar: 0, etiquetas: true }],
        formato: formateador(columna.formato),
        alto: Math.max(200, p.tabla.filas.length * 40),
    };
});

/**
 * Costo por producto: la cuenta del primer producto escrita como suma
 * (material registrado + obra y otros = costo calculado), con las `cifras`
 * de la pregunta. Las demás preguntas no tienen una cuenta así.
 */
const cuentaCosto = computed(() => {
    const p = pregunta.value;

    if (p.clave !== 'costo_producto' || !p.cifras?.producto) {
        return null;
    }

    const { producto, unidad, material, previsto, obra, total } = p.cifras;
    const desvio = previsto ? ((material - previsto) / previsto) * 100 : null;

    return {
        producto,
        unidad,
        material,
        obra,
        total,
        desvio: desvio === null || Math.abs(desvio) < 0.05
            ? null
            : `El material registrado ${desvio > 0 ? 'supera' : 'está'} en ${porcentaje(Math.abs(desvio), 1)} ${desvio > 0 ? 'al' : 'por debajo del'} material presupuestado.`,
    };
});

/**
 * Bajada de la pregunta 11: la tabla es la respuesta; la bajada dice cuántas
 * acciones hay y que cada una enlaza a sus datos (el aviso naranja ya dice
 * que no se suman). La respuesta del servicio, más larga, va al PDF.
 */
const bajadaAcciones = computed(() => {
    const cuantas = pregunta.value.priorizadas?.length ?? 0;

    if (cuantas === 0) {
        return pregunta.value.respuesta;
    }

    return cuantas === 1
        ? '1 acción con monto involucrado. Se vincula con los datos que la originan.'
        : `${cuantas} acciones ordenadas por monto involucrado. Cada una se vincula con los datos que la originan.`;
});

/**
 * La respuesta en dos partes: la primera oración, que va destacada, y el
 * resto. Corta en un punto seguido de mayúscula o número, así "S.R.L. es…"
 * o "Bs 1.450,00" no parten la frase.
 */
const respuesta = computed(() => {
    const texto = pregunta.value.respuesta ?? '';
    const partes = /^(.+?[.!?])\s+(?=[A-ZÁÉÍÓÚÑ¿¡0-9])(.+)$/su.exec(texto);

    return partes ? { titular: partes[1], resto: partes[2] } : { titular: texto, resto: '' };
});

/**
 * Pregunta 11: los cuadros de lectura rápida de "Si sube la materia prima"
 * y "Pagos pendientes", con las `cifras` que esas preguntas exponen. Sin
 * datos en su pregunta, el cuadro no sale.
 */
const escenario = computed(() => {
    const p = numeradas.value.find((q) => q.clave === 'materia_prima');

    return p?.cifras?.margen_hoy != null ? p : null;
});

const cobros = computed(() => {
    const p = numeradas.value.find((q) => q.clave === 'cobranza');

    return p?.cifras?.pedidos ? p : null;
});
</script>

<template>
    <Head title="Preguntas para decidir · Inteligencia de negocios" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <div class="preguntas-vista">
            <!-- ==================== ÍNDICE ==================== -->
            <nav class="card preguntas-menu" aria-label="Índice de preguntas">
                <div class="card-body">
                    <p class="preguntas-menu-titulo">{{ datos.preguntas.length }} preguntas</p>

                    <label class="preguntas-buscar">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <span class="sr-only">Buscar pregunta</span>
                        <input v-model="busqueda" type="search" class="form-control" placeholder="Buscar pregunta..." />
                    </label>

                    <div v-for="g in grupos" :key="g.clave" class="preguntas-menu-grupo">
                        <p class="preguntas-menu-rotulo">{{ g.titulo }}</p>
                        <ol>
                            <li v-for="p in g.preguntas" :key="p.clave">
                                <button type="button" class="preguntas-menu-item" :class="{ 'is-activa': p.clave === pregunta.clave }"
                                    :aria-current="p.clave === pregunta.clave ? 'true' : null" :title="p.pregunta" @click="abrir(p.clave)">
                                    <span class="preguntas-menu-numero">{{ p.numero }}</span>
                                    <span>{{ p.corto }}</span>
                                </button>
                            </li>
                        </ol>
                    </div>

                    <p v-if="!grupos.length" class="preguntas-menu-vacio">Ninguna pregunta coincide con la búsqueda.</p>
                </div>
            </nav>

            <!-- ==================== PREGUNTA ABIERTA ==================== -->
            <article ref="contenido" class="card preguntas-detalle" :aria-labelledby="`titulo-${pregunta.clave}`">
                <div class="card-body">
                    <p class="preguntas-miga">
                        {{ datos.grupos[pregunta.grupo] }} <span aria-hidden="true">/</span>
                        Pregunta {{ pregunta.numero }} de {{ datos.preguntas.length }}
                    </p>

                    <div class="preguntas-cabecera">
                        <div>
                            <h2 :id="`titulo-${pregunta.clave}`" class="preguntas-titulo">
                                {{ pregunta.numero }}. {{ pregunta.pregunta }} <AyudaInfo v-if="pregunta.ayuda" :clave="pregunta.ayuda" />
                            </h2>
                            <!-- Acciones: la respuesta es una bajada del título; la tabla es la respuesta. -->
                            <p v-if="pregunta.clave === 'decisiones'" class="preguntas-bajada">
                                <i class="fa-solid fa-link" aria-hidden="true"></i> {{ bajadaAcciones }}
                            </p>
                        </div>
                        <span v-if="pregunta.etiqueta" class="preguntas-etiqueta">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> {{ pregunta.etiqueta }}
                        </span>
                    </div>

                    <div v-if="pregunta.clave !== 'decisiones'" class="preguntas-respuesta">
                        <span class="preguntas-respuesta-icono"><i class="fa-regular fa-lightbulb" aria-hidden="true"></i></span>
                        <div>
                            <p class="preguntas-respuesta-titulo">Respuesta breve</p>
                            <p class="preguntas-respuesta-texto">
                                <strong>{{ respuesta.titular }}</strong> {{ respuesta.resto }}
                            </p>
                        </div>
                    </div>

                    <template v-if="cuentaCosto">
                        <div class="preguntas-cuenta" :aria-label="`Costo de ${cuentaCosto.producto}`">
                            <div class="preguntas-cuenta-dato">
                                <i class="fa-solid fa-database" aria-hidden="true"></i>
                                <span>
                                    <small>Material registrado</small>
                                    <strong>{{ money(cuentaCosto.material) }}</strong>
                                    <small>por {{ cuentaCosto.unidad }}</small>
                                </span>
                            </div>
                            <span class="preguntas-cuenta-signo" aria-hidden="true">+</span>
                            <div class="preguntas-cuenta-dato">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <span>
                                    <small>Mano de obra y otros presupuestados</small>
                                    <strong>{{ money(cuentaCosto.obra) }}</strong>
                                    <small>por {{ cuentaCosto.unidad }}</small>
                                </span>
                            </div>
                            <span class="preguntas-cuenta-signo" aria-hidden="true">=</span>
                            <div class="preguntas-cuenta-dato is-total">
                                <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                                <span>
                                    <small>Costo calculado por {{ cuentaCosto.unidad }}</small>
                                    <strong>{{ money(cuentaCosto.total) }}</strong>
                                    <small>{{ cuentaCosto.producto }}</small>
                                </span>
                            </div>
                        </div>
                        <p v-if="cuentaCosto.desvio" class="preguntas-desvio">{{ cuentaCosto.desvio }}</p>
                    </template>

                    <p v-if="pregunta.aviso" class="preguntas-aviso">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>{{ pregunta.aviso }}</span>
                    </p>

                    <!-- Preguntas con cuerpo propio: Acciones (11) y Aumento de materiales (2). -->
                    <PreguntaAcciones v-if="pregunta.clave === 'decisiones'" :pregunta="pregunta" :escenario="escenario" :cobros="cobros"
                        class="preguntas-bloque" @abrir="abrir" />

                    <PreguntaAumentoMateriales v-else-if="pregunta.clave === 'costos_suben' && pregunta.tabla.filas.length"
                        :pregunta="pregunta" class="preguntas-bloque" />

                    <section v-else-if="pregunta.tabla.filas.length" class="preguntas-bloque preguntas-tabla-caja">
                        <header class="preguntas-tabla-cabecera">
                            <h3>{{ pregunta.tituloTabla }}</h3>
                            <div v-if="grafico" class="financiero-vista panorama-vista" role="group" aria-label="Ver como">
                                <button type="button" :class="{ 'is-active': vista === 'grafico' }" :aria-pressed="vista === 'grafico'"
                                    @click="vista = 'grafico'"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Gráfico</button>
                                <button type="button" :class="{ 'is-active': vista === 'tabla' }" :aria-pressed="vista === 'tabla'"
                                    @click="vista = 'tabla'"><i class="fa-solid fa-table" aria-hidden="true"></i> Tabla</button>
                            </div>
                        </header>

                        <BaseChart v-if="grafico && vista === 'grafico'" :titulo="pregunta.tituloTabla" tipo="bar" horizontal
                            :labels="grafico.labels" :series="grafico.series" :formato="grafico.formato" :alto="grafico.alto" sin-tabla />

                        <div v-else class="table-responsive">
                            <table class="table-dashboard table-sm pregunta-tabla">
                                <thead>
                                    <tr>
                                        <th v-for="c in pregunta.tabla.columnas" :key="c.titulo" scope="col"
                                            :class="{ 'text-end': c.formato !== 'texto' }">
                                            {{ c.titulo }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(fila, i) in pregunta.tabla.filas" :key="i">
                                        <td v-for="(valor, j) in fila" :key="j"
                                            :class="pregunta.tabla.columnas[j].formato === 'texto' ? { 'fw-semibold': j === 0 } : 'text-end chart-table-num'">
                                            {{ celda(valor, pregunta.tabla.columnas[j]) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <p v-else class="pregunta-vacia preguntas-bloque">Sin datos para esta pregunta en el periodo elegido.</p>

                    <div v-if="pregunta.acciones.length && pregunta.clave !== 'decisiones'" class="preguntas-accion">
                        <span class="preguntas-accion-icono"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></span>
                        <div>
                            <p class="preguntas-accion-titulo">{{ pregunta.acciones.length === 1 ? 'Acción sugerida' : 'Acciones sugeridas' }}</p>
                            <ol>
                                <li v-for="(a, i) in pregunta.acciones" :key="i">
                                    <span class="preguntas-accion-numero" aria-hidden="true">{{ i + 1 }}</span>
                                    <span>{{ a.texto }}</span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <div v-if="pregunta.base || pregunta.interpretacion" class="preguntas-calculo">
                        <button type="button" class="preguntas-calculo-boton" :aria-expanded="verCalculo" @click="verCalculo = !verCalculo">
                            <i class="fa-solid fa-circle-info preguntas-calculo-info" aria-hidden="true"></i>
                            Cómo se calcula y qué datos utiliza
                            <i class="fa-solid fa-chevron-down preguntas-calculo-flecha" :class="{ 'is-abierto': verCalculo }" aria-hidden="true"></i>
                        </button>
                        <div v-show="verCalculo" class="preguntas-calculo-texto">
                            <p v-if="pregunta.interpretacion"><strong>Cómo interpretar el resultado.</strong> {{ pregunta.interpretacion }}</p>
                            <p v-if="pregunta.base"><strong>Datos utilizados.</strong> {{ pregunta.base }}</p>
                        </div>
                    </div>

                    <nav class="preguntas-pie" aria-label="Otras preguntas">
                        <button v-if="anterior" type="button" class="btn btn-soft-secondary" :aria-label="`Anterior: ${anterior.corto}`"
                            @click="abrir(anterior.clave)">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ anterior.corto }}
                        </button>
                        <span v-else></span>
                        <button v-if="siguiente" type="button" class="btn btn-primary" @click="abrir(siguiente.clave)">
                            Siguiente: {{ siguiente.corto }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </nav>
                </div>
            </article>
        </div>
    </div>
</template>
