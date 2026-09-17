<script setup>
/**
 * Campos del formulario de producto (SIN la imagen referencial: esa sube un
 * archivo y el modal de alta rápida guarda por axios/JSON — ver
 * Components/QuickCreateModal.vue). Compartidos entre Productos/Index.vue
 * (que sí agrega su propio FileDropzone aparte) y el modal de alta rápida
 * embebido en la línea "Producto" de Cotizaciones/Partials/CotizacionForm.vue.
 */
import SearchableSelect from '@/Components/SearchableSelect.vue';

defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    categoriasProducto: { type: Array, default: () => [] },
    // El botón "Nueva categoría" solo tiene sentido cuando quien usa este
    // componente sabe manejar el evento (abrir su propio modal) — ver
    // Productos/Index.vue. Sin esto, el modal de alta rápida de producto
    // (dentro de Cotizaciones) mostraría un botón que no hace nada.
    permiteCrearCategoria: { type: Boolean, default: false },
});

defineEmits(['crear-categoria']);

const unidadesMedida = [
    { value: 'M2', label: 'm²' },
    { value: 'UNIDAD', label: 'Unidad' },
    { value: 'METRO_LINEAL', label: 'Metro lineal' },
];
</script>

<template>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label mb-0" for="producto-categoria">Categoría</label>
                    <button v-if="permiteCrearCategoria" v-can="'categorias-producto.crear'" type="button"
                        class="btn btn-sm btn-soft-primary" @click="$emit('crear-categoria')">
                        <i class="fa-solid fa-plus"></i>
                        Nueva categoría
                    </button>
                </div>
                <SearchableSelect id="producto-categoria" v-model="form.categoria_producto_id" :options="categoriasProducto"
                    option-label="nombre" placeholder="Selecciona una categoría"
                    :invalid="!!errors.categoria_producto_id" />
                <p v-if="errors.categoria_producto_id" class="form-error">{{ errors.categoria_producto_id }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="producto-nombre">Nombre</label>
                <input id="producto-nombre" v-model="form.nombre" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.nombre }" required autofocus />
                <p v-if="errors.nombre" class="form-error">{{ errors.nombre }}</p>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="producto-descripcion">Descripción</label>
        <textarea id="producto-descripcion" v-model="form.descripcion" class="form-control" rows="2"
            :class="{ 'is-invalid': errors.descripcion }"></textarea>
        <p v-if="errors.descripcion" class="form-error">{{ errors.descripcion }}</p>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="producto-unidad">Unidad de medida</label>
                <select id="producto-unidad" v-model="form.unidad_medida" class="form-control">
                    <option v-for="unidad in unidadesMedida" :key="unidad.value" :value="unidad.value">
                        {{ unidad.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="producto-precio-base">Precio base (Bs)</label>
                <input id="producto-precio-base" v-model="form.precio_base" type="number" step="0.01" min="0"
                    class="form-control" :class="{ 'is-invalid': errors.precio_base }" />
                <p v-if="errors.precio_base" class="form-error">{{ errors.precio_base }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="producto-requiere-medidas">¿Pide medidas al cotizar?</label>
                <select id="producto-requiere-medidas" v-model="form.requiere_medidas" class="form-control">
                    <option value="SI">Sí</option>
                    <option value="NO">No</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label class="form-label" for="producto-cotizable-web">¿Se ofrece en el cotizador web?</label>
                <select id="producto-cotizable-web" v-model="form.cotizable_web" class="form-control">
                    <option value="NO">No</option>
                    <option value="SI">Sí, publicarlo</option>
                </select>
                <p class="fs-sm text-muted mt-1 mb-0">
                    Aparece en /cotizador solo si además tiene receta (BOM) cargada.
                </p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label class="form-label" for="producto-estado">Estado</label>
                <select id="producto-estado" v-model="form.estado" class="form-control">
                    <option value="ACTIVO">Activo</option>
                    <option value="INACTIVO">Inactivo</option>
                </select>
            </div>
        </div>
    </div>
</template>
