<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import ClienteQuickCreateModal from '@/Components/Cliente/ClienteQuickCreateModal.vue';
import { showError } from '@/Utils/AlertUtil';
import { CLASES_SEMAFORO, calcularMargen, costoBaseDe, evaluarMargen } from '@/Utils/MotorMargen';
import { hoyYmd } from '@/Utils/Fecha';

/**
 * Cotizador de muebles: asistente de 4 pasos para cotizar UN mueble (isla,
 * cabecera, exhibidor…). Guarda una cotización normal con `tipo = MUEBLE` y
 * una sola línea, así que el PDF, la aprobación y el pedido no cambian.
 *
 *   1. Cliente, plantilla del mueble, modalidad de precio y unidad de las
 *      medidas (m o cm).
 *   2. Medidas, niveles, cantidad, descripción e imagen.
 *   3. Costo de UNA unidad: materiales de la receta (precios reales), los
 *      que se agreguen del catálogo y la mano de obra del carpintero (un
 *      monto por mueble; el diseño va incluido).
 *   4. Complejidad, recargo sobre costo, instalación por unidad, descuento y
 *      la vista del cliente.
 *
 * Modalidades (`cotizacion_detalle.modalidad`):
 *   - COMPOSICION: precio = costo ajustado × (1 + recargo). Si el recargo es
 *     el margen de la complejidad, el precio lo calcula el servidor con el
 *     motor; si se cambió, viaja como precio fijado a mano.
 *   - PRECIO_MANUAL: precio por unidad escrito a mano.
 *   - SUPERFICIE: precio por m² × ancho × alto.
 *
 * Las medidas se pueden escribir en centímetros, pero viajan SIEMPRE en
 * metros: el resto del sistema (fórmulas, PDF, pedido) trabaja en metros.
 *
 * Los números son previsualización (Utils/MotorMargen.js): al guardar, el
 * servidor recalcula todo (CotizacionController::normalizarDetalles).
 */
const props = defineProps({
    cotizacion: { type: Object, default: null },
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    // Solo plantillas de mueble (producto.modo_cotizacion = MUEBLE).
    productos: { type: Array, default: () => [] },
    // Catálogo de materiales activos, para "Agregar insumo".
    materiales: { type: Array, default: () => [] },
    tiposProyecto: { type: Array, default: () => [] },
    tiposItem: { type: Object, default: () => ({}) },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
});

const esEdicion = computed(() => !!props.cotizacion);

const PASOS = [
    { numero: 1, titulo: 'Elegir el mueble' },
    { numero: 2, titulo: 'Definir el mueble' },
    { numero: 3, titulo: 'Costear una unidad' },
    { numero: 4, titulo: 'Precio y vista del cliente' },
];

const MODALIDADES = {
    COMPOSICION: {
        etiqueta: 'Por unidad con composición',
        explicacion: 'Se costean los materiales y la mano de obra, y se les suma un recargo.',
    },
    PRECIO_MANUAL: {
        etiqueta: 'Por unidad con precio manual',
        explicacion: 'Escribes el precio de cada mueble. Cargar los costos es opcional: sirve para ver la utilidad.',
    },
    SUPERFICIE: {
        etiqueta: 'Por superficie (m²)',
        explicacion: 'Escribes un precio por m² y se multiplica por ancho × alto del mueble.',
    },
};

/** Cuántas unidades de cada medida hay en un metro. */
const POR_METRO = { m: 1, cm: 100 };

const DESCRIPCION_MANO_OBRA = 'Mano de obra (carpintero)';

const paso = ref(1);

/* ── Estado inicial (nuevo o edición) ────────────────────────────────── */

const linea = props.cotizacion?.detalles?.[0] ?? null;

/** Los insumos guardados, sin la mano de obra (esa va en su propia fila). */
const materialesIniciales = (linea?.items ?? [])
    .filter((i) => i.tipo !== 'MANO_OBRA')
    .map((i) => ({
        material_id: i.material_id ?? null,
        tipo: i.tipo ?? 'MATERIAL',
        descripcion: i.descripcion ?? '',
        unidad: i.unidad ?? '',
        cantidad: Number(i.cantidad ?? 0),
        costo_unitario: Number(i.costo_unitario ?? 0),
    }));

const manoObraInicial = (linea?.items ?? [])
    .filter((i) => i.tipo === 'MANO_OBRA')
    .reduce((total, i) => total + Number(i.cantidad || 0) * Number(i.costo_unitario || 0), 0);

/**
 * Una línea anterior a las modalidades: con precio a mano y sin hoja de
 * costos era un precio manual; con hoja, una composición con su recargo.
 */
function modalidadInicial() {
    if (linea?.modalidad) return linea.modalidad;

    return linea?.precio_manual === 'SI' && !(linea.items ?? []).length ? 'PRECIO_MANUAL' : 'COMPOSICION';
}

function precioM2Inicial() {
    const area = Number(linea?.ancho || 0) * Number(linea?.alto || 0);

    return linea?.modalidad === 'SUPERFICIE' && area > 0
        ? Number((Number(linea.precio_unitario) / area).toFixed(2))
        : '';
}

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

const form = useForm(() => ({
    tipo: 'MUEBLE',
    cliente_id: props.cotizacion?.cliente_id ?? '',
    empleado_id: props.cotizacion?.empleado_id ?? props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    sucursal_id: props.cotizacion?.sucursal_id ?? (props.sucursales[0]?.id ?? ''),
    fecha: toDateInput(props.cotizacion?.fecha) || hoyYmd(),
    fecha_vencimiento: toDateInput(props.cotizacion?.fecha_vencimiento) || hoyYmd(props.config.dias_vencimiento),
    descuento: props.cotizacion?.descuento ?? 0,
    aplicar_iva: props.cotizacion ? Number(props.cotizacion.iva) > 0 : true,
    instalacion_con_iva: props.cotizacion
        ? props.cotizacion.instalacion_con_iva === 'SI'
        : !!props.config.instalacion_con_iva,
    observaciones: props.cotizacion?.observaciones ?? '',
    forma_pago: props.cotizacion?.forma_pago ?? '',
    tiempo_entrega: props.cotizacion?.tiempo_entrega ?? '',
    lugar_entrega: props.cotizacion?.lugar_entrega ?? '',

    // El mueble (la única línea de la cotización).
    producto_id: linea?.producto_id ?? '',
    modalidad: modalidadInicial(),
    unidad_medidas: 'm',
    tipo_proyecto_id: linea?.tipo_proyecto_id ?? (props.tiposProyecto[0]?.id ?? ''),
    descripcion: linea?.descripcion ?? '',
    ancho: linea?.ancho ?? '',
    alto: linea?.alto ?? '',
    profundidad: linea?.profundidad ?? '',
    niveles: linea?.niveles ?? '',
    cantidad: Number(linea?.cantidad ?? 1),
    // La instalación se escribe por unidad; se guarda el total de la línea.
    instalacion_unidad: linea && Number(linea.cantidad) > 0
        ? Number((Number(linea.instalacion ?? 0) / Number(linea.cantidad)).toFixed(2))
        : 0,
    // Recargo sobre el costo ajustado, en %. Se inicializa más abajo, cuando
    // ya se conoce el costo.
    recargo: '',
    precio_unitario: linea?.precio_unitario ?? '',
    precio_m2: precioM2Inicial(),
    imagen: null,
    imagenActualRuta: linea?.imagen ?? null,
    imagenActualUrl: linea?.imagen_url ?? null,
    usarImagenProducto: false,
    materiales: materialesIniciales,
    mano_obra: linea ? Number(manoObraInicial.toFixed(2)) : '',
}));

