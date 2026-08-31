<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompraDetalle>
 */
class CompraDetalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 1, 40);
        $precioUnitario = fake()->randomFloat(2, 10, 400);

        return [
            'compra_id' => Compra::factory(),
            'material_id' => Material::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => round($cantidad * $precioUnitario, 2),
        ];
    }
}
