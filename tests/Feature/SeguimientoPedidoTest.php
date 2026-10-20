<?php

use App\Http\Controllers\SeguimientoPedidoController;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\NotaEntrega;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use App\Services\Pedido\SeguimientoPublicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/**
 * Seguimiento público de pedidos (`/seguimiento`).
 *
 * Como en el cotizador, buena parte de estos tests no comprueba que la
 * página "funcione" sino que no se pueda abusar de ella: que el número solo
 * no abra el pedido, que no se distinga un número inexistente de unos
 * dígitos equivocados, que lo que el taller anota para sí no salga y que los
 * 4 dígitos no se puedan adivinar a fuerza bruta.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('seguimiento-buscar');
});

/**
 * Un pedido de un cliente con teléfono 7123-4567 y NIT 1020304050, con los
 * ítems en las etapas que se pidan.
 *
 * @param  list<string>  $etapas
 */
function pedidoDeCliente(array $etapas = ['ELABORACION'], array $atributos = []): Pedido
{
    $cliente = Cliente::factory()->create([
        'razon_social' => 'Farmacias del Sur S.R.L.',
        'telefono' => '7123-4567',
        'nit' => '1020304050',
    ]);

    $pedido = Pedido::factory()->create([
        'cotizacion_id' => Cotizacion::factory()->convertida()->create(['cliente_id' => $cliente->id])->id,
        'numero_pedido' => 'PED-20261020-AB12C',
        'fecha_pedido' => '2026-10-01',
        'fecha_entrega_estimada' => '2026-10-30',
        'total' => 1000,
        ...$atributos,
    ]);

    foreach ($etapas as $indice => $etapa) {
        PedidoDetalle::factory()->estadoItem($etapa)->create([
            'pedido_id' => $pedido->id,
            'descripcion' => 'Exhibidor de mostrador '.($indice + 1),
            'ancho' => 1.2,
            'alto' => 0.8,
            'cantidad' => 3,
        ]);
    }

    $pedido->recalcularEstado();

    return $pedido->fresh();
}

function verificar(string $numero = 'PED-20261020-AB12C', string $digitos = '4567'): TestResponse
{
    return test()->post(route('seguimiento.buscar'), ['numero' => $numero, 'digitos' => $digitos]);
}

/*
|--------------------------------------------------------------------------
| El formulario
|--------------------------------------------------------------------------
*/

test('el formulario es publico, se indexa y no carga javascript', function () {
    $html = $this->get(route('seguimiento'))
        ->assertOk()
        ->assertSee('¿En qué va tu pedido?')
        ->assertSee('Control de calidad')
        ->getContent();

    expect($html)->not->toContain('resources/js/publico.js')
        ->and($html)->not->toContain('resources/js/app.js');
});

test('el enlace del vendedor llega con el numero ya escrito', function () {
    $this->get(route('seguimiento', ['pedido' => 'ped-20261020-ab12c']))
        ->assertOk()
        ->assertSee('value="PED-20261020-AB12C"', false);
});

test('la cabecera y el pie enlazan la seccion', function () {
    $this->get(route('inicio'))
        ->assertOk()
        ->assertSee(route('seguimiento'), false)
        ->assertSee('Mi pedido');
});

/*
|--------------------------------------------------------------------------
| Verificación
|--------------------------------------------------------------------------
*/

test('numero y ultimos 4 digitos del telefono abren el pedido', function () {
    pedidoDeCliente();

    verificar()->assertRedirect(route('seguimiento.show', 'PED-20261020-AB12C'));

    $this->get(route('seguimiento.show', 'PED-20261020-AB12C'))
        ->assertOk()
        ->assertSee('Farmacias del Sur S.R.L.')
        ->assertSee('Exhibidor de mostrador 1')
        ->assertSee('1,20 × 0,80 m')
        ->assertSee('Elaboración');
});

