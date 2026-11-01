import { http } from './http';

/*
 * Every endpoint the dashboard uses, in one place. Each function returns the
 * response body; components never touch axios directly.
 */

export const authApi = {
    me: () => http.get('/api/user').then((r) => r.data),
    login: (credentials) => http.post('/api/login', credentials).then((r) => r.data),
    logout: () => http.post('/api/logout'),
};

export const plantsApi = {
    list: () => http.get('/api/plants').then((r) => r.data),
    get: (id) => http.get(`/api/plants/${id}`).then((r) => r.data),
    create: (payload) => http.post('/api/plants', payload).then((r) => r.data),
    update: (id, payload) => http.patch(`/api/plants/${id}`, payload).then((r) => r.data),
    remove: (id) => http.delete(`/api/plants/${id}`),
    readings: (id, params) => http.get(`/api/plants/${id}/readings`, { params }).then((r) => r.data),
};

export const devicesApi = {
    saveThresholds: (deviceId, thresholds) =>
        http.put(`/api/devices/${deviceId}/thresholds`, { thresholds }).then((r) => r.data),
};

export const alertsApi = {
    list: (params) => http.get('/api/alerts', { params }).then((r) => r.data),
    summary: () => http.get('/api/alerts/summary').then((r) => r.data),
};
