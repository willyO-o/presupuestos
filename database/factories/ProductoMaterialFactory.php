<?php

namespace Database\Factories;

use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoMaterial>
 */
class ProductoMaterialFactory extends Factory
{
    /**
     * Por defecto, línea de BOM "estática": factor fijo, sin fórmula. Usa
     * el estado dinamica() para una línea que calcula su cantidad con una
     * fórmula en vez del factor fijo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'material_id' => Material::factory(),
            'formula_id' => null,
            'cantidad_por_unidad' => fake()->randomFloat(4, 0.1, 5),
        ];
    }

    /**
     * Línea de BOM "dinámica": la cantidad se calcula con la fórmula dada
     * (o una nueva) en vez del factor fijo cantidad_por_unidad.
     */
    public function dinamica(?Formula $formula = null): static
    {
        return $this->state(fn (array $attributes) => [
            'formula_id' => $formula?->id ?? Formula::factory(),
            'cantidad_por_unidad' => null,
        ]);
    }
}
