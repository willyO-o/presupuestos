/**
 * Menu de navegacion del sidebar (ver resources/js/Components/Layout/Sidebar.vue).
 *
 * JavaScript plano a proposito (sin TypeScript). Es una version simplificada de un
 * menu multi-nivel mas avanzado: aqui solo hay 2 formas de entrada y un maximo de
 * 3 niveles de profundidad (item -> children -> children.children).
 *
 * Separador de seccion (encabezado en mayusculas dentro del sidebar):
 *   { menutitle: string, permission: string|string[] }
 *
 * Item de menu (JSDoc solo como referencia, no se valida en runtime):
 *   @typedef {Object} MenuItem
 *   @property {string} title
 *   @property {string} [icon]        Clase de icono Font Awesome o MDI, ej. "fa-solid fa-gauge-high"
 *                                     o "mdi mdi-truck". No se usan SVG aqui (ver skill
 *                                     "vue-custom-directives" / regla de iconos del proyecto).
 *   @property {'link'|'sub'} type
 *   @property {string} [path]        Requerido si type es 'link'. Es la URL final (no un nombre
 *                                     de ruta de Ziggy), Sidebar.vue la usa directo en <Link :href>.
 *   @property {MenuItem[]} [children] Requerido si type es 'sub'. Puede volver a contener items
 *                                     type 'sub' (eso ya es el nivel 3, el maximo soportado).
 *   @property {string|string[]} permission Permiso(s) de Spatie que exige v-can. OBLIGATORIO en
 *                                     todo item, incluso en los que "cualquiera deberia ver": la
 *                                     directiva v-can oculta el elemento si el valor viene vacio
 *                                     (falla cerrado a proposito), asi que un item sin permission
 *                                     desaparece para cualquier usuario que no sea super-admin.
 */

export const NAV_MENU = [
    { menutitle: 'Principal', permission: 'dashboard.ver' },
    {
        title: 'Dashboard',
        icon: 'fa-solid fa-gauge-high',
        type: 'link',
        path: '/dashboard',
        permission: 'dashboard.ver',
    },

    {
        menutitle: 'Gestión',
        permission: [
            'vales.ver',
            'cargas-combustible.ver',
            'operacion-diaria.ver',
            'mantenimiento.solicitudes.ver',
            'mantenimiento.ordenes.ver',
        ],
    },
    {
        title: 'Vales',
        icon: 'fa-solid fa-ticket',
        type: 'link',
        path: '/vales',
        permission: 'vales.ver',
    },
    {
        title: 'Cargas de Combustible',
        icon: 'fa-solid fa-gas-pump',
        type: 'link',
        path: '/cargas',
        permission: 'cargas-combustible.ver',
    },
    {
        title: 'Operación Diaria',
        icon: 'fa-solid fa-list-check',
        type: 'sub',
        permission: ['operacion-diaria.ver', 'operacion-diaria.crear'],
        children: [
            {
                title: 'Actividades',
                type: 'link',
                path: '/operacion-diaria',
                permission: 'operacion-diaria.ver',
            },
            {
                title: 'Registrar Actividad',
                type: 'link',
                path: '/operacion-diaria/create',
                permission: 'operacion-diaria.crear',
            },
        ],
    },
    {
        title: 'Mantenimiento',
        icon: 'fa-solid fa-screwdriver-wrench',
        type: 'sub',
        permission: [
            'mantenimiento.solicitudes.ver',
            'mantenimiento.solicitudes.crear',
            'mantenimiento.ordenes.ver',
        ],
        children: [
            {
                title: 'Solicitudes',
                type: 'link',
                path: '/mantenimiento/solicitudes',
                permission: 'mantenimiento.solicitudes.ver',
            },
            {
                title: 'Crear Solicitud',
                type: 'link',
                path: '/mantenimiento/solicitudes/crear',
                permission: 'mantenimiento.solicitudes.crear',
            },
            {
                title: 'Órdenes de Trabajo',
                type: 'link',
                path: '/mantenimiento/ordenes',
                permission: 'mantenimiento.ordenes.ver',
            },
        ],
    },

    {
        menutitle: 'Reportes',
        permission: [
            'cargas-combustible.reporte',
            'cargas-combustible.reporte.rendimiento',
        ],
    },
    {
        title: 'Reportes de Combustible',
        icon: 'fa-solid fa-chart-column',
        type: 'link',
        path: '/reportes/cargas-combustible',
        permission: 'cargas-combustible.reporte',
    },
    {
        title: 'Rendimiento de Combustible',
        icon: 'fa-solid fa-chart-line',
        type: 'link',
        path: '/reportes/cargas-combustible/rendimiento',
        permission: 'cargas-combustible.reporte.rendimiento',
    },

    {
        menutitle: 'Catálogos',
        permission: [
            'sucursales.ver',
            'categorias-material.ver',
            'categorias-producto.ver',
            'conductores.ver',
            'vehiculos.ver',
            'grifos.ver',
            'tipos-combustible.ver',
            'tipos-mantenimiento.ver',
            'tipos-vehiculo.ver',
            'grupos-vehiculo.ver',
            'repuestos.ver',
        ],
    },
    {
        title: 'Sucursales',
        icon: 'fa-solid fa-building-user',
        type: 'link',
        path: '/sucursales',
        permission: 'sucursales.ver',
    },
    {
        title: 'Categorías de Material',
        icon: 'fa-solid fa-boxes-stacked',
        type: 'link',
        path: '/categorias-material',
        permission: 'categorias-material.ver',
    },
    {
        title: 'Categorías de Producto',
        icon: 'fa-solid fa-tags',
        type: 'link',
        path: '/categorias-producto',
        permission: 'categorias-producto.ver',
    },


    {
        menutitle: 'Administración',
        permission: [
            'usuarios.ver',
            'personas.ver',
            'areas.ver',
            'roles.ver',
            'parametros-empresa.ver',
        ],
    },
    {
        title: 'Personas',
        icon: 'fa-solid fa-user',
        type: 'link',
        path: '/personas',
        permission: 'personas.ver',
    },
    {
        title: 'Áreas',
        icon: 'fa-solid fa-building',
        type: 'link',
        path: '/areas',
        permission: 'areas.ver',
    },

];
