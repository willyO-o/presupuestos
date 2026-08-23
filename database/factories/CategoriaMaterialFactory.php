<?php

namespace Database\Factories;

use App\Models\CategoriaMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaMaterial>
 */
class CategoriaMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement([
                'Gigantografía', 'Cerrajería', 'Carpintería', 'Otros materiales', 'Pinturas',
            ]).' '.fake()->unique()->numerify('####'),
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
