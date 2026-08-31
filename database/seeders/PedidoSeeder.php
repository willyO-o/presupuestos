<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Services\Pedido\ConvertirCotizacionService;
use Illuminate\Database\Seeder;

class PedidoSeeder extends Seeder
{
    /**
     * Convierte las cotizaciones aprobadas/convertidas del CotizacionSeeder
     * en pedidos (misma lógica que PedidoController vía
     * ConvertirCotizacionService) y les da un avance realista por etapas,
     * con bitácora de seguimiento y consumo real de materiales — así el
     * módulo de BI tiene rentabilidad real vs. presupuestada y tiempos de
     * entrega con los que trabajar.
     *
     * No es idempotente (ver .ai/rules/seeders.md): se salta si ya hay pedidos.
     */
    public function run(): void
    {
        if (Pedido::query()->exists()) {
            return;
        }

        $convertir = app(ConvertirCotizacionService::class);

        $cotizaciones = Cotizacion::query()
            ->whereIn('estado', ['APROBADA', 'CONVERTIDA'])
            ->with('detalles.producto.productoMateriales.material')
            ->get();

        if ($cotizaciones->isEmpty()) {
            return;
        }

        $areas = Area::query()->where('estado', 'ACTIVO')->get();
        $empleados = Empleado::query()->where('estado', 'ACTIVO')->get();

        // Cada plantilla: hasta qué etapa llegó cada ítem del pedido.
        $avances = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO', 'ENTREGADO', 'ELABORACION'];

        foreach ($cotizaciones->values() as $i => $cotizacion) {
            $cotizacion->update(['estado' => 'APROBADA']);
            $pedido = $convertir->convertir($cotizacion, now()->addDays(fake()->numberBetween(5, 20))->toDateString());

            $hasta = $avances[$i % count($avances)];
            $pedido->loadMissing('detalles');

            foreach ($pedido->detalles as $detalle) {
                $this->avanzarItem($detalle, $hasta, $areas, $empleados);
                $this->registrarConsumo($detalle, $cotizacion);
            }

            $pedido->recalcularEstado();

            if ($pedido->fresh()->estado === 'ENTREGADO') {
                $pedido->update([
                    'fecha_entrega_real' => now()->subDays(fake()->numberBetween(0, 6))->toDateString(),
                ]);
            }
        }
    }

    private function avanzarItem(PedidoDetalle $detalle, string $hasta, $areas, $empleados): void
    {
        $flujo = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'];
        $etapas = ['DISENO' => 'DISENO', 'ELABORACION' => 'ELABORACION', 'ACABADO' => 'ACABADO', 'ENTREGADO' => 'ENTREGA'];
        $limite = array_search($hasta, $flujo, true);

        $inicio = now()->subDays(fake()->numberBetween(20, 45));

        foreach ($flujo as $idx => $estado) {
            if ($idx > $limite) {
                break;
            }

            $cerrada = $idx < $limite;
            $detalle->seguimientos()->create([
                'area_id' => $areas->isNotEmpty() ? $areas->random()->id : Area::factory()->create()->id,
                'empleado_id' => $empleados->isNotEmpty() ? $empleados->random()->id : Empleado::factory()->create()->id,
                'etapa' => $etapas[$estado],
                'fecha_inicio' => $inicio->copy(),
                'fecha_fin' => $cerrada ? $inicio->copy()->addDays(fake()->numberBetween(1, 4)) : null,
                'observaciones' => null,
            ]);
            $inicio = $inicio->copy()->addDays(fake()->numberBetween(2, 6));
        }

        $detalle->update(['estado_item' => $hasta]);
    }

    private function registrarConsumo(PedidoDetalle $detalle, Cotizacion $cotizacion): void
    {
        if (! in_array($detalle->estado_item, ['ACABADO', 'ENTREGADO'], true)) {
            return;
        }

        $lineaCotizacion = $cotizacion->detalles->firstWhere('id', $detalle->cotizacion_detalle_id);
        $producto = $lineaCotizacion?->producto;

        if ($producto === null || $producto->productoMateriales->isEmpty()) {
            return;
        }

        foreach ($producto->productoMateriales as $bom) {
            $material = $bom->material;
            if ($material === null) {
                continue;
            }

            $base = ($bom->cantidad_por_unidad ?? 1) * (float) $detalle->cantidad;
            $cantidadUsada = round(max($base, 0.5) * fake()->randomFloat(2, 0.9, 1.2), 2);

            $detalle->materialesUsados()->create([
                'material_id' => $material->id,
                'cantidad_usada' => $cantidadUsada,
                'costo_real' => round($cantidadUsada * (float) $material->precio_unitario * fake()->randomFloat(2, 0.95, 1.15), 2),
            ]);
        }
    }
}
