import axios from 'axios';
import { v4 as uuidv4 } from 'uuid';

export const apiClient = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const method = config.method ? config.method.toLowerCase() : '';
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
        if (!config.headers['Idempotency-Key']) {
            config.headers['Idempotency-Key'] = uuidv4();
        }
    }

    return config;
}, (error) => {
    return Promise.reject(error);
});

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { config, response } = error;

        // Auto retry on 409 In-Flight idempotency collision
        if (response?.status === 409 && response?.data?.error === 'IDEMPOTENCY_IN_FLIGHT' && config && !config._retry) {
            config._retry = true;
            const retryAfterSec = parseInt(response.headers['retry-after'], 10) || 2;
            await new Promise((resolve) => setTimeout(resolve, retryAfterSec * 1000));
            return apiClient(config);
        }

        // Auto clear token on 401
        if (response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            if (window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                window.dispatchEvent(new Event('auth:unauthorized'));
            }
        }

        return Promise.reject(error);
    }
);
