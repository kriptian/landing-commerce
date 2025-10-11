// Helper para construir rutas de forma segura cuando Ziggy no está disponible
export function safeRoute(name, params = {}, fallbackPath = '/') {
    try {
        if (typeof route === 'function') {
            return route(name, params);
        }
    } catch (e) { /* ignore */ }
    let url = String(fallbackPath);
    Object.entries(params || {}).forEach(([key, value]) => {
        url = url.replace(new RegExp(`:${key}\\b|{${key}}`, 'g'), String(value));
    });
    return url;
}


