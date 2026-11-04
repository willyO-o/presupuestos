<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import QuickCreateModal from '@/Components/QuickCreateModal.vue';
import ClienteQuickCreateModal from '@/Components/Cliente/ClienteQuickCreateModal.vue';
import EmpleadoFormFields from '@/Components/Empleado/EmpleadoFormFields.vue';
import ProductoFormFields from '@/Components/Producto/ProductoFormFields.vue';
import { showError } from '@/Utils/AlertUtil';
import { CLASES_SEMAFORO, calcularMargen, costoBaseDe, evaluarMargen } from '@/Utils/MotorMargen';
import { hoyYmd } from '@/Utils/Fecha';

/**
 * Formulario compartido de crear/editar una cotización (ver .ai/rules/pages.md:
 * información compleja con líneas de detalle → vista independiente + partial
 * compartido, no un modal). Si recibe `cotizacion` guarda con PUT, si no con POST.
 *
 * Cada línea replica una hoja del Excel de margen automático: una lista de
 * insumos (materiales, mano de obra, impresión…), un nivel de complejidad y,
 * opcionalmente, un monto de instalación. El precio sale del motor de margen;
 * el vendedor puede tomar el control y escribirlo a mano.
 *
 * Los números que se ven acá son previsualización (Utils/MotorMargen.js): al
 * guardar, el servidor recalcula todo y su resultado es el que manda.
 */
const props = defineProps({
    cotizacion: { type: Object, default: null },
    clientes: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
    tiposProyecto: { type: Array, default: () => [] },
    tiposItem: { type: Object, default: () => ({}) },
    empleadoActualId: { type: [Number, String], default: null },
    config: { type: Object, required: true },
    // Solo alimentan los modales de alta rápida (Nuevo cliente/vendedor/
    // producto) — ver Components/QuickCreateModal.vue.
    categoriasProducto: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargosEmpleado: { type: Array, default: () => [] },
});

const esEdicion = computed(() => !!props.cotizacion);

/* ── Alta rápida de cliente (ver Components/Cliente/ClienteQuickCreateModal.vue) ── */

// Copia local editable: `props.clientes` no se puede mutar, y el cliente
// recién creado tiene que aparecer en el desplegable YA, sin recargar la
// página (eso perdería el resto de la cotización a medio llenar).
const clientesDisponibles = ref([...props.clientes]);
const mostrarModalCliente = ref(false);

function onClienteCreado(cliente) {
    clientesDisponibles.value = [cliente, ...clientesDisponibles.value];
    form.cliente_id = cliente.id;
    mostrarModalCliente.value = false;
}

/* ── Alta rápida de empleado (vendedor) ──────────────────────────────── */

const empleadosDisponibles = ref([...props.empleados]);
const mostrarModalEmpleado = ref(false);

function empleadoVacio() {
    return {
        user_id: '',
        sucursal_id: props.sucursales[0]?.id ?? '',
        area_id: props.areas[0]?.id ?? '',
        nombres: '',
        paterno: '',
        materno: '',
        ci: '',
        cargo: '',
        telefono: '',
        fecha_ingreso: hoyYmd(),
        estado: 'ACTIVO',
    };
}

function onEmpleadoCreado(empleado) {
    empleadosDisponibles.value = [empleado, ...empleadosDisponibles.value];
    form.empleado_id = empleado.id;
    mostrarModalEmpleado.value = false;
}

/* ── Alta rápida de producto (por línea) ─────────────────────────────── */

const categoriasProductoDisponibles = ref([...props.categoriasProducto]);
const productosDisponibles = ref([...props.productos]);
const mostrarModalProducto = ref(false);
// Qué línea disparó el modal: el mismo modal sirve para cualquier ítem, no
// hace falta una instancia por línea.
const lineaModalProducto = ref(null);

function productoVacio() {
    return {
        categoria_producto_id: categoriasProductoDisponibles.value[0]?.id ?? '',
        nombre: '',
        descripcion: '',
        unidad_medida: 'M2',
        precio_base: '',
        horas_mano_obra: '',
        requiere_medidas: 'SI',
        cotizable_web: 'NO',
        modo_cotizacion: 'GENERAL',
        estado: 'ACTIVO',
    };
}

function abrirModalProducto(index) {
    lineaModalProducto.value = index;
    mostrarModalProducto.value = true;
}

function onProductoCreado(producto) {
    productosDisponibles.value = [producto, ...productosDisponibles.value];

    if (lineaModalProducto.value !== null) {
        form.detalles[lineaModalProducto.value].producto_id = producto.id;
        onProductoChange(lineaModalProducto.value);
    }

    mostrarModalProducto.value = false;
}

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

function hoy() {
    return hoyYmd();
}

function hoyMas(dias) {
    return hoyYmd(dias);
}

function itemVacio(extra = {}) {
    return {
        material_id: null,
        tipo: 'MATERIAL',
        descripcion: '',
        unidad: '',
        cantidad: 1,
        costo_unitario: '',
        ...extra,
    };
}

function lineaVacia(extra = {}) {
    return {
        producto_id: '',
        tipo_proyecto_id: props.tiposProyecto[0]?.id ?? '',
        descripcion: '',
        ancho: '',
        alto: '',
        // Muebles y exhibidores: ancho × alto × profundidad, y niveles.
        profundidad: '',
        niveles: '',
        cantidad: 1,
        instalacion: 0,
        // Desglose con el que se calcula la instalación (ver
        // activarCalculoInstalacion); null = monto escrito a mano.
        instalacion_detalle: null,
        precio_manual: 'NO',
        precio_unitario: '',
        // Imagen referencial de la línea: un archivo nuevo (`imagen`, lo
        // maneja FileDropzone), la ruta que ya tenía (`imagenActualRuta`,
        // para no perderla al reemplazar el detalle entero al guardar) y su
        // URL pública (`imagenActualUrl`, solo para mostrarla — ver
        // `previewExistente`). `usarImagenProducto` pide copiar la del
        // producto del catálogo en vez de subir una propia.
        imagen: null,
        imagenActualRuta: null,
        imagenActualUrl: null,
        usarImagenProducto: false,
        items: [],
        ...extra,
    };
}

