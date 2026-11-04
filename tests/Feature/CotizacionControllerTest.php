<?php

use App\Models\CategoriaMaterial;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionDetalleItem;
use App\Models\Empleado;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\Sucursal;
use App\Models\TipoProyecto;
use App\Models\User;
use App\Services\Calculo\PrecioSugeridoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect([
        'cotizaciones.ver', 'cotizaciones.crear', 'cotizaciones.editar',
        'cotizaciones.aprobar', 'cotizaciones.eliminar',
    ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWith(string ...$permissions): User
{
    // Alcance TODAS: estas pruebas comprueban el MODULO, no el acotado por
    // sucursal (eso vive en AlcanceSucursalTest y AlcanceModulosTest). Sin
    // alcance la cuenta no veria ninguna fila y todo daria falso negativo.
    $user = User::factory()->todasLasSucursales()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function cabeceraValida(): array
{
    return [
        'cliente_id' => Cliente::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'sucursal_id' => Sucursal::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(15)->toDateString(),
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('cotizaciones.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cotizaciones.index'))
        ->assertForbidden();
});

test('a user with permission sees the list', function () {
    Cotizacion::factory()->count(3)->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Index')->has('cotizaciones.data', 3));
});

test('the show page renders the cotizacion with its detalle', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(2), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.show', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cotizaciones/Show')
            ->has('cotizacion.detalles', 2)
            ->has('margen', 2)
            // "Ver fórmula y criterios del semáforo" usa las tasas y umbrales de la configuración.
            ->where('config.impuestos.it', config('margen.impuestos.it'))
            ->where('config.semaforo.umbral_verde', config('margen.semaforo.umbral_verde'))
            ->where('config.semaforo.umbral_amarillo', config('margen.semaforo.umbral_amarillo')));
});

test('calendar dates reach the frontend as Y-m-d, without a UTC time that shifts the day', function () {
    $cotizacion = Cotizacion::factory()->create([
        'fecha' => '2026-11-01',
        'fecha_vencimiento' => '2026-11-16',
    ]);

    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.show', $cotizacion))
        ->assertInertia(fn ($page) => $page
            ->where('cotizacion.fecha', '2026-11-01')
            ->where('cotizacion.fecha_vencimiento', '2026-11-16'));
});

test('the edit page renders for a pending cotizacion', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Edit')->has('productos'));
});

test('the create page requires the crear permission', function () {
    $this->actingAs(userWith('cotizaciones.ver'))
        ->get(route('cotizaciones.create'))
        ->assertForbidden();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->get(route('cotizaciones.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cotizaciones/Create'));
});

test('storing a cotizacion computes totals on the server and ignores client amounts', function () {
    $user = userWith('cotizaciones.crear');

    $payload = [
        ...cabeceraValida(),
        'descuento' => 50,
        'aplicar_iva' => false,
        // Estos campos NO deben influir en el total guardado:
        'subtotal' => 999999,
        'total' => 999999,
        'iva' => 999999,
        'codigo_verificacion' => 'HACKEADO',
        'estado' => 'APROBADA',
        'detalles' => [
            ['descripcion' => 'Banner', 'ancho' => 2, 'alto' => 1, 'cantidad' => 3, 'precio_unitario' => 100],
            ['descripcion' => 'Vinil', 'cantidad' => 2, 'precio_unitario' => 25],
        ],
    ];

    $response = $this->actingAs($user)->post(route('cotizaciones.store'), $payload);

    $cotizacion = Cotizacion::latest('id')->first();

    $response->assertRedirect(route('cotizaciones.show', $cotizacion));
    expect($cotizacion->estado)->toBe('PENDIENTE')
        ->and((float) $cotizacion->subtotal)->toBe(350.0)
        ->and((float) $cotizacion->descuento)->toBe(50.0)
        ->and((float) $cotizacion->iva)->toBe(0.0)
        ->and((float) $cotizacion->total)->toBe(300.0)
        ->and($cotizacion->codigo_verificacion)->not->toBe('HACKEADO')
        ->and($cotizacion->detalles)->toHaveCount(2)
        // Sin hoja de costos, el precio de la línea solo puede ser manual.
        ->and($cotizacion->detalles->first()->precio_manual)->toBe('SI');

    $this->assertDatabaseHas('cotizacion_detalle', [
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Banner',
        'area_m2' => 2,
        'subtotal' => 300,
    ]);
});

