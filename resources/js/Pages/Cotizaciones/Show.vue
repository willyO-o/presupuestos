<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { confirmation } from '@/Utils/AlertUtil';
import { CLASES_SEMAFORO, textoRecomendacion } from '@/Utils/MotorMargen';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    cotizacion: { type: Object, required: true },
    // Desglose de rentabilidad por línea (interno, no se imprime).
    margen: { type: Array, default: () => [] },
    config: { type: Object, default: () => ({ impuestos: {}, semaforo: {} }) },
});

/**
 * Detalle de una cotización, en cuatro partes:
 * 1. resumen (cliente, total cotizado, estado);
 * 2. acciones (PDF, aprobar, convertir en pedido…);
 * 3. el documento que ve el cliente (lo único que se imprime);
 * 4. el análisis de rentabilidad, SOLO interno (`.margen-interno` lo saca de
 *    la impresión): costo, resultado calculado y recomendación del semáforo.
 *
 * Los montos llegan calculados del servidor (CotizacionController::
 * calcularMontos y margenPorLinea): acá solo se muestran.
 */

/** Rentabilidad de una línea, por id de detalle. */
function margenDe(detalleId) {
    return props.margen.find((m) => m.detalle_id === detalleId) ?? null;
}

const puedeVerMargen = computed(() => props.cotizacion.costo_ajustado > 0 || props.margen.length > 0);

function numero(valor, decimales = 2) {
    return Number(valor ?? 0).toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
}

function money(value) {
    return `Bs ${numero(value)}`;
}

function pct(fraccion) {
    return `${numero(Number(fraccion || 0) * 100, 1)} %`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}

