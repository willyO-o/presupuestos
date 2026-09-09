<?php

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\SeguimientoPostventa;
use App\Services\Pedido\ProgramarPostventaService;
use Illuminate\Database\Seeder;

class SeguimientoPostventaSeeder extends Seeder
{
    /**
     * Programa el contacto de postventa de todo pedido ya entregado y cierra
     * algunos como REALIZADO, para que la bandeja no arranque vacía y el BI
     * tenga satisfacción con la que trabajar.
     *
     * Idempotente: `ProgramarPostventaService` no duplica (la FK `pedido_id`
     * es única) y solo se completan los que siguen PENDIENTES. Sirve además
     * como backfill de los pedidos entregados ANTES de que existiera el
     * módulo de postventa.
     */
    public function run(): void
    {
        $programar = app(ProgramarPostventaService::class);

        Pedido::query()->where('estado', 'ENTREGADO')->each(
            fn (Pedido $pedido) => $programar->programar($pedido)
        );

        // Se cierran los que ya tocaba llamar; los futuros quedan pendientes.
        // `get()` antes de recorrer: el update va cambiando el estado que
        // filtra el scope, y un chunk sobre un filtro móvil saltea filas.
        SeguimientoPostventa::query()
            ->pendientesDeContacto()
            ->with('pedido.cotizacion.empleado')
            ->get()
            ->each(function (SeguimientoPostventa $seguimiento): void {
                $contactado = fake()->boolean(85);
                $requiereAccion = $contactado && fake()->boolean(15);

                $seguimiento->update([
                    'empleado_id' => $seguimiento->pedido->cotizacion->empleado_id,
                    'estado' => $contactado ? 'REALIZADO' : 'NO_CONTACTADO',
                    'fecha_contacto' => $seguimiento->fecha_programada,
                    // Sin contacto no hay medio ni satisfacción que registrar.
                    'medio' => $contactado ? fake()->randomElement(SeguimientoPostventa::MEDIOS) : null,
                    'satisfaccion' => match (true) {
                        ! $contactado => null,
                        $requiereAccion => fake()->numberBetween(1, 3),
                        default => fake()->numberBetween(4, (int) config('postventa.satisfaccion_maxima')),
                    },
                    'requiere_accion' => $requiereAccion ? 'SI' : 'NO',
                    'oportunidad' => $contactado && fake()->boolean(30)
                        ? 'Consulta por renovación de señalética en otra sucursal.'
                        : null,
                    'observaciones' => match (true) {
                        ! $contactado => 'No se logró contactar al cliente.',
                        $requiereAccion => 'El cliente reportó un detalle de acabado a revisar.',
                        default => 'Cliente conforme con el trabajo entregado.',
                    },
                ]);
            });
    }
}
