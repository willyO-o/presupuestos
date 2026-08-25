<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Volumen de proveedores vía factory: no hay una lista fija en
     * database-design.md, a diferencia de las categorías/áreas.
     */
    public function run(): void
    {
        Proveedor::factory(8)->create();
    }
}
