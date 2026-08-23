<?php

use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['empleados.ver', 'empleados.crear', 'empleados.editar', 'empleados.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithEmpleadoPermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('empleados.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('empleados.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Empleado::factory()->count(3)->create();
    $user = userWithEmpleadoPermissions('empleados.ver');

    $response = $this->actingAs($user)->get(route('empleados.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Empleados/Index')
        ->has('empleados.data', 3)
    );
});

test('the list can be searched by nombres, apellidos, ci or cargo', function () {
    Empleado::factory()->create(['nombres' => 'Juan', 'paterno' => 'Pérez', 'materno' => 'Gómez', 'ci' => '1234567']);
    Empleado::factory()->create(['nombres' => 'Ana', 'paterno' => 'López', 'materno' => 'Ruiz', 'ci' => '7654321']);
    $user = userWithEmpleadoPermissions('empleados.ver');

    $response = $this->actingAs($user)->get(route('empleados.index', ['search' => 'Juan']));

    $response->assertInertia(fn ($page) => $page
        ->has('empleados.data', 1)
        ->where('empleados.data.0.nombre_completo', 'Juan Pérez Gómez')
    );
});

test('the list can be filtered by sucursal and area', function () {
    $sucursal = Sucursal::factory()->create();
    $area = Area::factory()->create();
    Empleado::factory()->create(['sucursal_id' => $sucursal->id, 'area_id' => $area->id]);
    Empleado::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver');

    $response = $this->actingAs($user)->get(route('empleados.index', ['sucursal' => $sucursal->id, 'area' => $area->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('empleados.data', 1)
        ->where('empleados.data.0.sucursal_id', $sucursal->id)
    );
});

test('the list can be filtered by estado', function () {
    Empleado::factory()->create(['estado' => 'ACTIVO']);
    Empleado::factory()->inactivo()->create();
    $user = userWithEmpleadoPermissions('empleados.ver');

    $response = $this->actingAs($user)->get(route('empleados.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('empleados.data', 1)
        ->where('empleados.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create an empleado', function () {
    $sucursal = Sucursal::factory()->create();
    $area = Area::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.crear');

    $response = $this->actingAs($user)->post(route('empleados.store'), [
        'sucursal_id' => $sucursal->id,
        'area_id' => $area->id,
        'nombres' => 'Juan',
        'paterno' => 'Pérez',
        'materno' => 'Gómez',
        'ci' => '1234567',
        'cargo' => 'Diseñador Gráfico',
        'telefono' => '71234567',
        'fecha_ingreso' => '2026-01-15',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('empleados.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('empleado', ['nombres' => 'Juan', 'paterno' => 'Pérez', 'ci' => '1234567']);
});

test('an empleado can be created without paterno or materno', function () {
    $sucursal = Sucursal::factory()->create();
    $area = Area::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.crear');

    $response = $this->actingAs($user)->post(route('empleados.store'), [
        'sucursal_id' => $sucursal->id,
        'area_id' => $area->id,
        'nombres' => 'Juan',
        'paterno' => '',
        'materno' => '',
        'ci' => '1234567',
        'cargo' => 'Diseñador Gráfico',
        'fecha_ingreso' => '2026-01-15',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('empleados.index'));
    $this->assertDatabaseHas('empleado', ['nombres' => 'Juan', 'paterno' => null, 'materno' => null]);
});

test('a user without permission cannot create an empleado', function () {
    $sucursal = Sucursal::factory()->create();
    $area = Area::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver');

    $this->actingAs($user)->post(route('empleados.store'), [
        'sucursal_id' => $sucursal->id,
        'area_id' => $area->id,
        'nombres' => 'Juan',
        'paterno' => 'Pérez',
        'ci' => '1234567',
        'cargo' => 'Diseñador Gráfico',
        'fecha_ingreso' => '2026-01-15',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('empleado', 0);
});

test('creating an empleado requires ci to be unique', function () {
    Empleado::factory()->create(['ci' => '1234567']);
    $sucursal = Sucursal::factory()->create();
    $area = Area::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.crear');

    $response = $this->actingAs($user)->post(route('empleados.store'), [
        'sucursal_id' => $sucursal->id,
        'area_id' => $area->id,
        'nombres' => 'Otro',
        'paterno' => 'Empleado',
        'ci' => '1234567',
        'cargo' => 'Operario',
        'fecha_ingreso' => '2026-01-15',
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['ci']);
    $this->assertDatabaseCount('empleado', 1);
});

test('creating an empleado requires valid sucursal, area, nombres, ci and fecha_ingreso', function () {
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.crear');

    $response = $this->actingAs($user)->post(route('empleados.store'), [
        'sucursal_id' => 999,
        'area_id' => 999,
        'nombres' => '',
        'ci' => '',
        'cargo' => '',
        'fecha_ingreso' => '',
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['sucursal_id', 'area_id', 'nombres', 'ci', 'cargo', 'fecha_ingreso']);
    $this->assertDatabaseCount('empleado', 0);
});

test('a user with permission can update an empleado', function () {
    $empleado = Empleado::factory()->create(['nombres' => 'Original']);
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.editar');

    $response = $this->actingAs($user)->put(route('empleados.update', $empleado), [
        'sucursal_id' => $empleado->sucursal_id,
        'area_id' => $empleado->area_id,
        'nombres' => 'Actualizado',
        'paterno' => $empleado->paterno,
        'materno' => $empleado->materno,
        'ci' => $empleado->ci,
        'cargo' => $empleado->cargo,
        'fecha_ingreso' => $empleado->fecha_ingreso->format('Y-m-d'),
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('empleados.index'));
    $this->assertDatabaseHas('empleado', [
        'id' => $empleado->id,
        'nombres' => 'Actualizado',
        'estado' => 'INACTIVO',
    ]);
});

test('a user without permission cannot update an empleado', function () {
    $empleado = Empleado::factory()->create(['nombres' => 'Original']);
    $user = userWithEmpleadoPermissions('empleados.ver');

    $this->actingAs($user)->put(route('empleados.update', $empleado), [
        'sucursal_id' => $empleado->sucursal_id,
        'area_id' => $empleado->area_id,
        'nombres' => 'Actualizado',
        'ci' => $empleado->ci,
        'cargo' => $empleado->cargo,
        'fecha_ingreso' => $empleado->fecha_ingreso->format('Y-m-d'),
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('empleado', ['id' => $empleado->id, 'nombres' => 'Original']);
});

test('a user with permission can delete an empleado', function () {
    $empleado = Empleado::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver', 'empleados.eliminar');

    $response = $this->actingAs($user)->delete(route('empleados.destroy', $empleado));

    $response->assertRedirect(route('empleados.index'));
    $this->assertDatabaseMissing('empleado', ['id' => $empleado->id]);
});

test('a user without permission cannot delete an empleado', function () {
    $empleado = Empleado::factory()->create();
    $user = userWithEmpleadoPermissions('empleados.ver');

    $this->actingAs($user)->delete(route('empleados.destroy', $empleado))
        ->assertForbidden();

    $this->assertDatabaseHas('empleado', ['id' => $empleado->id]);
});

test('super-admin bypasses individual permissions', function () {
    Empleado::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('empleados.index'))
        ->assertOk();
});