test('a mueble line keeps profundidad, niveles and a long multi paragraph descripcion', function () {
    $descripcion = implode(PHP_EOL, [
        'Elaborado de Cabecera',
        str_repeat('Base metálica montada con trupan MDF, sulfaceado y pintado completo. ', 6),
        'Medida promedio: 70 cm ancho x 175 cm altura x 30 cm profundidad.',
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'detalles' => [[
            'descripcion' => $descripcion,
            'ancho' => 0.7,
            'alto' => 1.75,
            'profundidad' => 0.3,
            'niveles' => 4,
            'cantidad' => 1,
            'precio_unitario' => 3000,
        ]],
    ])->assertSessionHasNoErrors();

    $linea = Cotizacion::latest('id')->first()->detalles->first();

    expect(mb_strlen($descripcion))->toBeGreaterThan(255)
        ->and($linea->descripcion)->toBe($descripcion)
        ->and((float) $linea->profundidad)->toBe(0.3)
        ->and($linea->niveles)->toBe(4)
        // El área sigue siendo ancho × alto: la profundidad no la cambia.
        ->and((float) $linea->area_m2)->toBe(1.23);
});

test('a flat line without profundidad or niveles stays as before', function () {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'detalles' => [['descripcion' => 'Lona', 'ancho' => 2, 'alto' => 1, 'cantidad' => 1, 'precio_unitario' => 100]],
    ])->assertSessionHasNoErrors();

    $linea = Cotizacion::latest('id')->first()->detalles->first();

    expect($linea->profundidad)->toBeNull()->and($linea->niveles)->toBeNull();
});

test('niveles must be a whole number between 1 and 50', function (mixed $niveles) {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'detalles' => [['descripcion' => 'Estante', 'niveles' => $niveles, 'cantidad' => 1, 'precio_unitario' => 100]],
    ])->assertSessionHasErrors('detalles.0.niveles');
})->with(['cero' => 0, 'decimal' => 2.5, 'demasiados' => 51]);

/** Una línea de precio fijo (sin hoja de costos) con instalación. */
function lineaConInstalacion(array $extra = []): array
{
    return ['descripcion' => 'Cabecera', 'cantidad' => 1, 'precio_unitario' => 1000, 'instalacion' => 100, ...$extra];
}

test('the installation breakdown is computed on the server, with the night surcharge and transport', function () {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'aplicar_iva' => false,
        'detalles' => [lineaConInstalacion([
            // El monto que manda el navegador se ignora si hay desglose.
            'instalacion' => 9999,
            'instalacion_detalle' => [
                'personas' => 2,
                'horas' => 4,
                'costo_hora' => 18,
                'nocturna' => true,
                'recargo_nocturno' => 50,
                'transporte' => 80,
            ],
        ])],
    ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();
    $linea = $cotizacion->detalles->first();

    // 2 personas × 4 h × Bs 18 × 1,5 (nocturno) = 216 + 80 de transporte
    expect((float) $linea->instalacion)->toBe(296.0)
        ->and($linea->instalacion_detalle['mano_obra'])->toEqual(216)
        ->and($linea->instalacion_detalle['nocturna'])->toBeTrue()
        ->and((float) $cotizacion->instalacion)->toBe(296.0)
        ->and((float) $cotizacion->total)->toBe(1296.0);
});

test('a daytime installation has no surcharge', function () {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'detalles' => [lineaConInstalacion(['instalacion_detalle' => [
            'personas' => 2, 'horas' => 4, 'costo_hora' => 18, 'nocturna' => false, 'recargo_nocturno' => 50,
        ]])],
    ])->assertSessionHasNoErrors();

    expect((float) Cotizacion::latest('id')->first()->detalles->first()->instalacion)->toBe(144.0);
});

