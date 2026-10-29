<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import BaseChart from '@/Components/Chart/BaseChart.vue';
import AyudaInfo from '@/Components/AyudaInfo.vue';
import BiBloque from '@/Components/Reporte/BiBloque.vue';
import BiEncabezado from '@/Components/Reporte/BiEncabezado.vue';
import BiInterruptor from '@/Components/Reporte/BiInterruptor.vue';
import BiKpi from '@/Components/Reporte/BiKpi.vue';
import ConfiabilidadPronostico from '@/Components/Reporte/ConfiabilidadPronostico.vue';
import MetodoPronostico from '@/Components/Reporte/MetodoPronostico.vue';
import { seriesProyectadas } from '@/Utils/SerieProyectada.js';
import { money, moneyCorto, numero, variacion } from '@/Utils/FormatoReporte.js';

defineOptions({ layout: MainDashboardLayout });

/**
 * Costos y materiales, en tres bloques que se leen de arriba abajo:
 *
 * 1. Cómo cambian los precios: el índice de precios (base 100) y el precio
 *    de un material elegido, con su estimación y qué significa.
 * 2. Cuánto costó el material consumido: el costo registrado por mes, los
 *    materiales de mayor costo frente a lo previsto y qué revisar.
 * 3. Qué efecto tienen las subidas de precio: el costo adicional calculado
 *    por material y su detalle.
 *
 * Los precios son de toda la empresa (el material no tiene sucursal); el
 * consumo se acota a las sucursales que ve el usuario. La franja de arriba
 * lo dice.
 */
