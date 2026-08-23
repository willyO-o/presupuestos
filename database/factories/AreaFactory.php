<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement([
                'Ventas', 'Diseño', 'Gigantografía', 'Cerrajería', 'Carpintería', 'Acabado', 'Administración',
            ]),
            'descripcion' => fake()->sentence(),
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
}
