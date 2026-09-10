<?php

use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Smoke del panel: ninguna pantalla GET puede reventar (500) para un usuario
 * ACOTADO a una sucursal, aunque no tenga datos que ver.
 *
 * Existe por el alcance por sucursal: acotar toca CADA listado, y una consulta
 * mal enchufada revienta una pantalla que las pruebas del módulo no miran.
 * Recorre las rutas GET sin parámetros que haya en ese momento, así que cubre
 * también los módulos que se agreguen después. Cuesta ~1,5 s.
 *
 * El segundo aserto es contra el falso verde: si los permisos dejaran de
 * aplicarse, todo respondería 403 y "ninguna revienta" sería cierto pero
 * inútil.
 */
test('ninguna pantalla del panel revienta para un usuario acotado', function () {
    $sucursal = Sucursal::factory()->create();

    // Todos los permisos declarados, para llegar a cada pantalla.
    $permisos = collect(config('acl.modules'))
        ->flatMap(fn (array $m) => array_keys($m['permissions']))
        ->each(fn (string $p) => Permission::findOrCreate($p, 'web'));

    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->givePermissionTo($permisos->all());
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);
    $user = $user->fresh();

    $rutas = collect(Route::getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true))
        ->filter(fn ($r) => ! str_contains($r->uri(), '{'))       // sin parámetros
        ->filter(fn ($r) => ! str_starts_with($r->uri(), 'portal')) // el portal es del rol cliente
        ->map(fn ($r) => $r->uri())
        ->unique()
        ->values();

    $rotas = [];
    $codigos = [];

    foreach ($rutas as $uri) {
        $codigo = $this->actingAs($user)->get("/{$uri}")->getStatusCode();
        $codigos[$codigo] = ($codigos[$codigo] ?? 0) + 1;

        if ($codigo >= 500) {
            $rotas[] = "/{$uri} -> {$codigo}";
        }
    }

    expect($rotas)->toBe([], 'Pantallas que revientan: '.implode(', ', $rotas));
    // Que no pase vacíamente: la mayoría tiene que responder 200, no 403.
    expect($codigos[200] ?? 0)->toBeGreaterThan(15);
});