const detallesIniciales = esEdicion.value && props.cotizacion.detalles?.length
    ? props.cotizacion.detalles.map((d) => ({
        producto_id: d.producto_id ?? '',
        tipo_proyecto_id: d.tipo_proyecto_id ?? '',
        descripcion: d.descripcion ?? '',
        ancho: d.ancho ?? '',
        alto: d.alto ?? '',
        profundidad: d.profundidad ?? '',
        niveles: d.niveles ?? '',
        cantidad: Number(d.cantidad ?? 1),
        instalacion: Number(d.instalacion ?? 0),
        instalacion_detalle: d.instalacion_detalle
            ? {
                personas: Number(d.instalacion_detalle.personas ?? 1),
                horas: d.instalacion_detalle.horas ?? '',
                costo_hora: d.instalacion_detalle.costo_hora ?? props.config.costo_hora_mano_obra,
                nocturna: !!d.instalacion_detalle.nocturna,
                recargo_nocturno: d.instalacion_detalle.recargo_nocturno ?? props.config.recargo_nocturno,
                transporte: d.instalacion_detalle.transporte ?? '',
            }
            : null,
        precio_manual: d.precio_manual ?? 'NO',
        precio_unitario: d.precio_unitario ?? '',
        imagen: null,
        imagenActualRuta: d.imagen ?? null,
        imagenActualUrl: d.imagen_url ?? null,
        usarImagenProducto: false,
        items: (d.items ?? []).map((i) => ({
            material_id: i.material_id ?? null,
            tipo: i.tipo ?? 'MATERIAL',
            descripcion: i.descripcion ?? '',
            unidad: i.unidad ?? '',
            cantidad: Number(i.cantidad ?? 0),
            costo_unitario: Number(i.costo_unitario ?? 0),
        })),
    }))
    : [lineaVacia()];

const form = useForm(() => ({
    cliente_id: props.cotizacion?.cliente_id ?? (props.clientes[0]?.id ?? ''),
    empleado_id: props.cotizacion?.empleado_id ?? props.empleadoActualId ?? (props.empleados[0]?.id ?? ''),
    sucursal_id: props.cotizacion?.sucursal_id ?? (props.sucursales[0]?.id ?? ''),
    fecha: toDateInput(props.cotizacion?.fecha) || hoy(),
    fecha_vencimiento: toDateInput(props.cotizacion?.fecha_vencimiento) || hoyMas(props.config.dias_vencimiento),
    descuento: props.cotizacion?.descuento ?? 0,
    // El IVA lo calcula el motor; acá solo se decide si la factura lo lleva.
    aplicar_iva: props.cotizacion ? Number(props.cotizacion.iva) > 0 : true,
    // Si la instalación también paga IVA (por defecto, lo que diga la
    // configuración de la empresa).
    instalacion_con_iva: props.cotizacion
        ? props.cotizacion.instalacion_con_iva === 'SI'
        : !!props.config.instalacion_con_iva,
    observaciones: props.cotizacion?.observaciones ?? '',
    forma_pago: props.cotizacion?.forma_pago ?? '',
    tiempo_entrega: props.cotizacion?.tiempo_entrega ?? '',
    lugar_entrega: props.cotizacion?.lugar_entrega ?? '',
    detalles: detallesIniciales,
}));

/* Estado auxiliar del costeo por línea (fuera del form para no enviarlo). */
const costeo = reactive(form.detalles.map(() => ({ cargando: false, error: null, info: null })));

/* ── Catálogo ────────────────────────────────────────────────────────── */

/** Igual que PrecioSugeridoService::DESCRIPCION_COSTO_BASE. */
const DESCRIPCION_COSTO_BASE = 'Costo base del producto';

function productoDe(lineaProductoId) {
    return productosDisponibles.value.find((p) => p.id === Number(lineaProductoId)) ?? null;
}

function tipoProyectoDe(tipoId) {
    return props.tiposProyecto.find((t) => t.id === Number(tipoId)) ?? null;
}

/**
 * ancho × alto redondeado a centavos como el servidor (0,7 × 1,75 = 1,23 y
 * no 1,22: en binario el producto queda apenas por debajo de 1,225).
 */
function areaDe(linea) {
    // Primero se limpia el ruido binario (6 decimales) y después se redondea
    // con notación exponencial, que no vuelve a pasar por un producto x 100.
    const area = Number((Number(linea.ancho || 0) * Number(linea.alto || 0)).toFixed(6));

    return Number(`${Math.round(Number(`${area}e2`))}e-2`);
}

/**
 * La medida por la que se cobra el producto, según su unidad: el área para
 * m², el ancho para metro lineal. `null` para los que se cobran por unidad.
 * Una línea sin producto (ítem personalizado) muestra el área.
 *
 * @returns {{ etiqueta: string, valor: number }|null}
 */
function medidaDeCobro(linea) {
    const unidad = productoDe(linea.producto_id)?.unidad_medida ?? 'M2';

    if (unidad === 'METRO_LINEAL') {
        return { etiqueta: 'Metros lineales', valor: Number(linea.ancho || 0) };
    }

    return unidad === 'M2' ? { etiqueta: 'Área m²', valor: areaDe(linea) } : null;
}

/**
 * Costo base vigente del producto si cambió desde que se trajo a la hoja de
 * costos (el dueño lo actualizó). `null` si no cambió o la línea no lo usa.
 */
function costoBaseDesactualizado(linea) {
    const fila = linea.items.find((i) => !i.material_id && i.descripcion === DESCRIPCION_COSTO_BASE);
    const vigente = Number(productoDe(linea.producto_id)?.precio_base || 0);

    if (!fila || vigente <= 0 || Math.abs(Number(fila.costo_unitario) - vigente) < 0.005) {
        return null;
    }

    return vigente;
}

function pideMedidas(linea) {
    const producto = productoDe(linea.producto_id);
    return !producto || producto.requiere_medidas === 'SI';
}

function onProductoChange(index) {
    const linea = form.detalles[index];
    const producto = productoDe(linea.producto_id);
    costeo[index] = { cargando: false, error: null, info: null };

    if (!producto) return;

    if (!linea.descripcion) {
        linea.descripcion = producto.nombre;
    }
    if (producto.requiere_medidas === 'NO') {
        linea.ancho = '';
        linea.alto = '';
        linea.profundidad = '';
        linea.niveles = '';
    }

    // Con la hoja vacía y lo necesario para costear, los costos del producto
    // (costo base o receta) se traen solos: si no, el vendedor escribe un
    // precio a mano sin ver que el producto ya tenía su costo cargado.
    if (!linea.items.length && (producto.requiere_medidas === 'NO' || medidaDeCobro(linea)?.valor > 0)) {
        traerInsumos(index);
    }
}

/* ── Imagen referencial de la línea ──────────────────────────────────── */

/**
 * Qué mostrar en el FileDropzone cuando la línea NO tiene un archivo recién
 * elegido: la del producto (si se pidió copiarla) o la que la línea ya
 * tenía guardada (edición sin tocar la imagen). FileDropzone le da
 * prioridad a `linea.imagen` sobre esto apenas hay un archivo nuevo.
 */
function previewExistente(linea) {
    if (linea.usarImagenProducto) {
        return productoDe(linea.producto_id)?.imagen_url ?? null;
    }

    return linea.imagenActualRuta ? linea.imagenActualUrl : null;
}

