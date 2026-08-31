<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoDetalleMaterial>
 */
class PedidoDetalleMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 0.5, 20);
        $precioUnitario = fake()->randomFloat(2, 5, 300);

        return [
            'pedido_detalle_id' => PedidoDetalle::factory(),
            'material_id' => Material::factory(),
            'cantidad_usada' => $cantidad,
            'costo_real' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
