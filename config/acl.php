<?php

/**
 * Catálogo de roles y permisos base de la aplicación (spatie/laravel-permission).
 *
 * Fuente de verdad única: el seeder `database/seeders/RolesAndPermissionsSeeder.php`
 * lee este archivo para crear los Roles/Permissions reales en base de datos —
 * para agregar un permiso nuevo, agrégalo aquí (dentro del módulo que
 * corresponda) y a los roles que deban tenerlo, después corre
 * `php artisan db:seed --class=RolesAndPermissionsSeeder`.
 *
 * Convención de nombres: "recurso.accion" (dashboard.ver, usuarios.crear,
 * usuarios.ver, ...). Deben coincidir con los `permission` que usa el
 * sidebar en resources/js/Data/Sidebar/Nav.js.
 *
 * Módulos y roles alineados a `.claude/skills/xtrapubli-design-system/references/database-design.md`
 * (sistema de costos y presupuestos XtraPubli) — reemplaza el catálogo anterior,
 * que era de una app de gestión de flota/combustible sin relación con este
 * negocio. Ver `.ai/rules/config.md`.
 *
 * 'modules' es también la fuente para la futura pantalla de "Roles y
 * Permisos": cada módulo se puede pintar como un grupo con sus acciones
 * (checkboxes) para asignar permisos a roles de forma dinámica.
 */
