import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { router, usePage } from '@inertiajs/vue3';
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

// Función para obtener el token CSRF del meta tag
const getCsrfTokenFromMeta = () => {
    const token = document.head.querySelector('meta[name="csrf-token"]');
    return token ? token.content : null;
};

// Función para obtener el token CSRF de las cookies (Laravel usa XSRF-TOKEN)
const getCsrfTokenFromCookie = () => {
    const getCookie = (name) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    };
    const xsrfToken = getCookie('XSRF-TOKEN');
    return xsrfToken ? decodeURIComponent(xsrfToken) : null;
};

// Función para actualizar el meta tag con un nuevo token
const updateCsrfMetaTag = (token) => {
    if (!token) return;
    let metaTag = document.head.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        metaTag.setAttribute('content', token);
    } else {
        metaTag = document.createElement('meta');
        metaTag.setAttribute('name', 'csrf-token');
        metaTag.setAttribute('content', token);
        document.head.appendChild(metaTag);
    }
    // También actualizar axios por si acaso
    if (window.axios) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token;
    }
};

createInertiaApp({
    // Mostrar solo el título provisto por cada página (sin sufijo "- Laravel")
    title: (title) => title,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    progress: {
        color: '#4B5563',
    },
    // Configurar headers para todas las peticiones de Inertia
    // Inertia lee automáticamente el token CSRF del meta tag 'csrf-token'
    // pero también podemos configurarlo explícitamente aquí
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // --- 2. AQUÍ REGISTRAMOS EL COMPONENTE ---
        app.component('ProductGallery', ProductGallery);

        // CRÍTICO: Configurar Inertia para usar el token CSRF en cada petición
        // Inertia lee el token CSRF del meta tag 'csrf-token' automáticamente
        // Necesitamos asegurarnos de que el meta tag esté siempre actualizado
        
        // Inicializar el token CSRF desde las props al cargar la app
        try {
            const initialPage = usePage();
            if (initialPage?.props?.csrf_token) {
                updateCsrfMetaTag(initialPage.props.csrf_token);
            } else {
                // Fallback: leer del meta tag inicial o cookie
                const tokenFromMeta = getCsrfTokenFromMeta();
                const tokenFromCookie = getCsrfTokenFromCookie();
                const token = tokenFromMeta || tokenFromCookie;
                if (token) {
                    updateCsrfMetaTag(token);
                }
            }
        } catch (error) {
            // Si usePage() no está disponible aún, usar el meta tag o cookie
            const tokenFromMeta = getCsrfTokenFromMeta();
            const tokenFromCookie = getCsrfTokenFromCookie();
            const token = tokenFromMeta || tokenFromCookie;
            if (token) {
                updateCsrfMetaTag(token);
            }
        }
        
        // Actualizar el token CSRF ANTES de cada petición de Inertia
        router.on('start', () => {
            try {
                // Obtener el token más reciente de las props compartidas (si está disponible)
                const page = usePage();
                const csrfTokenFromProps = page?.props?.csrf_token;
                
                if (csrfTokenFromProps) {
                    // Actualizar el meta tag con el token de las props (más confiable)
                    updateCsrfMetaTag(csrfTokenFromProps);
                } else {
                    // Fallback: intentar obtener de cookie (más confiable después de regenerar sesión)
                    const tokenFromCookie = getCsrfTokenFromCookie();
                    if (tokenFromCookie) {
                        updateCsrfMetaTag(tokenFromCookie);
                    } else {
                        // Último fallback: leer del meta tag actual
                        const tokenFromMeta = getCsrfTokenFromMeta();
                        if (tokenFromMeta) {
                            updateCsrfMetaTag(tokenFromMeta);
                        }
                    }
                }
            } catch (error) {
                // Si hay error, intentar obtener de cookie o meta tag
                const tokenFromCookie = getCsrfTokenFromCookie();
                const tokenFromMeta = getCsrfTokenFromMeta();
                const token = tokenFromCookie || tokenFromMeta;
                if (token) {
                    updateCsrfMetaTag(token);
                }
            }
        });

        // Actualizar el token CSRF DESPUÉS de cada respuesta exitosa de Inertia
        router.on('finish', () => {
            try {
                // Obtener el nuevo token de las props compartidas (Laravel lo actualiza en cada respuesta)
                const page = usePage();
                const csrfTokenFromProps = page?.props?.csrf_token;
                
                if (csrfTokenFromProps) {
                    // Actualizar el meta tag con el nuevo token
                    updateCsrfMetaTag(csrfTokenFromProps);
                } else {
                    // Fallback: leer de cookie (Laravel actualiza la cookie XSRF-TOKEN)
                    const tokenFromCookie = getCsrfTokenFromCookie();
                    if (tokenFromCookie) {
                        updateCsrfMetaTag(tokenFromCookie);
                    }
                }
            } catch (error) {
                // Si hay error, intentar obtener de cookie
                const tokenFromCookie = getCsrfTokenFromCookie();
                if (tokenFromCookie) {
                    updateCsrfMetaTag(tokenFromCookie);
                }
            }
        });

        // Manejar errores 419 (CSRF token mismatch)
        router.on('error', (errors) => {
            if (errors.response?.status === 419) {
                try {
                    // Intentar obtener el nuevo token de las props o cookies
                    const page = usePage();
                    const csrfTokenFromProps = page?.props?.csrf_token;
                    
                    if (csrfTokenFromProps) {
                        updateCsrfMetaTag(csrfTokenFromProps);
                        // Reintentar la petición después de actualizar el token
                        router.reload({ only: [] });
                    } else {
                        // Si no hay token disponible, recargar la página completa
                        window.location.reload();
                    }
                } catch (error) {
                    // Si hay error, recargar la página completa
                    window.location.reload();
                }
            }
        });

        // Configurar interceptores de axios para peticiones no-Inertia
        if (window.axios) {
            // Interceptor de request: actualizar token CSRF antes de cada petición axios
            window.axios.interceptors.request.use(
                (config) => {
                    // Obtener el token más reciente del meta tag
                    const token = getCsrfTokenFromMeta() || getCsrfTokenFromCookie();
                    if (token) {
                        config.headers['X-CSRF-TOKEN'] = token;
                        config.headers['X-XSRF-TOKEN'] = token;
                        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token;
                    }
                    return config;
                },
                (error) => Promise.reject(error)
            );

            // Interceptor de response: manejar errores y actualizar token si viene en headers
            window.axios.interceptors.response.use(
                (response) => {
                    // Si la respuesta incluye un nuevo token CSRF en los headers, actualizarlo
                    const newToken = response.headers?.['x-csrf-token'] || 
                                    response.headers?.['X-CSRF-TOKEN'];
                    if (newToken) {
                        updateCsrfMetaTag(newToken);
                    }
                    return response;
                },
                (error) => {
                    const status = error?.response?.status;
                    
                    // Error 419: CSRF token mismatch
                    if (status === 419) {
                        // Intentar obtener nuevo token de headers de respuesta
                        const newToken = error?.response?.headers?.['x-csrf-token'] || 
                                        error?.response?.headers?.['X-CSRF-TOKEN'];
                        if (newToken) {
                            updateCsrfMetaTag(newToken);
                        } else {
                            // Si no hay token en headers, intentar de cookie
                            const tokenFromCookie = getCsrfTokenFromCookie();
                            if (tokenFromCookie) {
                                updateCsrfMetaTag(tokenFromCookie);
                            }
                        }
                        // Rechazar el error para que el código de la página lo maneje
                        return Promise.reject(error);
                    }
                    
                    // Redireccionar en caso de errores de autorización
                    if (status === 403) {
                        try { 
                            window.location.href = route('dashboard'); 
                        } catch (e) { 
                            window.location.href = '/'; 
                        }
                        return Promise.resolve();
                    }
                    if (status === 401) {
                        try {
                            window.location.href = route('login');
                        } catch (e) {
                            window.location.href = '/login';
                        }
                        return Promise.resolve();
                    }
                    return Promise.reject(error);
                }
            );
        }

        app.mount(el);
    },
});