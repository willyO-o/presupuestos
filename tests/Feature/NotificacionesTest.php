<?php

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\AvancePedidoActualizado;
use App\Notifications\CotizacionAprobada;
use App\Notifications\CotizacionSolicitada;
use App\Notifications\OrdenCompraEmitida;
use App\Notifications\OrdenEntregaEmitida;
use App\Notifications\PagoRegistrado;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'compras.crear', 'pagos.registrar', 'cotizaciones.ver', 'cotizaciones.aprobar',
        'pedidos.ver', 'pedidos.actualizar_estado', 'notas-entrega.crear',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

    Role::findOrCreate('administrador', 'web');
    Role::findOrCreate('cliente', 'web');
});

function administrador(): User
{
    $user = User::factory()->create();
    $user->assignRole('administrador');

    return $user;
}

function staff(string ...$permissions): User
{
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * Cotización con un vendedor (empleado con cuenta de usuario vinculada), que
 * es el destinatario esperado de las notificaciones de "vendedor".
 *
 * @return array{0: User, 1: Cotizacion}
 */
function cotizacionConVendedor(array $atributos = []): array
{
    $vendedorUser = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $vendedorUser->id]);
    $cotizacion = Cotizacion::factory()->create([...$atributos, 'empleado_id' => $empleado->id]);

    return [$vendedorUser, $cotizacion];
}

test('storing a compra notifies every administrador', function () {
    Notification::fake();
    $admin = administrador();
    $otroAdmin = administrador();
    User::factory()->create(); // no es admin, no debe recibir nada
    $material = Material::factory()->create();

    $this->actingAs(staff('compras.crear'))->post(route('compras.store'), [
        'proveedor_id' => Proveedor::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'numero_factura' => 'F-001',
        'fecha' => now()->toDateString(),
        'detalles' => [['material_id' => $material->id, 'cantidad' => 2, 'precio_unitario' => 50]],
    ])->assertRedirect();

    Notification::assertSentTo([$admin, $otroAdmin], OrdenCompraEmitida::class);
    Notification::assertCount(2);
});

test('registering a pago notifies every administrador', function () {
    Notification::fake();
    $admin = administrador();
    $pedido = Pedido::factory()->create(['total' => 500]);

    $this->actingAs(staff('pagos.registrar'))->post(route('pagos.store'), [
        'pedido_id' => $pedido->id,
        'monto' => 200,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => 'QR',
    ])->assertRedirect();

    Notification::assertSentTo($admin, PagoRegistrado::class);
});

test('a portal cotizacion request notifies every administrador', function () {
    Notification::fake();
    $admin = administrador();
    Sucursal::factory()->create(['estado' => 'ACTIVO']);
    $producto = Producto::factory()->create();

    $clienteUser = User::factory()->create();
    $clienteUser->assignRole('cliente');
    Cliente::factory()->create(['user_id' => $clienteUser->id]);

    $this->actingAs($clienteUser)->post(route('portal.solicitar.store'), [
        'detalles' => [
            ['producto_id' => $producto->id, 'descripcion' => 'Vinil vitrina', 'cantidad' => 1],
        ],
    ])->assertRedirect();

    Notification::assertSentTo($admin, CotizacionSolicitada::class);
});

