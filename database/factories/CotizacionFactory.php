<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cotizacion>
 */
class CotizacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Los montos son coherentes entre sí (total = subtotal − descuento +
     * impuesto) pero no reflejan un detalle real — para eso, encadená
     * `->has(CotizacionDetalle::factory()...)` y recalculá, o usá
     * CotizacionSeeder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = fake()->dateTimeBetween('-3 months', 'now');
        $subtotal = fake()->randomFloat(2, 200, 8000);
        $descuento = fake()->boolean(30) ? round($subtotal * 0.1, 2) : 0;
        $impuesto = fake()->boolean(40) ? round(($subtotal - $descuento) * 0.13, 2) : 0;

        return [
            'codigo_verificacion' => 'COT-'.fake()->unique()->numerify('########'),
            'cliente_id' => Cliente::factory(),
            'empleado_id' => Empleado::factory(),
            'sucursal_id' => Sucursal::factory(),
            'fecha' => $fecha->format('Y-m-d'),
            'fecha_vencimiento' => (clone $fecha)->modify('+15 days')->format('Y-m-d'),
            'estado' => 'PENDIENTE',
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'impuesto' => $impuesto,
            'total' => round($subtotal - $descuento + $impuesto, 2),
            'observaciones' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'APROBADA']);
    }

    public function rechazada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'RECHAZADA']);
    }

    public function convertida(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'CONVERTIDA']);
    }
}
