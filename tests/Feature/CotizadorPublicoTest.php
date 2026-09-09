<?php

use App\Http\Requests\Cotizador\GuardarEstimacionRequest;
use App\Models\CotizacionPublica;
use App\Models\Formula;
use App\Models\Material;
use App\Models\Producto;
use App\Models\ProductoMaterial;
use App\Models\TipoProyecto;
use App\Services\Calculo\MotorMargenService;
use App\Services\Pdf\GeneradorPdf;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cotizador público (`/cotizador`).
 *
 * Es el único punto del sistema donde un desconocido, sin autenticarse, hace
 * que el servidor calcule y escriba. Buena parte de estos tests no comprueba
 * que "funcione" sino que NO se pueda abusar de él: que no se coticen
 * productos fuera de la lista blanca, que no se pueda dictar el precio desde
 * el navegador y que la respuesta pública no lleve información de
 * rentabilidad de la empresa.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Los rate limiters comparten el cache entre tests del mismo proceso: sin
    // esto, el sexto test que postea al cotizador se come el 429 del anterior.
    RateLimiter::clear('cotizador-guardar');
    RateLimiter::clear('cotizador-calcular');
    RateLimiter::clear('cotizador-descargar');
});

/**
 * Un producto realmente cotizable en la web: marcado, activo y con receta.
 * El material cuesta Bs 50 el m² y la receta consume 1 m² por m² de producto,
 * así que el costo de una pieza de 2 × 1 m es exactamente Bs 100.
 */
function productoWeb(array $atributos = []): Producto
{
    $producto = Producto::factory()->cotizableWeb()->create([
        'nombre' => 'Banner de prueba',
        'unidad_medida' => 'M2',
        'requiere_medidas' => 'SI',
        ...$atributos,
    ]);

    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'formula_id' => null,
        'cantidad_por_unidad' => 1,
        'material_id' => Material::factory()->create([
            'unidad_medida' => 'M2',
            'precio_unitario' => 50,
            'redondeo_compra' => null,
        ])->id,
    ]);

    return $producto->fresh();
}

/**
 * Abre el formulario y espera el tiempo mínimo: es lo que hace una persona, y
 * lo que `GuardarEstimacionRequest` exige antes de aceptar un envío.
 */
function abrirCotizador(): void
{
    test()->get(route('cotizador'))->assertOk();

    session([
        GuardarEstimacionRequest::SESION_ABIERTO_EN => time() - config('cotizador.segundos_minimos') - 1,
    ]);
}

/*
|--------------------------------------------------------------------------
| La página
|--------------------------------------------------------------------------
*/

test('el cotizador se ve sin iniciar sesion y lista solo los productos publicados', function () {
    $publicado = productoWeb(['nombre' => 'Banner publicado']);

    // Marcado pero SIN receta: no se puede costear, así que no se ofrece.
    Producto::factory()->cotizableWeb()->create(['nombre' => 'Sin receta todavia']);
    // Con receta pero sin marcar: es catálogo interno.
    productoWeb(['nombre' => 'Solo interno', 'cotizable_web' => 'NO']);

    $this->get(route('cotizador'))
        ->assertOk()
        ->assertSee($publicado->nombre)
        ->assertDontSee('Sin receta todavia')
        ->assertDontSee('Solo interno');
});

