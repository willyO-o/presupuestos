<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import QuickCreateModal from '@/Components/QuickCreateModal.vue';
import EmpleadoFormFields from '@/Components/Empleado/EmpleadoFormFields.vue';
import { showError } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    pedido: { type: Object, required: true },
    empleados: { type: Array, default: () => [] },
    empleadoActualId: { type: [Number, String], default: null },
    // Solo alimentan el modal de alta rápida "Nuevo empleado" — ver
    // Components/QuickCreateModal.vue.
    sucursales: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargosEmpleado: { type: Array, default: () => [] },
});

/* ── Alta rápida de empleado (entregado por) ─────────────────────────── */

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

const form = useForm(() => ({
    pedido_id: props.pedido.id,
    empleado_id: props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    fecha_entrega: new Date().toISOString().slice(0, 10),
    recibido_por: '',
    cargo_receptor: '',
    observaciones: '',
    archivo_pdf: null,
    detalles: (props.pedido.detalles ?? []).map((d) => ({
        incluir: d.estado_item !== 'ENTREGADO',
        pedido_detalle_id: d.id,
        descripcion: d.descripcion,
        cantidad_entregada: Number(d.cantidad ?? 1),
        ubicacion: '',
        foto: null,
    })),
}));

function nombreEmpleado(e) {
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function submit() {
    form.transform((data) => ({
        ...data,
        detalles: data.detalles
            .filter((d) => d.incluir)
            .map((d) => ({
                pedido_detalle_id: d.pedido_detalle_id,
                descripcion: d.descripcion,
                cantidad_entregada: Number(d.cantidad_entregada),
                ubicacion: d.ubicacion || null,
                foto: d.foto,
            })),
    }));

    form.post(route('notas-entrega.store'), { onError: (errors) => showError(errors), forceFormData: true });
}
</script>

<template>
    <Head :title="`Nota de entrega · ${pedido.numero_pedido}`" />

    <!-- Envoltorio para que QuickCreateModal (con su propio <form>) quede
         FUERA del <form> de la nota de entrega — ver el mismo comentario en
         CotizacionForm.vue. -->
    <div>
    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Nota de entrega — pedido {{ pedido.numero_pedido }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0">Entregado por</label>
                                <button v-can="'empleados.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                    @click="mostrarModalEmpleado = true">
                                    <i class="fa-solid fa-plus"></i>
                                    Nuevo empleado
                                </button>
                            </div>
                            <SearchableSelect v-model="form.empleado_id" :options="empleadosDisponibles"
                                :option-label="nombreEmpleado" placeholder="Selecciona un empleado"
                                :invalid="!!form.errors.empleado_id" />
                            <p v-if="form.errors.empleado_id" class="form-error">{{ form.errors.empleado_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">Fecha de entrega</label>
                            <input v-model="form.fecha_entrega" type="date" class="form-control" required />
                            <p v-if="form.errors.fecha_entrega" class="form-error">{{ form.errors.fecha_entrega }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">Recibido por</label>
                            <input v-model="form.recibido_por" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">Cargo del receptor</label>
                            <input v-model="form.cargo_receptor" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">PDF firmado (opcional)</label>
                            <FileDropzone v-model="form.archivo_pdf" accept="application/pdf" kind="archivo" />
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea v-model="form.observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Ítems entregados</span></div>
            <div class="card-body">
                <p v-if="typeof form.errors.detalles === 'string'" class="form-error mb-3">{{ form.errors.detalles }}</p>

                <div v-for="(linea, index) in form.detalles" :key="linea.pedido_detalle_id" class="compra-linea">
                    <label class="d-flex align-items-center gap-2 fw-semibold fs-sm mb-3">
                        <input v-model="linea.incluir" type="checkbox" />
                        {{ linea.descripcion }}
                    </label>

                    <div v-if="linea.incluir" class="row">
                        <div class="col-lg-3 col-6">
                            <div class="form-group">
                                <label class="form-label">Cantidad entregada</label>
                                <input v-model="linea.cantidad_entregada" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control" required />
                                <p v-if="form.errors[`detalles.${index}.cantidad_entregada`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.cantidad_entregada`] }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-5 col-6">
                            <div class="form-group">
                                <label class="form-label">Ubicación</label>
                                <input v-model="linea.ubicacion" type="text" class="form-control"
                                    placeholder="Ingreso tienda lado derecho..." />
                            </div>
                        </div>
                        <div class="col-lg-4 col-12">
                            <div class="form-group">
                                <label class="form-label">Foto de evidencia</label>
                                <FileDropzone v-model="linea.foto" accept="image/*" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('pedidos.show', pedido.id)" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-truck-ramp-box"></i>
                Emitir nota de entrega
            </button>
        </div>
    </form>

    <QuickCreateModal :show="mostrarModalEmpleado" title="Nuevo empleado" route-name="empleados.rapido"
        :initial-data="empleadoVacio" submit-label="Crear empleado"
        hint="Se guarda de una vez en el catálogo de empleados; al terminar queda elegido como quien entrega."
        @close="mostrarModalEmpleado = false" @created="onEmpleadoCreado">
        <template #default="{ form: empleadoForm, errors: empleadoErrors }">
            <EmpleadoFormFields :form="empleadoForm" :errors="empleadoErrors" :sucursales="sucursales" :areas="areas"
                :cargos="cargosEmpleado" />
        </template>
    </QuickCreateModal>
    </div>
</template>
