import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import can from '@/Directives/Can';
import Decimal from '@/Directives/Decimal';
import Entero from '@/Directives/Entero';
import MaxLength from '@/Directives/MaxLength';



const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('can', can)
            .directive('decimal', Decimal)
            .directive('entero', Entero)
            .directive('max-length', MaxLength)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
