<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(['reportes.financiero', 'reportes.produccion', 'reportes.bi'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithReporte(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('the dashboard renders with the resumen payload for any authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('resumen.pedidos_por_etapa')
            ->has('resumen.ventas_por_mes'));
});

test('each reporte requires its own permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reportes.financiero'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('reportes.produccion'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('reportes.bi'))->assertForbidden();

    $this->actingAs(userWithReporte('reportes.financiero'))->get(route('reportes.financiero'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Reportes/Financiero')->has('datos.rentabilidad'));
    $this->actingAs(userWithReporte('reportes.produccion'))->get(route('reportes.produccion'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Reportes/Produccion')->has('datos.cumplimiento'));
    $this->actingAs(userWithReporte('reportes.bi'))->get(route('reportes.bi'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Reportes/Bi')->has('datos.demanda.proyeccion'));
});

test('super-admin can open every reporte', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('reportes.financiero'))->assertOk();
    $this->actingAs($user)->get(route('reportes.bi'))->assertOk();
});