test('approving a cotizacion as staff notifies its vendedor, but not for a rechazo', function () {
    Notification::fake();
    [$vendedorUser, $cotizacion] = cotizacionConVendedor(['estado' => 'PENDIENTE', 'total' => 100]);

    $this->actingAs(staff('cotizaciones.ver', 'cotizaciones.aprobar'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertRedirect();

    Notification::assertSentTo($vendedorUser, CotizacionAprobada::class);

    [$vendedorUser2, $cotizacion2] = cotizacionConVendedor(['estado' => 'PENDIENTE', 'total' => 100]);
    $this->actingAs(staff('cotizaciones.ver', 'cotizaciones.aprobar'))
        ->post(route('cotizaciones.rechazar', $cotizacion2))
        ->assertRedirect();

    Notification::assertNotSentTo($vendedorUser2, CotizacionAprobada::class);
});

test('a cliente approving their cotizacion from the portal notifies its vendedor', function () {
    Notification::fake();
    [$vendedorUser, $cotizacion] = cotizacionConVendedor(['estado' => 'PENDIENTE', 'total' => 500]);

    $clienteUser = User::factory()->create();
    $clienteUser->assignRole('cliente');
    $cotizacion->cliente->update(['user_id' => $clienteUser->id]);

    $this->actingAs($clienteUser)
        ->post(route('portal.responder', $cotizacion), ['accion' => 'aprobar'])
        ->assertRedirect();

    Notification::assertSentTo($vendedorUser, CotizacionAprobada::class);
});

test('advancing a pedido item notifies its vendedor, unless the vendedor is the one advancing it', function () {
    Notification::fake();
    [$vendedorUser, $cotizacion] = cotizacionConVendedor(['estado' => 'CONVERTIDA']);
    $pedido = Pedido::factory()->create(['cotizacion_id' => $cotizacion->id]);
    $detalle = PedidoDetalle::factory()->for($pedido)->create(['estado_item' => 'DISENO']);

    // Otro miembro del staff avanza el item: el vendedor SÍ se entera.
    $this->actingAs(staff('pedidos.ver', 'pedidos.actualizar_estado'))
        ->put(route('pedidos.detalle.estado', [$pedido, $detalle]), ['estado_item' => 'ELABORACION'])
        ->assertRedirect();

    Notification::assertSentTo($vendedorUser, AvancePedidoActualizado::class);

    // El propio vendedor avanza el item: no se auto-notifica. Alcance TODAS
    // para que pase la verificación de sucursal (su ficha de empleado y la
    // cotización nacieron con sucursales aleatorias distintas).
    Notification::fake();
    $vendedorUser->update(['alcance_sucursal' => 'TODAS']);
    $vendedorUser->givePermissionTo('pedidos.ver', 'pedidos.actualizar_estado');

    $this->actingAs($vendedorUser)
        ->put(route('pedidos.detalle.estado', [$pedido, $detalle]), ['estado_item' => 'ACABADO'])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('issuing a nota de entrega notifies the pedido vendedor', function () {
    Notification::fake();
    [$vendedorUser, $cotizacion] = cotizacionConVendedor(['estado' => 'CONVERTIDA']);
    $pedido = Pedido::factory()->create(['cotizacion_id' => $cotizacion->id, 'estado' => 'ACABADO']);
    $detalle = PedidoDetalle::factory()->for($pedido)->create(['estado_item' => 'ACABADO']);
    $empleado = Empleado::factory()->create();

    $this->actingAs(staff('notas-entrega.crear'))->post(route('notas-entrega.store'), [
        'pedido_id' => $pedido->id,
        'empleado_id' => $empleado->id,
        'fecha_entrega' => now()->toDateString(),
        'detalles' => [[
            'pedido_detalle_id' => $detalle->id,
            'descripcion' => $detalle->descripcion,
            'cantidad_entregada' => 1,
        ]],
    ])->assertRedirect();

    Notification::assertSentTo($vendedorUser, OrdenEntregaEmitida::class);
});

test('a user only sees their own notifications and marking one read redirects to its url', function () {
    $user = User::factory()->create();
    $otro = User::factory()->create();

    $compra = Compra::factory()->create();
    $user->notify(new OrdenCompraEmitida($compra));
    $otro->notify(new OrdenCompraEmitida(Compra::factory()->create()));

    $this->actingAs($user)
        ->get(route('notificaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Notificaciones/Index')
            ->has('notificaciones.data', 1)
            ->where('noLeidas', 1));

    $notificacion = $user->notifications()->first();
    expect($notificacion->read_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('notificaciones.abrir', $notificacion->id))
        ->assertRedirect(route('compras.show', $compra));

    expect($notificacion->fresh()->read_at)->not->toBeNull();
});

test('a user cannot open another user notification', function () {
    $user = User::factory()->create();
    $otro = User::factory()->create();
    $otro->notify(new OrdenCompraEmitida(Compra::factory()->create()));
    $notificacion = $otro->notifications()->first();

    $this->actingAs($user)
        ->get(route('notificaciones.abrir', $notificacion->id))
        ->assertForbidden();
});

test('marcar todas leidas clears every unread notification for the user', function () {
    $user = User::factory()->create();
    $user->notify(new OrdenCompraEmitida(Compra::factory()->create()));
    $user->notify(new OrdenCompraEmitida(Compra::factory()->create()));

    $this->actingAs($user)
        ->post(route('notificaciones.marcar-todas'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
