<?php

use App\Ai\Agents\AsistenteBi;
use App\Ai\Tools\BuscarEnCatalogo;
use App\Ai\Tools\CompararPeriodos;
use App\Ai\Tools\ConsultarVentas;
use App\Ai\Tools\HerramientaBi;
use App\Models\Area;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empleado;
use App\Models\FamiliaProducto;
use App\Models\IaConsulta;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Permission;

/**
 * Asistente de BI: herramientas cerradas (alcance por sucursal, solo
 * lectura, datos minimizados) y endpoints del panel (permiso delegable,
 * límite por hora, conversación del propio usuario, IA no disponible).
 */
beforeEach(function () {
    config(['ai.providers.gemini.key' => null, 'ai.providers.anthropic.key' => null]);
    Permission::findOrCreate('reportes.asistente', 'web');

    $this->mia = Sucursal::factory()->create(['nombre' => 'El Alto · Casa matriz']);
    $this->ajena = Sucursal::factory()->create(['nombre' => 'Santa Cruz · Equipetrol']);
    $this->area = Area::factory()->create();
    $this->cliente = Cliente::factory()->create(['razon_social' => 'Farmacias Sajama S.A.', 'email' => 'secreto@farmacias.example']);

    $this->familia = FamiliaProducto::factory()->create(['nombre' => 'Letreros y letras 3D']);
    $categoria = CategoriaProducto::factory()->create(['nombre' => 'Letreros Luminosos', 'familia_producto_id' => $this->familia->id]);
    $this->producto = Producto::factory()->create(['nombre' => 'Letras corpóreas 3D iluminadas', 'categoria_producto_id' => $categoria->id]);

    $this->local = usuarioAsistenteEnSucursal($this->mia);
    $this->global = User::factory()->todasLasSucursales()->create();
});

function usuarioAsistenteEnSucursal(Sucursal $sucursal): User
{
    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id, 'area_id' => test()->area->id]);

    return $user->fresh();
}

function ventaParaAsistente(Sucursal $sucursal, string $fecha, float $subtotal, string $estado = 'APROBADA'): Cotizacion
{
    $test = test();
    $vendedor = Empleado::query()->where('sucursal_id', $sucursal->id)->first()
        ?? Empleado::factory()->create(['sucursal_id' => $sucursal->id, 'area_id' => $test->area->id]);

    $cotizacion = Cotizacion::factory()->create([
        'sucursal_id' => $sucursal->id,
        'empleado_id' => $vendedor->id,
        'cliente_id' => $test->cliente->id,
        'fecha' => $fecha,
        'estado' => $estado,
    ]);

    CotizacionDetalle::factory()->for($cotizacion, 'cotizacion')->create([
        'producto_id' => $test->producto->id,
        'cantidad' => 1,
        'subtotal' => $subtotal,
        'costo_ajustado' => $subtotal / 2,
    ]);

    return $cotizacion;
}

function ejecutarHerramienta(HerramientaBi $herramienta, array $argumentos): array
{
    return json_decode((string) $herramienta->handle(new Request($argumentos)), true);
}

/*
|--------------------------------------------------------------------------
| Herramientas
|--------------------------------------------------------------------------
*/

