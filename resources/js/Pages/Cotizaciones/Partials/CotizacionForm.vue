<script setup>
import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { showError } from '@/Utils/AlertUtil';
import { CLASES_SEMAFORO, calcularMargen, costoBaseDe, evaluarMargen } from '@/Utils/MotorMargen';

/**
 * Formulario compartido de crear/editar una cotización (ver .ai/rules/pages.md:
 * información compleja con líneas de detalle → vista independiente + partial
 * compartido, no un modal). Si recibe `cotizacion` guarda con PUT, si no con POST.
 *
 * Cada línea replica una hoja del Excel de margen automático: una lista de
 * insumos (materiales, mano de obra, impresión…), un nivel de complejidad y,
 * opcionalmente, un monto de instalación. El precio sale del motor de margen;
 * el vendedor puede tomar el control y escribirlo a mano.
 *
 * Los números que se ven acá son previsualización (Utils/MotorMargen.js): al
 * guardar, el servidor recalcula todo y su resultado es el que manda.
 */
const props = defineProps({
    cotizacion: { type: Object, default: null },
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
    tiposProyecto: { type: Array, default: () => [] },
    tiposItem: { type: Object, default: () => ({}) },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
});

const esEdicion = computed(() => !!props.cotizacion);

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

function hoy() {
    return new Date().toISOString().slice(0, 10);
}

function hoyMas(dias) {
    const d = new Date();
    d.setDate(d.getDate() + dias);
    return d.toISOString().slice(0, 10);
}

function itemVacio(extra = {}) {
    return {
        material_id: null,
        tipo: 'MATERIAL',
        descripcion: '',
        unidad: '',
        cantidad: 1,
        costo_unitario: '',
        ...extra,
    };
}

function lineaVacia(extra = {}) {
    return {
        producto_id: '',
        tipo_proyecto_id: props.tiposProyecto[0]?.id ?? '',
        descripcion: '',
        ancho: '',
        alto: '',
        cantidad: 1,
        instalacion: 0,
        precio_manual: 'NO',
        precio_unitario: '',
        items: [],
        ...extra,
    };
}

const detallesIniciales = esEdicion.value && props.cotizacion.detalles?.length
    ? props.cotizacion.detalles.map((d) => ({
        producto_id: d.producto_id ?? '',
        tipo_proyecto_id: d.tipo_proyecto_id ?? '',
        descripcion: d.descripcion ?? '',
        ancho: d.ancho ?? '',
        alto: d.alto ?? '',
        cantidad: Number(d.cantidad ?? 1),
        instalacion: Number(d.instalacion ?? 0),
        precio_manual: d.precio_manual ?? 'NO',
        precio_unitario: d.precio_unitario ?? '',
        items: (d.items ?? []).map((i) => ({
            material_id: i.material_id ?? null,
            tipo: i.tipo ?? 'MATERIAL',
            descripcion: i.descripcion ?? '',
            unidad: i.unidad ?? '',
            cantidad: Number(i.cantidad ?? 0),
            costo_unitario: Number(i.costo_unitario ?? 0),
        })),
    }))
    : [lineaVacia()];

const form = useForm(() => ({
    cliente_id: props.cotizacion?.cliente_id ?? (props.clientes[0]?.id ?? ''),
    empleado_id: props.cotizacion?.empleado_id ?? props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    sucursal_id: props.cotizacion?.sucursal_id ?? (props.sucursales[0]?.id ?? ''),
    fecha: toDateInput(props.cotizacion?.fecha) || hoy(),
    fecha_vencimiento: toDateInput(props.cotizacion?.fecha_vencimiento) || hoyMas(props.config.dias_vencimiento),
    descuento: props.cotizacion?.descuento ?? 0,
    // El IVA lo calcula el motor; acá solo se decide si la factura lo lleva.
    aplicar_iva: props.cotizacion ? Number(props.cotizacion.impuesto) > 0 : true,
    observaciones: props.cotizacion?.observaciones ?? '',
    detalles: detallesIniciales,
}));

/* Estado auxiliar del costeo por línea (fuera del form para no enviarlo). */
const costeo = reactive(form.detalles.map(() => ({ cargando: false, error: null, info: null })));

/* ── Catálogo ────────────────────────────────────────────────────────── */

function productoDe(lineaProductoId) {
    return props.productos.find((p) => p.id === Number(lineaProductoId)) ?? null;
}

function tipoProyectoDe(tipoId) {
    return props.tiposProyecto.find((t) => t.id === Number(tipoId)) ?? null;
}

