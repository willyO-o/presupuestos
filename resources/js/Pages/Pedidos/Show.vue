<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import Modal from '@/Components/Modal.vue';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    pedido: { type: Object, required: true },
    costos: { type: Object, required: true },
    cobranza: { type: Object, required: true },
    metodosPago: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    materiales: { type: Array, default: () => [] },
    etapas: { type: Array, default: () => [] },
    estadosItem: { type: Array, default: () => [] },
});

const estadoBadge = {
    DISENO: 'badge-soft-secondary',
    ELABORACION: 'badge-soft-info',
    ACABADO: 'badge-soft-warning',
    ENTREGADO: 'badge-soft-success',
    CANCELADO: 'badge-soft-danger',
};

const cancelable = computed(() => !['ENTREGADO', 'CANCELADO'].includes(props.pedido.estado));

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
}

function fechaHora(value) {
    return value ? new Date(value).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

function nombreEmpleado(e) {
    if (!e) return '—';
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function pasoIndex(estado) {
    return props.estadosItem.indexOf(estado);
}

const costoItem = (id) => props.costos.items.find((i) => i.pedido_detalle_id === id) ?? { estimado: null, real: 0 };

/* ── Modales de acción por ítem ─────────────────────────────────────────── */
const modal = ref(null); // 'area' | 'estado' | 'consumo'
const detalleActivo = ref(null);

const areaForm = useForm(() => ({ area_id: props.areas[0]?.id ?? '', empleado_id: props.empleados[0]?.id ?? '', etapa: 'DISENO', observaciones: '' }));
const estadoForm = useForm(() => ({ estado_item: 'ELABORACION', observaciones: '' }));
const consumoForm = useForm(() => ({ material_id: props.materiales[0]?.id ?? '', cantidad_usada: '', costo_real: '' }));
const pagoForm = useForm(() => ({
    pedido_id: props.pedido.id,
    monto: props.cobranza.saldo || '',
    fecha_pago: new Date().toISOString().slice(0, 10),
    metodo_pago: props.metodosPago[0] ?? 'EFECTIVO',
    comprobante: null,
}));

function abrir(tipo, detalle) {
    detalleActivo.value = detalle;
    modal.value = tipo;
    if (tipo === 'area') { areaForm.clearErrors(); areaForm.reset(); }
    if (tipo === 'estado') { estadoForm.clearErrors(); estadoForm.reset(); estadoForm.estado_item = detalle.estado_item; }
    if (tipo === 'consumo') { consumoForm.clearErrors(); consumoForm.reset(); }
    if (tipo === 'pago') { pagoForm.clearErrors(); pagoForm.reset(); }
}

function cerrar() {
    modal.value = null;
    detalleActivo.value = null;
}

function enviarArea() {
    areaForm.post(route('pedidos.detalle.asignar-area', [props.pedido.id, detalleActivo.value.id]), {
        preserveScroll: true,
        onSuccess: cerrar,
    });
}

function enviarEstado() {
    estadoForm.put(route('pedidos.detalle.estado', [props.pedido.id, detalleActivo.value.id]), {
        preserveScroll: true,
        onSuccess: cerrar,
    });
}

function enviarConsumo() {
    consumoForm.transform((data) => ({
        ...data,
        costo_real: data.costo_real === '' ? null : Number(data.costo_real),
    })).post(route('pedidos.detalle.consumo', [props.pedido.id, detalleActivo.value.id]), {
        preserveScroll: true,
        onSuccess: cerrar,
    });
}

function enviarPago() {
    pagoForm.post(route('pagos.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: cerrar,
    });
}

const cancelForm = useForm({});
async function cancelarPedido() {
    const ok = await confirmation(
        `¿Cancelar el pedido <strong>${props.pedido.numero_pedido}</strong>?`,
        'Cancelar pedido',
        'Sí, cancelar',
    );
    if (!ok) return;
    cancelForm.post(route('pedidos.cancelar', props.pedido.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Pedido ${pedido.numero_pedido}`" />

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <Link :href="route('pedidos.index')" class="btn btn-soft-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
                Volver
            </Link>
            <div class="d-flex flex-wrap gap-2">
                <Link :href="route('cotizaciones.show', pedido.cotizacion_id)" class="btn btn-soft-info btn-sm">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    Ver cotización de origen
                </Link>
                <Link v-if="pedido.estado !== 'CANCELADO'" v-can="'notas-entrega.crear'"
                    :href="route('notas-entrega.create', { pedido: pedido.id })" class="btn btn-soft-primary btn-sm">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    Emitir nota de entrega
                </Link>
                <button v-if="cancelable" v-can="'pedidos.actualizar_estado'" type="button"
                    class="btn btn-soft-danger btn-sm" @click="cancelarPedido">
                    <i class="fa-solid fa-ban"></i>
                    Cancelar pedido
                </button>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <p class="fs-lg fw-bold mb-1">{{ pedido.numero_pedido }}</p>
                    <span class="badge" :class="estadoBadge[pedido.estado] ?? 'badge-soft-secondary'">
                        {{ pedido.estado }}
                    </span>
                </div>
                <div class="text-end">
                    <p class="fs-sm mb-0"><span class="text-muted">Cliente:</span>
                        {{ pedido.cotizacion?.cliente?.razon_social ?? '—' }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Sucursal:</span>
                        {{ pedido.cotizacion?.sucursal?.nombre ?? '—' }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Pedido:</span> {{ fecha(pedido.fecha_pedido) }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Entrega estimada:</span>
                        {{ fecha(pedido.fecha_entrega_estimada) }}</p>
                    <p class="fs-sm mb-0"><span class="text-muted">Entrega real:</span>
                        {{ fecha(pedido.fecha_entrega_real) }}</p>
                    <p class="fs-sm fw-semibold mb-0 mt-1">Total: {{ money(pedido.total) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentos: OC del cliente + notas de entrega -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Documentos</span></div>
        <div class="card-body">
            <p class="fs-sm mb-2">
                <span class="text-muted">Orden de compra del cliente:</span>
                <template v-if="pedido.orden_compra">
                    <span class="fw-semibold">{{ pedido.orden_compra.numero_oc }}</span>
                    <span class="badge ms-2"
                        :class="{ 'badge-soft-success': pedido.orden_compra.estado === 'VALIDADA', 'badge-soft-warning': pedido.orden_compra.estado === 'PENDIENTE', 'badge-soft-secondary': pedido.orden_compra.estado === 'ANULADA' }">
                        {{ pedido.orden_compra.estado }}
                    </span>
                    <a v-if="pedido.orden_compra.archivo_url" :href="pedido.orden_compra.archivo_url" target="_blank"
                        rel="noopener" class="text-primary"> · PDF</a>
                </template>
                <template v-else>
                    <span class="text-muted">sin registrar</span>
                    <Link v-can="'ordenes-compra-cliente.crear'" :href="route('ordenes-compra-cliente.index')"
                        class="text-primary"> · registrar</Link>
                </template>
            </p>
            <p class="fs-sm mb-0">
                <span class="text-muted">Notas de entrega:</span>
                <template v-if="pedido.notas_entrega?.length">
                    <Link v-for="n in pedido.notas_entrega" :key="n.id" :href="route('notas-entrega.show', n.id)"
                        class="text-primary me-2">{{ n.numero_nota }}</Link>
                </template>
                <span v-else class="text-muted">ninguna</span>
            </p>
        </div>
    </div>

    <!-- Cobranza -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">Cobranza</span>
            <button v-can="'pagos.registrar'" type="button" class="btn btn-primary btn-sm"
                :disabled="pedido.estado === 'CANCELADO'" @click="abrir('pago', null)">
                <i class="fa-solid fa-money-check-dollar"></i>
                Registrar pago
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-3 col-6">
                    <p class="fs-sm text-muted mb-0">Total</p>
                    <p class="fw-bold mb-0">{{ money(cobranza.total) }}</p>
                </div>
                <div class="col-lg-3 col-6">
                    <p class="fs-sm text-muted mb-0">Pagado</p>
                    <p class="fw-bold mb-0 text-success">{{ money(cobranza.pagado) }}</p>
                </div>
                <div class="col-lg-3 col-6">
                    <p class="fs-sm text-muted mb-0">Saldo</p>
                    <p class="fw-bold mb-0" :class="cobranza.saldo > 0 ? 'text-danger' : 'text-success'">
                        {{ money(cobranza.saldo) }}
                    </p>
                </div>
                <div class="col-lg-3 col-6">
                    <p class="fs-sm text-muted mb-0">Estado</p>
                    <span class="badge"
                        :class="{ 'badge-soft-success': cobranza.estado === 'PAGADO', 'badge-soft-info': cobranza.estado === 'PARCIAL', 'badge-soft-warning': cobranza.estado === 'PENDIENTE' }">
                        {{ cobranza.estado }}
                    </span>
                </div>
            </div>
            <ul v-if="pedido.pagos?.length" class="pedido-consumo-lista mt-3">
                <li v-for="p in pedido.pagos" :key="p.id">
                    {{ fecha(p.fecha_pago) }} · {{ p.metodo_pago }} · {{ money(p.monto) }}
                </li>
            </ul>
        </div>
    </div>

    <!-- Costo estimado (BOM) vs real -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Costo de materiales: presupuestado vs. real</span></div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <p class="fs-sm text-muted mb-1">Presupuestado (BOM de la cotización)</p>
                    <p class="fs-lg fw-bold mb-0">{{ money(costos.estimado_total) }}</p>
                </div>
                <div class="col-lg-6">
                    <p class="fs-sm text-muted mb-1">Registrado como consumo real</p>
                    <p class="fs-lg fw-bold mb-0"
                        :class="costos.real_total > costos.estimado_total ? 'text-danger' : 'text-success'">
                        {{ money(costos.real_total) }}
                    </p>
                </div>
            </div>
            <div class="table-responsive mt-3">
                <table class="table-dashboard table-sm">
                    <thead>
                        <tr>
                            <th>Ítem</th>
                            <th class="text-end">Presupuestado</th>
                            <th class="text-end">Real</th>
                            <th class="text-end">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in costos.items" :key="c.pedido_detalle_id">
                            <td>{{ c.descripcion }}</td>
                            <td class="text-end">{{ c.estimado === null ? '—' : money(c.estimado) }}</td>
                            <td class="text-end">{{ money(c.real) }}</td>
                            <td class="text-end"
                                :class="c.estimado !== null && c.real > c.estimado ? 'text-danger' : 'text-muted'">
                                {{ c.estimado === null ? '—' : money(c.real - c.estimado) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ítems del pedido -->
    <div v-for="detalle in pedido.detalles" :key="detalle.id" class="card mb-4 pedido-item">
        <div class="card-header">
            <span class="card-title">{{ detalle.descripcion }}</span>
            <span class="badge" :class="estadoBadge[detalle.estado_item] ?? 'badge-soft-secondary'">
                {{ detalle.estado_item }}
            </span>
        </div>
        <div class="card-body">
            <p class="fs-sm text-muted mb-3">
                <span v-if="detalle.ancho && detalle.alto">
                    {{ Number(detalle.ancho).toFixed(2) }} × {{ Number(detalle.alto).toFixed(2) }} m ·
                </span>
                Cantidad: {{ Number(detalle.cantidad) }}
                · Costo material presupuestado:
                {{ costoItem(detalle.id).estimado === null ? '—' : money(costoItem(detalle.id).estimado) }}
                · Real: {{ money(costoItem(detalle.id).real) }}
            </p>

            <!-- Tracker de etapas -->
            <div class="pedido-tracker">
                <div v-for="(paso, i) in estadosItem" :key="paso" class="pedido-tracker-step"
                    :class="{ done: i <= pasoIndex(detalle.estado_item) }">
                    <span class="pedido-tracker-dot">{{ i + 1 }}</span>
                    <span class="pedido-tracker-label">{{ paso }}</span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button v-can="'pedidos.asignar_area'" type="button" class="btn btn-sm btn-soft-primary"
                    @click="abrir('area', detalle)">
                    <i class="fa-solid fa-diagram-project"></i> Asignar área
                </button>
                <button v-can="'pedidos.actualizar_estado'" type="button" class="btn btn-sm btn-soft-info"
                    :disabled="detalle.estado_item === 'ENTREGADO' || pedido.estado === 'CANCELADO'"
                    @click="abrir('estado', detalle)">
                    <i class="fa-solid fa-forward-step"></i> Avanzar estado
                </button>
                <button v-can="'pedidos.actualizar_estado'" type="button" class="btn btn-sm btn-soft-secondary"
                    @click="abrir('consumo', detalle)">
                    <i class="fa-solid fa-boxes-packing"></i> Registrar consumo
                </button>
            </div>

            <!-- Bitácora de seguimiento -->
            <div v-if="detalle.seguimientos?.length" class="pedido-timeline mt-4">
                <div v-for="s in detalle.seguimientos" :key="s.id" class="pedido-timeline-item">
                    <span class="badge badge-soft-secondary">{{ s.etapa }}</span>
                    <span class="fs-sm">
                        {{ s.area?.nombre ?? '—' }} · {{ nombreEmpleado(s.empleado) }}
                    </span>
                    <span class="fs-xs text-muted">
                        {{ fechaHora(s.fecha_inicio) }}<span v-if="s.fecha_fin"> → {{ fechaHora(s.fecha_fin) }}</span>
                    </span>
                    <span v-if="s.observaciones" class="fs-xs text-muted d-block">{{ s.observaciones }}</span>
                </div>
            </div>

            <!-- Consumo de materiales -->
            <ul v-if="detalle.materiales_usados?.length" class="pedido-consumo-lista mt-3">
                <li v-for="m in detalle.materiales_usados" :key="m.id">
                    {{ m.material?.nombre ?? '—' }}: {{ Number(m.cantidad_usada) }} {{ m.material?.unidad_medida }}
                    · {{ money(m.costo_real) }}
                </li>
            </ul>
        </div>
    </div>

    <!-- Modal compartido -->
    <Modal :show="modal !== null" max-width="md" @close="cerrar">
        <div class="card-header">
            <span class="card-title">
                {{ modal === 'area' ? 'Asignar área'
                    : modal === 'estado' ? 'Avanzar estado del ítem'
                    : modal === 'pago' ? 'Registrar pago'
                    : 'Registrar consumo de material' }}
            </span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form v-if="modal === 'area'" @submit.prevent="enviarArea">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Área</label>
                    <select v-model="areaForm.area_id" class="form-control" required>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                    </select>
                    <p v-if="areaForm.errors.area_id" class="form-error">{{ areaForm.errors.area_id }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Responsable</label>
                    <select v-model="areaForm.empleado_id" class="form-control" required>
                        <option v-for="e in empleados" :key="e.id" :value="e.id">
                            {{ nombreEmpleado(e) }}<span v-if="e.cargo"> ({{ e.cargo }})</span>
                        </option>
                    </select>
                    <p v-if="areaForm.errors.empleado_id" class="form-error">{{ areaForm.errors.empleado_id }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Etapa</label>
                    <select v-model="areaForm.etapa" class="form-control" required>
                        <option v-for="et in etapas" :key="et" :value="et">{{ et }}</option>
                    </select>
                    <p v-if="areaForm.errors.etapa" class="form-error">{{ areaForm.errors.etapa }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea v-model="areaForm.observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="areaForm.processing">Asignar</button>
            </div>
        </form>

        <form v-else-if="modal === 'estado'" @submit.prevent="enviarEstado">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Nuevo estado del ítem</label>
                    <select v-model="estadoForm.estado_item" class="form-control" required>
                        <option v-for="s in estadosItem" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <p v-if="estadoForm.errors.estado_item" class="form-error">{{ estadoForm.errors.estado_item }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Observaciones (cierra la etapa abierta)</label>
                    <textarea v-model="estadoForm.observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="estadoForm.processing">Guardar</button>
            </div>
        </form>

        <form v-else-if="modal === 'pago'" @submit.prevent="enviarPago">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Monto (Bs)</label>
                    <input v-model="pagoForm.monto" v-decimal="2" type="text" inputmode="decimal" class="form-control"
                        required />
                    <p v-if="pagoForm.errors.monto" class="form-error">{{ pagoForm.errors.monto }}</p>
                    <p class="fs-xs text-muted mt-1">Saldo actual: {{ money(cobranza.saldo) }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de pago</label>
                    <input v-model="pagoForm.fecha_pago" type="date" class="form-control" required />
                    <p v-if="pagoForm.errors.fecha_pago" class="form-error">{{ pagoForm.errors.fecha_pago }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Método</label>
                    <select v-model="pagoForm.metodo_pago" class="form-control" required>
                        <option v-for="m in metodosPago" :key="m" :value="m">{{ m }}</option>
                    </select>
                    <p v-if="pagoForm.errors.metodo_pago" class="form-error">{{ pagoForm.errors.metodo_pago }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Comprobante (opcional)</label>
                    <input type="file" accept="image/*,application/pdf" class="form-control"
                        @input="pagoForm.comprobante = $event.target.files[0]" />
                    <p v-if="pagoForm.errors.comprobante" class="form-error">{{ pagoForm.errors.comprobante }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="pagoForm.processing">Registrar</button>
            </div>
        </form>

        <form v-else-if="modal === 'consumo'" @submit.prevent="enviarConsumo">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Material</label>
                    <select v-model="consumoForm.material_id" class="form-control" required>
                        <option v-for="m in materiales" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                    </select>
                    <p v-if="consumoForm.errors.material_id" class="form-error">{{ consumoForm.errors.material_id }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Cantidad usada</label>
                    <input v-model="consumoForm.cantidad_usada" v-decimal="2" type="text" inputmode="decimal"
                        class="form-control" required />
                    <p v-if="consumoForm.errors.cantidad_usada" class="form-error">
                        {{ consumoForm.errors.cantidad_usada }}
                    </p>
                </div>
                <div class="form-group">
                    <label class="form-label">Costo real (Bs, opcional)</label>
                    <input v-model="consumoForm.costo_real" v-decimal="2" type="text" inputmode="decimal"
                        class="form-control" placeholder="Se calcula del precio del material si se deja vacío" />
                    <p v-if="consumoForm.errors.costo_real" class="form-error">{{ consumoForm.errors.costo_real }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="consumoForm.processing">Registrar</button>
            </div>
        </form>
    </Modal>
</template>
