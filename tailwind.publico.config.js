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
 * Los colores NO son los del mockup (#0066d6 / #0070bb, aproximaciones a ojo):
 * son los tokens reales de marca de `resources/css/app.css`, para que el sitio
 * público y el panel se vean como la misma empresa. Si cambia la identidad, se
 * cambia allá y se replica acá.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './resources/views/publico/**/*.blade.php',
        './resources/views/components/publico/**/*.blade.php',
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

                    // Verde oficial de WhatsApp: no es de marca, es del canal.
                    whatsapp: '#25d366',
                    'whatsapp-oscuro': '#1eb757',
                },
            },

            fontFamily: {
                titulo: ['Montserrat', ...defaultTheme.fontFamily.sans],
                texto: ['Roboto', ...defaultTheme.fontFamily.sans],
            },

            maxWidth: {
                contenido: '72rem',
            },
        },
    },

    plugins: [],
};