function onImagenSeleccionada(index) {
    if (form.detalles[index].imagen) {
        form.detalles[index].usarImagenProducto = false;
    }
}

/** Copia la imagen del producto del catálogo elegido (se re-codifica a JPG al guardar). */
function usarImagenDelProducto(index) {
    const linea = form.detalles[index];

    if (!productoDe(linea.producto_id)?.imagen_url) return;

    linea.imagen = null;
    linea.usarImagenProducto = true;
}

function quitarImagenLinea(index) {
    const linea = form.detalles[index];

    linea.imagen = null;
    linea.imagenActualRuta = null;
    linea.usarImagenProducto = false;
}

/* ── Instalación: monto a mano o calculado ───────────────────────────── */

/**
 * Mano de obra de la instalación: personas × horas × Bs/hora, con el
 * recargo de horas extra si es nocturna. Espejo de
 * CotizacionController::normalizarInstalacion (manda el servidor).
 */
function manoObraInstalacion(detalle) {
    const base = Number(detalle.personas || 0) * Number(detalle.horas || 0) * Number(detalle.costo_hora || 0);

    return detalle.nocturna ? base * (1 + Number(detalle.recargo_nocturno || 0) / 100) : base;
}

/** Monto de instalación de la línea: el calculado si hay desglose, si no el escrito a mano. */
function instalacionDe(linea) {
    const detalle = linea.instalacion_detalle;

    if (!detalle) {
        return Number(linea.instalacion || 0);
    }

    return Math.round((manoObraInstalacion(detalle) + Number(detalle.transporte || 0)) * 100) / 100;
}

function activarCalculoInstalacion(linea) {
    linea.instalacion_detalle = {
        personas: 1,
        horas: '',
        costo_hora: props.config.costo_hora_mano_obra,
        nocturna: false,
        recargo_nocturno: props.config.recargo_nocturno,
        transporte: '',
    };
}

/** Vuelve al monto a mano, conservando lo que daba el cálculo. */
function quitarCalculoInstalacion(linea) {
    linea.instalacion = instalacionDe(linea);
    linea.instalacion_detalle = null;
}

/** "2 personas × 4 h × Bs 18,00 + 50 % nocturno" para el resumen del cálculo. */
function resumenInstalacion(detalle) {
    const personas = Number(detalle.personas || 0);
    const partes = [
        `${personas} ${personas === 1 ? 'persona' : 'personas'}`,
        `${Number(detalle.horas || 0).toLocaleString('es-BO')} h`,
        money(detalle.costo_hora),
    ];
    const nocturno = detalle.nocturna ? ` + ${Number(detalle.recargo_nocturno || 0)} % nocturno` : '';

    return `${partes.join(' × ')}${nocturno}`;
}

/** Errores del desglose de una línea (personas, horas…), en un solo mensaje. */
function erroresInstalacion(index) {
    return Object.entries(form.errors)
        .filter(([campo]) => campo.startsWith(`detalles.${index}.instalacion_detalle.`))
        .map(([, mensaje]) => mensaje);
}

/** Qué dice la ayuda bajo el monto: si se cobra con IVA y de dónde sale. */
function ayudaInstalacion(linea) {
    const conIva = form.aplicar_iva && form.instalacion_con_iva;

    if (linea.instalacion_detalle) {
        return conIva ? 'Calculada abajo · con IVA' : 'Calculada abajo · sin IVA';
    }

    return conIva ? 'Monto fijo, con IVA' : 'Monto fijo, sin IVA';
}

/* ── Motor de margen (previsualización en vivo) ──────────────────────── */

function factorDe(linea) {
    return Number(tipoProyectoDe(linea.tipo_proyecto_id)?.factor_complejidad ?? 1);
}

function margenDe(linea) {
    const tipo = tipoProyectoDe(linea.tipo_proyecto_id);
    return tipo ? Number(tipo.margen_minimo) : props.config.margen_sugerido;
}

function costoBaseLinea(linea) {
    return costoBaseDe(linea.items);
}

/** Resultado del motor para UNA unidad de la línea, con el precio sugerido. */
function motorLinea(linea) {
    return calcularMargen(
        props.config,
        costoBaseLinea(linea),
        factorDe(linea),
        margenDe(linea),
        instalacionDe(linea),
    );
}

/** Precio unitario efectivo: el del motor, salvo que el vendedor lo fije. */
function precioUnitario(linea) {
    if (linea.precio_manual === 'SI' || linea.items.length === 0) {
        return Number(linea.precio_unitario || 0);
    }
    return motorLinea(linea).precio;
}

function subtotalLinea(linea) {
    return Number(linea.cantidad || 0) * precioUnitario(linea);
}

/** Rentabilidad real de la línea con el precio que se va a cobrar. */
function margenLinea(linea) {
    const cantidad = Number(linea.cantidad || 0);

    return evaluarMargen(
        props.config,
        costoBaseLinea(linea) * factorDe(linea) * cantidad,
        subtotalLinea(linea),
        instalacionDe(linea),
    );
}

/** La línea tiene hoja de costos y el vendedor fijó el precio a mano. */
function tienePrecioFijado(linea) {
    return linea.precio_manual === 'SI' && linea.items.length > 0;
}

/** Margen sobre el costo ajustado que da el precio fijado a mano (0.45 = 45 %). */
function margenFijado(linea) {
    const costoAjustado = motorLinea(linea).costoAjustado;

    return costoAjustado > 0 ? precioUnitario(linea) / costoAjustado - 1 : 0;
}

function tomarControlDelPrecio(linea) {
    linea.precio_unitario = Number(motorLinea(linea).precio.toFixed(2));
    linea.precio_manual = 'SI';
}

function devolverPrecioAlMotor(linea) {
    linea.precio_manual = 'NO';
}

/* ── Hoja de costos ──────────────────────────────────────────────────── */

function agregarItem(index, extra = {}) {
    form.detalles[index].items.push(itemVacio(extra));
}

function quitarItem(index, itemIndex) {
    form.detalles[index].items.splice(itemIndex, 1);
}

function subtotalItem(item) {
    return Number(item.cantidad || 0) * Number(item.costo_unitario || 0);
}

/**
 * Trae los insumos de la receta/BOM del producto para las medidas cargadas
 * (POST /cotizaciones/costear). Reemplaza los materiales traídos antes pero
 * respeta las filas que el vendedor escribió a mano (mano de obra, servicios).
 */