test('the installation is outside the IVA unless the seller marks it', function (bool $instalacionConIva, float $iva, string $guardado) {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'aplicar_iva' => true,
        'instalacion_con_iva' => $instalacionConIva,
        'detalles' => [lineaConInstalacion()],
    ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    // IVA 13 % de 1000 = 130; con la instalación, 13 % de 100 más.
    expect((float) $cotizacion->iva)->toBe($iva)
        ->and((float) $cotizacion->total)->toBe(1000 + $iva + 100)
        ->and($cotizacion->instalacion_con_iva)->toBe($guardado);
})->with([
    'sin IVA en la instalación' => [false, 130.0, 'NO'],
    'con IVA en la instalación' => [true, 143.0, 'SI'],
]);

test('without IVA on the cotizacion the installation pays none either', function () {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'aplicar_iva' => false,
        'instalacion_con_iva' => true,
        'detalles' => [lineaConInstalacion()],
    ]);

    expect((float) Cotizacion::latest('id')->first()->iva)->toBe(0.0);
});

test('the installation IVA default comes from the configuration', function () {
    config(['cotizacion.instalacion_con_iva' => true]);

    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'aplicar_iva' => true,
        'detalles' => [lineaConInstalacion()],
    ]);

    expect(Cotizacion::latest('id')->first()->instalacion_con_iva)->toBe('SI');
});

test('the create form receives the labour rate, night surcharge and installation IVA default', function () {
    config([
        'cotizacion.costo_hora_mano_obra' => 18,
        'cotizacion.recargo_nocturno' => 50,
        'cotizacion.instalacion_con_iva' => false,
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->get(route('cotizaciones.create'))
        ->assertInertia(fn ($page) => $page
            ->where('config.costo_hora_mano_obra', 18)
            ->where('config.recargo_nocturno', 50)
            ->where('config.instalacion_con_iva', false));
});

test('an installation breakdown needs at least one person', function () {
    $this->actingAs(userWith('cotizaciones.crear'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'detalles' => [lineaConInstalacion(['instalacion_detalle' => ['personas' => 0, 'horas' => 4, 'costo_hora' => 18]])],
    ])->assertSessionHasErrors('detalles.0.instalacion_detalle.personas');
});

test('the sale conditions are saved with the cotizacion and the show page gets the amount in words', function () {
    $this->actingAs(userWith('cotizaciones.crear', 'cotizaciones.ver'))->post(route('cotizaciones.store'), [
        ...cabeceraValida(),
        'aplicar_iva' => false,
        'forma_pago' => '15 días crédito',
        'tiempo_entrega' => '10 días hábiles',
        'lugar_entrega' => 'Instalado en el punto de venta',
        'detalles' => [['descripcion' => 'Cabecera', 'cantidad' => 1, 'precio_unitario' => 2875]],
    ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    expect($cotizacion->forma_pago)->toBe('15 días crédito')
        ->and($cotizacion->tiempo_entrega)->toBe('10 días hábiles')
        ->and($cotizacion->lugar_entrega)->toBe('Instalado en el punto de venta');

    $this->get(route('cotizaciones.show', $cotizacion))
        ->assertInertia(fn ($page) => $page
            ->where('montoEnLetras', 'Dos Mil Ochocientos Setenta y Cinco 00/100 Bolivianos'));
});

test('updating keeps the installation IVA choice and the sale conditions', function () {
    $cotizacion = Cotizacion::factory()->create(['estado' => 'PENDIENTE']);

    $this->actingAs(userWith('cotizaciones.editar'))->put(route('cotizaciones.update', $cotizacion), [
        ...cabeceraValida(),
        'aplicar_iva' => true,
        'instalacion_con_iva' => true,
        'forma_pago' => 'Contado',
        'detalles' => [lineaConInstalacion()],
    ])->assertSessionHasNoErrors();

    $cotizacion->refresh();

    expect($cotizacion->instalacion_con_iva)->toBe('SI')
        ->and((float) $cotizacion->iva)->toBe(143.0)
        ->and($cotizacion->forma_pago)->toBe('Contado');
});

test('a cotizacion needs at least one detalle line', function () {
    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [...cabeceraValida(), 'detalles' => []])
        ->assertSessionHasErrors('detalles');
});

test('an approved cotizacion cannot be edited', function () {
    $cotizacion = Cotizacion::factory()->aprobada()->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [['descripcion' => 'X', 'cantidad' => 1, 'precio_unitario' => 10]],
        ])
        ->assertRedirect(route('cotizaciones.show', $cotizacion))
        ->assertSessionHas('error');
});

test('updating a pending cotizacion replaces its detalle', function () {
    $cotizacion = Cotizacion::factory()->has(CotizacionDetalle::factory()->count(3), 'detalles')->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [['descripcion' => 'Único ítem', 'cantidad' => 1, 'precio_unitario' => 200]],
        ])
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    // El IVA lo aplica el motor por defecto (13 % de la base imponible),
    // ya no es un monto que mande el navegador.
    expect($cotizacion->fresh()->detalles)->toHaveCount(1)
        ->and((float) $cotizacion->fresh()->subtotal)->toBe(200.0)
        ->and((float) $cotizacion->fresh()->iva)->toBe(26.0)
        ->and((float) $cotizacion->fresh()->total)->toBe(226.0);
});

