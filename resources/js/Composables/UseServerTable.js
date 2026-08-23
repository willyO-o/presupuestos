import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Conecta un <DataTable> con una ruta Inertia: maneja el estado de los
 * filtros, dispara la busqueda (automatica o manual, a eleccion) y cambia
 * de pagina, todo mediante `router.get()` con `preserveState`/`preserveScroll`
 * para no perder el scroll ni recargar props que no cambiaron.
 *
 * Uso tipico en una pagina (ej. Pages/Clientes/Index.vue):
 *
 *   const table = useServerTable({
 *       url: route('clientes.index'),
 *       filters: { search: '', estado: '' },
 *       mode: 'manual',       // 'manual' (boton Buscar) | 'auto' (al escribir/cambiar)
 *       only: ['clientes'],   // partial reload: solo pide esa prop de vuelta
 *   });
 *
 *   <input v-model="table.filters.search" />
 *   <button @click="table.search">Buscar</button>          <!-- modo manual -->
 *
 *   <DataTable
 *       :items="clientes.data"
 *       :paginator="clientes"
 *       :loading="table.loading"
 *       @page-change="table.changePage"
 *   />
 *
 * En modo 'auto' no hace falta el botón: cualquier cambio en `table.filters`
 * dispara la busqueda solo, con debounce (para no golpear el servidor en
 * cada tecla). En modo 'manual' los filtros solo se aplican cuando se llama
 * a `table.search()` (por ejemplo desde un botón "Buscar" o un @keyup.enter).
 *
 * @param {object} options
 * @param {string} options.url - Ruta Inertia a visitar (normalmente `route('recurso.index')`).
 * @param {object} [options.filters] - Valores iniciales de los filtros (search, selects, ...).
 * @param {'manual'|'auto'} [options.mode] - 'manual': solo busca al llamar search(). 'auto': busca solo al cambiar los filtros (debounced).
 * @param {number} [options.debounce] - Milisegundos de espera en modo 'auto' antes de disparar la busqueda.
 * @param {string[]|null} [options.only] - Claves de props a pedir en la respuesta parcial de Inertia (`router.get(..., { only })`). `null` pide la pagina completa.
 * @param {boolean} [options.preserveScroll]
 * @param {boolean} [options.preserveState]
 * @returns {{ filters: object, loading: boolean, search: Function, changePage: Function, reset: Function }} Objeto reactive(): `loading` se lee directo (sin `.value`) tanto en script como en plantilla.
 */
export function useServerTable(options = {}) {
    const {
        url,
        filters = {},
        mode = 'manual',
        debounce = 400,
        only = null,
        preserveScroll = true,
        preserveState = true,
    } = options;

    if (!url) {
        throw new Error(
            'useServerTable: se requiere la opción "url" (ruta a visitar con Inertia).',
        );
    }

    const initialFilters = { ...filters };
    const filterState = reactive({ ...initialFilters });
    const loading = ref(false);

    let debounceTimer = null;

    /**
     * Visita `url` combinando los filtros actuales con los parametros extra
     * (normalmente `{ page }`), preservando estado/scroll para que la tabla
     * no "salte" ni pierda lo que el usuario tenia escrito en otros campos.
     */
    function visit(extraParams = {}) {
        loading.value = true;

        router.get(
            url,
            { ...filterState, ...extraParams },
            {
                preserveState,
                preserveScroll,
                replace: true,
                only: only ?? undefined,
                onFinish: () => {
                    loading.value = false;
                },
            },
        );
    }

    /** Dispara la búsqueda con los filtros actuales, siempre desde la página 1. */
    function search() {
        visit({ page: 1 });
    }

    /** Cambia de página conservando los filtros actuales. Pensado para `@page-change` del DataTable. */
    function changePage(page) {
        visit({ page });
    }

    /** Vuelve los filtros a su valor inicial y vuelve a consultar. */
    function reset() {
        Object.keys(filterState).forEach((key) => delete filterState[key]);
        Object.assign(filterState, initialFilters);
        search();
    }

    if (mode === 'auto') {
        // Al observar directamente un objeto reactivo, Vue activa modo deep
        // automaticamente: cualquier propiedad que cambie dispara el callback.
        watch(filterState, () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(search, debounce);
        });
    }

    // reactive() (no un objeto plano) es lo que permite escribir
    // `table.loading` directo en la plantilla (ej. :disabled="table.loading")
    // y que Vue lo desenvuelva solo: un `ref` dentro de un objeto plano NO se
    // desenvuelve al leerlo como propiedad anidada (`table.loading` daria el
    // Ref en si, siempre "truthy" -> el boton quedaria deshabilitado para
    // siempre). Dentro de un objeto reactive() si se desenvuelve.
    return reactive({
        filters: filterState,
        loading,
        search,
        changePage,
        reset,
    });
}
