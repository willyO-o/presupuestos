<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'cotizacion_id' => Cotizacion::factory()->convertida(),
            'numero_pedido' => 'PED-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
            'fecha_pedido' => $fecha->format('Y-m-d'),
            'fecha_entrega_estimada' => (clone $fecha)->modify('+10 days')->format('Y-m-d'),
            'fecha_entrega_real' => null,
            'estado' => 'DISENO',
            'total' => fake()->randomFloat(2, 300, 12000),
        ];
    }

    public function estado(string $estado): static
    {
        return $this->state(fn (array $attributes) => ['estado' => $estado]);
    }

    public function entregado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ENTREGADO',
            'fecha_entrega_real' => now()->toDateString(),
        ]);
    }
}
