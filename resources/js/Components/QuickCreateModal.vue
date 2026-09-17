<script setup>
/**
 * Modal genérico de alta rápida para un catálogo (cliente, producto,
 * material, proveedor, empleado, categoría...) desde OTRO formulario que
 * solo necesita elegirlo en un `SearchableSelect`. Un POST de Inertia
 * navegaría a la pantalla propia de ese catálogo y perdería lo que ya se
 * cargó en el formulario que lo abrió — por eso esto usa `axios` y espera
 * JSON de vuelta, nunca un `useForm` de Inertia.
 *
 * El shell (modal, guardado, errores 422, spinner) vive acá UNA sola vez;
 * cada catálogo aporta sus propios campos por el slot por defecto — ver
 * Components/Cliente/ClienteQuickCreateModal.vue para el primer uso y
 * Components/Cliente/ClienteFormFields.vue para el patrón de "campos
 * compartidos con el CRUD completo".
 *
 * Uso:
 *   <QuickCreateModal :show="mostrar" title="Nuevo material" route-name="materiales.rapido"
 *       :initial-data="materialVacio" @close="mostrar = false" @created="onCreado">
 *       <template #default="{ form, errors }">
 *           <MaterialFormFields :form="form" :errors="errors" :categorias-material="categoriasMaterial" />
 *       </template>
 *   </QuickCreateModal>
 */
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    routeName: { type: String, required: true },
    // Función (no un objeto): cada apertura necesita un objeto NUEVO, si no
    // todos los modales de un mismo tipo compartirían la misma referencia.
    initialData: { type: Function, required: true },
    submitLabel: { type: String, default: 'Crear' },
    hint: { type: String, default: '' },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close', 'created']);

const form = ref(props.initialData());
const errors = ref({});
const procesando = ref(false);
const errorGeneral = ref(null);

function cerrar() {
    form.value = props.initialData();
    errors.value = {};
    errorGeneral.value = null;
    emit('close');
}

async function guardar() {
    procesando.value = true;
    errors.value = {};
    errorGeneral.value = null;

    try {
        const { data } = await window.axios.post(route(props.routeName), form.value);
        emit('created', data);
        cerrar();
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(([campo, mensajes]) => [campo, mensajes[0]]),
            );
        } else {
            errorGeneral.value = 'No se pudo guardar. Intenta de nuevo.';
        }
    } finally {
        procesando.value = false;
    }
}
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="cerrar">
        <div class="card-header">
            <span class="card-title">{{ title }}</span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="guardar">
            <div class="card-body">
                <p v-if="hint" class="fs-sm text-muted mt-0 mb-3">{{ hint }}</p>
                <p v-if="errorGeneral" class="form-error mb-3">{{ errorGeneral }}</p>
                <slot :form="form" :errors="errors" />
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': procesando }"
                    :disabled="procesando">
                    <i v-if="procesando" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    {{ submitLabel }}
                </button>
            </div>
        </form>
    </Modal>
</template>
