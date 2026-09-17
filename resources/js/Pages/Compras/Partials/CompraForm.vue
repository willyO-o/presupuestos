<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import QuickCreateModal from '@/Components/QuickCreateModal.vue';
import ProveedorFormFields from '@/Components/Proveedor/ProveedorFormFields.vue';
import EmpleadoFormFields from '@/Components/Empleado/EmpleadoFormFields.vue';
import MaterialFormFields from '@/Components/Material/MaterialFormFields.vue';
import { showError } from '@/Utils/AlertUtil';

/**
 * Formulario compartido de crear/editar una compra (ver .ai/rules/pages.md:
 * información compleja con líneas de detalle → vista independiente + partial
 * compartido). Si recibe `compra` guarda con PUT, si no con POST.
 */
const props = defineProps({
    compra: { type: Object, default: null },
    proveedores: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    materiales: { type: Array, default: () => [] },
    empleadoActualId: { type: [Number, String], default: null },
    // Solo alimentan los modales de alta rápida (Nuevo proveedor/
    // responsable/material) — ver Components/QuickCreateModal.vue.
    categoriasMaterial: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargosEmpleado: { type: Array, default: () => [] },
});

const esEdicion = computed(() => !!props.compra);

/* ── Alta rápida de proveedor ─────────────────────────────────────────── */

const proveedoresDisponibles = ref([...props.proveedores]);
const mostrarModalProveedor = ref(false);

function proveedorVacio() {
    return { nombre: '', nit: '', contacto: '', telefono: '', direccion: '', estado: 'ACTIVO' };
}

function onProveedorCreado(proveedor) {
    proveedoresDisponibles.value = [proveedor, ...proveedoresDisponibles.value];
    form.proveedor_id = proveedor.id;
    mostrarModalProveedor.value = false;
}

/* ── Alta rápida de empleado (responsable) ───────────────────────────── */

const empleadosDisponibles = ref([...props.empleados]);
const mostrarModalEmpleado = ref(false);

function empleadoVacio() {
    return {
        user_id: '',
        sucursal_id: props.sucursales[0]?.id ?? '',
        area_id: props.areas[0]?.id ?? '',
        nombres: '',
        paterno: '',
        materno: '',
        ci: '',
        cargo: '',
        telefono: '',
        fecha_ingreso: new Date().toISOString().slice(0, 10),
        estado: 'ACTIVO',
    };
}

function onEmpleadoCreado(empleado) {
    empleadosDisponibles.value = [empleado, ...empleadosDisponibles.value];
    form.empleado_id = empleado.id;
    mostrarModalEmpleado.value = false;
}

/* ── Alta rápida de material (por línea) ─────────────────────────────── */

const materialesDisponibles = ref([...props.materiales]);
const mostrarModalMaterial = ref(false);
const lineaModalMaterial = ref(null);

function materialVacio() {
    return {
        categoria_material_id: props.categoriasMaterial[0]?.id ?? '',
        nombre: '',
        presentacion: '',
        unidad_medida: 'M2',
        precio_presentacion: '',
        precio_unitario: '',
        stock_actual: 0,
        stock_minimo: 0,
        redondeo_compra: '',
        estado: 'ACTIVO',
    };
}

function abrirModalMaterial(index) {
    lineaModalMaterial.value = index;
    mostrarModalMaterial.value = true;
}

function onMaterialCreado(material) {
    materialesDisponibles.value = [material, ...materialesDisponibles.value];

    if (lineaModalMaterial.value !== null) {
        form.detalles[lineaModalMaterial.value].material_id = material.id;
        onMaterialChange(lineaModalMaterial.value);
    }

    mostrarModalMaterial.value = false;
}

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

function hoy() {
    return new Date().toISOString().slice(0, 10);
}

function lineaVacia() {
    return { material_id: '', cantidad: 1, precio_unitario: '' };
}

