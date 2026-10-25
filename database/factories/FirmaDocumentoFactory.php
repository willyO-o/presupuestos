<?php

namespace Database\Factories;

use App\Models\FirmaDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirmaDocumento>
 */
class FirmaDocumentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->randomElement(array_keys(FirmaDocumento::FIRMAS)),
            'nombre' => fake()->name(),
            'cargo' => 'Jefe de producción',
        ];
    }
}
