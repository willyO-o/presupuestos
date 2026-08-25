<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Mezcla de clientes jurídicos (mayoría, es el perfil típico de
     * XtraPubli: marcas/agencias) y naturales.
     */
    public function run(): void
    {
        Cliente::factory(12)->create();
        Cliente::factory(5)->natural()->create();
    }
}