return [

    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.ver' => 'Ver dashboard',
            ],
        ],

        // --- Organización (sucursal, area, empleado) ---
        'sucursales' => [
            'label' => 'Sucursales',
            'permissions' => [
                'sucursales.ver' => 'Ver sucursales',
                'sucursales.crear' => 'Crear sucursales',
                'sucursales.editar' => 'Editar sucursales',
                'sucursales.eliminar' => 'Eliminar sucursales',
            ],
        ],
        'areas' => [
            'label' => 'Áreas',
            'permissions' => [
                'areas.ver' => 'Ver áreas',
                'areas.crear' => 'Crear áreas',
                'areas.editar' => 'Editar áreas',
                'areas.eliminar' => 'Eliminar áreas',
            ],
        ],
        'empleados' => [
            'label' => 'Empleados',
            'permissions' => [
                'empleados.ver' => 'Ver empleados',
                'empleados.crear' => 'Crear empleados',
                'empleados.editar' => 'Editar empleados',
                'empleados.eliminar' => 'Eliminar empleados',
            ],
        ],

        // --- Clientes ---
        'clientes' => [
            'label' => 'Clientes',
            'permissions' => [
                'clientes.ver' => 'Ver clientes',
                'clientes.crear' => 'Crear clientes',
                'clientes.editar' => 'Editar clientes',
                'clientes.eliminar' => 'Eliminar clientes',
            ],
        ],

        // --- Materiales e insumos (categoria_material, material, proveedor, compra) ---
        'categorias-material' => [
            'label' => 'Categorías de material',
            'permissions' => [
                'categorias-material.ver' => 'Ver categorías de material',
                'categorias-material.crear' => 'Crear categorías de material',
                'categorias-material.editar' => 'Editar categorías de material',
                'categorias-material.eliminar' => 'Eliminar categorías de material',
            ],
        ],
        'materiales' => [
            'label' => 'Materiales',
            'permissions' => [
                'materiales.ver' => 'Ver materiales',
                'materiales.crear' => 'Crear materiales',
                'materiales.editar' => 'Editar materiales',
                'materiales.eliminar' => 'Eliminar materiales',
                // Precios/costos de compra son sensibles: separado del CRUD
                // normal para poder ocultarlo a roles que solo consultan
                // stock (ej. producción), ver database-design.md 3.2/3.3.
                'materiales.ver_costos' => 'Ver costos de materiales',
            ],
        ],
        'proveedores' => [
            'label' => 'Proveedores',
            'permissions' => [
                'proveedores.ver' => 'Ver proveedores',
                'proveedores.crear' => 'Crear proveedores',
                'proveedores.editar' => 'Editar proveedores',
                'proveedores.eliminar' => 'Eliminar proveedores',
            ],
        ],
        'compras' => [
            'label' => 'Compras',
            'permissions' => [
                'compras.ver' => 'Ver compras',
                'compras.crear' => 'Registrar compras',
                'compras.aprobar' => 'Aprobar compras',
            ],
        ],

        // --- Catálogo de productos (categoria_producto, producto, producto_material) ---
        'categorias-producto' => [
            'label' => 'Categorías de producto',
            'permissions' => [
                'categorias-producto.ver' => 'Ver categorías de producto',
                'categorias-producto.crear' => 'Crear categorías de producto',
                'categorias-producto.editar' => 'Editar categorías de producto',
                'categorias-producto.eliminar' => 'Eliminar categorías de producto',
            ],
        ],
        'productos' => [
            'label' => 'Productos',
            'permissions' => [
                'productos.ver' => 'Ver productos',
                'productos.crear' => 'Crear productos',
                'productos.editar' => 'Editar productos (incluye receta/BOM)',
                'productos.eliminar' => 'Eliminar productos',
            ],
        ],
        // Catálogo de fórmulas dinámicas reutilizables (ver
        // App\Services\Calculo\FormulaCalculator y .ai/rules/calculo.md),
        // usadas por las líneas de receta/BOM de 'productos.editar'.
        'formulas' => [
            'label' => 'Fórmulas',
            'permissions' => [
                'formulas.ver' => 'Ver fórmulas',
                'formulas.crear' => 'Crear fórmulas',
                'formulas.editar' => 'Editar fórmulas',
                'formulas.eliminar' => 'Eliminar fórmulas',
            ],
        ],

        // --- Cotizaciones ---
        'cotizaciones' => [
            'label' => 'Cotizaciones',
            'permissions' => [
                'cotizaciones.ver' => 'Ver cotizaciones',
                'cotizaciones.crear' => 'Crear cotizaciones',
                'cotizaciones.editar' => 'Editar cotizaciones',
                'cotizaciones.aprobar' => 'Aprobar/rechazar cotizaciones',
                'cotizaciones.eliminar' => 'Eliminar cotizaciones',
            ],
        ],

        // --- Pedidos / órdenes de trabajo ---
        'pedidos' => [
            'label' => 'Pedidos',
            'permissions' => [
                'pedidos.ver' => 'Ver pedidos',
                'pedidos.crear' => 'Crear pedidos (desde una cotización aprobada)',
                'pedidos.asignar_area' => 'Asignar área/responsable de un pedido',
                'pedidos.actualizar_estado' => 'Actualizar estado/etapa de un pedido',
                'pedidos.ver_todas_sucursales' => 'Ver pedidos de todas las sucursales',
            ],
        ],

        // --- Documentos comerciales (orden_compra_cliente, nota_entrega) ---
        'ordenes-compra-cliente' => [
            'label' => 'Órdenes de compra de cliente',
            'permissions' => [
                'ordenes-compra-cliente.ver' => 'Ver órdenes de compra de cliente',
                'ordenes-compra-cliente.crear' => 'Registrar órdenes de compra de cliente',
                'ordenes-compra-cliente.validar' => 'Validar órdenes de compra de cliente',
            ],
        ],
        'notas-entrega' => [
            'label' => 'Notas de entrega',
            'permissions' => [
                'notas-entrega.ver' => 'Ver notas de entrega',
                'notas-entrega.crear' => 'Crear notas de entrega',
            ],
        ],

        // --- Pagos ---
        'pagos' => [
            'label' => 'Pagos',
            'permissions' => [
                'pagos.ver' => 'Ver pagos',
                'pagos.registrar' => 'Registrar pagos',
            ],
        ],

        // --- Reportes / Inteligencia de negocios ---
        'reportes' => [
            'label' => 'Reportes',
            'permissions' => [
                'reportes.financiero' => 'Ver reportes financieros',
                'reportes.produccion' => 'Ver reportes de producción',
                'reportes.bi' => 'Ver inteligencia de negocios (BI)',
            ],
        ],

        // --- Seguridad y accesos ---
        'usuarios' => [
            'label' => 'Usuarios',
            'permissions' => [
                'usuarios.ver' => 'Ver usuarios',
                'usuarios.crear' => 'Crear usuarios',
                'usuarios.editar' => 'Editar usuarios',
                'usuarios.eliminar' => 'Eliminar usuarios',
            ],
        ],
        'roles' => [
            'label' => 'Roles y permisos',
            'permissions' => [
                'roles.ver' => 'Ver roles y permisos',
                'roles.crear' => 'Crear roles',
                'roles.editar' => 'Editar roles',
                'roles.eliminar' => 'Eliminar roles',
            ],
        ],
    ],

    /*
     * Roles base de arranque, según el organigrama de la empresa
     * (database-design.md 3.1): Administrador, Vendedor, Diseñador, Jefe de
     * Producción, Operario de Producción, Contador, Secretaria y Cliente
     * (rol de portal, sin acceso al panel interno).
     *
     * 'super-admin' es especial y se mantiene tal cual: además de recibir
     * aquí TODOS los permisos, tiene bypass total via Gate::before en
     * App\Providers\AppServiceProvider y vía la directiva v-can en el
     * frontend (resources/js/Directives/Can.js) — no depende de esta lista
     * de permisos para funcionar. Es el rol del usuario de arranque
     * superadmin@gmail.com (ver RolesAndPermissionsSeeder).
     *
     * La matriz rol↔permiso sigue el resumen de database-design.md 3.3;
     * donde el documento no detalla un permiso puntual (ej. accesos de
     * solo lectura a catálogos), se asignó el mínimo razonable según el
     * cargo. El cliente NO recibe `cotizaciones.aprobar` aunque el
     * documento menciona que "aprueba la propia": esa aprobación debe
     * quedar scopeada por policy (cliente_id propio) desde el futuro
     * portal, no por un permiso plano que le dejaría aprobar cualquier
     * cotización.
     */
    'roles' => [
        'super-admin' => [
            'label' => 'Super administrador',
            'permissions' => '*',
        ],

        'administrador' => [
            'label' => 'Administrador',
            'permissions' => [
                'dashboard.ver',
                'sucursales.ver', 'sucursales.crear', 'sucursales.editar', 'sucursales.eliminar',
                'areas.ver', 'areas.crear', 'areas.editar', 'areas.eliminar',
                'empleados.ver', 'empleados.crear', 'empleados.editar', 'empleados.eliminar',
                'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
                'categorias-material.ver', 'categorias-material.crear', 'categorias-material.editar', 'categorias-material.eliminar',
                'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.eliminar', 'materiales.ver_costos',
                'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar',
                'compras.ver', 'compras.crear', 'compras.aprobar',
                'categorias-producto.ver', 'categorias-producto.crear', 'categorias-producto.editar', 'categorias-producto.eliminar',
                'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar',
                'formulas.ver', 'formulas.crear', 'formulas.editar', 'formulas.eliminar',
                'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar', 'cotizaciones.aprobar', 'cotizaciones.eliminar',
                'pedidos.ver', 'pedidos.crear', 'pedidos.asignar_area', 'pedidos.actualizar_estado', 'pedidos.ver_todas_sucursales',
                'ordenes-compra-cliente.ver', 'ordenes-compra-cliente.crear', 'ordenes-compra-cliente.validar',
                'notas-entrega.ver', 'notas-entrega.crear',
                'pagos.ver', 'pagos.registrar',
                'reportes.financiero', 'reportes.produccion', 'reportes.bi',
                'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
                'roles.ver', 'roles.crear', 'roles.editar', 'roles.eliminar',
            ],
        ],

        'vendedor' => [
            'label' => 'Vendedor',
            'permissions' => [
                'dashboard.ver',
                'sucursales.ver',
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'categorias-producto.ver',
                'productos.ver',
                'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar',
                'pedidos.ver',
                'ordenes-compra-cliente.ver', 'ordenes-compra-cliente.crear',
            ],
        ],

        'disenador' => [
            'label' => 'Diseñador',
            'permissions' => [
                'dashboard.ver',
                'productos.ver',
                'cotizaciones.ver',
                'pedidos.ver', 'pedidos.asignar_area', 'pedidos.actualizar_estado',
            ],
        ],

        'jefe-produccion' => [
            'label' => 'Jefe de Producción',
            'permissions' => [
                'dashboard.ver',
                'empleados.ver',
                'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.ver_costos',
                'productos.ver',
                'pedidos.ver', 'pedidos.asignar_area', 'pedidos.actualizar_estado',
                'notas-entrega.ver', 'notas-entrega.crear',
                'reportes.produccion',
            ],
        ],

        'operario-produccion' => [
            'label' => 'Operario de Producción',
            'permissions' => [
                'dashboard.ver',
                'pedidos.ver', 'pedidos.actualizar_estado',
                'notas-entrega.ver', 'notas-entrega.crear',
            ],
        ],

        'contador' => [
            'label' => 'Contador',
            'permissions' => [
                'dashboard.ver',
                'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.ver_costos',
                'proveedores.ver',
                'compras.ver', 'compras.crear', 'compras.aprobar',
                'pagos.ver', 'pagos.registrar',
                'reportes.financiero', 'reportes.bi',
            ],
        ],

        'secretaria' => [
            'label' => 'Secretaria',
            'permissions' => [
                'dashboard.ver',
                'sucursales.ver',
                'clientes.ver', 'clientes.crear',
                'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar',
                'pedidos.ver',
                'ordenes-compra-cliente.ver', 'ordenes-compra-cliente.crear',
            ],
        ],

        // Rol de portal (cliente externo), sin acceso al panel interno —
        // ver nota arriba sobre cotizaciones.aprobar.
        'cliente' => [
            'label' => 'Cliente',
            'permissions' => [
                'cotizaciones.ver',
                'pedidos.ver',
            ],
        ],
    ],

];
