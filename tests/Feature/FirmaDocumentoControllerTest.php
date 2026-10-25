<?php

use App\Models\FirmaDocumento;
use App\Models\NotaEntrega;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Pdf\GeneradorPdf;
use Spatie\Permission\Models\Permission;

/*
 * Quién firma por la empresa en la orden de trabajo, la nota de entrega y la
 * cotización. Pedido del usuario: hoy firma todo el propietario, pero tiene
 * que poder cambiarse cuando haya responsable de producción.
 */

beforeEach(function () {
    collect(['firmas-documento.ver', 'firmas-documento.editar'])
        ->each(fn (string $permiso) => Permission::findOrCreate($permiso, 'web'));
});

function usuarioConFirmas(string ...$permisos): User
{
    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * @return array<string, array{nombre: string, cargo: string}>
 */
function firmasValidas(array $cambios = []): array
{
    return array_replace_recursive(
        collect(FirmaDocumento::FIRMAS)->map(fn () => ['nombre' => 'Guillermo Apaza Apaza', 'cargo' => 'Propietario'])->all(),
        $cambios,
    );
}

function textoPdf(string $contenido): string
{
    preg_match_all('/\((.*?)\) Tj/s', $contenido, $trozos);

    return mb_convert_encoding(implode(' ', $trozos[1]), 'UTF-8', 'Windows-1252');
}

test('sin cambios, todas las firmas son del propietario', function () {
    $firmas = FirmaDocumento::vigentes();

    expect($firmas)->toHaveKeys(array_keys(FirmaDocumento::FIRMAS));

    foreach ($firmas as $firma) {
        expect($firma['nombre'])->toBe(config('sitio.empresa.propietario'))
            ->and($firma['cargo'])->toBe('Propietario');
    }
});

test('la pantalla exige el permiso de ver', function () {
    $this->get(route('firmas-documento.index'))->assertRedirect(route('login'));

    $this->actingAs(usuarioConFirmas())->get(route('firmas-documento.index'))->assertForbidden();

    $this->actingAs(usuarioConFirmas('firmas-documento.ver'))
        ->get(route('firmas-documento.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('FirmasDocumento/Index')
            ->where('firmas.orden_trabajo_produccion.nombre', config('sitio.empresa.propietario'))
            ->where('firmas.nota_entrega.documento', 'Nota de entrega'));
});

test('ver no alcanza para cambiar las firmas', function () {
    $this->actingAs(usuarioConFirmas('firmas-documento.ver'))
        ->put(route('firmas-documento.update'), ['firmas' => firmasValidas()])
        ->assertForbidden();

    expect(FirmaDocumento::count())->toBe(0);
});

test('se cambia el responsable de produccion y el PDF lo usa', function () {
    $this->actingAs(usuarioConFirmas('firmas-documento.ver', 'firmas-documento.editar'))
        ->put(route('firmas-documento.update'), ['firmas' => firmasValidas([
            'orden_trabajo_produccion' => ['nombre' => '  Juan   Mamani  ', 'cargo' => 'Jefe de producción'],
        ])])
        ->assertRedirect(route('firmas-documento.index'))
        ->assertSessionHas('success');

    expect(FirmaDocumento::where('clave', 'orden_trabajo_produccion')->first())
        ->nombre->toBe('Juan Mamani')
        ->cargo->toBe('Jefe de producción');

    $texto = textoPdf(app(GeneradorPdf::class)->pedido(Pedido::factory()->create())->sinComprimir()->contenido());

    // Dos personas distintas: dos firmas.
    expect($texto)->toContain('Juan Mamani')
        ->toContain('Jefe de producción · Recibe la orden')
        ->toContain('Guillermo Apaza Apaza')
        ->toContain('Propietario · Aprueba antes de entregar');
});

test('la validacion pide nombre y cargo y rechaza firmas inventadas', function () {
    $usuario = usuarioConFirmas('firmas-documento.ver', 'firmas-documento.editar');

    $this->actingAs($usuario)
        ->put(route('firmas-documento.update'), ['firmas' => firmasValidas(['nota_entrega' => ['nombre' => '', 'cargo' => '']])])
        ->assertSessionHasErrors(['firmas.nota_entrega.nombre', 'firmas.nota_entrega.cargo']);

    $this->actingAs($usuario)
        ->put(route('firmas-documento.update'), ['firmas' => [...firmasValidas(), 'gerente_general' => ['nombre' => 'X', 'cargo' => 'Y']]])
        ->assertSessionHasErrors('firmas');

    expect(FirmaDocumento::count())->toBe(0);
});

test('orden de trabajo y nota de entrega las firma el propietario por defecto', function () {
    $orden = textoPdf(app(GeneradorPdf::class)->pedido(Pedido::factory()->create())->sinComprimir()->contenido());
    $nota = textoPdf(app(GeneradorPdf::class)->notaEntrega(NotaEntrega::factory()->create())->sinComprimir()->contenido());

    // Misma persona en las dos líneas de la orden: una sola firma.
    expect(substr_count($orden, 'Guillermo Apaza Apaza'))->toBe(1)
        ->and($orden)->toContain('Propietario · Recibe la orden y aprueba antes de entregar')
        ->and($nota)->toContain('Guillermo Apaza Apaza')
        ->and($nota)->toContain('Propietario · '.config('sitio.empresa.nombre'))
        ->and($nota)->toContain('Firma y sello');
});
