<script setup>
/**
 * Tabla generica reutilizable (Bootstrap-style, sobre `.table-dashboard` de
 * app.css) con cabeceras flexibles, columnas personalizables via slots,
 * escape hatch para tablas complejas y paginador con saltos de rango.
 *
 * ── Cabeceras (prop `headers`) ──────────────────────────────────────────
 * Cada elemento acepta 2 formatos:
 *   - string: contenido crudo del <th> (se renderiza con v-html, admite
 *     HTML con sus propias clases), ej: '<span class="text-center d-block">Nombre</span>'
 *   - objeto: { label, key, class, cellClass }
 *       label     -> texto del <th> (texto plano, se escapa solo)
 *       key       -> propiedad a leer de cada fila (item[key]); si se omite
 *                    y las filas son arreglos, se usa el indice de columna
 *       class     -> clases del <th>
 *       cellClass -> clases del <td> generado para esa columna
 *
 * ── Filas (prop `items`) ────────────────────────────────────────────────
 * Cada fila puede ser un objeto (leido por header.key) o un arreglo (leido
 * por posicion). Cada columna puede sobreescribirse con un slot dinamico
 * `#cell-<key>="{ item, value, index }"` donde <key> es el `key` del header
 * o, si no tiene, su indice (0, 1, 2...) — util quando se combinan imagenes
 * y texto con flex/grid dentro de una celda.
 *
 * ── Columna de acciones ─────────────────────────────────────────────────
 * Si se usa el slot `#actions="{ item, index }"`, se agrega automaticamente
 * una ultima columna (no hace falta declararla en `headers`).
 *
 * ── Escape hatch para tablas muy complejas ──────────────────────────────
 * El slot `#tbody="{ items, headers }"` reemplaza TODO el tbody generado
 * automaticamente, para casos donde ni los slots por columna alcanzan
 * (rowspans, agrupaciones, celdas con su propio grid interno, etc.). El
 * <thead> se sigue generando normal. IMPORTANTE: el contenido del slot debe
 * incluir su propia etiqueta <tbody>...</tbody> (el componente no la pone).
 *
 * ── Paginacion ───────────────────────────────────────────────────────────
 * La prop `paginator` acepta el paginador de Laravel tal cual (paginate())
 * o su version normalizada en camelCase. Emite `@page-change="page"`.
 *
 * ── Clases de la tabla ───────────────────────────────────────────────────
 * La prop `tableClass` agrega clases al <table> ademas de `.table-dashboard`
 * (no la reemplaza), ej: `table-class="table-sm"` para una version compacta
 * (menos padding/tipografia mas chica, ver app.css seccion "6. TABLE").
 *
 * ── Loader ───────────────────────────────────────────────────────────────
 * Mientras `loading` es true se atenua la tabla y se superpone un loader
 * centrado (3 puntos con los colores de marca, `--c-primary`/`--c-secondary`).
 * Para uno personalizado, usa el slot `#loader` (reemplaza los 3 puntos por
 * completo, ej. un spinner o texto propio).
 *
 * ── Ejemplo minimo ───────────────────────────────────────────────────────
 *   <DataTable
 *       :headers="[{ label: 'Nombre', key: 'name' }, { label: 'Correo', key: 'email' }]"
 *       :items="usuarios.data"
 *       :paginator="usuarios"
 *       :loading="table.loading"
 *       @page-change="table.changePage"
 *   >
 *       <template #cell-name="{ item }">
 *           <div class="d-flex align-items-center gap-2">
 *               <img :src="item.avatar" class="article-thumb" />
 *               <span class="article-title">{{ item.name }}</span>
 *           </div>
 *       </template>
 *
 *       <template #actions="{ item }">
 *           <button class="btn btn-icon btn-soft-primary" @click="edit(item)">
 *               <i class="fa-solid fa-pen"></i>
 *           </button>
 *       </template>
 *   </DataTable>
 */
import { computed, useSlots } from 'vue';
import TablePagination from './TablePagination.vue';

