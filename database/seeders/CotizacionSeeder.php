<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\Calculo\PrecioSugeridoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CotizacionSeeder extends Seeder
{
    /**
     * Cotizaciones de prueba con detalle real: cada línea toma un producto
     * del catálogo curado (ProductoSeeder), y su precio unitario sale del
     * costeo del BOM + margen (App\Services\Calculo\PrecioSugeridoService)
     * cuando el producto tiene receta, o de `precio_base` si no. Los montos
     * de cabecera se calculan igual que en CotizacionController.
     *
     * No es idempotente (como los demás seeders de volumen, ver
     * .ai/rules/seeders.md): se salta si ya hay cotizaciones para no
     * duplicar en cada corrida.
     */
    public function run(): void
    {
        if (Cotizacion::query()->exists()) {
            return;
        }

        $clientes = Cliente::query()->where('estado', 'ACTIVO')->get();
        $empleados = Empleado::query()->where('estado', 'ACTIVO')->get();
        $sucursales = Sucursal::query()->where('estado', 'ACTIVO')->get();
        $productos = Producto::query()->where('estado', 'ACTIVO')->get()->keyBy('nombre');

        if ($clientes->isEmpty() || $empleados->isEmpty() || $sucursales->isEmpty() || $productos->isEmpty()) {
            return;
        }

        $precioSugerido = app(PrecioSugeridoService::class);

        // [estado, [ [nombreProducto|null, descripcion, ancho, alto, cantidad, precioManual?], ... ], descuento, impuesto?]
        $plantillas = [
            ['APROBADA', [
                ['Gigantografía frontlight', 'Gigantografía frontlight para fachada', 3.35, 2.00, 1],
                ['Banner lona frontlight', 'Banner promoción temporada', 2.00, 1.00, 3],
            ], 0, true],
            ['PENDIENTE', [
                ['Letras corpóreas 3D iluminadas', 'Logo corpóreo iluminado recepción', 1.20, 0.45, 1],
                ['Rotulado vinil adhesivo', 'Vinilos de vidriera', 4.00, 1.10, 1],
            ], 0, false],
            ['PENDIENTE', [
                ['Banner roll-up 85x200cm', 'Roll-up autoportante evento', null, null, 2],
            ], 0, false],
            ['RECHAZADA', [
                ['Toldo publicitario lona', 'Toldo entrada local', 4.50, 1.20, 1],
            ], 0, true],
            ['CONVERTIDA', [
                ['Rotulado vehicular completo', 'Rotulado flota (2 camionetas)', null, null, 2],
                ['Habladores/Stoppers', 'Habladores de góndola', null, null, 50],
            ], 150, true],
            ['APROBADA', [
                ['Letrero luminoso caja de luz', 'Caja de luz doble cara', 1.80, 0.80, 2],
            ], 0, true],
            ['PENDIENTE', [
                ['Exhibidor de piso MDF a medida', 'Exhibidor lanzamiento producto', 0.60, 1.60, 4],
                [null, 'Servicio de instalación en tienda', null, null, 1, 350.00],
            ], 0, false],
            ['VENCIDA', [
                ['Gigantografía backlight', 'Backlight sala de ventas', 2.40, 1.20, 1],
            ], 0, false],
        ];

        foreach ($plantillas as [$estado, $lineas, $descuento, $conImpuesto]) {
            $fecha = now()->subDays(fake()->numberBetween(3, 90));

            $detalles = [];
            foreach ($lineas as $linea) {
                [$nombreProducto, $descripcion, $ancho, $alto, $cantidad] = $linea;
                $precioManual = $linea[5] ?? null;
                $producto = $nombreProducto ? $productos->get($nombreProducto) : null;

                $precioUnitario = $precioManual
                    ?? $this->precioUnitario($precioSugerido, $producto, $ancho, $alto);

                $detalles[] = [
                    'producto_id' => $producto?->id,
                    'descripcion' => $descripcion,
                    'ancho' => $ancho,
                    'alto' => $alto,
                    'area_m2' => ($ancho !== null && $alto !== null) ? round($ancho * $alto, 2) : null,
                    'cantidad' => $cantidad,
                    'precio_unitario' => round($precioUnitario, 2),
                    'subtotal' => round($precioUnitario * $cantidad, 2),
                ];
            }

            $subtotal = round(array_sum(array_column($detalles, 'subtotal')), 2);
            $descuento = round(min((float) $descuento, $subtotal), 2);
            $impuesto = $conImpuesto ? round(($subtotal - $descuento) * 0.13, 2) : 0;

            $cotizacion = Cotizacion::create([
                'codigo_verificacion' => 'COT-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
                'cliente_id' => $clientes->random()->id,
                'empleado_id' => $empleados->random()->id,
                'sucursal_id' => $sucursales->random()->id,
                'fecha' => $fecha->toDateString(),
                'fecha_vencimiento' => $fecha->copy()->addDays(15)->toDateString(),
                'estado' => $estado,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'total' => round(max($subtotal - $descuento + $impuesto, 0), 2),
                'observaciones' => null,
            ]);

            $cotizacion->detalles()->createMany($detalles);
        }
    }

    private function precioUnitario(PrecioSugeridoService $precioSugerido, ?Producto $producto, ?float $ancho, ?float $alto): float
    {
        if ($producto === null) {
            return fake()->randomFloat(2, 50, 500);
        }

        try {
            $resultado = $precioSugerido->calcular($producto, $ancho, $alto);

            if ($resultado['precio_sugerido'] > 0) {
                return $resultado['precio_sugerido'];
            }
        } catch (\Throwable) {
            // Sin receta o sin medidas para el driver: se usa precio_base.
        }

        return (float) ($producto->precio_base ?? fake()->randomFloat(2, 80, 600));
    }
}
