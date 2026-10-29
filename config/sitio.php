<?php

/*
|--------------------------------------------------------------------------
| Sitio público de XtraPubli
|--------------------------------------------------------------------------
|
| Todo el contenido editable de las dos páginas públicas (`/` y `/proyectos`)
| vive acá: datos de contacto, servicios, diferenciales, la vitrina y la
| galería de proyectos. La idea es que marketing pueda cambiar textos sin
| tocar Blade. Desde el 2026-10-29 los textos e imágenes son los de
| xtrapubli.com (el sitio público lo replica tal cual).
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
        // La meta description de xtrapubli.com: va al <head> de la portada y al JSON-LD.
        'descripcion' => 'XtraPubli desarrolla exhibidores para puntos de venta, material POP, '
            .'letreros luminosos y soluciones visuales para retail, marcas y negocios.',
        'fundacion' => '2015',

        // El número en formato E.164 sin signos, tal como lo pide wa.me.
        'whatsapp' => env('SITIO_WHATSAPP', '59176578910'),
        'telefono' => env('SITIO_TELEFONO', '+59176578910'),
        'telefono_visible' => env('SITIO_TELEFONO_VISIBLE', '591 765-7-8-9-10'),
        'email' => env('SITIO_EMAIL', 'xtrapubli@gmail.com'),
        'web' => env('SITIO_WEB', 'https://www.xtrapubli.com'),

        // Datos tributarios del padrón (NIT 2021, Régimen General, emite
        // factura). XtraPubli es el nombre comercial de una empresa
        // unipersonal: la razón social es el nombre del propietario.
        'propietario' => 'Guillermo Apaza Apaza',
        'razon_social' => 'Guillermo Apaza Apaza',
        'nit' => '4755806014',
        'actividad' => 'Publicidad',

        // Domicilio de la casa matriz tal como figura en el NIT. `direccion`
        // es la versión corta que entra en una línea del membrete;
        // `referencia` completa cómo llegar.
        'direccion' => 'C/ René Vargas Nro. 113, Z. Villa Ballivián',
        'referencia' => 'Entre C/ Álvarez Plata y C/ Catacora, planta baja (tienda externa), '
            .'frente al Colegio Técnico Humanístico José Ballivián',
        // La misma dirección como la escribe xtrapubli.com en su pie.
        'direccion_sitio' => 'Z/Ballivián C/R. Vargas 113',
        'ciudad' => 'El Alto',
        'departamento' => 'La Paz',
        'pais' => 'BO',
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Qué Hacemos? — las 4 líneas de servicio
    |--------------------------------------------------------------------------
    |
    | Textos e íconos tal como están en xtrapubli.com (con la ortografía
    | corregida). `imagen` es la ruta relativa a `public/`: son los mismos
    | íconos azules del sitio original, descargados a `img/publico/sitio/`.
    |
    */

    'servicios' => [
        [
            'imagen' => 'img/publico/sitio/servicio-exhibidores.png',
            'titulo' => 'Exhibidores',
            'descripcion' => 'Diseño y fabricación de exhibidores personalizados para destacar '
                .'productos y potenciar campañas comerciales.',
        ],
        [
            'imagen' => 'img/publico/sitio/servicio-material-pop.png',
            'titulo' => 'Material POP',
            'descripcion' => 'Banners, roll ups, back panels, stands y mucho más: producción de '
                .'material visual para campañas promocionales y activaciones de marca.',
        ],
        [
            'imagen' => 'img/publico/sitio/servicio-implementaciones.png',
            'titulo' => 'Implementaciones',
            'descripcion' => 'Instalación y ejecución en punto de venta, con enfoque en '
                .'cumplimiento y presentación profesional.',
        ],
        [
            'imagen' => 'img/publico/sitio/servicio-produccion.png',
            'titulo' => 'Producción a escala',
            'descripcion' => 'Capacidad para trabajar proyectos en volumen y campañas.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Cómo hacemos? — los 4 compromisos
    |--------------------------------------------------------------------------
    */

    'diferenciales' => [
        [
            'imagen' => 'img/publico/sitio/compromiso-entregas.png',
            'titulo' => 'Entregas a Tiempo',
            'descripcion' => 'Coordinamos tiempos para garantizar entregas a tiempo.',
        ],
        [
            'imagen' => 'img/publico/sitio/compromiso-calidad.png',
            'titulo' => 'Garantizamos calidad',
            'descripcion' => 'Cuidamos cada detalle, desde el material y acabados.',
        ],
        [
            'imagen' => 'img/publico/sitio/compromiso-ejecucion.png',
            'titulo' => 'Aseguramos ejecución',
            'descripcion' => 'Implementación profesional.',
        ],
        [
            'imagen' => 'img/publico/sitio/compromiso-venta.png',
            'titulo' => 'Pensamos en la venta',
            'descripcion' => 'Orientados a resultados comerciales.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ¿Para quién es?
    |--------------------------------------------------------------------------
    */

    'publico_objetivo' => [
        [
            'imagen' => 'img/publico/sitio/publico-empresas.png',
            'titulo' => 'Empresas de productos masivos',
            'descripcion' => 'Empresas de consumo masivo con necesidad de ejecución en retail y '
                .'puntos de venta físicos.',
        ],
        [
            'imagen' => 'img/publico/sitio/publico-marketing.png',
            'titulo' => 'Equipos de marketing y trade',
            'descripcion' => 'Agencias y departamentos que buscan un aliado clave confiable.',
        ],
        [
            'imagen' => 'img/publico/sitio/publico-negocios.png',
            'titulo' => 'Negocios en crecimiento y expansión',
            'descripcion' => 'Ayudamos a negocios en crecimiento a construir presencia visual profesional.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Algunos Proyectos Realizados — la vitrina de la portada
    |--------------------------------------------------------------------------
    |
    | Las cuatro tarjetas de la portada, iguales a las de xtrapubli.com: cada
    | imagen ya es un collage con su rótulo ("Islas Cabeceras", "Exhibidores",
    | ...), por eso no salen de la galería (que muestra fotos sueltas).
    |
    */

    'vitrina' => [
        [
            'imagen' => 'img/publico/sitio/vitrina-islas-cabeceras.jpg',
            'alt' => 'Collage de islas y cabeceras promocionales en supermercados',
            'descripcion' => 'Cabeceras e islas promocionales diseñadas para maximizar la '
                .'visibilidad y presencia de marca en supermercados.',
        ],
        [
            'imagen' => 'img/publico/sitio/vitrina-exhibidores.jpg',
            'alt' => 'Collage de exhibidores personalizados',
            'descripcion' => 'Exhibidores personalizados diseñados para destacar productos y '
                .'fortalecer la presencia de marca en el punto de venta.',
        ],
        [
            'imagen' => 'img/publico/sitio/vitrina-toldos-letreros.jpg',
            'alt' => 'Collage de toldos y letreros en tiendas',
            'descripcion' => 'Toldos retráctiles y toldos fijos en tiendas, diseñados para brindar '
                .'visibilidad, protección y presencia de marca.',
        ],
        [
            'imagen' => 'img/publico/sitio/vitrina-letras-3d.jpg',
            'alt' => 'Collage de letras 3D y letreros luminosos',
            'descripcion' => 'Fabricamos soluciones visuales para fachadas comerciales, creando '
                .'letreros luminosos modernos que fortalecen la presencia e identidad de cada marca.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Categorías de la galería
    |--------------------------------------------------------------------------
    |
    | La clave del array es el valor de `?categoria=` en /proyectos y también
    | el `categoria` de cada proyecto de abajo.
    |
    */

    'categorias' => [
        'exhibidores' => [
            'nombre' => 'Exhibidores y muebles',
            'descripcion' => 'Exhibidores de piso, islas, exhibidores de alambre y muebles fabricados a medida.',
        ],
        'fachadas' => [
            'nombre' => 'Fachadas, letreros y toldos',
            'descripcion' => 'Letreros luminosos y corpóreos, tótems, toldos y fachadas comerciales.',
        ],
        'vehicular' => [
            'nombre' => 'Rotulado vehicular',
            'descripcion' => 'Rotulado de camiones, camionetas y buses.',
        ],
        'activaciones' => [
            'nombre' => 'Stands y módulos',
            'descripcion' => 'Stands de feria y módulos promocionales.',
        ],
        'implementaciones' => [
            'nombre' => 'Implementación en punto de venta',
            'descripcion' => 'Instalación de letreros, gráficas y mobiliario en tiendas, edificios y supermercados.',
        ],
        'pop' => [
            'nombre' => 'Material POP',
            'descripcion' => 'Habladores y piezas gráficas para el punto de venta.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Galería de proyectos
    |--------------------------------------------------------------------------
    |
    | Las 40 fotos de la galería de xtrapubli.com, en el mismo orden. `slug`
    | tiene que coincidir con el nombre del archivo en
    | `public/img/publico/proyectos/` (`{slug}.jpg`), `categoria` con una clave
    | de `categorias`, y `alt` describe la foto para lectores de pantalla y
    | para Google Imágenes.
    |
    */

    'proyectos' => [
        [
            'slug' => 'delizia-helados-camion-furgon',
            'titulo' => 'Delizia Helados - Camión furgón',
            'detalle' => 'Rotulado integral del furgón',
            'categoria' => 'vehicular',
            'alt' => 'Camión furgón de Delizia Helados rotulado con la marca',
        ],
        [
            'slug' => 'ice-fruit-camioneta',
            'titulo' => 'Ice Fruit - Camioneta',
            'detalle' => 'Rotulado vehicular en vinil',
            'categoria' => 'vehicular',
            'alt' => 'Camioneta pick-up rotulada con la marca Ice Fruit',
        ],
        [
            'slug' => 'bus-de-la-paz',
            'titulo' => 'Bus de La Paz',
            'detalle' => 'Rotulado de bus con ilustraciones',
            'categoria' => 'vehicular',
            'alt' => 'Bus blanco rotulado con las ilustraciones del Bus de La Paz',
        ],
        [
            'slug' => 'dismac-servicio-tecnico',
            'titulo' => 'Dismac - Servicio Técnico',
            'detalle' => 'Fachada con letrero y caja de luz',
            'categoria' => 'fachadas',
            'alt' => 'Fachada de Dismac con el letrero de Servicio Técnico',
        ],
        [
            'slug' => 'delizia-logo-corporeo',
            'titulo' => 'Delizia - Logo corpóreo',
            'detalle' => 'Letrero corpóreo para interior',
            'categoria' => 'fachadas',
            'alt' => 'Logo corpóreo de Delizia sobre una pared de recepción',
        ],
        [
            'slug' => 'entel-multicentro',
            'titulo' => 'Entel Multicentro',
            'detalle' => 'Instalación de letrero de fachada',
            'categoria' => 'fachadas',
            'alt' => 'Técnicos instalando el letrero de Entel Multicentro en una fachada',
        ],
        [
            'slug' => 'dismac-fachada-luminosa',
            'titulo' => 'Dismac - Fachada luminosa',
            'detalle' => 'Letreros luminosos y fachada comercial',
            'categoria' => 'fachadas',
            'alt' => 'Fachada de Dismac iluminada de noche con letreros luminosos',
        ],
        [
            'slug' => 'delizia-totem-torre-32',
            'titulo' => 'Delizia Agencia - Tótem Torre 32',
            'detalle' => 'Tótem de señalización exterior',
            'categoria' => 'fachadas',
            'alt' => 'Tótem de señalización Torre 32 con el logo de Delizia Agencia',
        ],
        [
            'slug' => 'delizia-letras-corporeas',
            'titulo' => 'Delizia Agencia - Letras corpóreas',
            'detalle' => 'Producción de letreros en el taller',
            'categoria' => 'fachadas',
            'alt' => 'Letreros corpóreos de Delizia Agencia en producción en el taller',
        ],
        [
            'slug' => 'delizia-instalacion-en-altura',
            'titulo' => 'Delizia - Instalación en altura',
            'detalle' => 'Montaje de letrero en edificio con grúa',
            'categoria' => 'implementaciones',
            'alt' => 'Montaje con grúa de un letrero en la fachada de un edificio',
        ],
        [
            'slug' => 'entel-modulo-promocional',
            'titulo' => 'Entel - Módulo promocional',
            'detalle' => 'Módulo para venta de tarjetas y chips',
            'categoria' => 'activaciones',
            'alt' => 'Módulo promocional de Entel para la venta de tarjetas y chips',
        ],
        [
            'slug' => 'pulpin-letrero-tienda',
            'titulo' => 'Pulpin - Letrero de tienda',
            'detalle' => 'Letrero publicitario sobre fachada',
            'categoria' => 'fachadas',
            'alt' => 'Letrero publicitario de Pulpin sobre la fachada de una tienda',
        ],
        [
            'slug' => 'dismac-implementacion-tienda',
            'titulo' => 'Dismac - Implementación en tienda',
            'detalle' => 'Instalación de gráficas en punto de venta',
            'categoria' => 'implementaciones',
            'alt' => 'Técnico de XtraPubli instalando gráficas en una tienda Dismac',
        ],
        [
            'slug' => 'plenitud-letrero-toldo',
            'titulo' => 'Plenitud - Letrero y toldo',
            'detalle' => 'Señalización de tienda de barrio',
            'categoria' => 'fachadas',
            'alt' => 'Letrero y toldo de Plenitud sobre una tienda de barrio',
        ],
        [
            'slug' => 'kris-toldo-almacen',
            'titulo' => 'Kris - Toldo de almacén',
            'detalle' => 'Toldo fijo con marca',
            'categoria' => 'fachadas',
            'alt' => 'Toldo de la marca Kris en la entrada de un almacén',
        ],
        [
            'slug' => 'huggies-toldo-retractil',
            'titulo' => 'Huggies - Toldo retráctil',
            'detalle' => 'Toldo con marca para tienda',
            'categoria' => 'fachadas',
            'alt' => 'Toldo retráctil de Huggies instalado sobre una tienda',
        ],
        [
            'slug' => 'kris-fachada-tienda',
            'titulo' => 'Kris - Fachada de tienda',
            'detalle' => 'Revestimiento gráfico de fachada',
            'categoria' => 'fachadas',
            'alt' => 'Fachada de tienda revestida con gráficas de Kris',
        ],
        [
            'slug' => 'kotex-letrero-tienda',
            'titulo' => 'Kotex - Letrero de tienda',
            'detalle' => 'Letrero y toldo de marca',
            'categoria' => 'fachadas',
            'alt' => 'Letrero de Kotex sobre un puesto comercial',
        ],
        [
            'slug' => 'frussion-fachada',
            'titulo' => 'Frussion - Fachada de tienda',
            'detalle' => 'Fachada intervenida con la marca',
            'categoria' => 'fachadas',
            'alt' => 'Fachada de tienda decorada con gráficas de Frussion',
        ],
        [
            'slug' => 'bon-o-bon-exhibidor',
            'titulo' => 'Bon o Bon - Exhibidor',
            'detalle' => 'Exhibidor de piso con estantes',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor amarillo de Bon o Bon cargado de golosinas',
        ],
        [
            'slug' => 'delizia-kefir-exhibidor',
            'titulo' => 'Delizia Kéfir - Exhibidor',
            'detalle' => 'Exhibidor de piso para supermercado',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor blanco de Delizia Kéfir en un supermercado',
        ],
        [
            'slug' => 'carozzi-exhibidor',
            'titulo' => 'Carozzi - Exhibidor',
            'detalle' => 'Exhibidor de piso con repisas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor blanco de Carozzi con repisas en un supermercado',
        ],
        [
            'slug' => 'lazzaroni-exhibidor',
            'titulo' => 'Lazzaroni - Exhibidor',
            'detalle' => 'Exhibidor de bandejas metálicas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor amarillo de Lazzaroni con bandejas',
        ],
        [
            'slug' => 'bristar-venado-bandejas',
            'titulo' => 'Bristar y Venado - Exhibidores de bandejas',
            'detalle' => 'Exhibidores circulares de tres niveles',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidores circulares, azul de Bristar y rojo de Venado',
        ],
        [
            'slug' => 'carozzi-isla',
            'titulo' => 'Carozzi - Isla promocional',
            'detalle' => 'Isla de cuatro caras',
            'categoria' => 'exhibidores',
            'alt' => 'Isla promocional de Carozzi con repisas en sus cuatro caras',
        ],
        [
            'slug' => 'princesa-exhibidor',
            'titulo' => 'Princesa - Exhibidor',
            'detalle' => 'Exhibidor de góndola',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor amarillo de la marca Princesa en un supermercado',
        ],
        [
            'slug' => 'exhibidor-cuidado-personal',
            'titulo' => 'Exhibidor de cuidado personal',
            'detalle' => 'Exhibidor escalonado de piso',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor blanco escalonado para productos de cuidado personal',
        ],
        [
            'slug' => 'kris-exhibidor',
            'titulo' => 'Kris - Exhibidor',
            'detalle' => 'Exhibidor de piso con repisas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor azul y rojo de Kris con repisas',
        ],
        [
            'slug' => 'vemassa-carrito-bebidas',
            'titulo' => 'Vemassa - Carrito de bebidas',
            'detalle' => 'Exhibidor móvil con ruedas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor con ruedas de Vemassa para bebidas',
        ],
        [
            'slug' => 'vemassa-exhibidor-bebidas',
            'titulo' => 'Vemassa - Exhibidor de bebidas',
            'detalle' => 'Exhibidor de piso para botellas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor naranja de Vemassa cargado con botellas de bebida',
        ],
        [
            'slug' => 'venado-exhibidores-alambre',
            'titulo' => 'Venado - Exhibidores de alambre',
            'detalle' => 'Exhibidores metálicos con marca',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidores rojos de alambre con la marca Venado',
        ],
        [
            'slug' => 'bristar-canastos',
            'titulo' => 'Bristar - Canastos de alambre',
            'detalle' => 'Canastos promocionales con base',
            'categoria' => 'exhibidores',
            'alt' => 'Canastos de alambre azules con la marca Bristar',
        ],
        [
            'slug' => 'bristar-pulpin-exhibidores',
            'titulo' => 'Bristar y Pulpin - Exhibidores',
            'detalle' => 'Exhibidores de alambre y repisas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidores de alambre, azules de Bristar y verde de Pulpin',
        ],
        [
            'slug' => 'nub-cilindro',
            'titulo' => 'NUB - Cilindro exhibidor',
            'detalle' => 'Exhibidor cilíndrico impreso',
            'categoria' => 'exhibidores',
            'alt' => 'Cilindro exhibidor rosado con la marca NUB',
        ],
        [
            'slug' => 'marcas-que-bolivia-ama',
            'titulo' => 'Las marcas que Bolivia ama - Exhibidor',
            'detalle' => 'Exhibidor escalonado de cajas',
            'categoria' => 'exhibidores',
            'alt' => 'Exhibidor rojo escalonado con las marcas Bristar, Pulpin y Kris',
        ],
        [
            'slug' => 'finilager-stand-feria',
            'titulo' => 'Finilager - Stand de feria',
            'detalle' => 'Stand para exposición',
            'categoria' => 'activaciones',
            'alt' => 'Stand de Finilager armado en una feria',
        ],
        [
            'slug' => 'mostrador-tematico',
            'titulo' => 'Mostrador temático',
            'detalle' => 'Mueble a medida para punto de venta',
            'categoria' => 'exhibidores',
            'alt' => 'Mostrador azul a rayas con un borde blanco en forma de gotas',
        ],
        [
            'slug' => 'habladores-heladeria',
            'titulo' => 'Habladores para heladería',
            'detalle' => 'Piezas recortadas con los sabores',
            'categoria' => 'pop',
            'alt' => 'Habladores recortados con los sabores de helado vainilla, chirimoya y cassatta',
        ],
        [
            'slug' => 'delizia-letrero-edificio',
            'titulo' => 'Delizia - Letrero en edificio',
            'detalle' => 'Letrero corpóreo en altura',
            'categoria' => 'fachadas',
            'alt' => 'Letrero corpóreo de Delizia en lo alto de un edificio de vidrio',
        ],
        [
            'slug' => 'implementacion-supermercado',
            'titulo' => 'Implementación de supermercado',
            'detalle' => 'Mobiliario y señalética de sala de ventas',
            'categoria' => 'implementaciones',
            'alt' => 'Sala de supermercado con góndolas y señalética colgante instaladas',
        ],
    ],

];
