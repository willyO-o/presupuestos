<script setup>
/**
 * Select con búsqueda para catálogos que pueden crecer mucho (cliente,
 * producto, material, proveedor, empleado, cotización...). Envuelve
 * vue-multiselect para no reimplementar teclado/accesibilidad/filtrado a
 * mano, pero expone la misma forma que un <select> nativo: `modelValue` es
 * el ID escalar (lo que espera el form de Inertia y el backend), no el
 * objeto completo — el componente hace la traducción id ⇄ objeto por dentro.
 *
 * Reemplaza <select><option v-for>...</select> así:
 *   <SearchableSelect v-model="form.cliente_id" :options="clientes"
 *       option-label="razon_social" placeholder="Selecciona un cliente" />
 */
import { computed } from 'vue';
import Multiselect from 'vue-multiselect';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    optionValue: { type: String, default: 'id' },
    // Nombre de la propiedad a mostrar, o una función (opcion) => string
    // para etiquetas compuestas ("Juan Pérez (Vendedor)").
    optionLabel: { type: [String, Function], default: 'nombre' },
    placeholder: { type: String, default: 'Buscar...' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    // Coincide con el <option value="">Todos</option> que suelen tener los
    // filtros: al limpiar la selección, emite '' en vez de null.
    emptyValue: { type: [String, Number, null], default: '' },
});

const emit = defineEmits(['update:modelValue']);

function etiquetaDe(opcion) {
    if (!opcion) return '';

    return typeof props.optionLabel === 'function' ? props.optionLabel(opcion) : opcion[props.optionLabel];
}

const seleccionado = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return null;
    }

    // Los ids en los <option> nativos viajan como número o string según
    // cómo los haya guardado el form; se compara por igualdad flexible para
    // no depender de cuál de los dos mandó cada pantalla.
    return props.options.find((o) => String(o[props.optionValue]) === String(props.modelValue)) ?? null;
});

function onChange(opcion) {
    emit('update:modelValue', opcion ? opcion[props.optionValue] : props.emptyValue);
}

function limpiar() {
    emit('update:modelValue', props.emptyValue);
}
</script>

<template>
    <Multiselect
        :model-value="seleccionado"
        :options="options"
        :track-by="optionValue"
        :custom-label="etiquetaDe"
        :placeholder="placeholder"
        :disabled="disabled"
        :show-labels="false"
        :close-on-select="true"
        deselect-label=""
        select-label=""
        selected-label=""
        class="searchable-select"
        :class="{ 'is-invalid': invalid }"
        @update:model-value="onChange"
    >
        <!-- vue-multiselect no trae un botón de limpiar por defecto en modo
             single (el slot #clear viene vacío) — sin esto no habría forma de
             volver a "sin selección" salvo eligiendo otra opción. -->
        <template #clear>
            <button v-if="seleccionado && !disabled" type="button" class="searchable-select-clear"
                aria-label="Limpiar selección" @mousedown.prevent="limpiar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </template>
        <template #noResult>Sin resultados para esa búsqueda.</template>
        <template #noOptions>No hay opciones para elegir.</template>
    </Multiselect>
</template>
