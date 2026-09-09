<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
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

        $this->vincularCuentasDeAcceso();
    }

    /**
     * Le da ficha de empleado a las cuentas que no la tienen.
     *
     * No es cosmético: el scoping por sucursal de Pedidos
     * (`Pedido::visiblePara()`) resuelve la sucursal del usuario a través de
     * `users -> empleado`, y sin esa ficha devuelve CERO pedidos (falla
     * cerrado, a propósito). Con la base sembrada y ningún usuario vinculado,
     * el módulo parecía vacío o roto.
     */
    private function vincularCuentasDeAcceso(): void
    {
        $sinFicha = User::query()->whereDoesntHave('empleado')->get();

        if ($sinFicha->isEmpty()) {
            return;
        }

        $disponibles = Empleado::query()->whereNull('user_id')->get();

        foreach ($sinFicha as $indice => $user) {
            $empleado = $disponibles->get($indice);

            if ($empleado === null) {
                return;
            }

            $empleado->update(['user_id' => $user->id]);
        }
    }
}
