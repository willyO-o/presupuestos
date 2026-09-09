<?php

use App\Models\User;

/**
 * Páginas de error del sitio público (`resources/views/errors/`).
 *
 * Antes de que existieran, cualquier `abort()` devolvía la pantalla gris por
 * defecto de Laravel: sin logo, sin menú y sin ninguna forma de volver. Estos
 * tests fijan lo que importa de verdad — que el visitante siga dentro del
 * sitio y que el buscador no indexe una página muerta — y no el texto exacto,
 * que es editorial y va a cambiar.
 */
test('un 404 se ve con el layout del sitio y ofrece por donde seguir', function () {
    $respuesta = $this->get('/esta-url-no-existe')->assertNotFound();

    $respuesta
        ->assertSee('No encontramos esta página')
        // La salida: cabecera, pie y enlaces del sitio, no una pantalla muerta.
        ->assertSee(route('inicio'), false)
        ->assertSee(route('proyectos'), false)
        ->assertSee(config('sitio.empresa.nombre'));
});

test('una categoria inventada de la galeria cae en la pagina de error del sitio', function () {
    // Es el 404 que un visitante real se puede encontrar sin escribir una URL
    // a mano: un enlace viejo a ?categoria=algo que ya no existe.
    $this->get(route('proyectos').'?categoria=no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esta página');
});

test('las paginas de error piden no ser indexadas', function () {
    // Una página de error indexada compite en los resultados con las que sí
    // importan, y manda al visitante a una vía muerta.
    $this->get('/esta-url-no-existe')
        ->assertNotFound()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('un 403 explica que la pagina es privada sin filtrar el motivo', function () {
    $this->actingAs(User::factory()->create());

    // Un usuario autenticado sin el permiso `usuarios.ver`.
    $respuesta = $this->get(route('usuarios.index'))->assertForbidden();

    $respuesta->assertSee('Esta página es privada')
        // Nada de "Unauthenticated" ni el nombre del permiso que faltó.
        ->assertDontSee('usuarios.ver');
});

test('el 429 del cotizador tiene su propia pagina', function () {
    // El rate limiter del cotizador público devuelve 429; sin plantilla, el
    // visitante veía "Too Many Requests" en texto plano.
    $this->view('errors.429', ['exception' => null])
        ->assertSee('Demasiados intentos seguidos')
        ->assertSee('429');
});

test('el 500 no cuenta que fallo por dentro', function () {
    $this->view('errors.500', ['exception' => null])
        ->assertSee('Algo salió mal de nuestro lado')
        ->assertDontSee('Exception')
        ->assertDontSee('stack');
});
