<script setup>
/**
 * Panel del dueño para el cotizador del sitio (/cotizador): ajustes
 * generales y, por producto, si se publica y de dónde sale su precio
 * (materiales, precio fijo o rangos de área). El botón "Probar" muestra lo
 * que vería el visitante ANTES de guardar.
 */
import { computed, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    productos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    ajustes: { type: Object, required: true },
    tiposProyecto: { type: Array, default: () => [] },
    iva: { type: Number, required: true },
});

const table = useServerTable({
    url: route('cotizador-web.index'),
    filters: {
        search: props.filters.search ?? '',
        publicado: props.filters.publicado ?? '',
    },
    mode: 'manual',
    only: ['productos', 'filters'],
});

const headers = [
    { label: 'Producto', key: 'nombre' },
    { label: 'Precio', key: 'modo_precio_web' },
    { label: 'Precio de referencia', key: 'precio_referencia', class: 'text-end', cellClass: 'text-end' },
    { label: 'Web', key: 'publicado', class: 'text-center', cellClass: 'text-center' },
];

const UNIDADES = { M2: 'm²', METRO_LINEAL: 'metro lineal', UNIDAD: 'unidad' };

const MODOS = {
    MATERIALES: {
        etiqueta: 'Según materiales',
        explicacion: 'Se calcula con el costo base o los materiales del producto, más el margen. Si sube un material, el precio web sube solo.',
    },
    PRECIO_FIJO: {
        etiqueta: 'Precio fijo',
        explicacion: 'Tú defines el precio de venta (sin IVA) por cada unidad de medida.',
    },
    RANGOS: {
        etiqueta: 'Rangos de área',
        explicacion: 'Un precio por m² distinto según el tamaño del trabajo. Solo para productos por m².',
    },
};

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function numero(value) {
    return Number(value ?? 0).toLocaleString('es-BO', { maximumFractionDigits: 4 });
}

function unidadDe(producto) {
    return UNIDADES[producto.unidad_medida] ?? 'unidad';
}

/* ── Ajustes generales ───────────────────────────────────────────────── */

const ajustesForm = useForm(() => ({
    tipo_proyecto: props.ajustes.tipo_proyecto ?? '',
    holgura: props.ajustes.holgura,
    vigencia_dias: props.ajustes.vigencia_dias,
}));

function guardarAjustes() {
    ajustesForm
        .transform((data) => ({ ...data, tipo_proyecto: data.tipo_proyecto || null }))
        .put(route('cotizador-web.ajustes'), { preserveScroll: true });
}

/* ── Modal de un producto ────────────────────────────────────────────── */

const showModal = ref(false);
const producto = ref(null);

const form = useForm(() => ({
    cotizable_web: 'NO',
    modo_precio_web: 'MATERIALES',
    precio_web: '',
    rangos: [],
}));

const prueba = reactive({ ancho: 1, alto: 1, cantidad: 1, cargando: false, resultado: null, error: null });

const pideMedidas = computed(() => producto.value?.requiere_medidas === 'SI');
const esPorM2 = computed(() => producto.value?.unidad_medida === 'M2');

function abrir(item) {
    producto.value = item;
    form.clearErrors();
    form.cotizable_web = item.cotizable_web;
    form.modo_precio_web = item.modo_precio_web;
    form.precio_web = item.precio_web ?? '';
    form.rangos = item.precios_web.length
        ? item.precios_web.map((r) => ({ area_hasta: r.area_hasta ?? '', precio_m2: r.precio_m2 }))
        : [{ area_hasta: 2, precio_m2: '' }, { area_hasta: '', precio_m2: '' }];
    Object.assign(prueba, { ancho: 1, alto: 1, cantidad: 1, resultado: null, error: null });
    showModal.value = true;
}

function cerrar() {
    showModal.value = false;
}

function agregarRango() {
    form.rangos.push({ area_hasta: '', precio_m2: '' });
}

function quitarRango(index) {
    form.rangos.splice(index, 1);
}

/** Texto "De X a Y m²" de cada rango, para que el dueño lo lea de corrido. */
function textoRango(index) {
    const desde = index === 0 ? 0 : Number(form.rangos[index - 1].area_hasta || 0);
    const hasta = form.rangos[index].area_hasta;

    return hasta === '' || hasta === null
        ? `Más de ${numero(desde)} m²`
        : `De ${numero(desde)} a ${numero(hasta)} m²`;
}

function guardar() {
    form.put(route('cotizador-web.productos.update', producto.value.id), {
        preserveScroll: true,
        onSuccess: () => cerrar(),
    });
}

