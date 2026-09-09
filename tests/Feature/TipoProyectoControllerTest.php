<?php

use App\Models\CotizacionDetalle;
use App\Models\TipoProyecto;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'tipos-proyecto.ver', 'tipos-proyecto.crear',
        'tipos-proyecto.editar', 'tipos-proyecto.eliminar',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function usuarioCon(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function tipoValido(array $overrides = []): array
{
    return [
        'nombre' => 'Complejo',
        'descripcion' => 'Varias áreas involucradas.',
        'factor_complejidad' => 1.5,
        // El formulario manda PORCENTAJE; la tabla guarda la fracción.
        'margen_minimo' => 60,
        'orden' => 3,
        'estado' => 'ACTIVO',
        ...$overrides,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('tipos-proyecto.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tipos-proyecto.index'))
        ->assertForbidden();
});

test('a user with permission sees the list ordered by orden', function () {
    TipoProyecto::factory()->create(['nombre' => 'Crítico', 'orden' => 4]);
    TipoProyecto::factory()->create(['nombre' => 'Básico', 'orden' => 1]);

    $this->actingAs(usuarioCon('tipos-proyecto.ver'))
        ->get(route('tipos-proyecto.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TiposProyecto/Index')
            ->has('tiposProyecto.data', 2)
            ->where('tiposProyecto.data.0.nombre', 'Básico'));
});

test('creating a tipo stores the margin as a fraction', function () {
    $this->actingAs(usuarioCon('tipos-proyecto.crear'))
        ->post(route('tipos-proyecto.store'), tipoValido())
        ->assertRedirect(route('tipos-proyecto.index'));

    $this->assertDatabaseHas('tipo_proyecto', [
        'nombre' => 'Complejo',
        'factor_complejidad' => 1.5,
        'margen_minimo' => 0.6,
    ]);
});

test('creating a tipo requires the crear permission', function () {
    $this->actingAs(usuarioCon('tipos-proyecto.ver'))
        ->post(route('tipos-proyecto.store'), tipoValido())
        ->assertForbidden();
});

test('the nombre must be unique', function () {
    TipoProyecto::factory()->create(['nombre' => 'Complejo']);

    $this->actingAs(usuarioCon('tipos-proyecto.crear'))
        ->post(route('tipos-proyecto.store'), tipoValido())
        ->assertSessionHasErrors('nombre');
});

test('the factor must be positive', function () {
    $this->actingAs(usuarioCon('tipos-proyecto.crear'))
        ->post(route('tipos-proyecto.store'), tipoValido(['factor_complejidad' => 0]))
        ->assertSessionHasErrors('factor_complejidad');
});

test('updating a tipo keeps its own nombre valid', function () {
    $tipo = TipoProyecto::factory()->create(['nombre' => 'Medio']);

    $this->actingAs(usuarioCon('tipos-proyecto.editar'))
        ->put(route('tipos-proyecto.update', $tipo), tipoValido(['nombre' => 'Medio', 'margen_minimo' => 55]))
        ->assertRedirect(route('tipos-proyecto.index'));

    expect((float) $tipo->fresh()->margen_minimo)->toBe(0.55);
});

test('a tipo without history can be deleted', function () {
    $tipo = TipoProyecto::factory()->create();

    $this->actingAs(usuarioCon('tipos-proyecto.eliminar'))
        ->delete(route('tipos-proyecto.destroy', $tipo))
        ->assertSessionHas('success');

    $this->assertModelMissing($tipo);
});

test('a tipo already used in a cotizacion cannot be deleted', function () {
    $tipo = TipoProyecto::factory()->create();
    CotizacionDetalle::factory()->create(['tipo_proyecto_id' => $tipo->id]);

    $this->actingAs(usuarioCon('tipos-proyecto.eliminar'))
        ->delete(route('tipos-proyecto.destroy', $tipo))
        ->assertSessionHas('error');

    $this->assertModelExists($tipo);
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('tipos-proyecto.index'))->assertOk();
});