test('ConsultarVentas respeta las sucursales del usuario', function () {
    ventaParaAsistente($this->mia, '2026-09-10', 1000);
    ventaParaAsistente($this->ajena, '2026-09-12', 5000);
    ventaParaAsistente($this->mia, '2026-09-15', 300, 'PENDIENTE'); // no vendida

    $argumentos = ['metrica' => 'monto_vendido', 'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'agrupar_por' => 'familia'];

    $local = ejecutarHerramienta(new ConsultarVentas($this->local), $argumentos);
    $global = ejecutarHerramienta(new ConsultarVentas($this->global), $argumentos);

    expect($local['resumen']['total'])->toEqual(1000)
        ->and($local['tabla']['filas'][0])->toMatchArray(['grupo' => 'Letreros y letras 3D'])
        ->and($local['grafico']['tipo'])->toBe('bar')
        ->and($local['calculo'])->toContain('sin IVA')
        ->and($global['resumen']['total'])->toEqual(6000);
});

test('CompararPeriodos devuelve la variación ya calculada', function () {
    ventaParaAsistente($this->mia, '2026-09-10', 1000);
    ventaParaAsistente($this->mia, '2026-08-10', 500);

    $resultado = ejecutarHerramienta(new CompararPeriodos($this->global), [
        'metrica' => 'monto_vendido', 'agrupar_por' => 'ninguno',
        'a_desde' => '2026-09-01', 'a_hasta' => '2026-09-30',
        'b_desde' => '2026-08-01', 'b_hasta' => '2026-08-31',
    ]);

    expect($resultado['resumen']['total_a'])->toEqual(1000)
        ->and($resultado['resumen']['total_b'])->toEqual(500)
        ->and($resultado['resumen']['variacion_pct'])->toEqual(100);
});

test('BuscarEnCatalogo traduce el texto del usuario a ids', function () {
    $resultado = ejecutarHerramienta(new BuscarEnCatalogo($this->global), ['texto' => 'letras 3D']);

    expect(collect($resultado['resumen']['coincidencias']['productos'])->pluck('id')->all())->toBe([$this->producto->id])
        ->and(collect($resultado['resumen']['coincidencias']['familias'])->pluck('id')->all())->toBe([$this->familia->id]);
});

test('un parámetro inválido vuelve al modelo como mensaje, no rompe la respuesta', function () {
    $resultado = ejecutarHerramienta(new ConsultarVentas($this->global), [
        'metrica' => 'inventada', 'desde' => '2026-09-01', 'hasta' => '2026-08-01', 'agrupar_por' => 'mes',
    ]);

    expect($resultado)->toHaveKey('error')
        ->and($resultado['error'])->toContain('Parámetros inválidos');
});

test('una herramienta no puede escribir en la base', function () {
    $intrusa = new class($this->global) extends HerramientaBi
    {
        public function description(): string
        {
            return 'prueba';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }

        protected function reglas(): array
        {
            return [];
        }

        protected function ejecutar(array $datos): array
        {
            DB::table('area')->insert(['nombre' => 'Intrusa', 'estado' => 'ACTIVO']);

            return [];
        }
    };

    $antes = Area::query()->count();

    expect(ejecutarHerramienta($intrusa, []))->toHaveKey('error')
        ->and(Area::query()->count())->toBe($antes);
});

test('lo que sale hacia el modelo no lleva datos personales', function () {
    ventaParaAsistente($this->mia, '2026-09-10', 1000);

    $crudo = (string) (new ConsultarVentas($this->global))->handle(new Request([
        'metrica' => 'monto_vendido', 'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'agrupar_por' => 'cliente',
    ]));

    expect($crudo)->toContain('Farmacias Sajama')
        ->not->toContain('secreto@farmacias.example');
});

/*
|--------------------------------------------------------------------------
| Endpoints del panel
|--------------------------------------------------------------------------
*/

test('el asistente pide el permiso reportes.asistente, delegable a cualquiera', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->getJson(route('reportes.bi.asistente.historial'))->assertForbidden();
    $this->actingAs($sinPermiso)->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => '¿Cuánto vendimos?'])->assertForbidden();

    $sinPermiso->givePermissionTo('reportes.asistente');

    $this->actingAs($sinPermiso)->getJson(route('reportes.bi.asistente.historial'))
        ->assertOk()
        ->assertJsonPath('estado.disponible', false);
});

test('sin claves de IA la pregunta queda registrada con el motivo', function () {
    $this->global->givePermissionTo('reportes.asistente');

    $this->actingAs($this->global)
        ->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => '¿Cuánto vendimos este mes?'])
        ->assertOk()
        ->assertJsonPath('consulta.estado', 'ERROR')
        ->assertJsonPath('consulta.respuesta', fn (string $texto) => str_contains($texto, 'GEMINI_API_KEY'));
});

test('con IA disponible la respuesta queda en el historial del usuario', function () {
    config(['ai.providers.gemini.key' => 'clave']);
    AsistenteBi::fake(['Este mes se vendió Bs 1.000,00 en letras 3D.']);
    $this->global->givePermissionTo('reportes.asistente');

    $this->actingAs($this->global)
        ->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => '¿Cuánto vendimos en letras 3D?'])
        ->assertOk()
        ->assertJsonPath('consulta.estado', 'OK')
        ->assertJsonPath('consulta.respuesta', 'Este mes se vendió Bs 1.000,00 en letras 3D.')
        ->assertJsonPath('consulta.proveedor', 'gemini');

    expect(IaConsulta::query()->where('user_id', $this->global->id)->count())->toBe(1);
});

test('no se puede continuar la conversación de otro usuario', function () {
    config(['ai.providers.gemini.key' => 'clave']);
    AsistenteBi::fake(['Respuesta.']);
    $this->global->givePermissionTo('reportes.asistente');

    IaConsulta::create([
        'user_id' => $this->local->id, 'conversacion_id' => 'conversacion-ajena',
        'pregunta' => 'x', 'respuesta' => 'y', 'estado' => 'OK',
    ]);

    $this->actingAs($this->global)
        ->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => '¿Y el mes pasado?', 'conversacion_id' => 'conversacion-ajena'])
        ->assertOk()
        ->assertJsonPath('consulta.conversacion_id', fn ($id) => $id !== 'conversacion-ajena');
});

test('el límite de preguntas por hora corta con 429', function () {
    config(['ia.asistente.preguntas_por_hora' => 1]);
    $this->global->givePermissionTo('reportes.asistente');

    $this->actingAs($this->global)->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => 'Primera pregunta'])->assertOk();
    $this->actingAs($this->global)->postJson(route('reportes.bi.asistente.preguntar'), ['pregunta' => 'Segunda pregunta'])
        ->assertStatus(429)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'límite'));
});
