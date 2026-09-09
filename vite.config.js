import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // `publico.css`/`publico.js` son entradas aparte: el sitio publico
            // es Blade puro y no debe cargar app.js ni el CSS del dashboard.
            // `publico.js` son unos pocos KB sin framework y solo lo carga el
            // cotizador; la portada y la galeria no cargan JavaScript.
            input: [
                'resources/js/app.js',
                'resources/css/publico.css',
                'resources/js/publico.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