async function probar() {
    prueba.cargando = true;
    prueba.error = null;
    prueba.resultado = null;

    try {
        const { data } = await window.axios.post(route('cotizador-web.productos.probar', producto.value.id), {
            modo_precio_web: form.modo_precio_web,
            precio_web: form.precio_web || null,
            rangos: form.rangos,
            ancho: pideMedidas.value ? prueba.ancho || null : null,
            alto: pideMedidas.value ? prueba.alto || null : null,
            cantidad: prueba.cantidad || 1,
        });
        prueba.resultado = data;
    } catch (error) {
        prueba.error = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'No se pudo calcular el precio.';
    } finally {
        prueba.cargando = false;
    }
}
</script>

<template>

    <Head title="Cotizador web" />

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1 fw-semibold text-heading">
                <i class="fa-solid fa-globe text-primary"></i>
                Lo que ve la gente en el cotizador del sitio
            </p>
            <p class="fs-sm text-muted mb-0">
                Elige qué productos se publican y de dónde sale su precio. El visitante ve un precio aproximado
                (con IVA y un rango ±), nunca los costos ni el margen.
            </p>
        </div>
    </div>

    <!-- Ajustes generales -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">Ajustes generales</span>
        </div>
        <div class="card-body">
            <form class="row" @submit.prevent="guardarAjustes">
                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="form-label" for="ajuste-tipo">Nivel de complejidad (modo "según materiales")</label>
                        <select id="ajuste-tipo" v-model="ajustesForm.tipo_proyecto" class="form-control"
                            :class="{ 'is-invalid': ajustesForm.errors.tipo_proyecto }">
                            <option value="">El primero activo</option>
                            <option v-for="tipo in tiposProyecto" :key="tipo.id" :value="tipo.nombre">
                                {{ tipo.nombre }} — ×{{ Number(tipo.factor_complejidad).toFixed(2) }} ·
                                margen {{ (Number(tipo.margen_minimo) * 100).toFixed(1) }} %
                            </option>
                        </select>
                        <p v-if="ajustesForm.errors.tipo_proyecto" class="form-error">
                            {{ ajustesForm.errors.tipo_proyecto }}
                        </p>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="ajuste-holgura">Rango aproximado (± %)</label>
                        <input id="ajuste-holgura" v-model="ajustesForm.holgura" v-decimal="2" type="text"
                            inputmode="decimal" class="form-control"
                            :class="{ 'is-invalid': ajustesForm.errors.holgura }" />
                        <p v-if="ajustesForm.errors.holgura" class="form-error">{{ ajustesForm.errors.holgura }}</p>
                        <p v-else class="fs-xs text-muted mt-1 mb-0">0 = muestra un precio exacto</p>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="ajuste-vigencia">Vigencia (días)</label>
                        <input id="ajuste-vigencia" v-model="ajustesForm.vigencia_dias" v-entero="1" type="text"
                            inputmode="numeric" class="form-control"
                            :class="{ 'is-invalid': ajustesForm.errors.vigencia_dias }" />
                        <p v-if="ajustesForm.errors.vigencia_dias" class="form-error">
                            {{ ajustesForm.errors.vigencia_dias }}
                        </p>
                    </div>
                </div>
                <div class="col-lg-2 flex items-end">
                    <div class="form-group w-full">
                        <button type="submit" class="btn btn-primary w-full" :disabled="ajustesForm.processing">
                            <i v-if="ajustesForm.processing" class="fa-solid fa-spinner fa-spin"></i>
                            <i v-else class="fa-solid fa-floppy-disk"></i>
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-6">
                    <label class="form-label" for="filter-search">Buscar</label>
                    <input id="filter-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="Nombre del producto..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="filter-publicado">En la web</label>
                    <select id="filter-publicado" v-model="table.filters.publicado" class="form-control">
                        <option value="">Todos</option>
                        <option value="SI">Marcados para publicar</option>
                        <option value="NO">No publicados</option>
                    </select>
                </div>
                <div class="col-lg-3 flex items-end gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="table.loading">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Buscar
                    </button>
                    <button type="button" class="btn btn-soft-secondary" :disabled="table.loading" @click="table.reset">
                        Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Productos</span>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="productos.data" :paginator="productos" :loading="table.loading"
                empty-text="No hay productos activos." @page-change="table.changePage">
                <template #cell-nombre="{ item }">
                    <span class="fw-semibold">{{ item.nombre }}</span>
                    <span class="d-block fs-xs text-muted">
                        {{ item.categoria_producto?.nombre ?? 'Sin categoría' }} · por {{ unidadDe(item) }}
                    </span>
                </template>

                <template #cell-modo_precio_web="{ item }">
                    {{ MODOS[item.modo_precio_web]?.etiqueta }}
                </template>

                <template #cell-precio_referencia="{ item }">
                    <template v-if="item.precio_referencia !== null">
                        <span class="fw-semibold">{{ money(item.precio_referencia) }}</span>
                        <span class="d-block fs-xs text-muted">por {{ unidadDe(item) }}, sin IVA</span>
                    </template>
                    <span v-else class="fs-xs text-muted">Sin precio</span>
                </template>

                <template #cell-publicado="{ item }">
                    <span v-if="item.publicado" class="badge badge-soft-success">Publicado</span>
                    <span v-else-if="item.cotizable_web === 'SI'" class="badge badge-soft-warning"
                        title="Está marcado, pero no tiene de dónde sacar el precio">
                        Falta precio
                    </span>
                    <span v-else class="badge badge-soft-secondary">No</span>
                </template>

                <template #actions="{ item }">
                    <button type="button" class="btn btn-sm btn-soft-primary" @click="abrir(item)">
                        <i class="fa-solid fa-sliders"></i>
                        Configurar
                    </button>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Modal de un producto -->
    <Modal :show="showModal" max-width="2xl" @close="cerrar">
        <template v-if="producto">
            <div class="card-header">
                <span class="card-title">{{ producto.nombre }} en el cotizador web</span>
                <button type="button" class="modal-close" aria-label="Cerrar" @click="cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form @submit.prevent="guardar">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="form-group">
                                <label class="form-label" for="web-publicar">¿Se publica?</label>
                                <select id="web-publicar" v-model="form.cotizable_web" class="form-control">
                                    <option value="SI">Sí, publicarlo</option>
                                    <option value="NO">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="form-group">
                                <label class="form-label" for="web-modo">¿De dónde sale el precio?</label>
                                <select id="web-modo" v-model="form.modo_precio_web" class="form-control"
                                    :class="{ 'is-invalid': form.errors.modo_precio_web }">
                                    <option v-for="(modo, valor) in MODOS" :key="valor" :value="valor"
                                        :disabled="valor === 'RANGOS' && !esPorM2">
                                        {{ modo.etiqueta }}
                                    </option>
                                </select>
                                <p v-if="form.errors.modo_precio_web" class="form-error">
                                    {{ form.errors.modo_precio_web }}
                                </p>
                                <p v-else class="fs-xs text-muted mt-1 mb-0">{{ MODOS[form.modo_precio_web].explicacion }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Según materiales: de dónde sale el costo -->
                    <div v-if="form.modo_precio_web === 'MATERIALES'" class="cotizador-web-detalle">
                        <p v-if="Number(producto.precio_base) > 0" class="mb-0 fs-sm">
                            Costo base: <strong>{{ money(producto.precio_base) }}</strong> por {{ unidadDe(producto) }}.
                            Se cambia en Productos.
                        </p>
                        <template v-else-if="producto.producto_materiales.length">
                            <p class="fs-sm fw-semibold mb-2">Materiales que usa (precio actual)</p>
                            <div v-for="linea in producto.producto_materiales" :key="linea.id"
                                class="cotizador-web-material">
                                <span>{{ linea.material?.nombre }}</span>
                                <span class="text-muted">
                                    {{ linea.formula ? linea.formula.nombre : `${numero(linea.cantidad_por_unidad)} por ${unidadDe(producto)}` }}
                                </span>
                                <span class="fw-semibold">
                                    {{ money(linea.material?.precio_unitario) }} / {{ linea.material?.unidad_medida }}
                                </span>
                            </div>
                            <Link :href="route('materiales.index')" class="btn btn-sm btn-soft-info mt-2">
                                <i class="fa-solid fa-pen"></i>
                                Cambiar precios de materiales
                            </Link>
                        </template>
                        <p v-else class="mb-0 fs-sm text-muted">
                            No tiene costo base ni materiales: elige precio fijo o rangos.
                        </p>
                    </div>

                    <!-- Precio fijo -->
                    <div v-else-if="form.modo_precio_web === 'PRECIO_FIJO'" class="form-group">
                        <label class="form-label" for="web-precio">
                            Precio de venta por {{ unidadDe(producto) }} (Bs, sin IVA)
                        </label>
                        <input id="web-precio" v-model="form.precio_web" v-decimal="2" type="text"
                            inputmode="decimal" class="form-control" :class="{ 'is-invalid': form.errors.precio_web }" />
                        <p v-if="form.errors.precio_web" class="form-error">{{ form.errors.precio_web }}</p>
                    </div>

                    <!-- Rangos de área -->
                    <div v-else class="cotizador-web-detalle">
                        <p class="fs-sm fw-semibold mb-2">Precio por m² según el tamaño del trabajo (sin IVA)</p>
                        <div v-for="(rango, index) in form.rangos" :key="index" class="cotizador-web-rango">
                            <span class="fs-sm">{{ textoRango(index) }}</span>
                            <div>
                                <input v-model="rango.area_hasta" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control" placeholder="Sin tope" :aria-label="`Tope del rango ${index + 1} en m²`"
                                    :class="{ 'is-invalid': form.errors[`rangos.${index}.area_hasta`] }" />
                                <p v-if="form.errors[`rangos.${index}.area_hasta`]" class="form-error">
                                    {{ form.errors[`rangos.${index}.area_hasta`] }}
                                </p>
                            </div>
                            <div>
                                <input v-model="rango.precio_m2" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control" placeholder="Bs por m²" :aria-label="`Precio por m² del rango ${index + 1}`"
                                    :class="{ 'is-invalid': form.errors[`rangos.${index}.precio_m2`] }" />
                                <p v-if="form.errors[`rangos.${index}.precio_m2`]" class="form-error">
                                    {{ form.errors[`rangos.${index}.precio_m2`] }}
                                </p>
                            </div>
                            <button type="button" class="btn btn-sm btn-icon btn-soft-danger" aria-label="Quitar rango"
                                :disabled="form.rangos.length === 1" @click="quitarRango(index)">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <p v-if="form.errors.rangos" class="form-error">{{ form.errors.rangos }}</p>
                        <button type="button" class="btn btn-sm btn-soft-primary mt-1" :disabled="form.rangos.length >= 10"
                            @click="agregarRango">
                            <i class="fa-solid fa-plus"></i>
                            Rango
                        </button>
                        <p class="fs-xs text-muted mt-2 mb-0">
                            Escribe el tope de cada rango en m². Deja vacío el último para "y más".
                        </p>
                    </div>

                    <!-- Probar antes de guardar -->
                    <div class="cotizador-web-prueba">
                        <p class="fs-sm fw-semibold mb-2">
                            <i class="fa-solid fa-flask text-primary"></i>
                            Probar: lo que vería el visitante
                        </p>
                        <div class="row">
                            <template v-if="pideMedidas">
                                <div class="col-lg-3 col-6">
                                    <label class="form-label" for="prueba-ancho">Ancho (m)</label>
                                    <input id="prueba-ancho" v-model="prueba.ancho" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" />
                                </div>
                                <div class="col-lg-3 col-6">
                                    <label class="form-label" for="prueba-alto">Alto (m)</label>
                                    <input id="prueba-alto" v-model="prueba.alto" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" />
                                </div>
                            </template>
                            <div class="col-lg-3 col-6">
                                <label class="form-label" for="prueba-cantidad">Cantidad</label>
                                <input id="prueba-cantidad" v-model="prueba.cantidad" v-entero="1" type="text"
                                    inputmode="numeric" class="form-control" />
                            </div>
                            <div class="col-lg-3 col-6 flex items-end">
                                <button type="button" class="btn btn-soft-info w-full" :disabled="prueba.cargando"
                                    @click="probar">
                                    <i v-if="prueba.cargando" class="fa-solid fa-spinner fa-spin"></i>
                                    <i v-else class="fa-solid fa-calculator"></i>
                                    Probar
                                </button>
                            </div>
                        </div>
                        <p v-if="prueba.error" class="form-error mt-2">{{ prueba.error }}</p>
                        <div v-if="prueba.resultado" class="margen-panel mt-3 mb-0">
                            <div class="margen-row">
                                <span>Precio por unidad (sin IVA)</span>
                                <span>{{ money(prueba.resultado.precio_unitario) }}</span>
                            </div>
                            <div class="margen-row">
                                <span>Total con IVA ({{ (iva * 100).toFixed(0) }} %)</span>
                                <span>{{ money(prueba.resultado.total) }}</span>
                            </div>
                            <div class="margen-row margen-row-destacada">
                                <span>Lo que ve el visitante</span>
                                <span>
                                    {{ money(prueba.resultado.estimado_min) }} – {{ money(prueba.resultado.estimado_max) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-secondary" @click="cerrar">Cancelar</button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                        <i v-else class="fa-solid fa-floppy-disk"></i>
                        Guardar
                    </button>
                </div>
            </form>
        </template>
    </Modal>
</template>