test('aprobar changes the estado and requires the aprobar permission', function () {
    $cotizacion = Cotizacion::factory()->create();

    $this->actingAs(userWith('cotizaciones.ver'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertForbidden();

    $this->actingAs(userWith('cotizaciones.aprobar'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertRedirect(route('cotizaciones.show', $cotizacion));

    expect($cotizacion->fresh()->estado)->toBe('APROBADA');
});

test('only a pending cotizacion can be approved', function () {
    $cotizacion = Cotizacion::factory()->rechazada()->create();

    $this->actingAs(userWith('cotizaciones.aprobar'))
        ->post(route('cotizaciones.aprobar', $cotizacion))
        ->assertSessionHas('error');

    expect($cotizacion->fresh()->estado)->toBe('RECHAZADA');
});

test('a converted cotizacion cannot be deleted', function () {
    $cotizacion = Cotizacion::factory()->convertida()->create();

    $this->actingAs(userWith('cotizaciones.eliminar'))
        ->delete(route('cotizaciones.destroy', $cotizacion))
        ->assertSessionHas('error');

    $this->assertModelExists($cotizacion);
});

test('costear returns the suggested price from the product BOM', function () {
    $categoriaProducto = CategoriaProducto::factory()->create();
    $producto = Producto::factory()->create([
        'categoria_producto_id' => $categoriaProducto->id,
        'unidad_medida' => 'M2',
    ]);
    $material = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'precio_unitario' => 100,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $response = $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), [
            'producto_id' => $producto->id,
            'ancho' => 2,
            'alto' => 1.5,
        ]);

    // driver M2 = area = 3 ; costo material unitario = 3 * 100 = 300
    $response->assertOk()
        ->assertJsonPath('costo_material_unitario', 300)
        ->assertJson(fn ($json) => $json->where('precio_sugerido', fn ($v) => $v > 300)->etc());
});

test('costear passes profundidad and niveles to the BOM formulas', function () {
    $producto = Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'UNIDAD',
    ]);
    $trupan = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'precio_unitario' => 100,
        'redondeo_compra' => null,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $trupan->id,
        'formula_id' => Formula::factory()->create(['expresion' => 'ancho * profundo * niveles'])->id,
        'cantidad_por_unidad' => null,
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), [
            'producto_id' => $producto->id,
            'ancho' => 0.7,
            'alto' => 1.75,
            'profundo' => 0.3,
            'niveles' => 4,
        ])
        // 0,7 × 0,3 × 4 = 0,84 m² × Bs 100
        ->assertOk()
        ->assertJsonPath('costo_material_unitario', 84);
});

