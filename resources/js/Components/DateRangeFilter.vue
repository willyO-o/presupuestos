<script setup>
/**
 * Filtro de rango de fechas reutilizable para cualquier pantalla que necesite
 * acotar por "fecha desde / hasta" (reportes, índices de recursos). Envuelve
 * `daterange-picker-vue3` con:
 *
 *  - Presets en español (Hoy, Ayer, Últimos 3/7 días, Esta semana desde el
 *    lunes, Este mes, Últimos 3/6 meses, Este año, Año pasado) + rango
 *    personalizado.
 *  - En el rango personalizado, una franja "Desde [mes][año] · Hasta
 *    [mes][año]" sobre los calendarios: el paquete solo avanza de a un mes
 *    con las flechas, y llegar a un año anterior costaba decenas de clics.
 *    Elegir mes y año fija el rango por meses completos y mueve el
 *    calendario ahí; los días se siguen afinando con clic.
 *  - Dos v-model en formato 'YYYY-MM-DD' (sólo fecha, sin horas) — el mismo
 *    formato que esperan los filtros whereDate/whereBetween del backend.
 *  - Botón opcional de "limpiar" para las páginas de listado.
 *
 * Todo el estilado (incluido el modo oscuro) vive en `resources/css/app.css`
 * sección "29. FILTRO DE RANGO DE FECHAS" — este componente no lleva <style>
 * (ver skill xtrapubli-design-system, Rule 3).
 *
 * Uso:
 *   <DateRangeFilter v-model:fecha-desde="filters.fecha_desde"
 *       v-model:fecha-hasta="filters.fecha_hasta" />
 *
 *   <!-- en un listado, con botón de limpiar y default -->
 *   <DateRangeFilter v-model:fecha-desde="table.filters.desde"
 *       v-model:fecha-hasta="table.filters.hasta"
 *       label="Emitidas entre" default-range="Este mes" clearable />
 */
import { ref, shallowRef, computed, watch, onMounted, markRaw } from 'vue'

// El paquete pesa ~80 KB y no se usa hasta que se abre el filtro, así que se
// carga con import() dinámico (mismo patrón que ECharts en BaseChart.vue):
// queda en su propio chunk y no engorda ni app.js ni la página que lo usa.
// Mientras carga se muestra un esqueleto no interactivo con el valor actual.
const DateRangePicker = shallowRef(null)

// El paquete sólo publica un build UMD (sin campo "module" en package.json) y,
// con import() dinámico, el namespace del módulo viene CONGELADO en producción
// (igual que con "import * as"): Vue no puede escribirle `inheritAttrs` al
// normalizarlo -> "TypeError: Cannot assign to property 'inheritAttrs' of
// [object Module]" (rompe sólo en el build, nunca en `npm run dev`). Por eso se
// desenvuelve hasta el componente real. markRaw() evita además que Vue intente
// volverlo reactivo.
function desenvolverComponente(mod) {
    let actual = mod
    while (actual && actual.default && actual.default !== actual && !actual.render && !actual.setup) {
        actual = actual.default
    }
    return actual
}

const props = defineProps({
    fechaDesde: { type: String, default: '' }, // 'YYYY-MM-DD' o ''
    fechaHasta: { type: String, default: '' },
    label: { type: String, default: 'Rango de fechas' },
    placeholder: { type: String, default: 'Selecciona un rango...' },
    opens: { type: String, default: 'right' }, // 'left' | 'right' | 'center' | 'inline'
    // Clave exacta de "ranges" (más abajo) a preseleccionar cuando el padre
    // todavía no trae fechaDesde/fechaHasta (primera carga sin filtros en la
    // URL). Ej. "Este mes". Vacío = sin selección por defecto.
    defaultRange: { type: String, default: '' },
    // Muestra una "x" para vaciar el rango (útil en filtros de listados).
    clearable: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    // Teletransporta el popup a <body>: úsalo si el filtro vive dentro de un
    // contenedor con overflow/z-index que recorta el calendario (modales,
    // .table-responsive, etc.).
    appendToBody: { type: Boolean, default: false },
    // Topes del calendario, en 'YYYY-MM-DD'. Ej. maxDate = hoy para no dejar
    // elegir fechas futuras en un reporte.
    minDate: { type: String, default: null },
    maxDate: { type: String, default: null },
})

const emit = defineEmits(['update:fechaDesde', 'update:fechaHasta'])

/* ------------------------------------------------------------------ */
/*  Helpers de fecha (día calendario, sin horas/minutos)               */
/* ------------------------------------------------------------------ */
function inicioDelDia(fecha) {
    const d = new Date(fecha)
    d.setHours(0, 0, 0, 0)
    return d
}

