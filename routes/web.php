<?php

use App\Http\Controllers\ProfileController;
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
});

require __DIR__.'/auth.php';