test('costear adds the product labour hours at the company rate and prices with them', function () {
    config(['cotizacion.costo_hora_mano_obra' => 18]);

    $producto = Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'UNIDAD',
        'requiere_medidas' => 'NO',
        'horas_mano_obra' => 16,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => Material::factory()->create([
            'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
            'precio_unitario' => 100,
            'redondeo_compra' => null,
        ])->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $sinNivel = config('cotizacion.margen_sugerido');

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $producto->id])
        ->assertOk()
        ->assertJsonPath('costo_material_unitario', 100)
        ->assertJsonPath('costo_mano_obra_unitario', 288)
        ->assertJsonPath('insumos.1.tipo', 'MANO_OBRA')
        ->assertJsonPath('insumos.1.cantidad', 16)
        ->assertJsonPath('insumos.1.costo_unitario', 18)
        ->assertJsonPath('insumos.1.descripcion', PrecioSugeridoService::DESCRIPCION_MANO_OBRA)
        ->assertJsonPath('descripcion_mano_obra', PrecioSugeridoService::DESCRIPCION_MANO_OBRA)
        // El precio sugerido sale de material + mano de obra (388), no solo del material.
        ->assertJsonPath('precio_sugerido', round(388 * (1 + $sinNivel), 2));
});

test('costear without labour hours on the product only brings the materials', function () {
    $producto = Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'UNIDAD',
        'requiere_medidas' => 'NO',
        'horas_mano_obra' => null,
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $producto->id])
        ->assertOk()
        ->assertJsonPath('costo_mano_obra_unitario', 0)
        ->assertJsonCount(0, 'insumos');
});

test('costear returns 422 when the product needs measures it did not get', function () {
    $producto = Producto::factory()->create(['unidad_medida' => 'M2']);
    Formula::query()->delete();
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), ['producto_id' => $producto->id])
        ->assertStatus(422);
});

test('super-admin bypasses individual permissions', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)->get(route('cotizaciones.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Motor de margen automático (hoja de costos por línea)
|--------------------------------------------------------------------------
|
| Ver tests/Unit/MotorMargenServiceTest.php para la validación numérica
| contra el Excel; acá se comprueba que el controlador lo aplica y que el
| navegador no puede inventarse precios ni rentabilidad.
*/

test('storing a cotizacion prices each line with the margin engine', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $payload = [
        ...cabeceraValida(),
        'aplicar_iva' => true,
        'detalles' => [[
            'descripcion' => 'Mueble exhibidor',
            'tipo_proyecto_id' => $tipoProyecto->id,
            'cantidad' => 1,
            // El navegador manda un precio ridículo: debe ignorarse, porque
            // la línea no está marcada como precio manual.
            'precio_unitario' => 1,
            'items' => [
                ['tipo' => 'MATERIAL', 'descripcion' => 'Fierro', 'cantidad' => 0.55, 'costo_unitario' => 45],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Plancha', 'cantidad' => 0.15, 'costo_unitario' => 145],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Pintura', 'cantidad' => 0.33, 'costo_unitario' => 70],
                ['tipo' => 'MATERIAL', 'descripcion' => 'Otros', 'cantidad' => 1, 'costo_unitario' => 10],
                ['tipo' => 'IMPRESION', 'descripcion' => 'Adhesivo', 'cantidad' => 0.07, 'costo_unitario' => 80],
                ['tipo' => 'MANO_OBRA', 'descripcion' => 'Horas', 'cantidad' => 6, 'costo_unitario' => 15],
            ],
        ]],
    ];

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), $payload)
        ->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();
    $detalle = $cotizacion->detalles->first();

    // Mismos números que la hoja "Muebles Exhibidores" del Excel.
    expect((float) $detalle->costo_base)->toBe(175.20)
        ->and((float) $detalle->costo_ajustado)->toBe(227.76)
        ->and((float) $detalle->precio_unitario)->toBe(341.64)
        ->and((float) $detalle->factor_complejidad)->toBe(1.30)
        ->and((float) $detalle->margen_aplicado)->toBe(0.5)
        ->and($detalle->precio_manual)->toBe('NO')
        ->and($detalle->items)->toHaveCount(6)
        ->and((float) $cotizacion->iva)->toBe(44.41)
        ->and((float) $cotizacion->total)->toBe(386.05)
        ->and((float) $cotizacion->utilidad_real)->toBe(77.72)
        ->and($cotizacion->estado_margen)->toBe('VERDE')
        ->and($cotizacion->recomendacion)->toBe('ACEPTAR');
});