async function traerInsumos(index) {
    const linea = form.detalles[index];
    if (!linea.producto_id) return;

    costeo[index].cargando = true;
    costeo[index].error = null;

    try {
        const { data } = await window.axios.post(route('cotizaciones.costear'), {
            producto_id: linea.producto_id,
            ancho: linea.ancho || null,
            alto: linea.alto || null,
            profundo: linea.profundidad || null,
            niveles: linea.niveles || null,
            tipo_proyecto_id: linea.tipo_proyecto_id || null,
            instalacion: instalacionDe(linea),
        });

        costeo[index].info = data;

        // Lo escrito a mano se conserva; la mano de obra que había aportado el
        // producto se reemplaza por la nueva (si no, se duplicaría).
        const escritosAMano = linea.items.filter(
            (i) => !i.material_id
                && !(i.tipo === 'MANO_OBRA' && i.descripcion === data.descripcion_mano_obra)
                && i.descripcion !== data.descripcion_costo_base,
        );
        linea.items = [...data.insumos.map((i) => ({ ...i })), ...escritosAMano];
    } catch (error) {
        costeo[index].error = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'No se pudieron traer los insumos del producto.';
    } finally {
        costeo[index].cargando = false;
    }
}

/* ── Líneas ──────────────────────────────────────────────────────────── */

function agregarLinea() {
    form.detalles.push(lineaVacia());
    costeo.push({ cargando: false, error: null, info: null });
}

function quitarLinea(index) {
    if (form.detalles.length === 1) return;
    form.detalles.splice(index, 1);
    costeo.splice(index, 1);
}

/* ── Totales de la cotización ────────────────────────────────────────── */

const subtotal = computed(() => form.detalles.reduce((acc, l) => acc + subtotalLinea(l), 0));
const descuento = computed(() => Math.min(Number(form.descuento || 0), subtotal.value));
const instalacionTotal = computed(() => form.detalles.reduce((acc, l) => acc + instalacionDe(l), 0));

const costoAjustadoTotal = computed(() => form.detalles.reduce(
    (acc, l) => acc + costoBaseLinea(l) * factorDe(l) * Number(l.cantidad || 0),
    0,
));

const costoBaseTotal = computed(() => form.detalles.reduce(
    (acc, l) => acc + costoBaseLinea(l) * Number(l.cantidad || 0),
    0,
));

/** Motor sobre el presupuesto completo: la base imponible es subtotal − descuento. */
const motorTotal = computed(() => evaluarMargen(
    props.config,
    costoAjustadoTotal.value,
    subtotal.value - descuento.value,
    instalacionTotal.value,
));

/** IVA de la factura; con "Instalación con IVA" también grava la instalación. */
const iva = computed(() => {
    if (!form.aplicar_iva) return 0;

    const ivaInstalacion = form.instalacion_con_iva ? instalacionTotal.value * props.config.impuestos.iva : 0;

    return motorTotal.value.iva + ivaInstalacion;
});
const total = computed(() => Math.max(subtotal.value - descuento.value + iva.value + instalacionTotal.value, 0));

/* ── Formato ─────────────────────────────────────────────────────────── */

