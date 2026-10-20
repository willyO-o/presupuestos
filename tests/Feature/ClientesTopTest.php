<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * "Clientes que más compran" del BI (Demanda y ventas).
 *
 * Hoy es 20 de octubre de 2026. El periodo de casi todas las pruebas es
 * julio–septiembre de 2026 (92 días), así que el periodo anterior de la
 * misma duración va del 31 de marzo al 30 de junio.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    $this->usuario = User::factory()->todasLasSucursales()->create();
    $this->periodo = PeriodoReporte::desdeFiltros('2026-07-01', '2026-09-30');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Una cotización de `$cliente` con ese total y fecha, reciclando sucursal,
 * área y vendedor (las factories de Área y Sucursal se agotan si cada
 * cotización arma su propia cadena, ver reporte.md).
 */
function venderA(Cliente $cliente, float $total, string $fecha, string $estado = 'APROBADA', ?Sucursal $sucursal = null): void
{
    $sucursal ??= Sucursal::query()->first() ?? Sucursal::factory()->create();
    $area = Area::query()->first() ?? Area::factory()->create();
    $empleado = Empleado::query()->first() ?? Empleado::factory()->recycle([$sucursal, $area])->create();

    Cotizacion::factory()->recycle([$sucursal, $area, $empleado])->create([
        'cliente_id' => $cliente->id,
        'sucursal_id' => $sucursal->id,
        'estado' => $estado,
        'fecha' => $fecha,
        'total' => $total,
    ]);
}

function clientesTop(User $usuario, PeriodoReporte $periodo): array
{
    return app(InteligenciaNegociosService::class)->datos($usuario, $periodo)['clientes_top'];
}

test('ordena por monto aprobado y da la parte de cada cliente', function () {
    $grande = Cliente::factory()->create(['razon_social' => 'Distribuidora Illimani S.R.L.']);
    $chico = Cliente::factory()->create(['razon_social' => 'Farmacias Sajama S.A.']);

    venderA($grande, 600, '2026-07-10');
    venderA($grande, 200, '2026-08-10', 'CONVERTIDA');
    venderA($chico, 200, '2026-09-10');
    // No cuentan: pendiente y fuera del periodo.
    venderA($chico, 9999, '2026-08-01', 'PENDIENTE');
    venderA($chico, 9999, '2026-10-05');

    $top = clientesTop($this->usuario, $this->periodo);

    expect($top['total'])->toBe(1000.0)
        ->and($top['total_clientes'])->toBe(2)
        ->and(array_column($top['clientes'], 'nombre'))->toBe(['Distribuidora Illimani S.R.L.', 'Farmacias Sajama S.A.'])
        ->and($top['clientes'][0])->toMatchArray(['monto' => 800.0, 'compras' => 2, 'participacion' => 80.0])
        ->and($top['clientes'][1])->toMatchArray(['monto' => 200.0, 'compras' => 1, 'participacion' => 20.0]);
});

test('lista los 10 primeros y junta el resto en otros para sumar 100', function () {
    foreach (range(1, 12) as $i) {
        venderA(Cliente::factory()->create(), $i * 100, '2026-08-15');
    }

    $top = clientesTop($this->usuario, $this->periodo);

    // Total 7.800: los 10 primeros (300..1200) suman 7.500; quedan 100 + 200.
    expect($top['clientes'])->toHaveCount(10)
        ->and($top['clientes'][0]['monto'])->toBe(1200.0)
        ->and($top['otros'])->toBe(['clientes' => 2, 'monto' => 300.0, 'participacion' => 3.8])
        ->and($top['concentracion'])->toBe(96.2);
});

test('compara contra un periodo anterior de la misma duracion y marca a los nuevos', function () {
    $habitual = Cliente::factory()->create();
    $nuevo = Cliente::factory()->create();

    venderA($habitual, 500, '2026-05-10'); // periodo anterior (31/03 al 30/06)
    venderA($habitual, 750, '2026-08-10');
    venderA($nuevo, 300, '2026-08-20');

    $top = clientesTop($this->usuario, $this->periodo);
    $porId = collect($top['clientes'])->keyBy('id');

    expect($top['periodo_anterior'])->toBe(['desde' => '2026-03-31', 'hasta' => '2026-06-30'])
        ->and($porId[$habitual->id]['variacion'])->toBe(50.0)
        ->and($porId[$nuevo->id]['variacion'])->toBeNull();
});

