<?php

use App\Ai\Agents\RedactorResumenSemanal;
use App\Ai\Excepciones\IaNoDisponible;
use App\Ai\Seguridad\ConsultaSoloLectura;
use App\Ai\ServicioIa;
use App\Models\Area;
use App\Models\IaUso;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * ServicioIa: la única puerta hacia los proveedores. Corte por interruptor,
 * por falta de claves y por tope de gasto; failover solo entre proveedores
 * con clave; registro de cada llamada en `ia_uso`.
 */
beforeEach(function () {
    config([
        'ia.habilitada' => true,
        'ia.presupuesto_mensual_usd' => 20,
        'ai.providers.gemini.key' => null,
        'ai.providers.anthropic.key' => null,
    ]);
    $this->ia = app(ServicioIa::class);
});

function resumenFalso(): array
{
    return ['titular' => 'Semana estable.', 'destacados' => [], 'riesgos' => [], 'recomendaciones' => []];
}

test('la cadena de failover salta los proveedores sin clave y respeta el orden', function () {
    config(['ai.providers.anthropic.key' => 'clave-anthropic']);
    expect($this->ia->proveedores('asistente'))->toBe(['anthropic' => 'claude-sonnet-5-5']);

    config(['ai.providers.gemini.key' => 'clave-gemini']);
    expect(array_keys($this->ia->proveedores('asistente')))->toBe(['gemini', 'anthropic']);
});

test('sin ninguna clave no sale ninguna llamada y queda registrado', function () {
    RedactorResumenSemanal::fake()->preventStrayPrompts();

    expect(fn () => $this->ia->ejecutar(new RedactorResumenSemanal, 'hechos', 'resumen_semanal'))
        ->toThrow(fn (IaNoDisponible $e) => expect($e->motivo)->toBe(IaNoDisponible::NO_CONFIGURADA));

    RedactorResumenSemanal::assertNeverPrompted();
    expect(IaUso::query()->where('estado', 'NO_CONFIGURADA')->count())->toBe(1)
        ->and($this->ia->estado('resumen_semanal')['disponible'])->toBeFalse();
});

test('con el interruptor apagado no se llama al proveedor', function () {
    config(['ia.habilitada' => false, 'ai.providers.gemini.key' => 'clave']);
    RedactorResumenSemanal::fake();

    expect(fn () => $this->ia->ejecutar(new RedactorResumenSemanal, 'hechos', 'resumen_semanal'))
        ->toThrow(fn (IaNoDisponible $e) => expect($e->motivo)->toBe(IaNoDisponible::DESHABILITADA));

    RedactorResumenSemanal::assertNeverPrompted();
});

test('alcanzado el tope mensual se corta, y lo gastado el mes pasado no cuenta', function () {
    config(['ai.providers.gemini.key' => 'clave']);
    RedactorResumenSemanal::fake([resumenFalso()]);

    IaUso::factory()->create(['costo_usd' => 15, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3)]);
    expect($this->ia->presupuestoAgotado())->toBeFalse();

    IaUso::factory()->create(['costo_usd' => 20]);

    expect(fn () => $this->ia->ejecutar(new RedactorResumenSemanal, 'hechos', 'resumen_semanal'))
        ->toThrow(fn (IaNoDisponible $e) => expect($e->motivo)->toBe(IaNoDisponible::SIN_PRESUPUESTO));

    RedactorResumenSemanal::assertNeverPrompted();
    expect($this->ia->estado('resumen_semanal'))
        ->disponible->toBeFalse()
        ->cerca_del_tope->toBeTrue();
});

test('una llamada exitosa registra proveedor, modelo y tokens', function () {
    config(['ai.providers.gemini.key' => 'clave']);
    RedactorResumenSemanal::fake([resumenFalso()]);

    $respuesta = $this->ia->ejecutar(new RedactorResumenSemanal, 'hechos', 'resumen_semanal');

    expect($respuesta)->toBeInstanceOf(StructuredAgentResponse::class)
        ->and($respuesta->structured['titular'])->toBe('Semana estable.');

    $uso = IaUso::query()->sole();
    expect($uso->estado)->toBe('OK')
        ->and($uso->funcionalidad)->toBe('resumen_semanal')
        ->and($uso->proveedor)->toBe('gemini')
        ->and($uso->modelo)->toBe('gemini-3.8-flash');
});

test('el costo separa entrada, caché y salida con los precios configurados', function () {
    // 1 M de entrada + 1 M de salida en gemini-3.8-flash = 0,75 + 3,75.
    expect($this->ia->costo('gemini-3.8-flash', new TextUsage(1_000_000, 1_000_000)))->toBe(4.5)
        // Medio millón leído de caché: 0,375 + 0,0375 + 3,75.
        ->and($this->ia->costo('gemini-3.8-flash', new TextUsage(1_000_000, 1_000_000, cacheReadInputTokens: 500_000)))->toBe(4.1625)
        // Un modelo desconocido se presupuesta caro a propósito.
        ->and($this->ia->costo('modelo-nuevo', new TextUsage(1_000_000, 0)))->toBe(4.0);
});

test('dentro del bloque de solo lectura no se puede escribir, y después sí', function () {
    $area = Area::factory()->create(['nombre' => 'Diseño']);

    expect(fn () => ConsultaSoloLectura::ejecutar(null, fn () => DB::table('area')->where('id', $area->id)->update(['nombre' => 'X'])))
        ->toThrow(RuntimeException::class, 'solo puede leer')
        ->and(ConsultaSoloLectura::ejecutar(null, fn () => Area::query()->count()))->toBe(1)
        ->and(ConsultaSoloLectura::activa())->toBeFalse();

    DB::table('area')->where('id', $area->id)->update(['nombre' => 'Acabado']);
    expect($area->fresh()->nombre)->toBe('Acabado');
});
