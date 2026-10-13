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

/*
|--------------------------------------------------------------------------
| Historial de consultas
|--------------------------------------------------------------------------
*/

test('el historial lista las últimas 20 consultas propias, la más reciente primero', function () {
    $this->global->givePermissionTo('reportes.asistente');

    IaConsulta::factory()->count(22)->for($this->global)->sequence(fn ($s) => ['pregunta' => "Pregunta {$s->index}"])->create();
    IaConsulta::factory()->for($this->local)->create(['pregunta' => 'De otro usuario']);
    IaConsulta::factory()->for($this->global)->create([
        'pregunta' => 'Con gráfico',
        'resultados' => [['herramienta' => 'ConsultarVentas', 'grafico' => ['tipo' => 'bar'], 'tabla' => ['filas' => []]]],
    ]);

    $respuesta = $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.consultas'))
        ->assertOk()
        ->assertJsonPath('limite', 20)
        ->assertJsonCount(20, 'consultas')
        ->assertJsonPath('consultas.0.pregunta', 'Con gráfico')
        ->assertJsonPath('consultas.0.graficos', 1)
        ->assertJsonPath('consultas.0.tablas', 1)
        ->assertJsonPath('consultas.1.pregunta', 'Pregunta 21')
        ->assertJsonMissingPath('consultas.0.respuesta');

    expect(collect($respuesta->json('consultas'))->pluck('pregunta'))->not->toContain('De otro usuario');
});

test('el historial filtra por día en hora de la empresa, no en UTC', function () {
    $this->global->givePermissionTo('reportes.asistente');

    // 02:00 UTC del 7 = 22:00 del 6 en La Paz (UTC-4).
    IaConsulta::factory()->for($this->global)->create(['pregunta' => 'Noche del 6', 'created_at' => '2026-10-07 02:00:00']);
    IaConsulta::factory()->for($this->global)->create(['pregunta' => 'Mañana del 7', 'created_at' => '2026-10-07 13:00:00']);

    $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.consultas', ['fecha' => '2026-10-06']))
        ->assertOk()
        ->assertJsonCount(1, 'consultas')
        ->assertJsonPath('consultas.0.pregunta', 'Noche del 6')
        ->assertJsonPath('consultas.0.dia', '2026-10-06')
        ->assertJsonPath('consultas.0.hora', '22:00')
        // Instante exacto (UTC): el panel lo usa para reconocer la respuesta de una espera retomada.
        ->assertJsonPath('consultas.0.fecha', '2026-10-07T02:00:00+00:00');

    $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.consultas', ['fecha' => '2999-01-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fecha');
});

test('una consulta del historial abre su conversación', function () {
    $this->global->givePermissionTo('reportes.asistente');

    $primera = IaConsulta::factory()->for($this->global)->create(['conversacion_id' => 'conv-vieja', 'pregunta' => '¿Cuánto vendimos?']);
    IaConsulta::factory()->for($this->global)->create(['conversacion_id' => 'conv-vieja', 'pregunta' => '¿Y el mes pasado?']);
    IaConsulta::factory()->for($this->global)->create(['conversacion_id' => 'conv-nueva', 'pregunta' => 'Otra cosa']);
    $sinRespuesta = IaConsulta::factory()->for($this->global)->sinRespuesta()->create();

    $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.historial', ['consulta_id' => $primera->id]))
        ->assertOk()
        ->assertJsonCount(2, 'consultas')
        ->assertJsonPath('consultas.0.pregunta', '¿Cuánto vendimos?')
        ->assertJsonPath('consultas.1.pregunta', '¿Y el mes pasado?');

    $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.historial', ['consulta_id' => $sinRespuesta->id]))
        ->assertOk()
        ->assertJsonCount(1, 'consultas')
        ->assertJsonPath('consultas.0.id', $sinRespuesta->id);
});

test('una conversación larga se abre alrededor de la consulta elegida', function () {
    $this->global->givePermissionTo('reportes.asistente');

    $consultas = IaConsulta::factory()->count(30)->for($this->global)->create(['conversacion_id' => 'conv-larga']);
    $elegida = $consultas[3];

    $ids = collect($this->actingAs($this->global)
        ->getJson(route('reportes.bi.asistente.historial', ['consulta_id' => $elegida->id]))
        ->assertOk()
        ->assertJsonCount(20, 'consultas')
        ->json('consultas'))->pluck('id');

    expect($ids)->toContain($elegida->id)
        ->and($ids->first())->toBe($consultas[0]->id);
});

test('no se puede abrir una consulta de otro usuario', function () {
    $this->global->givePermissionTo('reportes.asistente');
    $ajena = IaConsulta::factory()->for($this->local)->create();

    $this->actingAs($this->global)->getJson(route('reportes.bi.asistente.historial', ['consulta_id' => $ajena->id]))
        ->assertNotFound();
});

test('el historial pide el permiso del asistente', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('reportes.bi.asistente.consultas'))
        ->assertForbidden();
});
