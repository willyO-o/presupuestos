<?php

use App\Models\Area;
use App\Models\CategoriaMaterial;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Material;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Limpieza de redundancias del esquema (2026-09-09). Cada test fija una de
 * las decisiones tomadas, para que no se reintroduzcan por costumbre.
 */
beforeEach(function () {
    collect(['empleados.crear', 'materiales.crear', 'productos.crear', 'pagos.ver'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function usuarioConPermiso(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/*
|--------------------------------------------------------------------------
| cotizacion.recomendacion: derivada, no almacenada
|--------------------------------------------------------------------------
*/

test('la recomendacion se deriva del semaforo y no es una columna', function () {
    expect(Schema::hasColumn('cotizacion', 'recomendacion'))->toBeFalse();

    $cotizacion = Cotizacion::factory()->create(['estado_margen' => 'AMARILLO']);

    expect($cotizacion->recomendacion)->toBe('REVISAR PRECIO')
        // Y viaja al frontend como un campo más (está en $appends).
        ->and($cotizacion->toArray())->toHaveKey('recomendacion');

    $cotizacion->update(['estado_margen' => 'ROJO']);

    expect($cotizacion->fresh()->recomendacion)->toBe('NO ACEPTAR');
});

test('la columna de impuesto de la cotizacion se llama iva', function () {
    expect(Schema::hasColumn('cotizacion', 'iva'))->toBeTrue()
        ->and(Schema::hasColumn('cotizacion', 'impuesto'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Vocabulario único de unidades de medida
|--------------------------------------------------------------------------
*/

test('material y producto comparten el vocabulario de unidades', function () {
    // Producto es un subconjunto de la lista canónica de Material.
    expect(array_diff(Producto::UNIDADES_MEDIDA, Material::UNIDADES_MEDIDA))->toBe([])
        ->and(Material::UNIDADES_MEDIDA)->toContain('METRO_LINEAL')
        ->and(Material::UNIDADES_MEDIDA)->not->toContain('METRO');
});

test('un material ya no acepta la unidad METRO', function () {
    $this->actingAs(usuarioConPermiso('materiales.crear'))
        ->post(route('materiales.store'), [
            'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
            'nombre' => 'Tubo cuadrado',
            'presentacion' => 'Barra 6m',
            'unidad_medida' => 'METRO',
            'precio_presentacion' => 66,
            'precio_unitario' => 11,
            'stock_actual' => 0,
            'stock_minimo' => 0,
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasErrors('unidad_medida');
});

/*
|--------------------------------------------------------------------------
| empleado.cargo: los roles del ACL, no texto libre
|--------------------------------------------------------------------------
*/

test('los cargos salen de los roles del negocio definidos en el ACL', function () {
    $cargos = Empleado::cargos();

    expect($cargos)->toContain('Vendedor')
        ->and($cargos)->not->toContain('Super administrador')
        ->and($cargos)->not->toContain('Cliente');
});

test('un cargo inventado se rechaza', function () {
    $this->actingAs(usuarioConPermiso('empleados.crear'))
        ->post(route('empleados.store'), [
            'sucursal_id' => Sucursal::factory()->create()->id,
            'area_id' => Area::factory()->create()->id,
            'nombres' => 'Ana',
            'paterno' => 'Quispe',
            'ci' => '9998887',
            'cargo' => 'Community Manager',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasErrors('cargo');
});

/*
|--------------------------------------------------------------------------
| pago.estado: el estado de cobranza es del pedido
|--------------------------------------------------------------------------
*/

test('el pago ya no guarda un estado propio', function () {
    expect(Schema::hasColumn('pago', 'estado'))->toBeFalse();
});

test('el listado de pagos filtra por el estado de cobranza del pedido', function () {
    $saldado = Pedido::factory()->create(['total' => 1000]);
    Pago::factory()->create(['pedido_id' => $saldado->id, 'monto' => 1000]);

    $conSaldo = Pedido::factory()->create(['total' => 1000]);
    Pago::factory()->create(['pedido_id' => $conSaldo->id, 'monto' => 400]);

    $user = usuarioConPermiso('pagos.ver');

    $this->actingAs($user)
        ->get(route('pagos.index', ['estado' => 'PAGADO']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pagos.data', 1)
            ->where('pagos.data.0.pedido_id', $saldado->id));

    $this->actingAs($user)
        ->get(route('pagos.index', ['estado' => 'PARCIAL']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pagos.data', 1)
            ->where('pagos.data.0.pedido_id', $conSaldo->id));
});

/*
|--------------------------------------------------------------------------
| Índices de reporte
|--------------------------------------------------------------------------
*/

test('las columnas por las que se filtra tienen indice', function () {
    // SQLite y MariaDB nombran los índices igual porque los define la migración.
    expect(Schema::hasIndex('cotizacion', 'cotizacion_estado_fecha_index'))->toBeTrue()
        ->and(Schema::hasIndex('pedido', 'pedido_estado_fecha_index'))->toBeTrue()
        ->and(Schema::hasIndex('compra', 'compra_estado_fecha_index'))->toBeTrue()
        ->and(Schema::hasIndex('pago', 'pago_fecha_pago_index'))->toBeTrue();
});
