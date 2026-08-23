<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empleado>
 */
class EmpleadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'area_id' => Area::factory(),
            'nombre_completo' => fake()->name(),
            'ci' => fake()->unique()->numerify('#######'),
            'cargo' => fake()->randomElement([
                'Vendedor', 'Diseñador Gráfico', 'Jefe de Producción', 'Operario de Producción', 'Contador', 'Secretaria',
            ]),
            'telefono' => fake()->numerify('7#######'),
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
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
