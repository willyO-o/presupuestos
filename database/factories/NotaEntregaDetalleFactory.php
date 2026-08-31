<?php

namespace Database\Factories;

use App\Models\NotaEntrega;
use App\Models\NotaEntregaDetalle;
use App\Models\PedidoDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaEntregaDetalle>
 */
class NotaEntregaDetalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nota_entrega_id' => NotaEntrega::factory(),
            'pedido_detalle_id' => PedidoDetalle::factory(),
            'descripcion' => fake()->sentence(4),
            'cantidad_entregada' => fake()->numberBetween(1, 5),
            'ubicacion' => fake()->boolean(70) ? fake()->randomElement([
                'Ingreso tienda lado derecho', 'Fachada principal', 'Góndola pasillo 3', 'Vitrina calle',
            ]) : null,
            'foto_url' => null,
        ];
    }
}
