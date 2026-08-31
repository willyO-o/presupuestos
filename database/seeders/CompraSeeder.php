<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Proveedor;
use App\Services\Compra\AprobarCompraService;
use Illuminate\Database\Seeder;

class CompraSeeder extends Seeder
{
    /**
     * Compras de prueba con detalle real sobre el catálogo curado de
     * MaterialSeeder. Las PAGADA se aprueban con AprobarCompraService (la
     * misma lógica del controlador): suben stock, actualizan el precio del
     * material y dejan historial — así el módulo de BI tiene evolución de
     * costos con la que trabajar.
     *
     * No es idempotente (ver .ai/rules/seeders.md): se salta si ya hay compras.
     */
    public function run(): void
    {
        if (Compra::query()->exists()) {
            return;
        }

        $empleados = Empleado::query()->where('estado', 'ACTIVO')->get();
        $proveedores = Proveedor::query()->where('estado', 'ACTIVO')->get();
        $materiales = Material::query()->get()->keyBy('nombre');

        if ($empleados->isEmpty() || $proveedores->isEmpty() || $materiales->isEmpty()) {
            return;
        }

        $aprobar = app(AprobarCompraService::class);

        // [estado, diasAtras, [ [nombreMaterial, cantidad, precioUnitario], ... ]]
        $plantillas = [
            ['PAGADA', 150, [
                ['Lona FrontLight 13oz', 3, 2750.00],
                ['Vinil adhesivo brillante', 2, 2200.00],
            ]],
            ['PAGADA', 120, [
                ['Tubo cuadrado 20x20x0,9mm', 30, 11.50],
                ['Tubo cuadrado 40x40x1,5mm', 18, 23.00],
                ['Platina 1x1/8', 12, 9.20],
            ]],
            ['PAGADA', 95, [
                ['MDF 9mm', 15, 185.00],
                ['Melamina blanca 18mm', 6, 430.00],
                ['Tornillos autorroscantes', 500, 0.36],
            ]],
            ['PAGADA', 60, [
                ['Lona FrontLight 13oz', 2, 2900.00],
                ['Lona Backlight', 1, 3700.00],
            ]],
            ['PAGADA', 40, [
                ['Acrílico transparente 3mm', 4, 540.00],
                ['Silicona industrial', 20, 29.00],
                ['Cinta doble contacto', 100, 1.90],
            ]],
            ['PAGADA', 25, [
                ['Pintura esmalte sintético', 8, 150.00],
                ['Thinner', 6, 72.00],
                ['Primer anticorrosivo', 4, 135.00],
            ]],
            ['PAGADA', 15, [
                ['Vinil adhesivo brillante', 3, 2350.00],
                ['MDF 9mm', 10, 192.00],
                ['Tubo cuadrado 20x20x0,9mm', 20, 12.80],
                ['Melamina blanca 18mm', 4, 445.00],
            ]],
            ['PENDIENTE', 8, [
                ['Tubo cuadrado 20x20x0,9mm', 24, 12.00],
                ['Angular 1x1/8', 12, 10.50],
            ]],
            ['PENDIENTE', 3, [
                ['Vinil microperforado (vision control)', 2, 3100.00],
            ]],
        ];

        foreach ($plantillas as [$estado, $diasAtras, $lineas]) {
            $fecha = now()->subDays($diasAtras);

            $detalles = [];
            foreach ($lineas as [$nombreMaterial, $cantidad, $precioUnitario]) {
                $material = $materiales->get($nombreMaterial);
                if ($material === null) {
                    continue;
                }

                $detalles[] = [
                    'material_id' => $material->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => round($cantidad * $precioUnitario, 2),
                ];
            }

            if ($detalles === []) {
                continue;
            }

            $compra = Compra::create([
                'proveedor_id' => $proveedores->random()->id,
                'empleado_id' => $empleados->random()->id,
                'numero_factura' => fake()->numerify('F-#####'),
                'fecha' => $fecha->toDateString(),
                'estado' => 'PENDIENTE',
                'total' => round(array_sum(array_column($detalles, 'subtotal')), 2),
            ]);

            $compra->detalles()->createMany($detalles);

            if ($estado === 'PAGADA') {
                $aprobar->aprobar($compra->fresh());
            }
        }
    }
}
