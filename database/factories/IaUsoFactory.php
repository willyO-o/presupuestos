<?php

namespace Database\Factories;

use App\Models\IaUso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IaUso>
 */
class IaUsoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'funcionalidad' => fake()->randomElement(['asistente', 'resumen_semanal', 'anomalias']),
            'proveedor' => 'gemini',
            'modelo' => 'gemini-3.8-flash',
            'tokens_entrada' => fake()->numberBetween(1000, 20000),
            'tokens_salida' => fake()->numberBetween(200, 3000),
            'tokens_cache' => 0,
            'costo_usd' => fake()->randomFloat(6, 0.001, 0.05),
            'latencia_ms' => fake()->numberBetween(500, 8000),
            'estado' => 'OK',
            'error' => null,
        ];
    }
}
