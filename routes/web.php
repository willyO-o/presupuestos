<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoriaMaterialController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoMaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SucursalController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return inertia('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/sucursales', [SucursalController::class, 'index'])
        ->middleware('can:sucursales.ver')
        ->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])
        ->middleware('can:sucursales.crear')
        ->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])
        ->middleware('can:sucursales.editar')
        ->name('sucursales.update');
    Route::delete('/sucursales/{sucursal}', [SucursalController::class, 'destroy'])
        ->middleware('can:sucursales.eliminar')
        ->name('sucursales.destroy');

    Route::get('/categorias-material', [CategoriaMaterialController::class, 'index'])
        ->middleware('can:categorias-material.ver')
        ->name('categorias-material.index');
    Route::post('/categorias-material', [CategoriaMaterialController::class, 'store'])
        ->middleware('can:categorias-material.crear')
        ->name('categorias-material.store');
    Route::put('/categorias-material/{categoriaMaterial}', [CategoriaMaterialController::class, 'update'])
        ->middleware('can:categorias-material.editar')
        ->name('categorias-material.update');
    Route::delete('/categorias-material/{categoriaMaterial}', [CategoriaMaterialController::class, 'destroy'])
        ->middleware('can:categorias-material.eliminar')
        ->name('categorias-material.destroy');

    Route::get('/categorias-producto', [CategoriaProductoController::class, 'index'])
        ->middleware('can:categorias-producto.ver')
        ->name('categorias-producto.index');
    Route::post('/categorias-producto', [CategoriaProductoController::class, 'store'])
        ->middleware('can:categorias-producto.crear')
        ->name('categorias-producto.store');
    Route::put('/categorias-producto/{categoriaProducto}', [CategoriaProductoController::class, 'update'])
        ->middleware('can:categorias-producto.editar')
        ->name('categorias-producto.update');
    Route::delete('/categorias-producto/{categoriaProducto}', [CategoriaProductoController::class, 'destroy'])
        ->middleware('can:categorias-producto.eliminar')
        ->name('categorias-producto.destroy');

    Route::get('/proveedores', [ProveedorController::class, 'index'])
        ->middleware('can:proveedores.ver')
        ->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])
        ->middleware('can:proveedores.crear')
        ->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->middleware('can:proveedores.editar')
        ->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])
        ->middleware('can:proveedores.eliminar')
        ->name('proveedores.destroy');

    Route::get('/areas', [AreaController::class, 'index'])
        ->middleware('can:areas.ver')
        ->name('areas.index');
    Route::post('/areas', [AreaController::class, 'store'])
        ->middleware('can:areas.crear')
        ->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class, 'update'])
        ->middleware('can:areas.editar')
        ->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])
        ->middleware('can:areas.eliminar')
        ->name('areas.destroy');

    Route::get('/empleados', [EmpleadoController::class, 'index'])
        ->middleware('can:empleados.ver')
        ->name('empleados.index');
    Route::post('/empleados', [EmpleadoController::class, 'store'])
        ->middleware('can:empleados.crear')
        ->name('empleados.store');
    Route::put('/empleados/{empleado}', [EmpleadoController::class, 'update'])
        ->middleware('can:empleados.editar')
        ->name('empleados.update');
    Route::delete('/empleados/{empleado}', [EmpleadoController::class, 'destroy'])
        ->middleware('can:empleados.eliminar')
        ->name('empleados.destroy');

    Route::get('/clientes', [ClienteController::class, 'index'])
        ->middleware('can:clientes.ver')
        ->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'store'])
        ->middleware('can:clientes.crear')
        ->name('clientes.store');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])
        ->middleware('can:clientes.editar')
        ->name('clientes.update');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])
        ->middleware('can:clientes.eliminar')
        ->name('clientes.destroy');

    Route::get('/materiales', [MaterialController::class, 'index'])
        ->middleware('can:materiales.ver')
        ->name('materiales.index');
    Route::post('/materiales', [MaterialController::class, 'store'])
        ->middleware('can:materiales.crear')
        ->name('materiales.store');
    Route::put('/materiales/{material}', [MaterialController::class, 'update'])
        ->middleware('can:materiales.editar')
        ->name('materiales.update');
    Route::delete('/materiales/{material}', [MaterialController::class, 'destroy'])
        ->middleware('can:materiales.eliminar')
        ->name('materiales.destroy');

    Route::get('/productos', [ProductoController::class, 'index'])
        ->middleware('can:productos.ver')
        ->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])
        ->middleware('can:productos.crear')
        ->name('productos.store');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])
        ->middleware('can:productos.editar')
        ->name('productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])
        ->middleware('can:productos.eliminar')
        ->name('productos.destroy');

    // Receta/BOM de un producto (App\Models\ProductoMaterial) — gestionada
    // bajo el mismo permiso que editar el producto (config/acl.php:
    // "productos.editar (incluye receta/BOM)").
    Route::get('/productos/{producto}/materiales', [ProductoMaterialController::class, 'index'])
        ->middleware('can:productos.editar')
        ->name('productos.materiales.index');
    Route::post('/productos/{producto}/materiales', [ProductoMaterialController::class, 'store'])
        ->middleware('can:productos.editar')
        ->name('productos.materiales.store');
    Route::put('/productos/{producto}/materiales/{productoMaterial}', [ProductoMaterialController::class, 'update'])
        ->middleware('can:productos.editar')
        ->name('productos.materiales.update');
    Route::delete('/productos/{producto}/materiales/{productoMaterial}', [ProductoMaterialController::class, 'destroy'])
        ->middleware('can:productos.editar')
        ->name('productos.materiales.destroy');

    Route::get('/formulas', [FormulaController::class, 'index'])
        ->middleware('can:formulas.ver')
        ->name('formulas.index');
    Route::post('/formulas', [FormulaController::class, 'store'])
        ->middleware('can:formulas.crear')
        ->name('formulas.store');
    Route::put('/formulas/{formula}', [FormulaController::class, 'update'])
        ->middleware('can:formulas.editar')
        ->name('formulas.update');
    Route::delete('/formulas/{formula}', [FormulaController::class, 'destroy'])
        ->middleware('can:formulas.eliminar')
        ->name('formulas.destroy');
    Route::post('/formulas/probar', [FormulaController::class, 'probar'])
        ->middleware('can:formulas.ver')
        ->name('formulas.probar');

    // --- Cotizaciones (presupuestos) ---
    Route::get('/cotizaciones', [CotizacionController::class, 'index'])
        ->middleware('can:cotizaciones.ver')
        ->name('cotizaciones.index');
    Route::get('/cotizaciones/crear', [CotizacionController::class, 'create'])
        ->middleware('can:cotizaciones.crear')
        ->name('cotizaciones.create');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])
        ->middleware('can:cotizaciones.crear')
        ->name('cotizaciones.store');
    // Costeo de un producto para una línea (JSON, sin guardar) — antes de la
    // ruta con {cotizacion} para que "costear" no se tome como un id.
    Route::post('/cotizaciones/costear', [CotizacionController::class, 'costear'])
        ->middleware('can:cotizaciones.crear')
        ->name('cotizaciones.costear');
    Route::get('/cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])
        ->middleware('can:cotizaciones.ver')
        ->name('cotizaciones.show');
    Route::get('/cotizaciones/{cotizacion}/editar', [CotizacionController::class, 'edit'])
        ->middleware('can:cotizaciones.editar')
        ->name('cotizaciones.edit');
    Route::put('/cotizaciones/{cotizacion}', [CotizacionController::class, 'update'])
        ->middleware('can:cotizaciones.editar')
        ->name('cotizaciones.update');
    Route::delete('/cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy'])
        ->middleware('can:cotizaciones.eliminar')
        ->name('cotizaciones.destroy');
    Route::post('/cotizaciones/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->middleware('can:cotizaciones.aprobar')
        ->name('cotizaciones.aprobar');
    Route::post('/cotizaciones/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar'])
        ->middleware('can:cotizaciones.aprobar')
        ->name('cotizaciones.rechazar');
});

require __DIR__.'/auth.php';
