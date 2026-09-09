<?php

namespace Database\Factories;

use App\Models\CotizacionDetalle;
use App\Models\CotizacionDetalleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotizacionDetalleItem>
 */
class CotizacionDetalleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 0.1, 8);
        $costoUnitario = fake()->randomFloat(2, 5, 200);

        return [
            'cotizacion_detalle_id' => CotizacionDetalle::factory(),
            'material_id' => null,
            'tipo' => 'MATERIAL',
            'descripcion' => fake()->words(2, true),
            'unidad' => fake()->randomElement(['M2', 'METRO', 'UNIDAD', 'KG']),
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'subtotal' => round($cantidad * $costoUnitario, 4),
        ];
    }

    /**
     * Línea de mano de obra (horas): no sale del inventario, por eso nunca
     * lleva `material_id`.
     */
    public function manoObra(): static
    {
        return $this->state(function (array $attributes) {
            $horas = fake()->numberBetween(1, 12);
            $costoHora = fake()->randomFloat(2, 12, 25);

            return [
                'material_id' => null,
                'tipo' => 'MANO_OBRA',
                'descripcion' => 'Horas',
                'unidad' => 'HORA',
                'cantidad' => $horas,
                'costo_unitario' => $costoHora,
                'subtotal' => round($horas * $costoHora, 4),
            ];
        });
    }
}
