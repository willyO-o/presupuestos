import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Tailwind del SITIO PÚBLICO — deliberadamente separado de `tailwind.config.js`.
 *
 * El panel interno carga `app.css`, que además de utilidades trae tres fuentes
 * de iconos y todo el design system del dashboard: ~1 MB que una landing no
 * necesita y que castiga el LCP, que es justo lo que Google mide para el SEO.
 * Por eso `resources/css/publico.css` apunta a este config con `@config` y
 * escanea solo las vistas públicas.
 *
 * `marca.*` son los tokens de marca de `resources/css/app.css` (los usan el
 * cotizador, el seguimiento y las páginas de error). `sitio.*` son los colores
 * EXACTOS de xtrapubli.com, que la portada, la galería, la cabecera y el pie
 * replican al 100 % (pedido del usuario, 2026-10-29): salen de las variables
 * CSS de ese sitio, no se aproximaron a ojo.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './resources/views/publico/**/*.blade.php',
        './resources/views/components/publico/**/*.blade.php',
        // Las paginas de error usan el mismo layout publico.
        './resources/views/errors/**/*.blade.php',
        // El cotizador agrega clases desde JavaScript (las lineas clonadas y
        // las filas del panel de resultado): sin este glob, Tailwind no las ve
        // al escanear y las purga del bundle.
        './resources/js/publico.js',
    ],

    theme: {
        extend: {
            colors: {
                marca: {
                    // Los 4 colores de marca (ver .claude/skills/xtrapubli-design-system).
                    azul: '#1c7fc4', // --brand-blue
                    celeste: '#6fcbec', // --brand-blue-light
                    turquesa: '#0e8b94', // --brand-teal
                    oscuro: '#14161a', // --brand-dark

                    // Derivados: hover del azul y fondos oscuros de sección.
                    'azul-oscuro': '#145f97', // --c-primary-dark
                    'azul-profundo': '#0f4870',
                    noche: '#0d1420',

                    // Tokens semánticos reutilizados como acentos de la galería.
                    ambar: '#f7b84b', // --c-warning
                    verde: '#17a673', // --c-success

                    // Rojo de los botones de PDF (--c-pdf del panel): el mismo
                    // color identifica "esto es un PDF" en todo el sistema.
                    pdf: '#c62828',
                    'pdf-oscuro': '#a11d1d',

                    // Verde oficial de WhatsApp: no es de marca, es del canal.
                    whatsapp: '#25d366',
                    'whatsapp-oscuro': '#1eb757',
                },

                // Paleta de xtrapubli.com (variables CSS del sitio original).
                sitio: {
                    marino: '#083791', // fondo del hero, bajo la foto al 30 %
                    azul: '#006db1', // secciones azules y franja de contacto
                    verde: '#37ca37', // botón "Solicitar Cotización"
                    naranja: '#f6ad55', // botón "Ver más proyectos"
                    gris: '#cbd5e0', // tarjetas de "¿Qué Hacemos?"
                    play: '#ff5e14', // ícono de reproducir del banner
                    sombra: '#707070', // sombra dura 2px 2px 2px de las tarjetas
                },
            },

            fontFamily: {
                titulo: ['Montserrat', ...defaultTheme.fontFamily.sans],
                // Las dos fuentes de xtrapubli.com: Montserrat en los títulos y
                // Barlow en el texto.
                texto: ['Barlow', ...defaultTheme.fontFamily.sans],
            },

            maxWidth: {
                contenido: '72rem',
                // Ancho del contenido de xtrapubli.com (1170px).
                sitio: '73.125rem',
            },
        },
    },

    plugins: [],
};