test('tambien sirven los ultimos 4 digitos del NIT', function () {
    pedidoDeCliente();

    verificar(digitos: '4050')->assertRedirect(route('seguimiento.show', 'PED-20261020-AB12C'));
});

test('el numero se acepta en minusculas y con espacios', function () {
    pedidoDeCliente();

    verificar(' ped-20261020-ab12c ')->assertRedirect(route('seguimiento.show', 'PED-20261020-AB12C'));
});

test('digitos equivocados y numero inexistente dan el mismo mensaje', function () {
    pedidoDeCliente();

    $mensaje = 'No encontramos un pedido con esos datos. Revisa el número y los 4 dígitos.';

    verificar(digitos: '0000')
        ->assertRedirect(route('seguimiento'))
        ->assertSessionHasErrors(['busqueda' => $mensaje]);

    verificar('PED-20261020-ZZZZZ')
        ->assertRedirect(route('seguimiento'))
        ->assertSessionHasErrors(['busqueda' => $mensaje]);

    expect(session(SeguimientoPedidoController::SESION_VERIFICADOS))->toBeNull();
});

test('un cliente sin telefono ni NIT no se puede verificar', function () {
    $pedido = pedidoDeCliente();
    $pedido->cotizacion->cliente->update(['telefono' => '', 'nit' => 'S/N']);

    verificar(digitos: '0000')->assertSessionHasErrors('busqueda');
});

test('valida la forma del numero y de los digitos', function () {
    verificar('12345', '45')
        ->assertSessionHasErrors(['numero', 'digitos']);
});

test('el enlace solo no abre el pedido: vuelve al formulario con el numero', function () {
    pedidoDeCliente();

    $this->get(route('seguimiento.show', 'PED-20261020-AB12C'))
        ->assertRedirect(route('seguimiento', ['pedido' => 'PED-20261020-AB12C']));
});

test('verificar un pedido no abre otro', function () {
    pedidoDeCliente();
    $ajeno = Pedido::factory()->create(['numero_pedido' => 'PED-20261020-OTRO1']);

    verificar();

    $this->get(route('seguimiento.show', $ajeno->numero_pedido))
        ->assertRedirect(route('seguimiento', ['pedido' => $ajeno->numero_pedido]));
});

test('cerrar la consulta olvida los pedidos verificados', function () {
    pedidoDeCliente();
    verificar();

    $this->post(route('seguimiento.cerrar'))->assertRedirect(route('seguimiento'));

    $this->get(route('seguimiento.show', 'PED-20261020-AB12C'))->assertRedirect();
});

test('los 4 digitos no se pueden adivinar a fuerza bruta contra un mismo pedido', function () {
    pedidoDeCliente();
    config(['seguimiento.throttle.por_minuto' => 1000, 'seguimiento.throttle.por_pedido_hora' => 3]);

    foreach (['0001', '0002', '0003'] as $intento) {
        verificar(digitos: $intento)->assertRedirect();
    }

    // El cuarto intento choca aunque traiga los dígitos correctos.
    verificar()->assertTooManyRequests();
});

/*
|--------------------------------------------------------------------------
| La página del pedido
|--------------------------------------------------------------------------
*/

