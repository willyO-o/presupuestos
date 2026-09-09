<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Sitio público (portada y galería de proyectos).
 *
 * Estas dos páginas son Blade y no Inertia, a diferencia de todo el panel: son
 * las únicas que tienen que posicionar en Google, y un buscador indexa mucho
 * mejor un HTML que ya viene completo en la primera respuesta que uno que se
 * arma con JavaScript. Por eso tampoco cargan `app.js` ni el CSS del dashboard
 * (ver `resources/css/publico.css`).
 *
 * El contenido vive entero en `config/sitio.php`; acá solo se filtra, se
 * ordena y se arma el JSON-LD.
 */
class SitioPublicoController extends Controller
{
    /**
     * Portada: qué hacemos, cómo lo hacemos, para quién y una vitrina de
     * proyectos que empuja a la galería completa.
     */
    public function inicio(): View
    {
        $proyectos = collect(config('sitio.proyectos'));

        return view('publico.inicio', [
            'servicios' => config('sitio.servicios'),
            'diferenciales' => config('sitio.diferenciales'),
            'publicoObjetivo' => config('sitio.publico_objetivo'),
            'destacados' => $proyectos
                ->whereIn('slug', config('sitio.destacados', []))
                ->values()
                ->all(),
            'totalProyectos' => $proyectos->count(),
            'datosEstructurados' => $this->negocioJsonLd(),
        ]);
    }

    /**
     * Galería de proyectos, filtrable por categoría vía `?categoria=`.
     *
     * El filtro es un enlace de verdad y no un toggle de JavaScript para que
     * funcione sin JS y para que cada vista sea compartible; a cambio, todas
     * las variantes declaran la misma canónica (`/proyectos`) y quedan fuera
     * del sitemap, para no competir entre ellas por el mismo contenido.
     */
    public function proyectos(Request $request): View
    {
        $categorias = collect(config('sitio.categorias'));
        $filtro = $request->query('categoria');

        abort_unless(
            $filtro === null || (is_string($filtro) && $categorias->has($filtro)),
            404
        );

        $todos = collect(config('sitio.proyectos'));
        $proyectos = $filtro ? $todos->where('categoria', $filtro)->values() : $todos;

        $categoria = $filtro ? $categorias->get($filtro) : null;

        return view('publico.proyectos', [
            'proyectos' => $proyectos->all(),
            'categorias' => $this->categoriasConConteo($categorias, $todos),
            'filtro' => $filtro,
            'titulo' => $categoria ? $categoria['nombre'] : 'Proyectos y ejecuciones realizadas',
            'descripcion' => $categoria
                ? $categoria['descripcion']
                : 'Exhibidores, fachadas, rotulado vehicular y activaciones ejecutadas por '
                    .config('sitio.empresa.nombre').' para marcas líderes en Bolivia.',
            'datosEstructurados' => $this->galeriaJsonLd($proyectos),
        ]);
    }

