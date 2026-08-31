<?php

namespace Database\Factories;

use App\Models\HistorialPrecioMaterial;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistorialPrecioMaterial>
 */
class HistorialPrecioMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $precioPresentacion = fake()->randomFloat(2, 50, 1500);

        return [
            'material_id' => Material::factory(),
            'precio_presentacion' => $precioPresentacion,
            'precio_unitario' => round($precioPresentacion / fake()->randomFloat(2, 1, 50), 2),
            'vigente_desde' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
