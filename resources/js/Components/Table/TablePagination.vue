<script setup>
/**
 * Paginador con "saltos de rango": en vez de listar todas las páginas, muestra
 * los extremos (1 ... 100) y una ventana de páginas alrededor de la actual,
 * con "..." donde hay un salto. Ejemplo con currentPage=10, lastPage=100:
 *
 *   «  ‹  1  …  8  9  10  11  12  …  100  ›  »
 *
 * Acepta el paginador de Laravel tal cual (current_page/last_page/...) o su
 * version normalizada en camelCase — ver normalizePaginator() en DataTable.vue.
 */
import { computed } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        default: null,
    },
    from: {
        type: Number,
        default: null,
    },
    to: {
        type: Number,
        default: null,
    },
    // Cuantas paginas mostrar a cada lado de la actual.
    siblingCount: {
        type: Number,
        default: 2,
    },
    // Cuantas paginas mostrar pegadas al inicio/final (1, 2, 3 ... o solo 1).
    boundaryCount: {
        type: Number,
        default: 1,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['change']);

const pages = computed(() => buildPageRange(props.currentPage, props.lastPage, props.siblingCount, props.boundaryCount));

/**
 * Genera el arreglo de páginas a mostrar, con '...' como marcador de salto.
 * Un salto solo se muestra si hay mas de una pagina oculta entre dos numeros
 * visibles; si solo se oculta una, se muestra ese numero en su lugar (no
 * tiene sentido colapsar un solo elemento).
 * @param {number} current
 * @param {number} last
 * @param {number} siblings
 * @param {number} boundaries
 * @returns {(number|string)[]}
 */
function buildPageRange(current, last, siblings, boundaries) {
    if (last <= 1) {
        return [1];
    }

    const startPages = range(1, Math.min(boundaries, last));
    const endPages = range(Math.max(last - boundaries + 1, boundaries + 1), last);

    const siblingsStart = Math.max(
        Math.min(current - siblings, last - boundaries - siblings * 2 - 1),
        boundaries + 2,
    );
    const siblingsEnd = Math.min(
        Math.max(current + siblings, boundaries + siblings * 2 + 2),
        endPages.length > 0 ? endPages[0] - 2 : last - 1,
    );

    const items = [
        ...startPages,
        ...(siblingsStart > boundaries + 2 ? ['...'] : range(boundaries + 1, siblingsStart - 1)),
        ...range(siblingsStart, siblingsEnd),
        ...(siblingsEnd < last - boundaries - 1 ? ['...'] : range(siblingsEnd + 1, last - boundaries)),
        ...endPages,
    ];

    // Puede haber numeros duplicados cuando current esta cerca de los bordes;
    // conservamos solo la primera aparicion, en orden.
    const seen = new Set();
    return items.filter((item) => {
        if (item === '...') {
            return true;
        }
        if (seen.has(item)) {
            return false;
        }
        seen.add(item);
        return true;
    });
}

function range(start, end) {
    if (end < start) {
        return [];
    }
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
}

function go(page) {
    if (
        props.disabled ||
        page === '...' ||
        page < 1 ||
        page > props.lastPage ||
        page === props.currentPage
    ) {
        return;
    }
    emit('change', page);
}
</script>

<template>
    <nav v-if="lastPage > 1" class="pagination-wrap" aria-label="Paginación">
        <p v-if="total !== null" class="pagination-info">
            Mostrando {{ from }}–{{ to }} de {{ total }} resultados
        </p>

        <ul class="pagination">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button
                    type="button"
                    class="page-link"
                    :disabled="disabled || currentPage === 1"
                    aria-label="Primera página"
                    @click="go(1)"
                >
                    «
                </button>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button
                    type="button"
                    class="page-link"
                    :disabled="disabled || currentPage === 1"
                    aria-label="Página anterior"
                    @click="go(currentPage - 1)"
                >
                    ‹
                </button>
            </li>

            <li
                v-for="(page, index) in pages"
                :key="`${page}-${index}`"
                class="page-item"
                :class="{
                    active: page === currentPage,
                    disabled: page === '...',
                }"
            >
                <span v-if="page === '...'" class="page-link page-ellipsis">
                    …
                </span>
                <button
                    v-else
                    type="button"
                    class="page-link"
                    :disabled="disabled"
                    :aria-current="page === currentPage ? 'page' : undefined"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </li>

            <li
                class="page-item"
                :class="{ disabled: currentPage === lastPage }"
            >
                <button
                    type="button"
                    class="page-link"
                    :disabled="disabled || currentPage === lastPage"
                    aria-label="Página siguiente"
                    @click="go(currentPage + 1)"
                >
                    ›
                </button>
            </li>
            <li
                class="page-item"
                :class="{ disabled: currentPage === lastPage }"
            >
                <button
                    type="button"
                    class="page-link"
                    :disabled="disabled || currentPage === lastPage"
                    aria-label="Última página"
                    @click="go(lastPage)"
                >
                    »
                </button>
            </li>
        </ul>
    </nav>
</template>