test('la pagina del pedido no se indexa ni se guarda en cache', function () {
    pedidoDeCliente();
    verificar();

    $this->get(route('seguimiento.show', 'PED-20261020-AB12C'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('noindex, nofollow', false);
});

test('no muestra lo que el taller anota para si', function () {
    $pedido = pedidoDeCliente();

    PedidoSeguimiento::factory()->create([
        'pedido_detalle_id' => $pedido->detalles->first()->id,
        'etapa' => 'DISENO',
        'observaciones' => 'Cliente complicado, cobrar adelantado',
    ]);

    verificar();

    $this->get(route('seguimiento.show', $pedido->numero_pedido))
        ->assertOk()
        ->assertDontSee('Cliente complicado')
        ->assertDontSee('7123-4567')
        ->assertDontSee('1020304050');
});

test('muestra entregas y saldo', function () {
    $pedido = pedidoDeCliente(['ENTREGADO']);
    NotaEntrega::factory()->create(['pedido_id' => $pedido->id, 'numero_nota' => 'NE-20261020-QWERT']);
    Pago::factory()->create(['pedido_id' => $pedido->id, 'monto' => 400]);

    verificar();

    $this->get(route('seguimiento.show', $pedido->numero_pedido))
        ->assertOk()
        ->assertSee('Tu pedido fue entregado')
        ->assertSee('NE-20261020-QWERT')
        ->assertSee('Bs 400,00')
        ->assertSee('Bs 600,00')
        ->assertSee('Pago parcial');
});

test('un pedido cancelado lo dice y muestra donde se detuvo', function () {
    $pedido = pedidoDeCliente(['ACABADO']);
    $pedido->update(['estado' => 'CANCELADO']);

    verificar();

    $this->get(route('seguimiento.show', $pedido->numero_pedido))
        ->assertOk()
        ->assertSee('Este pedido fue cancelado')
        ->assertSee('acabado');
});

/*
|--------------------------------------------------------------------------
| Línea de tiempo
|--------------------------------------------------------------------------
*/

test('la linea de tiempo marca completas, actual y pendientes', function () {
    $pedido = pedidoDeCliente(['ACABADO']);

    $pasos = collect(app(SeguimientoPublicoService::class)->resumen($pedido)['pasos'])
        ->pluck('estado', 'clave')
        ->all();

    expect($pasos)->toBe([
        'CONFIRMADO' => 'completo',
        'DISENO' => 'completo',
        'ELABORACION' => 'completo',
        'ACABADO' => 'actual',
        'CONTROL_CALIDAD' => 'pendiente',
        'ENTREGADO' => 'pendiente',
    ]);
});

test('con varios trabajos avanza al ritmo del mas lento y cuenta los que ya pasaron', function () {
    $pedido = pedidoDeCliente(['ELABORACION', 'CONTROL_CALIDAD', 'ACABADO']);

    $resumen = app(SeguimientoPublicoService::class)->resumen($pedido);
    $actual = collect($resumen['pasos'])->firstWhere('estado', 'actual');

    expect($resumen['etapa']['clave'])->toBe('ELABORACION')
        ->and($actual['avance'])->toBe(['listos' => 2, 'total' => 3]);
});

test('las fechas de cada paso salen del seguimiento del taller', function () {
    $pedido = pedidoDeCliente(['ELABORACION']);
    $detalle = $pedido->detalles->first();

    PedidoSeguimiento::factory()->create([
        'pedido_detalle_id' => $detalle->id,
        'etapa' => 'DISENO',
        'fecha_inicio' => '2026-10-02 09:00:00',
        'fecha_fin' => '2026-10-04 17:00:00',
    ]);
    PedidoSeguimiento::factory()->create([
        'pedido_detalle_id' => $detalle->id,
        'etapa' => 'ELABORACION',
        'fecha_inicio' => '2026-10-05 08:00:00',
        'fecha_fin' => null,
    ]);

    $pasos = collect(app(SeguimientoPublicoService::class)->resumen($pedido)['pasos'])->keyBy('clave');

    expect($pasos['DISENO']['fecha']->toDateString())->toBe('2026-10-04')
        ->and($pasos['ELABORACION']['fecha']->toDateString())->toBe('2026-10-05')
        ->and($pasos['ACABADO']['fecha'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Buscadores
|--------------------------------------------------------------------------
*/

test('robots bloquea los pedidos y el sitemap lista el formulario', function () {
    app()->detectEnvironment(fn () => 'production');

    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /seguimiento/');

    $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

    expect(collect($xml->url)->map(fn ($url) => (string) $url->loc)->all())->toContain(route('seguimiento'));
});
