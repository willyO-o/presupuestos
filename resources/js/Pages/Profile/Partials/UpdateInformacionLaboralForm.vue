<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    // Ficha de empleado vinculada a esta cuenta (con sucursal/area
    // cargadas), o null si la cuenta no tiene ficha.
    empleado: {
        type: Object,
        default: null,
    },
});

/**
 * "2024-03-15" o "2024-03-15T00:00:00.000000Z" → "15-03-2024". Se toman
 * los 10 primeros caracteres tal cual (sin pasar por Date) para que la
 * zona horaria del navegador no corra el dia.
 */
function aFechaCorta(valor) {
    if (!valor) {
        return '';
    }

    const [anio, mes, dia] = String(valor).slice(0, 10).split('-');

    return `${dia}-${mes}-${anio}`;
}

// Solo los campos que el empleado puede corregir. Cargo, sucursal, area y
// estado no viajan: definen el rol y el alcance por sucursal de la cuenta y
// se administran desde el modulo Empleados (el backend los ignora igual,
// ver ProfileEmpleadoUpdateRequest).
const form = useForm(() => ({
    nombres: props.empleado?.nombres ?? '',
    paterno: props.empleado?.paterno ?? '',
    materno: props.empleado?.materno ?? '',
    ci: props.empleado?.ci ?? '',
    telefono: props.empleado?.telefono ?? '',
    fecha_ingreso: aFechaCorta(props.empleado?.fecha_ingreso),
}));

/**
 * Mascara dd-mm-aaaa: deja solo digitos y coloca los guiones al escribir,
 * asi el usuario nunca ve otro formato que el que se guarda.
 */
function onFechaInput(event) {
    const digitos = event.target.value.replace(/\D/g, '').slice(0, 8);
    const valor = [digitos.slice(0, 2), digitos.slice(2, 4), digitos.slice(4, 8)].filter(Boolean).join('-');

    form.fecha_ingreso = valor;
    event.target.value = valor;
}

const antiguedad = computed(() => {
    const coincide = /^(\d{2})-(\d{2})-(\d{4})$/.exec(form.fecha_ingreso);

    if (!coincide) {
        return null;
    }

    const [, dia, mes, anio] = coincide.map(Number);
    const hoy = new Date();
    let meses = (hoy.getFullYear() - anio) * 12 + (hoy.getMonth() + 1 - mes);

    if (hoy.getDate() < dia) {
        meses -= 1;
    }

    if (meses < 0 || mes < 1 || mes > 12) {
        return null;
    }

    if (meses === 0) {
        return 'Menos de un mes en la empresa';
    }

    const anios = Math.floor(meses / 12);
    const resto = meses % 12;
    const partes = [];

    if (anios) {
        partes.push(`${anios} ${anios === 1 ? 'año' : 'años'}`);
    }

    if (resto) {
        partes.push(`${resto} ${resto === 1 ? 'mes' : 'meses'}`);
    }

    return `${partes.join(' y ')} en la empresa`;
});

const asignados = computed(() => [
    { label: 'Cargo', valor: props.empleado?.cargo || '—' },
    { label: 'Sucursal', valor: props.empleado?.sucursal?.nombre ?? '—' },
    { label: 'Área', valor: props.empleado?.area?.nombre ?? '—' },
]);

const estaActivo = computed(() => props.empleado?.estado === 'ACTIVO');

function submit() {
    form.patch(route('profile.empleado.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="profile-section-divider">
        <header class="mb-4">
            <h3 class="card-title">Información laboral</h3>
            <p v-if="empleado" class="fs-sm text-muted mt-1">
                Mantén al día tus datos de identificación y contacto.
            </p>
        </header>

        <p v-if="!empleado" class="fs-sm text-muted">
            Tu cuenta todavía no está vinculada a una ficha de empleado.
        </p>

        <form v-else @submit.prevent="submit">
            <div class="row">
                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-nombres">Nombres</label>
                        <input id="perfil-nombres" v-model="form.nombres" v-max-length="255" type="text"
                            class="form-control" :class="{ 'is-invalid': form.errors.nombres }" required
                            autocomplete="given-name" />
                        <p v-if="form.errors.nombres" class="form-error">{{ form.errors.nombres }}</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-paterno">Apellido paterno</label>
                        <input id="perfil-paterno" v-model="form.paterno" v-max-length="255" type="text"
                            class="form-control" :class="{ 'is-invalid': form.errors.paterno }"
                            autocomplete="family-name" />
                        <p v-if="form.errors.paterno" class="form-error">{{ form.errors.paterno }}</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-materno">Apellido materno</label>
                        <input id="perfil-materno" v-model="form.materno" v-max-length="255" type="text"
                            class="form-control" :class="{ 'is-invalid': form.errors.materno }" />
                        <p v-if="form.errors.materno" class="form-error">{{ form.errors.materno }}</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-ci">CI</label>
                        <input id="perfil-ci" v-model="form.ci" v-max-length="255" type="text" class="form-control"
                            :class="{ 'is-invalid': form.errors.ci }" required />
                        <p v-if="form.errors.ci" class="form-error">{{ form.errors.ci }}</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-telefono">Teléfono</label>
                        <input id="perfil-telefono" v-model="form.telefono" v-max-length="25" type="tel"
                            class="form-control" :class="{ 'is-invalid': form.errors.telefono }"
                            autocomplete="tel" />
                        <p v-if="form.errors.telefono" class="form-error">{{ form.errors.telefono }}</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="perfil-fecha-ingreso">Fecha de ingreso</label>
                        <input id="perfil-fecha-ingreso" :value="form.fecha_ingreso" type="text" inputmode="numeric"
                            maxlength="10" placeholder="dd-mm-aaaa" class="form-control tabular-nums"
                            :class="{ 'is-invalid': form.errors.fecha_ingreso }" required autocomplete="off"
                            aria-describedby="perfil-fecha-ingreso-ayuda" @input="onFechaInput" />
                        <p v-if="form.errors.fecha_ingreso" class="form-error">{{ form.errors.fecha_ingreso }}</p>
                        <p v-else id="perfil-fecha-ingreso-ayuda" class="profile-field-hint">
                            {{ antiguedad ?? 'Formato dd-mm-aaaa' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="profile-locked">
                <div class="profile-locked-head">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>Asignado por administración</span>
                </div>

                <dl class="profile-locked-grid">
                    <div v-for="item in asignados" :key="item.label">
                        <dt class="profile-info-label">{{ item.label }}</dt>
                        <dd class="profile-info-value">{{ item.valor }}</dd>
                    </div>

                    <div>
                        <dt class="profile-info-label">Estado</dt>
                        <dd>
                            <span class="badge" :class="estaActivo ? 'badge-soft-success' : 'badge-soft-danger'">
                                {{ estaActivo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <p class="profile-locked-note">
                    Si alguno de estos datos no es correcto, pide el cambio a Recursos Humanos.
                </p>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing || !form.isDirty">
                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-floppy-disk"></i>
                    Guardar información laboral
                </button>

                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <span v-if="form.recentlySuccessful" class="fs-sm text-muted">Guardado.</span>
                </Transition>
            </div>
        </form>
    </section>
</template>
