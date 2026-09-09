<?php

use Illuminate\Support\Facades\File;

/**
 * Sitio público (portada y galería). Lo que se fija acá es, sobre todo, que el
 * contenido llegue en el HTML de la primera respuesta: si algún día alguien
 * convierte estas páginas a Inertia "por consistencia", estos tests se caen y
 * explican por qué no se debe.
 */

/*
|--------------------------------------------------------------------------
| Portada
|--------------------------------------------------------------------------
*/

test('la portada se ve sin iniciar sesion y trae el contenido en el HTML', function () {
    $this->get(route('inicio'))
        ->assertOk()
        ->assertSee(config('sitio.empresa.lema'))
        ->assertSee('¿Qué hacemos?')
        ->assertSee('¿Cómo lo hacemos?')
        ->assertSee('¿Para quién es?')
        // Los servicios salen del config, no están escritos en el Blade.
        ->assertSee(config('sitio.servicios.0.titulo'))
        ->assertSee(config('sitio.servicios.0.descripcion'));
});

test('la portada declara las etiquetas que necesita un buscador', function () {
    $html = $this->get(route('inicio'))->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="es"')
        ->toContain('| XtraPubli</title>')
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical" href="'.route('inicio').'"')
        ->toContain('property="og:image"')
        ->toContain('name="twitter:card"')
        // Un solo <h1> por página: el buscador lo lee como el tema de la página.
        ->and(substr_count($html, '<h1'))->toBe(1);
});

test('la portada publica los datos de la empresa como JSON-LD', function () {
    $html = $this->get(route('inicio'))->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $coincidencias);

    $datos = json_decode(trim($coincidencias[1] ?? ''), true);

    expect($datos)->not->toBeNull('El JSON-LD tiene que ser JSON válido')
        ->and($datos['@graph'][0]['@type'])->toBe('LocalBusiness')
        ->and($datos['@graph'][0]['telephone'])->toBe(config('sitio.empresa.telefono'))
        ->and($datos['@graph'][0]['address']['addressLocality'])->toBe(config('sitio.empresa.ciudad'))
        // Los cuatro servicios viajan como catálogo de ofertas.
        ->and($datos['@graph'][0]['hasOfferCatalog']['itemListElement'])->toHaveCount(4);
});

test('la portada muestra los destacados y empuja a la galeria completa', function () {
    $destacados = config('sitio.destacados');

    $respuesta = $this->get(route('inicio'))->assertOk();

    foreach ($destacados as $slug) {
        $respuesta->assertSee("img/publico/proyectos/{$slug}.jpg", false);
    }

    $respuesta
        ->assertSee(route('proyectos'), false)
        ->assertSee('Ver los '.count(config('sitio.proyectos')).' proyectos');
});

/*
|--------------------------------------------------------------------------
| Galería
|--------------------------------------------------------------------------
*/

test('la galeria lista todos los proyectos con su imagen', function () {
    $proyectos = config('sitio.proyectos');

    $respuesta = $this->get(route('proyectos'))->assertOk();

    foreach ($proyectos as $proyecto) {
        $respuesta
            // Sin `false`: el título de KRIS lleva comillas y Blade las escapa.
            ->assertSee($proyecto['titulo'])
            ->assertSee("img/publico/proyectos/{$proyecto['slug']}.jpg", false);
    }
});

test('el filtro por categoria recorta la galeria', function () {
    $vehiculares = collect(config('sitio.proyectos'))->where('categoria', 'vehicular');

    $respuesta = $this->get(route('proyectos', ['categoria' => 'vehicular']))
        ->assertOk()
        ->assertSee(config('sitio.categorias.vehicular.nombre'));

    foreach ($vehiculares as $proyecto) {
        $respuesta->assertSee($proyecto['titulo']);
    }

    // Y no arrastra los de otra categoría.
    $ajeno = collect(config('sitio.proyectos'))->firstWhere('categoria', 'fachadas');
    $respuesta->assertDontSee($ajeno['titulo']);
});

test('una categoria inexistente devuelve 404 y no la galeria completa', function () {
    $this->get(route('proyectos').'?categoria=no-existe')->assertNotFound();

    // Tampoco se acepta un array: ?categoria[]=x no debe pasar por is_string().
    $this->get(route('proyectos').'?categoria[]=vehicular')->assertNotFound();
});