const props = defineProps({
    headers: {
        type: Array,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    // Nombre de propiedad (string) o funcion (item, index) => clave unica,
    // usado como :key de cada <tr>. Por defecto usa 'id' si existe, si no
    // el indice de la fila.
    rowKey: {
        type: [String, Function],
        default: 'id',
    },
    // Paginador de Laravel (paginate()) o su version normalizada. `null`
    // oculta el paginador por completo (tabla sin paginacion server-side).
    paginator: {
        type: Object,
        default: null,
    },
    // Atenua la tabla y desactiva la paginacion mientras hay una peticion
    // en curso (ver useServerTable().loading).
    loading: {
        type: Boolean,
        default: false,
    },
    emptyText: {
        type: String,
        default: 'No se encontraron resultados.',
    },
    actionsLabel: {
        type: String,
        default: 'Acciones',
    },
    actionsClass: {
        type: String,
        default: 'text-end ',
    },
    siblingCount: {
        type: Number,
        default: 2,
    },
    // Clases extra para el <table>, ademas de `.table-dashboard` (se
    // combinan, no la reemplazan). Acepta cualquier formato de :class
    // (string, array u objeto), ej: 'table-sm' para una version compacta.
    tableClass: {
        type: [String, Array, Object],
        default: '',
    },
});

const emit = defineEmits(['page-change']);

const slots = useSlots();

const hasActionsColumn = computed(() => !!slots.actions);

/**
 * Normaliza cada header a { label, html, key, class, cellClass } para que
 * el template no tenga que preguntar "es string u objeto" en cada v-for.
 */
const normalizedHeaders = computed(() =>
    props.headers.map((header, index) => {
        if (typeof header === 'string') {
            return {
                html: header,
                label: null,
                key: index,
                class: '',
                cellClass: '',
            };
        }

        return {
            html: null,
            label: header.label ?? '',
            key: header.key ?? index,
            class: header.class ?? '',
            cellClass: header.cellClass ?? '',
        };
    }),
);

const paginatorNormalized = computed(() => normalizePaginator(props.paginator));

/**
 * Acepta tanto el paginador crudo de Laravel (snake_case) como una version
 * ya normalizada en camelCase, para no obligar al backend/pagina a mapear
 * los campos a mano.
 */
function normalizePaginator(paginator) {
    if (!paginator) {
        return null;
    }

    return {
        currentPage: paginator.currentPage ?? paginator.current_page ?? 1,
        lastPage: paginator.lastPage ?? paginator.last_page ?? 1,
        total: paginator.total ?? null,
        from: paginator.from ?? null,
        to: paginator.to ?? null,
    };
}

function cellValue(row, header) {
    return row[header.key];
}

function resolveRowKey(row, index) {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row, index);
    }
    if (row && typeof row === 'object' && props.rowKey in row) {
        return row[props.rowKey];
    }
    return index;
}
</script>

<template>
    <div class="table-wrap">
        <div class="table-responsive" :class="{ 'table-loading': loading }">
            <table class="table-dashboard" :class="tableClass">
                <thead>
                    <tr>
                        <th
                            v-for="header in normalizedHeaders"
                            :key="header.key"
                            :class="header.class"
                        >
                            <span v-if="header.html" v-html="header.html"></span>
                            <template v-else>{{ header.label }}</template>
                        </th>
                        <th v-if="hasActionsColumn" :class="actionsClass">
                            {{ actionsLabel }}
                        </th>
                    </tr>
                </thead>

                <!-- Escape hatch: control total sobre el tbody para tablas complejas -->
                <slot
                    v-if="$slots.tbody"
                    name="tbody"
                    :items="items"
                    :headers="normalizedHeaders"
                />

                <tbody v-else-if="items.length">
                    <tr
                        v-for="(item, index) in items"
                        :key="resolveRowKey(item, index)"
                    >
                        <td
                            v-for="header in normalizedHeaders"
                            :key="header.key"
                            :class="header.cellClass"
                        >
                            <slot
                                :name="`cell-${header.key}`"
                                :item="item"
                                :index="index"
                                :value="cellValue(item, header)"
                            >
                                {{ cellValue(item, header) }}
                            </slot>
                        </td>
                        <td v-if="hasActionsColumn" :class="actionsClass">
                            <slot name="actions" :item="item" :index="index" />
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td
                            class="table-empty p-10 " colspan="100%"
                        >
                            {{ emptyText }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Loader por defecto (3 puntos, colores de marca) mientras `loading`
             es true. Se puede reemplazar por completo con el slot #loader. -->
        <div v-if="loading" class="table-loading-overlay">
            <slot name="loader">
                <div class="table-loading-dots">
                    <span class="table-loading-dot"></span>
                    <span class="table-loading-dot"></span>
                    <span class="table-loading-dot"></span>
                </div>
            </slot>
        </div>
    </div>

    <TablePagination
        v-if="paginatorNormalized"
        :current-page="paginatorNormalized.currentPage"
        :last-page="paginatorNormalized.lastPage"
        :total="paginatorNormalized.total"
        :from="paginatorNormalized.from"
        :to="paginatorNormalized.to"
        :sibling-count="siblingCount"
        :disabled="loading"
        @change="emit('page-change', $event)"
    />
</template>
