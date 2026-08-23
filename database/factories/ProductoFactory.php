<?php

namespace Database\Factories;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_producto_id' => CategoriaProducto::factory(),
            'nombre' => fake()->unique()->words(3, true),
            'descripcion' => fake()->sentence(),
            'unidad_medida' => fake()->randomElement(['M2', 'UNIDAD', 'METRO_LINEAL']),
            'precio_base' => fake()->randomFloat(2, 20, 500),
            'requiere_medidas' => 'SI',
            'estado' => 'ACTIVO',
        ];
    }

    /**
     * Producto sin medidas (precio fijo, no pide ancho/alto al cotizar).
     */
    public function sinMedidas(): static
    {
        return $this->state(fn (array $attributes) => [
            'requiere_medidas' => 'NO',
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