test('la ultima compra sale de toda la historia, aunque sea despues del periodo', function () {
    $cliente = Cliente::factory()->create();

    venderA($cliente, 100, '2026-07-05');
    venderA($cliente, 100, '2026-10-12'); // fuera del periodo, pero es su última compra

    $fila = clientesTop($this->usuario, $this->periodo)['clientes'][0];

    expect($fila['ultima_compra'])->toBe('2026-10-12')
        ->and($fila['dias_sin_comprar'])->toBe(8)
        ->and($fila['monto'])->toBe(100.0);
});

test('respeta las sucursales del usuario', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();
    $area = Area::factory()->create();
    Empleado::factory()->recycle([$mia, $area])->create();

    venderA(Cliente::factory()->create(['razon_social' => 'Cliente de mi sucursal']), 100, '2026-08-01', sucursal: $mia);
    venderA(Cliente::factory()->create(['razon_social' => 'Cliente ajeno']), 900, '2026-08-01', sucursal: $ajena);

    $acotado = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    Empleado::factory()->recycle($area)->create(['user_id' => $acotado->id, 'sucursal_id' => $mia->id]);

    $top = clientesTop($acotado->fresh(), $this->periodo);

    expect(array_column($top['clientes'], 'nombre'))->toBe(['Cliente de mi sucursal'])
        ->and($top['total'])->toBe(100.0);
});

test('trae el logo del cliente cuando lo tiene', function () {
    $conLogo = Cliente::factory()->create(['logo' => 'clientes/illimani.jpg']);
    $sinLogo = Cliente::factory()->create();

    venderA($conLogo, 500, '2026-08-01');
    venderA($sinLogo, 100, '2026-08-01');

    $clientes = clientesTop($this->usuario, $this->periodo)['clientes'];

    expect($clientes[0]['logo_url'])->toEndWith('clientes/illimani.jpg')
        ->and($clientes[1]['logo_url'])->toBeNull();
});

test('sin ventas en el periodo no hay ranking', function () {
    $top = clientesTop($this->usuario, $this->periodo);

    expect($top['clientes'])->toBe([])
        ->and($top['concentracion'])->toBeNull()
        ->and($top['otros']['clientes'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Pareto: todos los clientes y el % acumulado
|--------------------------------------------------------------------------
*/

test('el pareto acumula el porcentaje y cuenta cuantos clientes hacen el 80', function () {
    venderA(Cliente::factory()->create(['razon_social' => 'Uno']), 500, '2026-08-01');
    venderA(Cliente::factory()->create(['razon_social' => 'Dos']), 300, '2026-08-01');
    venderA(Cliente::factory()->create(['razon_social' => 'Tres']), 200, '2026-08-01');

    $pareto = clientesTop($this->usuario, $this->periodo)['pareto'];

    expect($pareto['labels'])->toBe(['Uno', 'Dos', 'Tres'])
        ->and($pareto['montos'])->toBe([500.0, 300.0, 200.0])
        ->and($pareto['acumulado'])->toBe([50.0, 80.0, 100.0])
        // Con el segundo se llega justo al 80 %.
        ->and($pareto['clientes_corte'])->toBe(2)
        ->and($pareto['agrupados'])->toBe(0);
});

test('con mas de 30 clientes la cola va en una barra y el corte se cuenta sobre todos', function () {
    // 35 clientes que compran lo mismo: el 80 % se alcanza con el 28.
    foreach (range(1, 35) as $i) {
        venderA(Cliente::factory()->create(), 100, '2026-08-01');
    }

    $pareto = clientesTop($this->usuario, $this->periodo)['pareto'];

    expect($pareto['labels'])->toHaveCount(31)
        ->and(end($pareto['labels']))->toBe('Otros 5 clientes')
        ->and(end($pareto['montos']))->toBe(500.0)
        ->and(end($pareto['acumulado']))->toBe(100.0)
        ->and($pareto['agrupados'])->toBe(5)
        ->and($pareto['clientes_corte'])->toBe(28);
});
