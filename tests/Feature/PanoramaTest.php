<?php

use App\Models\Area;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\FamiliaProducto;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Reporte\ConsultaSql;
use App\Services\Reporte\PanoramaService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Panorama del BI (`/reportes/bi/panorama`).
 *
 * Hoy es 20 de octubre de 2026. El periodo es julio–septiembre (3 meses), así
 * que el periodo anterior es abril–junio: cada mes se compara con el que está
 * 3 meses antes (agosto con mayo).
 *
 * Los números se pueden sacar a mano: una cotización de Bs 1.000 con Bs 100
 * de descuento (10 %) deja Bs 900 de ingreso, repartidos entre sus líneas.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');

    $this->usuario = User::factory()->todasLasSucursales()->create();
    $this->periodo = PeriodoReporte::desdeFiltros('2026-07-01', '2026-09-30');

    $this->sucursal = Sucursal::factory()->create(['nombre' => 'El Alto']);
    $this->area = Area::factory()->create();
    $this->vendedor = Empleado::factory()->recycle([$this->sucursal, $this->area])->create(['nombres' => 'Daniel', 'paterno' => 'Huarachi']);
    $this->cliente = Cliente::factory()->create(['razon_social' => 'Distribuidora Illimani S.R.L.', 'ciudad' => 'La Paz']);

    $muebles = FamiliaProducto::factory()->create(['nombre' => 'Muebles y exhibidores']);
    $letreros = FamiliaProducto::factory()->create(['nombre' => 'Letreros y letras 3D']);
    $this->exhibidores = CategoriaProducto::factory()->create(['nombre' => 'Exhibidores', 'familia_producto_id' => $muebles->id]);
    $letras = CategoriaProducto::factory()->create(['nombre' => 'Letras corpóreas', 'familia_producto_id' => $letreros->id]);
    $this->muebles = $muebles;
    $this->isla = Producto::factory()->create(['nombre' => 'Isla promocional', 'categoria_producto_id' => $this->exhibidores->id]);
    $this->letra = Producto::factory()->create(['nombre' => 'Letra 3D', 'categoria_producto_id' => $letras->id]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Una cotización con sus líneas. Cada línea: [producto|null, subtotal, costo].
 *
 * @param  list<array{0: ?Producto, 1: float, 2: float}>  $lineas
 * @param  array<string, mixed>  $atributos
 */
function cotizar(array $lineas, array $atributos = []): Cotizacion
{
    $subtotal = array_sum(array_column($lineas, 1));

    $cotizacion = Cotizacion::factory()->recycle([test()->sucursal, test()->area, test()->vendedor])->create([
        'cliente_id' => test()->cliente->id,
        'empleado_id' => test()->vendedor->id,
        'sucursal_id' => test()->sucursal->id,
        'estado' => 'APROBADA',
        'fecha' => '2026-08-10',
        'subtotal' => $subtotal,
        'descuento' => 0,
        'total' => $subtotal,
        ...$atributos,
    ]);

    foreach ($lineas as [$producto, $monto, $costo]) {
        CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create([
            'producto_id' => $producto?->id,
            'cantidad' => 1,
            'precio_unitario' => $monto,
            'subtotal' => $monto,
            'costo_ajustado' => $costo,
        ]);
    }

    return $cotizacion;
}

function panorama(?User $usuario = null, array $filtros = []): array
{
    return app(PanoramaService::class)->datos(
        $usuario ?? test()->usuario,
        test()->periodo,
        [...['familia_id' => null, 'categoria_id' => null, 'cliente_id' => null, 'sucursal_id' => null], ...$filtros],
    );
}

function kpi(array $datos, string $clave): array
{
    return collect($datos['kpis'])->firstWhere('clave', $clave);
}

/*
|--------------------------------------------------------------------------
| Pantalla y permisos
|--------------------------------------------------------------------------
*/

test('el panorama pide el permiso del BI y abre con todos sus cuadros', function () {
    Permission::findOrCreate('reportes.bi', 'web');
    $sinPermiso = User::factory()->create();
    $conPermiso = User::factory()->todasLasSucursales()->create();
    $conPermiso->givePermissionTo('reportes.bi');

    $this->actingAs($sinPermiso)->get(route('reportes.bi.panorama'))->assertForbidden();

    $this->actingAs($conPermiso)->get(route('reportes.bi.panorama'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reportes/BiPanorama')
            ->has('datos.kpis', 5)
            ->has('datos.ventas_mensuales')
            ->has('datos.ingresos_margen')
            ->has('datos.por_tipo')
            ->has('datos.embudo.etapas', 4)
            ->has('datos.top_clientes')
            ->has('datos.top_productos')
            ->has('datos.por_vendedor')
            ->has('datos.por_ciudad')
            ->has('datos.opciones'));
});

test('una fecha al reves no llega a calcular nada', function () {
    Permission::findOrCreate('reportes.bi', 'web');
    $usuario = User::factory()->todasLasSucursales()->create();
    $usuario->givePermissionTo('reportes.bi');

    $this->actingAs($usuario)
        ->get(route('reportes.bi.panorama', ['desde' => '2026-09-30', 'hasta' => '2026-07-01']))
        ->assertSessionHasErrors('hasta');
});

/*
|--------------------------------------------------------------------------
| KPIs
|--------------------------------------------------------------------------
*/

test('las ventas prorratean el descuento y el margen ignora las lineas sin costo', function () {
    // 1.000 con 10 % de descuento: la isla deja 540 (costo 300), la letra 360
    // pero no tiene hoja de costos (costo 0) y no entra en el margen.
    cotizar([[$this->isla, 600, 300], [$this->letra, 400, 0]], ['descuento' => 100]);

    $datos = panorama();

    expect(kpi($datos, 'ventas')['valor'])->toBe(900.0)
        // (540 - 300) / 540 = 44,4 %; con la letra adentro sería 66,7 %.
        ->and(kpi($datos, 'margen')['valor'])->toBe(44.4)
        ->and(kpi($datos, 'ticket')['valor'])->toBe(900.0);
});

test('compara contra el mismo tramo corrido tantos meses como dura el periodo', function () {
    cotizar([[$this->isla, 900, 450]]);                                // agosto
    cotizar([[$this->isla, 500, 250]], ['fecha' => '2026-05-12']);     // mayo: periodo anterior
    cotizar([[$this->isla, 9999, 1]], ['fecha' => '2026-03-01']);      // fuera de los dos

    $datos = panorama();
    $ventas = kpi($datos, 'ventas');

    expect($datos['periodo_anterior'])->toBe(['desde' => '2026-04-01', 'hasta' => '2026-06-30'])
        ->and($ventas['anterior'])->toBe(500.0)
        ->and($ventas['variacion'])->toBe(80.0)
        ->and($ventas['unidad_variacion'])->toBe('%')
        // Mes con mes: agosto queda alineado con mayo.
        ->and($datos['ventas_mensuales']['labels'])->toBe(['Jul-2026', 'Ago-2026', 'Sep-2026'])
        ->and($datos['ventas_mensuales']['claves'])->toBe(['2026-07', '2026-08', '2026-09'])
        ->and($datos['ventas_mensuales']['actual'])->toBe([0.0, 900.0, 0.0])
        ->and($datos['ventas_mensuales']['anterior'])->toBe([0.0, 500.0, 0.0])
        ->and($datos['ventas_mensuales']['mejor'])->toBe(1)
        ->and($datos['ventas_mensuales']['promedio'])->toBe(300.0)
        ->and($datos['ventas_mensuales']['hallazgo'])->toContain('Ago-2026 fue el mejor mes');
});

test('un solo mes se compara con el mes anterior completo y sin promedio', function () {
    cotizar([[$this->isla, 900, 450]], ['fecha' => '2026-08-10']);
    cotizar([[$this->isla, 600, 300]], ['fecha' => '2026-07-31']);   // último día del mes anterior

    $datos = app(PanoramaService::class)->datos(
        $this->usuario,
        PeriodoReporte::desdeFiltros('2026-08-01', '2026-08-31'),
        ['familia_id' => null, 'categoria_id' => null, 'cliente_id' => null, 'sucursal_id' => null],
    );

    expect($datos['periodo_anterior'])->toBe(['desde' => '2026-07-01', 'hasta' => '2026-07-31'])
        ->and(kpi($datos, 'ventas')['anterior'])->toBe(600.0)
        ->and($datos['ventas_mensuales']['promedio'])->toBeNull()
        ->and($datos['ventas_mensuales']['hallazgo'])->toBe('Ago-2026 cerró con Bs 900, +50,0 % frente al mes anterior.');
});

test('la conversion se compara en puntos y no cuenta los pendientes', function () {
    cotizar([[$this->isla, 100, 50]]);
    cotizar([[$this->isla, 100, 50]], ['estado' => 'RECHAZADA']);
    cotizar([[$this->isla, 100, 50]], ['estado' => 'PENDIENTE']);
    cotizar([[$this->isla, 100, 50]], ['fecha' => '2026-05-05']);

    $conversion = kpi(panorama(), 'conversion');

    expect($conversion['valor'])->toBe(50.0)       // 1 aprobada de 2 decididas
        ->and($conversion['anterior'])->toBe(100.0)
        ->and($conversion['variacion'])->toBe(-50.0)
        ->and($conversion['unidad_variacion'])->toBe('pp');
});

/*
|--------------------------------------------------------------------------
| Tipo de producto: drill-down por filtros
|--------------------------------------------------------------------------
*/

test('baja de familia a categoria y de categoria a producto', function () {
    cotizar([[$this->isla, 600, 300], [$this->letra, 400, 300], [null, 100, 0]]);

    $familias = panorama()['por_tipo'];

    expect($familias['nivel'])->toBe('familia')
        ->and($familias['siguiente'])->toBe('familia_id')
        ->and(array_column($familias['items'], 'nombre'))->toBe(['Muebles y exhibidores', 'Letreros y letras 3D', 'Trabajos a medida (sin catálogo)'])
        ->and($familias['items'][0])->toMatchArray(['ingreso' => 600.0, 'margen' => 50.0])
        ->and($familias['hallazgo'])->toContain('Muebles y exhibidores trae el');

    $categorias = panorama(filtros: ['familia_id' => $this->muebles->id])['por_tipo'];

    expect($categorias['nivel'])->toBe('categoria')
        ->and(array_column($categorias['items'], 'nombre'))->toBe(['Exhibidores'])
        ->and(array_column($categorias['migas'], 'texto'))->toBe(['Todos los tipos', 'Muebles y exhibidores'])
        // Con uno solo, "trae el 100 %" no dice nada: cuenta su margen.
        ->and($categorias['hallazgo'])->toBe('Todo lo vendido acá es de una sola categoría: Exhibidores, con 50,0 % de margen.');

    $productos = panorama(filtros: ['categoria_id' => $this->exhibidores->id])['por_tipo'];

    expect($productos['nivel'])->toBe('producto')
        ->and($productos['siguiente'])->toBeNull()
        ->and(array_column($productos['items'], 'nombre'))->toBe(['Isla promocional'])
        ->and(array_column($productos['migas'], 'texto'))->toBe(['Todos los tipos', 'Muebles y exhibidores', 'Exhibidores']);
});

test('el filtro de tipo de producto baja tambien a los KPIs', function () {
    cotizar([[$this->isla, 600, 300], [$this->letra, 400, 100]]);

    $datos = panorama(filtros: ['familia_id' => $this->muebles->id]);

    expect(kpi($datos, 'ventas')['valor'])->toBe(600.0)
        ->and(kpi($datos, 'margen')['valor'])->toBe(50.0);
});

/*
|--------------------------------------------------------------------------
| Embudo
|--------------------------------------------------------------------------
*/

test('el embudo va de emitidas a cobradas y marca donde se pierde mas', function () {
    $cobrada = cotizar([[$this->isla, 1000, 500]], ['estado' => 'CONVERTIDA']);
    $debe = cotizar([[$this->isla, 2000, 900]], ['estado' => 'CONVERTIDA']);
    cotizar([[$this->isla, 300, 100]]);                                  // aprobada sin pedido
    cotizar([[$this->isla, 300, 100]], ['estado' => 'RECHAZADA']);
    cotizar([[$this->isla, 300, 100]], ['estado' => 'PENDIENTE']);

    $pedido = Pedido::factory()->create(['cotizacion_id' => $cobrada->id, 'total' => 1000]);
    Pago::factory()->create(['pedido_id' => $pedido->id, 'monto' => 1000]);
    $pendiente = Pedido::factory()->create(['cotizacion_id' => $debe->id, 'total' => 2000]);
    Pago::factory()->create(['pedido_id' => $pendiente->id, 'monto' => 500]);

    $embudo = panorama()['embudo'];
    $cantidades = array_column($embudo['etapas'], 'cantidad', 'clave');

    expect($cantidades)->toBe(['emitidas' => 5, 'aprobadas' => 3, 'pedido' => 2, 'pagadas' => 1])
        ->and(array_column($embudo['etapas'], 'paso'))->toBe([null, 60.0, 66.7, 50.0])
        ->and($embudo['etapas'][3]['monto'])->toBe(1000.0)
        ->and($embudo['hallazgo'])->toContain('«pasaron a pedido» y «cobradas por completo»');
});

/*
|--------------------------------------------------------------------------
| Rankings y filtros
|--------------------------------------------------------------------------
*/

test('top de clientes y productos por ingreso, con su parte y margen', function () {
    $otro = Cliente::factory()->create(['razon_social' => 'Farmacias Sajama S.A.']);

    cotizar([[$this->isla, 800, 400]]);
    cotizar([[$this->letra, 200, 150]], ['cliente_id' => $otro->id]);

    $datos = panorama();

    expect(array_column($datos['top_clientes']['items'], 'nombre'))->toBe(['Distribuidora Illimani S.R.L.', 'Farmacias Sajama S.A.'])
        ->and($datos['top_clientes']['items'][0])->toMatchArray(['ingreso' => 800.0, 'participacion' => 80.0, 'margen' => 50.0])
        ->and(array_column($datos['top_productos']['items'], 'nombre'))->toBe(['Isla promocional', 'Letra 3D'])
        ->and($datos['top_productos']['items'][1]['margen'])->toBe(25.0);
});

test('vendedor y ciudad juntan la cola en otros', function () {
    foreach (range(1, 10) as $i) {
        $cliente = Cliente::factory()->create(['ciudad' => "Ciudad {$i}"]);
        cotizar([[$this->isla, $i * 100, 10]], ['cliente_id' => $cliente->id]);
    }

    $datos = panorama();

    expect($datos['por_ciudad']['items'])->toHaveCount(9)
        ->and($datos['por_ciudad']['items'][0]['nombre'])->toBe('Ciudad 10')
        ->and(end($datos['por_ciudad']['items'])['nombre'])->toBe('Otros (2)')
        ->and($datos['por_vendedor']['items'][0]['nombre'])->toBe('Daniel Huarachi')
        ->and($datos['por_vendedor']['hallazgo'])->toBe('Daniel Huarachi lidera con el 100,0 % de las ventas.');
});

test('el filtro de cliente deja solo sus ventas', function () {
    $otro = Cliente::factory()->create();
    cotizar([[$this->isla, 800, 400]]);
    cotizar([[$this->isla, 200, 100]], ['cliente_id' => $otro->id]);

    expect(kpi(panorama(filtros: ['cliente_id' => $otro->id]), 'ventas')['valor'])->toBe(200.0);
});

test('respeta las sucursales del usuario', function () {
    $ajena = Sucursal::factory()->create();
    cotizar([[$this->isla, 800, 400]]);
    cotizar([[$this->isla, 5000, 100]], ['sucursal_id' => $ajena->id]);

    $acotado = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    Empleado::factory()->recycle($this->area)->create(['user_id' => $acotado->id, 'sucursal_id' => $this->sucursal->id]);

    $datos = panorama($acotado->fresh());

    expect(kpi($datos, 'ventas')['valor'])->toBe(800.0)
        ->and(array_column($datos['opciones']['sucursales']->all(), 'nombre'))->toBe(['El Alto']);
});

test('sin ventas todo sale en cero y lo dice', function () {
    $datos = panorama();

    expect(kpi($datos, 'ventas')['valor'])->toBe(0.0)
        ->and(kpi($datos, 'margen')['valor'])->toBeNull()
        ->and($datos['ventas_mensuales']['mejor'])->toBeNull()
        ->and($datos['ventas_mensuales']['hallazgo'])->toBe('No hubo ventas en el periodo.')
        ->and($datos['embudo']['hallazgo'])->toBe('No hubo cotizaciones en el periodo.');
});

/*
|--------------------------------------------------------------------------
| Agregación en SQL portable
|--------------------------------------------------------------------------
*/

test('la expresion de mes se adapta al motor y no acepta cualquier cosa', function () {
    expect(ConsultaSql::mes('c.fecha'))->toBe("strftime('%Y-%m', c.fecha)");

    expect(fn () => ConsultaSql::mes('c.fecha); DROP TABLE cliente; --'))->toThrow(InvalidArgumentException::class);
});
