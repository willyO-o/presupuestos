<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('cliente', 'web');
});

function clienteConPortal(): array
{
    $user = User::factory()->create();
    $user->assignRole('cliente');
    $cliente = Cliente::factory()->create(['user_id' => $user->id]);

    return [$user, $cliente];
}

test('staff without the cliente role cannot enter the portal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.cotizaciones'))
        ->assertForbidden();
});

test('a cliente only sees their own cotizaciones', function () {
    [$user, $cliente] = clienteConPortal();
    Cotizacion::factory()->count(2)->create(['cliente_id' => $cliente->id]);
    Cotizacion::factory()->create(); // de otro cliente

    $this->actingAs($user)
        ->get(route('portal.cotizaciones'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Cotizaciones')->has('cotizaciones.data', 2));
});

test('a cliente cannot open another cliente cotizacion', function () {
    [$user] = clienteConPortal();
    $ajena = Cotizacion::factory()->create();

    $this->actingAs($user)->get(route('portal.cotizacion', $ajena))->assertForbidden();
});

test('a cliente can approve their own pending cotizacion with a total', function () {
    [$user, $cliente] = clienteConPortal();
    $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'PENDIENTE', 'total' => 500]);

    $this->actingAs($user)
        ->post(route('portal.responder', $cotizacion), ['accion' => 'aprobar'])
        ->assertRedirect(route('portal.cotizacion', $cotizacion));

    expect($cotizacion->fresh()->estado)->toBe('APROBADA');
});

test('a cliente cannot respond to a cotizacion that is not theirs', function () {
    [$user] = clienteConPortal();
    $ajena = Cotizacion::factory()->create(['estado' => 'PENDIENTE', 'total' => 500]);

    $this->actingAs($user)
        ->post(route('portal.responder', $ajena), ['accion' => 'aprobar'])
        ->assertForbidden();

    expect($ajena->fresh()->estado)->toBe('PENDIENTE');
});

test('a portal request creates a PENDIENTE cotizacion with no vendedor and zero total', function () {
    [$user, $cliente] = clienteConPortal();
    Sucursal::factory()->create(['estado' => 'ACTIVO']);
    $producto = Producto::factory()->create();

    $this->actingAs($user)->post(route('portal.solicitar.store'), [
        'observaciones' => 'Para la vitrina',
        'detalles' => [
            ['producto_id' => $producto->id, 'descripcion' => 'Vinil vitrina', 'cantidad' => 2],
        ],
    ])->assertRedirect();

    $cotizacion = Cotizacion::where('cliente_id', $cliente->id)->latest('id')->first();
    expect($cotizacion->estado)->toBe('PENDIENTE')
        ->and($cotizacion->empleado_id)->toBeNull()
        ->and((float) $cotizacion->total)->toBe(0.0)
        ->and($cotizacion->detalles)->toHaveCount(1);
});

test('a cliente only sees their own pedidos', function () {
    [$user, $cliente] = clienteConPortal();
    $propia = Cotizacion::factory()->convertida()->create(['cliente_id' => $cliente->id]);
    Pedido::factory()->create(['cotizacion_id' => $propia->id]);
    Pedido::factory()->create();

    $this->actingAs($user)
        ->get(route('portal.pedidos'))
        ->assertInertia(fn ($page) => $page->has('pedidos.data', 1));
});

test('a cliente is redirected to the portal after login and away from the dashboard', function () {
    [$user] = clienteConPortal();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('portal.cotizaciones'));

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.cotizaciones'));
});
