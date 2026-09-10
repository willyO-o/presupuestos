import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PageLoader from '@/Components/Layout/PageLoader.vue';
import can from '@/Directives/Can';
import Decimal from '@/Directives/Decimal';
import Entero from '@/Directives/Entero';
import MaxLength from '@/Directives/MaxLength';
import { useFlashNotifications } from '@/Composables/UseFlashNotifications';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Toasts de exito/error automaticos en cualquier pagina, sin que
        // cada formulario tenga que llamar showToast() a mano — ver
        // Composables/UseFlashNotifications.js.
        useFlashNotifications();

        // El loader de cambio de pagina va HERMANO de la app, no dentro de un
        // layout: asi cubre tambien las pantallas de acceso y el portal, y no
        // se remonta en cada navegacion (que es justo cuando tiene que estar
        // vivo). Ver Components/Layout/PageLoader.vue.
        return createApp({ render: () => [h(App, props), h(PageLoader)] })
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