test('las vistas filtradas apuntan su canonica a la galeria sin filtro', function () {
    // Son el mismo catálogo recortado; sin esto Google las trata como cinco
    // páginas distintas con el mismo contenido y reparte el posicionamiento.
    $this->get(route('proyectos', ['categoria' => 'exhibidores']))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('proyectos').'">', false);
});

/*
|--------------------------------------------------------------------------
| Coherencia entre el config y los archivos en disco
|--------------------------------------------------------------------------
*/

test('cada proyecto tiene su imagen en disco', function () {
    $faltantes = collect(config('sitio.proyectos'))
        ->map(fn (array $proyecto) => $proyecto['slug'])
        ->reject(fn (string $slug) => File::exists(public_path("img/publico/proyectos/{$slug}.jpg")))
        ->all();

    expect($faltantes)->toBe([], 'Faltan imágenes en public/img/publico/proyectos/');
});

test('cada proyecto pertenece a una categoria declarada', function () {
    $categorias = array_keys(config('sitio.categorias'));

    $huerfanos = collect(config('sitio.proyectos'))
        ->reject(fn (array $proyecto) => in_array($proyecto['categoria'], $categorias, true))
        ->pluck('slug')
        ->all();

    expect($huerfanos)->toBe([]);
});

test('cada destacado de la portada existe en la galeria', function () {
    $slugs = collect(config('sitio.proyectos'))->pluck('slug')->all();

    expect(array_diff(config('sitio.destacados'), $slugs))->toBe([]);
});

test('cada icono referenciado en el config esta dibujado en el componente', function () {
    $componente = File::get(resource_path('views/components/publico/icono.blade.php'));

    $usados = collect(config('sitio.servicios'))
        ->concat(config('sitio.diferenciales'))
        ->pluck('icono');

    foreach ($usados as $icono) {
        expect($componente)->toContain("@case('{$icono}')");
    }
});

/*
|--------------------------------------------------------------------------
| Rendimiento y rastreo
|--------------------------------------------------------------------------
*/

test('el sitio publico no carga el bundle del panel', function () {
    // El CSS del dashboard pesa 30 veces más que el del sitio público y trae
    // tres fuentes de iconos que la landing no usa. Cargarlo hundiría el LCP,
    // que es justo la métrica con la que Google posiciona.
    $html = $this->get(route('inicio'))->assertOk()->getContent();

    // Con `npm run dev` el <link> apunta al archivo fuente y con `npm run
    // build` al del manifiesto: se acepta cualquiera de las dos formas.
    $cargaHojaPublica = str_contains($html, 'resources/css/publico.css')
        || preg_match('#/build/assets/publico-[^"]+\.css#', $html) === 1;

    expect($cargaHojaPublica)->toBeTrue('La portada debe cargar publico.css')
        ->and($html)->not->toContain('resources/js/app.js')
        ->and(preg_match('#/build/assets/app-[^"]+\.(css|js)#', $html))->toBe(0);
});

test('el sitemap es XML valido y lista solo las paginas canonicas', function () {
    // Las tres páginas públicas y nada más: las vistas filtradas de la galería
    // apuntan su canonical a /proyectos y las estimaciones ya emitidas no son
    // contenido público (ver CotizadorPublicoTest).
    $respuesta = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($respuesta->getContent());

    expect($xml)->not->toBeFalse('El sitemap tiene que ser XML válido')
        ->and($xml->url)->toHaveCount(3)
        ->and((string) $xml->url[0]->loc)->toBe(route('inicio'))
        ->and((string) $xml->url[1]->loc)->toBe(route('proyectos'))
        ->and((string) $xml->url[2]->loc)->toBe(route('cotizador'));
});

test('robots.txt bloquea el rastreo fuera de produccion', function () {
    $contenido = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->getContent();

    expect($contenido)->toContain('Disallow: /')
        ->not->toContain('Sitemap:');
});

test('robots.txt abre el sitio y publica el sitemap en produccion', function () {
    // Es la razón de servirlo por ruta en vez de dejarlo estático en public/.
    app()->detectEnvironment(fn () => 'production');

    $contenido = $this->get('/robots.txt')->assertOk()->getContent();

    expect($contenido)
        ->toContain('Allow: /')
        ->toContain('Sitemap: '.route('sitemap'))
        // El panel interno no tiene nada que hacer en un buscador.
        ->toContain('Disallow: /dashboard');
});
