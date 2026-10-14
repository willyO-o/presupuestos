<?php

use App\Models\Cotizacion;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CategoriaMaterialSeeder;
use Database\Seeders\CategoriaProductoSeeder;
use Database\Seeders\ClienteSeeder;
use Database\Seeders\EmpleadoSeeder;
use Database\Seeders\FamiliaProductoSeeder;
use Database\Seeders\FormulaSeeder;
use Database\Seeders\HistorialComercialSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ProductoMaterialSeeder;
use Database\Seeders\ProductoSeeder;
use Database\Seeders\ProveedorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Database\Seeders\TipoProyectoSeeder;

/**
 * La cantidad de meses de los datos de prueba sale de
 * `BI_DEMO_MESES_HISTORIAL` (config/bi.php), para poder sembrar 12 meses en
 * una demo en vez de los 24 por defecto.
 */
test('el historial de prueba simula los meses que dice la configuracion', function () {
    HistorialComercialSeeder::$mesesParaPruebas = null;
    config(['bi.demo_meses_historial' => 3]);

    $this->seed([
        RolesAndPermissionsSeeder::class, SucursalSeeder::class, AreaSeeder::class,
        CategoriaMaterialSeeder::class, CategoriaProductoSeeder::class, FamiliaProductoSeeder::class,
        ProveedorSeeder::class, ClienteSeeder::class, EmpleadoSeeder::class, MaterialSeeder::class,
        ProductoSeeder::class, FormulaSeeder::class, ProductoMaterialSeeder::class, TipoProyectoSeeder::class,
        HistorialComercialSeeder::class,
    ]);

    $primera = Cotizacion::query()->min('fecha');

    expect(substr((string) $primera, 0, 7))->toBe(now()->startOfMonth()->subMonths(3)->format('Y-m'));
});
