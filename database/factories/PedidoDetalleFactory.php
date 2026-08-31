<?php

namespace Database\Factories;

use App\Models\CotizacionDetalle;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoDetalle>
 */
class PedidoDetalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'cotizacion_detalle_id' => CotizacionDetalle::factory(),
            'descripcion' => fake()->sentence(3),
            'ancho' => fake()->boolean(70) ? fake()->randomFloat(2, 0.5, 4) : null,
            'alto' => fake()->boolean(70) ? fake()->randomFloat(2, 0.5, 3) : null,
            'cantidad' => fake()->numberBetween(1, 6),
            'estado_item' => 'DISENO',
        ];
    }

    public function estadoItem(string $estado): static
    {
        return $this->state(fn (array $attributes) => ['estado_item' => $estado]);
    }
}
