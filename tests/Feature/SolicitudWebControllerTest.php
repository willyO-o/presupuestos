<?php

use App\Models\Cotizacion;
use App\Models\CotizacionPublica;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Bandeja de las solicitudes que llegan por el cotizador público.
 *
 * Es el otro extremo de CotizadorPublicoTest: allá se comprueba que un
 * desconocido pueda pedir un estimado sin romper nada; acá, que ese estimado
 * llegue a alguien de la empresa y que sus datos de contacto no queden a la
 * vista de cualquier usuario del panel.
 */
beforeEach(function () {
    collect(['solicitudes-web.ver', 'solicitudes-web.gestionar'])
        ->each(fn (string $permiso) => Permission::findOrCreate($permiso, 'web'));
});

function usuarioConPermisosSolicitudWeb(string ...$permisos): User
{
    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('un invitado no ve las solicitudes', function () {
    $this->get(route('solicitudes-web.index'))->assertRedirect(route('login'));
});

test('un usuario sin permiso no ve las solicitudes', function () {
    // Llevan nombre, teléfono y correo de personas que escribieron al sitio:
    // no es información para cualquier cuenta del panel.
    $this->actingAs(User::factory()->create())
        ->get(route('solicitudes-web.index'))
        ->assertForbidden();
});

test('un usuario con permiso ve la bandeja con su resumen', function () {
    CotizacionPublica::factory()->count(2)->create();
    CotizacionPublica::factory()->contactada()->create();
    CotizacionPublica::factory()->vencida()->create();

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver'))
        ->get(route('solicitudes-web.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SolicitudesWeb/Index')
            ->has('solicitudes.data', 4)
            // La vencida sigue siendo NUEVA, por eso cuenta en las dos.
            ->where('resumen.nuevas', 3)
            ->where('resumen.vencidas', 1)
            ->where('resumen.contactadas', 1)
        );
});

test('se puede buscar una solicitud por su codigo', function () {
    // Es el caso de uso real: el cliente llama dictando su código WEB-…
    $buscada = CotizacionPublica::factory()->create();
    CotizacionPublica::factory()->count(3)->create();

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver'))
        ->get(route('solicitudes-web.index', ['search' => $buscada->codigo]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('solicitudes.data', 1)
            ->where('solicitudes.data.0.codigo', $buscada->codigo)
        );
});

test('quien solo puede ver no puede cambiar el estado', function () {
    $solicitud = CotizacionPublica::factory()->create();

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver'))
        ->put(route('solicitudes-web.estado', $solicitud), ['estado' => 'CONTACTADA'])
        ->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe('NUEVA');
});

test('se puede marcar una solicitud como contactada', function () {
    $solicitud = CotizacionPublica::factory()->create();

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver', 'solicitudes-web.gestionar'))
        ->put(route('solicitudes-web.estado', $solicitud), ['estado' => 'CONTACTADA'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($solicitud->fresh()->estado)->toBe('CONTACTADA');
});

test('CONVERTIDA no se puede poner a mano', function () {
    // Ese estado lo escribe quien emite el presupuesto formal, junto con
    // `cotizacion_id`. Permitirlo acá dejaría solicitudes "convertidas" sin
    // ninguna cotización detrás.
    $solicitud = CotizacionPublica::factory()->create();

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver', 'solicitudes-web.gestionar'))
        ->put(route('solicitudes-web.estado', $solicitud), ['estado' => 'CONVERTIDA'])
        ->assertSessionHasErrors('estado');

    expect($solicitud->fresh()->estado)->toBe('NUEVA');
});

test('una solicitud ya convertida no cambia de estado', function () {
    $solicitud = CotizacionPublica::factory()->create([
        'estado' => 'CONVERTIDA',
        'cotizacion_id' => Cotizacion::factory()->create()->id,
    ]);

    $this->actingAs(usuarioConPermisosSolicitudWeb('solicitudes-web.ver', 'solicitudes-web.gestionar'))
        ->put(route('solicitudes-web.estado', $solicitud), ['estado' => 'DESCARTADA'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($solicitud->fresh()->estado)->toBe('CONVERTIDA');
});