/* ── Cliente (alta rápida) ───────────────────────────────────────────── */

const clientesDisponibles = ref([...props.clientes]);
const mostrarModalCliente = ref(false);

function onClienteCreado(cliente) {
    clientesDisponibles.value = [cliente, ...clientesDisponibles.value];
    form.cliente_id = cliente.id;
    mostrarModalCliente.value = false;
}

function etiquetaCliente(c) {
    return c.nit ? `${c.razon_social} — ${c.nit}` : c.razon_social;
}

function etiquetaEmpleado(e) {
    const nombre = [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');

    return e.cargo ? `${nombre} (${e.cargo})` : nombre;
}

/* ── Plantilla ───────────────────────────────────────────────────────── */

const plantilla = computed(() => props.productos.find((p) => p.id === Number(form.producto_id)) ?? null);

function elegirPlantilla(producto) {
    const cambio = Number(form.producto_id) !== producto.id;
    form.producto_id = producto.id;

    if (!cambio) return;

    // Otra plantilla: sus materiales, su tarifa y su texto, no los de la
    // anterior. Lo agregado a mano o del catálogo se conserva.
    form.descripcion = producto.descripcion || producto.nombre;
    form.materiales = form.materiales.filter((m) => !m.material_id || m.agregado);
    form.mano_obra = producto.costo_mano_obra !== null ? Number(producto.costo_mano_obra) : '';
    form.usarImagenProducto = false;
    composicionTraida.value = null;
}

/* ── Medidas (m o cm) ────────────────────────────────────────────────── */

function enMetros(valor) {
    return Number(valor || 0) / POR_METRO[form.unidad_medidas];
}

/** Las medidas en metros, que es como viajan al servidor. */
const medidas = computed(() => ({
    ancho: enMetros(form.ancho),
    alto: enMetros(form.alto),
    profundidad: enMetros(form.profundidad),
}));

const superficie = computed(() => Number((medidas.value.ancho * medidas.value.alto).toFixed(4)));

// Al cambiar de unidad se convierten las medidas ya escritas.
watch(() => form.unidad_medidas, (nueva, anterior) => {
    const factor = POR_METRO[nueva] / POR_METRO[anterior];

    for (const campo of ['ancho', 'alto', 'profundidad']) {
        if (form[campo] !== '' && form[campo] !== null) {
            form[campo] = Number((Number(form[campo]) * factor).toFixed(2));
        }
    }
});

/* ── Paso 3: composición (receta del producto con precios reales) ────── */

const costeo = reactive({ cargando: false, error: null, info: null });

// Para qué medidas se trajo la composición: si cambian, se vuelve a traer.
const composicionTraida = ref(esEdicion.value ? claveMedidas() : null);

function claveMedidas() {
    return [form.producto_id, medidas.value.ancho, medidas.value.alto, medidas.value.profundidad, form.niveles].join('|');
}

async function traerComposicion() {
    if (!form.producto_id) return;

    costeo.cargando = true;
    costeo.error = null;

    try {
        const { data } = await window.axios.post(route('cotizaciones.costear'), {
            producto_id: form.producto_id,
            ancho: medidas.value.ancho || null,
            alto: medidas.value.alto || null,
            profundo: medidas.value.profundidad || null,
            niveles: form.niveles || null,
        });

        costeo.info = data;

        // Los materiales de la receta se reemplazan; lo escrito a mano y lo
        // agregado del catálogo (que no está en la receta) se conserva. La
        // mano de obra tiene su propia fila.
        const composicion = data.insumos.filter((i) => i.tipo !== 'MANO_OBRA');
        const deLaReceta = new Set(composicion.map((i) => i.material_id).filter(Boolean));
        const conservados = form.materiales.filter((m) => (!m.material_id || !deLaReceta.has(m.material_id))
            && m.descripcion !== data.descripcion_costo_base);
        form.materiales = [...composicion.map((i) => ({ ...i })), ...conservados];

        const tarifa = data.insumos.find((i) => i.tipo === 'MANO_OBRA');
        if (tarifa && form.mano_obra === '') {
            form.mano_obra = Number(tarifa.costo_unitario);
        }

        composicionTraida.value = claveMedidas();
    } catch (error) {
        costeo.error = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'No se pudo traer la composición del mueble.';
    } finally {
        costeo.cargando = false;
    }
}

/** Material del catálogo elegido en "Agregar insumo" (se agrega y se limpia). */
const insumoDelCatalogo = ref('');

function etiquetaMaterial(m) {
    return `${m.nombre} — ${money(m.precio_unitario)} / ${m.unidad_medida}`;
}

watch(insumoDelCatalogo, (materialId) => {
    const material = props.materiales.find((m) => m.id === Number(materialId));

    if (!material) return;

    form.materiales.push({
        material_id: material.id,
        tipo: 'MATERIAL',
        descripcion: material.nombre,
        unidad: material.unidad_medida,
        cantidad: 1,
        costo_unitario: Number(material.precio_unitario),
        agregado: true,
    });
    insumoDelCatalogo.value = '';
});

function agregarInsumoAMano() {
    form.materiales.push({
        material_id: null,
        tipo: 'MATERIAL',
        descripcion: '',
        unidad: '',
        cantidad: 1,
        costo_unitario: '',
    });
}

function quitarInsumo(index) {
    form.materiales.splice(index, 1);
}

/** Tipos de insumo de la tabla: la mano de obra tiene su propia fila. */
const tiposInsumo = computed(() => Object.entries(props.tiposItem).filter(([valor]) => valor !== 'MANO_OBRA'));

function subtotalInsumo(item) {
    return Number(item.cantidad || 0) * Number(item.costo_unitario || 0);
}

/* ── Motor de margen (previsualización en vivo) ──────────────────────── */

const tipoProyecto = computed(() => props.tiposProyecto.find((t) => t.id === Number(form.tipo_proyecto_id)) ?? null);
const factor = computed(() => Number(tipoProyecto.value?.factor_complejidad ?? 1));
const margenDelNivel = computed(() => (tipoProyecto.value ? Number(tipoProyecto.value.margen_minimo) : props.config.margen_sugerido));

const costoMateriales = computed(() => costoBaseDe(form.materiales));
const manoObra = computed(() => Number(form.mano_obra || 0));
const costoUnidad = computed(() => costoMateriales.value + manoObra.value);
const tieneHojaDeCostos = computed(() => costoUnidad.value > 0);

const costoAjustado = computed(() => costoUnidad.value * factor.value);

const recargo = computed(() => Number(form.recargo || 0) / 100);

/** El recargo es el del nivel de complejidad: el precio lo calcula el motor. */
const recargoEsDelNivel = computed(() => Math.abs(recargo.value - margenDelNivel.value) < 0.00005);

const cantidad = computed(() => Number(form.cantidad || 0));
const instalacionUnidad = computed(() => Number(form.instalacion_unidad || 0));
const instalacion = computed(() => Number((instalacionUnidad.value * cantidad.value).toFixed(2)));

const motor = computed(() => calcularMargen(props.config, costoUnidad.value, factor.value, recargo.value, instalacion.value));

/**
 * Precio de una edición con precio fijado a mano, con el recargo y el costo
 * con que se guardó (se completa más abajo, cuando ya se conoce el costo).
 *
 * @type {{ precio: number, recargo: number, costoAjustado: number }|null}
 */
let precioGuardado = null;

const precioUnitario = computed(() => {
    if (form.modalidad === 'PRECIO_MANUAL') return Number(form.precio_unitario || 0);
    if (form.modalidad === 'SUPERFICIE') return Number((Number(form.precio_m2 || 0) * superficie.value).toFixed(2));

    // Sin tocar el recargo ni los costos, la edición conserva el precio
    // guardado: el recargo se muestra con 2 decimales y recalcularlo
    // movería unos centavos.
    if (precioGuardado && form.recargo === precioGuardado.recargo
        && Math.abs(costoAjustado.value - precioGuardado.costoAjustado) < 0.005) {
        return precioGuardado.precio;
    }

    return Number(motor.value.precio.toFixed(2));
});

const subtotal = computed(() => cantidad.value * precioUnitario.value);
const descuento = computed(() => Math.min(Number(form.descuento || 0), subtotal.value));
const costoTotal = computed(() => costoAjustado.value * cantidad.value);

const rentabilidad = computed(() => evaluarMargen(
    props.config,
    costoTotal.value,
    subtotal.value - descuento.value,
    instalacion.value,
));

const iva = computed(() => {
    if (!form.aplicar_iva) return 0;

    const ivaInstalacion = form.instalacion_con_iva ? instalacion.value * props.config.impuestos.iva : 0;

    return rentabilidad.value.iva + ivaInstalacion;
});

const totalAntesDeImpuestos = computed(() => Math.max(subtotal.value - descuento.value + instalacion.value, 0));
const total = computed(() => Math.max(subtotal.value - descuento.value + iva.value + instalacion.value, 0));

/** Recargo efectivo de un precio escrito a mano, sobre el costo ajustado. */
const recargoEfectivo = computed(() => (costoAjustado.value > 0 ? precioUnitario.value / costoAjustado.value - 1 : 0));

function usarRecargoDelNivel() {
    form.recargo = Number((margenDelNivel.value * 100).toFixed(2));
}

// Una edición con precio fijado a mano conserva su recargo; si no, el del nivel.
if (linea?.precio_manual === 'SI' && form.modalidad === 'COMPOSICION' && costoAjustado.value > 0) {
    form.recargo = Number(((Number(linea.precio_unitario) / costoAjustado.value - 1) * 100).toFixed(2));
    precioGuardado = {
        precio: Number(linea.precio_unitario),
        recargo: form.recargo,
        costoAjustado: costoAjustado.value,
    };
} else {
    usarRecargoDelNivel();
}

// Otro nivel de complejidad trae su margen como recargo.
watch(() => form.tipo_proyecto_id, usarRecargoDelNivel);

// Al pasar a una modalidad manual sin precio escrito, se parte del precio
// que ya se veía (y no de cero).
watch(() => form.modalidad, (nueva, anterior) => {
    const precioAnterior = anterior === 'SUPERFICIE'
        ? Number(form.precio_m2 || 0) * superficie.value
        : anterior === 'PRECIO_MANUAL' ? Number(form.precio_unitario || 0) : motor.value.precio;

    if (!(precioAnterior > 0)) return;

    if (nueva === 'PRECIO_MANUAL' && !(Number(form.precio_unitario) > 0)) {
        form.precio_unitario = Number(precioAnterior.toFixed(2));
    }

    if (nueva === 'SUPERFICIE' && !(Number(form.precio_m2) > 0) && superficie.value > 0) {
        form.precio_m2 = Number((precioAnterior / superficie.value).toFixed(2));
    }
});

/* ── Imagen ──────────────────────────────────────────────────────────── */

const previewImagen = computed(() => {
    if (form.usarImagenProducto) {
        return plantilla.value?.imagen_url ?? null;
    }

    return form.imagenActualRuta ? form.imagenActualUrl : null;
});

// URL temporal del archivo recién elegido, para la vista del cliente.
const urlImagenNueva = computed(() => (form.imagen ? URL.createObjectURL(form.imagen) : null));
const imagenVistaCliente = computed(() => urlImagenNueva.value ?? previewImagen.value);

function onImagenSeleccionada() {
    if (form.imagen) {
        form.usarImagenProducto = false;
    }
}

function usarImagenDePlantilla() {
    form.imagen = null;
    form.usarImagenProducto = true;
}

function quitarImagen() {
    form.imagen = null;
    form.imagenActualRuta = null;
    form.usarImagenProducto = false;
}

/* ── Navegación entre pasos ──────────────────────────────────────────── */

const avisoPaso = ref('');

function positivo(valor) {
    return Number(valor) > 0;
}

/** Qué le falta a un paso para poder seguir (texto vacío = está completo). */
function faltaEnPaso(numero) {
    if (numero === 1) {
        if (!form.cliente_id) return 'Elige el cliente.';
        if (!form.empleado_id) return 'Elige el vendedor.';
        if (!form.sucursal_id) return 'Elige la sucursal.';
        if (!form.producto_id) return 'Elige qué mueble vas a cotizar.';
    }

    if (numero === 2) {
        if (!positivo(form.ancho) || !positivo(form.alto) || !positivo(form.profundidad)) {
            return 'Completa ancho, alto y profundidad.';
        }
        if (!(Number(form.niveles) >= 1)) return 'Indica cuántos niveles tiene (al menos 1).';
        if (!positivo(form.cantidad)) return 'Indica cuántos muebles se cotizan.';
        if (!String(form.descripcion).trim()) return 'Escribe la descripción que verá el cliente.';
    }

    // Solo la composición necesita costos: en precio manual son opcionales.
    if (numero === 3 && form.modalidad === 'COMPOSICION' && !tieneHojaDeCostos.value) {
        return 'Carga al menos un material o la mano de obra: sin costo no hay precio que calcular.';
    }

    if (numero === 4 && precioUnitario.value <= 0) {
        return form.modalidad === 'SUPERFICIE' ? 'Escribe el precio por m².' : 'El precio por unidad tiene que ser mayor a cero.';
    }

    return '';
}

async function irAPaso(destino) {
    avisoPaso.value = '';

    // Hacia atrás siempre; hacia adelante, solo con los pasos previos completos.
    for (let numero = paso.value; numero < destino; numero++) {
        const falta = faltaEnPaso(numero);

        if (falta) {
            paso.value = numero;
            avisoPaso.value = falta;
            return;
        }

        // Al entrar al costeo, la composición se trae sola (y se vuelve a
        // traer si cambiaron las medidas o la plantilla).
        if (numero === 2 && composicionTraida.value !== claveMedidas()) {
            paso.value = 3;
            await traerComposicion();
        }
    }

    paso.value = destino;
}

function estadoPaso(numero) {
    if (numero === paso.value) return 'is-activo';

    return numero < paso.value ? 'is-hecho' : '';
}

/** Paso donde vive el campo con error que devolvió el servidor. */
function pasoDelError(campo) {
    if (/^(cliente_id|empleado_id|sucursal_id|fecha|fecha_vencimiento)$/.test(campo)) return 1;
    if (/^detalles\.0\.(producto_id|modalidad)$/.test(campo)) return 1;
    if (/^detalles\.0\.(descripcion|ancho|alto|profundidad|niveles|cantidad|imagen)/.test(campo)) return 2;
    if (/^detalles\.0\.items/.test(campo)) return 3;

    return 4;
}

function errorLinea(campo) {
    return form.errors[`detalles.0.${campo}`];
}

/* ── Formato ─────────────────────────────────────────────────────────── */

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function pct(fraccion) {
    return `${(Number(fraccion || 0) * 100).toFixed(1)} %`;
}

function metros(valor) {
    return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 2 });
}