test('the installation amount is added after IVA and is not taxed', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Isla cabecera',
                'tipo_proyecto_id' => $tipoProyecto->id,
                'cantidad' => 1,
                'instalacion' => 100,
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 175.20],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    expect((float) $cotizacion->instalacion)->toBe(100.0)
        // IVA sobre 341,64 (el precio), no sobre 441,64.
        ->and((float) $cotizacion->iva)->toBe(44.41)
        ->and((float) $cotizacion->total)->toBe(486.05);
});

test('a manual price overrides the engine but still feeds the traffic light', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Trabajo negociado',
                'tipo_proyecto_id' => $tipoProyecto->id,
                'cantidad' => 1,
                'precio_manual' => 'SI',
                'precio_unitario' => 250,
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 175.20],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::latest('id')->first();

    // 250 sobre un costo ajustado de 227,76 deja una utilidad real mínima.
    expect((float) $cotizacion->subtotal)->toBe(250.0)
        ->and((float) $cotizacion->costo_ajustado)->toBe(227.76)
        ->and($cotizacion->estado_margen)->toBe('ROJO')
        ->and($cotizacion->recomendacion)->toBe('NO ACEPTAR');
});

test('a manual price is required when the seller takes over the price', function () {
    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Sin precio',
                'cantidad' => 1,
                'precio_manual' => 'SI',
                'items' => [
                    ['tipo' => 'MATERIAL', 'descripcion' => 'Insumos', 'cantidad' => 1, 'costo_unitario' => 10],
                ],
            ]],
        ])->assertSessionHasErrors('detalles.0.precio_unitario');
});

test('updating a cotizacion replaces the cost sheet of its lines', function () {
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1)->has(CotizacionDetalleItem::factory()->count(3), 'items'), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Línea nueva',
                'cantidad' => 2,
                'items' => [
                    ['tipo' => 'MANO_OBRA', 'descripcion' => 'Horas', 'cantidad' => 4, 'costo_unitario' => 15],
                ],
            ]],
        ])->assertSessionHasNoErrors();

    $detalle = $cotizacion->fresh()->detalles->first();

    expect($detalle->items)->toHaveCount(1)
        ->and((float) $detalle->costo_base)->toBe(120.0)
        ->and(CotizacionDetalleItem::query()->count())->toBe(1);
});

test('costear brings back the BOM as prefilled cost sheet rows', function () {
    $producto = Producto::factory()->create([
        'categoria_producto_id' => CategoriaProducto::factory()->create()->id,
        'unidad_medida' => 'M2',
    ]);
    $material = Material::factory()->create([
        'categoria_material_id' => CategoriaMaterial::factory()->create()->id,
        'precio_unitario' => 100,
        'redondeo_compra' => null,
    ]);
    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => $material->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
    ]);
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.costear'), [
            'producto_id' => $producto->id,
            'ancho' => 2,
            'alto' => 1.5,
            'tipo_proyecto_id' => $tipoProyecto->id,
        ])
        ->assertOk()
        ->assertJsonPath('costo_material_unitario', 300)
        ->assertJsonPath('insumos.0.cantidad', 3)
        ->assertJsonPath('insumos.0.costo_unitario', 100)
        ->assertJsonPath('insumos.0.material_id', $material->id)
        // 300 × 1,3 × 1,5 = 585
        ->assertJsonPath('precio_sugerido', 585)
        ->assertJsonPath('motor.estado', 'VERDE');
});

