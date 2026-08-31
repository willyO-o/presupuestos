<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClientePortalLayout from '@/Layouts/ClientePortalLayout.vue';
import { showError } from '@/Utils/AlertUtil';

defineOptions({ layout: ClientePortalLayout });

const props = defineProps({
    productos: { type: Array, default: () => [] },
});

function lineaVacia() {
    return { producto_id: '', descripcion: '', ancho: '', alto: '', cantidad: 1 };
}

const form = useForm(() => ({
    observaciones: '',
    detalles: [lineaVacia()],
}));

function productoDe(id) {
    return props.productos.find((p) => p.id === Number(id)) ?? null;
}
function pideMedidas(linea) {
    const p = productoDe(linea.producto_id);
    return !p || p.requiere_medidas === 'SI';
}
function onProducto(i) {
    const l = form.detalles[i];
    const p = productoDe(l.producto_id);
    if (p && !l.descripcion) l.descripcion = p.nombre;
}
function agregar() {
    form.detalles.push(lineaVacia());
}
function quitar(i) {
    if (form.detalles.length > 1) form.detalles.splice(i, 1);
}

function submit() {
    form.transform((data) => ({
        ...data,
        detalles: data.detalles.map((l) => ({
            producto_id: l.producto_id || null,
            descripcion: l.descripcion,
            ancho: l.ancho === '' ? null : Number(l.ancho),
            alto: l.alto === '' ? null : Number(l.alto),
            cantidad: Number(l.cantidad),
        })),
    }));
    form.post(route('portal.solicitar.store'), { onError: (e) => showError(e) });
}
</script>

<template>
    <Head title="Solicitar cotización" />

    <form @submit.prevent="submit">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">¿Qué necesitas cotizar?</span>
                <button type="button" class="btn btn-primary btn-sm" @click="agregar">
                    <i class="fa-solid fa-plus"></i> Agregar ítem
                </button>
            </div>
            <div class="card-body">
                <div v-for="(linea, i) in form.detalles" :key="i" class="compra-linea">
                    <div class="compra-linea-head">
                        <span class="fw-semibold fs-sm">Ítem {{ i + 1 }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                            :disabled="form.detalles.length === 1" @click="quitar(i)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label">Producto (opcional)</label>
                                <select v-model="linea.producto_id" class="form-control" @change="onProducto(i)">
                                    <option value="">Otro / no listado</option>
                                    <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="form-group">
                                <label class="form-label">Descripción</label>
                                <input v-model="linea.descripcion" type="text" class="form-control" required
                                    placeholder="Ej. Banner para fachada del local" />
                                <p v-if="form.errors[`detalles.${i}.descripcion`]" class="form-error">
                                    {{ form.errors[`detalles.${i}.descripcion`] }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <template v-if="pideMedidas(linea)">
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label">Ancho (m)</label>
                                    <input v-model="linea.ancho" v-decimal="2" type="text" inputmode="decimal"
                                        class="form-control" />
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label">Alto (m)</label>
                                    <input v-model="linea.alto" v-decimal="2" type="text" inputmode="decimal"
                                        class="form-control" />
                                </div>
                            </div>
                        </template>
                        <div class="col-lg-3 col-6">
                            <div class="form-group">
                                <label class="form-label">Cantidad</label>
                                <input v-model="linea.cantidad" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control" required />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Comentarios</label>
                    <textarea v-model="form.observaciones" class="form-control" rows="3"
                        placeholder="Plazos, referencias, ubicación de instalación..."></textarea>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('portal.cotizaciones')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-paper-plane"></i>
                Enviar solicitud
            </button>
        </div>
    </form>
</template>