function anchoBarra(valor) {
    const tope = props.config.semaforo.umbral_verde;

    return `${Math.min(Math.max(Number(valor || 0) / tope, 0), 1) * 100}%`;
}

const tituloCliente = computed(() => String(form.descripcion || '').split('\n')[0] || plantilla.value?.nombre || 'Mueble');
const detalleCliente = computed(() => String(form.descripcion || '').split('\n').slice(1).join('\n'));

/* ── Guardar ─────────────────────────────────────────────────────────── */

function submit() {
    const falta = faltaEnPaso(4);

    if (falta) {
        avisoPaso.value = falta;
        return;
    }

    const itemsManoObra = manoObra.value > 0
        ? [{
            material_id: null,
            tipo: 'MANO_OBRA',
            descripcion: DESCRIPCION_MANO_OBRA,
            unidad: 'SERVICIO',
            cantidad: 1,
            costo_unitario: manoObra.value,
        }]
        : [];

    // En composición con el recargo del nivel, el precio lo pone el motor
    // del servidor; con otro recargo o en las modalidades manuales, viaja.
    const precioManual = form.modalidad !== 'COMPOSICION' || !recargoEsDelNivel.value;

    form.transform((data) => ({
        ...(esEdicion.value ? { _method: 'put' } : {}),
        tipo: 'MUEBLE',
        cliente_id: data.cliente_id,
        empleado_id: data.empleado_id,
        sucursal_id: data.sucursal_id,
        fecha: data.fecha,
        fecha_vencimiento: data.fecha_vencimiento || null,
        descuento: Number(data.descuento || 0),
        aplicar_iva: !!data.aplicar_iva,
        instalacion_con_iva: !!data.instalacion_con_iva,
        observaciones: data.observaciones,
        forma_pago: data.forma_pago,
        tiempo_entrega: data.tiempo_entrega,
        lugar_entrega: data.lugar_entrega,
        detalles: [{
            producto_id: data.producto_id || null,
            tipo_proyecto_id: data.tipo_proyecto_id || null,
            modalidad: data.modalidad,
            descripcion: data.descripcion,
            ancho: Number(medidas.value.ancho.toFixed(2)),
            alto: Number(medidas.value.alto.toFixed(2)),
            profundidad: Number(medidas.value.profundidad.toFixed(2)),
            niveles: Number(data.niveles),
            cantidad: Number(data.cantidad),
            instalacion: instalacion.value,
            instalacion_detalle: null,
            precio_manual: precioManual ? 'SI' : 'NO',
            precio_unitario: precioUnitario.value,
            imagen: data.imagen,
            usar_imagen_producto: data.usarImagenProducto,
            imagen_actual: data.imagenActualRuta,
            items: [
                ...data.materiales.map((i) => ({
                    material_id: i.material_id || null,
                    tipo: i.tipo,
                    descripcion: i.descripcion,
                    unidad: i.unidad || null,
                    cantidad: Number(i.cantidad || 0),
                    costo_unitario: Number(i.costo_unitario || 0),
                })),
                ...itemsManoObra,
            ],
        }],
    }));

    const opciones = {
        onError: (errors) => {
            paso.value = Math.min(...Object.keys(errors).map(pasoDelError));
            showError(errors);
        },
    };

    if (esEdicion.value) {
        form.post(route('cotizaciones.update', props.cotizacion.id), opciones);
    } else {
        form.post(route('cotizaciones.store'), opciones);
    }
}
</script>

