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
            'unidad_medida' => fake()->randomElement(Producto::UNIDADES_MEDIDA),
            'precio_base' => fake()->randomFloat(2, 20, 500),
            'requiere_medidas' => 'SI',
            // Fuera del cotizador público por defecto: publicar un producto es
            // una decisión explícita, no algo que pase por descuido en un
            // seeder de volumen (ver la migración add_cotizable_web_to_producto_table).
            'cotizable_web' => 'NO',
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
     * Producto ofrecido en el cotizador público. Para que realmente aparezca
     * necesita además al menos una línea de receta: encadená
     * `->has(ProductoMaterial::factory())` o usá ProductoMaterialSeeder.
     */
    public function cotizableWeb(): static
    {
        return $this->state(fn (array $attributes) => [
            'cotizable_web' => 'SI',
            'estado' => 'ACTIVO',
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