test('el cotizador declara las etiquetas que necesita un buscador', function () {
    productoWeb();

    $html = $this->get(route('cotizador'))->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="'.route('cotizador').'"')
        ->toContain('<meta name="description"')
        ->toContain('"@type":"WebApplication"')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

test('el cotizador carga su propio JS y no el bundle del panel', function () {
    productoWeb();

    $html = $this->get(route('cotizador'))->assertOk()->getContent();

    $cargaJsPublico = str_contains($html, 'resources/js/publico.js')
        || preg_match('#/build/assets/publico-[^"]+\.js#', $html) === 1;

    expect($cargaJsPublico)->toBeTrue('El cotizador debe cargar publico.js')
        ->and($html)->not->toContain('resources/js/app.js')
        ->and(preg_match('#/build/assets/app-[^"]+\.(css|js)#', $html))->toBe(0);
});

test('sin productos publicados el cotizador no muestra un formulario roto', function () {
    $this->get(route('cotizador'))
        ->assertOk()
        ->assertSee('El cotizador está en mantenimiento')
        // Sin catálogo no hay nada que postear.
        ->assertDontSee('data-cotizador', false);
});

/*
|--------------------------------------------------------------------------
| Cálculo
|--------------------------------------------------------------------------
*/

test('el precio sale del mismo motor que usa ventas, no de una formula aparte', function () {
    $producto = productoWeb();

    // Nivel de complejidad único y conocido, para poder rehacer la cuenta.
    TipoProyecto::factory()->create([
        'nombre' => 'Básico',
        'factor_complejidad' => 1.2,
        'margen_minimo' => 0.5,
        'orden' => 1,
        'estado' => 'ACTIVO',
    ]);
    config(['cotizador.tipo_proyecto' => 'Básico']);

    $respuesta = $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 3]],
    ])->assertOk();

    // 2 m² × Bs 50 = Bs 100 de material para UNA unidad; el motor decide el
    // precio a partir de ahí. Se compara contra el motor real: si algún día
    // el cotizador público empieza a calcular por su cuenta, esto se cae.
    $esperado = app(MotorMargenService::class)->calcular(100.0, 1.2, 0.5);

    // `toEqual` y no `toBe`: un monto redondo viaja como int en el JSON
    // (180, no 180.0) y la comparación estricta fallaría por el tipo.
    expect($respuesta->json('lineas.0.precio_unitario'))->toEqual(round($esperado->precio, 2))
        ->and($respuesta->json('subtotal'))->toEqual(round($esperado->precio * 3, 2))
        ->and($respuesta->json('iva'))->toEqual(round($respuesta->json('subtotal') * config('margen.impuestos.iva'), 2))
        ->and($respuesta->json('total'))->toEqual(round($respuesta->json('subtotal') + $respuesta->json('iva'), 2));
});

test('la respuesta publica no filtra costos ni rentabilidad', function () {
    // El semáforo, el margen y el costo son información interna de la empresa
    // (ver Cotizacion::ESTADOS_MARGEN). Que salgan por un endpoint sin login
    // sería regalarle la estructura de costos a la competencia.
    $producto = productoWeb();

    $cuerpo = $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertOk()->getContent();

    foreach (['costo_base', 'costo_ajustado', 'margen', 'factor_complejidad', 'utilidad', 'estado_margen', 'recomendacion', '"it"', '"iue"', 'VERDE', 'AMARILLO'] as $prohibido) {
        expect($cuerpo)->not->toContain($prohibido);
    }
});

test('el rango aproximado se abre segun la holgura configurada', function () {
    $producto = productoWeb();
    config(['cotizador.holgura' => 0.2]);

    $datos = $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertOk()->json();

    expect($datos['estimado_min'])->toEqual(round($datos['total'] * 0.8, 2))
        ->and($datos['estimado_max'])->toEqual(round($datos['total'] * 1.2, 2));
});

test('la vigencia sale del parametro y viaja con la estimacion', function () {
    $producto = productoWeb();
    config(['cotizador.vigencia_dias' => 21]);

    $datos = $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertOk()->json();

    expect($datos['vigencia_dias'])->toBe(21)
        ->and($datos['fecha_vencimiento'])->toBe(today()->addDays(21)->toDateString());
});

test('una receta que necesita datos que el cotizador no pide no revienta en la cara del visitante', function () {
    // "Perímetro con profundidad" es el caso real del catálogo: el formulario
    // público solo pregunta ancho y alto.
    $producto = Producto::factory()->cotizableWeb()->create(['unidad_medida' => 'UNIDAD', 'requiere_medidas' => 'SI']);

    ProductoMaterial::factory()->create([
        'producto_id' => $producto->id,
        'material_id' => Material::factory()->create(['precio_unitario' => 10])->id,
        'cantidad_por_unidad' => null,
        'formula_id' => Formula::factory()->create(['expresion' => '(ancho + alto) * 2 * profundo'])->id,
    ]);

    $respuesta = $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertStatus(422);

    // Mensaje para una persona, no la traza del motor de fórmulas.
    expect($respuesta->json('error'))
        ->toContain('Escríbenos')
        ->not->toContain('profundo');
});

/*
|--------------------------------------------------------------------------
| Defensas del endpoint público
|--------------------------------------------------------------------------
*/

test('no se puede cotizar un producto que no esta publicado', function () {
    $interno = productoWeb(['cotizable_web' => 'NO']);

    $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $interno->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('lineas.0.producto_id');
});

test('no se puede cotizar un producto inactivo aunque este marcado', function () {
    $producto = productoWeb();
    $producto->update(['estado' => 'INACTIVO']);

    $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('lineas.0.producto_id');
});

