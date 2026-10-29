<script setup>
import { moneyCorto, numero, porcentaje } from '@/Utils/FormatoReporte.js';

/**
 * Cuerpo de la pregunta 11 ("¿Qué acciones conviene revisar primero?") de
 * Preguntas para decidir: la tabla de acciones —cada una con qué representa
 * su monto y un enlace a la pregunta de la que sale— y los cuadros de lectura
 * rápida de los escenarios (pregunta 9) y los cobros (pregunta 10).
 *
 * Las cifras las arma `PreguntasNegocioService`; acá solo se maqueta.
 */
defineProps({
    /** La pregunta 11, con sus `priorizadas` (titulo, concepto, tipo, impacto, origen, numero). */
    pregunta: { type: Object, required: true },
    /** La pregunta 9 numerada, o null si no tiene `cifras`. */
    escenario: { type: Object, default: null },
    /** La pregunta 10 numerada, o null si no tiene `cifras`. */
    cobros: { type: Object, default: null },
});

const emit = defineEmits(['abrir']);

/** Color del rótulo "Qué representa el monto", por tipo de monto (PreguntasNegocioService::MONTOS). */
const TONO_MONTO = {
    observado: 'badge-soft-warning',
    riesgo: 'badge-soft-danger',
    estimado: 'badge-soft-success',
    diferencia: 'badge-soft-info',
};
</script>

<template>
    <div class="acciones-cuerpo">
        <section v-if="pregunta.priorizadas?.length" class="acciones-caja">
            <div class="table-responsive">
                <table class="table-dashboard table-sm acciones-tabla">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">Orden</th>
                            <th scope="col">Acción sugerida</th>
                            <th scope="col">Qué representa el monto</th>
                            <th scope="col" class="text-end">Monto</th>
                            <th scope="col">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(accion, i) in pregunta.priorizadas" :key="i">
                            <td class="text-center"><span class="decision-orden">{{ i + 1 }}</span></td>
                            <td class="acciones-titulo" :title="accion.texto">{{ accion.titulo ?? accion.texto }}</td>
                            <td>
                                <span class="badge" :class="TONO_MONTO[accion.tipo] ?? 'badge-soft-secondary'">
                                    {{ accion.concepto ?? 'Monto involucrado' }}
                                </span>
                            </td>
                            <td class="text-end acciones-monto">{{ moneyCorto(accion.impacto) }}</td>
                            <td>
                                <button type="button" class="acciones-ver" @click="emit('abrir', accion.origen)">
                                    Ver pregunta {{ accion.numero }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="acciones-nota">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Estimaciones sujetas a mantener las condiciones del análisis.
            </p>
        </section>

        <div v-if="escenario || cobros" class="acciones-lecturas">
            <section v-if="escenario" class="acciones-lectura" aria-labelledby="lectura-escenarios">
                <header class="acciones-lectura-cabecera">
                    <span class="acciones-lectura-icono"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span>
                    <h3 id="lectura-escenarios">Escenarios: ¿qué pasa si suben los materiales?</h3>
                </header>
                <div class="acciones-escenario">
                    <p>
                        <strong>+{{ numero(escenario.cifras.subida, 0) }} %</strong>
                        <span>en materiales</span>
                    </p>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <p>
                        <span>Margen:</span>
                        <strong>
                            {{ porcentaje(escenario.cifras.margen_hoy) }}
                            <span aria-hidden="true">→</span><span class="sr-only">pasaría a</span>
                            {{ porcentaje(escenario.cifras.margen) }}
                        </strong>
                    </p>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <p>
                        <span>Reducción estimada:</span>
                        <strong>{{ moneyCorto(escenario.cifras.reduccion) }}</strong>
                    </p>
                </div>
                <footer class="acciones-lectura-pie">
                    <p><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Es una simulación; no es una pérdida ya ocurrida.</p>
                    <button type="button" class="acciones-ver" @click="emit('abrir', escenario.clave)">
                        Ver pregunta {{ escenario.numero }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </footer>
            </section>

            <section v-if="cobros" class="acciones-lectura" aria-labelledby="lectura-cobros">
                <header class="acciones-lectura-cabecera">
                    <span class="acciones-lectura-icono"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <h3 id="lectura-cobros">Cobros: ¿cuánto falta recibir?</h3>
                </header>
                <div class="acciones-cobros">
                    <strong class="acciones-cobros-monto">{{ moneyCorto(cobros.cifras.por_cobrar) }}</strong>
                    <p>
                        {{ numero(cobros.cifras.pedidos, 0) }} {{ cobros.cifras.pedidos === 1 ? 'pedido' : 'pedidos' }} con saldo
                        <span aria-hidden="true">·</span> {{ porcentaje(cobros.cifras.cobrado) }} cobrado
                    </p>
                    <p class="acciones-cobros-antiguo">
                        Más de {{ cobros.cifras.dias }} días desde el pedido: <strong>{{ moneyCorto(cobros.cifras.antiguo) }}</strong>
                    </p>
                </div>
                <footer class="acciones-lectura-pie">
                    <p><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Antigüedad desde el pedido, no necesariamente atraso.</p>
                    <button type="button" class="acciones-ver" @click="emit('abrir', cobros.clave)">
                        Ver pregunta {{ cobros.numero }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>
