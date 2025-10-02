import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
// Eliminamos Toast: usaremos AlertModal en las páginas específicas

// --- 1. IMPORTAMOS NUESTRO COMPONENTE DE GALERÍA ---
import ProductGallery from './Components/Product/ProductGallery.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Ondigitalsolution';

createInertiaApp({
    // Mostrar solo el título provisto por cada página (sin sufijo "- Laravel")
    title: (title) => title,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // --- 2. AQUÍ REGISTRAMOS EL COMPONENTE ---
        app.component('ProductGallery', ProductGallery);

        // Mantenemos los redireccionamientos simples para 401/403 (las páginas pueden mostrar AlertModal locales)
        if (window.axios) {
            window.axios.interceptors.response.use(
                (response) => response,
                (error) => {
                    const status = error?.response?.status;
                    if (status === 403) {
                        try { window.location.href = route('dashboard'); } catch (e) { window.location.href = '/'; }
                        return Promise.resolve();
                    }
                    if (status === 401) {
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