const detallesIniciales = esEdicion.value && props.compra.detalles?.length
    ? props.compra.detalles.map((d) => ({
        material_id: d.material_id ?? '',
        cantidad: Number(d.cantidad ?? 1),
        precio_unitario: d.precio_unitario ?? '',
    }))
    : [lineaVacia()];

const form = useForm(() => ({
    proveedor_id: props.compra?.proveedor_id ?? (props.proveedores[0]?.id ?? ''),
    empleado_id: props.compra?.empleado_id ?? props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    numero_factura: props.compra?.numero_factura ?? '',
    fecha: toDateInput(props.compra?.fecha) || hoy(),
    detalles: detallesIniciales,
}));

function materialDe(id) {
    return materialesDisponibles.value.find((m) => m.id === Number(id)) ?? null;
}

function onMaterialChange(index) {
    const linea = form.detalles[index];
    const material = materialDe(linea.material_id);
    if (material && !linea.precio_unitario) {
        linea.precio_unitario = material.precio_unitario;
    }
}

function agregarLinea() {
    form.detalles.push(lineaVacia());
}

function quitarLinea(index) {
    if (form.detalles.length === 1) return;
    form.detalles.splice(index, 1);
}

function subtotalLinea(linea) {
    return Number(linea.cantidad || 0) * Number(linea.precio_unitario || 0);
}

