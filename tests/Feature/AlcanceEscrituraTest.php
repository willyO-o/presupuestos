<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/**
 * Alcance por sucursal en la ESCRITURA (fase 4).
 *
 * Acotar solo la lectura deja la mitad del agujero abierto: el desplegable
 * puede listar una sola sucursal y el navegador mandar igual el id de otra a
 * mano. Y hasta la fase 4, cinco rutas de pedido (asignar área, estado,
 * medidas, consumo, cancelar) cambiaban el estado de un pedido ajeno con solo
 * poner su id en la URL, sin pasar nunca por la pantalla.
 *
 * La regla de "qué ve quién" está en AlcanceSucursalTest y el cableado de los
 * listados en AlcanceModulosTest. Acá solo se comprueba que NO se pueda
 * ESCRIBIR fuera del alcance.
 */
uses(RefreshDatabase::class);

/** Cuenta acotada a `$sucursal` por su ficha de empleado. */
function escritor(Sucursal $sucursal, string ...$permisos): User
{
    collect($permisos)->each(fn (string $p) => Permission::findOrCreate($p, 'web'));

    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->givePermissionTo($permisos);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);

    return $user->fresh();
}

/**
 * Payload mínimo de una cotización válida.
 *
 * @return array<string, mixed>
 */
function datosCotizacion(Sucursal $sucursal): array
{
    return [
        'cliente_id' => Cliente::factory()->create()->id,
        'empleado_id' => Empleado::factory()->create()->id,
        'sucursal_id' => $sucursal->id,
        'fecha' => now()->toDateString(),
        'detalles' => [[
            'descripcion' => 'Letrero',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'precio_manual' => 'SI',
        ]],
    ];
}

/*
|--------------------------------------------------------------------------
| Crear / mover algo a una sucursal ajena
|--------------------------------------------------------------------------
*/

test('no se puede crear una cotizacion a nombre de una sucursal ajena', function () {
    // El desplegable ya viene filtrado, pero el POST se puede armar a mano.
    // Un `Rule::exists` NO alcanza: comprueba que la sucursal exista, no que
    // sea suya.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $this->actingAs(escritor($mia, 'cotizaciones.crear'))
        ->post(route('cotizaciones.store'), datosCotizacion($ajena))
        ->assertSessionHasErrors('sucursal_id');

    expect(Cotizacion::count())->toBe(0);
});

test('si se puede crear en la sucursal propia', function () {
    $mia = Sucursal::factory()->create();

    $this->actingAs(escritor($mia, 'cotizaciones.crear'))
        ->post(route('cotizaciones.store'), datosCotizacion($mia))
        ->assertSessionHasNoErrors();

    expect(Cotizacion::where('sucursal_id', $mia->id)->count())->toBe(1);
});

test('tampoco se puede MOVER una cotizacion propia a una sucursal ajena', function () {
    // El caso que se escapa fácil: la cotización es suya, el destino no.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $mia->id, 'estado' => 'PENDIENTE']);

    $this->actingAs(escritor($mia, 'cotizaciones.editar'))
        ->put(route('cotizaciones.update', $cotizacion), datosCotizacion($ajena))
        ->assertSessionHasErrors('sucursal_id');

    expect($cotizacion->fresh()->sucursal_id)->toBe($mia->id);
});

test('no se puede dar de alta un empleado en una sucursal ajena', function () {
    // La sucursal de la ficha define el alcance PROPIA de su cuenta: crearla
    // en una ajena sería repartir alcance.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $this->actingAs(escritor($mia, 'empleados.crear'))
        ->post(route('empleados.store'), [
            'sucursal_id' => $ajena->id,
            'area_id' => Area::factory()->create()->id,
            'nombres' => 'Ana',
            'ci' => '9876543',
            'cargo' => Empleado::cargos()[0],
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasErrors('sucursal_id');
});

/*
|--------------------------------------------------------------------------
| Claves foráneas hacia documentos ajenos
|--------------------------------------------------------------------------
*/

test('no se puede cobrar contra el pedido de otra sucursal', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $ajeno = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->create(['sucursal_id' => $ajena->id])->id,
    ]);

    $this->actingAs(escritor($mia, 'pagos.registrar'))
        ->post(route('pagos.store'), [
            'pedido_id' => $ajeno->id,
            'monto' => 500,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'EFECTIVO',
        ])
        ->assertSessionHasErrors('pedido_id');

    expect($ajeno->pagos()->count())->toBe(0);
});