function finDelDia(fecha) {
    const d = new Date(fecha)
    d.setHours(23, 59, 59, 999)
    return d
}

function sumarDias(fecha, dias) {
    const d = new Date(fecha)
    d.setDate(d.getDate() + dias)
    return d
}

function sumarMeses(fecha, meses) {
    const d = new Date(fecha)
    d.setMonth(d.getMonth() + meses)
    return d
}

// Lunes de la semana de "fecha" (getDay(): 0 domingo ... 6 sábado).
function inicioSemanaLunes(fecha) {
    const dia = fecha.getDay()
    const diff = dia === 0 ? -6 : 1 - dia
    return inicioDelDia(sumarDias(fecha, diff))
}

// 'YYYY-MM-DD' en hora local (evita el corrimiento de día de toISOString(),
// que convierte a UTC).
function aYmd(fecha) {
    if (!fecha) return ''
    const d = new Date(fecha)
    if (isNaN(d)) return ''
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const dia = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${dia}`
}

// props.fechaDesde/fechaHasta ya vienen en 'YYYY-MM-DD' (salida de aYmd) —
// se reordena el string directo, sin pasar por Date. Antes hacía
// "new Date('YYYY-MM-DD')" que JS interpreta como medianoche UTC; al leerlo
// de vuelta con getters locales, en un huso horario negativo (ej. Bolivia
// UTC-4) el día se corría uno hacia atrás (mostraba el 31 del mes/año
// anterior en vez del 1 real).
function aTextoDisplay(ymd) {
    if (!ymd) return ''
    const partes = String(ymd).split('-')
    if (partes.length !== 3) return ''
    const [y, m, d] = partes
    return `${d}/${m}/${y}`
}

// 'YYYY-MM-DD' -> Date en hora LOCAL (a diferencia de "new Date(str)", que
// interpreta ese formato como UTC). Necesario para que, si la fecha
// coincide con uno de los "ranges" de abajo, el picker lo marque como
// activo (compara instantes exactos, no sólo el día calendario).
function desdeYmd(str, finDia = false) {
    if (!str) return null
    const partes = String(str).split('-').map(Number)
    if (partes.length !== 3 || partes.some((n) => Number.isNaN(n))) return null
    const [y, m, d] = partes
    const fecha = new Date(y, m - 1, d)
    return finDia ? finDelDia(fecha) : inicioDelDia(fecha)
}

/* ------------------------------------------------------------------ */
/*  Rangos predefinidos                                                */
/* ------------------------------------------------------------------ */
const hoy = new Date()
const inicioHoy = inicioDelDia(hoy)
const finHoy = finDelDia(hoy)
const ayer = sumarDias(hoy, -1)

const ranges = {
    'Hoy': [inicioHoy, finHoy],
    'Ayer': [inicioDelDia(ayer), finDelDia(ayer)],
    'Últimos 3 días': [inicioDelDia(sumarDias(hoy, -2)), finHoy],
    'Últimos 7 días': [inicioDelDia(sumarDias(hoy, -6)), finHoy],
    'Esta semana': [inicioSemanaLunes(hoy), finHoy],
    'Este mes': [inicioDelDia(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), finHoy],
    'Últimos 3 meses': [inicioDelDia(sumarMeses(hoy, -3)), finHoy],
    'Últimos 6 meses': [inicioDelDia(sumarMeses(hoy, -6)), finHoy],
    'Este año': [inicioDelDia(new Date(hoy.getFullYear(), 0, 1)), finHoy],
    'Año pasado': [
        inicioDelDia(new Date(hoy.getFullYear() - 1, 0, 1)),
        finDelDia(new Date(hoy.getFullYear() - 1, 11, 31)),
    ],
}

/* ------------------------------------------------------------------ */
/*  Localización en español (daysOfWeek va Dom->Sáb; el propio          */
/*  componente lo reordena según firstDay para que la grilla arranque   */
/*  en lunes)                                                          */
/* ------------------------------------------------------------------ */
const localeEs = {
    direction: 'ltr',
    format: 'dd/mm/yyyy',
    separator: ' - ',
    applyLabel: 'Aplicar',
    cancelLabel: 'Cancelar',
    weekLabel: 'Sem',
    customRangeLabel: 'Rango personalizado',
    daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
    monthNames: [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ],
    firstDay: 1, // la semana empieza en lunes
}

/* ------------------------------------------------------------------ */
/*  v-model interno del picker, sincronizado con los props             */
/* ------------------------------------------------------------------ */
// Si el padre no trae fechas todavía y hay defaultRange, se preselecciona
// usando los MISMOS objetos Date del preset (no reconstruidos desde texto)
// para que quede marcado como activo en la lista de rangos desde el primer
// render.
function rangoInicial() {
    if (props.fechaDesde || props.fechaHasta) {
        return { startDate: desdeYmd(props.fechaDesde), endDate: desdeYmd(props.fechaHasta, true) }
    }
    if (props.defaultRange && ranges[props.defaultRange]) {
        const [startDate, endDate] = ranges[props.defaultRange]
        return { startDate, endDate }
    }
    return { startDate: null, endDate: null }
}

const rango = ref(rangoInicial())

// Re-sincroniza cuando el padre cambia los props desde afuera (ej. botón
// "Limpiar filtros" de la página); evita loops comparando contra el valor
// actual del picker antes de reemplazarlo.
watch(() => [props.fechaDesde, props.fechaHasta], ([desde, hasta]) => {
    if (aYmd(rango.value.startDate) === (desde || '') && aYmd(rango.value.endDate) === (hasta || '')) {
        return
    }
    rango.value = { startDate: desdeYmd(desde), endDate: desdeYmd(hasta, true) }
})

function onUpdate({ startDate, endDate }) {
    emit('update:fechaDesde', aYmd(startDate))
    emit('update:fechaHasta', aYmd(endDate))
}

function limpiar() {
    emit('update:fechaDesde', '')
    emit('update:fechaHasta', '')
}

/* ------------------------------------------------------------------ */
/*  Saltar por mes y año (franja "Desde / Hasta" del rango             */
/*  personalizado)                                                     */
/* ------------------------------------------------------------------ */
// Instancia del picker del paquete: expone `showCalendars` (si el rango
// personalizado está abierto) y `changeLeftMonth`/`changeRightMonth`, que
// son los mismos que usan sus flechas.
const picker = ref(null)

const nombresMes = localeEs.monthNames

// Años del selector, del más reciente al más antiguo (lo que más se busca
// arriba). Sin minDate/maxDate: los últimos 10 años hasta el actual. Si el
// rango vigente cae fuera, su año se agrega igual para que el select no
// quede en blanco.
const anios = computed(() => {
    const maximo = props.maxDate ? Number(props.maxDate.slice(0, 4)) : hoy.getFullYear()
    const minimo = props.minDate ? Number(props.minDate.slice(0, 4)) : hoy.getFullYear() - 10
    const lista = new Set()
    for (let anio = maximo; anio >= minimo; anio--) lista.add(anio)
    lista.add(desde.value.anio)
    lista.add(hasta.value.anio)
    return [...lista].sort((a, b) => b - a)
})

function mesAnioDe(fecha) {
    const d = fecha ? new Date(fecha) : hoy
    return { anio: d.getFullYear(), mes: d.getMonth() }
}

const desde = computed(() => mesAnioDe(rango.value.startDate))
const hasta = computed(() => mesAnioDe(rango.value.endDate ?? rango.value.startDate))

// Último instante que puede tener el "hasta" de un mes: su último día, sin
// pasar de maxDate ni de hoy si es el mes en curso (igual que los presets
// "Este mes" o "Este año", que terminan hoy y no a fin de mes).
function finDelMes(anio, mes) {
    let fin = finDelDia(new Date(anio, mes + 1, 0))
    const maximo = desdeYmd(props.maxDate, true)
    if (anio === hoy.getFullYear() && mes === hoy.getMonth() && fin > finHoy) fin = finHoy
    if (maximo && fin > maximo) fin = maximo
    return fin
}

function inicioDelMes(anio, mes) {
    const inicio = inicioDelDia(new Date(anio, mes, 1))
    const minimo = desdeYmd(props.minDate)
    return minimo && inicio < minimo ? minimo : inicio
}

function fijarRango(inicio, fin) {
    rango.value = { startDate: inicio, endDate: fin }
    // Si había un clic de "inicio" a medio camino en el calendario, se
    // descarta: el rango ya quedó completo con los selects.
    if (picker.value) picker.value.in_selection = false
    emit('update:fechaDesde', aYmd(inicio))
    emit('update:fechaHasta', aYmd(fin))
}

// Cambiar "Desde" deja el fin donde estaba, salvo que quede antes del
// nuevo inicio: ahí el rango pasa a ser ese mes completo. "Hasta" es el
// espejo. Nunca queda un rango al revés.
function cambiarDesde(cambio) {
    const { anio, mes } = { ...desde.value, ...cambio }
    const inicio = inicioDelMes(anio, mes)
    let fin = rango.value.endDate ? finDelDia(rango.value.endDate) : finDelMes(anio, mes)
    if (fin < inicio) fin = finDelMes(anio, mes)
    fijarRango(inicio, fin)
    picker.value?.changeLeftMonth({ year: anio, month: mes + 1 })
}

function cambiarHasta(cambio) {
    const { anio, mes } = { ...hasta.value, ...cambio }
    const fin = finDelMes(anio, mes)
    let inicio = rango.value.startDate ? inicioDelDia(rango.value.startDate) : inicioDelMes(anio, mes)
    if (inicio > fin) inicio = inicioDelMes(anio, mes)
    fijarRango(inicio, fin)
    picker.value?.changeRightMonth({ year: anio, month: mes + 1 })
}

onMounted(async () => {
    // Si se preseleccionó defaultRange, se avisa al padre (v-model) para que
    // su propio filtro (y la petición al backend) queden en sincro con lo
    // marcado.
    if (!props.fechaDesde && !props.fechaHasta && props.defaultRange && ranges[props.defaultRange]) {
        const [startDate, endDate] = ranges[props.defaultRange]
        emit('update:fechaDesde', aYmd(startDate))
        emit('update:fechaHasta', aYmd(endDate))
    }

    const mod = await import('daterange-picker-vue3')
    DateRangePicker.value = markRaw(desenvolverComponente(mod))
})

const textoRango = computed(() => {
    if (!props.fechaDesde || !props.fechaHasta) return ''
    return `${aTextoDisplay(props.fechaDesde)} - ${aTextoDisplay(props.fechaHasta)}`
})

const tieneValor = computed(() => Boolean(props.fechaDesde || props.fechaHasta))
</script>

<template>
    <div class="date-range-filter">
        <label v-if="label" class="form-label">{{ label }}</label>

        <component
            :is="DateRangePicker"
            v-if="DateRangePicker"
            ref="picker"
            v-model="rango"
            :locale-data="localeEs"
            :ranges="ranges"
            :always-show-calendars="false"
            :auto-apply="true"
            :opens="opens"
            :disabled="disabled"
            :append-to-body="appendToBody"
            :min-date="minDate"
            :max-date="maxDate"
            control-container-class="form-control date-range-filter-input"
            @update="onUpdate"
        >
            <!-- Solo con el rango personalizado abierto: con la lista de
                 atajos sola no hay calendario que mover. -->
            <template #header>
                <div v-if="picker?.showCalendars" class="date-range-meses" role="group"
                    aria-label="Elegir el rango por mes y año">
                    <div class="date-range-meses-campo">
                        <span class="date-range-meses-etiqueta" aria-hidden="true">Desde</span>
                        <select class="date-range-meses-select" :value="desde.mes"
                            aria-label="Mes de inicio"
                            @change="cambiarDesde({ mes: Number($event.target.value) })">
                            <option v-for="(nombre, i) in nombresMes" :key="nombre" :value="i">{{ nombre }}</option>
                        </select>
                        <select class="date-range-meses-select date-range-meses-anio" :value="desde.anio"
                            aria-label="Año de inicio"
                            @change="cambiarDesde({ anio: Number($event.target.value) })">
                            <option v-for="anio in anios" :key="anio" :value="anio">{{ anio }}</option>
                        </select>
                    </div>

                    <i class="ri-arrow-right-line date-range-meses-flecha" aria-hidden="true"></i>

                    <div class="date-range-meses-campo">
                        <span class="date-range-meses-etiqueta" aria-hidden="true">Hasta</span>
                        <select class="date-range-meses-select" :value="hasta.mes"
                            aria-label="Mes final"
                            @change="cambiarHasta({ mes: Number($event.target.value) })">
                            <option v-for="(nombre, i) in nombresMes" :key="nombre" :value="i">{{ nombre }}</option>
                        </select>
                        <select class="date-range-meses-select date-range-meses-anio" :value="hasta.anio"
                            aria-label="Año final"
                            @change="cambiarHasta({ anio: Number($event.target.value) })">
                            <option v-for="anio in anios" :key="anio" :value="anio">{{ anio }}</option>
                        </select>
                    </div>

                    <p class="date-range-meses-nota">Toma meses completos; para elegir días exactos, haz clic en el calendario.</p>
                </div>
            </template>

            <template #input>
                <span :class="{ 'text-muted': !textoRango }">{{ textoRango || placeholder }}</span>
                <button
                    v-if="clearable && tieneValor && !disabled"
                    type="button"
                    class="date-range-filter-clear"
                    aria-label="Limpiar rango de fechas"
                    @click.stop="limpiar"
                >
                    <i class="ri-close-line"></i>
                </button>
                <i v-else class="ri-calendar-line"></i>
            </template>
        </component>

        <!-- Esqueleto no interactivo mientras carga el chunk del calendario:
             muestra el valor actual para que no haya salto de layout. -->
        <div v-else class="form-control date-range-filter-input date-range-filter-skeleton" aria-hidden="true">
            <span :class="{ 'text-muted': !textoRango }">{{ textoRango || placeholder }}</span>
            <i class="ri-calendar-line"></i>
        </div>
    </div>
</template>
