<script setup>
/**
 * Rótulo de qué sucursales incluyen los números de esta pantalla.
 *
 * Existe porque un total acotado y uno de toda la empresa se ven EXACTAMENTE
 * igual. En un listado el acotado se nota (faltan filas); en un KPI o un
 * gráfico no hay con qué compararlo, así que un "Bs 12.400" filtrado se lee
 * como la facturación de la empresa entera. El rótulo es lo único que
 * distingue un dato de otro.
 *
 * Lee `auth.sucursales`, que comparte HandleInertiaRequests. El filtrado real
 * pasa en el servidor (App\Models\Concerns\AcotaPorSucursal); esto solo lo
 * cuenta.
 *
 * Uso:
 *   <EtiquetaAlcance />                          <!-- "Viendo: El Alto" -->
 *   <EtiquetaAlcance global texto="Inventario" /> <!-- dato sin sucursal -->
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    /**
     * El dato NO se acota por sucursal (inventario, compras, historial de
     * precios: esas tablas no tienen `sucursal_id`). Se rotula como global
     * para que no se confunda con el resto de la pantalla, que sí va acotada.
     */
    global: { type: Boolean, default: false },
    /** Qué es el dato global. Solo se usa junto con `global`. */
    texto: { type: String, default: 'Dato' },
});

const page = usePage();

const alcance = computed(() => page.props.auth?.sucursales ?? null);

const etiqueta = computed(() => {
    if (props.global) {
        return { texto: `${props.texto} · toda la empresa`, tono: 'badge-soft-secondary' };
    }

    if (!alcance.value || alcance.value.ve_todas) {
        return { texto: 'Todas las sucursales', tono: 'badge-soft-primary' };
    }

    const nombres = (alcance.value.visibles ?? []).map((s) => s.nombre);

    return nombres.length
        ? { texto: `Viendo: ${nombres.join(', ')}`, tono: 'badge-soft-info' }
        : { texto: 'Sin sucursales asignadas', tono: 'badge-soft-danger' };
});
</script>

<template>
    <span class="badge" :class="etiqueta.tono">{{ etiqueta.texto }}</span>
</template>
