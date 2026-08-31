<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Empleado;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    /**
     * El `total` es coherente por sí solo pero no refleja un detalle real —
     * para eso encadená `->has(CompraDetalle::factory()...)` y recalculá, o
     * usá CompraSeeder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'empleado_id' => Empleado::factory(),
            'numero_factura' => fake()->boolean(80) ? fake()->numerify('F-#####') : null,
            'fecha' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'total' => fake()->randomFloat(2, 200, 12000),
            'estado' => 'PENDIENTE',
        ];
    }

    public function pagada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'PAGADA']);
    }

    public function anulada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'ANULADA']);
    }
}