function pideMedidas(linea) {
    const producto = productoDe(linea.producto_id);
    return !producto || producto.requiere_medidas === 'SI';
}

function onProductoChange(index) {
    const linea = form.detalles[index];
    const producto = productoDe(linea.producto_id);
    costeo[index] = { cargando: false, error: null, info: null };

    if (!producto) return;

    if (!linea.descripcion) {
        linea.descripcion = producto.nombre;
    }
    if (producto.requiere_medidas === 'NO') {
        linea.ancho = '';
        linea.alto = '';
    }
}

/* ── Motor de margen (previsualización en vivo) ──────────────────────── */

function factorDe(linea) {
    return Number(tipoProyectoDe(linea.tipo_proyecto_id)?.factor_complejidad ?? 1);
}

function margenDe(linea) {
    const tipo = tipoProyectoDe(linea.tipo_proyecto_id);
    return tipo ? Number(tipo.margen_minimo) : props.config.margen_sugerido;
}

function costoBaseLinea(linea) {
    return costoBaseDe(linea.items);
}

/** Resultado del motor para UNA unidad de la línea, con el precio sugerido. */
function motorLinea(linea) {
    return calcularMargen(
        props.config,
        costoBaseLinea(linea),
        factorDe(linea),
        margenDe(linea),
        Number(linea.instalacion || 0),
    );
}

/** Precio unitario efectivo: el del motor, salvo que el vendedor lo fije. */
function precioUnitario(linea) {
    if (linea.precio_manual === 'SI' || linea.items.length === 0) {
        return Number(linea.precio_unitario || 0);
    }
    return motorLinea(linea).precio;
}

function subtotalLinea(linea) {
    return Number(linea.cantidad || 0) * precioUnitario(linea);
}

/** Rentabilidad real de la línea con el precio que se va a cobrar. */
function margenLinea(linea) {
    const cantidad = Number(linea.cantidad || 0);

    return evaluarMargen(
        props.config,
        costoBaseLinea(linea) * factorDe(linea) * cantidad,
        subtotalLinea(linea),
        Number(linea.instalacion || 0),
    );
}

function tomarControlDelPrecio(linea) {
    linea.precio_unitario = Number(motorLinea(linea).precio.toFixed(2));
    linea.precio_manual = 'SI';
}

function devolverPrecioAlMotor(linea) {
    linea.precio_manual = 'NO';
}

/* ── Hoja de costos ──────────────────────────────────────────────────── */

function agregarItem(index, extra = {}) {
    form.detalles[index].items.push(itemVacio(extra));
}

function quitarItem(index, itemIndex) {
    form.detalles[index].items.splice(itemIndex, 1);
}

function subtotalItem(item) {
    return Number(item.cantidad || 0) * Number(item.costo_unitario || 0);
}

/**
 * Trae los insumos de la receta/BOM del producto para las medidas cargadas
 * (POST /cotizaciones/costear). Reemplaza los materiales traídos antes pero
 * respeta las filas que el vendedor escribió a mano (mano de obra, servicios).
 */
async function traerInsumos(index) {
    const linea = form.detalles[index];
    if (!linea.producto_id) return;

    costeo[index].cargando = true;
    costeo[index].error = null;

    try {
        const { data } = await window.axios.post(route('cotizaciones.costear'), {
            producto_id: linea.producto_id,
            ancho: linea.ancho || null,
            alto: linea.alto || null,
            tipo_proyecto_id: linea.tipo_proyecto_id || null,
            instalacion: Number(linea.instalacion || 0),
        });

        costeo[index].info = data;

        const escritosAMano = linea.items.filter((i) => !i.material_id);
        linea.items = [...data.insumos.map((i) => ({ ...i })), ...escritosAMano];
    } catch (error) {
        costeo[index].error = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'No se pudieron traer los insumos del producto.';
    } finally {
        costeo[index].cargando = false;
    }
}

/* ── Líneas ──────────────────────────────────────────────────────────── */

function agregarLinea() {
    form.detalles.push(lineaVacia());
    costeo.push({ cargando: false, error: null, info: null });
}

function quitarLinea(index) {
    if (form.detalles.length === 1) return;
    form.detalles.splice(index, 1);
    costeo.splice(index, 1);
}

/* ── Totales de la cotización ────────────────────────────────────────── */

