<?php

namespace Database\Factories;

use App\Models\CotizacionPublica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotizacionPublica>
 */
class CotizacionPublicaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Los montos son coherentes entre sí (total = subtotal + IVA, rango =
     * total ± holgura) pero el detalle no sale del motor de margen: para una
     * estimación calculada de verdad, usá
     * App\Services\Cotizador\CotizadorPublicoService.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vigencia = (int) config('cotizador.vigencia_dias', 7);
        $holgura = (float) config('cotizador.holgura', 0.15);

        $subtotal = fake()->randomFloat(2, 300, 6000);
        $iva = round($subtotal * (float) config('margen.impuestos.iva'), 2);
        $total = round($subtotal + $iva, 2);

        return [
            // Mismo formato que genera CotizadorPublicoController: la ruta
            // `cotizador.show` lo restringe con una expresión regular, así que
            // un código con otra forma daría 404 en los tests.
            'codigo' => 'WEB-'.now()->format('Ymd').'-'.fake()->unique()->bothify('?????'),
            'nombre' => fake()->name(),
            'empresa' => fake()->boolean(60) ? fake()->company() : null,
            'email' => fake()->boolean(70) ? fake()->safeEmail() : null,
            'telefono' => fake()->numerify('7#######'),
            'mensaje' => fake()->boolean(40) ? fake()->sentence() : null,
            'detalle' => [[
                'producto' => 'Banner lona frontlight',
                'descripcion' => 'Banner lona frontlight 2,00 × 1,00 m',
                'ancho' => 2.0,
                'alto' => 1.0,
                'cantidad' => 1.0,
                'precio_unitario' => $subtotal,
                'subtotal' => $subtotal,
            ]],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'estimado_min' => round($total * (1 - $holgura), 2),
            'estimado_max' => round($total * (1 + $holgura), 2),
            'holgura' => $holgura,
            'vigencia_dias' => $vigencia,
            'fecha_vencimiento' => today()->addDays($vigencia)->toDateString(),
            'estado' => 'NUEVA',
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Estimación cuya vigencia ya pasó: el visitante ve el aviso de vencida y
     * el precio queda como referencia histórica.
     */
    public function vencida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fecha_vencimiento' => today()->subDay()->toDateString(),
        ]);
    }

    public function contactada(): static
    {
        return $this->state(fn (array $attributes): array => ['estado' => 'CONTACTADA']);
    }
}