<template>
    <div>
        <form @submit.prevent="submit">
            <!-- Barra de pasos -->
            <ol class="mueble-pasos">
                <li v-for="p in PASOS" :key="p.numero">
                    <button type="button" class="mueble-paso" :class="estadoPaso(p.numero)"
                        :aria-current="p.numero === paso ? 'step' : undefined" @click="irAPaso(p.numero)">
                        <span class="mueble-paso-numero">
                            <i v-if="p.numero < paso" class="fa-solid fa-check" aria-hidden="true"></i>
                            <template v-else>{{ p.numero }}</template>
                        </span>
                        <span class="mueble-paso-titulo">{{ p.titulo }}</span>
                    </button>
                </li>
            </ol>

            <!-- ── Paso 1: cliente y mueble ─────────────────────────────── -->
            <div v-show="paso === 1" class="card mb-4">
                <div class="card-header">
                    <span class="card-title">1. Elegir el mueble</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label mb-0" for="mueble-cliente">Cliente</label>
                                    <button v-can="'clientes.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                        @click="mostrarModalCliente = true">
                                        <i class="fa-solid fa-plus"></i>
                                        Nuevo cliente
                                    </button>
                                </div>
                                <SearchableSelect id="mueble-cliente" v-model="form.cliente_id"
                                    :options="clientesDisponibles" :option-label="etiquetaCliente"
                                    placeholder="Selecciona un cliente" :invalid="!!form.errors.cliente_id" />
                                <p v-if="form.errors.cliente_id" class="form-error">{{ form.errors.cliente_id }}</p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label" for="mueble-vendedor">Vendedor</label>
                                <SearchableSelect id="mueble-vendedor" v-model="form.empleado_id" :options="empleados"
                                    :option-label="etiquetaEmpleado" placeholder="Selecciona un vendedor"
                                    :invalid="!!form.errors.empleado_id" />
                                <p v-if="form.errors.empleado_id" class="form-error">{{ form.errors.empleado_id }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="mueble-sucursal">Sucursal</label>
                                <select id="mueble-sucursal" v-model="form.sucursal_id" class="form-control"
                                    :class="{ 'is-invalid': form.errors.sucursal_id }">
                                    <option value="" disabled>Selecciona una sucursal</option>
                                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                </select>
                                <p v-if="form.errors.sucursal_id" class="form-error">{{ form.errors.sucursal_id }}</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="mueble-fecha">Fecha</label>
                                <input id="mueble-fecha" v-model="form.fecha" type="date" class="form-control"
                                    :class="{ 'is-invalid': form.errors.fecha }" />
                                <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="mueble-vence">Válida hasta</label>
                                <input id="mueble-vence" v-model="form.fecha_vencimiento" type="date"
                                    class="form-control" :class="{ 'is-invalid': form.errors.fecha_vencimiento }" />
                                <p v-if="form.errors.fecha_vencimiento" class="form-error">
                                    {{ form.errors.fecha_vencimiento }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="form-label">¿Qué mueble vas a cotizar?</p>
                    <div v-if="productos.length" class="mueble-plantillas">
                        <button v-for="p in productos" :key="p.id" type="button" class="mueble-plantilla"
                            :class="{ 'is-elegida': Number(form.producto_id) === p.id }"
                            :aria-pressed="Number(form.producto_id) === p.id" @click="elegirPlantilla(p)">
                            <img v-if="p.imagen_url" :src="p.imagen_url" alt="" class="mueble-plantilla-imagen" />
                            <span v-else class="mueble-plantilla-icono" aria-hidden="true">
                                <i class="fa-solid fa-couch"></i>
                            </span>
                            <span class="mueble-plantilla-nombre">{{ p.nombre }}</span>
                            <span class="mueble-plantilla-dato">
                                {{ Number(p.costo_mano_obra) > 0 ? `Mano de obra ${money(p.costo_mano_obra)}` : 'Insumos a mano' }}
                            </span>
                        </button>
                    </div>
                    <p v-else class="hoja-costos-vacia">
                        No hay plantillas de mueble. Créalas en Productos eligiendo
                        "Cotizador de muebles" en "¿Dónde se cotiza?".
                    </p>
                    <p v-if="errorLinea('producto_id')" class="form-error">{{ errorLinea('producto_id') }}</p>

                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="form-group">
                                <label class="form-label" for="mueble-modalidad">Modalidad</label>
                                <select id="mueble-modalidad" v-model="form.modalidad" class="form-control"
                                    :class="{ 'is-invalid': errorLinea('modalidad') }">
                                    <option v-for="(m, valor) in MODALIDADES" :key="valor" :value="valor">
                                        {{ m.etiqueta }}
                                    </option>
                                </select>
                                <p class="fs-xs text-muted mt-1 mb-0">{{ MODALIDADES[form.modalidad].explicacion }}</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="mueble-unidad">Unidad de las medidas</label>
                                <select id="mueble-unidad" v-model="form.unidad_medidas" class="form-control">
                                    <option value="m">Metros (m)</option>
                                    <option value="cm">Centímetros (cm)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <p class="mueble-guia-nota mt-1">
                        <i class="fa-solid fa-circle-info text-primary" aria-hidden="true"></i>
                        La composición de la plantilla se puede editar en cada cotización.
                    </p>
                </div>
            </div>

            <!-- ── Paso 2: definir el mueble ────────────────────────────── -->
            <div v-show="paso === 2" class="card mb-4">
                <div class="card-header">
                    <span class="card-title">2. Definir el mueble</span>
                    <span v-if="plantilla" class="badge badge-soft-primary">{{ plantilla.nombre }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="row">
                                <div class="col-lg-4 col-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-ancho">Ancho ({{ form.unidad_medidas }})</label>
                                        <input id="mueble-ancho" v-model="form.ancho" v-decimal="2" type="text"
                                            inputmode="decimal" class="form-control" placeholder="0.70"
                                            :class="{ 'is-invalid': errorLinea('ancho') }" />
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-alto">Alto ({{ form.unidad_medidas }})</label>
                                        <input id="mueble-alto" v-model="form.alto" v-decimal="2" type="text"
                                            inputmode="decimal" class="form-control" placeholder="1.60"
                                            :class="{ 'is-invalid': errorLinea('alto') }" />
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-profundidad">Profundidad ({{ form.unidad_medidas }})</label>
                                        <input id="mueble-profundidad" v-model="form.profundidad" v-decimal="2"
                                            type="text" inputmode="decimal" class="form-control" placeholder="0.40"
                                            :class="{ 'is-invalid': errorLinea('profundidad') }" />
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-niveles">Niveles</label>
                                        <input id="mueble-niveles" v-model="form.niveles" v-entero="1" type="text"
                                            inputmode="numeric" class="form-control" placeholder="4"
                                            :class="{ 'is-invalid': errorLinea('niveles') }" />
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-cantidad">Cantidad</label>
                                        <input id="mueble-cantidad" v-model="form.cantidad" v-entero="1" type="text"
                                            inputmode="numeric" class="form-control"
                                            :class="{ 'is-invalid': errorLinea('cantidad') }" />
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="mueble-descripcion">Descripción (la ve el cliente)</label>
                                <textarea id="mueble-descripcion" v-model="form.descripcion" v-max-length="2000"
                                    class="form-control" rows="5" :class="{ 'is-invalid': errorLinea('descripcion') }"
                                    placeholder="Primer renglón: título. Debajo: estructura, acabado, impresión."></textarea>
                                <p v-if="errorLinea('descripcion')" class="form-error">{{ errorLinea('descripcion') }}</p>
                            </div>

                            <ul class="mueble-guia">
                                <li><strong>Medidas:</strong> ancho × alto × profundidad del mueble armado, en {{ form.unidad_medidas === 'cm' ? 'centímetros' : 'metros' }}.</li>
                                <li><strong>Niveles:</strong> repisas del mueble, contando la base.</li>
                                <li>Con estas medidas se calculan los materiales del paso 3.</li>
                            </ul>
                        </div>

                        <div class="col-lg-5">
                            <div class="form-group">
                                <label class="form-label">Imagen de referencia (opcional)</label>
                                <FileDropzone v-model="form.imagen" :preview="previewImagen" accept="image/*"
                                    :invalid="!!errorLinea('imagen')" @update:model-value="onImagenSeleccionada" />
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button v-if="plantilla?.imagen_url" type="button" class="btn btn-sm btn-soft-info"
                                        :disabled="form.usarImagenProducto" @click="usarImagenDePlantilla">
                                        <i class="fa-solid fa-copy"></i>
                                        Usar imagen de la plantilla
                                    </button>
                                    <button v-if="form.imagen || previewImagen" type="button"
                                        class="btn btn-sm btn-soft-danger" @click="quitarImagen">
                                        <i class="fa-solid fa-trash"></i>
                                        Quitar imagen
                                    </button>
                                </div>
                                <p v-if="errorLinea('imagen')" class="form-error">{{ errorLinea('imagen') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Paso 3: costear una unidad ───────────────────────────── -->
            <div v-show="paso === 3" class="card mb-4">
                <div class="card-header">
                    <span class="card-title">3. Costear una unidad</span>
                    <button type="button" class="btn btn-sm btn-soft-info" :disabled="costeo.cargando"
                        @click="traerComposicion">
                        <i class="fa-solid" :class="costeo.cargando ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                        Traer composición
                    </button>
                </div>
                <div class="card-body">
                    <p v-if="costeo.error" class="form-error mb-3">{{ costeo.error }}</p>
                    <div v-if="form.modalidad !== 'COMPOSICION'" class="mb-3">
                        <p class="mueble-guia-nota">
                            <i class="fa-solid fa-circle-info text-primary" aria-hidden="true"></i>
                            Con precio manual este paso es opcional: los costos sirven para ver la utilidad del paso 4.
                        </p>
                    </div>

                    <div class="mueble-agregar-insumo">
                        <div class="flex-1">
                            <label class="form-label" for="mueble-insumo-catalogo">Agregar insumo del catálogo</label>
                            <SearchableSelect id="mueble-insumo-catalogo" v-model="insumoDelCatalogo"
                                :options="materiales" :option-label="etiquetaMaterial"
                                placeholder="Fierro, trupán, pintura, pegamento..." />
                        </div>
                        <button type="button" class="btn btn-soft-primary" @click="agregarInsumoAMano">
                            <i class="fa-solid fa-plus"></i>
                            Insumo a mano
                        </button>
                    </div>

                    <div class="hoja-costos mt-0">
                        <div class="hoja-costos-head">
                            <span class="fw-semibold fs-sm">
                                <i class="fa-solid fa-boxes-stacked text-primary"></i>
                                Materiales e impresión
                            </span>
                            <span class="fs-xs text-muted">Precios vigentes de cada material</span>
                        </div>

                        <div class="hoja-costos-labels">
                                <span class="col-span-2">Tipo</span>
                                <span class="col-span-4">Insumo</span>
                                <span class="col-span-1">Unidad</span>
                                <span class="col-span-2">Consumo</span>
                                <span class="col-span-2">Costo unit.</span>
                                <span class="col-span-1">Subtotal</span>
                            </div>

                            <p v-if="!form.materiales.length" class="hoja-costos-vacia">
                                Sin materiales. Usa "Traer composición" o agrega insumos.
                            </p>

                            <div v-for="(item, index) in form.materiales" :key="index" class="hoja-costos-fila">
                                <div class="col-span-12 lg:col-span-2">
                                    <select v-model="item.tipo" class="form-control" aria-label="Tipo de insumo">
                                        <option v-for="[valor, etiqueta] in tiposInsumo" :key="valor" :value="valor">
                                            {{ etiqueta }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-span-12 lg:col-span-4">
                                    <input v-model="item.descripcion" type="text" class="form-control"
                                        aria-label="Insumo" placeholder="Ej. Tubo cuadrado 20x20" />
                                </div>
                                <div class="col-span-4 lg:col-span-1">
                                    <input v-model="item.unidad" type="text" class="form-control" aria-label="Unidad" />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.cantidad" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" aria-label="Consumo" />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.costo_unitario" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" aria-label="Costo unitario" />
                                </div>
                                <div class="col-span-12 lg:col-span-1 d-flex align-items-center gap-2">
                                    <span class="fs-sm fw-semibold flex-1">{{ money(subtotalInsumo(item)) }}</span>
                                    <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                                        aria-label="Quitar insumo" @click="quitarInsumo(index)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <ul v-if="costeo.info?.lineas?.some((l) => l.redondeada)" class="cotizacion-costeo-lista">
                                <li v-for="(l, li) in costeo.info.lineas.filter((x) => x.redondeada)" :key="li">
                                    {{ l.material }}: {{ Number(l.cantidad_bruta) }} →
                                    <strong>{{ Number(l.cantidad) }}</strong> {{ l.unidad }}
                                    <span class="text-info">(redondeado a unidad de compra)</span>
                                </li>
                            </ul>

                            <!-- Mano de obra: una fila más, un monto por mueble -->
                            <div class="hoja-costos-fila mueble-fila-mano-obra">
                                <div class="col-span-12 lg:col-span-2">
                                    <span class="badge badge-soft-primary">
                                        <i class="fa-solid fa-hammer" aria-hidden="true"></i>
                                        Mano de obra
                                    </span>
                                </div>
                                <div class="col-span-12 lg:col-span-4 fs-sm">
                                    Carpintero (diseño incluido)
                                    <span v-if="Number(plantilla?.costo_mano_obra) > 0" class="d-block fs-xs text-muted">
                                        Tarifa de la plantilla: {{ money(plantilla.costo_mano_obra) }}
                                    </span>
                                </div>
                                <div class="col-span-4 lg:col-span-1 fs-sm">servicio</div>
                                <div class="col-span-4 lg:col-span-2 fs-sm">1</div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input id="mueble-mano-obra" v-model="form.mano_obra" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" placeholder="0.00"
                                        aria-label="Mano de obra por mueble" />
                                </div>
                                <div class="col-span-12 lg:col-span-1 fs-sm fw-semibold">{{ money(manoObra) }}</div>
                            </div>
                    </div>

                    <div class="mueble-costo-unidad">
                        <span>{{ money(costoMateriales) }} materiales + {{ money(manoObra) }} mano de obra</span>
                        <span>Costo por unidad: <strong>{{ money(costoUnidad) }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- ── Paso 4: precio y vista del cliente ───────────────────── -->
            <div v-show="paso === 4" class="row">
                <div class="col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header">
                            <span class="card-title">4. Precio</span>
                            <span v-if="tieneHojaDeCostos" :class="CLASES_SEMAFORO[rentabilidad.estado]">
                                <span class="semaforo-punto"></span>
                                {{ rentabilidad.recomendacion }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label" for="mueble-tipo-proyecto">Complejidad del trabajo</label>
                                <select id="mueble-tipo-proyecto" v-model="form.tipo_proyecto_id" class="form-control">
                                    <option value="">Sin nivel (margen por defecto)</option>
                                    <option v-for="t in tiposProyecto" :key="t.id" :value="t.id">
                                        {{ t.nombre }} — costo ×{{ Number(t.factor_complejidad).toFixed(2) }} ·
                                        margen {{ pct(t.margen_minimo) }}
                                    </option>
                                </select>
                            </div>

                            <div class="row">
                                <div v-if="form.modalidad === 'COMPOSICION'" class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-recargo">Recargo sobre costo (%)</label>
                                        <input id="mueble-recargo" v-model="form.recargo" v-decimal="2" type="text"
                                            inputmode="decimal" class="form-control" />
                                        <button v-if="!recargoEsDelNivel" type="button"
                                            class="btn btn-sm btn-soft-info mt-2" @click="usarRecargoDelNivel">
                                            <i class="fa-solid fa-rotate-left"></i>
                                            Usar el de la complejidad ({{ pct(margenDelNivel) }})
                                        </button>
                                    </div>
                                </div>
                                <div v-else-if="form.modalidad === 'SUPERFICIE'" class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-precio-m2">Precio por m² (Bs)</label>
                                        <input id="mueble-precio-m2" v-model="form.precio_m2" v-decimal="2" type="text"
                                            inputmode="decimal" class="form-control" />
                                        <p class="fs-xs text-muted mt-1 mb-0">
                                            Superficie: {{ metros(medidas.ancho) }} × {{ metros(medidas.alto) }} =
                                            {{ metros(superficie) }} m²
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-precio">Precio unitario (Bs)</label>
                                        <input v-if="form.modalidad === 'PRECIO_MANUAL'" id="mueble-precio"
                                            v-model="form.precio_unitario" v-decimal="2" type="text" inputmode="decimal"
                                            class="form-control" :class="{ 'is-invalid': errorLinea('precio_unitario') }" />
                                        <input v-else id="mueble-precio" :value="precioUnitario.toFixed(2)" type="text"
                                            class="form-control fw-semibold" disabled />
                                        <p v-if="errorLinea('precio_unitario')" class="form-error">
                                            {{ errorLinea('precio_unitario') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label">Cantidad</label>
                                        <input :value="cantidad" type="text" class="form-control" disabled />
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-instalacion">Instalación por unidad (Bs)</label>
                                        <input id="mueble-instalacion" v-model="form.instalacion_unidad" v-decimal="2"
                                            type="text" inputmode="decimal" class="form-control" placeholder="0.00" />
                                        <p v-if="instalacion > 0" class="fs-xs text-muted mt-1 mb-0">
                                            {{ cantidad }} × {{ money(instalacionUnidad) }} = {{ money(instalacion) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-descuento">Descuento (Bs)</label>
                                        <input id="mueble-descuento" v-model="form.descuento" v-decimal="2" type="text"
                                            inputmode="decimal" class="form-control" />
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="form-label">Factura</label>
                                        <label class="d-flex align-items-center gap-2 mb-1" for="mueble-iva">
                                            <input id="mueble-iva" v-model="form.aplicar_iva" type="checkbox" />
                                            Con IVA {{ pct(config.impuestos.iva) }}
                                        </label>
                                        <label v-if="instalacion > 0" class="d-flex align-items-center gap-2 mb-0"
                                            for="mueble-iva-instalacion" :class="{ 'text-muted': !form.aplicar_iva }">
                                            <input id="mueble-iva-instalacion" v-model="form.instalacion_con_iva"
                                                type="checkbox" :disabled="!form.aplicar_iva" />
                                            IVA también sobre la instalación
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <span class="card-title">Desglose interno</span>
                            <span class="fs-xs text-muted">No se imprime</span>
                        </div>
                        <div class="card-body">
                            <div class="margen-panel margen-interno mb-0">
                                <div class="margen-row">
                                    <span>Costo ajustado = {{ money(costoUnidad) }} × {{ factor.toFixed(2) }}</span>
                                    <span>{{ money(costoAjustado) }}</span>
                                </div>
                                <div class="margen-row">
                                    <span v-if="form.modalidad === 'COMPOSICION'">
                                        Precio unitario = {{ money(costoAjustado) }} × {{ (1 + recargo).toFixed(4).replace(/0+$/, '').replace(/\.$/, '') }}
                                    </span>
                                    <span v-else-if="form.modalidad === 'SUPERFICIE'">
                                        Precio unitario = {{ money(form.precio_m2) }} × {{ metros(superficie) }} m²
                                    </span>
                                    <span v-else>
                                        Precio unitario (a mano<template v-if="costoAjustado > 0">, recargo {{ pct(recargoEfectivo) }}</template>)
                                    </span>
                                    <span>{{ money(precioUnitario) }}</span>
                                </div>
                                <div class="margen-row">
                                    <span>Costo total de producción ({{ cantidad }} × {{ money(costoAjustado) }})</span>
                                    <span>{{ money(costoTotal) }}</span>
                                </div>
                                <div class="margen-row">
                                    <span>Precio + utilidad ({{ cantidad }} × {{ money(precioUnitario) }})</span>
                                    <span>{{ money(subtotal) }}</span>
                                </div>
                                <div v-if="descuento > 0" class="margen-row">
                                    <span>Precio con descuento (− {{ money(descuento) }})</span>
                                    <span>{{ money(subtotal - descuento) }}</span>
                                </div>
                                <div v-if="instalacion > 0" class="margen-row">
                                    <span>Instalación ({{ cantidad }} × {{ money(instalacionUnidad) }})</span>
                                    <span>{{ money(instalacion) }}</span>
                                </div>
                                <div class="margen-row margen-row-destacada">
                                    <span>Total antes de impuestos</span>
                                    <span>{{ money(totalAntesDeImpuestos) }}</span>
                                </div>
                                <template v-if="tieneHojaDeCostos">
                                    <div class="margen-row margen-row-destacada">
                                        <span>Utilidad real (después de IT e IUE)</span>
                                        <span>
                                            {{ money(rentabilidad.utilidadReal) }}
                                            <span class="fs-xs text-muted">({{ pct(rentabilidad.rentabilidad) }} del costo)</span>
                                        </span>
                                    </div>
                                    <div class="margen-barra">
                                        <div :class="`margen-barra-fill is-${rentabilidad.estado.toLowerCase()}`"
                                            :style="{ width: anchoBarra(rentabilidad.rentabilidad) }"></div>
                                    </div>
                                </template>
                                <p v-else class="fs-xs text-muted mt-2 mb-0">
                                    Sin costos cargados no se puede calcular la utilidad.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <span class="card-title">Condiciones de venta</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-forma-pago">Forma de pago</label>
                                        <input id="mueble-forma-pago" v-model="form.forma_pago" v-max-length="255"
                                            type="text" class="form-control" placeholder="15 días crédito" />
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-tiempo">Tiempo de entrega</label>
                                        <input id="mueble-tiempo" v-model="form.tiempo_entrega" v-max-length="255"
                                            type="text" class="form-control" placeholder="10 días hábiles" />
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="form-label" for="mueble-lugar">Lugar de entrega</label>
                                        <input id="mueble-lugar" v-model="form.lugar_entrega" v-max-length="255"
                                            type="text" class="form-control" />
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label" for="mueble-observaciones">Observaciones</label>
                                <textarea id="mueble-observaciones" v-model="form.observaciones" v-max-length="2000"
                                    class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lo que verá el cliente: sin costos internos -->
                <div class="col-lg-5">
                    <div class="card mb-4 mueble-vista-cliente">
                        <div class="card-header">
                            <span class="card-title">
                                <i class="fa-solid fa-eye text-primary" aria-hidden="true"></i>
                                Vista del cliente
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="mueble-vista-titulo">{{ tituloCliente }}</p>
                            <p v-if="detalleCliente" class="mueble-vista-detalle">{{ detalleCliente }}</p>
                            <p class="fs-sm mb-3">
                                <strong>Medidas:</strong>
                                {{ metros(medidas.ancho) }} × {{ metros(medidas.alto) }} × {{ metros(medidas.profundidad) }} m
                                · {{ form.niveles || 0 }} {{ Number(form.niveles) === 1 ? 'nivel' : 'niveles' }}
                            </p>
                            <img v-if="imagenVistaCliente" :src="imagenVistaCliente" alt="Imagen de referencia"
                                class="mueble-vista-imagen" />

                            <div class="cotizacion-total-row">
                                <span>{{ cantidad }} × {{ money(precioUnitario) }}</span>
                                <span class="fw-semibold">{{ money(subtotal) }}</span>
                            </div>
                            <div v-if="descuento > 0" class="cotizacion-total-row">
                                <span>Descuento</span>
                                <span class="fw-semibold">− {{ money(descuento) }}</span>
                            </div>
                            <div v-if="form.aplicar_iva" class="cotizacion-total-row">
                                <span>IVA</span>
                                <span class="fw-semibold">{{ money(iva) }}</span>
                            </div>
                            <div v-if="instalacion > 0" class="cotizacion-total-row">
                                <span>Instalación ({{ cantidad }} × {{ money(instalacionUnidad) }})</span>
                                <span class="fw-semibold">{{ money(instalacion) }}</span>
                            </div>
                            <div class="cotizacion-total-row cotizacion-total-grand">
                                <span>Total</span>
                                <span>{{ money(total) }}</span>
                            </div>
                            <p class="fs-xs text-muted mt-3 mb-0">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                Los costos internos no se imprimen.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación -->
            <p v-if="avisoPaso" class="form-error mb-3" role="alert">{{ avisoPaso }}</p>
            <div class="mueble-nav">
                <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary">Cancelar</Link>
                <div class="d-flex gap-2">
                    <button v-if="paso > 1" type="button" class="btn btn-soft-primary" @click="irAPaso(paso - 1)">
                        <i class="fa-solid fa-chevron-left"></i>
                        Anterior
                    </button>
                    <button v-if="paso < 4" type="button" class="btn btn-primary" :disabled="costeo.cargando"
                        @click="irAPaso(paso + 1)">
                        Siguiente
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button v-else type="submit" class="btn btn-primary" :disabled="form.processing">
                        <i class="fa-solid" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                        {{ esEdicion ? 'Guardar cambios' : 'Guardar cotización' }}
                    </button>
                </div>
            </div>
        </form>

        <ClienteQuickCreateModal :show="mostrarModalCliente" @close="mostrarModalCliente = false"
            @created="onClienteCreado" />
    </div>
</template>
