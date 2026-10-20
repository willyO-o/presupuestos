<?php

use App\Models\Cotizacion;
use App\Models\Pedido;
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
            ->has('resumen.ventas_por_mes', 12)
            ->has('resumen.pedidos_recientes')
            ->has('resumen.productos_mas_vendidos'));
});

test('the dashboard greets the user by first name', function () {
    $this->actingAs(User::factory()->create(['name' => 'William Chambi']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('pageTitle', '¡Hola, William!')
            ->has('pageSubtitle'));
});

test('the dashboard compares the month against the same cut of the previous month', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-05 12:00'));

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));

    Cotizacion::factory()->create(['estado' => 'APROBADA', 'fecha' => '2026-10-02', 'total' => 500]);
    Cotizacion::factory()->create(['estado' => 'APROBADA', 'fecha' => '2026-09-03', 'total' => 300]);
    // Pasado el corte (5 de septiembre): no entra en la comparación.
    Cotizacion::factory()->create(['estado' => 'APROBADA', 'fecha' => '2026-09-20', 'total' => 9000]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.ventas_mes', fn ($v) => (float) $v === 500.0)
            ->where('resumen.ventas_mes_anterior', fn ($v) => (float) $v === 300.0)
            ->where('resumen.cotizaciones_mes_anterior', 1)
            ->where('resumen.ventas_por_mes.8.total', fn ($v) => (float) $v === 9300.0)
            ->where('resumen.ventas_por_mes.11.total', null));
});

test('the dashboard lists the latest pedidos with their cliente', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));

    Pedido::factory()->count(7)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('resumen.pedidos_recientes', 5)
            ->has('resumen.pedidos_recientes.0', fn ($pedido) => $pedido
                ->hasAll(['id', 'numero_pedido', 'cliente', 'fecha', 'total', 'estado'])));
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
