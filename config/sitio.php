<?php

/*
|--------------------------------------------------------------------------
| Sitio público de XtraPubli
|--------------------------------------------------------------------------
|
| Todo el contenido editable de las dos páginas públicas (`/` y `/proyectos`)
| vive acá: datos de contacto, servicios, diferenciales y la galería de
| proyectos. La idea es que marketing pueda cambiar textos sin tocar Blade.
|
| Los datos de contacto salen de `.env` porque cambian de un despliegue a
| otro (staging no debería mandar WhatsApp al número real); el resto es
| contenido editorial y se edita acá directamente.
|
| Tras editar este archivo en producción hay que correr `php artisan
| config:clear` (o `config:cache` de nuevo) para que el cambio se vea.
|
| Las imágenes de la galería están en `public/img/publico/proyectos/` y se
| resuelven por el `slug` de cada proyecto: `{slug}.jpg`.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de la empresa
    |--------------------------------------------------------------------------
    |
    | Alimentan el pie de página, los botones de contacto y el JSON-LD de
    | `LocalBusiness` que Google usa para el panel de conocimiento.
    |
    */

    'empresa' => [
        'nombre' => 'XtraPubli',
        'lema' => 'Hacemos que tu Marca Venda!',

        // Ruta del logo relativa a `public/`, tal como la lee FPDF en el
        // membrete de los PDF (por sistema de archivos, nunca por URL).
        'logo' => 'img/logo/logo.png',
        'descripcion' => 'Diseñamos, fabricamos e implementamos exhibidores, material POP y '
            .'soluciones visuales para marcas que necesitan presencia real en el punto de venta.',
        'fundacion' => '2015',

        // El número en formato E.164 sin signos, tal como lo pide wa.me.
        'whatsapp' => env('SITIO_WHATSAPP', '59176578910'),
        'telefono' => env('SITIO_TELEFONO', '+59176578910'),
        'telefono_visible' => env('SITIO_TELEFONO_VISIBLE', '591 765-7-8-9-10'),
        'email' => env('SITIO_EMAIL', 'xtrapubli@gmail.com'),
        'web' => env('SITIO_WEB', 'https://www.xtrapubli.com'),

        'direccion' => 'Z/Ballivián C/R. Vargas 113',
        'ciudad' => 'El Alto',
        'departamento' => 'La Paz',
        'pais' => 'BO',
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Qué hacemos? — las 4 líneas de servicio
    |--------------------------------------------------------------------------
    |
    | `icono` es la clave que resuelve <x-publico.icono>; si se agrega un
    | servicio nuevo hay que darle de alta el ícono ahí.
    |
    */

    'servicios' => [
        [
            'icono' => 'exhibidor',
            'titulo' => 'Exhibidores',
            'descripcion' => 'Diseño y fabricación de exhibidores personalizados para destacar '
                .'productos y potenciar campañas comerciales.',
        ],
        [
            'icono' => 'pop',
            'titulo' => 'Material POP',
            'descripcion' => 'Banners, roll ups, back panels y stands: producción de material '
                .'visual para campañas promocionales y activaciones de marca.',
        ],
        [
            'icono' => 'implementacion',
            'titulo' => 'Implementaciones',
            'descripcion' => 'Instalación y ejecución en punto de venta, con enfoque en '
                .'cumplimiento y presentación profesional.',
        ],
        [
            'icono' => 'produccion',
            'titulo' => 'Producción a escala',
            'descripcion' => 'Capacidad para trabajar proyectos en volumen y campañas '
                .'comerciales de alta exigencia.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Cómo lo hacemos? — los 4 compromisos
    |--------------------------------------------------------------------------
    */

    'diferenciales' => [
        [
            'icono' => 'reloj',
            'titulo' => 'Entregas a tiempo',
            'descripcion' => 'Coordinamos tiempos de producción y montaje para cumplir la fecha comprometida.',
        ],
        [
            'icono' => 'calidad',
            'titulo' => 'Garantizamos calidad',
            'descripcion' => 'Cuidamos cada detalle, desde la selección del material hasta los acabados.',
        ],
        [
            'icono' => 'escudo',
            'titulo' => 'Aseguramos la ejecución',
            'descripcion' => 'Implementación profesional y seguimiento en todo el proceso.',
        ],
        [
            'icono' => 'crecimiento',
            'titulo' => 'Pensamos en la venta',
            'descripcion' => 'Cada pieza se diseña orientada a resultados comerciales tangibles.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Para quién es?
    |--------------------------------------------------------------------------
    */

    'publico_objetivo' => [
        [
            'titulo' => 'Empresas de consumo masivo',
            'descripcion' => 'Marcas con necesidad de ejecución en retail y puntos de venta físicos.',
        ],
        [
            'titulo' => 'Equipos de marketing y trade',
            'descripcion' => 'Agencias y departamentos que buscan un aliado confiable para ejecuciones integrales.',
        ],
        [
            'titulo' => 'Negocios en crecimiento',
            'descripcion' => 'Empresas en expansión que necesitan construir presencia visual profesional.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Categorías de la galería
    |--------------------------------------------------------------------------
    |
    | La clave del array es el valor de `?categoria=` en /proyectos y también
    | el `categoria` de cada proyecto de abajo. `color` es semántico: lo
    | traduce a clases <x-publico.etiqueta>, para no meter CSS en el config.
    |
    */

    'categorias' => [
        'exhibidores' => [
            'nombre' => 'Exhibidores y muebles',
            'color' => 'azul',
            'descripcion' => 'Góndolas, islas, torres y exhibidores metálicos fabricados a medida.',
        ],
        'fachadas' => [
            'nombre' => 'Fachadas y letreros',
            'color' => 'oscuro',
            'descripcion' => 'Letreros corporativos, cajas luminosas y revestimiento de tiendas.',
        ],
        'vehicular' => [
            'nombre' => 'Rotulado vehicular',
            'color' => 'turquesa',
            'descripcion' => 'Branding de flotas con vinilo polimérico de alta durabilidad.',
        ],
        'activaciones' => [
            'nombre' => 'Stands y activaciones',
            'color' => 'ambar',
            'descripcion' => 'Módulos desarmables, gazebos y estructuras para campañas BTL.',
        ],
        'implementaciones' => [
            'nombre' => 'Implementación en punto de venta',
            'color' => 'verde',
            'descripcion' => 'Montajes de alta complejidad e implementación integral de pasillos.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Proyectos destacados en la portada
    |--------------------------------------------------------------------------
    |
    | Slugs (de `proyectos`) que se muestran en la vista previa de la portada.
    | Se listan a mano y no se toman "los primeros" porque son la vitrina: se
    | eligen por lo que mejor representa a la empresa, no por orden de carga.
    |
    */

    'destacados' => [
        'implementacion-de-pasillo',
        'delizia-flota-vehicular',
        'dismac-letrero-corporativo',
        'frussion-cabecera-frutas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Galería de proyectos
    |--------------------------------------------------------------------------
    |
    | El orden de este array es el orden en que se muestran. `slug` tiene que
    | coincidir con el nombre del archivo en `public/img/publico/proyectos/`,
    | `categoria` con una clave de `categorias`, y `alt` describe la foto para
    | lectores de pantalla y para Google Imágenes (no repetir el título).
    |
    */

    'proyectos' => [
        [
            'slug' => 'delizia-flota-vehicular',
            'titulo' => 'Delizia - Flota Vehicular',
            'detalle' => 'Branding y rotulado vinil',
            'etiqueta' => 'Vehicular',
            'categoria' => 'vehicular',
            'alt' => 'Rotulación Camión Delizia',
        ],
        [
            'slug' => 'entel-modulo-desarmable',
            'titulo' => 'Entel - Módulo Desarmable',
            'detalle' => 'Punto de activación SIM',
            'etiqueta' => 'Stand BTL',
            'categoria' => 'activaciones',
            'alt' => 'Módulo promocional Entel',
        ],
        [
            'slug' => 'delizia-kefir-gondola',
            'titulo' => 'Delizia Kéfir - Góndola',
            'detalle' => 'Mueble metálico retail',
            'etiqueta' => 'Exhibidor',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor retail Delizia',
        ],
        [
            'slug' => 'torre-beauty-care',
            'titulo' => 'Torre Beauty & Care',
            'detalle' => 'Acrílico y metal termoesmaltado',
            'etiqueta' => 'Floor Stand',
            'categoria' => 'exhibidores',
            'alt' => 'Torre exhibidora blanca',
        ],
        [
            'slug' => 'exhibidores-de-alambre',
            'titulo' => 'Exhibidores de Alambre',
            'detalle' => 'Canastillas regulables',
            'etiqueta' => 'Metalmecánica',
            'categoria' => 'exhibidores',
            'alt' => 'Canastillos y estantes',
        ],
        [
            'slug' => 'flota-pick-up-comercial',
            'titulo' => 'Flota Pick-up Comercial',
            'detalle' => 'Wrap con vinilo polimérico',
            'etiqueta' => 'Vehicular',
            'categoria' => 'vehicular',
            'alt' => 'Rotulación Camioneta Pickup',
        ],
        [
            'slug' => 'pulpin-cabecera-gondola',
            'titulo' => 'Pulpín - Cabecera Góndola',
            'detalle' => 'Branding de impacto visual',
            'etiqueta' => 'Exhibidor',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor Pulpin',
        ],
        [
            'slug' => 'carozzi-isla-central',
            'titulo' => 'Carozzi - Isla Central',
            'detalle' => 'Mueble 4 caras con cenefa',
            'etiqueta' => 'Supermercado',
            'categoria' => 'exhibidores',
            'alt' => 'Torre Carozzi Supermercado',
        ],
        [
            'slug' => 'kris-el-sabor-de-bolivia',
            'titulo' => 'KRIS - "El Sabor de Bolivia"',
            'detalle' => 'Estructura metálica termoformada',
            'etiqueta' => 'Punto de Venta',
            'categoria' => 'activaciones',
            'alt' => 'Exhibidor KRIS',
        ],
        [
            'slug' => 'nub-cilindro-luminoso',
            'titulo' => 'NUB - Cilindro Luminoso',
            'detalle' => 'Backlight publicitario 360°',
            'etiqueta' => 'Tótem Led',
            'categoria' => 'fachadas',
            'alt' => 'Tótem Cilíndrico Iluminado',
        ],
        [
            'slug' => 'dismac-letrero-corporativo',
            'titulo' => 'Dismac - Letrero Corporativo',
            'detalle' => 'Alucobond y letras volumétricas',
            'etiqueta' => 'Fachada',
            'categoria' => 'fachadas',
            'alt' => 'Fachada Corporativa Dismac',
        ],
        [
            'slug' => 'huggies-gazebo-btl',
            'titulo' => 'Huggies - Gazebo BTL',
            'detalle' => 'Campaña y carpa publicitaria',
            'etiqueta' => 'Activación',
            'categoria' => 'activaciones',
            'alt' => 'Toldo BTL Huggies',
        ],
        [
            'slug' => 'bristar-bandejas-circulares',
            'titulo' => 'Bristar - Bandejas Circulares',
            'detalle' => 'Pintura electrostática al horno',
            'etiqueta' => 'Exhibidor',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor Bristar Circular',
        ],
        [
            'slug' => 'delizia-logotipo-edificio',
            'titulo' => 'Delizia - Logotipo Edificio',
            'detalle' => 'Caja luminosa de gran formato',
            'etiqueta' => 'Letrero Corp',
            'categoria' => 'fachadas',
            'alt' => 'Branding Torre Delizia',
        ],
        [
            'slug' => 'kotex-identidad-comercial',
            'titulo' => 'Kotex - Identidad Comercial',
            'detalle' => 'Revestimiento de tienda retail',
            'etiqueta' => 'Fachada',
            'categoria' => 'fachadas',
            'alt' => 'Fachada Tienda Kotex',
        ],
        [
            'slug' => 'frussion-cabecera-frutas',
            'titulo' => 'Frussion - Cabecera Frutas',
            'detalle' => 'Exhibición masiva de jugos',
            'etiqueta' => 'Isla Retail',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor Frussion',
        ],
        [
            'slug' => 'gondola-piramidal-amarilla',
            'titulo' => 'Góndola Piramidal Amarilla',
            'detalle' => 'Metal perforado y branding',
            'etiqueta' => 'Fabricación',
            'categoria' => 'exhibidores',
            'alt' => 'Mueble Metálico XtraPubli',
        ],
        [
            'slug' => 'bristar-tambos-promocionales',
            'titulo' => 'Bristar - Tambos Promocionales',
            'detalle' => 'Exhibidores circulares de oferta',
            'etiqueta' => 'Tambos POP',
            'categoria' => 'exhibidores',
            'alt' => 'Tambos y canastillos Bristar',
        ],
        [
            'slug' => 'montajes-de-alta-complejidad',
            'titulo' => 'Montajes de Alta Complejidad',
            'detalle' => 'Instalación con grúa pluma',
            'etiqueta' => 'Instalación',
            'categoria' => 'implementaciones',
            'alt' => 'Montaje de Letreros en Altura',
        ],
        [
            'slug' => 'implementacion-de-pasillo',
            'titulo' => 'Implementación de Pasillo',
            'detalle' => 'Góndolas, cenefas y stop-stands',
            'etiqueta' => 'Retail 360°',
            'categoria' => 'implementaciones',
            'alt' => 'Pasillo completo de supermercado',
        ],
    ],

];
