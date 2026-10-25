<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';

defineOptions({ layout: MainDashboardLayout });

/**
 * Quién firma cada documento en nombre de la empresa. Las firmas son fijas
 * (FirmaDocumento::FIRMAS): solo se cambian nombre y cargo. Mientras no se
 * editen, firma el propietario. Si la misma persona firma las dos líneas de
 * la orden de trabajo, el PDF imprime una sola firma.
 */
const props = defineProps({
    /** `{ clave: { nombre, cargo, documento, funcion, detalle } }` */
    firmas: { type: Object, required: true },
    propietario: { type: String, default: null },
});

const form = useForm({
    firmas: Object.fromEntries(
        Object.entries(props.firmas).map(([clave, f]) => [clave, { nombre: f.nombre, cargo: f.cargo }]),
    ),
});

/** Las firmas agrupadas por documento, en el orden en que llegan. */
const documentos = computed(() => {
    const grupos = new Map();
    for (const [clave, firma] of Object.entries(props.firmas)) {
        if (!grupos.has(firma.documento)) grupos.set(firma.documento, []);
        grupos.get(firma.documento).push({ clave, ...firma });
    }
    return [...grupos.entries()].map(([documento, firmas]) => ({ documento, firmas }));
});

function usarPropietario(clave) {
    form.firmas[clave].nombre = props.propietario ?? '';
    form.firmas[clave].cargo = 'Propietario';
}

function guardar() {
    form.put(route('firmas-documento.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Firmas de documentos" />

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1 fw-semibold text-heading">
                <i class="fa-solid fa-signature text-primary"></i>
                Quién firma por la empresa
            </p>
            <p class="fs-sm text-muted mb-0">
                Estos nombres se imprimen en la línea de firma de cada PDF. Por defecto firma el propietario; cuando
                asigne un responsable de producción, cambie aquí su nombre y cargo. Si una misma persona firma las dos
                líneas de la orden de trabajo, el PDF muestra una sola firma con ambas funciones. Los cambios se aplican
                a los PDF que se generen desde ese momento.
            </p>
        </div>
    </div>

    <form @submit.prevent="guardar">
        <div v-for="grupo in documentos" :key="grupo.documento" class="card mb-4">
            <div class="card-header">
                <span class="card-title">{{ grupo.documento }}</span>
            </div>
            <div class="card-body">
                <div v-for="(firma, indice) in grupo.firmas" :key="firma.clave" class="row"
                    :class="{ 'mt-3': indice > 0 }">
                    <div class="col-12">
                        <p class="fs-sm text-muted mb-2">{{ firma.detalle }}</p>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" :for="`firma-${firma.clave}-nombre`">Nombre</label>
                            <input :id="`firma-${firma.clave}-nombre`" v-model="form.firmas[firma.clave].nombre"
                                type="text" class="form-control" maxlength="150" required
                                :class="{ 'is-invalid': form.errors[`firmas.${firma.clave}.nombre`] }" />
                            <p v-if="form.errors[`firmas.${firma.clave}.nombre`]" class="form-error">
                                {{ form.errors[`firmas.${firma.clave}.nombre`] }}
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" :for="`firma-${firma.clave}-cargo`">Cargo</label>
                            <input :id="`firma-${firma.clave}-cargo`" v-model="form.firmas[firma.clave].cargo"
                                type="text" class="form-control" maxlength="100" required
                                placeholder="Propietario, Jefe de producción…"
                                :class="{ 'is-invalid': form.errors[`firmas.${firma.clave}.cargo`] }" />
                            <p v-if="form.errors[`firmas.${firma.clave}.cargo`]" class="form-error">
                                {{ form.errors[`firmas.${firma.clave}.cargo`] }}
                            </p>
                        </div>
                    </div>
                    <div v-if="propietario" class="col-lg-2 flex items-end">
                        <button v-can="'firmas-documento.editar'" type="button"
                            class="btn btn-soft-secondary btn-sm mb-3" @click="usarPropietario(firma.clave)">
                            Usar propietario
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-can="'firmas-documento.editar'" class="flex justify-end">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <i class="fa-solid fa-floppy-disk"></i> Guardar firmas
            </button>
        </div>
    </form>
</template>
