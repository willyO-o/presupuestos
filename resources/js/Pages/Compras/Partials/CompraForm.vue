<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
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
});

const esEdicion = computed(() => !!props.compra);

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
    return props.materiales.find((m) => m.id === Number(id)) ?? null;
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
    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Datos de la compra</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="proveedor_id">Proveedor</label>
                            <select id="proveedor_id" v-model="form.proveedor_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.proveedor_id }" required>
                                <option value="" disabled>Selecciona un proveedor</option>
                                <option v-for="p in proveedores" :key="p.id" :value="p.id">
                                    {{ p.nombre }}<span v-if="p.nit"> — {{ p.nit }}</span>
                                </option>
                            </select>
                            <p v-if="form.errors.proveedor_id" class="form-error">{{ form.errors.proveedor_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" for="empleado_id">Responsable</label>
                            <select id="empleado_id" v-model="form.empleado_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.empleado_id }" required>
                                <option value="" disabled>Selecciona un responsable</option>
                                <option v-for="e in empleados" :key="e.id" :value="e.id">
                                    {{ nombreEmpleado(e) }}<span v-if="e.cargo"> ({{ e.cargo }})</span>
                                </option>
                            </select>
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
                                <label class="form-label">Material</label>
                                <select v-model="linea.material_id" class="form-control"
                                    :class="{ 'is-invalid': form.errors[`detalles.${index}.material_id`] }" required
                                    @change="onMaterialChange(index)">
                                    <option value="" disabled>Selecciona un material</option>
                                    <option v-for="m in materiales" :key="m.id" :value="m.id">
                                        {{ m.nombre }}<span v-if="m.presentacion"> — {{ m.presentacion }}</span>
                                    </option>
                                </select>
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
</template>