    /**
     * Sitemap XML para Google Search Console.
     *
     * Solo las dos URLs canónicas: las vistas filtradas apuntan su canonical a
     * `/proyectos`, así que incluirlas sería pedirle a Google que indexe cinco
     * versiones del mismo contenido. `lastmod` sale de la fecha del archivo de
     * contenido, que es exactamente lo que cambia cuando se edita el sitio.
     */
    public function sitemap(): Response
    {
        $actualizado = date('Y-m-d', File::lastModified(config_path('sitio.php')));

        return response()
            ->view('publico.sitemap', [
                'urls' => [
                    ['loc' => route('inicio'), 'prioridad' => '1.0', 'frecuencia' => 'weekly'],
                    ['loc' => route('proyectos'), 'prioridad' => '0.9', 'frecuencia' => 'monthly'],
                    // El formulario del cotizador, no las estimaciones que
                    // genera: esas son de una sola persona y van bloqueadas
                    // en robots.txt.
                    ['loc' => route('cotizador'), 'prioridad' => '0.9', 'frecuencia' => 'monthly'],
                ],
                'actualizado' => $actualizado,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * robots.txt dinámico.
     *
     * Se sirve por ruta y no como archivo estático en `public/` por dos
     * razones: la URL del sitemap sale de APP_URL (y así nunca queda apuntando
     * al dominio equivocado), y fuera de producción se bloquea el sitio
     * entero. Un staging indexado por Google compite con el sitio real por las
     * mismas palabras y es dificilísimo de sacar después.
     */
    public function robots(): Response
    {
        $lineas = app()->isProduction()
            ? [
                'User-agent: *',
                'Allow: /',
                // El panel interno no aporta nada a un buscador y ya exige login.
                'Disallow: /dashboard',
                'Disallow: /portal/',
                'Disallow: /login',
                'Disallow: /verificar/',
                // `/cotizador` sí se indexa; lo que se bloquea son las
                // estimaciones ya emitidas, que llevan el contacto y el
                // presupuesto de una persona concreta.
                'Disallow: /cotizador/',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : [
                '# Entorno '.app()->environment().': fuera de producción no se indexa nada.',
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode(PHP_EOL, $lineas).PHP_EOL)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Agrega a cada categoría cuántos proyectos tiene, para el contador de los
     * filtros. Una categoría sin proyectos no se muestra: sería un enlace que
     * lleva a una página vacía.
     *
     * @param  Collection<string, array{nombre: string, color: string, descripcion: string}>  $categorias
     * @param  Collection<int, array{categoria: string}>  $proyectos
     * @return array<string, array{nombre: string, color: string, descripcion: string, total: int}>
     */
    private function categoriasConConteo(Collection $categorias, Collection $proyectos): array
    {
        $conteo = $proyectos->countBy('categoria');

        return $categorias
            ->map(fn (array $categoria, string $slug) => $categoria + ['total' => $conteo->get($slug, 0)])
            ->filter(fn (array $categoria) => $categoria['total'] > 0)
            ->all();
    }

    /**
     * JSON-LD de la empresa. Es lo que Google lee para armar el panel lateral
     * con dirección, teléfono y horario, y lo que conecta la marca con el
     * dominio. Va en la portada porque es la página de referencia del sitio.
     *
     * @return array<string, mixed>
     */
    private function negocioJsonLd(): array
    {
        $empresa = config('sitio.empresa');
        $id = route('inicio').'#empresa';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'LocalBusiness',
                    '@id' => $id,
                    'name' => $empresa['nombre'],
                    'description' => $empresa['descripcion'],
                    'url' => route('inicio'),
                    'telephone' => $empresa['telefono'],
                    'email' => $empresa['email'],
                    'foundingDate' => $empresa['fundacion'],
                    'image' => asset('img/publico/banner-ejecucion.jpg'),
                    'logo' => asset('img/logo/logo.webp'),
                    'priceRange' => 'BOB',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $empresa['direccion'],
                        'addressLocality' => $empresa['ciudad'],
                        'addressRegion' => $empresa['departamento'],
                        'addressCountry' => $empresa['pais'],
                    ],
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'Bolivia',
                    ],
                    'sameAs' => array_filter([$empresa['web']]),
                    'hasOfferCatalog' => [
                        '@type' => 'OfferCatalog',
                        'name' => 'Servicios',
                        'itemListElement' => array_map(fn (array $servicio) => [
                            '@type' => 'Offer',
                            'itemOffered' => [
                                '@type' => 'Service',
                                'name' => $servicio['titulo'],
                                'description' => $servicio['descripcion'],
                            ],
                        ], config('sitio.servicios')),
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => route('inicio').'#sitio',
                    'url' => route('inicio'),
                    'name' => $empresa['nombre'],
                    'inLanguage' => 'es-BO',
                    'publisher' => ['@id' => $id],
                ],
            ],
        ];
    }

    /**
     * JSON-LD de la galería: la miga de pan y la lista de trabajos, que es lo
     * que puede aparecer como carrusel de imágenes en los resultados.
     *
     * @param  Collection<int, array{slug: string, titulo: string, detalle: string}>  $proyectos
     * @return array<string, mixed>
     */
    private function galeriaJsonLd(Collection $proyectos): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => route('inicio')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Proyectos', 'item' => route('proyectos')],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    'name' => 'Proyectos y ejecuciones realizadas',
                    'numberOfItems' => $proyectos->count(),
                    'itemListElement' => $proyectos
                        ->values()
                        ->map(fn (array $proyecto, int $indice) => [
                            '@type' => 'ListItem',
                            'position' => $indice + 1,
                            'item' => [
                                '@type' => 'CreativeWork',
                                'name' => $proyecto['titulo'],
                                'description' => $proyecto['detalle'],
                                'image' => asset("img/publico/proyectos/{$proyecto['slug']}.jpg"),
                            ],
                        ])
                        ->all(),
                ],
            ],
        ];
    }
}
