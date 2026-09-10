<?php

use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
    Role::findOrCreate('vendedor', 'web');
    Role::findOrCreate('contador', 'web');
});

/**
 * Administrador de usuarios, con alcance TODAS.
 *
 * No es solo para que vea filas: nadie puede REPARTIR más alcance del que
 * tiene, así que una cuenta sin alcance no podría asignar ninguna sucursal ni
 * conceder TODAS. Ese caso se prueba aparte, en el bloque de escalada.
 */
function userWithUsuario(string ...$permissions): User
{
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * Payload válido del formulario de usuario. `alcance_sucursal` es obligatorio
 * desde que existe el alcance por sucursal (ver AlcanceSucursalTest): no tiene
 * default en el Form Request a propósito, para que ningún formulario nuevo se
 * olvide de decidirlo.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function datosUsuario(array $extra = []): array
{
    return [
        'name' => 'Nueva Cuenta',
        'email' => 'nueva@xtrapubli.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'estado' => 'ACTIVO',
        'rol' => 'vendedor',
        'alcance_sucursal' => 'PROPIA',
        ...$extra,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('usuarios.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('usuarios.index'))
        ->assertForbidden();
});

test('a user with permission sees the list', function () {
    $this->actingAs(userWithUsuario('usuarios.ver'))
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Usuarios/Index')->has('roles'));
});

test('creating a user assigns exactly one role and hashes the password', function () {
    $this->actingAs(userWithUsuario('usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario())
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('email', 'nueva@xtrapubli.test')->first();
    expect($user->getRoleNames()->all())->toBe(['vendedor'])
        ->and($user->password)->not->toBe('password123');
});

test('updating a user without a password keeps the current one and can change the role', function () {
    $target = User::factory()->create();
    $target->assignRole('vendedor');
    $originalPassword = $target->password;

    $this->actingAs(userWithUsuario('usuarios.editar'))
        ->put(route('usuarios.update', $target), datosUsuario([
            'name' => 'Cambiado',
            'email' => $target->email,
            'password' => '',
            'estado' => 'INACTIVO',
            'rol' => 'contador',
        ]))
        ->assertRedirect(route('usuarios.index'));

    $target->refresh();
    expect($target->name)->toBe('Cambiado')
        ->and($target->estado)->toBe('INACTIVO')
        ->and($target->password)->toBe($originalPassword)
        ->and($target->getRoleNames()->all())->toBe(['contador']);
});

test('a user cannot delete their own account', function () {
    $user = userWithUsuario('usuarios.eliminar');

    $this->actingAs($user)->delete(route('usuarios.destroy', $user))->assertSessionHas('error');

    $this->assertModelExists($user);
});

test('the last super-admin cannot be deleted', function () {
    Role::findOrCreate('super-admin', 'web');
    $super = User::factory()->create();
    $super->assignRole('super-admin');
    $actor = userWithUsuario('usuarios.eliminar');

    $this->actingAs($actor)->delete(route('usuarios.destroy', $super))->assertSessionHas('error');

    $this->assertModelExists($super);
});

test('an inactive account cannot log in', function () {
    $user = User::factory()->inactivo()->create(['password' => bcrypt('secret123')]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Alcance por sucursal (se configura desde esta pantalla)
|--------------------------------------------------------------------------
|
| La regla de qué ve cada alcance vive en AlcanceSucursalTest. Acá se prueba
| solo que el formulario la GUARDE bien, que es donde se puede colar un
| permiso de más.
|
*/

test('el listado manda el catalogo de sucursales y los roles globales', function () {
    Sucursal::factory()->count(2)->create();

    $this->actingAs(userWithUsuario('usuarios.ver'))
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Usuarios/Index')
            ->has('sucursales', 2)
            ->has('alcances', 3)
            ->where('rolesGlobales', ['super-admin', 'administrador']));
});

test('se puede crear una cuenta con varias sucursales asignadas', function () {
    // El caso que motivó todo esto: el contador regional.
    $sucursales = Sucursal::factory()->count(2)->create();

    $this->actingAs(userWithUsuario('usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario([
            'alcance_sucursal' => 'ASIGNADAS',
            'sucursales' => $sucursales->pluck('id')->all(),
        ]))
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('email', 'nueva@xtrapubli.test')->first();

    expect($user->alcance_sucursal)->toBe('ASIGNADAS')
        ->and($user->sucursalesVisibles())->toEqualCanonicalizing($sucursales->pluck('id')->all());
});

test('ASIGNADAS sin marcar ninguna sucursal se rechaza', function () {
    // Guardarlo dejaría una cuenta que no ve absolutamente nada, sin que quien
    // la creó se entere. Mejor un error de validación.
    $this->actingAs(userWithUsuario('usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario(['alcance_sucursal' => 'ASIGNADAS']))
        ->assertSessionHasErrors('sucursales');

    expect(User::where('email', 'nueva@xtrapubli.test')->exists())->toBeFalse();
});

test('no se puede asignar una sucursal inexistente', function () {
    $this->actingAs(userWithUsuario('usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario([
            'alcance_sucursal' => 'ASIGNADAS',
            'sucursales' => [99999],
        ]))
        ->assertSessionHasErrors('sucursales.0');
});

test('un alcance inventado se rechaza', function () {
    $this->actingAs(userWithUsuario('usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario(['alcance_sucursal' => 'TODAS_MENOS_UNA']))
        ->assertSessionHasErrors('alcance_sucursal');
});

test('cambiar el alcance a TODAS vacia las sucursales asignadas', function () {
    // Una fila huérfana en el pivote no hace nada hoy, pero le devolvería esas
    // sucursales de golpe si mañana alguien vuelve a poner ASIGNADAS.
    $sucursal = Sucursal::factory()->create();
    $target = User::factory()->sucursalesAsignadas()->create();
    $target->assignRole('vendedor');
    $target->sucursales()->sync([$sucursal->id]);

    $this->actingAs(userWithUsuario('usuarios.editar'))
        ->put(route('usuarios.update', $target), datosUsuario([
            'email' => $target->email,
            'password' => '',
            'alcance_sucursal' => 'TODAS',
            // Se mandan igual: el controlador debe ignorarlas, no guardarlas.
            'sucursales' => [$sucursal->id],
        ]))
        ->assertRedirect(route('usuarios.index'));

    $target->refresh();

    expect($target->alcance_sucursal)->toBe('TODAS')
        ->and($target->sucursales()->count())->toBe(0);
});

test('las props compartidas dicen el alcance del usuario logueado', function () {
    // Sirve para que la pantalla ROTULE lo que muestra ("Viendo: El Alto"); el
    // filtrado de verdad pasa en el servidor.
    $sucursal = Sucursal::factory()->create(['nombre' => 'El Alto']);
    // `jefeDeSucursal` y no `userWithUsuario`: este último da alcance TODAS y
    // el aserto pasaría por el motivo equivocado.
    $acotado = jefeDeSucursal($sucursal, 'usuarios.ver');

    $this->actingAs($acotado)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.sucursales.ve_todas', false)
            ->where('auth.sucursales.visibles.0.nombre', 'El Alto'));
});

/*
|--------------------------------------------------------------------------
| Escalada de privilegios: nadie reparte más alcance del que tiene
|--------------------------------------------------------------------------
|
| Hoy solo `administrador` (global) tiene `usuarios.*`, así que esto no es
| explotable tal cual está el ACL. Pero los roles se editan desde la propia
| UI: en cuanto alguien arme un "supervisor" con `usuarios.editar`, sin estas
| reglas se daría acceso a toda la empresa creando una cuenta con TODAS.
|
*/

/** Administrador de usuarios acotado a UNA sucursal. */
function jefeDeSucursal(Sucursal $sucursal, string ...$permisos): User
{
    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->givePermissionTo($permisos);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);

    return $user->fresh();
}

test('quien no ve todas las sucursales no puede conceder el alcance TODAS', function () {
    $mia = Sucursal::factory()->create();

    $this->actingAs(jefeDeSucursal($mia, 'usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario(['alcance_sucursal' => 'TODAS']))
        ->assertSessionHasErrors('alcance_sucursal');

    expect(User::where('email', 'nueva@xtrapubli.test')->exists())->toBeFalse();
});

test('tampoco puede asignar una sucursal que no administra', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $this->actingAs(jefeDeSucursal($mia, 'usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario([
            'alcance_sucursal' => 'ASIGNADAS',
            'sucursales' => [$ajena->id],
        ]))
        ->assertSessionHasErrors('sucursales.0');
});

test('si puede repartir la sucursal que si administra', function () {
    // La regla acota, no bloquea: el jefe de sucursal sigue pudiendo dar de
    // alta a su propia gente.
    $mia = Sucursal::factory()->create();

    $this->actingAs(jefeDeSucursal($mia, 'usuarios.crear'))
        ->post(route('usuarios.store'), datosUsuario([
            'alcance_sucursal' => 'ASIGNADAS',
            'sucursales' => [$mia->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'nueva@xtrapubli.test')->first()->sucursalesVisibles())
        ->toBe([$mia->id]);
});

test('el desplegable de sucursales tampoco ofrece las ajenas', function () {
    // Validar y no filtrar el desplegable sería ofrecer algo que después se
    // rechaza al guardar.
    $mia = Sucursal::factory()->create();
    Sucursal::factory()->count(3)->create();

    $this->actingAs(jefeDeSucursal($mia, 'usuarios.ver'))
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sucursales', 1));
});

test('quien ve todas no arrastra la lista de sucursales en cada request', function () {
    // `visibles` es null a propósito para no consultar la tabla en el caso más
    // común (super-admin/administrador).
    $global = User::factory()->todasLasSucursales()->create();
    $global->givePermissionTo('usuarios.ver');

    $this->actingAs($global)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.sucursales.ve_todas', true)
            ->where('auth.sucursales.visibles', null));
});