test('simular runs the engine over a hand written cost sheet', function () {
    $tipoProyecto = TipoProyecto::factory()->medio()->create();

    $this->actingAs(userWith('cotizaciones.crear'))
        ->postJson(route('cotizaciones.simular'), [
            'costo_base' => 175.20,
            'tipo_proyecto_id' => $tipoProyecto->id,
        ])
        ->assertOk()
        ->assertJsonPath('costo_ajustado', 227.76)
        ->assertJsonPath('precio', 341.64)
        ->assertJsonPath('utilidad_real', 77.72)
        ->assertJsonPath('estado', 'VERDE');
});

/*
|--------------------------------------------------------------------------
| Imagen referencial de la línea: subida propia, copiada del producto, o
| conservada al editar (se convierte/re-codifica a JPG en el servidor).
|--------------------------------------------------------------------------
*/

test('a line accepts an uploaded imagen and converts it to jpg', function () {
    Storage::fake('public');

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Banner con foto',
                'cantidad' => 1,
                'precio_unitario' => 100,
                'imagen' => UploadedFile::fake()->image('referencia.png', 400, 300),
            ]],
        ])->assertSessionHasNoErrors();

    $detalle = Cotizacion::latest('id')->first()->detalles->first();

    expect($detalle->imagen)->not->toBeNull()
        ->and($detalle->imagen)->toEndWith('.jpg');
    Storage::disk('public')->assertExists($detalle->imagen);
});

test('a line can reuse the producto imagen instead of uploading its own', function () {
    Storage::fake('public');
    // Contenido REAL de imagen (no un string cualquiera): el servicio la lee
    // con getimagesize/GD para re-codificarla a JPG al copiarla.
    Storage::disk('public')->putFileAs('productos', UploadedFile::fake()->image('catalogo.jpg', 100, 100), 'catalogo.jpg');
    $producto = Producto::factory()->create(['imagen' => 'productos/catalogo.jpg']);

    $this->actingAs(userWith('cotizaciones.crear'))
        ->post(route('cotizaciones.store'), [
            ...cabeceraValida(),
            'detalles' => [[
                'producto_id' => $producto->id,
                'descripcion' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'usar_imagen_producto' => true,
            ]],
        ])->assertSessionHasNoErrors();

    $detalle = Cotizacion::latest('id')->first()->detalles->first();

    // Es una copia (foto histórica), no la misma ruta del producto: cambiar
    // o borrar la imagen del producto después no debe afectar el presupuesto.
    expect($detalle->imagen)->not->toBeNull()
        ->and($detalle->imagen)->not->toBe('productos/catalogo.jpg');
    Storage::disk('public')->assertExists($detalle->imagen);
});

test('updating a cotizacion keeps an untouched line imagen and removes orphaned ones', function () {
    Storage::fake('public');
    Storage::disk('public')->put('cotizaciones/original.jpg', 'contenido');
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1)->state(['imagen' => 'cotizaciones/original.jpg']), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Línea sin tocar la imagen',
                'cantidad' => 1,
                'precio_unitario' => 50,
                'imagen_actual' => 'cotizaciones/original.jpg',
            ]],
        ])->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists('cotizaciones/original.jpg');
    expect($cotizacion->fresh()->detalles->first()->imagen)->toBe('cotizaciones/original.jpg');
});

test('updating a cotizacion without keeping the previous imagen deletes it from disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('cotizaciones/vieja.jpg', 'contenido');
    $cotizacion = Cotizacion::factory()
        ->has(CotizacionDetalle::factory()->count(1)->state(['imagen' => 'cotizaciones/vieja.jpg']), 'detalles')
        ->create();

    $this->actingAs(userWith('cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), [
            ...cabeceraValida(),
            'detalles' => [[
                'descripcion' => 'Línea nueva sin imagen',
                'cantidad' => 1,
                'precio_unitario' => 50,
            ]],
        ])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('cotizaciones/vieja.jpg');
});
