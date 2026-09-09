<?php

namespace Database\Factories;

use App\Models\TipoProyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoProyecto>
 */
class TipoProyectoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Sufijo aleatorio: `nombre` es único en la tabla.
            'nombre' => fake()->unique()->words(2, true),
            'descripcion' => fake()->sentence(),
            'factor_complejidad' => fake()->randomFloat(2, 1, 2),
            'margen_minimo' => fake()->randomFloat(4, 0.30, 0.75),
            'orden' => fake()->numberBetween(1, 20),
            'estado' => 'ACTIVO',
        ];
    }

    /**
     * El nivel "Medio" del Excel original: factor 1.3, margen mínimo 50 %.
     * Es el caso que usa el test de referencia del motor de margen.
     */
    public function medio(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Medio',
            'factor_complejidad' => 1.3,
            'margen_minimo' => 0.5,
            'orden' => 2,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'INACTIVO',
        ]);
    }
}
