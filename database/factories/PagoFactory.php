<?php

namespace Database\Factories;

use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'monto' => fake()->randomFloat(2, 100, 5000),
            'fecha_pago' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'metodo_pago' => fake()->randomElement(Pago::METODOS),
            'estado' => fake()->randomElement(Pago::ESTADOS),
            'comprobante_url' => null,
        ];
    }
}
