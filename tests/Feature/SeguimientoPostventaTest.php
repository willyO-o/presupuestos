<?php

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\SeguimientoPostventa;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    collect(['seguimientos-postventa.ver', 'seguimientos-postventa.registrar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function usuarioPostventa(string ...$permissions): User
{
    // Alcance TODAS: estas pruebas comprueban el MODULO, no el acotado por
    // sucursal (eso vive en AlcanceSucursalTest y AlcanceModulosTest). Sin
    // alcance la cuenta no veria ninguna fila y todo daria falso negativo.
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Programación automática (cierre del Proceso 3 del flujo)
|--------------------------------------------------------------------------
*/

test('a delivered pedido schedules its postventa follow up automatically', function () {
    $pedido = Pedido::factory()->create(['estado' => 'DISENO']);
    PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'estado_item' => 'ENTREGADO']);

    $pedido->recalcularEstado();

    $seguimiento = $pedido->fresh()->seguimientoPostventa;

    expect($seguimiento)->not->toBeNull()
        ->and($seguimiento->estado)->toBe('PENDIENTE')
        ->and($seguimiento->fecha_programada->toDateString())
        ->toBe($pedido->fresh()->fecha_entrega_real->copy()
            ->addDays(config('postventa.dias_seguimiento'))->toDateString());
});

test('a pedido still in production has no postventa follow up', function () {
    $pedido = Pedido::factory()->create(['estado' => 'DISENO']);
    PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'estado_item' => 'CONTROL_CALIDAD']);

    $pedido->recalcularEstado();

    expect($pedido->fresh()->estado)->toBe('CONTROL_CALIDAD')
        ->and($pedido->fresh()->seguimientoPostventa)->toBeNull();
});

test('scheduling twice does not duplicate the follow up', function () {
    $pedido = Pedido::factory()->create(['estado' => 'DISENO']);
    PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'estado_item' => 'ENTREGADO']);

    $pedido->recalcularEstado();
    $pedido->fresh()->recalcularEstado();

    expect(SeguimientoPostventa::query()->where('pedido_id', $pedido->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Bandeja de seguimientos
|--------------------------------------------------------------------------
*/

test('guests are redirected to login', function () {
    $this->get(route('seguimientos-postventa.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the follow ups', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('seguimientos-postventa.index'))
        ->assertForbidden();
});

test('the list shows pending follow ups by default with its summary', function () {
    SeguimientoPostventa::factory()->vencido()->create();
    SeguimientoPostventa::factory()->realizado()->create();

    $this->actingAs(usuarioPostventa('seguimientos-postventa.ver'))
        ->get(route('seguimientos-postventa.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SeguimientosPostventa/Index')
            ->has('seguimientos.data', 1)
            ->where('resumen.vencidos', 1)
            ->where('resumen.realizados', 1));
});

test('registering the contact stores who called and when', function () {
    $seguimiento = SeguimientoPostventa::factory()->vencido()->create();
    $user = usuarioPostventa('seguimientos-postventa.registrar');

    $this->actingAs($user)
        ->post(route('seguimientos-postventa.registrar', $seguimiento), [
            'estado' => 'REALIZADO',
            'medio' => 'LLAMADA',
            'satisfaccion' => 5,
            'requiere_accion' => 'NO',
            'observaciones' => 'Cliente conforme con la instalación.',
        ])->assertSessionHasNoErrors();

    $seguimiento->refresh();

    expect($seguimiento->estado)->toBe('REALIZADO')
        ->and($seguimiento->satisfaccion)->toBe(5)
        ->and($seguimiento->fecha_contacto->toDateString())->toBe(now()->toDateString());
});

test('a contacted follow up requires satisfaction and medio', function () {
    $seguimiento = SeguimientoPostventa::factory()->vencido()->create();

    $this->actingAs(usuarioPostventa('seguimientos-postventa.registrar'))
        ->post(route('seguimientos-postventa.registrar', $seguimiento), [
            'estado' => 'REALIZADO',
            'requiere_accion' => 'NO',
        ])->assertSessionHasErrors(['medio', 'satisfaccion']);
});

test('a failed contact attempt does not need satisfaction', function () {
    $seguimiento = SeguimientoPostventa::factory()->vencido()->create();

    $this->actingAs(usuarioPostventa('seguimientos-postventa.registrar'))
        ->post(route('seguimientos-postventa.registrar', $seguimiento), [
            'estado' => 'NO_CONTACTADO',
            'requiere_accion' => 'NO',
            'observaciones' => 'No atiende el teléfono.',
        ])->assertSessionHasNoErrors();

    expect($seguimiento->fresh()->estado)->toBe('NO_CONTACTADO');
});

test('an already registered follow up cannot be overwritten', function () {
    $seguimiento = SeguimientoPostventa::factory()->realizado()->create(['satisfaccion' => 5]);

    $this->actingAs(usuarioPostventa('seguimientos-postventa.registrar'))
        ->post(route('seguimientos-postventa.registrar', $seguimiento), [
            'estado' => 'REALIZADO',
            'medio' => 'LLAMADA',
            'satisfaccion' => 1,
            'requiere_accion' => 'SI',
        ])->assertSessionHas('error');

    expect($seguimiento->fresh()->satisfaccion)->toBe(5);
});
