<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
    Role::findOrCreate('vendedor', 'web');
    Role::findOrCreate('contador', 'web');
});

function userWithUsuario(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
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
        ->post(route('usuarios.store'), [
            'name' => 'Nueva Cuenta',
            'email' => 'nueva@xtrapubli.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'estado' => 'ACTIVO',
            'rol' => 'vendedor',
        ])
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
        ->put(route('usuarios.update', $target), [
            'name' => 'Cambiado',
            'email' => $target->email,
            'password' => '',
            'estado' => 'INACTIVO',
            'rol' => 'contador',
        ])
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