test('no se puede convertir en pedido la cotizacion de otra sucursal', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->aprobada()->create(['sucursal_id' => $ajena->id]);

    $this->actingAs(escritor($mia, 'pedidos.crear'))
        ->post(route('pedidos.store'), ['cotizacion_id' => $cotizacion->id])
        ->assertSessionHasErrors('cotizacion_id');

    expect(Pedido::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Acciones sobre un documento ajeno ya identificado
|--------------------------------------------------------------------------
*/

test('no se puede cambiar el estado de un item de un pedido ajeno', function () {
    // El agujero que dejó abierto la fase 3: acotó `show` pero no las cinco
    // rutas que operan sobre el pedido. Con solo el permiso de acción se
    // avanzaba la producción de otra sucursal poniendo su id en la URL.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $pedido = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->create(['sucursal_id' => $ajena->id])->id,
    ]);
    $detalle = PedidoDetalle::factory()->create(['pedido_id' => $pedido->id, 'estado_item' => 'DISENO']);

    $usuario = escritor($mia, 'pedidos.ver', 'pedidos.actualizar_estado', 'pedidos.asignar_area');

    $this->actingAs($usuario)
        ->put(route('pedidos.detalle.estado', [$pedido, $detalle]), ['estado_item' => 'ENTREGADO'])
        ->assertForbidden();

    $this->actingAs($usuario)
        ->put(route('pedidos.detalle.medidas', [$pedido, $detalle]), [
            'descripcion' => 'Otro', 'cantidad' => 5, 'motivo' => 'porque sí',
        ])
        ->assertForbidden();

    // El payload va COMPLETO a propósito: el Form Request se resuelve antes
    // que el cuerpo del controlador, así que uno inválido daría 302 por
    // validación y no llegaría a probar el guarda de sucursal. (No es un
    // agujero: la escritura sigue detrás del guarda igual.)
    $this->actingAs($usuario)
        ->post(route('pedidos.detalle.asignar-area', [$pedido, $detalle]), [
            'area_id' => Area::factory()->create()->id,
            'empleado_id' => Empleado::factory()->create()->id,
            'etapa' => 'ELABORACION',
        ])
        ->assertForbidden();

    expect($detalle->fresh()->estado_item)->toBe('DISENO');
});

test('no se puede cancelar el pedido de otra sucursal', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $pedido = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->create(['sucursal_id' => $ajena->id])->id,
        'estado' => 'DISENO',
    ]);

    $this->actingAs(escritor($mia, 'pedidos.ver', 'pedidos.actualizar_estado'))
        ->post(route('pedidos.cancelar', $pedido))
        ->assertForbidden();

    expect($pedido->fresh()->estado)->toBe('DISENO');
});

test('no se puede aprobar ni borrar una cotizacion ajena', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $ajena->id, 'estado' => 'PENDIENTE']);

    $usuario = escritor($mia, 'cotizaciones.ver', 'cotizaciones.aprobar', 'cotizaciones.eliminar');

    $this->actingAs($usuario)->post(route('cotizaciones.aprobar', $cotizacion))->assertForbidden();
    $this->actingAs($usuario)->delete(route('cotizaciones.destroy', $cotizacion))->assertForbidden();

    expect($cotizacion->fresh()->estado)->toBe('PENDIENTE');
});

test('no se puede editar ni borrar la ficha de un empleado ajeno', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $empleado = Empleado::factory()->create(['sucursal_id' => $ajena->id, 'nombres' => 'Original']);

    $usuario = escritor($mia, 'empleados.editar', 'empleados.eliminar');

    $this->actingAs($usuario)
        ->put(route('empleados.update', $empleado), [
            'sucursal_id' => $mia->id,
            'area_id' => $empleado->area_id,
            'nombres' => 'Robado',
            'ci' => $empleado->ci,
            'cargo' => $empleado->cargo,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'ACTIVO',
        ])
        ->assertForbidden();

    $this->actingAs($usuario)->delete(route('empleados.destroy', $empleado))->assertForbidden();

    expect($empleado->fresh()->nombres)->toBe('Original');
});

/*
|--------------------------------------------------------------------------
| Lectura y escritura tienen que coincidir
|--------------------------------------------------------------------------
*/

test('lo que se ve se puede editar: no hay documentos mirables pero intocables', function () {
    // El riesgo de tener dos mecanismos (alcance + override por módulo): que el
    // listado use uno y el Form Request otro. Si divergen aparece el "lo veo
    // pero me da 403", o peor, el "no lo veo pero lo puedo modificar".
    $mia = Sucursal::factory()->create();

    $cotizacion = Cotizacion::factory()->create(['sucursal_id' => $mia->id, 'estado' => 'PENDIENTE']);
    $usuario = escritor($mia, 'cotizaciones.ver', 'cotizaciones.aprobar');

    $this->actingAs($usuario)->get(route('cotizaciones.show', $cotizacion))->assertOk();
    $this->actingAs($usuario)->post(route('cotizaciones.aprobar', $cotizacion))->assertRedirect();

    expect($cotizacion->fresh()->estado)->toBe('APROBADA');
});
