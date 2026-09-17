<script setup>
/**
 * Reemplaza <input type="file" class="form-control"> en los formularios que
 * suben imágenes o PDFs (Productos, Cotizaciones, Usuarios, Notas de
 * entrega, Órdenes de compra cliente, Pagos): zona de arrastrar-y-soltar +
 * miniatura/chip del archivo elegido, en vez del input nativo pelado.
 *
 * `modelValue` sigue siendo el File (o null) que el form de Inertia manda
 * como FormData — no cambia el contrato, solo la presentación. `preview` es
 * la URL de un archivo YA guardado (modo edición); no implica que se pueda
 * "quitar" ese archivo del servidor, solo que se puede reemplazar por otro.
 */
import { computed, ref, useTemplateRef } from 'vue';

const props = defineProps({
    modelValue: { type: File, default: null },
    // URL del archivo ya guardado (edición). Null/'' si todavía no hay ninguno.
    preview: { type: String, default: null },
    accept: { type: String, default: 'image/*' },
    // 'imagen' fuerza miniatura, 'archivo' fuerza chip con ícono (PDF), 'auto'
    // decide según el mime del File elegido o la extensión de `preview`.
    kind: { type: String, default: 'auto' },
    hint: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const inputRef = useTemplateRef('input');
const arrastrando = ref(false);

// computed (no una función llamada desde el template): así solo se crea un
// blob URL nuevo cuando `modelValue` realmente cambia, no en cada render.
const previewLocal = computed(() => (props.modelValue ? URL.createObjectURL(props.modelValue) : null));
const previewActivo = computed(() => previewLocal.value ?? props.preview ?? null);
const nombreArchivo = computed(() => props.modelValue?.name ?? null);

const esImagen = computed(() => {
    if (props.kind === 'imagen') return true;
    if (props.kind === 'archivo') return false;
    if (props.modelValue) return props.modelValue.type.startsWith('image/');
    if (props.preview) return /\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i.test(props.preview);

    return true;
});

function abrirSelector() {
    if (!props.disabled) {
        inputRef.value?.click();
    }
}

function onFileChange(event) {
    emit('update:modelValue', event.target.files[0] ?? null);
    // Sin esto, elegir el MISMO archivo dos veces seguidas no dispara "change".
    event.target.value = '';
}

function onDrop(event) {
    arrastrando.value = false;
    if (props.disabled) return;

    const archivo = event.dataTransfer.files[0];
    if (archivo) emit('update:modelValue', archivo);
}

function quitarSeleccion(event) {
    event.stopPropagation();
    emit('update:modelValue', null);
}
</script>

<template>
    <div class="file-dropzone" :class="{ 'is-invalid': invalid, 'is-disabled': disabled, 'is-dragging': arrastrando }"
        role="button" :tabindex="disabled ? -1 : 0" @click="abrirSelector" @keydown.enter.prevent="abrirSelector"
        @keydown.space.prevent="abrirSelector" @dragover.prevent="!disabled && (arrastrando = true)"
        @dragleave.prevent="arrastrando = false" @drop.prevent="onDrop">
        <input ref="input" type="file" :accept="accept" :disabled="disabled" class="hidden" @click.stop
            @change="onFileChange" />

        <template v-if="esImagen && previewActivo">
            <img :src="previewActivo" alt="" class="file-dropzone-thumb" />
            <div class="file-dropzone-overlay">
                <i class="fa-solid fa-camera"></i>
                <span>Cambiar imagen</span>
            </div>
        </template>

        <template v-else-if="!esImagen && (nombreArchivo || previewActivo)">
            <div class="file-dropzone-file">
                <i class="fa-solid fa-file-pdf"></i>
                <span class="file-dropzone-file-name">{{ nombreArchivo ?? 'Archivo actual' }}</span>
            </div>
            <span class="file-dropzone-change">Cambiar archivo</span>
        </template>

        <template v-else>
            <i class="fa-solid fa-cloud-arrow-up file-dropzone-icon"></i>
            <p class="file-dropzone-text"><strong>Haz clic</strong> o arrastra un archivo aquí</p>
        </template>

        <button v-if="modelValue" type="button" class="file-dropzone-clear" aria-label="Quitar archivo elegido"
            @click="quitarSeleccion">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <p v-if="hint" class="file-dropzone-hint">{{ hint }}</p>
    </div>
</template>
