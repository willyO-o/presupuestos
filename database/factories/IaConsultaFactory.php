<?php

namespace Database\Factories;

use App\Models\IaConsulta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IaConsulta>
 */
class IaConsultaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversacion_id' => (string) Str::uuid(),
            'pregunta' => fake()->randomElement([
                '¿Cuánto vendimos este mes?',
                '¿Qué familia deja más margen?',
                '¿Hay pedidos atrasados?',
            ]),
            'respuesta' => 'Este mes se vendió Bs 1.000,00.',
            'resultados' => [],
            'proveedor' => 'gemini',
            'modelo' => 'gemini-3.8-flash',
            'costo_usd' => fake()->randomFloat(6, 0.001, 0.05),
            'estado' => 'OK',
        ];
    }

    /**
     * Pregunta que no tuvo respuesta (IA apagada, sin clave o sin presupuesto).
     */
    public function sinRespuesta(): static
    {
        return $this->state(fn () => [
            'conversacion_id' => null,
            'respuesta' => 'El asistente de IA está desactivado.',
            'proveedor' => null,
            'modelo' => null,
            'costo_usd' => 0,
            'estado' => 'ERROR',
        ]);
    }
}
