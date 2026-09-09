<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TipoProyecto;
use App\Services\Calculo\MotorMargenService;
use App\Services\Calculo\PrecioSugeridoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CotizacionSeeder extends Seeder
{
    /**
     * Cotizaciones de prueba con hoja de costos real: cada línea toma un
     * producto del catálogo curado (ProductoSeeder), sus insumos salen del
     * BOM (App\Services\Calculo\PrecioSugeridoService) más una línea de mano
     * de obra, y el precio lo calcula el motor de margen con el nivel de
     * complejidad de la línea — la misma cadena que usa CotizacionController,
     * no una copia simplificada.
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
        $tipos = TipoProyecto::query()->where('estado', 'ACTIVO')->get()->keyBy('nombre');

        if ($clientes->isEmpty() || $empleados->isEmpty() || $sucursales->isEmpty() || $productos->isEmpty()) {
            return;
        }

        $precioSugerido = app(PrecioSugeridoService::class);
        $motor = app(MotorMargenService::class);

        // [estado, [ [producto|null, descripcion, ancho, alto, cantidad, tipoProyecto, instalacion, precioManual?], ... ], descuento, aplicaIva]
        $plantillas = [
            ['APROBADA', [
                ['Gigantografía frontlight', 'Gigantografía frontlight para fachada', 3.35, 2.00, 1, 'Básico', 0],
                ['Banner lona frontlight', 'Banner promoción temporada', 2.00, 1.00, 3, 'Básico', 0],
            ], 0, true],
            ['PENDIENTE', [
                ['Letras corpóreas 3D iluminadas', 'Logo corpóreo iluminado recepción', 1.20, 0.45, 1, 'Complejo', 250],
                ['Rotulado vinil adhesivo', 'Vinilos de vidriera', 4.00, 1.10, 1, 'Básico', 0],
            ], 0, false],
            ['PENDIENTE', [
                ['Banner roll-up 85x200cm', 'Roll-up autoportante evento', null, null, 2, 'Básico', 0],
            ], 0, false],
            ['RECHAZADA', [
                ['Toldo publicitario lona', 'Toldo entrada local', 4.50, 1.20, 1, 'Medio', 180],
            ], 0, true],
            ['CONVERTIDA', [
                ['Rotulado vehicular completo', 'Rotulado flota (2 camionetas)', null, null, 2, 'Medio', 0],
                ['Habladores/Stoppers', 'Habladores de góndola', null, null, 50, 'Básico', 0],
            ], 150, true],
            ['APROBADA', [
                ['Letrero luminoso caja de luz', 'Caja de luz doble cara', 1.80, 0.80, 2, 'Complejo', 300],
            ], 0, true],
            ['PENDIENTE', [
                ['Exhibidor de piso MDF a medida', 'Exhibidor lanzamiento producto', 0.60, 1.60, 4, 'Medio', 0],
                [null, 'Servicio de instalación en tienda', null, null, 1, null, 0, 350.00],
            ], 0, false],
            ['VENCIDA', [
                ['Gigantografía backlight', 'Backlight sala de ventas', 2.40, 1.20, 1, 'Medio', 0],
            ], 0, false],

            // Más cotizaciones APROBADAS para que PedidoSeeder genere pedidos
            // en distintas etapas (incluidos entregados) — insumo del BI.
            ['APROBADA', [
                ['Gigantografía frontlight', 'Gigantografía campaña verano', 3.00, 2.00, 2, 'Básico', 0],
                ['Banner lona frontlight', 'Banners sucursales', 2.00, 1.00, 6, 'Básico', 0],
            ], 0, true],
            ['APROBADA', [
                ['Exhibidor de piso MDF a medida', 'Exhibidores lanzamiento', 0.60, 1.60, 8, 'Medio', 0],
            ], 200, true],
            ['APROBADA', [
                ['Rotulado vinil adhesivo', 'Vinilos vidrieras temporada', 3.50, 1.20, 4, 'Básico', 0],
                ['Habladores/Stoppers', 'Habladores góndola', null, null, 80, 'Básico', 0],
            ], 0, false],
            ['APROBADA', [
                ['Letras corpóreas 3D iluminadas', 'Logo corpóreo fachada', 2.00, 0.60, 1, 'Crítico', 400],
            ], 0, true],
            ['APROBADA', [
                ['Letrero luminoso caja de luz', 'Caja de luz sucursal centro', 2.20, 0.90, 3, 'Complejo', 0],
            ], 0, true],
            ['APROBADA', [
                ['Rotulado vehicular completo', 'Rotulado 3 vehículos flota', null, null, 3, 'Medio', 0],
            ], 300, true],
        ];

        foreach ($plantillas as [$estado, $lineas, $descuento, $aplicaIva]) {
            $fecha = now()->subDays(fake()->numberBetween(3, 330));

            $detalles = [];
            foreach ($lineas as $linea) {
                [$nombreProducto, $descripcion, $ancho, $alto, $cantidad, $nombreTipo, $instalacion] = $linea;
                $precioManual = $linea[7] ?? null;

                $detalles[] = $this->armarLinea(
                    $precioSugerido,
                    $motor,
                    producto: $nombreProducto ? $productos->get($nombreProducto) : null,
                    tipoProyecto: $nombreTipo ? $tipos->get($nombreTipo) : null,
                    descripcion: $descripcion,
                    ancho: $ancho,
                    alto: $alto,
                    cantidad: (float) $cantidad,
                    instalacion: (float) $instalacion,
                    precioManual: $precioManual,
                );
            }

            $cotizacion = Cotizacion::create([
                'codigo_verificacion' => 'COT-'.$fecha->format('Ymd').'-'.Str::upper(Str::random(5)),
                'cliente_id' => $clientes->random()->id,
                'empleado_id' => $empleados->random()->id,
                'sucursal_id' => $sucursales->random()->id,
                'fecha' => $fecha->toDateString(),
                'fecha_vencimiento' => $fecha->copy()->addDays(15)->toDateString(),
                'estado' => $estado,
                'observaciones' => null,
                ...$this->totalizar($motor, $detalles, (float) $descuento, $aplicaIva),
            ]);

            foreach ($detalles as $detalle) {
                $items = $detalle['items'];
                unset($detalle['items']);

                $cotizacion->detalles()->create($detalle)->items()->createMany($items);
            }
        }
    }

    /**
     * Arma una línea completa: hoja de costos (BOM + mano de obra) → motor de
     * margen → precio unitario. Si el producto no tiene receta (o le faltan
     * medidas para el driver), cae a `precio_base` con precio manual, igual
     * que haría el vendedor en pantalla.
     *
     * @return array<string, mixed>
     */
    private function armarLinea(
        PrecioSugeridoService $precioSugerido,
        MotorMargenService $motor,
        ?Producto $producto,
        ?TipoProyecto $tipoProyecto,
        string $descripcion,
        ?float $ancho,
        ?float $alto,
        float $cantidad,
        float $instalacion,
        ?float $precioManual,
    ): array {
        $items = $precioManual === null ? $this->insumos($precioSugerido, $producto, $ancho, $alto) : [];
        $costoBaseUnitario = round(array_sum(array_column($items, 'subtotal')), 2);

        $resultado = $motor->calcularCon($tipoProyecto, $costoBaseUnitario, $instalacion);

        $esManual = $items === [];
        $precioUnitario = $esManual
            ? round($precioManual ?? (float) ($producto->precio_base ?? fake()->randomFloat(2, 80, 600)), 2)
            : round($resultado->precio, 2);

        return [
            'producto_id' => $producto?->id,
            'tipo_proyecto_id' => $esManual ? null : $tipoProyecto?->id,
            'descripcion' => $descripcion,
            'ancho' => $ancho,
            'alto' => $alto,
            'area_m2' => ($ancho !== null && $alto !== null) ? round($ancho * $alto, 2) : null,
            'cantidad' => $cantidad,
            'costo_base' => round($costoBaseUnitario * $cantidad, 2),
            'factor_complejidad' => round($resultado->factorComplejidad, 2),
            'margen_aplicado' => round($resultado->margen, 4),
            'costo_ajustado' => round($resultado->costoAjustado * $cantidad, 2),
            'precio_unitario' => $precioUnitario,
            'precio_manual' => $esManual ? 'SI' : 'NO',
            'instalacion' => $instalacion,
            'subtotal' => round($precioUnitario * $cantidad, 2),
            'items' => $items,
        ];
    }

    /**
     * Insumos de UNA unidad: los materiales del BOM más las horas de mano de
     * obra, que el Excel siempre cargaba a mano porque no salen del
     * inventario.
     *
     * @return list<array<string, mixed>>
     */
    private function insumos(PrecioSugeridoService $precioSugerido, ?Producto $producto, ?float $ancho, ?float $alto): array
    {
        if ($producto === null) {
            return [];
        }

        try {
            $insumos = $precioSugerido->calcular($producto, $ancho, $alto)['insumos'];
        } catch (\Throwable) {
            // Sin receta o sin medidas para el driver: la línea queda con
            // precio manual (precio_base del catálogo).
            return [];
        }

        if ($insumos === []) {
            return [];
        }

        $horas = fake()->numberBetween(2, 8);
        $costoHora = 15.0;

        return [...$insumos, [
            'material_id' => null,
            'tipo' => 'MANO_OBRA',
            'descripcion' => 'Horas de taller',
            'unidad' => 'HORA',
            'cantidad' => $horas,
            'costo_unitario' => $costoHora,
            'subtotal' => round($horas * $costoHora, 4),
        ]];
    }

    /**
     * Totales de cabecera: la misma agregación que hace
     * CotizacionController::calcularMontos.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return array<string, mixed>
     */
    private function totalizar(MotorMargenService $motor, array $detalles, float $descuento, bool $aplicaIva): array
    {
        $subtotal = round(array_sum(array_column($detalles, 'subtotal')), 2);
        $descuento = round(min($descuento, $subtotal), 2);
        $instalacion = round(array_sum(array_column($detalles, 'instalacion')), 2);
        $costoBase = round(array_sum(array_column($detalles, 'costo_base')), 2);
        $costoAjustado = round(array_sum(array_column($detalles, 'costo_ajustado')), 2);

        $resultado = $motor->evaluar(
            costoBase: $costoBase,
            costoAjustado: $costoAjustado,
            precio: $subtotal - $descuento,
            instalacion: $instalacion,
        );

        $iva = $aplicaIva ? round($resultado->iva, 2) : 0.0;

        return [
            'costo_base' => $costoBase,
            'costo_ajustado' => $costoAjustado,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'impuesto' => $iva,
            'it' => round($resultado->it, 2),
            'iue' => round($resultado->iue, 2),
            'utilidad_real' => round($resultado->utilidadReal, 2),
            'instalacion' => $instalacion,
            'estado_margen' => $resultado->estado,
            'recomendacion' => $resultado->recomendacion,
            'total' => round(max($subtotal - $descuento + $iva + $instalacion, 0), 2),
        ];
    }
}
