<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Siembra los roles y permisos base definidos en config/acl.php (ver ahí
     * la matriz rol↔permiso, alineada a database-design.md), más el usuario
     * superadministrador que se usa para operar el sistema mientras se
     * construyen el resto de módulos.
     *
     * Roles y permisos son una base de arranque a propósito — se van a ir
     * ajustando a medida que se agreguen módulos nuevos.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = config('acl.modules', []);
        $rolesConfig = config('acl.roles', []);

        $allPermissions = [];
        foreach ($modules as $module) {
            foreach (array_keys($module['permissions']) as $permission) {
                $allPermissions[] = $permission;
                Permission::findOrCreate($permission, 'web');
            }
        }

        // Sin este flush, syncPermissions() de abajo resuelve los nombres
        // de permiso contra el cache que quedo vacio en el flush inicial
        // (de antes de crear los permisos) y falla con
        // "There is no permission named ... " aunque el permiso ya exista
        // en la base de datos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($rolesConfig as $roleName => $roleConfig) {
            $permissions = $roleConfig['permissions'] === '*'
                ? $allPermissions
                : $roleConfig['permissions'];

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        // acl.php es la fuente de verdad: cualquier Role/Permission que ya
        // no esté ahí (ej. un módulo que se elimina o se renombra) se borra
        // en vez de quedar huérfano en la base de datos. El cascadeOnDelete
        // de las tablas pivote de Spatie limpia model_has_permissions/
        // model_has_roles/role_has_permissions solo.
        Permission::where('guard_name', 'web')->whereNotIn('name', $allPermissions)->delete();
        Role::where('guard_name', 'web')->whereNotIn('name', array_keys($rolesConfig))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Usuario de arranque: solo fuera de produccion, para no dejar una
        // contraseña conocida (admin123) sembrada en un ambiente real. Es el
        // superadministrador que se usa para operar el sistema.
        if (! app()->environment('production')) {
            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@gmail.com'],
                ['name' => 'Super Admin', 'password' => Hash::make('admin123')],
            );
            $superAdmin->syncRoles(['super-admin']);
        }
    }
}