const subtotal = computed(() => form.detalles.reduce((acc, l) => acc + subtotalLinea(l), 0));
const descuento = computed(() => Math.min(Number(form.descuento || 0), subtotal.value));
const instalacionTotal = computed(() => form.detalles.reduce((acc, l) => acc + Number(l.instalacion || 0), 0));

const costoAjustadoTotal = computed(() => form.detalles.reduce(
    (acc, l) => acc + costoBaseLinea(l) * factorDe(l) * Number(l.cantidad || 0),
    0,
));

const costoBaseTotal = computed(() => form.detalles.reduce(
    (acc, l) => acc + costoBaseLinea(l) * Number(l.cantidad || 0),
    0,
));

/** Motor sobre el presupuesto completo: la base imponible es subtotal − descuento. */
const motorTotal = computed(() => evaluarMargen(
    props.config,
    costoAjustadoTotal.value,
    subtotal.value - descuento.value,
    instalacionTotal.value,
));

const iva = computed(() => (form.aplicar_iva ? motorTotal.value.iva : 0));
const total = computed(() => Math.max(subtotal.value - descuento.value + iva.value + instalacionTotal.value, 0));

/* ── Formato ─────────────────────────────────────────────────────────── */

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function pct(fraccion) {
    return `${(Number(fraccion || 0) * 100).toFixed(1)} %`;
}

