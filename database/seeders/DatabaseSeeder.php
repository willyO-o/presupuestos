<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SucursalSeeder::class,
            AreaSeeder::class,
            CategoriaMaterialSeeder::class,
            CategoriaProductoSeeder::class,
            ProveedorSeeder::class,
            ClienteSeeder::class,
            EmpleadoSeeder::class,
            MaterialSeeder::class,
            ProductoSeeder::class,
            FormulaSeeder::class,
            ProductoMaterialSeeder::class,
            CotizacionSeeder::class,
        ]);

        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw(['name' => 'Test User']),
        );
    }
}
