<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import { showError } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    pedido: { type: Object, required: true },
    empleados: { type: Array, default: () => [] },
    empleadoActualId: { type: [Number, String], default: null },
});

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

    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Nota de entrega — pedido {{ pedido.numero_pedido }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">Entregado por</label>
                            <select v-model="form.empleado_id" class="form-control" required>
                                <option v-for="e in empleados" :key="e.id" :value="e.id">{{ nombreEmpleado(e) }}</option>
                            </select>
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
                            <input type="file" accept="application/pdf" class="form-control"
                                @input="form.archivo_pdf = $event.target.files[0]" />
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
                                <input type="file" accept="image/*" class="form-control"
                                    @input="linea.foto = $event.target.files[0]" />
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
</template>