const props = defineProps({
    datos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

/** Cuántos se ven en los rankings antes de "Ver los N". */
const PRIMEROS = 5;

const verPromedioConsumo = ref(false);
const verTodosConsumo = ref(false);
const verTodosImpacto = ref(false);

/** Unidad de medida del material (enum de `material.unidad_medida`) legible. */
const UNIDADES = { M2: 'm²', METRO_LINEAL: 'm lineal', UNIDAD: 'u.', LITRO: 'l' };

function unidad(valor) {
    return UNIDADES[valor] ?? valor ?? '';
}

function puntos(value) {
    return numero(value, 1);
}

/**
 * Une una serie histórica con su proyección sobre un mismo eje de tiempo.
 * La proyección y su banda las arma Utils/SerieProyectada.js; el histórico
 * queda vacío en los meses futuros.
 */
function conProyeccion(labels, historico, proyeccion, metodo, nombre, { area = false } = {}) {
    const futuros = props.datos.meses_proyeccion.slice(0, proyeccion.length);
    const vacio = Array(futuros.length).fill(null);

    return {
        labels: [...labels, ...futuros],
        inicio: futuros[0] ?? null,
        vacio,
        series: [
            { nombre, datos: [...historico, ...vacio], area },
            ...seriesProyectadas(historico, proyeccion, metodo, 'Estimación'),
        ],
    };
}

/** El mes en curso quedó fuera de la recta: se marca para que no se lea como una caída. */
function marcaMesIncompleto(metodo) {
    return metodo?.mes_final_excluido ? { etiqueta: metodo.mes_final_excluido, texto: 'Mes incompleto' } : null;
}

/* ----- Indicadores de arriba ---------------------------------------------- */

const indice = computed(() => props.datos.indice);
const consumo = computed(() => props.datos.consumo);
const impacto = computed(() => props.datos.impacto);

const notaHistorial = computed(() => {
    const sinCambio = indice.value.materiales - impacto.value.suben - impacto.value.bajan;

    return `${impacto.value.suben} subieron · ${impacto.value.bajan} bajaron`
        + (sinCambio > 0 ? ` · ${sinCambio} sin cambio` : '');
});

/* ----- 1. Cómo cambian los precios -------------------------------------------- */

const indiceGrafico = computed(() => conProyeccion(
    indice.value.labels,
    indice.value.serie,
    indice.value.proyeccion,
    indice.value.metodo,
    'Índice registrado',
));

/** "Un índice de 113,6 representa un aumento de 13,6 %": el número del gráfico, en palabras. */
const lecturaIndice = computed(() => {
    const ultimo = [...indice.value.serie].reverse().find((v) => v !== null && v !== undefined);
    const cambio = indice.value.variacion_periodo;

    if (ultimo === undefined || cambio === null) {
        return 'Base 100 = nivel de precios al inicio del periodo.';
    }

    const sentido = cambio >= 0 ? 'un aumento' : 'una baja';

    return `Base 100 = nivel de precios al inicio del periodo. Un índice de ${numero(ultimo, 1)} representa`
        + ` ${sentido} de ${numero(Math.abs(cambio), 1)} %, no un precio en bolivianos.`;
});

const materialElegido = ref(props.datos.materiales[0]?.id ?? null);

const material = computed(() => props.datos.materiales.find((m) => m.id === materialElegido.value) ?? null);

const materialGrafico = computed(() => (material.value
    ? conProyeccion(indice.value.labels, material.value.serie, material.value.proyeccion, material.value.metodo, 'Precio unitario')
    : { labels: [], series: [], inicio: null }));

/** El último mes estimado, con su rango. */
const estimacionMaterial = computed(() => {
    const ultimo = material.value?.proyeccion.at(-1);

    return ultimo ? { ...ultimo, mes: props.datos.meses_proyeccion[material.value.proyeccion.length - 1] } : null;
});

function porUnidad(valor) {
    const u = unidad(material.value?.unidad);

    return valor === null || valor === undefined ? '—' : `${money(valor)}${u ? ` / ${u}` : ''}`;
}

/* ----- 2. Cuánto costó el material consumido ---------------------------------- */

/** El promedio de 3 meses va al final: prenderlo no les cambia el color a las demás series. */
const consumoGrafico = computed(() => {
    const base = conProyeccion(consumo.value.labels, consumo.value.serie, consumo.value.proyeccion,
        consumo.value.metodo, 'Costo consumido', { area: true });

    if (verPromedioConsumo.value) {
        base.series.push({ nombre: 'Promedio últimos 3 meses', datos: [...consumo.value.media_movil, ...base.vacio] });
    }

    return base;
});

/**
 * Registrado contra presupuestado, por material. Solo se agrega la serie
 * presupuestada cuando existe: sin hojas de costo en el periodo sería una
 * columna de ceros que se lee como "presupuestamos cero".
 */
const consumoPorMaterial = computed(() => {
    const filas = verTodosConsumo.value ? consumo.value.por_material : consumo.value.por_material.slice(0, PRIMEROS);

    return {
        labels: filas.map((f) => f.material),
        series: [
            { nombre: 'Registrado', datos: filas.map((f) => f.costo_real) },
            ...(consumo.value.tiene_presupuestado
                ? [{ nombre: 'Presupuestado', datos: filas.map((f) => f.costo_presupuestado) }]
                : []),
        ],
        alto: Math.max(filas.length * 48 + 40, 180),
    };
});

/** La diferencia más grande, en %, entre lo registrado y lo previsto en las hojas de costos. */
const mayorDiferencia = computed(() => consumo.value.por_material
    .filter((f) => f.costo_real > 0 && f.costo_presupuestado > 0)
    .map((f) => ({ material: f.material, pct: (f.costo_real - f.costo_presupuestado) / f.costo_presupuestado * 100 }))
    .sort((a, b) => Math.abs(b.pct) - Math.abs(a.pct))[0] ?? null);

/** Qué revisar en el consumo: pasos fijos, con las cifras del periodo donde las hay. */
const queRevisar = computed(() => {
    const n = consumo.value.materiales_distintos;
    const pasos = [{
        titulo: 'Completar el registro de consumos.',
        detalle: `Verificar que todos los materiales usados en los pedidos estén registrados: en el periodo hay ${n} ${n === 1 ? 'material' : 'materiales'} con consumo.`,
    }];

    if (consumo.value.tiene_presupuestado) {
        pasos.push({
            titulo: 'Comparar lo registrado con lo previsto en las hojas de costos.',
            detalle: mayorDiferencia.value
                ? `La mayor diferencia está en ${mayorDiferencia.value.material}: ${variacion(mayorDiferencia.value.pct)} frente a lo previsto.`
                : 'Identificar las diferencias relevantes y sus causas.',
        });
    }

    pasos.push({
        titulo: 'Revisar las diferencias antes de atribuirlas a desperdicio o sobreconsumo.',
        detalle: 'Considerar cambios de diseño, mermas y ajustes de producción.',
    });

    return pasos;
});

/* ----- 3. Qué efecto tienen las subidas de precio ------------------------------ */

/** Costo adicional en bolivianos de los materiales que más pesaron (ya vienen ordenados). */
const costoAdicional = computed(() => {
    const filas = impacto.value.materiales.filter((f) => f.impacto !== 0).slice(0, PRIMEROS);

    return {
        labels: filas.map((f) => f.material),
        series: [{ nombre: 'Costo adicional', datos: filas.map((f) => f.impacto) }],
        alto: Math.max(filas.length * 44 + 40, 180),
    };
});

const filasImpacto = computed(() => (verTodosImpacto.value
    ? impacto.value.materiales
    : impacto.value.materiales.slice(0, PRIMEROS)));

/** El material con mayor costo adicional: por donde empezar. */
const prioridad = computed(() => impacto.value.materiales
    .filter((f) => f.impacto > 0)
    .sort((a, b) => b.impacto - a.impacto)[0] ?? null);
</script>

<template>
    <Head title="Costos y materiales" />

    <div class="page-stack">
        <BiEncabezado :periodo="datos.periodo" :filters="filters" />

        <p class="bi-aviso">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>
                Los precios salen del historial de compras de toda la empresa; el consumo, del material registrado en los
                pedidos de las sucursales que puede ver.
            </span>
        </p>

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Aumento del índice de precios" ayuda="bi-costos.kpi-indice" icono="fa-solid fa-arrow-trend-up"
                    tono="warning" :valor="indice.variacion_periodo === null ? '—' : variacion(indice.variacion_periodo)"
                    :variacion="indice.variacion_mensual" :subir-es-bueno="false" nota="Frente al inicio del periodo" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Costo del material consumido" ayuda="bi-costos.kpi-consumido" icono="fa-solid fa-boxes-stacked"
                    tono="primary" :valor="money(consumo.total)"
                    :nota="`${consumo.materiales_distintos} ${consumo.materiales_distintos === 1 ? 'material' : 'materiales'} con consumo registrado`" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi :etiqueta="impacto.total < 0 ? 'Ahorro por variación de precios' : 'Costo adicional por variación de precios'"
                    ayuda="bi-costos.kpi-impacto" icono="fa-solid fa-chart-column" :tono="impacto.total > 0 ? 'danger' : 'success'"
                    :valor="money(Math.abs(impacto.total))" nota="Calculado con las cantidades consumidas" />
            </div>
            <div class="col-lg-3 col-md-6">
                <BiKpi etiqueta="Materiales con historial de precios" ayuda="bi-costos.kpi-historial" icono="fa-solid fa-database"
                    tono="info" :valor="String(indice.materiales)" :nota="notaHistorial" />
            </div>
        </div>

        <!-- ==================== 1. CÓMO CAMBIAN LOS PRECIOS ==================== -->
        <BiBloque :numero="1" titulo="Cómo cambian los precios"
            subtitulo="El precio de los materiales a lo largo del periodo y hacia dónde va.">
            <div class="card">
                <div class="card-header bi-cabecera-con-aviso">
                    <div>
                        <span class="card-title">Evolución y estimación del índice de precios <AyudaInfo clave="bi-costos.indice" /></span>
                        <p class="card-subtitle">
                            {{ lecturaIndice }}
                            <span v-if="!indice.proyeccion.length">Se necesitan al menos 3 meses con precios para estimar.</span>
                        </p>
                    </div>
                    <ConfiabilidadPronostico :metodo="indice.metodo" />
                </div>
                <div class="card-body">
                    <BaseChart titulo="Índice de precios de materiales" :labels="indiceGrafico.labels" :series="indiceGrafico.series"
                        :formato="puntos" :alto="300" :inicio-proyeccion="indiceGrafico.inicio" unir-huecos ajustar-escala
                        :umbrales="[{ valor: 100, nombre: 'Base 100' }]"
                        vacio="Se necesita más de un registro de precio por material (se genera al aprobar compras)." />
                    <MetodoPronostico :metodo="indice.metodo" />
                </div>
            </div>

            <div class="bi-costos-material">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Precio del material seleccionado <AyudaInfo clave="bi-costos.material" /></span>
                            <p class="card-subtitle">
                                Precio vigente al cierre de cada mes<template v-if="material && unidad(material.unidad)"> · Bs por {{ unidad(material.unidad) }}</template>
                            </p>
                        </div>
                        <select v-if="datos.materiales.length" v-model="materialElegido" class="form-control bi-select"
                            aria-label="Material a analizar">
                            <option v-for="m in datos.materiales" :key="m.id" :value="m.id">{{ m.material }}</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Precio del material seleccionado" :labels="materialGrafico.labels"
                            :series="materialGrafico.series" :formato="moneyCorto" :formato-tooltip="money" :alto="260"
                            unir-huecos ajustar-escala :inicio-proyeccion="materialGrafico.inicio"
                            vacio="Ningún material tiene historial de precios suficiente." />
                        <MetodoPronostico :metodo="material?.metodo" />
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Qué significa para este material <AyudaInfo clave="bi-costos.lectura" /></span>
                    </div>
                    <div class="card-body bi-costos-lectura">
                        <template v-if="material">
                            <div class="margen-panel">
                                <div class="margen-row">
                                    <span>Precio actual</span>
                                    <strong>{{ porUnidad(material.precio_actual) }}</strong>
                                </div>
                                <div class="margen-row">
                                    <span>Tendencia calculada</span>
                                    <strong :class="material.variacion_mensual > 0 ? 'text-danger' : 'text-success'">
                                        {{ material.variacion_mensual === null ? '—' : `${variacion(material.variacion_mensual)} mensual` }}
                                    </strong>
                                </div>
                                <div class="margen-row margen-row-destacada">
                                    <span>Estimación para {{ estimacionMaterial?.mes ?? 'los próximos meses' }}</span>
                                    <strong>{{ porUnidad(estimacionMaterial?.valor) }}</strong>
                                </div>
                                <div v-if="estimacionMaterial" class="margen-row">
                                    <span>Rango probable</span>
                                    <strong>{{ money(estimacionMaterial.inferior) }} – {{ money(estimacionMaterial.superior) }}</strong>
                                </div>
                            </div>
                            <p class="bi-nota">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                <span>El precio estimado es una referencia, no una cotización del proveedor.</span>
                            </p>
                            <p v-if="material.variacion_mensual > 0" class="bi-sugerencia">
                                <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                                <span>
                                    Comparar proveedores y revisar la hoja de costos antes de cotizar nuevos trabajos con
                                    {{ material.material }}.
                                </span>
                            </p>
                        </template>
                        <p v-else class="chart-empty">Sin materiales para analizar.</p>
                    </div>
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 2. CUÁNTO COSTÓ EL MATERIAL CONSUMIDO ==================== -->
        <BiBloque :numero="2" titulo="Cuánto costó el material consumido"
            subtitulo="El costo del material que producción registró en los pedidos y cómo se compara con lo previsto.">
            <template #accion>
                <BiInterruptor v-model="verPromedioConsumo" texto="Mostrar promedio de 3 meses" />
            </template>

            <div class="card">
                <div class="card-header bi-cabecera-con-aviso">
                    <div>
                        <span class="card-title">Costo del consumo registrado por mes <AyudaInfo clave="bi-costos.consumo" /></span>
                        <p class="card-subtitle">Importe en bolivianos; no representa unidades físicas</p>
                    </div>
                    <ConfiabilidadPronostico :metodo="consumo.metodo" />
                </div>
                <div class="card-body">
                    <BaseChart titulo="Costo del consumo registrado por mes" :labels="consumoGrafico.labels"
                        :series="consumoGrafico.series" :formato="moneyCorto" :formato-tooltip="money" :alto="280"
                        :inicio-proyeccion="consumoGrafico.inicio" :marca-eje="marcaMesIncompleto(consumo.metodo)"
                        vacio="Producción no registró consumo de materiales en el periodo." />
                    <MetodoPronostico :metodo="consumo.metodo" />
                </div>
            </div>

            <div class="bi-costos-consumo">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Materiales con mayor costo registrado <AyudaInfo clave="bi-costos.mas-consumidos" /></span>
                            <p class="card-subtitle">
                                <template v-if="consumo.tiene_presupuestado">
                                    Comparación en bolivianos. Verifique que ambos grupos incluyan los mismos trabajos.
                                </template>
                                <template v-else>Costo en bolivianos del periodo</template>
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Materiales con mayor costo registrado" tipo="bar" horizontal
                            :labels="consumoPorMaterial.labels" :series="consumoPorMaterial.series" :formato="moneyCorto"
                            :formato-tooltip="money" :alto="consumoPorMaterial.alto" vacio="Sin consumo registrado en el periodo." />
                        <button v-if="consumo.por_material.length > 5" type="button" class="bi-ver-mas"
                            :aria-expanded="verTodosConsumo" @click="verTodosConsumo = !verTodosConsumo">
                            <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                            {{ verTodosConsumo ? 'Ver solo los 5 primeros' : `Ver los ${consumo.por_material.length} materiales` }}
                            <i :class="verTodosConsumo ? 'fa-solid fa-chevron-up' : 'fa-solid fa-arrow-right'" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                            Qué revisar <AyudaInfo clave="bi-costos.que-revisar" />
                        </span>
                    </div>
                    <ol class="card-body resumen-acciones">
                        <li v-for="(paso, i) in queRevisar" :key="paso.titulo" class="resumen-accion-item">
                            <span class="resumen-accion-numero" aria-hidden="true">{{ i + 1 }}</span>
                            <span class="resumen-accion-cuerpo">
                                <strong class="resumen-accion">{{ paso.titulo }}</strong>
                                <span class="resumen-motivo">{{ paso.detalle }}</span>
                            </span>
                        </li>
                    </ol>
                </div>
            </div>
        </BiBloque>

        <!-- ==================== 3. QUÉ EFECTO TIENEN LAS SUBIDAS DE PRECIO ==================== -->
        <BiBloque :numero="3" titulo="Qué efecto tienen las subidas de precio"
            subtitulo="Cuánto costo adicional generaron los aumentos, según las cantidades consumidas.">
            <div class="bi-costos-impacto">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Materiales con mayor costo adicional calculado <AyudaInfo clave="bi-costos.variacion" /></span>
                            <p class="card-subtitle">Los {{ costoAdicional.labels.length }} de mayor efecto, en bolivianos</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <BaseChart titulo="Materiales con mayor costo adicional calculado" tipo="bar" horizontal
                            :labels="costoAdicional.labels" :series="costoAdicional.series" :formato="moneyCorto"
                            :formato-tooltip="money" :alto="costoAdicional.alto"
                            vacio="Ningún material consumido cambió de precio en el periodo." />
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <span class="card-title">Detalle del impacto en costos <AyudaInfo clave="bi-costos.detalle-impacto" /></span>
                            <p class="card-subtitle">Costo adicional = (precio final − precio inicial) × cantidad consumida</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div v-if="impacto.materiales.length" class="table-responsive">
                            <table class="table-dashboard table-sm bi-costos-tabla">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end bi-col-opcional">Precio inicial</th>
                                        <th class="text-end bi-col-opcional">Precio final</th>
                                        <th class="text-end">Aumento</th>
                                        <th class="text-end bi-col-opcional">Cantidad consumida</th>
                                        <th class="text-end">Costo adicional</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="f in filasImpacto" :key="f.material">
                                        <td>{{ f.material }}</td>
                                        <td class="text-end chart-table-num bi-col-opcional">{{ money(f.precio_inicio) }}</td>
                                        <td class="text-end chart-table-num bi-col-opcional">{{ money(f.precio_fin) }}</td>
                                        <td class="text-end chart-table-num fw-semibold"
                                            :class="f.variacion_pct > 0 ? 'text-danger' : 'text-success'">
                                            {{ variacion(f.variacion_pct) }}
                                        </td>
                                        <td class="text-end chart-table-num bi-col-opcional">{{ numero(f.cantidad_consumida, 2) }} {{ unidad(f.unidad) }}</td>
                                        <td class="text-end chart-table-num fw-semibold"
                                            :class="f.impacto > 0 ? 'text-danger' : f.impacto < 0 ? 'text-success' : ''">
                                            {{ money(f.impacto) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="chart-empty">Ningún material cambió de precio en el periodo.</p>

                        <div v-if="impacto.materiales.length" class="bi-costos-tabla-pie">
                            <button v-if="impacto.materiales.length > 5" type="button" class="btn btn-sm btn-soft-primary"
                                :aria-expanded="verTodosImpacto" @click="verTodosImpacto = !verTodosImpacto">
                                {{ verTodosImpacto ? 'Ver solo los 5 primeros' : `Ver los ${impacto.materiales.length} materiales` }}
                                <i :class="verTodosImpacto ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-right'" aria-hidden="true"></i>
                            </button>
                            <p class="bi-nota mt-0">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                <span>
                                    El total ({{ money(impacto.total) }}) corresponde a los {{ impacto.materiales.length }} materiales.
                                    Es una estimación que supone todo el consumo al precio final: no es una pérdida neta ni debe
                                    sumarse al costo consumido como un gasto aparte.
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="prioridad" class="bi-sugerencia bi-sugerencia-franja">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>
                    Priorizar la revisión de <strong>{{ prioridad.material }}</strong> por su mayor costo adicional calculado
                    ({{ money(prioridad.impacto) }}, con un aumento de {{ variacion(prioridad.variacion_pct) }}).
                </span>
            </p>
        </BiBloque>
    </div>
</template>
