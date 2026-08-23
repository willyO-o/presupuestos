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
     * Siembra los roles y permisos base definidos en config/acl.php, más 2
     * usuarios de prueba para poder ver el sidebar
     * (resources/js/Data/Sidebar/Nav.js) funcionando con permisos reales.
     *
     * Todo esto es una base de arranque a propósito — roles, permisos y
     * usuarios se van a redefinir más adelante con los datos reales.
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Usuarios de prueba: solo fuera de produccion, para no dejar una
        // contraseña conocida (admin123) sembrada en un ambiente real.
        if (! app()->environment('production')) {
            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@gmail.com'],
                ['name' => 'Super Admin', 'password' => Hash::make('admin123')],
            );
            $superAdmin->syncRoles(['super-admin']);

            $operador = User::firstOrCreate(
                ['email' => 'operador@gmail.com'],
                ['name' => 'Usuario de Prueba', 'password' => Hash::make('admin123')],
            );
            $operador->syncRoles(['operador']);
        }
    }
}
