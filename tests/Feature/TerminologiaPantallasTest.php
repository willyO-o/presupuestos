<?php

use Illuminate\Support\Facades\File;

/**
 * Lo que el usuario lee no dice "receta" ni "BOM": la lista de materiales que
 * lleva un producto se llama "Materiales del producto" (pedido del usuario;
 * "receta" sonaba a cocina y "BOM" es jerga). Tampoco "materiales
 * utilizados": eso es el consumo real que se registra en Pedidos.
 *
 * Ni "ticket", que tampoco se entendía: lo que vale cada presupuesto es el
 * "precio promedio" (o "monto promedio") y lo que deja cada venta, la "venta
 * promedio".
 *
 * Solo el texto visible: los comentarios y los nombres internos (la ruta
 * `productos.materiales.*`, el componente `Productos/Receta`, el modelo
 * `ProductoMaterial`, las claves `ticket`/`ticket_promedio`) se quedan como
 * están.
 */
const TERMINOS_RETIRADOS = '/\breceta\b|\bBOM\b|\bticket\b/iu';

/**
 * En el código JS "ticket" sigue siendo clave (`tendencia.ticket`,
 * `ticket: {...}`, `kpi-ticket`): cuenta solo suelta, como palabra de un texto.
 */
const TERMINOS_RETIRADOS_JS = '/\breceta\b|\bBOM\b|(?<![\w.\-])ticket(?![\w\-]|:\s*\{)/iu';

/** Nombres internos que contienen la palabra pero no se ven en pantalla. */
const NOMBRES_INTERNOS = ['Productos/Receta'];

/**
 * El texto de un .vue o .js sin sus comentarios.
 */
function sinComentariosJs(string $fuente): string
{
    $fuente = preg_replace('/<!--.*?-->/s', '', $fuente);
    $fuente = preg_replace('#/\*.*?\*/#s', '', $fuente);

    // `//` de comentario, no el de una URL (`https://`).
    return preg_replace('#(?<![:"\'])//.*$#m', '', $fuente);
}

/**
 * Las cadenas literales de un archivo PHP (lo que puede llegar a la pantalla:
 * avisos, títulos, migas, etiquetas de permisos), sin los comentarios.
 *
 * @return list<string>
 */
function cadenasPhp(string $ruta): array
{
    return collect(token_get_all(File::get($ruta)))
        ->filter(fn ($token): bool => is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true))
        ->map(fn (array $token): string => trim($token[1], '\'"'))
        ->reject(fn (string $cadena): bool => in_array($cadena, NOMBRES_INTERNOS, true))
        ->values()
        ->all();
}

test('las pantallas no dicen receta, BOM ni ticket', function () {
    $archivos = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($archivo): bool => in_array($archivo->getExtension(), ['vue', 'js'], true));

    $conTermino = $archivos
        ->filter(fn ($archivo): bool => preg_match(TERMINOS_RETIRADOS_JS, sinComentariosJs($archivo->getContents())) === 1)
        ->map(fn ($archivo): string => str_replace('\\', '/', $archivo->getRelativePathname()))
        ->values()
        ->all();

    expect($archivos)->not->toBeEmpty()
        ->and($conTermino)->toBe([]);
});

test('los avisos, titulos y permisos tampoco', function () {
    $rutas = [
        ...collect(File::allFiles(app_path('Http')))->map->getPathname()->all(),
        // Reportes, BI y asistente: rótulos de indicadores y frases armadas.
        ...collect(File::allFiles(app_path('Services')))->map->getPathname()->all(),
        ...collect(File::allFiles(app_path('Ai')))->map->getPathname()->all(),
        config_path('acl.php'),
    ];

    $conTermino = collect($rutas)
        ->flatMap(fn (string $ruta): array => collect(cadenasPhp($ruta))
            // Con un espacio adentro es un texto; sin él, una clave (`ticket_promedio`).
            ->filter(fn (string $cadena): bool => str_contains($cadena, ' ') && preg_match(TERMINOS_RETIRADOS, $cadena) === 1)
            ->map(fn (string $cadena): string => basename($ruta).': '.$cadena)
            ->all())
        ->values()
        ->all();

    expect($conTermino)->toBe([]);
});

test('el permiso de editar productos nombra los materiales', function () {
    // La clave lleva punto: no se puede leer con la notación de config().
    expect(config('acl.modules.productos.permissions')['productos.editar'])->toBe('Editar productos (incluye sus materiales)');
});
