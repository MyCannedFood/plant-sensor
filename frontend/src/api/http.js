import axios from 'axios';

/**
 * One axios instance for the whole app.
 *
 * Sanctum SPA authentication relies on two cookies Laravel sets: the session
 * cookie (HttpOnly, invisible to JS) and XSRF-TOKEN. withCredentials sends
 * them; withXSRFToken copies XSRF-TOKEN into the X-XSRF-TOKEN header even when
 * the API is on another subdomain.
 */
export const http = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let csrfReady = null;

/**
 * Fetch the XSRF-TOKEN cookie once, before the first state-changing request.
 */
export function ensureCsrfCookie() {
    csrfReady ??= http.get('/sanctum/csrf-cookie').catch((error) => {
        csrfReady = null;
        throw error;
    });

    return csrfReady;
}

/**
 * Forget the CSRF cookie state, e.g. after logout regenerated the token.
 */
export function resetCsrfCookie() {
    csrfReady = null;
}

const unauthorizedHandlers = new Set();

/**
 * Register a callback for "the session is gone" (401). The router uses it to
 * send the user back to the login page.
 */
export function onUnauthorized(handler) {
    unauthorizedHandlers.add(handler);

    return () => unauthorizedHandlers.delete(handler);
}

http.interceptors.request.use(async (config) => {
    const method = (config.method || 'get').toLowerCase();

    if (!['get', 'head', 'options'].includes(method) && !config.url.startsWith('/sanctum')) {
        await ensureCsrfCookie();
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;

        // 419 means the CSRF token expired: refresh it and retry once.
        if (status === 419 && !error.config.__retried) {
            resetCsrfCookie();
            await ensureCsrfCookie();

            return http({ ...error.config, __retried: true });
        }

        if (status === 401 && !error.config.url.endsWith('/api/user')) {
            unauthorizedHandlers.forEach((handler) => handler());
        }

        return Promise.reject(error);
    },
);

/**
 * Laravel validation errors as { field: 'first message' }.
 */
export function validationErrors(error) {
    if (error?.response?.status !== 422) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(error.response.data.errors || {}).map(([field, messages]) => [field, messages[0]]),
    );
}

/**
 * A human sentence for any failed request.
 */
export function errorMessage(error) {
    if (!error?.response) {
        return 'Cannot reach the server. Check that the API is running.';
    }

    switch (error.response.status) {
        case 403:
        case 404:
            return 'This item does not exist, or it belongs to another account.';
        case 429:
            return 'Too many requests. Wait a moment, then try again.';
        case 500:
            return 'The server ran into an error. Check the Laravel log.';
        default:
            return error.response.data?.message || `Request failed (${error.response.status}).`;
    }
}
