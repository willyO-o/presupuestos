<?php

use App\Models\Area;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Reporte\InteligenciaNegociosService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * Estacionalidad del BI: pedidos por mes calendario, una serie por gestión
 * y el promedio, sobre TODA la historia.
 *
 * Hoy es 20 de octubre de 2026 en todas las pruebas: octubre es el mes en
 * curso (no entra en el promedio) y noviembre y diciembre todavía no llegan.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    // Alcance TODAS: acá se prueba la cuenta; el acotado está en AlcanceReportesTest.
    $this->usuario = User::factory()->todasLasSucursales()->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * `$cantidad` pedidos con esa fecha, reciclando sucursal, área, vendedor y
 * cliente (las factories de Área y Sucursal se agotan si cada pedido arma
 * su propia cadena, ver reporte.md).
 */
function pedidosEnFecha(int $cantidad, string $fecha): void
{
    $sucursal = Sucursal::query()->first() ?? Sucursal::factory()->create();
    $area = Area::query()->first() ?? Area::factory()->create();
    $empleado = Empleado::query()->first() ?? Empleado::factory()->recycle([$sucursal, $area])->create();
    $cliente = Cliente::query()->first() ?? Cliente::factory()->create();

    foreach (range(1, $cantidad) as $i) {
        Pedido::factory()->create([
            'cotizacion_id' => Cotizacion::factory()->convertida()->recycle([$sucursal, $area, $empleado, $cliente])->create()->id,
            'fecha_pedido' => $fecha,
        ]);
    }
}

function estacionalidadDe(User $usuario, ?PeriodoReporte $periodo = null): array
{
    return app(InteligenciaNegociosService::class)->datos($usuario, $periodo)['estacionalidad'];
}

test('arma una serie por gestion, con vacios antes del primer pedido y en los meses que no llegaron', function () {
    pedidosEnFecha(2, '2025-03-10');
    pedidosEnFecha(1, '2025-11-05');
    pedidosEnFecha(3, '2026-03-02');
    pedidosEnFecha(1, '2026-10-15');

    $e = estacionalidadDe($this->usuario);

    expect($e['meses'])->toBe(['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'])
        ->and($e['desde'])->toBe('Mar-2025')
        ->and($e['mes_en_curso'])->toBe('Oct-2026')
        ->and($e['gestiones'])->toBe([
            // Enero y febrero de 2025 son anteriores al primer pedido: vacíos, no ceros.
            ['anio' => 2025, 'pedidos' => [null, null, 2, 0, 0, 0, 0, 0, 0, 0, 1, 0], 'total' => 3],
            // Noviembre y diciembre de 2026 todavía no llegaron.
            ['anio' => 2026, 'pedidos' => [0, 0, 3, 0, 0, 0, 0, 0, 0, 1, null, null], 'total' => 4],
        ]);
});

test('el promedio es por gestion disponible y no cuenta el mes en curso', function () {
    pedidosEnFecha(2, '2025-03-10');
    pedidosEnFecha(1, '2025-11-05');
    pedidosEnFecha(3, '2026-03-02');
    pedidosEnFecha(4, '2026-10-15');

    $promedio = estacionalidadDe($this->usuario)['promedio'];

    expect($promedio[2])->toBe(2.5)   // marzo: (2 + 3) / 2, no la suma 5
        ->and($promedio[0])->toBe(0.0) // enero: solo 2026 tiene enero
        ->and($promedio[10])->toBe(1.0) // noviembre: solo 2025
        // Octubre: el de 2026 está en curso y no cuenta; queda el 0 de 2025.
        ->and($promedio[9])->toBe(0.0);
});

test('no depende del filtro de fechas de la pantalla', function () {
    pedidosEnFecha(1, '2024-06-10');
    pedidosEnFecha(1, '2026-09-10');

    $e = estacionalidadDe($this->usuario, PeriodoReporte::desdeFiltros('2026-09-01', '2026-10-20'));

    expect(array_column($e['gestiones'], 'anio'))->toBe([2024, 2025, 2026]);
});

test('sin pedidos no hay series y el promedio queda vacio', function () {
    $e = estacionalidadDe($this->usuario);

    expect($e['gestiones'])->toBe([])
        ->and($e['promedio'])->toBe(array_fill(0, 12, null))
        ->and($e['desde'])->toBeNull();
});

test('grafica las 5 gestiones mas recientes y avisa cuantas quedan afuera', function () {
    // El promedio más 5 gestiones = los 6 colores de la paleta, que no se cicla.
    foreach (range(2020, 2026) as $anio) {
        pedidosEnFecha(1, "{$anio}-05-10");
    }

    $e = estacionalidadDe($this->usuario);

    expect(array_column($e['gestiones'], 'anio'))->toBe([2022, 2023, 2024, 2025, 2026])
        ->and($e['gestiones_ocultas'])->toBe(2)
        // El promedio sí usa las 7 gestiones.
        ->and($e['promedio'][4])->toBe(1.0);
});
