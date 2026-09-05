import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { useOfflineSync } from './Composables/useOfflineSync';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Registra, uma única vez para o app inteiro, o listener que sincroniza a
// fila offline (sessões/séries registradas sem conexão) assim que a internet
// volta — independente de qual página o usuário estiver quando isso acontece.
useOfflineSync();

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
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
