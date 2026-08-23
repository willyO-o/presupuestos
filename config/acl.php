<?php

/**
 * Catálogo de roles y permisos base de la aplicación (spatie/laravel-permission).
 *
 * Fuente de verdad única: la migración
 * database/migrations/*_seed_roles_and_permissions.php lee este archivo para
 * crear los Roles/Permissions reales en base de datos — para agregar un
 * permiso nuevo, agrégalo aquí (dentro del módulo que corresponda) y a los
 * roles que deban tenerlo, después corre `php artisan migrate`.
 *
 * Convención de nombres: "recurso.accion" (dashboard.ver, usuarios.crear,
 * usuarios.ver, ...). Deben coincidir con los `permission` que usa el
 * sidebar en resources/js/Data/Sidebar/Nav.js.
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
        'vales' => [
            'label' => 'Vales',
            'permissions' => [
                'vales.ver' => 'Ver vales',
            ],
        ],
        'cargas-combustible' => [
            'label' => 'Cargas de combustible',
            'permissions' => [
                'cargas-combustible.ver' => 'Ver cargas de combustible',
                'cargas-combustible.reporte' => 'Ver reporte de cargas',
                'cargas-combustible.reporte.rendimiento' => 'Ver reporte de rendimiento',
            ],
        ],
        'operacion-diaria' => [
            'label' => 'Operación diaria',
            'permissions' => [
                'operacion-diaria.ver' => 'Ver actividades',
                'operacion-diaria.crear' => 'Registrar actividad',
            ],
        ],
        'mantenimiento' => [
            'label' => 'Mantenimiento',
            'permissions' => [
                'mantenimiento.solicitudes.ver' => 'Ver solicitudes de mantenimiento',
                'mantenimiento.solicitudes.crear' => 'Crear solicitud de mantenimiento',
                'mantenimiento.ordenes.ver' => 'Ver órdenes de trabajo',
            ],
        ],
        'conductores' => [
            'label' => 'Conductores',
            'permissions' => [
                'conductores.ver' => 'Ver conductores',
            ],
        ],
        'vehiculos' => [
            'label' => 'Vehículos',
            'permissions' => [
                'vehiculos.ver' => 'Ver vehículos',
            ],
        ],
        'sucursales' => [
            'label' => 'Sucursales',
            'permissions' => [
                'sucursales.ver' => 'Ver sucursales',
                'sucursales.crear' => 'Crear sucursales',
                'sucursales.editar' => 'Editar sucursales',
                'sucursales.eliminar' => 'Eliminar sucursales',
            ],
        ],
        'grifos' => [
            'label' => 'Surtidores',
            'permissions' => [
                'grifos.ver' => 'Ver surtidores',
            ],
        ],
        'tipos-combustible' => [
            'label' => 'Tipos de combustible',
            'permissions' => [
                'tipos-combustible.ver' => 'Ver tipos de combustible',
            ],
        ],
        'tipos-mantenimiento' => [
            'label' => 'Tipos de mantenimiento',
            'permissions' => [
                'tipos-mantenimiento.ver' => 'Ver tipos de mantenimiento',
            ],
        ],
        'tipos-vehiculo' => [
            'label' => 'Tipos de vehículo',
            'permissions' => [
                'tipos-vehiculo.ver' => 'Ver tipos de vehículo',
            ],
        ],
        'grupos-vehiculo' => [
            'label' => 'Grupos de vehículo',
            'permissions' => [
                'grupos-vehiculo.ver' => 'Ver grupos de vehículo',
            ],
        ],
        'repuestos' => [
            'label' => 'Repuestos',
            'permissions' => [
                'repuestos.ver' => 'Ver repuestos',
            ],
        ],
        'personas' => [
            'label' => 'Personas',
            'permissions' => [
                'personas.ver' => 'Ver personas',
            ],
        ],
        'areas' => [
            'label' => 'Áreas',
            'permissions' => [
                'areas.ver' => 'Ver áreas',
            ],
        ],
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
        'parametros-empresa' => [
            'label' => 'Parámetros de la empresa',
            'permissions' => [
                'parametros-empresa.ver' => 'Ver parámetros de la empresa',
            ],
        ],
    ],

    /*
     * Roles base de arranque. Son datos de prueba a proposito (ver la
     * migracion que los siembra) — se van a redefinir cuando existan los
     * roles reales del negocio.
     *
     * 'super-admin' es especial: ademas de recibir aqui TODOS los permisos,
     * tiene bypass total via Gate::before en App\Providers\AppServiceProvider
     * y via la directiva v-can en el frontend (resources/js/Directives/Can.js)
     * — no depende de esta lista de permisos para funcionar.
     */
    'roles' => [
        'super-admin' => [
            'label' => 'Super administrador',
            'permissions' => '*',
        ],
        'operador' => [
            'label' => 'Operador',
            'permissions' => [
                'dashboard.ver',
                'vales.ver',
                'cargas-combustible.ver',
                'operacion-diaria.ver',
                'operacion-diaria.crear',
            ],
        ],
    ],

];
