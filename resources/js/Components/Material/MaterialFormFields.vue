<script setup>
/**
 * Campos del formulario de material, compartidos entre Materiales/Index.vue
 * y los modales de alta rápida embebidos en Compras/Partials/CompraForm.vue,
 * Pedidos/Show.vue (registro de consumo) y Productos/Receta.vue.
 */
const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    categoriasMaterial: { type: Array, default: () => [] },
});

const unidadesMedida = [
    { value: 'M2', label: 'm²' },
    { value: 'METRO_LINEAL', label: 'Metro lineal' },
    { value: 'UNIDAD', label: 'Unidad' },
    { value: 'LITRO', label: 'Litro' },
];

function etiquetaUnidad(valor) {
    return unidadesMedida.find((u) => u.value === valor)?.label ?? valor;
}

/**
 * El precio por unidad de medida sale del precio de la presentación y de lo
 * que trae (rollo de Bs 2.800 con 160 m² = Bs 17,50 el m²). Solo se
 * recalcula cuando se escribe en uno de esos dos campos, no al abrir.
 */
function recalcularPrecioUnitario() {
    const contenido = Number(props.form.contenido_presentacion || 0);
    const precio = Number(props.form.precio_presentacion || 0);

    if (contenido > 0 && precio > 0) {
        props.form.precio_unitario = Number((precio / contenido).toFixed(2));
    }
}
</script>

<template>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="material-categoria">Categoría</label>
                <select id="material-categoria" v-model="form.categoria_material_id" class="form-control"
                    :class="{ 'is-invalid': errors.categoria_material_id }" required>
                    <option v-for="categoria in categoriasMaterial" :key="categoria.id" :value="categoria.id">
                        {{ categoria.nombre }}
                    </option>
                </select>
                <p v-if="errors.categoria_material_id" class="form-error">{{ errors.categoria_material_id }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="material-nombre">Nombre</label>
                <input id="material-nombre" v-model="form.nombre" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.nombre }" required autofocus />
                <p v-if="errors.nombre" class="form-error">{{ errors.nombre }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="form-group">
                <label class="form-label" for="material-presentacion">Presentación</label>
                <input id="material-presentacion" v-model="form.presentacion" type="text" class="form-control"
                    :class="{ 'is-invalid': errors.presentacion }" placeholder="Rollo 3,20x50m, Plancha 2x1m..."
                    required />
                <p v-if="errors.presentacion" class="form-error">{{ errors.presentacion }}</p>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="form-group">
                <label class="form-label" for="material-unidad">Unidad de medida</label>
                <select id="material-unidad" v-model="form.unidad_medida" class="form-control">
                    <option v-for="unidad in unidadesMedida" :key="unidad.value" :value="unidad.value">
                        {{ unidad.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="material-contenido">
                    ¿Cuánto trae? (en {{ etiquetaUnidad(form.unidad_medida) }})
                </label>
                <input id="material-contenido" v-model="form.contenido_presentacion" v-decimal="4" type="text"
                    inputmode="decimal" class="form-control" placeholder="1"
                    :class="{ 'is-invalid': errors.contenido_presentacion }" @input="recalcularPrecioUnitario" />
                <p v-if="errors.contenido_presentacion" class="form-error">{{ errors.contenido_presentacion }}</p>
                <p v-else class="fs-xs text-muted mt-1 mb-0">Ej.: rollo 3,20 × 50 m = 160 m²; galón = 3,78 L.</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="material-precio-presentacion">Precio presentación (Bs)</label>
                <input id="material-precio-presentacion" v-model="form.precio_presentacion" type="number" step="0.01"
                    min="0" class="form-control" :class="{ 'is-invalid': errors.precio_presentacion }" required
                    @input="recalcularPrecioUnitario" />
                <p v-if="errors.precio_presentacion" class="form-error">{{ errors.precio_presentacion }}</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="form-label" for="material-precio-unitario">
                    Precio por {{ etiquetaUnidad(form.unidad_medida).toLowerCase() }} (Bs)
                </label>
                <input id="material-precio-unitario" v-model="form.precio_unitario" type="number" step="0.01" min="0"
                    class="form-control" :class="{ 'is-invalid': errors.precio_unitario }" required />
                <p v-if="errors.precio_unitario" class="form-error">{{ errors.precio_unitario }}</p>
                <p v-else class="fs-xs text-muted mt-1 mb-0">Es el que usan las cotizaciones. Se calcula solo.</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="material-stock-actual">Stock actual</label>
                <input id="material-stock-actual" v-model="form.stock_actual" type="number" step="0.01" min="0"
                    class="form-control" :class="{ 'is-invalid': errors.stock_actual }" required />
                <p v-if="errors.stock_actual" class="form-error">{{ errors.stock_actual }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="material-stock-minimo">Stock mínimo</label>
                <input id="material-stock-minimo" v-model="form.stock_minimo" type="number" step="0.01" min="0"
                    class="form-control" :class="{ 'is-invalid': errors.stock_minimo }" required />
                <p v-if="errors.stock_minimo" class="form-error">{{ errors.stock_minimo }}</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label class="form-label" for="material-redondeo">Redondeo de compra</label>
                <input id="material-redondeo" v-model="form.redondeo_compra" v-decimal="4" type="text"
                    inputmode="decimal" class="form-control" :class="{ 'is-invalid': errors.redondeo_compra }"
                    placeholder="Sin redondeo" />
                <p v-if="errors.redondeo_compra" class="form-error">{{ errors.redondeo_compra }}</p>
                <p class="fs-sm text-muted mt-1">
                    Al costear, la cantidad consumida se redondea hacia arriba a este múltiplo (en
                    {{ unidadesMedida.find((u) => u.value === form.unidad_medida)?.label ?? form.unidad_medida }}).
                    Vacío = se usa la cantidad exacta. Ej.: <code>1</code> unidades enteras,
                    <code>6</code> barra de 6 m, <code>2.98</code> plancha de acrílico.
                </p>
            </div>
        </div>
    </div>

    <div class="form-group mb-0">
        <label class="form-label" for="material-estado">Estado</label>
        <select id="material-estado" v-model="form.estado" class="form-control">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
        </select>
    </div>
</template>
