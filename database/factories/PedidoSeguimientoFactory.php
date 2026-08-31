<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Empleado;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoSeguimiento>
 */
class PedidoSeguimientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-2 months', '-1 week');

        return [
            'pedido_detalle_id' => PedidoDetalle::factory(),
            'area_id' => Area::factory(),
            'empleado_id' => Empleado::factory(),
            'etapa' => fake()->randomElement(PedidoSeguimiento::ETAPAS),
            'fecha_inicio' => $inicio,
            'fecha_fin' => fake()->boolean(60) ? (clone $inicio)->modify('+2 days') : null,
            'observaciones' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
