<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\NotaEntrega;
use App\Models\Pedido;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotaEntregaSeeder extends Seeder
{
    /**
     * Emite la nota de entrega de los pedidos entregados (con detalle por
     * ítem, ubicación y receptor, como las notas reales de Dismac / Black
     * Weekend en `publi/`). No idempotente: se salta si ya hay notas.
     */
    public function run(): void
    {
        if (NotaEntrega::query()->exists()) {
            return;
        }

        $empleados = Empleado::query()->where('estado', 'ACTIVO')->get();
        $pedidos = Pedido::query()
            ->where('estado', 'ENTREGADO')
            ->with('detalles')
            ->get();

        if ($empleados->isEmpty()) {
            return;
        }

        foreach ($pedidos as $pedido) {
            $fecha = $pedido->fecha_entrega_real ?? now();

            $nota = $pedido->notasEntrega()->create([
                'empleado_id' => $empleados->random()->id,
                'numero_nota' => 'NE-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
                'fecha_entrega' => $fecha->toDateString(),
                'recibido_por' => fake()->name(),
                'cargo_receptor' => fake()->randomElement(['Encargado de tienda', 'Jefe de local', 'Marketing']),
                'observaciones' => fake()->boolean(30) ? 'Instalación conforme, sin observaciones.' : null,
                'archivo_pdf' => null,
            ]);

            foreach ($pedido->detalles as $detalle) {
                $nota->detalles()->create([
                    'pedido_detalle_id' => $detalle->id,
                    'descripcion' => $detalle->descripcion,
                    'cantidad_entregada' => $detalle->cantidad,
                    'ubicacion' => fake()->randomElement([
                        'Ingreso tienda lado derecho', 'Fachada principal', 'Góndola pasillo central', 'Vitrina calle',
                    ]),
                    'foto_url' => null,
                ]);
            }
        }
    }
}
