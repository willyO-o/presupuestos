<?php

namespace Database\Seeders;

use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

class PagoSeeder extends Seeder
{
    /**
     * Registra cobros sobre los pedidos avanzados: pago total en los
     * entregados, parcial (anticipo) en los que están en acabado. No
     * idempotente: se salta si ya hay pagos.
     */
    public function run(): void
    {
        if (Pago::query()->exists()) {
            return;
        }

        $pedidos = Pedido::query()
            ->whereIn('estado', ['ACABADO', 'ENTREGADO'])
            ->get();

        foreach ($pedidos as $pedido) {
            $total = (float) $pedido->total;
            $metodo = fake()->randomElement(Pago::METODOS);

            if ($pedido->estado === 'ENTREGADO') {
                // Anticipo del 50% + saldo al entregar.
                $anticipo = round($total * 0.5, 2);
                $pedido->pagos()->create([
                    'monto' => $anticipo,
                    'fecha_pago' => $pedido->fecha_pedido->copy()->addDays(2)->toDateString(),
                    'metodo_pago' => $metodo,
                    'estado' => 'PARCIAL',
                    'comprobante_url' => null,
                ]);
                $pedido->pagos()->create([
                    'monto' => round($total - $anticipo, 2),
                    'fecha_pago' => ($pedido->fecha_entrega_real ?? now())->toDateString(),
                    'metodo_pago' => $metodo,
                    'estado' => 'PAGADO',
                    'comprobante_url' => null,
                ]);
            } else {
                $pedido->pagos()->create([
                    'monto' => round($total * fake()->randomFloat(2, 0.3, 0.6), 2),
                    'fecha_pago' => $pedido->fecha_pedido->copy()->addDays(3)->toDateString(),
                    'metodo_pago' => $metodo,
                    'estado' => 'PARCIAL',
                    'comprobante_url' => null,
                ]);
            }
        }
    }
}
