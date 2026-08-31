<?php

namespace Database\Seeders;

use App\Models\OrdenCompraCliente;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

class OrdenCompraClienteSeeder extends Seeder
{
    /**
     * Registra la orden de compra formal del cliente para la mayoría de los
     * pedidos (como las OC reales de la empresa en `publi/`). No idempotente:
     * se salta si ya hay órdenes de compra.
     */
    public function run(): void
    {
        if (OrdenCompraCliente::query()->exists()) {
            return;
        }

        $pedidos = Pedido::query()
            ->where('estado', '!=', 'CANCELADO')
            ->with('cotizacion:id,cliente_id')
            ->get();

        foreach ($pedidos as $i => $pedido) {
            // Deja algunos pedidos sin OC para poder probar el alta.
            if ($i % 4 === 3) {
                continue;
            }

            OrdenCompraCliente::create([
                'pedido_id' => $pedido->id,
                'cliente_id' => $pedido->cotizacion->cliente_id,
                'numero_oc' => fake()->unique()->numerify('OC-110#####'),
                'fecha' => $pedido->fecha_pedido->copy()->addDays(fake()->numberBetween(0, 3))->toDateString(),
                'monto_total' => $pedido->total,
                'condicion_pago' => fake()->randomElement(['CONTADO', '30 DIAS', '60 DIAS']),
                'archivo_pdf' => null,
                'estado' => in_array($pedido->estado, ['ACABADO', 'ENTREGADO'], true) ? 'VALIDADA' : 'PENDIENTE',
            ]);
        }
    }
}
