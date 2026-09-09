import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // `publico.css` es una entrada aparte: el sitio publico es Blade
            // puro y no debe cargar app.js ni el CSS del dashboard.
            input: ['resources/js/app.js', 'resources/css/publico.css'],
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