test('el precio que manda el navegador se ignora por completo', function () {
    // El ataque obvio: postear el detalle con el precio ya puesto en 1 Bs.
    $producto = productoWeb();
    abrirCotizador();

    $this->post(route('cotizador.store'), [
        'nombre' => 'Persona Curiosa',
        'telefono' => '76578910',
        'lineas' => [[
            'producto_id' => $producto->id,
            'ancho' => 2,
            'alto' => 1,
            'cantidad' => 1,
            'precio_unitario' => 1,
            'subtotal' => 1,
        ]],
    ])->assertRedirect();

    $estimacion = CotizacionPublica::sole();

    expect((float) $estimacion->total)->toBeGreaterThan(100.0)
        ->and((float) $estimacion->detalle[0]['precio_unitario'])->toBeGreaterThan(1.0);
});

test('se rechazan mas lineas de las permitidas', function () {
    $producto = productoWeb();

    $lineas = array_fill(0, config('cotizador.limites.lineas') + 1, [
        'producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1,
    ]);

    $this->postJson(route('cotizador.calcular'), ['lineas' => $lineas])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lineas');
});

test('se rechazan medidas y cantidades fuera de los topes', function () {
    $producto = productoWeb();

    $this->postJson(route('cotizador.calcular'), [
        'lineas' => [[
            'producto_id' => $producto->id,
            'ancho' => config('cotizador.limites.dimension') + 1,
            'alto' => 1,
            'cantidad' => config('cotizador.limites.cantidad') + 1,
        ]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lineas.0.ancho', 'lineas.0.cantidad']);
});

test('un producto que se cotiza por medidas las exige', function () {
    $producto = productoWeb();

    $this->postJson(route('cotizador.calcular'), [
        'lineas' => [['producto_id' => $producto->id, 'cantidad' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lineas.0.ancho', 'lineas.0.alto']);
});

test('el honeypot descarta el envio de un bot', function () {
    $producto = productoWeb();
    abrirCotizador();

    $this->post(route('cotizador.store'), [
        'nombre' => 'Bot',
        'telefono' => '76578910',
        // Campo invisible para una persona: solo lo llena un script.
        'sitio_web' => 'https://spam.example',
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1]],
    ])->assertSessionHasErrors('sitio_web');

    expect(CotizacionPublica::count())->toBe(0);
});

test('un POST directo sin haber abierto el formulario se descarta', function () {
    // Sin la marca de tiempo en sesión no hay forma de haber pasado por la
    // página: es exactamente lo que hace un script.
    $producto = productoWeb();

    $this->post(route('cotizador.store'), [
        'nombre' => 'Script',
        'telefono' => '76578910',
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1]],
    ])->assertSessionHasErrors('nombre');

    expect(CotizacionPublica::count())->toBe(0);
});

test('un envio demasiado rapido se descarta', function () {
    $producto = productoWeb();

    $this->get(route('cotizador'))->assertOk();
    session([GuardarEstimacionRequest::SESION_ABIERTO_EN => time()]);

    $this->post(route('cotizador.store'), [
        'nombre' => 'Apurado',
        'telefono' => '76578910',
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1]],
    ])->assertSessionHasErrors('nombre');

    expect(CotizacionPublica::count())->toBe(0);
});

test('el rate limiter corta la insistencia sobre el guardado', function () {
    $producto = productoWeb();
    $intentos = (int) config('cotizador.throttle.guardar');

    foreach (range(1, $intentos) as $numero) {
        abrirCotizador();

        $this->post(route('cotizador.store'), [
            'nombre' => "Persona {$numero}",
            'telefono' => '76578910',
            'lineas' => [['producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1]],
        ])->assertRedirect();
    }

    abrirCotizador();

    $this->post(route('cotizador.store'), [
        'nombre' => 'Uno más',
        'telefono' => '76578910',
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 1, 'alto' => 1, 'cantidad' => 1]],
    ])->assertStatus(429);

    expect(CotizacionPublica::count())->toBe($intentos);
});

/*
|--------------------------------------------------------------------------
| Guardado y consulta por código
|--------------------------------------------------------------------------
*/

test('guardar la estimacion genera un codigo y redirige a su pagina', function () {
    $producto = productoWeb();
    abrirCotizador();

    $respuesta = $this->post(route('cotizador.store'), [
        'nombre' => 'Ana Quispe',
        'empresa' => 'Marca S.A.',
        'telefono' => '765 78910',
        'email' => 'ana@example.com',
        'mensaje' => 'Lo necesito para el lunes.',
        'lineas' => [['producto_id' => $producto->id, 'ancho' => 2, 'alto' => 1, 'cantidad' => 2]],
    ]);

    $estimacion = CotizacionPublica::sole();

    expect($estimacion->codigo)->toStartWith('WEB-')
        ->and($estimacion->estado)->toBe('NUEVA')
        ->and($estimacion->nombre)->toBe('Ana Quispe')
        ->and($estimacion->detalle)->toHaveCount(1)
        // La vigencia se congela en la fila: cambiar el parámetro después no
        // puede acortar una estimación ya entregada.
        ->and($estimacion->vigencia_dias)->toBe((int) config('cotizador.vigencia_dias'))
        ->and($estimacion->fecha_vencimiento->toDateString())
        ->toBe(today()->addDays((int) config('cotizador.vigencia_dias'))->toDateString());

    $respuesta->assertRedirect(route('cotizador.show', $estimacion->codigo));
});

test('la estimacion guardada se consulta por su codigo y muestra su vigencia', function () {
    $estimacion = CotizacionPublica::factory()->create();

    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertSee($estimacion->codigo)
        ->assertSee('Vigente')
        ->assertSee($estimacion->nombre);
});

test('una estimacion vencida se muestra igual pero avisa que vencio', function () {
    // No se esconde: el visitante tiene derecho a ver qué pidió, y el código
    // le sigue sirviendo para que un asesor lo recupere.
    $estimacion = CotizacionPublica::factory()->vencida()->create();

    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertSee('Vencida')
        ->assertSee('venció el');
});

test('un codigo inexistente devuelve 404', function () {
    $this->get(route('cotizador.show', 'WEB-20260101-AAAAA'))->assertNotFound();
});

test('la pagina de una estimacion pide no ser indexada', function () {
    // Lleva el nombre y el teléfono de una persona: no es contenido público.
    $estimacion = CotizacionPublica::factory()->create();

    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

/*
|--------------------------------------------------------------------------
| Integración con el resto del sitio público
|--------------------------------------------------------------------------
*/

test('el sitemap publica el cotizador y robots.txt bloquea las estimaciones', function () {
    app()->detectEnvironment(fn () => 'production');

    $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
    $urls = array_map(fn ($url): string => (string) $url->loc, iterator_to_array($xml->url));

    expect($urls)->toContain(route('cotizador'));

    // El formulario sí se indexa; las estimaciones emitidas, no.
    expect($this->get('/robots.txt')->assertOk()->getContent())
        ->toContain('Disallow: /cotizador/');
});

/*
|--------------------------------------------------------------------------
| Documento imprimible
|--------------------------------------------------------------------------
*/

test('el documento se entrega como PDF con la fecha de emision y los datos', function () {
    $estimacion = CotizacionPublica::factory()->create([
        'nombre' => 'Ana Quispe',
        'empresa' => 'Marca S.A.',
        'mensaje' => 'Lo necesito para el lunes.',
    ]);

    $respuesta = $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();

    expect($respuesta->headers->get('content-type'))->toBe('application/pdf');

    // El texto se lee del PDF de verdad (ver GeneradorPdfTest::textoDelPdf).
    $texto = textoDelPdf(app(GeneradorPdf::class)->cotizacionPublica($estimacion->fresh()));

    expect($texto)
        ->toContain($estimacion->codigo)
        ->toContain('ESTIMACIÓN REFERENCIAL')
        // Fecha de emisión y vigencia: lo que hace de esto un documento.
        ->toContain('Fecha de emisión')
        ->toContain($estimacion->created_at->translatedFormat('d \d\e F \d\e Y'))
        ->toContain($estimacion->fecha_vencimiento->translatedFormat('d \d\e F \d\e Y'))
        // Datos de quien lo pidió y lo que pidió.
        ->toContain('Ana Quispe')
        ->toContain('Marca S.A.')
        ->toContain('Lo necesito para el lunes.')
        // Y los datos de la empresa que emite.
        ->toContain(config('sitio.empresa.direccion'));
});

test('el documento se descarga con un nombre de archivo reconocible', function () {
    $estimacion = CotizacionPublica::factory()->create();

    $respuesta = $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();

    // Al visitante se le BAJA el archivo (no vista previa como en el panel):
    // el código es su único hilo para volver a contactarnos, y un PDF abierto
    // en una pestaña se pierde al cerrarla.
    expect($respuesta->headers->get('content-disposition'))->toBe(
        'attachment; filename="xtrapubli-estimacion-'.strtolower($estimacion->codigo).'.pdf"',
    );
});

test('descargar el documento se anota en la estimacion', function () {
    $estimacion = CotizacionPublica::factory()->create();

    expect($estimacion->descargado_en)->toBeNull();

    $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();

    $estimacion->refresh();

    expect($estimacion->descargas)->toBe(1)
        ->and($estimacion->descargado_en)->not->toBeNull();
});

test('el boton de descargar desaparece una vez descargado el documento', function () {
    $estimacion = CotizacionPublica::factory()->create();

    // Antes: el botón es la acción principal de la página.
    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertSee('Descargar cotización en PDF');

    $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();

    // Después: se cambia por la constancia de cuándo se emitió.
    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertDontSee('Descargar cotización en PDF')
        ->assertSee('Documento ya descargado');
});

test('una estimacion no se puede reimprimir sin limite', function () {
    // El rate limiter por IP se renueva solo; sin un tope por FILA, un código
    // válido alcanza para pedir el documento indefinidamente.
    $estimacion = CotizacionPublica::factory()->create();
    $maximo = (int) config('cotizador.descargas_maximas');

    foreach (range(1, $maximo) as $intento) {
        $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();
    }

    $this->get(route('cotizador.documento', $estimacion->codigo))
        ->assertRedirect(route('cotizador.show', $estimacion->codigo))
        ->assertSessionHas('error');

    expect($estimacion->fresh()->descargas)->toBe($maximo);
});

test('agotado el tope la pagina deja de ofrecer el documento', function () {
    $estimacion = CotizacionPublica::factory()->create([
        'descargas' => config('cotizador.descargas_maximas'),
        'descargado_en' => now(),
    ]);

    $this->get(route('cotizador.show', $estimacion->codigo))
        ->assertOk()
        ->assertDontSee('Descargar cotización en PDF')
        ->assertDontSee('Ábrelo de nuevo')
        ->assertSee('te lo reenviamos');
});

test('el documento de un codigo inexistente devuelve 404', function () {
    $this->get(route('cotizador.documento', 'WEB-20260101-AAAAA'))->assertNotFound();
});

test('el rate limiter corta la insistencia sobre la descarga', function () {
    // Emitir el documento arma un presupuesto entero: es caro, y el tope por
    // fila no protege a quien tenga muchos códigos válidos.
    $intentos = (int) config('cotizador.throttle.descargar');

    foreach (range(1, $intentos) as $numero) {
        $estimacion = CotizacionPublica::factory()->create();

        $this->get(route('cotizador.documento', $estimacion->codigo))->assertOk();
    }

    $ultima = CotizacionPublica::factory()->create();

    $this->get(route('cotizador.documento', $ultima->codigo))->assertStatus(429);
});

test('guardar una solicitud esta protegido en tres capas, no solo por IP', function () {
    // Un límite por IP no frena una avalancha repartida entre cientos de
    // direcciones, que es justo lo que ahogaría la bandeja de ventas. Por eso
    // conviven: por hora y por día contra una IP insistente, y uno global.
    // Se inspecciona la definición en vez de disparar cientos de peticiones.
    $limites = (RateLimiter::limiter('cotizador-guardar'))(Request::create('/cotizador', 'POST'));

    expect($limites)->toHaveCount(3);

    $porClave = collect($limites)->keyBy(fn (Limit $limite): string => (string) $limite->key);

    expect($porClave->keys())->each->toBeString()
        ->and($porClave->get('global'))->not->toBeNull('Falta el techo global del sitio')
        ->and($porClave->get('global')->maxAttempts)->toBe((int) config('cotizador.throttle.guardar_global'))
        ->and($porClave->get('global')->decaySeconds)->toBe(3600);

    // Las tres claves son distintas: con la misma se pisarían el contador.
    expect($porClave)->toHaveCount(3);

    $porVentana = collect($limites)->keyBy(fn (Limit $limite): int => $limite->decaySeconds);

    expect($porVentana->get(86400)?->maxAttempts)->toBe((int) config('cotizador.throttle.guardar_dia'));
});

test('descargar el documento tambien tiene su propio limite por hora', function () {
    $limite = (RateLimiter::limiter('cotizador-descargar'))(Request::create('/cotizador/x/documento'));

    expect($limite->maxAttempts)->toBe((int) config('cotizador.throttle.descargar'))
        ->and($limite->decaySeconds)->toBe(3600);
});
