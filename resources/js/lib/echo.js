import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
}

let echoInstance = null;
let currentToken = null;

/**
 * Get or initialize the Laravel Echo singleton configured for Laravel Reverb.
 */
export function getEcho() {
    const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;

    if (!token) {
        disconnectEcho();
        return null;
    }

    // If token has changed, recreate echo connection with fresh credentials
    if (echoInstance && currentToken !== token) {
        disconnectEcho();
    }

    if (echoInstance) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) {
        console.warn('VITE_REVERB_APP_KEY is not defined. Real-time broadcasting is disabled.');
        return null;
    }

    const host = import.meta.env.VITE_REVERB_HOST || (typeof window !== 'undefined' ? window.location.hostname : 'localhost');
    const port = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : 8080;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    currentToken = token;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    const activeToken = localStorage.getItem('auth_token');
                    if (!activeToken) {
                        callback(new Error('Unauthenticated for broadcast channel'), null);
                        return;
                    }

                    fetch('/broadcasting/auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${activeToken}`,
                        },
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                        .then(async (response) => {
                            if (!response.ok) {
                                const errorData = await response.json().catch(() => ({}));
                                throw new Error(errorData.message || `Channel auth failed: HTTP ${response.status}`);
                            }
                            return response.json();
                        })
                        .then((data) => {
                            callback(null, data);
                        })
                        .catch((error) => {
                            callback(error, null);
                        });
                },
            };
        },
    });

    return echoInstance;
}

/**
 * Disconnect and destroy the active Echo instance.
 */
export function disconnectEcho() {
    if (echoInstance) {
        try {
            echoInstance.disconnect();
        } catch (e) {
            console.error('Error disconnecting Echo:', e);
        }
        echoInstance = null;
        currentToken = null;
    }
}
