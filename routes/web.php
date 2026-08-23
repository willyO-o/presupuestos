<?php

use App\Http\Controllers\CategoriaMaterialController;
use App\Http\Controllers\CategoriaProductoController;
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
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
});

require __DIR__.'/auth.php';
