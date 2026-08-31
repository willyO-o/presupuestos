<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenCompraCliente>
 */
class OrdenCompraClienteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'cliente_id' => Cliente::factory(),
            'numero_oc' => fake()->unique()->numerify('OC-########'),
            'fecha' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'monto_total' => fake()->randomFloat(2, 300, 15000),
            'condicion_pago' => fake()->randomElement(['CONTADO', '30 DIAS', '60 DIAS', '90 DIAS']),
            'archivo_pdf' => null,
            'estado' => 'PENDIENTE',
        ];
    }

    public function validada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'VALIDADA']);
    }
}
