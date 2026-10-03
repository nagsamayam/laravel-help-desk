import { create } from 'zustand';
import { apiClient } from '@/lib/api-client';

export const useAuthStore = create((set, get) => ({
    user: (() => {
        try {
            const raw = localStorage.getItem('auth_user');
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    })(),
    token: localStorage.getItem('auth_token') || null,
    isAuthenticated: !!localStorage.getItem('auth_token'),
    isLoadingProfile: false,

    setAuth: (user, token) => {
        if (token) {
            localStorage.setItem('auth_token', token);
        }
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        }
        set({
            user: user ?? get().user,
            token: token || get().token,
            isAuthenticated: true,
        });
    },

    setToken: (token) => {
        if (token) {
            localStorage.setItem('auth_token', token);
            set({ token, isAuthenticated: true });
        } else {
            localStorage.removeItem('auth_token');
            set({ token: null, isAuthenticated: false });
        }
    },

    setUser: (user) => {
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('auth_user');
        }
        set({ user });
    },

    fetchProfile: async () => {
        const token = get().token || localStorage.getItem('auth_token');
        if (!token) return null;
        try {
            set({ isLoadingProfile: true });
            const res = await apiClient.get('/auth/me');
            const userData = res.data?.data || res.data;
            if (userData && userData.id) {
                get().setUser(userData);
                return userData;
            }
        } catch {
            // Keep existing offline cached user if network fails
        } finally {
            set({ isLoadingProfile: false });
        }
        return get().user;
    },

    logout: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        set({
            user: null,
            token: null,
            isAuthenticated: false,
        });
    },

    hasRole: (roles) => {
        const user = get().user;
        if (!user || !user.role) return false;
        const currentRole = String(user.role).toUpperCase();
        if (Array.isArray(roles)) {
            return roles.map((r) => String(r).toUpperCase()).includes(currentRole);
        }
        return currentRole === String(roles).toUpperCase();
    },

    isAdmin: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return role === 'ADMIN';
    },

    isAgent: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    isCustomer: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return role === 'CUSTOMER';
    },

    canCreateTicket: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'CUSTOMER'].includes(role);
    },

    canManageTicket: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    canViewAuditLogs: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    canChangeTicketState: (ticket) => {
        const user = get().user;
        if (!user) return false;
        const role = String(user.role || '').toUpperCase();
        if (['ADMIN', 'AGENT'].includes(role)) return true;
        if (role === 'CUSTOMER' && ticket) {
            return ticket.customer_id === user.id;
        }
        return false;
    },
}));

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key === 'auth_token') {
            const newToken = event.newValue;
            if (newToken) {
                useAuthStore.setState({ token: newToken, isAuthenticated: true });
            } else {
                useAuthStore.setState({ token: null, user: null, isAuthenticated: false });
            }
        } else if (event.key === 'auth_user') {
            try {
                const newUser = event.newValue ? JSON.parse(event.newValue) : null;
                useAuthStore.setState({ user: newUser });
            } catch {
                // Ignore parse errors
            }
        }
    });

    window.addEventListener('auth:unauthorized', () => {
        useAuthStore.getState().logout();
    });
}