const total = computed(() => form.detalles.reduce((acc, l) => acc + subtotalLinea(l), 0));

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function nombreEmpleado(e) {
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function etiquetaEmpleado(e) {
    return e.cargo ? `${nombreEmpleado(e)} (${e.cargo})` : nombreEmpleado(e);
}

function etiquetaProveedor(p) {
    return p.nit ? `${p.nombre} — ${p.nit}` : p.nombre;
}

function etiquetaMaterial(m) {
    return m.presentacion ? `${m.nombre} — ${m.presentacion}` : m.nombre;
}

function submit() {
    form.transform((data) => ({
        ...data,
        detalles: data.detalles.map((l) => ({
            material_id: l.material_id || null,
            cantidad: Number(l.cantidad),
            precio_unitario: Number(l.precio_unitario),
        })),
    }));

    const opciones = { onError: (errors) => showError(errors) };

    if (esEdicion.value) {
        form.put(route('compras.update', props.compra.id), opciones);
    } else {
        form.post(route('compras.store'), opciones);
    }
}
</script>

<template>
    <!-- Envoltorio para que los QuickCreateModal (cada uno con su propio
         <form>) queden FUERA del <form> de la compra — ver el mismo
         comentario en CotizacionForm.vue. -->
    <div>
    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Datos de la compra</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0" for="proveedor_id">Proveedor</label>
                                <button v-can="'proveedores.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                    @click="mostrarModalProveedor = true">
                                    <i class="fa-solid fa-plus"></i>
                                    Nuevo proveedor
                                </button>
                            </div>
                            <SearchableSelect id="proveedor_id" v-model="form.proveedor_id" :options="proveedoresDisponibles"
                                :option-label="etiquetaProveedor" placeholder="Selecciona un proveedor"
                                :invalid="!!form.errors.proveedor_id" />
                            <p v-if="form.errors.proveedor_id" class="form-error">{{ form.errors.proveedor_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0" for="empleado_id">Responsable</label>
                                <button v-can="'empleados.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                    @click="mostrarModalEmpleado = true">
                                    <i class="fa-solid fa-plus"></i>
                                    Nuevo empleado
                                </button>
                            </div>
                            <SearchableSelect id="empleado_id" v-model="form.empleado_id" :options="empleadosDisponibles"
                                :option-label="etiquetaEmpleado" placeholder="Selecciona un responsable"
                                :invalid="!!form.errors.empleado_id" />
                            <p v-if="form.errors.empleado_id" class="form-error">{{ form.errors.empleado_id }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="numero_factura">N.º de factura</label>
                            <input id="numero_factura" v-model="form.numero_factura" type="text" class="form-control"
                                :class="{ 'is-invalid': form.errors.numero_factura }" placeholder="Opcional" />
                            <p v-if="form.errors.numero_factura" class="form-error">{{ form.errors.numero_factura }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="fecha">Fecha</label>
                            <input id="fecha" v-model="form.fecha" type="date" class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha }" required />
                            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Materiales comprados</span>
                <button type="button" class="btn btn-primary btn-sm" @click="agregarLinea">
                    <i class="fa-solid fa-plus"></i>
                    Agregar material
                </button>
            </div>
            <div class="card-body">
                <p v-if="typeof form.errors.detalles === 'string'" class="form-error mb-3">
                    {{ form.errors.detalles }}
                </p>

                <div v-for="(linea, index) in form.detalles" :key="index" class="compra-linea">
                    <div class="compra-linea-head">
                        <span class="fw-semibold fs-sm">Ítem {{ index + 1 }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            :disabled="form.detalles.length === 1" aria-label="Quitar ítem" @click="quitarLinea(index)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-lg-5">
                            <div class="form-group">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label mb-0">Material</label>
                                    <button v-can="'materiales.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                        @click="abrirModalMaterial(index)">
                                        <i class="fa-solid fa-plus"></i>
                                        Nuevo
                                    </button>
                                </div>
                                <SearchableSelect v-model="linea.material_id" :options="materialesDisponibles"
                                    :option-label="etiquetaMaterial" placeholder="Selecciona un material"
                                    :invalid="!!form.errors[`detalles.${index}.material_id`]"
                                    @update:model-value="onMaterialChange(index)" />
                                <p v-if="form.errors[`detalles.${index}.material_id`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.material_id`] }}
                                </p>
                            </div>
                        </div>
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
                        <div class="col-lg-3 col-6">
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
                        <div class="col-lg-2 col-12">
                            <div class="form-group">
                                <label class="form-label">Subtotal</label>
                                <input :value="money(subtotalLinea(linea))" type="text" class="form-control fw-semibold"
                                    disabled />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="compra-total-row mt-3">
                    <span>Total de la compra</span>
                    <span>{{ money(total) }}</span>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('compras.index')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-floppy-disk"></i>
                {{ esEdicion ? 'Guardar cambios' : 'Registrar compra' }}
            </button>
        </div>
    </form>

    <QuickCreateModal :show="mostrarModalProveedor" title="Nuevo proveedor" route-name="proveedores.rapido"
        :initial-data="proveedorVacio" submit-label="Crear proveedor" max-width="md"
        hint="Se guarda de una vez en el catálogo de proveedores; al terminar queda elegido en esta compra."
        @close="mostrarModalProveedor = false" @created="onProveedorCreado">
        <template #default="{ form: proveedorForm, errors: proveedorErrors }">
            <ProveedorFormFields :form="proveedorForm" :errors="proveedorErrors" />
        </template>
    </QuickCreateModal>

    <QuickCreateModal :show="mostrarModalEmpleado" title="Nuevo empleado" route-name="empleados.rapido"
        :initial-data="empleadoVacio" submit-label="Crear empleado"
        hint="Se guarda de una vez en el catálogo de empleados; al terminar queda elegido como responsable."
        @close="mostrarModalEmpleado = false" @created="onEmpleadoCreado">
        <template #default="{ form: empleadoForm, errors: empleadoErrors }">
            <EmpleadoFormFields :form="empleadoForm" :errors="empleadoErrors" :sucursales="sucursales" :areas="areas"
                :cargos="cargosEmpleado" />
        </template>
    </QuickCreateModal>

    <QuickCreateModal :show="mostrarModalMaterial" title="Nuevo material" route-name="materiales.rapido"
        :initial-data="materialVacio" submit-label="Crear material"
        hint="Se guarda de una vez en el catálogo de materiales; al terminar queda elegido en esta línea."
        @close="mostrarModalMaterial = false" @created="onMaterialCreado">
        <template #default="{ form: materialForm, errors: materialErrors }">
            <MaterialFormFields :form="materialForm" :errors="materialErrors" :categorias-material="categoriasMaterial" />
        </template>
    </QuickCreateModal>
    </div>
</template>
