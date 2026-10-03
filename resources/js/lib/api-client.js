import axios from 'axios';
import { v4 as uuidv4 } from 'uuid';
import { useAuthStore } from '@/stores/auth-store';

export const apiClient = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token && !config.headers.Authorization && !config.headers.authorization) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const method = config.method ? config.method.toLowerCase() : '';
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
        const url = config.url || '';
        if (!url.includes('/auth/') && !config.headers['Idempotency-Key']) {
            config.headers['Idempotency-Key'] = uuidv4();
        }
    }

    return config;
}, (error) => {
    return Promise.reject(error);
});

let refreshPromise = null;

/**
 * Executes token refresh request, sharing the in-flight promise across all concurrent callers.
 */
export async function refreshAuthToken() {
    if (!refreshPromise) {
        refreshPromise = (async () => {
            const currentToken = localStorage.getItem('auth_token');
            if (!currentToken) {
                throw new Error('No authentication token available to refresh');
            }

            try {
                // Use plain axios instance to bypass apiClient interceptors and avoid recursion
                const response = await axios.post('/api/v1/auth/refresh', {}, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${currentToken}`,
                    },
                });

                const data = response.data?.data || response.data;
                const newToken = data?.access_token || data?.token;
                const user = data?.user;

                if (!newToken) {
                    throw new Error('Invalid token refresh response');
                }

                localStorage.setItem('auth_token', newToken);
                if (user) {
                    localStorage.setItem('auth_user', JSON.stringify(user));
                }

                // Update Zustand auth store
                useAuthStore.getState().setAuth(user, newToken);

                return newToken;
            } catch (err) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('auth_user');
                useAuthStore.getState().logout();
                if (typeof window !== 'undefined' && window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                    window.dispatchEvent(new Event('auth:unauthorized'));
                }
                throw err;
            } finally {
                refreshPromise = null;
            }
        })();
    }

    return refreshPromise;
}

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { config, response } = error;

        // Auto retry on 409 In-Flight idempotency collision with Retry-After backoff (up to 3 attempts)
        const errorCode = response?.data?.error?.code || (typeof response?.data?.error === 'string' ? response.data.error : null);
        const isInFlight = response?.status === 409 && errorCode === 'IDEMPOTENCY_IN_FLIGHT';

        const maxRetries = 3;
        if (isInFlight && config && (config._retryCount || 0) < maxRetries) {
            config._retryCount = (config._retryCount || 0) + 1;
            const rawHeader = response.headers?.['retry-after'] || response.headers?.get?.('retry-after');
            const parsedSeconds = parseInt(rawHeader, 10);
            const retryAfterSec = (!isNaN(parsedSeconds) && parsedSeconds > 0) ? Math.min(parsedSeconds, 10) : 2;

            await new Promise((resolve) => setTimeout(resolve, retryAfterSec * 1000));
            return apiClient(config);
        }

        // Auto refresh access token on 401 Unauthorized
        if (response?.status === 401 && config) {
            const url = config.url || '';
            const isLoginOrRegister = url.includes('/auth/login') || url.includes('/auth/register');
            const isRefreshRequest = url.includes('/auth/refresh');

            if (isLoginOrRegister) {
                return Promise.reject(error);
            }

            if (isRefreshRequest) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('auth_user');
                useAuthStore.getState().logout();
                if (typeof window !== 'undefined' && window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                    window.dispatchEvent(new Event('auth:unauthorized'));
                }
                return Promise.reject(error);
            }

            if (config._retry) {
                // If this request was already retried with a refreshed token and still failed with 401, logout
                localStorage.removeItem('auth_token');
                localStorage.removeItem('auth_user');
                useAuthStore.getState().logout();
                if (typeof window !== 'undefined' && window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                    window.dispatchEvent(new Event('auth:unauthorized'));
                }
                return Promise.reject(error);
            }

            config._retry = true;

            const currentStoredToken = localStorage.getItem('auth_token');
            const authHeader = config.headers?.Authorization || config.headers?.authorization;
            const requestToken = typeof authHeader === 'string' ? authHeader.replace(/^Bearer\s+/i, '') : null;

            // If token in localStorage has already been updated by another concurrent request, retry immediately
            if (currentStoredToken && requestToken && currentStoredToken !== requestToken) {
                config.headers.Authorization = `Bearer ${currentStoredToken}`;
                return apiClient(config);
            }

            try {
                const newToken = await refreshAuthToken();
                config.headers.Authorization = `Bearer ${newToken}`;
                return apiClient(config);
            } catch (refreshError) {
                return Promise.reject(refreshError);
            }
        }

        return Promise.reject(error);
    }
);
