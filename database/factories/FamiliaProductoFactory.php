<?php

namespace Database\Factories;

use App\Models\FamiliaProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamiliaProducto>
 */
class FamiliaProductoFactory extends Factory
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
                'Impresión gran formato', 'Letreros y letras 3D', 'Muebles y exhibidores',
                'Rotulado y vinilos', 'Material POP', 'Toldos y estructuras',
            ]).' '.fake()->unique()->numerify('####'),
            'descripcion' => null,
            'orden' => fake()->numberBetween(1, 10),
            'estado' => 'ACTIVO',
        ];
    }
}
