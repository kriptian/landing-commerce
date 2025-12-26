import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
// Eliminamos Toast: usaremos AlertModal en las páginas específicas

// --- 1. IMPORTAMOS NUESTRO COMPONENTE DE GALERÍA ---
import ProductGallery from './Components/Product/ProductGallery.vue';
// Helper global: usa Ziggy si existe; si no, usa un path literal de respaldo
export function safeRoute(name, params = {}, fallbackPath = '/') {
    try {
        if (typeof route === 'function') {
            return route(name, params);
        }
    } catch (e) { /* ignore */ }
    let url = String(fallbackPath);
    Object.entries(params || {}).forEach(([k, v]) => {
        url = url.replace(new RegExp(`:${k}\\b|{${k}}`, 'g'), String(v));
    });
    return url;
}

const appName = import.meta.env.VITE_APP_NAME || 'Ondigitalsolution';

// Función para obtener el token CSRF actualizado
const getCsrfToken = () => {
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token) {
        return token.content;
    }
    // Si no hay meta tag, intentar leer de cookies (Laravel usa XSRF-TOKEN)
    const getCookie = (name) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    };
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        return decodeURIComponent(xsrfToken);
    }
    return null;
};

createInertiaApp({
    // Mostrar solo el título provisto por cada página (sin sufijo "- Laravel")
    title: (title) => title,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    // Configurar headers globales para todas las peticiones de Inertia
    progress: {
        color: '#4B5563',
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // --- 2. AQUÍ REGISTRAMOS EL COMPONENTE ---
        app.component('ProductGallery', ProductGallery);

        // Configurar Inertia para manejar errores 419 (CSRF token mismatch)
        router.on('error', (errors) => {
            if (errors.response?.status === 419) {
                // Actualizar el token CSRF desde el meta tag
                const newToken = getCsrfToken();
                if (newToken) {
                    // Actualizar el meta tag si no está actualizado
                    let metaTag = document.head.querySelector('meta[name="csrf-token"]');
                    if (metaTag && metaTag.content !== newToken) {
                        metaTag.setAttribute('content', newToken);
                    }
                    // Recargar la página para obtener el nuevo token
                    router.reload({ only: [] });
                    return;
                }
                // Si no se pudo actualizar, recargar la página completa
                window.location.reload();
            }
        });

        // Actualizar el token CSRF después de cada petición exitosa de Inertia
        // Esto asegura que el token esté siempre actualizado después de login/logout
        router.on('finish', (event) => {
            // Esperar un momento para que el DOM se actualice con el nuevo token
            setTimeout(() => {
                // Actualizar el token desde el meta tag después de cada petición
                const token = getCsrfToken();
                if (token) {
                    // Actualizar el meta tag si es necesario
                    let metaTag = document.head.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        // SIEMPRE actualizar el contenido del meta tag con el token actual
                        metaTag.setAttribute('content', token);
                    }
                    // Actualizar axios
                    if (window.axios) {
                        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                        // También actualizar X-XSRF-TOKEN por si acaso
                        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token;
                    }
                    // También actualizar la función global si existe
                    if (window.updateCsrfToken) {
                        window.updateCsrfToken();
                    }
                }
            }, 150);
        });

        // También actualizar el token antes de cada petición de Inertia
        router.on('start', () => {
            const token = getCsrfToken();
            if (token && window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
        });

        // Mantenemos los redireccionamientos simples para 401/403/419 (las páginas pueden mostrar AlertModal locales)
        if (window.axios) {
            // Interceptor de request para actualizar el token CSRF antes de cada petición
            // CRÍTICO: Este interceptor se ejecuta ANTES de cada petición para asegurar token actualizado
            window.axios.interceptors.request.use(
                (config) => {
                    // SIEMPRE actualizar el token CSRF antes de cada petición
                    // Primero intentar desde el meta tag (más confiable)
                    let token = null;
                    const metaTag = document.head.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        token = metaTag.content;
                        config.headers['X-CSRF-TOKEN'] = token;
                        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                    }
                    
                    // Si no hay meta tag, leer de cookies (Laravel usa XSRF-TOKEN)
                    if (!token) {
                        const getCookie = (name) => {
                            const value = `; ${document.cookie}`;
                            const parts = value.split(`; ${name}=`);
                            if (parts.length === 2) return parts.pop().split(';').shift();
                            return null;
                        };
                        const xsrfToken = getCookie('XSRF-TOKEN');
                        if (xsrfToken) {
                            token = decodeURIComponent(xsrfToken);
                            config.headers['X-XSRF-TOKEN'] = token;
                            window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token;
                        }
                    }
                    
                    return config;
                },
                (error) => Promise.reject(error)
            );

            window.axios.interceptors.response.use(
                (response) => response,
                (error) => {
                    const status = error?.response?.status;
                    
                    // Error 419: CSRF token mismatch
                    // NO reintentar automáticamente aquí, dejar que el código de la página lo maneje
                    if (status === 419) {
                        // Solo actualizar el token si está disponible, pero no reintentar
                        const newToken = error?.response?.headers?.['x-csrf-token'] || 
                                        error?.response?.headers?.['X-CSRF-TOKEN'] ||
                                        document.head.querySelector('meta[name="csrf-token"]')?.content;
                        if (newToken) {
                            // Actualizar el meta tag
                            let metaTag = document.head.querySelector('meta[name="csrf-token"]');
                            if (metaTag) {
                                metaTag.setAttribute('content', newToken);
                            } else {
                                metaTag = document.createElement('meta');
                                metaTag.setAttribute('name', 'csrf-token');
                                metaTag.setAttribute('content', newToken);
                                document.head.appendChild(metaTag);
                            }
                            // Actualizar el header de axios
                            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                        }
                        // Rechazar el error para que el código de la página lo maneje
                        return Promise.reject(error);
                    }
                    
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