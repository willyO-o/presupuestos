<?php

namespace Database\Factories;

use App\Models\Formula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formula>
 */
class FormulaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$nombre, $expresion] = fake()->randomElement([
            ['Área simple', 'ancho * alto'],
            ['Perímetro', '(ancho + alto) * 2'],
            ['Volumen', 'ancho * alto * profundo'],
        ]);

        return [
            'nombre' => $nombre.' '.fake()->unique()->numerify('####'),
            'expresion' => $expresion,
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
