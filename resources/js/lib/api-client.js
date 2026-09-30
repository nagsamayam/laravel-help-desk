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

        const errorCode = response?.data?.error?.code || (typeof response?.data?.error === 'string' ? response.data.error : null);
        const isInFlight = response?.status === 409 && errorCode === 'IDEMPOTENCY_IN_FLIGHT';

        // Auto retry on 409 In-Flight idempotency collision with Retry-After backoff (up to 3 attempts)
        const maxRetries = 3;
        if (isInFlight && config && (config._retryCount || 0) < maxRetries) {
            config._retryCount = (config._retryCount || 0) + 1;
            const rawHeader = response.headers?.['retry-after'] || response.headers?.get?.('retry-after');
            const parsedSeconds = parseInt(rawHeader, 10);
            const retryAfterSec = (!isNaN(parsedSeconds) && parsedSeconds > 0) ? Math.min(parsedSeconds, 10) : 2;

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