function nombreEmpleado(e) {
    if (!e) return '—';
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

/** "3,00 × 1,58 m · 4,74 m²" (medidas de una unidad). */
function medidas(d) {
    return d.ancho && d.alto ? `${numero(d.ancho)} × ${numero(d.alto)} m · ${numero(d.area_m2)} m²` : '—';
}

/* ----- Resumen ------------------------------------------------------------- */

/** Qué significa cada estado y qué se puede hacer con la cotización. */
const ESTADOS = {
    PENDIENTE: { texto: 'Pendiente de aprobación', detalle: 'Puede editarse, aprobarse o rechazarse.', icono: 'fa-solid fa-hourglass-half', tono: 'warning', badge: 'badge-soft-warning' },
    APROBADA: { texto: 'Aprobada', detalle: 'Lista para convertir en pedido.', icono: 'fa-solid fa-thumbs-up', tono: 'success', badge: 'badge-soft-success' },
    RECHAZADA: { texto: 'Rechazada', detalle: 'No se convertirá en pedido.', icono: 'fa-solid fa-xmark', tono: 'danger', badge: 'badge-soft-danger' },
    CONVERTIDA: { texto: 'Convertida en pedido', detalle: 'Consulte el pedido vinculado.', icono: 'fa-solid fa-check', tono: 'success', badge: 'badge-soft-success' },
    VENCIDA: { texto: 'Vencida', detalle: 'Pasó la fecha de validez.', icono: 'fa-regular fa-calendar-xmark', tono: 'info', badge: 'badge-soft-secondary' },
};

const estado = computed(() => ESTADOS[props.cotizacion.estado]
    ?? { texto: props.cotizacion.estado, detalle: '', icono: 'fa-solid fa-circle-info', tono: 'info', badge: 'badge-soft-secondary' });

/** "Incluye IVA", "Incluye IVA e instalación" o "Sin IVA". */
const incluyeTotal = computed(() => {
    const partes = [];

    if (Number(props.cotizacion.iva) > 0) partes.push('IVA');
    if (Number(props.cotizacion.instalacion) > 0) partes.push('instalación');

    return partes.length ? `Incluye ${partes.join(' e ')}` : 'Sin IVA';
});

/* ----- Acciones ------------------------------------------------------------ */

const esPendiente = computed(() => props.cotizacion.estado === 'PENDIENTE');
const esConvertible = computed(() => props.cotizacion.estado === 'APROBADA' && !props.cotizacion.pedido);

const convertirForm = useForm({ cotizacion_id: props.cotizacion.id });

async function convertirEnPedido() {
    const ok = await confirmation(
        `¿Generar un pedido / orden de trabajo desde la cotización <strong>${props.cotizacion.codigo_verificacion}</strong>?`,
        'Convertir en pedido',
        'Sí, generar',
    );
    if (!ok) return;
    convertirForm.post(route('pedidos.store'), { preserveScroll: true });
}

function imprimir() {
    window.print();
}

const accionForm = useForm({});

async function cambiarEstado(accion) {
    const textos = {
        aprobar: ['¿Aprobar esta cotización?', 'Aprobar cotización', 'Sí, aprobar'],
        rechazar: ['¿Rechazar esta cotización?', 'Rechazar cotización', 'Sí, rechazar'],
    };
    const [msg, titulo, boton] = textos[accion];
    if (!(await confirmation(msg, titulo, boton))) return;

    accionForm.post(route(`cotizaciones.${accion}`, props.cotizacion.id), { preserveScroll: true });
}

async function eliminar() {
    const ok = await confirmation(
        `¿Eliminar la cotización <strong>${props.cotizacion.codigo_verificacion}</strong>? Esta acción no se puede deshacer.`,
        'Eliminar cotización',
    );
    if (!ok) return;

    router.delete(route('cotizaciones.destroy', props.cotizacion.id));
}

/* ----- Documento ----------------------------------------------------------- */

const conImagenes = computed(() => props.cotizacion.detalles.some((d) => d.imagen_url));
const conMedidas = computed(() => props.cotizacion.detalles.some((d) => d.ancho && d.alto));

/* ----- Análisis de rentabilidad (interno) ---------------------------------- */

/** El ícono de la rentabilidad toma el color de la recomendación. */
const TONO_SEMAFORO = { VERDE: 'success', AMARILLO: 'warning', ROJO: 'danger' };

/** Precio sin IVA sobre el que se calcula el resultado: subtotal − descuento (como calcularMontos). */
const precioSinIva = computed(() => Number(props.cotizacion.subtotal) - Number(props.cotizacion.descuento || 0));

/**
 * Cada producto se evalúa a su precio de lista (margenPorLinea), antes del
 * descuento; el total sí lo resta. Con descuento pueden recomendar cosas
 * distintas, y la pantalla lo dice.
 */
const tieneDescuento = computed(() => Number(props.cotizacion.descuento) > 0);

const rentabilidad = computed(() => (Number(props.cotizacion.costo_ajustado) > 0
    ? Number(props.cotizacion.utilidad_real) / Number(props.cotizacion.costo_ajustado)
    : null));

const ajusteComplejidad = computed(() => {
    const diferencia = Number(props.cotizacion.costo_ajustado) - Number(props.cotizacion.costo_base);

    return Math.abs(diferencia) < 0.005 ? 'sin cambio' : `${diferencia > 0 ? '+' : '−'}${money(Math.abs(diferencia))}`;
});

/** Niveles (tipos de proyecto) usados en las líneas, sin repetir. */
const niveles = computed(() => {
    const vistos = new Map();

    props.cotizacion.detalles.forEach((d) => {
        if (d.tipo_proyecto && !vistos.has(d.tipo_proyecto.id)) {
            vistos.set(d.tipo_proyecto.id, d);
        }
    });

    return [...vistos.values()];
});

const lineasConInsumos = computed(() => props.cotizacion.detalles.filter((d) => d.items?.length));
const totalInsumos = computed(() => lineasConInsumos.value.reduce((total, d) => total + d.items.length, 0));
</script>

<template>
    <Head :title="`Cotización ${cotizacion.codigo_verificacion}`" />

    <div class="page-stack">
        <!-- 1. Resumen (no se imprime) -->
        <div class="cotizacion-resumen">
            <div class="card stat-card">
                <div class="stat-icon stat-icon-primary"><i class="fa-solid fa-users"></i></div>
                <div class="cotizacion-resumen-cuerpo">
                    <p class="stat-label mb-0">Cliente</p>
                    <p class="stat-value cotizacion-resumen-cliente mb-0">{{ cotizacion.cliente?.razon_social ?? '—' }}</p>
                    <p class="cotizacion-resumen-nota">
                        NIT: {{ cotizacion.cliente?.nit ?? '—' }}
                        <template v-if="cotizacion.cliente?.telefono"> · Tel: {{ cotizacion.cliente.telefono }}</template>
                    </p>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon stat-icon-info"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="cotizacion-resumen-cuerpo">
                    <p class="stat-label mb-0">Total cotizado</p>
                    <p class="stat-value mb-0">{{ money(cotizacion.total) }}</p>
                    <p class="cotizacion-resumen-nota">{{ incluyeTotal }} · válida hasta {{ fecha(cotizacion.fecha_vencimiento) }}</p>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon" :class="`stat-icon-${estado.tono}`"><i :class="estado.icono"></i></div>
                <div class="cotizacion-resumen-cuerpo">
                    <p class="stat-label mb-0">Estado</p>
                    <span class="badge cotizacion-resumen-estado" :class="estado.badge">{{ estado.texto }}</span>
                    <p class="cotizacion-resumen-nota">
                        <Link v-if="cotizacion.pedido" :href="route('pedidos.show', cotizacion.pedido.id)" class="text-primary">
                            Ver el pedido {{ cotizacion.pedido.numero_pedido }}
                        </Link>
                        <template v-else>{{ estado.detalle }}</template>
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Acciones (no se imprime) -->
        <div class="card cotizacion-actions">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    Volver a cotizaciones
                </Link>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-pdf btn-sm" @click="imprimir">
                        <i class="fa-solid fa-print"></i>
                        Imprimir
                    </button>
                    <!-- PDF con membrete, generado por App\Services\Pdf\GeneradorPdf.
                         Enlaces normales y no Link de Inertia: la respuesta es un
                         archivo, no una visita. "Ver PDF" abre la vista previa
                         (inline) en otra pestaña para revisarla antes de enviarla. -->
                    <a :href="route('cotizaciones.pdf', { cotizacion: cotizacion.id, descargar: 1 })"
                        class="btn btn-pdf btn-sm">
                        <i class="fa-solid fa-download"></i>
                        Descargar PDF
                    </a>
                    <a :href="route('cotizaciones.pdf', cotizacion.id)" target="_blank" rel="noopener"
                        class="btn btn-pdf btn-sm">
                        <i class="fa-solid fa-file-pdf"></i>
                        Ver PDF
                    </a>
                    <Link v-if="esPendiente" v-can="'cotizaciones.editar'" :href="route('cotizaciones.edit', cotizacion.id)"
                        class="btn btn-soft-primary btn-sm">
                        <i class="fa-solid fa-pen"></i>
                        Editar
                    </Link>
                    <button v-if="esPendiente" v-can="'cotizaciones.aprobar'" type="button" class="btn btn-success btn-sm"
                        @click="cambiarEstado('aprobar')">
                        <i class="fa-solid fa-check"></i>
                        Aprobar
                    </button>
                    <button v-if="esPendiente" v-can="'cotizaciones.aprobar'" type="button" class="btn btn-soft-warning btn-sm"
                        @click="cambiarEstado('rechazar')">
                        <i class="fa-solid fa-xmark"></i>
                        Rechazar
                    </button>
                    <button v-if="esConvertible" v-can="'pedidos.crear'" type="button" class="btn btn-primary btn-sm"
                        @click="convertirEnPedido">
                        <i class="fa-solid fa-dolly"></i>
                        Convertir en pedido
                    </button>
                    <Link v-if="cotizacion.pedido" :href="route('pedidos.show', cotizacion.pedido.id)"
                        class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-cart-shopping"></i>
                        Ver pedido {{ cotizacion.pedido.numero_pedido }}
                    </Link>
                    <button v-if="cotizacion.estado !== 'CONVERTIDA'" v-can="'cotizaciones.eliminar'" type="button"
                        class="btn btn-soft-danger btn-sm" @click="eliminar">
                        <i class="fa-solid fa-trash"></i>
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Documento: lo que ve el cliente (es lo único que se imprime) -->
        <section class="card cotizacion-doc" aria-labelledby="cotizacion-doc-titulo">
            <div class="card-body">
                <h2 id="cotizacion-doc-titulo" class="cotizacion-doc-titulo">Detalle de la cotización</h2>

                <div class="cotizacion-doc-head">
                    <div>
                        <p class="cotizacion-doc-marca">XtraPubli</p>
                        <p class="fs-sm text-muted mb-0">Publicidad Industrial · El Alto, Bolivia</p>
                    </div>
                    <div class="cotizacion-doc-numero">
                        <p class="cotizacion-doc-tipo">Cotización</p>
                        <p class="fs-sm mb-0"><span class="text-muted">N.º </span>{{ cotizacion.codigo_verificacion }}</p>
                    </div>
                </div>

                <div class="cotizacion-doc-meta">
                    <div>
                        <p class="cotizacion-doc-rotulo">Cliente</p>
                        <p class="fw-semibold mb-0">{{ cotizacion.cliente?.razon_social ?? '—' }}</p>
                        <p class="fs-sm text-muted mb-0">NIT: {{ cotizacion.cliente?.nit ?? '—' }}</p>
                        <p v-if="cotizacion.cliente?.telefono" class="fs-sm text-muted mb-0">
                            Tel: {{ cotizacion.cliente.telefono }}
                        </p>
                    </div>
                    <div class="cotizacion-doc-meta-detalles">
                        <p class="cotizacion-doc-rotulo">Detalles de la cotización</p>
                        <p class="fs-sm mb-0"><span class="text-muted">Fecha de emisión:</span> {{ fecha(cotizacion.fecha) }}</p>
                        <p class="fs-sm mb-0">
                            <span class="text-muted">Válida hasta:</span> {{ fecha(cotizacion.fecha_vencimiento) }}
                        </p>
                        <p class="fs-sm mb-0">
                            <span class="text-muted">Sucursal:</span> {{ cotizacion.sucursal?.nombre ?? '—' }}
                        </p>
                        <p class="fs-sm mb-0">
                            <span class="text-muted">Vendedor:</span> {{ nombreEmpleado(cotizacion.empleado) }}
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-dashboard table-sm cotizacion-doc-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th v-if="conImagenes" scope="col"><span class="sr-only">Imagen</span></th>
                                <th scope="col">Producto</th>
                                <th scope="col" class="text-center">Medidas por unidad</th>
                                <th scope="col" class="text-center">Cantidad</th>
                                <th scope="col" class="text-end">Precio unitario</th>
                                <th scope="col" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(d, i) in cotizacion.detalles" :key="d.id">
                                <td>{{ i + 1 }}</td>
                                <td v-if="conImagenes">
                                    <a v-if="d.imagen_url" :href="d.imagen_url" target="_blank" rel="noopener">
                                        <img :src="d.imagen_url" :alt="`Imagen de referencia: ${d.descripcion}`" class="article-thumb" />
                                    </a>
                                </td>
                                <td>
                                    {{ d.descripcion }}
                                    <span v-if="d.producto" class="d-block fs-xs text-muted">{{ d.producto.nombre }}</span>
                                </td>
                                <td class="text-center">{{ medidas(d) }}</td>
                                <td class="text-center">{{ Number(d.cantidad) }}</td>
                                <td class="text-end">{{ money(d.precio_unitario) }}</td>
                                <td class="text-end fw-semibold">{{ money(d.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="conMedidas" class="fs-xs text-muted mt-2 mb-0">Las medidas corresponden a cada unidad.</p>

                <div class="cotizacion-doc-totals">
                    <div class="cotizacion-total-row"><span>Subtotal</span><span>{{ money(cotizacion.subtotal) }}</span></div>
                    <div v-if="Number(cotizacion.descuento) > 0" class="cotizacion-total-row">
                        <span>Descuento</span><span>− {{ money(cotizacion.descuento) }}</span>
                    </div>
                    <div v-if="Number(cotizacion.iva) > 0" class="cotizacion-total-row">
                        <span>IVA</span><span>{{ money(cotizacion.iva) }}</span>
                    </div>
                    <div v-if="Number(cotizacion.instalacion) > 0" class="cotizacion-total-row">
                        <span>Instalación</span><span>{{ money(cotizacion.instalacion) }}</span>
                    </div>
                    <div class="cotizacion-total-row cotizacion-total-grand">
                        <span>Total</span><span>{{ money(cotizacion.total) }}</span>
                    </div>
                </div>

                <div v-if="cotizacion.observaciones" class="cotizacion-doc-notes">
                    <p class="cotizacion-doc-rotulo">Observaciones</p>
                    <p class="fs-sm mb-0 text-prewrap">{{ cotizacion.observaciones }}</p>
                </div>

                <p class="fs-xs text-muted mt-4 mb-0">
                    Documento generado por el sistema de costos y presupuestos XtraPubli.
                    Código de verificación: <strong>{{ cotizacion.codigo_verificacion }}</strong>.
                </p>
            </div>
        </section>

        <!-- 4. Análisis de rentabilidad: INTERNO, .margen-interno lo saca de la impresión -->
        <section v-if="puedeVerMargen" class="card margen-interno" aria-labelledby="cotizacion-analisis-titulo">
            <div class="card-header cotizacion-analisis-cabecera">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h2 id="cotizacion-analisis-titulo" class="card-title mb-0">Análisis de rentabilidad</h2>
                        <span class="badge badge-soft-primary">Solo uso interno</span>
                    </div>
                    <p class="card-subtitle mb-0">
                        Costos y resultado calculados por el sistema. Revise la hoja de costos para conocer su composición.
                    </p>
                </div>
                <span v-if="cotizacion.estado_margen" :class="CLASES_SEMAFORO[cotizacion.estado_margen]">
                    <span class="semaforo-punto"></span>
                    {{ textoRecomendacion(cotizacion.estado_margen) }} según el sistema
                </span>
            </div>

            <div class="card-body cotizacion-analisis">
                <div class="cotizacion-analisis-kpis">
                    <div class="card stat-card">
                        <div class="stat-icon stat-icon-primary"><i class="fa-solid fa-coins"></i></div>
                        <div>
                            <p class="stat-label mb-0">Costo ajustado</p>
                            <p class="stat-value mb-0">{{ money(cotizacion.costo_ajustado) }}</p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon stat-icon-primary"><i class="fa-solid fa-chart-column"></i></div>
                        <div>
                            <p class="stat-label mb-0">Resultado calculado</p>
                            <p class="stat-value mb-0">{{ money(cotizacion.utilidad_real) }}</p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" :class="`stat-icon-${TONO_SEMAFORO[cotizacion.estado_margen] ?? 'primary'}`">
                            <i class="fa-solid fa-percent"></i>
                        </div>
                        <div>
                            <p class="stat-label mb-0">Rentabilidad sobre costo</p>
                            <p class="stat-value mb-0">{{ rentabilidad === null ? '—' : pct(rentabilidad) }}</p>
                        </div>
                    </div>
                </div>

                <p v-if="rentabilidad !== null" class="bi-aviso mb-0">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>
                        Por cada Bs 100 de costo ajustado, el sistema calcula {{ money(rentabilidad * 100) }} de resultado
                        después de los impuestos indicados.
                    </span>
                </p>

                <div class="cotizacion-analisis-paneles">
                    <!-- Cómo se obtiene el resultado: la resta, paso a paso -->
                    <div class="margen-panel">
                        <h3 class="cotizacion-analisis-subtitulo">Cómo se obtiene el resultado</h3>
                        <!-- Con descuento, se ve el paso: es lo que más mueve el resultado. -->
                        <template v-if="tieneDescuento">
                            <div class="margen-row">
                                <span>Subtotal de los productos</span>
                                <span>{{ money(cotizacion.subtotal) }}</span>
                            </div>
                            <div class="margen-row">
                                <span>Menos descuento</span>
                                <span>− {{ money(cotizacion.descuento) }}</span>
                            </div>
                        </template>
                        <div class="margen-row" :class="{ 'fw-semibold': tieneDescuento }">
                            <span>Precio sin IVA</span>
                            <span>{{ money(precioSinIva) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>Menos costo ajustado</span>
                            <span>− {{ money(cotizacion.costo_ajustado) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IT calculado ({{ pct(config.impuestos.it) }})</span>
                            <span>− {{ money(cotizacion.it) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IUE calculado ({{ pct(config.impuestos.iue) }})</span>
                            <span>− {{ money(cotizacion.iue) }}</span>
                        </div>
                        <div class="margen-row cotizacion-analisis-resultado">
                            <span>Resultado calculado</span>
                            <span>{{ money(cotizacion.utilidad_real) }}</span>
                        </div>
                        <p class="cotizacion-analisis-pie">
                            Costo de insumos: {{ money(cotizacion.costo_base) }} · Ajuste por complejidad: {{ ajusteComplejidad }}
                            <template v-if="Number(cotizacion.instalacion) > 0">
                                · La instalación ({{ money(cotizacion.instalacion) }}) se cobra aparte y no entra en este cálculo.
                            </template>
                        </p>
                    </div>

                    <!-- Resultado por producto -->
                    <div class="margen-panel">
                        <h3 class="cotizacion-analisis-subtitulo">Resultado por producto</h3>
                        <div class="table-responsive">
                            <table class="table-dashboard table-sm cotizacion-analisis-productos">
                                <thead>
                                    <tr>
                                        <th scope="col">Producto</th>
                                        <th scope="col">Nivel</th>
                                        <th scope="col" class="text-center">Factor</th>
                                        <th scope="col" class="text-end">Margen del nivel</th>
                                        <th scope="col" class="text-end">Costo</th>
                                        <th scope="col" class="text-end">Precio</th>
                                        <th scope="col" class="text-end">Resultado</th>
                                        <th scope="col" class="text-end">Sobre costo</th>
                                        <th scope="col" class="text-center">Recomendación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="d in cotizacion.detalles" :key="`m-${d.id}`">
                                        <td>{{ d.descripcion }}</td>
                                        <td>{{ d.tipo_proyecto?.nombre ?? 'Precio a mano' }}</td>
                                        <td class="text-center">× {{ numero(d.factor_complejidad) }}</td>
                                        <td class="text-end">{{ d.tipo_proyecto ? pct(d.margen_aplicado) : '—' }}</td>
                                        <td class="text-end">{{ money(d.costo_ajustado) }}</td>
                                        <td class="text-end">{{ money(d.subtotal) }}</td>
                                        <td class="text-end">{{ money(margenDe(d.id)?.utilidad_real) }}</td>
                                        <td class="text-end">{{ pct(margenDe(d.id)?.rentabilidad) }}</td>
                                        <td class="text-center">
                                            <span v-if="margenDe(d.id)" :class="CLASES_SEMAFORO[margenDe(d.id).estado]">
                                                <span class="semaforo-punto"></span>
                                                {{ textoRecomendacion(margenDe(d.id).estado) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="tieneDescuento" class="cotizacion-analisis-pie">
                            Cada producto se evalúa a su precio antes del descuento de {{ money(cotizacion.descuento) }}.
                            El total de arriba ya lo resta: por eso su recomendación puede ser distinta.
                        </p>
                        <details v-if="niveles.length" class="cotizacion-desplegable cotizacion-desplegable-simple">
                            <summary>
                                <i class="fa-solid fa-chevron-right"></i>
                                Ver parámetros del nivel:
                                {{ niveles.map((d) => `${d.tipo_proyecto.nombre} · × ${numero(d.tipo_proyecto.factor_complejidad)} · ${pct(d.tipo_proyecto.margen_minimo)}`).join('; ') }}
                            </summary>
                            <ul class="cotizacion-desplegable-lista">
                                <li v-for="d in niveles" :key="d.tipo_proyecto.id">
                                    <strong>{{ d.tipo_proyecto.nombre }}:</strong>
                                    el costo de insumos se multiplica por {{ numero(d.tipo_proyecto.factor_complejidad) }}
                                    (factor de complejidad) y el precio sugerido suma un margen mínimo de
                                    {{ pct(d.tipo_proyecto.margen_minimo) }} sobre ese costo ajustado.
                                </li>
                            </ul>
                        </details>
                    </div>
                </div>

                <!-- Desplegables: al abrir uno ocupa todo el ancho -->
                <div class="cotizacion-analisis-pie-fila">
                    <details v-if="totalInsumos" class="cotizacion-desplegable">
                        <summary>
                            <i class="fa-solid fa-chevron-right"></i>
                            Ver hoja de costos · {{ totalInsumos }} {{ totalInsumos === 1 ? 'insumo' : 'insumos' }}
                        </summary>
                        <div v-for="d in lineasConInsumos" :key="`h-${d.id}`" class="cotizacion-desplegable-cuerpo">
                            <p v-if="lineasConInsumos.length > 1" class="fs-sm fw-semibold mb-2">{{ d.descripcion }}</p>
                            <div class="table-responsive">
                                <table class="table-dashboard table-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col">Tipo</th>
                                            <th scope="col">Descripción</th>
                                            <th scope="col" class="text-end">Cantidad</th>
                                            <th scope="col" class="text-end">Costo unit.</th>
                                            <th scope="col" class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in d.items" :key="item.id">
                                            <td>{{ item.tipo }}</td>
                                            <td>{{ item.descripcion }}</td>
                                            <td class="text-end">{{ Number(item.cantidad) }} {{ item.unidad ?? '' }}</td>
                                            <td class="text-end">{{ money(item.costo_unitario) }}</td>
                                            <td class="text-end fw-semibold">{{ money(item.subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>

                    <details class="cotizacion-desplegable">
                        <summary>
                            <i class="fa-solid fa-chevron-right"></i>
                            Ver fórmula y criterios del semáforo
                        </summary>
                        <dl class="bi-formulas-lista cotizacion-desplegable-cuerpo">
                            <div>
                                <dt>Costo ajustado</dt>
                                <dd>Costo de insumos × factor de complejidad del nivel.</dd>
                            </div>
                            <div>
                                <dt>Precio sin IVA</dt>
                                <dd>Suma de los subtotales de las líneas, menos el descuento.</dd>
                            </div>
                            <div>
                                <dt>IT calculado</dt>
                                <dd>{{ pct(config.impuestos.it) }} del precio sin IVA.</dd>
                            </div>
                            <div>
                                <dt>IUE calculado</dt>
                                <dd>{{ pct(config.impuestos.iue) }} de (precio sin IVA − costo ajustado − IT).</dd>
                            </div>
                            <div>
                                <dt>Resultado calculado</dt>
                                <dd>Precio sin IVA − costo ajustado − IT − IUE.</dd>
                            </div>
                            <div>
                                <dt>Rentabilidad sobre costo</dt>
                                <dd>Resultado calculado ÷ costo ajustado.</dd>
                            </div>
                            <div v-if="config.semaforo?.umbral_verde !== undefined">
                                <dt>Recomendación del sistema</dt>
                                <dd>
                                    "Aceptar" si la rentabilidad supera el {{ pct(config.semaforo.umbral_verde) }};
                                    "Revisar precio" si supera el {{ pct(config.semaforo.umbral_amarillo) }};
                                    "No aceptar" en otro caso.
                                </dd>
                            </div>
                        </dl>
                    </details>

                    <p class="cotizacion-analisis-aclaracion">
                        El porcentaje se calcula sobre el costo, no sobre las ventas. La recomendación no aprueba la
                        cotización por sí sola.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
