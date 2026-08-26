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
 *
 * Módulos alineados a `.claude/skills/xtrapubli-design-system/references/database-design.md`
 * (sistema de costos y presupuestos XtraPubli) y a `config/acl.php` — ver `.ai/rules/config.md`.
 * OJO: a la fecha solo Sucursales, Categorías de Material y Categorías de Producto tienen
 * pantalla real (`resources/js/Pages/**`); el resto de items de este menú son la navegación
 * ya preparada para los módulos que faltan por construir — sus `path` no tienen ruta/página
 * Inertia todavía (404 hasta que se implementen), quedan visibles a proposito para ir
 * completando el sistema sin tener que retocar el menú en cada módulo nuevo.
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
        menutitle: 'Ventas',
        permission: [
            'cotizaciones.ver',
            'pedidos.ver',
            'ordenes-compra-cliente.ver',
            'notas-entrega.ver',
            'pagos.ver',
        ],
    },
    {
        title: 'Cotizaciones',
        icon: 'fa-solid fa-file-invoice-dollar',
        type: 'link',
        path: '/cotizaciones',
        permission: 'cotizaciones.ver',
    },
    {
        title: 'Pedidos',
        icon: 'fa-solid fa-dolly',
        type: 'link',
        path: '/pedidos',
        permission: 'pedidos.ver',
    },
    {
        title: 'Órdenes de Compra',
        icon: 'fa-solid fa-file-contract',
        type: 'link',
        path: '/ordenes-compra-cliente',
        permission: 'ordenes-compra-cliente.ver',
    },
    {
        title: 'Notas de Entrega',
        icon: 'fa-solid fa-truck-ramp-box',
        type: 'link',
        path: '/notas-entrega',
        permission: 'notas-entrega.ver',
    },
    {
        title: 'Pagos',
        icon: 'fa-solid fa-money-check-dollar',
        type: 'link',
        path: '/pagos',
        permission: 'pagos.ver',
    },

    {
        menutitle: 'Clientes',
        permission: 'clientes.ver',
    },
    {
        title: 'Clientes',
        icon: 'fa-solid fa-people-group',
        type: 'link',
        path: '/clientes',
        permission: 'clientes.ver',
    },


    {
        menutitle: 'Reportes',
        permission: ['reportes.financiero', 'reportes.produccion', 'reportes.bi'],
    },
    {
        title: 'Reporte Financiero',
        icon: 'fa-solid fa-chart-column',
        type: 'link',
        path: '/reportes/financiero',
        permission: 'reportes.financiero',
    },
    {
        title: 'Reporte de Producción',
        icon: 'fa-solid fa-industry',
        type: 'link',
        path: '/reportes/produccion',
        permission: 'reportes.produccion',
    },
    {
        title: 'Inteligencia de Negocios',
        icon: 'fa-solid fa-chart-line',
        type: 'link',
        path: '/reportes/bi',
        permission: 'reportes.bi',
    },

    {
        menutitle: 'Catálogo de Productos',
        permission: ['categorias-producto.ver', 'productos.ver', 'formulas.ver'],
    },
    {
        title: 'Categorías de Producto',
        icon: 'fa-solid fa-tags',
        type: 'link',
        path: '/categorias-producto',
        permission: 'categorias-producto.ver',
    },
    {
        title: 'Productos',
        icon: 'fa-solid fa-box-open',
        type: 'link',
        path: '/productos',
        permission: 'productos.ver',
    },
    {
        title: 'Fórmulas',
        icon: 'fa-solid fa-flask',
        type: 'link',
        path: '/formulas',
        permission: 'formulas.ver',
    },



    {
        menutitle: 'Materiales e Insumos',
        permission: [
            'categorias-material.ver',
            'materiales.ver',
            'proveedores.ver',
            'compras.ver',
        ],
    },
    {
        title: 'Categorías de Material',
        icon: 'fa-solid fa-boxes-stacked',
        type: 'link',
        path: '/categorias-material',
        permission: 'categorias-material.ver',
    },
    {
        title: 'Materiales',
        icon: 'fa-solid fa-layer-group',
        type: 'link',
        path: '/materiales',
        permission: 'materiales.ver',
    },
    {
        title: 'Proveedores',
        icon: 'fa-solid fa-truck-field',
        type: 'link',
        path: '/proveedores',
        permission: 'proveedores.ver',
    },
    {
        title: 'Compras',
        icon: 'fa-solid fa-cart-shopping',
        type: 'link',
        path: '/compras',
        permission: 'compras.ver',
    },
    {
        menutitle: 'Organización',
        permission: ['sucursales.ver', 'areas.ver', 'empleados.ver'],
    },
    {
        title: 'Sucursales',
        icon: 'fa-solid fa-building-user',
        type: 'link',
        path: '/sucursales',
        permission: 'sucursales.ver',
    },
    {
        title: 'Áreas',
        icon: 'fa-solid fa-building',
        type: 'link',
        path: '/areas',
        permission: 'areas.ver',
    },
    {
        title: 'Empleados',
        icon: 'fa-solid fa-id-badge',
        type: 'link',
        path: '/empleados',
        permission: 'empleados.ver',
    },

    {
        menutitle: 'Administración',
        permission: ['usuarios.ver', 'roles.ver'],
    },
    {
        title: 'Usuarios',
        icon: 'fa-solid fa-user-gear',
        type: 'link',
        path: '/usuarios',
        permission: 'usuarios.ver',
    },
    {
        title: 'Roles y Permisos',
        icon: 'fa-solid fa-user-shield',
        type: 'link',
        path: '/roles',
        permission: 'roles.ver',
    },
];
