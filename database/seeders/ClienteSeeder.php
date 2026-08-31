<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClienteSeeder extends Seeder
{
    /**
     * Mezcla de clientes jurídicos (mayoría, es el perfil típico de
     * XtraPubli: marcas/agencias) y naturales. Los dos primeros reciben una
     * cuenta de portal (rol `cliente`) para probar el portal externo.
     */
    public function run(): void
    {
        Cliente::factory(12)->create();
        Cliente::factory(5)->natural()->create();

        if (app()->environment('production')) {
            return;
        }

        Role::findOrCreate('cliente', 'web');

        Cliente::query()->take(2)->get()->each(function (Cliente $cliente, int $i): void {
            $user = User::firstOrCreate(
                ['email' => "cliente{$i}@gmail.com"],
                ['name' => $cliente->razon_social, 'password' => Hash::make('cliente123'), 'estado' => 'ACTIVO'],
            );
            $user->syncRoles(['cliente']);
            $cliente->update(['user_id' => $user->id]);
        });
    }
}
