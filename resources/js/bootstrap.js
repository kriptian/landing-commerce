import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configurar CSRF token desde cookies (Laravel usa XSRF-TOKEN)
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    // Si no hay meta tag, intentar leer de cookies (Laravel usa XSRF-TOKEN)
    const getCookie = (name) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    };
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }
}
