<?php

namespace Database\Factories;

use App\Models\BiAnomalia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiAnomalia>
 */
class BiAnomaliaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipo = fake()->randomElement(array_keys(BiAnomalia::TIPOS));

        return [
            'huella' => $tipo.':'.fake()->unique()->numerify('######'),
            'tipo' => $tipo,
            'severidad' => fake()->randomElement(['ALTA', 'MEDIA']),
            'titulo' => BiAnomalia::TIPOS[$tipo],
            'detalle' => fake()->sentence(12),
            'que_revisar' => fake()->sentence(8),
            'hechos' => [],
            'sucursal_id' => null,
            'url' => null,
            'redaccion' => 'PLANTILLA',
            'estado' => 'NUEVA',
            'detectada_en' => now()->toDateString(),
        ];
    }
}
