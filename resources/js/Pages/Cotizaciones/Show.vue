<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { confirmation } from '@/Utils/AlertUtil';
import { CLASES_SEMAFORO } from '@/Utils/MotorMargen';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    cotizacion: { type: Object, required: true },
    // Desglose de rentabilidad por línea (interno, no se imprime).
    margen: { type: Array, default: () => [] },
    config: { type: Object, default: () => ({ impuestos: {} }) },
});

/** Rentabilidad de una línea, por id de detalle. */
function margenDe(detalleId) {
    return props.margen.find((m) => m.detalle_id === detalleId) ?? null;
}

const puedeVerMargen = computed(() => props.cotizacion.costo_ajustado > 0 || props.margen.length > 0);

function pct(fraccion) {
    return `${(Number(fraccion || 0) * 100).toFixed(1)} %`;
}

const estadoBadge = {
    PENDIENTE: 'badge-soft-warning',
    APROBADA: 'badge-soft-success',
    RECHAZADA: 'badge-soft-danger',
    CONVERTIDA: 'badge-soft-info',
    VENCIDA: 'badge-soft-secondary',
};

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

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
}

function nombreEmpleado(e) {
    if (!e) return '—';
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
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
</script>

<template>
    <Head :title="`Cotización ${cotizacion.codigo_verificacion}`" />

    <!-- Barra de acciones (no se imprime) -->
    <div class="card mb-4 cotizacion-actions">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
                Volver
            </Link>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-soft-secondary btn-sm" @click="imprimir">
                    <i class="fa-solid fa-print"></i>
                    Imprimir
                </button>
                <!-- PDF con membrete, generado por App\Services\Pdf\GeneradorPdf.
                     Es un enlace normal y no un Link de Inertia: la respuesta es
                     un archivo, no una visita. Se abre en pestaña nueva porque
                     el servidor lo manda como vista previa (inline): asi se
                     revisa la cotizacion antes de enviarla sin perder esta
                     pantalla ni llenar el disco de PDFs. -->
                <a :href="route('cotizaciones.pdf', cotizacion.id)" target="_blank" rel="noopener"
                    class="btn btn-soft-primary btn-sm">
                    <i class="fa-solid fa-file-pdf"></i>
                    Ver PDF
                </a>
                <a :href="route('cotizaciones.pdf', { cotizacion: cotizacion.id, descargar: 1 })"
                    class="btn btn-soft-secondary btn-sm btn-icon" aria-label="Descargar la cotizacion en PDF"
                    title="Descargar PDF">
                    <i class="fa-solid fa-download"></i>
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
                    class="btn btn-soft-info btn-sm">
                    <i class="fa-solid fa-dolly"></i>
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

    <!-- Análisis de rentabilidad: INTERNO, la clase .margen-interno lo saca de la impresión -->
    <div v-if="puedeVerMargen" class="card mb-4 margen-interno">
        <div class="card-header">
            <span class="card-title">
                <i class="fa-solid fa-chart-pie text-primary"></i>
                Análisis de rentabilidad (uso interno)
            </span>
            <span v-if="cotizacion.estado_margen" :class="CLASES_SEMAFORO[cotizacion.estado_margen]">
                <span class="semaforo-punto"></span>
                {{ cotizacion.recomendacion }}
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-5">
                    <div class="margen-row">
                        <span>Costo de insumos</span>
                        <span>{{ money(cotizacion.costo_base) }}</span>
                    </div>
                    <div class="margen-row">
                        <span>Costo ajustado por complejidad</span>
                        <span>{{ money(cotizacion.costo_ajustado) }}</span>
                    </div>
                    <div class="margen-row">
                        <span>IT ({{ pct(config.impuestos.it) }})</span>
                        <span>− {{ money(cotizacion.it) }}</span>
                    </div>
                    <div class="margen-row">
                        <span>IUE ({{ pct(config.impuestos.iue) }})</span>
                        <span>− {{ money(cotizacion.iue) }}</span>
                    </div>
                    <div class="margen-row margen-row-destacada">
                        <span>Utilidad real</span>
                        <span>{{ money(cotizacion.utilidad_real) }}</span>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table class="table-dashboard table-sm">
                            <thead>
                                <tr>
                                    <th>Ítem</th>
                                    <th>Nivel</th>
                                    <th class="text-end">Costo ajustado</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Utilidad</th>
                                    <th class="text-center">Semáforo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in cotizacion.detalles" :key="`m-${d.id}`">
                                    <td>{{ d.descripcion }}</td>
                                    <td>
                                        <span v-if="d.tipo_proyecto">
                                            {{ d.tipo_proyecto.nombre }}
                                            <span class="d-block fs-xs text-muted">
                                                ×{{ Number(d.factor_complejidad).toFixed(2) }} ·
                                                {{ pct(d.margen_aplicado) }}
                                            </span>
                                        </span>
                                        <span v-else class="text-muted">Precio a mano</span>
                                    </td>
                                    <td class="text-end">{{ money(d.costo_ajustado) }}</td>
                                    <td class="text-end">{{ money(d.subtotal) }}</td>
                                    <td class="text-end">
                                        {{ money(margenDe(d.id)?.utilidad_real) }}
                                        <span class="d-block fs-xs text-muted">
                                            {{ pct(margenDe(d.id)?.rentabilidad) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="margenDe(d.id)"
                                            :class="CLASES_SEMAFORO[margenDe(d.id).estado]">
                                            <span class="semaforo-punto"></span>
                                            {{ margenDe(d.id).estado }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Hoja de costos de cada ítem: de dónde salió el costo base -->
            <details v-for="d in cotizacion.detalles.filter((x) => x.items?.length)" :key="`h-${d.id}`" class="mt-3">
                <summary class="fs-sm fw-semibold cursor-pointer">
                    Hoja de costos — {{ d.descripcion }} ({{ d.items.length }} insumos)
                </summary>
                <div class="table-responsive mt-2">
                    <table class="table-dashboard table-sm">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Costo unit.</th>
                                <th class="text-end">Subtotal</th>
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
            </details>
        </div>
    </div>

    <!-- Documento -->
    <div class="card cotizacion-doc">
        <div class="card-body">
            <div class="cotizacion-doc-head">
                <div>
                    <p class="fs-xl fw-bold mb-0 text-heading">XtraPubli</p>
                    <p class="fs-sm text-muted mb-0">Publicidad Industrial · El Alto, Bolivia</p>
                </div>
                <div class="text-end">
                    <p class="fs-lg fw-bold mb-1">COTIZACIÓN</p>
                    <p class="fs-sm mb-1">
                        <span class="text-muted">N.º </span>{{ cotizacion.codigo_verificacion }}
                    </p>
                    <span class="badge" :class="estadoBadge[cotizacion.estado] ?? 'badge-soft-secondary'">
                        {{ cotizacion.estado }}
                    </span>
                </div>
            </div>

            <div class="cotizacion-doc-meta">
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Cliente</p>
                    <p class="fw-semibold mb-0">{{ cotizacion.cliente?.razon_social ?? '—' }}</p>
                    <p class="fs-sm text-muted mb-0">NIT: {{ cotizacion.cliente?.nit ?? '—' }}</p>
                    <p v-if="cotizacion.cliente?.telefono" class="fs-sm text-muted mb-0">
                        Tel: {{ cotizacion.cliente.telefono }}
                    </p>
                </div>
                <div>
                    <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Detalles</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Fecha:</span> {{ fecha(cotizacion.fecha) }}</p>
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
                            <th>#</th>
                            <th></th>
                            <th>Descripción</th>
                            <th class="text-center">Medidas (m)</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">P. unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in cotizacion.detalles" :key="d.id">
                            <td>{{ i + 1 }}</td>
                            <td>
                                <a v-if="d.imagen_url" :href="d.imagen_url" target="_blank" rel="noopener">
                                    <img :src="d.imagen_url" alt="" class="article-thumb" />
                                </a>
                            </td>
                            <td>
                                {{ d.descripcion }}
                                <span v-if="d.producto" class="d-block fs-xs text-muted">{{ d.producto.nombre }}</span>
                            </td>
                            <td class="text-center">
                                <span v-if="d.ancho && d.alto">
                                    {{ Number(d.ancho).toFixed(2) }} × {{ Number(d.alto).toFixed(2) }}
                                    <span class="fs-xs text-muted">({{ Number(d.area_m2).toFixed(2) }} m²)</span>
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="text-end">{{ Number(d.cantidad) }}</td>
                            <td class="text-end">{{ money(d.precio_unitario) }}</td>
                            <td class="text-end fw-semibold">{{ money(d.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

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
                <p class="fs-xs text-muted mb-1 text-uppercase fw-semibold">Observaciones</p>
                <p class="fs-sm mb-0 text-prewrap">{{ cotizacion.observaciones }}</p>
            </div>

            <p class="fs-xs text-muted mt-4 mb-0">
                Documento generado por el sistema de costos y presupuestos XtraPubli.
                Código de verificación: <strong>{{ cotizacion.codigo_verificacion }}</strong>.
            </p>
        </div>
    </div>
</template>
