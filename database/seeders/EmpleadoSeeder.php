<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class EmpleadoSeeder extends Seeder
{
    /**
     * `recycle()` hace que la factory reutilice las sucursales/áreas ya
     * sembradas (Sucursal/AreaSeeder) en vez de crear filas nuevas cada vez
     * que `EmpleadoFactory::definition()` invoca `Sucursal::factory()` /
     * `Area::factory()` — así el empleado queda ligado a un catálogo real.
     */
    public function run(): void
    {
        Empleado::factory(15)
            ->recycle(Sucursal::all())
            ->recycle(Area::all())
            ->create();
    }
}