function nombreEmpleado(e) {
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function anchoBarra(rentabilidad) {
    // El umbral verde marca el 100 % de la barra: pasar de ahí ya es óptimo.
    const tope = props.config.semaforo.umbral_verde;
    return `${Math.min(Math.max(Number(rentabilidad || 0) / tope, 0), 1) * 100}%`;
}

function claseBarra(estado) {
    return `margen-barra-fill is-${estado.toLowerCase()}`;
}

/* ── Guardar ─────────────────────────────────────────────────────────── */

function submit() {
    form.transform((data) => ({
        ...data,
        descuento: Number(data.descuento || 0),
        aplicar_iva: !!data.aplicar_iva,
        detalles: data.detalles.map((l) => ({
            producto_id: l.producto_id || null,
            tipo_proyecto_id: l.tipo_proyecto_id || null,
            descripcion: l.descripcion,
            ancho: l.ancho === '' ? null : Number(l.ancho),
            alto: l.alto === '' ? null : Number(l.alto),
            cantidad: Number(l.cantidad),
            instalacion: Number(l.instalacion || 0),
            precio_manual: l.items.length === 0 ? 'SI' : l.precio_manual,
            precio_unitario: Number(l.precio_unitario || 0),
            items: l.items.map((i) => ({
                material_id: i.material_id || null,
                tipo: i.tipo,
                descripcion: i.descripcion,
                unidad: i.unidad || null,
                cantidad: Number(i.cantidad || 0),
                costo_unitario: Number(i.costo_unitario || 0),
            })),
        })),
    }));

    const opciones = {
        onError: (errors) => showError(errors),
    };

    if (esEdicion.value) {
        form.put(route('cotizaciones.update', props.cotizacion.id), opciones);
    } else {
        form.post(route('cotizaciones.store'), opciones);
    }
}
</script>

<template>
    <form @submit.prevent="submit">
        <!-- Cabecera -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Datos de la cotización</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="cliente_id">Cliente</label>
                            <select id="cliente_id" v-model="form.cliente_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.cliente_id }" required>
                                <option value="" disabled>Selecciona un cliente</option>
                                <option v-for="c in clientes" :key="c.id" :value="c.id">
                                    {{ c.razon_social }} — {{ c.nit }}
                                </option>
                            </select>
                            <p v-if="form.errors.cliente_id" class="form-error">{{ form.errors.cliente_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="empleado_id">Vendedor</label>
                            <select id="empleado_id" v-model="form.empleado_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.empleado_id }" required>
                                <option value="" disabled>Selecciona un vendedor</option>
                                <option v-for="e in empleados" :key="e.id" :value="e.id">
                                    {{ nombreEmpleado(e) }}<span v-if="e.cargo"> ({{ e.cargo }})</span>
                                </option>
                            </select>
                            <p v-if="form.errors.empleado_id" class="form-error">{{ form.errors.empleado_id }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="sucursal_id">Sucursal</label>
                            <select id="sucursal_id" v-model="form.sucursal_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.sucursal_id }" required>
                                <option value="" disabled>Selecciona una sucursal</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">
                                    {{ s.nombre }}<span v-if="s.ciudad"> — {{ s.ciudad }}</span>
                                </option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="form-error">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="fecha">Fecha</label>
                            <input id="fecha" v-model="form.fecha" type="date" class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha }" required />
                            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="fecha_vencimiento">Válida hasta</label>
                            <input id="fecha_vencimiento" v-model="form.fecha_vencimiento" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_vencimiento }" />
                            <p v-if="form.errors.fecha_vencimiento" class="form-error">
                                {{ form.errors.fecha_vencimiento }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle: una hoja de costos por ítem -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Detalle del presupuesto</span>
                <button type="button" class="btn btn-primary btn-sm" @click="agregarLinea">
                    <i class="fa-solid fa-plus"></i>
                    Agregar ítem
                </button>
            </div>
            <div class="card-body">
                <p v-if="typeof form.errors.detalles === 'string'" class="form-error mb-3">
                    {{ form.errors.detalles }}
                </p>

                <div v-for="(linea, index) in form.detalles" :key="index" class="cotizacion-linea">
                    <div class="cotizacion-linea-head">
                        <span class="fw-semibold fs-sm">Ítem {{ index + 1 }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="linea.items.length" :class="CLASES_SEMAFORO[margenLinea(linea).estado]">
                                <span class="semaforo-punto"></span>
                                {{ margenLinea(linea).recomendacion }}
                            </span>
                            <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                                :disabled="form.detalles.length === 1" aria-label="Quitar ítem"
                                @click="quitarLinea(index)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label">Producto</label>
                                <select v-model="linea.producto_id" class="form-control"
                                    @change="onProductoChange(index)">
                                    <option value="">Ítem personalizado</option>
                                    <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label">Tipo de proyecto</label>
                                <select v-model="linea.tipo_proyecto_id" class="form-control">
                                    <option value="">Sin nivel (margen por defecto)</option>
                                    <option v-for="t in tiposProyecto" :key="t.id" :value="t.id">
                                        {{ t.nombre }} — ×{{ Number(t.factor_complejidad).toFixed(2) }} ·
                                        {{ pct(t.margen_minimo) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label">Descripción</label>
                                <input v-model="linea.descripcion" type="text" class="form-control"
                                    :class="{ 'is-invalid': form.errors[`detalles.${index}.descripcion`] }"
                                    placeholder="Detalle que verá el cliente" required />
                                <p v-if="form.errors[`detalles.${index}.descripcion`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.descripcion`] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <template v-if="pideMedidas(linea)">
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label">Ancho (m)</label>
                                    <input v-model="linea.ancho" v-decimal="2" type="text" inputmode="decimal"
                                        class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label">Alto (m)</label>
                                    <input v-model="linea.alto" v-decimal="2" type="text" inputmode="decimal"
                                        class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label">Área m²</label>
                                    <input :value="(Number(linea.ancho || 0) * Number(linea.alto || 0)).toFixed(2)"
                                        type="text" class="form-control" disabled />
                                </div>
                            </div>
                        </template>

                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <label class="form-label">Cantidad</label>
                                <input v-model="linea.cantidad" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`detalles.${index}.cantidad`] }" required />
                                <p v-if="form.errors[`detalles.${index}.cantidad`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.cantidad`] }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <label class="form-label">Instalación (Bs)</label>
                                <input v-model="linea.instalacion" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control" placeholder="0.00" />
                                <p class="fs-xs text-muted mt-1 mb-0">Monto fijo, sin IVA</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <label class="form-label">Subtotal</label>
                                <input :value="money(subtotalLinea(linea))" type="text" class="form-control fw-semibold"
                                    disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Hoja de costos: los insumos que componen UNA unidad -->
                    <div class="hoja-costos">
                        <div class="hoja-costos-head">
                            <span class="fw-semibold fs-sm">
                                <i class="fa-solid fa-table-list text-primary"></i>
                                Hoja de costos (por unidad)
                            </span>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-soft-info"
                                    :disabled="!linea.producto_id || costeo[index].cargando"
                                    title="Trae los materiales de la receta del producto para estas medidas"
                                    @click="traerInsumos(index)">
                                    <i v-if="costeo[index].cargando" class="fa-solid fa-spinner fa-spin"></i>
                                    <i v-else class="fa-solid fa-flask"></i>
                                    Traer insumos del producto
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-primary" @click="agregarItem(index)">
                                    <i class="fa-solid fa-plus"></i>
                                    Insumo
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary"
                                    @click="agregarItem(index, { tipo: 'MANO_OBRA', descripcion: 'Horas', unidad: 'HORA' })">
                                    <i class="fa-solid fa-user-clock"></i>
                                    Mano de obra
                                </button>
                            </div>
                        </div>

                        <p v-if="costeo[index].error" class="form-error">{{ costeo[index].error }}</p>

                        <p v-if="!linea.items.length" class="hoja-costos-vacia">
                            Sin insumos cargados: el precio de este ítem se escribe a mano.
                            Traé la receta del producto o agregá insumos para que el sistema calcule el precio y la
                            rentabilidad.
                        </p>

                        <template v-else>
                            <div class="hoja-costos-labels">
                                <span class="col-span-2">Tipo</span>
                                <span class="col-span-4">Descripción</span>
                                <span class="col-span-1">Unidad</span>
                                <span class="col-span-2">Cantidad</span>
                                <span class="col-span-2">Costo unit.</span>
                                <span class="col-span-1">Subtotal</span>
                            </div>

                            <div v-for="(item, itemIndex) in linea.items" :key="itemIndex" class="hoja-costos-fila">
                                <div class="col-span-12 lg:col-span-2">
                                    <select v-model="item.tipo" class="form-control">
                                        <option v-for="(etiqueta, valor) in tiposItem" :key="valor" :value="valor">
                                            {{ etiqueta }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-span-12 lg:col-span-4">
                                    <input v-model="item.descripcion" type="text" class="form-control"
                                        placeholder="Fierro, plancha, horas..." required />
                                </div>
                                <div class="col-span-4 lg:col-span-1">
                                    <input v-model="item.unidad" type="text" class="form-control" placeholder="m²" />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.cantidad" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" required />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.costo_unitario" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" required />
                                </div>
                                <div class="col-span-12 lg:col-span-1 d-flex align-items-center gap-2">
                                    <span class="fs-sm fw-semibold flex-1">{{ money(subtotalItem(item)) }}</span>
                                    <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                                        aria-label="Quitar insumo" @click="quitarItem(index, itemIndex)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="hoja-costos-total">
                                <span>Costo base (1 unidad)</span>
                                <span>{{ money(costoBaseLinea(linea)) }}</span>
                            </div>
                        </template>
                    </div>

                    <!-- Precio de la línea: sugerido por el motor o tomado a mano -->
                    <div class="cotizacion-linea-foot">
                        <div class="row">
                            <div class="col-lg-7">
                                <div class="margen-panel margen-interno">
                                    <div class="margen-row">
                                        <span>Costo ajustado (× {{ factorDe(linea).toFixed(2) }})</span>
                                        <span>{{ money(motorLinea(linea).costoAjustado) }}</span>
                                    </div>
                                    <div class="margen-row">
                                        <span>Precio sugerido (margen {{ pct(margenDe(linea)) }})</span>
                                        <span>{{ money(motorLinea(linea).precio) }}</span>
                                    </div>
                                    <div class="margen-row margen-row-destacada">
                                        <span>Utilidad real de la línea</span>
                                        <span>
                                            {{ money(margenLinea(linea).utilidadReal) }}
                                            <span class="fs-xs text-muted">
                                                ({{ pct(margenLinea(linea).rentabilidad) }} del costo)
                                            </span>
                                        </span>
                                    </div>
                                    <div class="margen-barra">
                                        <div :class="claseBarra(margenLinea(linea).estado)"
                                            :style="{ width: anchoBarra(margenLinea(linea).rentabilidad) }"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="form-group">
                                    <label class="form-label">Precio unitario (Bs)</label>
                                    <!-- Con hoja de costos y sin tomar el control, el precio lo
                                         manda el motor: se muestra en solo lectura para que no
                                         parezca editable y quede pisado al guardar. -->
                                    <input v-if="linea.precio_manual !== 'SI' && linea.items.length"
                                        :value="motorLinea(linea).precio.toFixed(2)" type="text"
                                        class="form-control fw-semibold" disabled />
                                    <input v-else v-model="linea.precio_unitario" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control"
                                        :class="{ 'is-invalid': form.errors[`detalles.${index}.precio_unitario`] }"
                                        required />
                                    <p v-if="form.errors[`detalles.${index}.precio_unitario`]" class="form-error">
                                        {{ form.errors[`detalles.${index}.precio_unitario`] }}
                                    </p>

                                    <div v-if="linea.items.length" class="mt-2">
                                        <button v-if="linea.precio_manual !== 'SI'" type="button"
                                            class="btn btn-sm btn-soft-warning" @click="tomarControlDelPrecio(linea)">
                                            <i class="fa-solid fa-pen"></i>
                                            Fijar precio a mano
                                        </button>
                                        <button v-else type="button" class="btn btn-sm btn-soft-info"
                                            @click="devolverPrecioAlMotor(linea)">
                                            <i class="fa-solid fa-rotate-left"></i>
                                            Volver al precio sugerido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Redondeo a unidad de compra: viene del costeo del BOM -->
                        <ul v-if="costeo[index].info?.lineas?.some((m) => m.redondeada)" class="cotizacion-costeo-lista">
                            <li v-for="(m, mi) in costeo[index].info.lineas.filter((l) => l.redondeada)" :key="mi">
                                {{ m.material }}: {{ Number(m.cantidad_bruta) }} →
                                <strong>{{ Number(m.cantidad) }}</strong> {{ m.unidad }}
                                <span class="text-info">(redondeado a unidad de compra)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Totales + rentabilidad + observaciones -->
        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header"><span class="card-title">Observaciones</span></div>
                    <div class="card-body">
                        <textarea v-model="form.observaciones" class="form-control" rows="5"
                            :class="{ 'is-invalid': form.errors.observaciones }"
                            placeholder="Condiciones, tiempo de entrega, notas para el cliente..."></textarea>
                        <p v-if="form.errors.observaciones" class="form-error">{{ form.errors.observaciones }}</p>
                    </div>
                </div>

                <!-- Panel interno: nunca se imprime (clase .margen-interno) -->
                <div class="card mb-4 margen-interno">
                    <div class="card-header">
                        <span class="card-title">Rentabilidad del presupuesto</span>
                        <span :class="CLASES_SEMAFORO[motorTotal.estado]">
                            <span class="semaforo-punto"></span>
                            {{ motorTotal.recomendacion }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="margen-row">
                            <span>Costo de insumos</span>
                            <span>{{ money(costoBaseTotal) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>Costo ajustado por complejidad</span>
                            <span>{{ money(costoAjustadoTotal) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IT ({{ pct(config.impuestos.it) }})</span>
                            <span>− {{ money(motorTotal.it) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IUE ({{ pct(config.impuestos.iue) }})</span>
                            <span>− {{ money(motorTotal.iue) }}</span>
                        </div>
                        <div class="margen-row margen-row-destacada">
                            <span>Utilidad real</span>
                            <span>
                                {{ money(motorTotal.utilidadReal) }}
                                <span class="fs-xs text-muted">({{ pct(motorTotal.rentabilidad) }} del costo)</span>
                            </span>
                        </div>
                        <div class="margen-barra">
                            <div :class="claseBarra(motorTotal.estado)"
                                :style="{ width: anchoBarra(motorTotal.rentabilidad) }"></div>
                        </div>
                        <p class="fs-xs text-muted mt-2 mb-0">
                            Verde: utilidad sobre {{ pct(config.semaforo.umbral_verde) }} del costo ajustado ·
                            Amarillo: sobre {{ pct(config.semaforo.umbral_amarillo) }} ·
                            Rojo: por debajo. Información interna, no se imprime en la cotización.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><span class="card-title">Totales</span></div>
                    <div class="card-body">
                        <div class="cotizacion-total-row">
                            <span>Subtotal</span>
                            <span class="fw-semibold">{{ money(subtotal) }}</span>
                        </div>
                        <div class="cotizacion-total-row">
                            <label class="mb-0" for="descuento">Descuento (Bs)</label>
                            <input id="descuento" v-model="form.descuento" v-decimal="2" type="text"
                                inputmode="decimal" class="form-control cotizacion-total-input" />
                        </div>
                        <div class="cotizacion-total-row">
                            <label class="mb-0 d-flex align-items-center gap-2" for="aplicar_iva">
                                <input id="aplicar_iva" v-model="form.aplicar_iva" type="checkbox" />
                                IVA {{ pct(config.impuestos.iva) }}
                            </label>
                            <span class="fw-semibold">{{ money(iva) }}</span>
                        </div>
                        <div v-if="instalacionTotal > 0" class="cotizacion-total-row">
                            <span>Instalación</span>
                            <span class="fw-semibold">{{ money(instalacionTotal) }}</span>
                        </div>
                        <div class="cotizacion-total-row cotizacion-total-grand">
                            <span>Total</span>
                            <span>{{ money(total) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-floppy-disk"></i>
                {{ esEdicion ? 'Guardar cambios' : 'Crear cotización' }}
            </button>
        </div>
    </form>
</template>
