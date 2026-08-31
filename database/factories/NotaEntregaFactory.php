<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotaEntrega>
 */
class NotaEntregaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'pedido_id' => Pedido::factory(),
            'empleado_id' => Empleado::factory(),
            'numero_nota' => 'NE-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
            'fecha_entrega' => $fecha->format('Y-m-d'),
            'recibido_por' => fake()->name(),
            'cargo_receptor' => fake()->randomElement(['Encargado de tienda', 'Jefe de local', 'Recepción', 'Marketing']),
            'observaciones' => fake()->boolean(30) ? fake()->sentence() : null,
            'archivo_pdf' => null,
        ];
    }
}
