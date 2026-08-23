<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => 'JURIDICO',
            'razon_social' => fake()->unique()->company(),
            'nit' => fake()->unique()->numerify('#########'),
            'contacto_nombre' => fake()->name(),
            'telefono' => fake()->numerify('7#######'),
            'email' => fake()->safeEmail(),
            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->city(),
            'estado' => 'ACTIVO',
        ];
    }

    /**
     * Cliente persona natural en vez de jurídico (empresa) por defecto.
     */
    public function natural(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'NATURAL',
            'razon_social' => fake()->name(),
        ]);
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
