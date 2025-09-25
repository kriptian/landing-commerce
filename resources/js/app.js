import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// --- 1. IMPORTAMOS NUESTRO COMPONENTE DE GALERÍA ---
import ProductGallery from './Components/Product/ProductGallery.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Toast);

        // --- 2. AQUÍ REGISTRAMOS EL COMPONENTE ---
        app.component('ProductGallery', ProductGallery);

        // Interceptor global para 403 y 401
        if (window.axios) {
            window.axios.interceptors.response.use(
                (response) => response,
                (error) => {
                    const status = error?.response?.status;
                    if (status === 403) {
                        app.config.globalProperties.$toast?.error('Acción denegada');
                        // Redirige a dashboard si existe, si no, a inicio
                        try { window.location.href = route('dashboard'); } catch (e) { window.location.href = '/'; }
                        return Promise.resolve();
                    }
                    if (status === 401) {
                        app.config.globalProperties.$toast?.info('Sesión expirada');
                        window.location.href = route('login');
                        return Promise.resolve();
                    }
                    return Promise.reject(error);
                }
            );
        }

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});