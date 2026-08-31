<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoriaMaterialController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClientePortalController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NotaEntregaController;
use App\Http\Controllers\OrdenCompraClienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoMaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VerificacionController;
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

Route::get('/dashboard', [ReporteController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Verificación pública de autenticidad de un presupuesto (sin login).
Route::get('/verificar/{codigo}', [VerificacionController::class, 'show'])
    ->where('codigo', '[A-Za-z0-9\-]+')
    ->name('verificar');

// --- Portal del cliente (rol `cliente`, sin acceso al panel interno) ---
Route::middleware(['auth', 'role:cliente'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/cotizaciones', [ClientePortalController::class, 'cotizaciones'])->name('cotizaciones');
    Route::get('/cotizaciones/{cotizacion}', [ClientePortalController::class, 'cotizacion'])->name('cotizacion');
    Route::post('/cotizaciones/{cotizacion}/responder', [ClientePortalController::class, 'responder'])->name('responder');
    Route::get('/pedidos', [ClientePortalController::class, 'pedidos'])->name('pedidos');
    Route::get('/pedidos/{pedido}', [ClientePortalController::class, 'pedido'])->name('pedido');
    Route::get('/solicitar', [ClientePortalController::class, 'solicitar'])->name('solicitar');
    Route::post('/solicitar', [ClientePortalController::class, 'solicitarStore'])->name('solicitar.store');
});

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

    // --- Compras (ingreso de materiales al inventario) ---
    Route::get('/compras', [CompraController::class, 'index'])
        ->middleware('can:compras.ver')
        ->name('compras.index');
    Route::get('/compras/crear', [CompraController::class, 'create'])
        ->middleware('can:compras.crear')
        ->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])
        ->middleware('can:compras.crear')
        ->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])
        ->middleware('can:compras.ver')
        ->name('compras.show');
    Route::get('/compras/{compra}/editar', [CompraController::class, 'edit'])
        ->middleware('can:compras.editar')
        ->name('compras.edit');
    Route::put('/compras/{compra}', [CompraController::class, 'update'])
        ->middleware('can:compras.editar')
        ->name('compras.update');
    Route::delete('/compras/{compra}', [CompraController::class, 'destroy'])
        ->middleware('can:compras.eliminar')
        ->name('compras.destroy');
    Route::post('/compras/{compra}/aprobar', [CompraController::class, 'aprobar'])
        ->middleware('can:compras.aprobar')
        ->name('compras.aprobar');
    Route::post('/compras/{compra}/anular', [CompraController::class, 'anular'])
        ->middleware('can:compras.aprobar')
        ->name('compras.anular');

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

    // --- Pedidos / órdenes de trabajo ---
    Route::get('/pedidos', [PedidoController::class, 'index'])
        ->middleware('can:pedidos.ver')
        ->name('pedidos.index');
    Route::get('/pedidos/crear', [PedidoController::class, 'create'])
        ->middleware('can:pedidos.crear')
        ->name('pedidos.create');
    Route::post('/pedidos', [PedidoController::class, 'store'])
        ->middleware('can:pedidos.crear')
        ->name('pedidos.store');
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])
        ->middleware('can:pedidos.ver')
        ->name('pedidos.show');
    Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])
        ->middleware('can:pedidos.actualizar_estado')
        ->name('pedidos.cancelar');
    Route::post('/pedidos/{pedido}/detalle/{detalle}/asignar-area', [PedidoController::class, 'asignarArea'])
        ->middleware('can:pedidos.asignar_area')
        ->name('pedidos.detalle.asignar-area');
    Route::put('/pedidos/{pedido}/detalle/{detalle}/estado', [PedidoController::class, 'actualizarEstado'])
        ->middleware('can:pedidos.actualizar_estado')
        ->name('pedidos.detalle.estado');
    Route::post('/pedidos/{pedido}/detalle/{detalle}/consumo', [PedidoController::class, 'registrarConsumo'])
        ->middleware('can:pedidos.actualizar_estado')
        ->name('pedidos.detalle.consumo');

    // --- Órdenes de compra de cliente ---
    Route::get('/ordenes-compra-cliente', [OrdenCompraClienteController::class, 'index'])
        ->middleware('can:ordenes-compra-cliente.ver')
        ->name('ordenes-compra-cliente.index');
    Route::post('/ordenes-compra-cliente', [OrdenCompraClienteController::class, 'store'])
        ->middleware('can:ordenes-compra-cliente.crear')
        ->name('ordenes-compra-cliente.store');
    Route::put('/ordenes-compra-cliente/{ordenCompra}', [OrdenCompraClienteController::class, 'update'])
        ->middleware('can:ordenes-compra-cliente.crear')
        ->name('ordenes-compra-cliente.update');
    Route::post('/ordenes-compra-cliente/{ordenCompra}/validar', [OrdenCompraClienteController::class, 'validar'])
        ->middleware('can:ordenes-compra-cliente.validar')
        ->name('ordenes-compra-cliente.validar');
    Route::post('/ordenes-compra-cliente/{ordenCompra}/anular', [OrdenCompraClienteController::class, 'anular'])
        ->middleware('can:ordenes-compra-cliente.validar')
        ->name('ordenes-compra-cliente.anular');

    // --- Notas de entrega ---
    Route::get('/notas-entrega', [NotaEntregaController::class, 'index'])
        ->middleware('can:notas-entrega.ver')
        ->name('notas-entrega.index');
    Route::get('/notas-entrega/crear', [NotaEntregaController::class, 'create'])
        ->middleware('can:notas-entrega.crear')
        ->name('notas-entrega.create');
    Route::post('/notas-entrega', [NotaEntregaController::class, 'store'])
        ->middleware('can:notas-entrega.crear')
        ->name('notas-entrega.store');
    Route::get('/notas-entrega/{notasEntrega}', [NotaEntregaController::class, 'show'])
        ->middleware('can:notas-entrega.ver')
        ->name('notas-entrega.show');

    // --- Pagos ---
    Route::get('/pagos', [PagoController::class, 'index'])
        ->middleware('can:pagos.ver')
        ->name('pagos.index');
    Route::post('/pagos', [PagoController::class, 'store'])
        ->middleware('can:pagos.registrar')
        ->name('pagos.store');

    // --- Seguridad: usuarios ---
    Route::get('/usuarios', [UsuarioController::class, 'index'])
        ->middleware('can:usuarios.ver')
        ->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])
        ->middleware('can:usuarios.crear')
        ->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])
        ->middleware('can:usuarios.editar')
        ->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])
        ->middleware('can:usuarios.eliminar')
        ->name('usuarios.destroy');

    // --- Reportes / Inteligencia de negocios ---
    Route::get('/reportes/financiero', [ReporteController::class, 'financiero'])
        ->middleware('can:reportes.financiero')
        ->name('reportes.financiero');
    Route::get('/reportes/produccion', [ReporteController::class, 'produccion'])
        ->middleware('can:reportes.produccion')
        ->name('reportes.produccion');
    Route::get('/reportes/bi', [ReporteController::class, 'bi'])
        ->middleware('can:reportes.bi')
        ->name('reportes.bi');

    // --- Seguridad: roles y permisos ---
    Route::get('/roles', [RolController::class, 'index'])
        ->middleware('can:roles.ver')
        ->name('roles.index');
    Route::get('/roles/crear', [RolController::class, 'create'])
        ->middleware('can:roles.crear')
        ->name('roles.create');
    Route::post('/roles', [RolController::class, 'store'])
        ->middleware('can:roles.crear')
        ->name('roles.store');
    Route::get('/roles/{rol}/editar', [RolController::class, 'edit'])
        ->middleware('can:roles.editar')
        ->name('roles.edit');
    Route::put('/roles/{rol}', [RolController::class, 'update'])
        ->middleware('can:roles.editar')
        ->name('roles.update');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])
        ->middleware('can:roles.eliminar')
        ->name('roles.destroy');
});

require __DIR__.'/auth.php';
