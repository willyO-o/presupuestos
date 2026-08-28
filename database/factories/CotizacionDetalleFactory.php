<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotizacionDetalle>
 */
class CotizacionDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ancho = fake()->randomFloat(2, 0.5, 5);
        $alto = fake()->randomFloat(2, 0.5, 3);
        $cantidad = fake()->numberBetween(1, 10);
        $precioUnitario = fake()->randomFloat(2, 30, 900);

        return [
            'cotizacion_id' => Cotizacion::factory(),
            'producto_id' => Producto::factory(),
            'descripcion' => fake()->words(3, true),
            'ancho' => $ancho,
            'alto' => $alto,
            'area_m2' => round($ancho * $alto, 2),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => round($precioUnitario * $cantidad, 2),
        ];
    }

    /**
     * Ítem personalizado sin producto de catálogo ni medidas.
     */
    public function personalizado(): static
    {
        return $this->state(fn (array $attributes) => [
            'producto_id' => null,
            'ancho' => null,
            'alto' => null,
            'area_m2' => null,
        ]);
    }
}
