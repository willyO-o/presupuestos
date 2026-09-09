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
            CompraSeeder::class,
            // Antes de CotizacionSeeder: cada línea cotizada elige un nivel
            // de complejidad para el motor de margen.
            TipoProyectoSeeder::class,
            CotizacionSeeder::class,
            PedidoSeeder::class,
            OrdenCompraClienteSeeder::class,
            NotaEntregaSeeder::class,
            PagoSeeder::class,
            // Cierra el flujo: programa/completa el contacto postventa de los
            // pedidos ya entregados.
            SeguimientoPostventaSeeder::class,
        ]);

        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw(['name' => 'Test User']),
        );
    }
}
