<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'roles.ver', 'roles.crear', 'roles.editar', 'roles.eliminar',
        'cotizaciones.ver', 'cotizaciones.crear', 'pedidos.ver',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
    Role::findOrCreate('super-admin', 'web');
});

function userWithRol(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('roles.index'))
        ->assertForbidden();
});

test('the list shows roles with permission and user counts', function () {
    Role::findOrCreate('vendedor', 'web')->givePermissionTo('cotizaciones.ver');

    $this->actingAs(userWithRol('roles.ver'))
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Roles/Index')->has('roles'));
});

test('creating a role syncs the selected permissions', function () {
    $this->actingAs(userWithRol('roles.crear'))
        ->post(route('roles.store'), [
            'name' => 'jefe-taller',
            'permissions' => ['pedidos.ver', 'cotizaciones.ver'],
        ])
        ->assertRedirect(route('roles.index'));

    $rol = Role::findByName('jefe-taller', 'web');
    expect($rol->permissions->pluck('name')->sort()->values()->all())->toBe(['cotizaciones.ver', 'pedidos.ver']);
});

test('a role name must be kebab-case', function () {
    $this->actingAs(userWithRol('roles.crear'))
        ->post(route('roles.store'), ['name' => 'Jefe Taller', 'permissions' => []])
        ->assertSessionHasErrors('name');
});

test('updating a role replaces its permission set', function () {
    $rol = Role::findOrCreate('vendedor', 'web');
    $rol->givePermissionTo(['cotizaciones.ver', 'cotizaciones.crear']);

    $this->actingAs(userWithRol('roles.editar'))
        ->put(route('roles.update', $rol), ['name' => 'vendedor', 'permissions' => ['pedidos.ver']])
        ->assertRedirect(route('roles.index'));

    expect($rol->fresh()->permissions->pluck('name')->all())->toBe(['pedidos.ver']);
});

test('the super-admin role cannot be edited or deleted from the UI', function () {
    $super = Role::findByName('super-admin', 'web');

    $this->actingAs(userWithRol('roles.editar'))
        ->get(route('roles.edit', $super))
        ->assertRedirect(route('roles.index'));

    $this->actingAs(userWithRol('roles.editar'))
        ->put(route('roles.update', $super), ['name' => 'super-admin', 'permissions' => []])
        ->assertForbidden();

    $this->actingAs(userWithRol('roles.eliminar'))
        ->delete(route('roles.destroy', $super))
        ->assertSessionHas('error');
});

test('a role with users assigned cannot be deleted', function () {
    $rol = Role::findOrCreate('vendedor', 'web');
    User::factory()->create()->assignRole($rol);

    $this->actingAs(userWithRol('roles.eliminar'))
        ->delete(route('roles.destroy', $rol))
        ->assertSessionHas('error');

    expect(Role::where('name', 'vendedor')->exists())->toBeTrue();
});

test('super-admin bypasses individual permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('roles.index'))->assertOk();
});
