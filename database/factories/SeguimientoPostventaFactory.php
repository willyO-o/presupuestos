<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\SeguimientoPostventa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeguimientoPostventa>
 */
class SeguimientoPostventaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory()->entregado(),
            'empleado_id' => null,
            'fecha_programada' => now()->addDays((int) config('postventa.dias_seguimiento'))->toDateString(),
            'fecha_contacto' => null,
            'estado' => 'PENDIENTE',
            'medio' => null,
            'satisfaccion' => null,
            'requiere_accion' => 'NO',
            'oportunidad' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Contacto ya hecho: cliente conforme, sin reclamo abierto.
     */
    public function realizado(): static
    {
        return $this->state(fn (array $attributes) => [
            'empleado_id' => Empleado::factory(),
            'estado' => 'REALIZADO',
            'fecha_contacto' => now()->toDateString(),
            'medio' => fake()->randomElement(SeguimientoPostventa::MEDIOS),
            'satisfaccion' => fake()->numberBetween(3, (int) config('postventa.satisfaccion_maxima')),
        ]);
    }

    /**
     * Ya pasó la fecha en que había que llamar y sigue pendiente: es lo que
     * la bandeja marca como vencido.
     */
    public function vencido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'PENDIENTE',
            'fecha_programada' => now()->subDays(3)->toDateString(),
        ]);
    }
}
