<script setup>
import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { showError } from '@/Utils/AlertUtil';

/**
 * Formulario compartido de crear/editar una cotización (ver .ai/rules/pages.md:
 * información compleja con líneas de detalle → vista independiente + partial
 * compartido, no un modal). Si recibe `cotizacion` guarda con PUT, si no con POST.
 */
const props = defineProps({
    cotizacion: { type: Object, default: null },
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
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

function lineaVacia(extra = {}) {
    return {
        producto_id: '',
        descripcion: '',
        ancho: '',
        alto: '',
        cantidad: 1,
        precio_unitario: '',
        ...extra,
    };
}

const detallesIniciales = esEdicion.value && props.cotizacion.detalles?.length
    ? props.cotizacion.detalles.map((d) => ({
        producto_id: d.producto_id ?? '',
        descripcion: d.descripcion ?? '',
        ancho: d.ancho ?? '',
        alto: d.alto ?? '',
        cantidad: Number(d.cantidad ?? 1),
        precio_unitario: d.precio_unitario ?? '',
    }))
    : [lineaVacia()];

const form = useForm(() => ({
    cliente_id: props.cotizacion?.cliente_id ?? (props.clientes[0]?.id ?? ''),
    empleado_id: props.cotizacion?.empleado_id ?? props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    sucursal_id: props.cotizacion?.sucursal_id ?? (props.sucursales[0]?.id ?? ''),
    fecha: toDateInput(props.cotizacion?.fecha) || hoy(),
    fecha_vencimiento: toDateInput(props.cotizacion?.fecha_vencimiento) || hoyMas(props.config.dias_vencimiento),
    descuento: props.cotizacion?.descuento ?? 0,
    impuesto: props.cotizacion?.impuesto ?? 0,
    observaciones: props.cotizacion?.observaciones ?? '',
    detalles: detallesIniciales,
}));

/* Estado auxiliar del costeo por línea (fuera del form para no enviarlo). */
const costeo = reactive(form.detalles.map(() => ({ cargando: false, error: null, info: null })));

function productoDe(lineaProductoId) {
    return props.productos.find((p) => p.id === Number(lineaProductoId)) ?? null;
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
        if (!linea.precio_unitario && producto.precio_base) {
            linea.precio_unitario = producto.precio_base;
        }
    }
}

async function calcularPrecio(index) {
    const linea = form.detalles[index];
    if (!linea.producto_id) return;

    costeo[index].cargando = true;
    costeo[index].error = null;

    try {
        const { data } = await window.axios.post(route('cotizaciones.costear'), {
            producto_id: linea.producto_id,
            ancho: linea.ancho || null,
            alto: linea.alto || null,
        });
        costeo[index].info = data;
        linea.precio_unitario = data.precio_sugerido;
    } catch (error) {
        costeo[index].error = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'No se pudo calcular el precio.';
    } finally {
        costeo[index].cargando = false;
    }
}

function agregarLinea() {
    form.detalles.push(lineaVacia());
    costeo.push({ cargando: false, error: null, info: null });
}

function quitarLinea(index) {
    if (form.detalles.length === 1) return;
    form.detalles.splice(index, 1);
    costeo.splice(index, 1);
}

function subtotalLinea(linea) {
    return Number(linea.cantidad || 0) * Number(linea.precio_unitario || 0);
}

const subtotal = computed(() => form.detalles.reduce((acc, l) => acc + subtotalLinea(l), 0));
const descuento = computed(() => Math.min(Number(form.descuento || 0), subtotal.value));
const impuesto = computed(() => Number(form.impuesto || 0));
const total = computed(() => Math.max(subtotal.value - descuento.value + impuesto.value, 0));

function aplicarIva() {
    form.impuesto = Number(((subtotal.value - descuento.value) * (props.config.impuesto_porcentaje / 100)).toFixed(2));
}

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function nombreEmpleado(e) {
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function submit() {
    form.transform((data) => ({
        ...data,
        descuento: Number(data.descuento || 0),
        impuesto: Number(data.impuesto || 0),
        detalles: data.detalles.map((l) => ({
            producto_id: l.producto_id || null,
            descripcion: l.descripcion,
            ancho: l.ancho === '' ? null : Number(l.ancho),
            alto: l.alto === '' ? null : Number(l.alto),
            cantidad: Number(l.cantidad),
            precio_unitario: Number(l.precio_unitario),
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
                            <input id="fecha_vencimiento" v-model="form.fecha_vencimiento" type="date" class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha_vencimiento }" />
                            <p v-if="form.errors.fecha_vencimiento" class="form-error">
                                {{ form.errors.fecha_vencimiento }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle -->
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
                        <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            :disabled="form.detalles.length === 1" aria-label="Quitar ítem" @click="quitarLinea(index)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
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
                        <div class="col-lg-8">
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
                                <label class="form-label">Precio unit. (Bs)</label>
                                <input v-model="linea.precio_unitario" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`detalles.${index}.precio_unitario`] }"
                                    required />
                                <p v-if="form.errors[`detalles.${index}.precio_unitario`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.precio_unitario`] }}
                                </p>
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

                    <div class="cotizacion-linea-foot">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <button type="button" class="btn btn-sm btn-soft-info"
                                :disabled="!linea.producto_id || costeo[index].cargando" @click="calcularPrecio(index)">
                                <i v-if="costeo[index].cargando" class="fa-solid fa-spinner fa-spin"></i>
                                <i v-else class="fa-solid fa-calculator"></i>
                                Calcular precio sugerido
                            </button>

                            <span v-if="costeo[index].info" class="fs-sm text-muted">
                                Costo material: <strong>{{ money(costeo[index].info.costo_material_unitario) }}</strong>
                                · Margen {{ Math.round(costeo[index].info.margen * 100) }}%
                                · Sugerido: <strong>{{ money(costeo[index].info.precio_sugerido) }}</strong>
                            </span>
                            <span v-if="costeo[index].error" class="fs-sm text-danger">
                                {{ costeo[index].error }}
                            </span>
                        </div>

                        <ul v-if="costeo[index].info?.lineas?.length" class="cotizacion-costeo-lista">
                            <li v-for="(m, mi) in costeo[index].info.lineas" :key="mi">
                                {{ m.material }}:
                                <template v-if="m.redondeada">
                                    {{ Number(m.cantidad_bruta) }} → <strong>{{ Number(m.cantidad) }}</strong>
                                    {{ m.unidad }} <span class="text-info">(unidad de compra)</span>
                                </template>
                                <template v-else>{{ Number(m.cantidad) }} {{ m.unidad }}</template>
                                · {{ money(m.costo) }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Totales + observaciones -->
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
                            <input id="descuento" v-model="form.descuento" v-decimal="2" type="text" inputmode="decimal"
                                class="form-control cotizacion-total-input" />
                        </div>
                        <div class="cotizacion-total-row">
                            <label class="mb-0" for="impuesto">
                                Impuesto (Bs)
                                <button type="button" class="btn btn-sm btn-soft-secondary ms-1" @click="aplicarIva">
                                    IVA {{ config.impuesto_porcentaje }}%
                                </button>
                            </label>
                            <input id="impuesto" v-model="form.impuesto" v-decimal="2" type="text" inputmode="decimal"
                                class="form-control cotizacion-total-input" />
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
