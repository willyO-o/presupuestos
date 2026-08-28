<?php

namespace Database\Factories;

use App\Models\CategoriaMaterial;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $precioPresentacion = fake()->randomFloat(2, 50, 1500);

        return [
            'categoria_material_id' => CategoriaMaterial::factory(),
            'nombre' => fake()->unique()->words(3, true),
            'presentacion' => fake()->randomElement(['Rollo 3,20x50m', 'Plancha 2x1m', 'Litro', 'Barra 6m']),
            'unidad_medida' => fake()->randomElement(['M2', 'METRO', 'UNIDAD', 'LITRO']),
            'precio_presentacion' => $precioPresentacion,
            'precio_unitario' => round($precioPresentacion / fake()->randomFloat(2, 1, 50), 2),
            'stock_actual' => fake()->randomFloat(2, 0, 200),
            'stock_minimo' => fake()->randomFloat(2, 5, 20),
            'redondeo_compra' => null,
            'estado' => 'ACTIVO',
        ];
    }

    /**
     * Estado INACTIVO en vez del ACTIVO por defecto.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'INACTIVO',
        ]);
    }

    /**
     * Material que se compra en unidades enteras de presentación: la
     * cantidad consumida se redondea hacia arriba al múltiplo dado al
     * costear (ver App\Services\Calculo\CosteoProductoService).
     */
    public function redondeoCompra(float $multiplo = 1.0): static
    {
        return $this->state(fn (array $attributes) => [
            'redondeo_compra' => $multiplo,
        ]);
    }
}
