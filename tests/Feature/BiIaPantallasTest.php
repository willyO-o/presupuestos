<?php

use App\Models\Area;
use App\Models\BiAnomalia;
use App\Models\CategoriaProducto;
use App\Models\Empleado;
use App\Models\FamiliaProducto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\FamiliaProductoSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Pantallas y acciones de la IA del BI (pestaña Resumen, anomalías en
 * Alertas) y la clasificación de categorías en familias.
 */
beforeEach(function () {
    config(['ai.providers.gemini.key' => null, 'ai.providers.anthropic.key' => null]);
    collect(['reportes.bi', 'reportes.asistente', 'categorias-producto.editar'])
        ->each(fn (string $p) => Permission::findOrCreate($p, 'web'));
});

function usuarioBi(string ...$permisos): User
{
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permisos);

    return $user;
}

test('la pestaña Resumen abre aunque todavía no haya resúmenes', function () {
    $this->actingAs(usuarioBi('reportes.bi'))
        ->get(route('reportes.bi.resumen'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reportes/BiResumen')
            ->where('datos.resumen', null)
            ->where('datos.puede_generar', false)
            ->where('datos.ia.disponible', false));
});

test('generar el resumen a mano pide el permiso del asistente', function () {
    $this->actingAs(usuarioBi('reportes.bi'))->post(route('reportes.bi.resumen.generar'))->assertForbidden();

    $this->actingAs(usuarioBi('reportes.bi', 'reportes.asistente'))
        ->post(route('reportes.bi.resumen.generar'))
        ->assertRedirect(route('reportes.bi.resumen'))
        ->assertSessionHas('success');
});

test('la pestaña Alertas trae las anomalías filtradas por estado', function () {
    BiAnomalia::factory()->create(['estado' => 'NUEVA', 'titulo' => 'Caída en La Paz']);
    BiAnomalia::factory()->create(['estado' => 'DESCARTADA']);

    $this->actingAs(usuarioBi('reportes.bi', 'reportes.asistente'))
        ->get(route('reportes.bi.alertas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reportes/BiAlertas')
            ->where('datos.anomalias.estado', 'NUEVA')
            ->has('datos.anomalias.items', 1)
            ->where('datos.anomalias.items.0.titulo', 'Caída en La Paz')
            ->where('datos.anomalias.puede_gestionar', true));
});

test('marcar una anomalía como revisada guarda quién y cuándo', function () {
    $usuario = usuarioBi('reportes.bi', 'reportes.asistente');
    $anomalia = BiAnomalia::factory()->create();

    $this->actingAs($usuario)
        ->patch(route('reportes.bi.anomalias.actualizar', $anomalia), ['estado' => 'REVISADA'])
        ->assertRedirect();

    expect($anomalia->fresh())
        ->estado->toBe('REVISADA')
        ->revisada_por->toBe($usuario->id)
        ->revisada_en->not->toBeNull();
});

test('no se puede marcar una anomalía de una sucursal que no se ve', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $local = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $local->givePermissionTo('reportes.asistente');
    Empleado::factory()->create(['user_id' => $local->id, 'sucursal_id' => $mia->id, 'area_id' => Area::factory()]);
    $local = $local->fresh();

    $deOtra = BiAnomalia::factory()->create(['sucursal_id' => $ajena->id]);
    $deTodaLaEmpresa = BiAnomalia::factory()->create(['sucursal_id' => null]);

    $this->actingAs($local)->patch(route('reportes.bi.anomalias.actualizar', $deOtra), ['estado' => 'DESCARTADA'])->assertForbidden();
    $this->actingAs($local)->patch(route('reportes.bi.anomalias.actualizar', $deTodaLaEmpresa), ['estado' => 'DESCARTADA'])->assertForbidden();

    expect($deOtra->fresh()->estado)->toBe('NUEVA');
});

test('correr la detección a mano pide el permiso del asistente', function () {
    $this->actingAs(usuarioBi('reportes.bi'))->post(route('reportes.bi.anomalias.detectar'))->assertForbidden();

    $this->actingAs(usuarioBi('reportes.bi', 'reportes.asistente'))
        ->post(route('reportes.bi.anomalias.detectar'))
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('el seeder de familias clasifica por palabras clave sin pisar lo ya clasificado', function () {
    $letras = CategoriaProducto::factory()->create(['nombre' => 'Letras corpóreas']);
    $ploteo = CategoriaProducto::factory()->create(['nombre' => 'Ploteo vehicular']);
    $rara = CategoriaProducto::factory()->create(['nombre' => 'Servicios varios']);

    $manual = FamiliaProducto::factory()->create(['nombre' => 'Clasificación manual']);
    $yaClasificada = CategoriaProducto::factory()->create(['nombre' => 'Banners especiales', 'familia_producto_id' => $manual->id]);

    $this->seed(FamiliaProductoSeeder::class);

    expect($letras->fresh()->familia->nombre)->toBe('Letreros y letras 3D')
        ->and($ploteo->fresh()->familia->nombre)->toBe('Rotulado y vinilos')
        ->and($rara->fresh()->familia_producto_id)->toBeNull()
        ->and($yaClasificada->fresh()->familia_producto_id)->toBe($manual->id);
});

test('una categoría se puede asignar a una familia desde su CRUD', function () {
    $familia = FamiliaProducto::factory()->create();
    $categoria = CategoriaProducto::factory()->create();

    $this->actingAs(usuarioBi('categorias-producto.editar'))
        ->put(route('categorias-producto.update', $categoria), [
            'nombre' => $categoria->nombre,
            'familia_producto_id' => $familia->id,
            'estado' => 'ACTIVO',
        ])
        ->assertRedirect();

    expect($categoria->fresh()->familia_producto_id)->toBe($familia->id);
});