function money(value) {
    return `Bs ${Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function pct(fraccion) {
    return `${(Number(fraccion || 0) * 100).toFixed(1)} %`;
}

function nombreEmpleado(e) {
    return [e.nombres, e.paterno, e.materno].filter(Boolean).join(' ');
}

function etiquetaEmpleado(e) {
    return e.cargo ? `${nombreEmpleado(e)} (${e.cargo})` : nombreEmpleado(e);
}

function etiquetaCliente(c) {
    return c.nit ? `${c.razon_social} — ${c.nit}` : c.razon_social;
}

function anchoBarra(rentabilidad) {
    // El umbral verde marca el 100 % de la barra: pasar de ahí ya es óptimo.
    const tope = props.config.semaforo.umbral_verde;
    return `${Math.min(Math.max(Number(rentabilidad || 0) / tope, 0), 1) * 100}%`;
}

function claseBarra(estado) {
    return `margen-barra-fill is-${estado.toLowerCase()}`;
}

/* ── Guardar ─────────────────────────────────────────────────────────── */

function submit() {
    form.transform((data) => ({
        // PUT + archivos no combina bien en PHP: se manda siempre por POST
        // con `_method` (mismo patrón que Usuarios/Index.vue y
        // OrdenesCompraCliente/Index.vue).
        ...(esEdicion.value ? { _method: 'put' } : {}),
        ...data,
        descuento: Number(data.descuento || 0),
        aplicar_iva: !!data.aplicar_iva,
        instalacion_con_iva: !!data.instalacion_con_iva,
        detalles: data.detalles.map((l) => ({
            producto_id: l.producto_id || null,
            tipo_proyecto_id: l.tipo_proyecto_id || null,
            descripcion: l.descripcion,
            ancho: l.ancho === '' ? null : Number(l.ancho),
            alto: l.alto === '' ? null : Number(l.alto),
            profundidad: l.profundidad === '' || l.profundidad === null ? null : Number(l.profundidad),
            niveles: l.niveles === '' || l.niveles === null ? null : Number(l.niveles),
            cantidad: Number(l.cantidad),
            instalacion: instalacionDe(l),
            instalacion_detalle: l.instalacion_detalle
                ? {
                    personas: Number(l.instalacion_detalle.personas || 0),
                    horas: Number(l.instalacion_detalle.horas || 0),
                    costo_hora: Number(l.instalacion_detalle.costo_hora || 0),
                    nocturna: !!l.instalacion_detalle.nocturna,
                    recargo_nocturno: Number(l.instalacion_detalle.recargo_nocturno || 0),
                    transporte: Number(l.instalacion_detalle.transporte || 0),
                }
                : null,
            precio_manual: l.items.length === 0 ? 'SI' : l.precio_manual,
            precio_unitario: Number(l.precio_unitario || 0),
            imagen: l.imagen,
            usar_imagen_producto: l.usarImagenProducto,
            imagen_actual: l.imagenActualRuta,
            items: l.items.map((i) => ({
                material_id: i.material_id || null,
                tipo: i.tipo,
                descripcion: i.descripcion,
                unidad: i.unidad || null,
                cantidad: Number(i.cantidad || 0),
                costo_unitario: Number(i.costo_unitario || 0),
            })),
        })),
    }));

    const opciones = {
        onError: (errors) => showError(errors),
    };

    if (esEdicion.value) {
        form.post(route('cotizaciones.update', props.cotizacion.id), opciones);
    } else {
        form.post(route('cotizaciones.store'), opciones);
    }
}
</script>

<template>
    <!-- Envoltorio para que ClienteQuickCreateModal (con su propio <form>)
         quede FUERA del <form> de la cotización — un <form> anidado es HTML
         inválido y el navegador lo reacomoda de forma impredecible. El
         componente sigue teniendo una única raíz (ver .ai/rules, Vue
         components must have a single root element). -->
    <div>
    <form @submit.prevent="submit">
        <!-- Cabecera -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Datos de la cotización</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0" for="cliente_id">Cliente</label>
                                <button v-can="'clientes.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                    @click="mostrarModalCliente = true">
                                    <i class="fa-solid fa-plus"></i>
                                    Nuevo cliente
                                </button>
                            </div>
                            <SearchableSelect id="cliente_id" v-model="form.cliente_id" :options="clientesDisponibles"
                                :option-label="etiquetaCliente" placeholder="Selecciona un cliente"
                                :invalid="!!form.errors.cliente_id" />
                            <p v-if="form.errors.cliente_id" class="form-error">{{ form.errors.cliente_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0" for="empleado_id">Vendedor</label>
                                <button v-can="'empleados.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                    @click="mostrarModalEmpleado = true">
                                    <i class="fa-solid fa-plus"></i>
                                    Nuevo empleado
                                </button>
                            </div>
                            <SearchableSelect id="empleado_id" v-model="form.empleado_id" :options="empleadosDisponibles"
                                :option-label="etiquetaEmpleado" placeholder="Selecciona un vendedor"
                                :invalid="!!form.errors.empleado_id" />
                            <p v-if="form.errors.empleado_id" class="form-error">{{ form.errors.empleado_id }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="sucursal_id">Sucursal</label>
                            <select id="sucursal_id" v-model="form.sucursal_id" class="form-control"
                                :class="{ 'is-invalid': form.errors.sucursal_id }" required>
                                <option value="" disabled>Selecciona una sucursal</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">
                                    {{ s.nombre }}<span v-if="s.ciudad"> — {{ s.ciudad }}</span>
                                </option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="form-error">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="fecha">Fecha</label>
                            <input id="fecha" v-model="form.fecha" type="date" class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha }" required />
                            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label" for="fecha_vencimiento">Válida hasta</label>
                            <input id="fecha_vencimiento" v-model="form.fecha_vencimiento" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_vencimiento }" />
                            <p v-if="form.errors.fecha_vencimiento" class="form-error">
                                {{ form.errors.fecha_vencimiento }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle: una hoja de costos por ítem -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Detalle del presupuesto</span>
                <button type="button" class="btn btn-primary btn-sm" @click="agregarLinea">
                    <i class="fa-solid fa-plus"></i>
                    Agregar ítem
                </button>
            </div>
            <div class="card-body">
                <p v-if="typeof form.errors.detalles === 'string'" class="form-error mb-3">
                    {{ form.errors.detalles }}
                </p>

                <div v-for="(linea, index) in form.detalles" :key="index" class="cotizacion-linea">
                    <div class="cotizacion-linea-head">
                        <span class="fw-semibold fs-sm">Ítem {{ index + 1 }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="linea.items.length" :class="CLASES_SEMAFORO[margenLinea(linea).estado]">
                                <span class="semaforo-punto"></span>
                                {{ margenLinea(linea).recomendacion }}
                            </span>
                            <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                                :disabled="form.detalles.length === 1" aria-label="Quitar ítem"
                                @click="quitarLinea(index)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label mb-0">Producto</label>
                                    <button v-can="'productos.crear'" type="button" class="btn btn-sm btn-soft-primary"
                                        @click="abrirModalProducto(index)">
                                        <i class="fa-solid fa-plus"></i>
                                        Nuevo
                                    </button>
                                </div>
                                <SearchableSelect v-model="linea.producto_id" :options="productosDisponibles"
                                    option-label="nombre" placeholder="Ítem personalizado"
                                    @update:model-value="onProductoChange(index)" />
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label">Tipo de proyecto</label>
                                <select v-model="linea.tipo_proyecto_id" class="form-control">
                                    <option value="">Sin nivel (margen por defecto)</option>
                                    <option v-for="t in tiposProyecto" :key="t.id" :value="t.id">
                                        {{ t.nombre }} — ×{{ Number(t.factor_complejidad).toFixed(2) }} ·
                                        {{ pct(t.margen_minimo) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Lo que lee el cliente: el primer renglón hace de título del
                         ítem; los siguientes, el detalle (estructura, acabado,
                         impresión). El PDF respeta los saltos de línea. -->
                    <div class="form-group">
                        <label class="form-label" :for="`linea-${index}-descripcion`">Descripción</label>
                        <textarea :id="`linea-${index}-descripcion`" v-model="linea.descripcion" v-max-length="2000"
                            class="form-control" rows="4"
                            :class="{ 'is-invalid': form.errors[`detalles.${index}.descripcion`] }"
                            placeholder="Elaborado de cabecera&#10;Base metálica montada con trupan MDF, pintado completo…"
                            required></textarea>
                        <p v-if="form.errors[`detalles.${index}.descripcion`]" class="form-error">
                            {{ form.errors[`detalles.${index}.descripcion`] }}
                        </p>
                        <p v-else class="fs-xs text-muted mt-1 mb-0">
                            Lo que verá el cliente. El primer renglón es el título del ítem.
                        </p>
                    </div>

                    <!-- Imagen referencial: subida a mano o copiada del producto del
                         catálogo (se convierte/re-codifica a JPG al guardar). -->
                    <div class="form-group">
                        <label class="form-label">Imagen referencial (opcional)</label>
                        <div class="cotizacion-linea-imagen">
                            <FileDropzone v-model="linea.imagen" :preview="previewExistente(linea)" accept="image/*"
                                :invalid="!!form.errors[`detalles.${index}.imagen`]"
                                @update:model-value="onImagenSeleccionada(index)" />
                            <div class="d-flex flex-wrap gap-2">
                                <button v-if="productoDe(linea.producto_id)?.imagen_url" type="button"
                                    class="btn btn-sm btn-soft-info" :disabled="linea.usarImagenProducto"
                                    @click="usarImagenDelProducto(index)">
                                    <i class="fa-solid fa-copy"></i>
                                    Usar imagen del producto
                                </button>
                                <button v-if="linea.imagen || previewExistente(linea)" type="button"
                                    class="btn btn-sm btn-soft-secondary" @click="quitarImagenLinea(index)">
                                    <i class="fa-solid fa-xmark"></i>
                                    Quitar imagen
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors[`detalles.${index}.imagen`]" class="form-error">
                            {{ form.errors[`detalles.${index}.imagen`] }}
                        </p>
                    </div>

                    <div class="row">
                        <template v-if="pideMedidas(linea)">
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-ancho`">Ancho (m)</label>
                                    <input :id="`linea-${index}-ancho`" v-model="linea.ancho" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-alto`">Alto (m)</label>
                                    <input :id="`linea-${index}-alto`" v-model="linea.alto" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-profundidad`">Profundidad (m)</label>
                                    <input :id="`linea-${index}-profundidad`" v-model="linea.profundidad" v-decimal="2"
                                        type="text" inputmode="decimal" class="form-control" placeholder="Opcional"
                                        :class="{ 'is-invalid': form.errors[`detalles.${index}.profundidad`] }" />
                                    <p v-if="form.errors[`detalles.${index}.profundidad`]" class="form-error">
                                        {{ form.errors[`detalles.${index}.profundidad`] }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-niveles`">Niveles</label>
                                    <input :id="`linea-${index}-niveles`" v-model="linea.niveles" v-entero="1"
                                        type="text" inputmode="numeric" class="form-control" placeholder="Opcional"
                                        :class="{ 'is-invalid': form.errors[`detalles.${index}.niveles`] }" />
                                    <p v-if="form.errors[`detalles.${index}.niveles`]" class="form-error">
                                        {{ form.errors[`detalles.${index}.niveles`] }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="medidaDeCobro(linea)" class="col-lg-2 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-area`">
                                        {{ medidaDeCobro(linea).etiqueta }}
                                    </label>
                                    <input :id="`linea-${index}-area`"
                                        :value="medidaDeCobro(linea).valor.toFixed(2)"
                                        type="text" class="form-control" disabled />
                                    <p class="fs-xs text-muted mt-1 mb-0">Interna, no se imprime</p>
                                </div>
                            </div>
                        </template>

                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <label class="form-label">Cantidad</label>
                                <input v-model="linea.cantidad" v-decimal="2" type="text" inputmode="decimal"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`detalles.${index}.cantidad`] }" required />
                                <p v-if="form.errors[`detalles.${index}.cantidad`]" class="form-error">
                                    {{ form.errors[`detalles.${index}.cantidad`] }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label mb-0" :for="`linea-${index}-instalacion`">
                                        Instalación (Bs)
                                    </label>
                                    <button v-if="!linea.instalacion_detalle" type="button"
                                        class="btn btn-sm btn-soft-primary instalacion-calcular"
                                        title="Calcular con personas, horas, horario nocturno y transporte"
                                        @click="activarCalculoInstalacion(linea)">
                                        <i class="fa-solid fa-calculator"></i>
                                        Calcular
                                    </button>
                                </div>
                                <input v-if="linea.instalacion_detalle" :id="`linea-${index}-instalacion`"
                                    :value="instalacionDe(linea).toFixed(2)" type="text" class="form-control fw-semibold"
                                    disabled />
                                <input v-else :id="`linea-${index}-instalacion`" v-model="linea.instalacion" v-decimal="2"
                                    type="text" inputmode="decimal" class="form-control" placeholder="0.00" />
                                <p class="fs-xs text-muted mt-1 mb-0">{{ ayudaInstalacion(linea) }}</p>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="form-group">
                                <label class="form-label">Subtotal</label>
                                <input :value="money(subtotalLinea(linea))" type="text" class="form-control fw-semibold"
                                    disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Instalación calculada: personal, horas (con recargo nocturno) y
                         transporte. Se suma igual que el monto a mano: después del
                         precio, con o sin IVA según la casilla de Totales. -->
                    <div v-if="linea.instalacion_detalle" class="instalacion-calculo">
                        <div class="hoja-costos-head">
                            <span class="fw-semibold fs-sm">
                                <i class="fa-solid fa-screwdriver-wrench text-primary"></i>
                                Cálculo de la instalación
                            </span>
                            <button type="button" class="btn btn-sm btn-soft-secondary"
                                @click="quitarCalculoInstalacion(linea)">
                                <i class="fa-solid fa-pen"></i>
                                Escribir monto a mano
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-inst-personas`">Personas</label>
                                    <input :id="`linea-${index}-inst-personas`" v-model="linea.instalacion_detalle.personas"
                                        v-entero="1" type="text" inputmode="numeric" class="form-control" />
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-inst-horas`">Horas</label>
                                    <input :id="`linea-${index}-inst-horas`" v-model="linea.instalacion_detalle.horas"
                                        v-decimal="2" type="text" inputmode="decimal" class="form-control"
                                        placeholder="0" />
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-inst-costo`">Bs por hora</label>
                                    <input :id="`linea-${index}-inst-costo`" v-model="linea.instalacion_detalle.costo_hora"
                                        v-decimal="2" type="text" inputmode="decimal" class="form-control" />
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="form-group">
                                    <label class="form-label" :for="`linea-${index}-inst-transporte`">
                                        Transporte (Bs)
                                    </label>
                                    <input :id="`linea-${index}-inst-transporte`"
                                        v-model="linea.instalacion_detalle.transporte" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                        </div>

                        <div class="instalacion-nocturna">
                            <label class="d-flex align-items-center gap-2 mb-0" :for="`linea-${index}-inst-nocturna`">
                                <input :id="`linea-${index}-inst-nocturna`" v-model="linea.instalacion_detalle.nocturna"
                                    type="checkbox" />
                                <i class="fa-solid fa-moon text-primary" aria-hidden="true"></i>
                                Instalación nocturna (horas extra)
                            </label>
                            <div v-if="linea.instalacion_detalle.nocturna" class="instalacion-recargo">
                                <label class="mb-0" :for="`linea-${index}-inst-recargo`">Recargo</label>
                                <input :id="`linea-${index}-inst-recargo`"
                                    v-model="linea.instalacion_detalle.recargo_nocturno" v-decimal="2" type="text"
                                    inputmode="decimal" class="form-control" />
                                <span>%</span>
                            </div>
                        </div>

                        <p v-for="(mensaje, mi) in erroresInstalacion(index)" :key="mi" class="form-error">
                            {{ mensaje }}
                        </p>

                        <div class="hoja-costos-total">
                            <span class="fw-normal">
                                {{ resumenInstalacion(linea.instalacion_detalle) }}
                                = {{ money(manoObraInstalacion(linea.instalacion_detalle)) }}
                                <template v-if="Number(linea.instalacion_detalle.transporte)">
                                    · transporte {{ money(linea.instalacion_detalle.transporte) }}
                                </template>
                            </span>
                            <span>{{ money(instalacionDe(linea)) }}</span>
                        </div>
                    </div>

                    <!-- Hoja de costos: los insumos que componen UNA unidad -->
                    <div class="hoja-costos">
                        <div class="hoja-costos-head">
                            <span class="fw-semibold fs-sm">
                                <i class="fa-solid fa-table-list text-primary"></i>
                                Hoja de costos (por unidad)
                            </span>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-soft-info"
                                    :disabled="!linea.producto_id || costeo[index].cargando"
                                    title="Trae los materiales del producto para estas medidas"
                                    @click="traerInsumos(index)">
                                    <i v-if="costeo[index].cargando" class="fa-solid fa-spinner fa-spin"></i>
                                    <i v-else class="fa-solid fa-boxes-stacked"></i>
                                    Traer insumos del producto
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-primary" @click="agregarItem(index)">
                                    <i class="fa-solid fa-plus"></i>
                                    Insumo
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary"
                                    :title="`Agrega horas de trabajo a ${money(config.costo_hora_mano_obra)} la hora`"
                                    @click="agregarItem(index, { tipo: 'MANO_OBRA', descripcion: 'Horas de mano de obra', unidad: 'HORA', costo_unitario: config.costo_hora_mano_obra })">
                                    <i class="fa-solid fa-user-clock"></i>
                                    Mano de obra
                                </button>
                            </div>
                        </div>

                        <p v-if="costeo[index].error" class="form-error">{{ costeo[index].error }}</p>

                        <div v-if="costoBaseDesactualizado(linea)" class="hoja-costos-aviso">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            El costo base del producto cambió a {{ money(costoBaseDesactualizado(linea)) }}.
                            <button type="button" class="btn btn-sm btn-soft-warning"
                                :disabled="costeo[index].cargando" @click="traerInsumos(index)">
                                Actualizar costos
                            </button>
                        </div>

                        <p v-if="!linea.items.length" class="hoja-costos-vacia">
                            Sin insumos cargados: el precio de este ítem se escribe a mano.
                            Traé los insumos del producto o agregá otros para que el sistema calcule el precio y la
                            rentabilidad.
                        </p>

                        <template v-else>
                            <div class="hoja-costos-labels">
                                <span class="col-span-2">Tipo</span>
                                <span class="col-span-4">Descripción</span>
                                <span class="col-span-1">Unidad</span>
                                <span class="col-span-2">Cantidad</span>
                                <span class="col-span-2">Costo unit.</span>
                                <span class="col-span-1">Subtotal</span>
                            </div>

                            <div v-for="(item, itemIndex) in linea.items" :key="itemIndex" class="hoja-costos-fila">
                                <div class="col-span-12 lg:col-span-2">
                                    <select v-model="item.tipo" class="form-control">
                                        <option v-for="(etiqueta, valor) in tiposItem" :key="valor" :value="valor">
                                            {{ etiqueta }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-span-12 lg:col-span-4">
                                    <input v-model="item.descripcion" type="text" class="form-control"
                                        placeholder="Fierro, plancha, horas..." required />
                                </div>
                                <div class="col-span-4 lg:col-span-1">
                                    <input v-model="item.unidad" type="text" class="form-control" placeholder="m²" />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.cantidad" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" required />
                                </div>
                                <div class="col-span-4 lg:col-span-2">
                                    <input v-model="item.costo_unitario" v-decimal="4" type="text" inputmode="decimal"
                                        class="form-control" required />
                                </div>
                                <div class="col-span-12 lg:col-span-1 d-flex align-items-center gap-2">
                                    <span class="fs-sm fw-semibold flex-1">{{ money(subtotalItem(item)) }}</span>
                                    <button type="button" class="btn btn-sm btn-icon btn-soft-danger"
                                        aria-label="Quitar insumo" @click="quitarItem(index, itemIndex)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="hoja-costos-total">
                                <span>Costo base (1 unidad)</span>
                                <span>{{ money(costoBaseLinea(linea)) }}</span>
                            </div>
                        </template>
                    </div>

                    <!-- Precio de la línea: sugerido por el motor o tomado a mano -->
                    <div class="cotizacion-linea-foot">
                        <div class="row">
                            <div class="col-lg-7">
                                <div class="margen-panel margen-interno">
                                    <div class="margen-row">
                                        <span>Costo ajustado (× {{ factorDe(linea).toFixed(2) }})</span>
                                        <span>{{ money(motorLinea(linea).costoAjustado) }}</span>
                                    </div>
                                    <div class="margen-row">
                                        <span>Precio sugerido (margen {{ pct(margenDe(linea)) }})</span>
                                        <span>{{ money(motorLinea(linea).precio) }}</span>
                                    </div>
                                    <div v-if="tienePrecioFijado(linea)" class="margen-row">
                                        <span>Precio fijado a mano (margen {{ pct(margenFijado(linea)) }})</span>
                                        <span>{{ money(precioUnitario(linea)) }}</span>
                                    </div>
                                    <div class="margen-row margen-row-destacada">
                                        <span>Utilidad real de la línea (después de IT e IUE)</span>
                                        <span>
                                            {{ money(margenLinea(linea).utilidadReal) }}
                                            <span class="fs-xs text-muted">
                                                ({{ pct(margenLinea(linea).rentabilidad) }} del costo)
                                            </span>
                                        </span>
                                    </div>
                                    <div class="margen-barra">
                                        <div :class="claseBarra(margenLinea(linea).estado)"
                                            :style="{ width: anchoBarra(margenLinea(linea).rentabilidad) }"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="form-group">
                                    <label class="form-label">Precio unitario (Bs)</label>
                                    <!-- Con hoja de costos y sin tomar el control, el precio lo
                                         manda el motor: se muestra en solo lectura para que no
                                         parezca editable y quede pisado al guardar. -->
                                    <input v-if="linea.precio_manual !== 'SI' && linea.items.length"
                                        :value="motorLinea(linea).precio.toFixed(2)" type="text"
                                        class="form-control fw-semibold" disabled />
                                    <input v-else v-model="linea.precio_unitario" v-decimal="2" type="text"
                                        inputmode="decimal" class="form-control"
                                        :class="{ 'is-invalid': form.errors[`detalles.${index}.precio_unitario`] }"
                                        required />
                                    <p v-if="form.errors[`detalles.${index}.precio_unitario`]" class="form-error">
                                        {{ form.errors[`detalles.${index}.precio_unitario`] }}
                                    </p>

                                    <div v-if="linea.items.length" class="mt-2">
                                        <button v-if="linea.precio_manual !== 'SI'" type="button"
                                            class="btn btn-sm btn-soft-warning" @click="tomarControlDelPrecio(linea)">
                                            <i class="fa-solid fa-pen"></i>
                                            Fijar precio a mano
                                        </button>
                                        <button v-else type="button" class="btn btn-sm btn-soft-info"
                                            @click="devolverPrecioAlMotor(linea)">
                                            <i class="fa-solid fa-rotate-left"></i>
                                            Volver al precio sugerido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Redondeo a unidad de compra: viene del costeo del BOM -->
                        <ul v-if="costeo[index].info?.lineas?.some((m) => m.redondeada)" class="cotizacion-costeo-lista">
                            <li v-for="(m, mi) in costeo[index].info.lineas.filter((l) => l.redondeada)" :key="mi">
                                {{ m.material }}: {{ Number(m.cantidad_bruta) }} →
                                <strong>{{ Number(m.cantidad) }}</strong> {{ m.unidad }}
                                <span class="text-info">(redondeado a unidad de compra)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Totales + rentabilidad + observaciones -->
        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header"><span class="card-title">Condiciones de venta</span></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="form-label" for="forma_pago">Forma de pago</label>
                                    <input id="forma_pago" v-model="form.forma_pago" v-max-length="255" type="text"
                                        class="form-control" :class="{ 'is-invalid': form.errors.forma_pago }"
                                        placeholder="15 días crédito" />
                                    <p v-if="form.errors.forma_pago" class="form-error">{{ form.errors.forma_pago }}</p>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="form-label" for="tiempo_entrega">Tiempo de entrega</label>
                                    <input id="tiempo_entrega" v-model="form.tiempo_entrega" v-max-length="255" type="text"
                                        class="form-control" :class="{ 'is-invalid': form.errors.tiempo_entrega }"
                                        placeholder="10 días hábiles" />
                                    <p v-if="form.errors.tiempo_entrega" class="form-error">
                                        {{ form.errors.tiempo_entrega }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="form-label" for="lugar_entrega">Lugar de entrega</label>
                                    <input id="lugar_entrega" v-model="form.lugar_entrega" v-max-length="255" type="text"
                                        class="form-control" :class="{ 'is-invalid': form.errors.lugar_entrega }"
                                        placeholder="Instalado en el punto de venta" />
                                    <p v-if="form.errors.lugar_entrega" class="form-error">
                                        {{ form.errors.lugar_entrega }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label" for="observaciones">Observaciones</label>
                            <textarea id="observaciones" v-model="form.observaciones" class="form-control" rows="4"
                                :class="{ 'is-invalid': form.errors.observaciones }"
                                placeholder="Notas para el cliente..."></textarea>
                            <p v-if="form.errors.observaciones" class="form-error">{{ form.errors.observaciones }}</p>
                            <p v-else class="fs-xs text-muted mt-1 mb-0">
                                Se imprimen en el documento. Lo que quede vacío no aparece.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Panel interno: nunca se imprime (clase .margen-interno) -->
                <div class="card mb-4 margen-interno">
                    <div class="card-header">
                        <span class="card-title">Rentabilidad del presupuesto</span>
                        <span :class="CLASES_SEMAFORO[motorTotal.estado]">
                            <span class="semaforo-punto"></span>
                            {{ motorTotal.recomendacion }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="margen-row">
                            <span>Costo de insumos</span>
                            <span>{{ money(costoBaseTotal) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>Costo ajustado por complejidad</span>
                            <span>{{ money(costoAjustadoTotal) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IT ({{ pct(config.impuestos.it) }})</span>
                            <span>− {{ money(motorTotal.it) }}</span>
                        </div>
                        <div class="margen-row">
                            <span>IUE ({{ pct(config.impuestos.iue) }})</span>
                            <span>− {{ money(motorTotal.iue) }}</span>
                        </div>
                        <div class="margen-row margen-row-destacada">
                            <span>Utilidad real</span>
                            <span>
                                {{ money(motorTotal.utilidadReal) }}
                                <span class="fs-xs text-muted">({{ pct(motorTotal.rentabilidad) }} del costo)</span>
                            </span>
                        </div>
                        <div class="margen-barra">
                            <div :class="claseBarra(motorTotal.estado)"
                                :style="{ width: anchoBarra(motorTotal.rentabilidad) }"></div>
                        </div>
                        <p class="fs-xs text-muted mt-2 mb-0">
                            Verde: utilidad sobre {{ pct(config.semaforo.umbral_verde) }} del costo ajustado ·
                            Amarillo: sobre {{ pct(config.semaforo.umbral_amarillo) }} ·
                            Rojo: por debajo. Información interna, no se imprime en la cotización.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><span class="card-title">Totales</span></div>
                    <div class="card-body">
                        <div class="cotizacion-total-row">
                            <span>Subtotal</span>
                            <span class="fw-semibold">{{ money(subtotal) }}</span>
                        </div>
                        <div class="cotizacion-total-row">
                            <label class="mb-0" for="descuento">Descuento (Bs)</label>
                            <input id="descuento" v-model="form.descuento" v-decimal="2" type="text"
                                inputmode="decimal" class="form-control cotizacion-total-input" />
                        </div>
                        <div class="cotizacion-total-row">
                            <label class="mb-0 d-flex align-items-center gap-2" for="aplicar_iva">
                                <input id="aplicar_iva" v-model="form.aplicar_iva" type="checkbox" />
                                IVA {{ pct(config.impuestos.iva) }}
                            </label>
                            <span class="fw-semibold">{{ money(iva) }}</span>
                        </div>
                        <div v-if="instalacionTotal > 0" class="cotizacion-total-row">
                            <span>Instalación</span>
                            <span class="fw-semibold">{{ money(instalacionTotal) }}</span>
                        </div>
                        <div v-if="instalacionTotal > 0" class="cotizacion-total-row cotizacion-total-opcion">
                            <label class="mb-0 d-flex align-items-center gap-2" for="instalacion_con_iva"
                                :class="{ 'text-muted': !form.aplicar_iva }">
                                <input id="instalacion_con_iva" v-model="form.instalacion_con_iva" type="checkbox"
                                    :disabled="!form.aplicar_iva" />
                                Cobrar IVA también sobre la instalación
                            </label>
                        </div>
                        <div class="cotizacion-total-row cotizacion-total-grand">
                            <span>Total</span>
                            <span>{{ money(total) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <Link :href="route('cotizaciones.index')" class="btn btn-soft-secondary">Cancelar</Link>
            <button type="submit" class="btn btn-primary" :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-floppy-disk"></i>
                {{ esEdicion ? 'Guardar cambios' : 'Crear cotización' }}
            </button>
        </div>
    </form>

    <ClienteQuickCreateModal :show="mostrarModalCliente" @close="mostrarModalCliente = false"
        @created="onClienteCreado" />

    <QuickCreateModal :show="mostrarModalEmpleado" title="Nuevo empleado" route-name="empleados.rapido"
        :initial-data="empleadoVacio" submit-label="Crear empleado"
        hint="Se guarda de una vez en el catálogo de empleados; al terminar queda elegido como vendedor."
        @close="mostrarModalEmpleado = false" @created="onEmpleadoCreado">
        <template #default="{ form: empleadoForm, errors: empleadoErrors }">
            <EmpleadoFormFields :form="empleadoForm" :errors="empleadoErrors" :sucursales="sucursales" :areas="areas"
                :cargos="cargosEmpleado" />
        </template>
    </QuickCreateModal>

    <QuickCreateModal :show="mostrarModalProducto" title="Nuevo producto" route-name="productos.rapido"
        :initial-data="productoVacio" submit-label="Crear producto"
        hint="Se guarda de una vez en el catálogo de productos (sin imagen — se puede agregar después desde Productos); al terminar queda elegido en esta línea."
        @close="mostrarModalProducto = false" @created="onProductoCreado">
        <template #default="{ form: productoForm, errors: productoErrors }">
            <ProductoFormFields :form="productoForm" :errors="productoErrors"
                :categorias-producto="categoriasProductoDisponibles" />
        </template>
    </QuickCreateModal>
    </div>
</template>
